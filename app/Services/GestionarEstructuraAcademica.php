<?php

namespace App\Services;

use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\Curso;
use App\Models\Grado;
use App\Models\Nivel;
use App\Models\Periodo;
use App\Models\Seccion;
use App\Models\UnidadDidactica;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarEstructuraAcademica
{
    /**
     * @template TModel of Model
     *
     * @param  TModel  $registro
     * @param  array<string, mixed>  $datos
     * @return TModel
     */
    public function crear(Usuario $actor, Model $registro, array $datos): Model
    {
        return DB::transaction(function () use ($actor, $registro, $datos): Model {
            $this->validarContexto($actor, $registro, $datos);
            $registro->fill($datos);
            $registro->forceFill(['colegio_id' => $actor->colegio_id])->save();

            return $registro->refresh();
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Usuario $actor, Model $modelo, array $datos): Model
    {
        return DB::transaction(function () use ($actor, $modelo, $datos): Model {
            $registro = $modelo->newQuery()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($modelo->getKey());

            $this->validarSinHistorialCerrado($actor, $registro);
            $this->validarContexto($actor, $registro, $datos);
            $registro->fill($datos)->save();

            return $registro->refresh();
        }, attempts: 5);
    }

    public function desactivar(Usuario $actor, Model $modelo): Model
    {
        return DB::transaction(function () use ($actor, $modelo): Model {
            $registro = $modelo->newQuery()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($modelo->getKey());

            $this->validarSinHistorialCerrado($actor, $registro);
            $registro->forceFill(['activo' => false])->save();

            return $registro->refresh();
        }, attempts: 5);
    }

    private function validarContexto(Usuario $actor, Model $registro, array $datos): void
    {
        if ($registro instanceof Periodo) {
            $anio = AnioEscolar::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($datos['anio_escolar_id'] ?? $registro->anio_escolar_id);
            $this->validarAnioAbierto($anio);
            $inicio = $datos['fecha_inicio'] ?? Carbon::parse($registro->fecha_inicio)->toDateString();
            $fin = $datos['fecha_fin'] ?? Carbon::parse($registro->fecha_fin)->toDateString();
            $this->validarFechasDentroDelAnio($anio, $inicio, $fin);
        }

        if ($registro instanceof UnidadDidactica) {
            $periodo = Periodo::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($datos['periodo_id'] ?? $registro->periodo_id);
            $anio = AnioEscolar::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($periodo->anio_escolar_id);
            $this->validarAnioAbierto($anio);
            $inicio = $datos['fecha_inicio'] ?? Carbon::parse($registro->fecha_inicio)->toDateString();
            $fin = $datos['fecha_fin'] ?? Carbon::parse($registro->fecha_fin)->toDateString();
            $this->validarFechasDentroDelAnio($anio, $inicio, $fin);
            $this->validarSinCruceDeUnidades($actor, $registro, $periodo, $inicio, $fin);
        }

        if ($registro instanceof Seccion) {
            $anioId = $datos['anio_escolar_id'] ?? $registro->anio_escolar_id;
            $anio = AnioEscolar::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($anioId);
            $this->validarAnioAbierto($anio);
        }

        if ($registro instanceof AsignacionDocente) {
            $this->validarAsignacion($actor, $registro, $datos);
        }
    }

    private function validarSinHistorialCerrado(Usuario $actor, Model $registro): void
    {
        $tieneEstructuraCerrada = match (true) {
            $registro instanceof Nivel => Seccion::query()
                ->where('colegio_id', $actor->colegio_id)
                ->whereHas('grado', fn ($query) => $query->where('nivel_id', $registro->getKey()))
                ->whereHas('anioEscolar', fn ($query) => $query->where('cerrado', true))
                ->exists(),
            $registro instanceof Grado => Seccion::query()
                ->where('colegio_id', $actor->colegio_id)
                ->where('grado_id', $registro->getKey())
                ->whereHas('anioEscolar', fn ($query) => $query->where('cerrado', true))
                ->exists(),
            $registro instanceof Curso => AsignacionDocente::query()
                ->where('colegio_id', $actor->colegio_id)
                ->where('curso_id', $registro->getKey())
                ->whereHas('anioEscolar', fn ($query) => $query->where('cerrado', true))
                ->exists(),
            $registro instanceof Seccion => AnioEscolar::query()
                ->whereKey($registro->anio_escolar_id)
                ->where('colegio_id', $actor->colegio_id)
                ->where('cerrado', true)
                ->exists(),
            $registro instanceof Periodo => AnioEscolar::query()
                ->whereKey($registro->anio_escolar_id)
                ->where('colegio_id', $actor->colegio_id)
                ->where('cerrado', true)
                ->exists(),
            $registro instanceof UnidadDidactica => Periodo::query()
                ->whereKey($registro->periodo_id)
                ->where('colegio_id', $actor->colegio_id)
                ->whereHas('anioEscolar', fn ($query) => $query->where('cerrado', true))
                ->exists(),
            $registro instanceof AsignacionDocente => AnioEscolar::query()
                ->whereKey($registro->anio_escolar_id)
                ->where('colegio_id', $actor->colegio_id)
                ->where('cerrado', true)
                ->exists(),
            default => false,
        };

        if ($tieneEstructuraCerrada) {
            throw ValidationException::withMessages([
                'estructura' => ['No se puede modificar la estructura de un año escolar cerrado.'],
            ]);
        }
    }

    private function validarAnioAbierto(AnioEscolar $anio): void
    {
        if ($anio->cerrado) {
            throw ValidationException::withMessages([
                'anio_escolar_id' => ['No se puede modificar la estructura de un año escolar cerrado.'],
            ]);
        }
    }

    private function validarFechasDentroDelAnio(
        AnioEscolar $anio,
        ?string $fechaInicio,
        ?string $fechaFin,
    ): void {
        if (
            $fechaInicio === null
            || $fechaFin === null
            || $fechaFin <= $fechaInicio
            || $anio->fecha_inicio === null
            || $anio->fecha_fin === null
            || $fechaInicio < Carbon::parse($anio->fecha_inicio)->toDateString()
            || $fechaFin > Carbon::parse($anio->fecha_fin)->toDateString()
        ) {
            throw ValidationException::withMessages([
                'fecha_inicio' => ['Las fechas deben estar dentro del año escolar y la fecha de fin debe ser posterior al inicio.'],
            ]);
        }
    }

    private function validarSinCruceDeUnidades(
        Usuario $actor,
        Model $registro,
        Periodo $periodo,
        ?string $fechaInicio,
        ?string $fechaFin,
    ): void {
        if ($fechaInicio === null || $fechaFin === null) {
            return;
        }

        $consulta = UnidadDidactica::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('periodo_id', $periodo->getKey())
            ->whereDate('fecha_inicio', '<=', $fechaFin)
            ->whereDate('fecha_fin', '>=', $fechaInicio);

        if ($registro->exists) {
            $consulta->whereKeyNot($registro->getKey());
        }

        if ($consulta->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'fecha_inicio' => ['Las unidades didácticas del mismo periodo no pueden solaparse.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarAsignacion(Usuario $actor, AsignacionDocente $registro, array $datos): void
    {
        $anioId = $datos['anio_escolar_id'] ?? $registro->anio_escolar_id;
        $cursoId = $datos['curso_id'] ?? $registro->curso_id;
        $seccionId = $datos['seccion_id'] ?? $registro->seccion_id;
        $docenteId = $datos['docente_id'] ?? $registro->docente_id;

        $anio = AnioEscolar::query()
            ->where('colegio_id', $actor->colegio_id)
            ->lockForUpdate()
            ->findOrFail($anioId);
        $this->validarAnioAbierto($anio);

        $curso = Curso::query()
            ->where('colegio_id', $actor->colegio_id)
            ->findOrFail($cursoId);
        $seccion = Seccion::query()
            ->where('colegio_id', $actor->colegio_id)
            ->findOrFail($seccionId);

        if (
            $seccion->anio_escolar_id !== $anio->id
            || $seccion->grado_id !== $curso->grado_id
            || $curso->nivel_id !== Grado::query()->whereKey($seccion->grado_id)->value('nivel_id')
        ) {
            throw ValidationException::withMessages([
                'seccion_id' => ['El curso debe pertenecer al mismo grado y año escolar de la sección.'],
            ]);
        }

        $docente = Usuario::query()
            ->where('colegio_id', $actor->colegio_id)
            ->findOrFail($docenteId);
        $equipoAnterior = getPermissionsTeamId();
        setPermissionsTeamId($actor->colegio_id);

        try {
            $esDocente = $docente->hasRole('Docente');
        } finally {
            setPermissionsTeamId($equipoAnterior);
        }

        if (! $esDocente) {
            throw ValidationException::withMessages([
                'docente_id' => ['El usuario seleccionado no tiene el rol Docente en este colegio.'],
            ]);
        }

        $duplicada = AsignacionDocente::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('anio_escolar_id', $anioId)
            ->where('curso_id', $cursoId)
            ->where('seccion_id', $seccionId)
            ->when($registro->exists, fn ($query) => $query->whereKeyNot($registro->getKey()))
            ->exists();

        if ($duplicada) {
            throw ValidationException::withMessages([
                'curso_id' => ['Ya existe una asignación para este curso, sección y año escolar.'],
            ]);
        }
    }
}
