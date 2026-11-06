<?php

namespace App\Services;

use App\Events\MatriculaConfirmada;
use App\Events\MatriculaRetirada;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\DocumentoRequerido;
use App\Models\Grado;
use App\Models\HistorialMatricula;
use App\Models\Matricula;
use App\Models\MatriculaDocumento;
use App\Models\Seccion;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarMatriculas
{
    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Usuario $actor, array $datos): Matricula
    {
        try {
            return DB::transaction(function () use ($actor, $datos): Matricula {
                $alumno = Alumno::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->findOrFail($datos['alumno_id']);
                $this->validarResponsablePago($actor, $alumno);

                $anio = AnioEscolar::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->where('cerrado', false)
                    ->lockForUpdate()
                    ->findOrFail($datos['anio_escolar_id']);
                $grado = Grado::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->findOrFail($datos['grado_id']);
                $seccion = Seccion::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->findOrFail($datos['seccion_id']);

                if (
                    ! $grado->activo
                    || ! $seccion->activo
                    || $seccion->anio_escolar_id !== $anio->id
                    || $seccion->grado_id !== $grado->id
                ) {
                    throw ValidationException::withMessages([
                        'seccion_id' => ['Selecciona una sección activa que corresponda al grado y año escolar.'],
                    ]);
                }

                $yaExiste = Matricula::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->where('alumno_id', $alumno->id)
                    ->where('anio_escolar_id', $anio->id)
                    ->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado'])
                    ->exists();

                if ($yaExiste) {
                    throw ValidationException::withMessages([
                        'alumno_id' => ['El alumno ya tiene una matrícula vigente para este año escolar.'],
                    ]);
                }

                $excesoAutorizado = $this->validarVacante($actor, $seccion, $datos);
                $estadoSolicitado = $this->estadoInicial($actor, $datos['estado'] ?? 'pre_inscrito');
                $matricula = Matricula::query()->create([
                    'colegio_id' => $actor->colegio_id,
                    'alumno_id' => $alumno->id,
                    'anio_escolar_id' => $anio->id,
                    'grado_id' => $grado->id,
                    'seccion_id' => $seccion->id,
                    'anio_escolar' => $anio->nombre,
                    'grado' => $grado->nombre,
                    'seccion' => $seccion->nombre,
                    'estado' => $estadoSolicitado,
                    'fecha_matricula' => $datos['fecha_matricula'],
                    'usuario_id' => $actor->id,
                    'exceso_vacantes_motivo' => $excesoAutorizado ? $datos['motivo_exceso'] : null,
                    'exceso_vacantes_autorizado_por_id' => $excesoAutorizado ? $actor->id : null,
                ]);

                $this->sincronizarDocumentos($actor, $matricula);
                $this->registrarHistorial(
                    $actor,
                    $matricula,
                    'creada',
                    estadoNuevo: $matricula->estado,
                );

                if ($excesoAutorizado) {
                    $this->auditarExceso($actor, $matricula, $seccion, $datos['motivo_exceso']);
                }

                if ($matricula->estado === 'matriculado') {
                    MatriculaConfirmada::dispatch($matricula);
                }

                return $matricula->load(['alumno', 'documentos.documentoRequerido']);
            }, attempts: 5);
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'matriculas_vigente_por_alumno_anio_unique')) {
                throw ValidationException::withMessages([
                    'alumno_id' => ['El alumno ya tiene una matrícula vigente para este año escolar.'],
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function cambiarEstado(Usuario $actor, Matricula $modelo, array $datos): Matricula
    {
        return DB::transaction(function () use ($actor, $modelo, $datos): Matricula {
            $matricula = $this->matriculaDelColegio($actor, $modelo, lock: true);
            $estadoAnterior = $matricula->estado;
            $estadoSolicitado = $datos['estado'];

            if ($estadoSolicitado === 'matriculado') {
                $this->validarResponsablePago(
                    $actor,
                    Alumno::query()->where('colegio_id', $actor->colegio_id)->findOrFail($matricula->alumno_id),
                );
                $this->sincronizarDocumentos($actor, $matricula);
                $estadoSolicitado = $this->estadoSegunDocumentos($matricula, $estadoSolicitado);
            }

            if ($estadoSolicitado === $estadoAnterior) {
                return $matricula->load(['alumno', 'documentos.documentoRequerido']);
            }

            $matricula->forceFill([
                'estado' => $estadoSolicitado,
                'motivo' => $datos['motivo'] ?? null,
                'fecha_retiro' => $estadoSolicitado === 'retirado' ? $datos['fecha_retiro'] : null,
            ])->save();

            $this->registrarHistorial(
                $actor,
                $matricula,
                $estadoSolicitado === 'retirado' ? 'retirada' : 'estado_cambiado',
                estadoAnterior: $estadoAnterior,
                estadoNuevo: $estadoSolicitado,
                motivo: $datos['motivo'] ?? null,
                fecha: $datos['fecha_retiro'] ?? now(),
            );

            if ($estadoSolicitado === 'retirado') {
                MatriculaRetirada::dispatch($matricula);
            } elseif ($estadoAnterior !== 'matriculado' && $estadoSolicitado === 'matriculado') {
                MatriculaConfirmada::dispatch($matricula);
            }

            return $matricula->load(['alumno', 'documentos.documentoRequerido']);
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function cambiarSeccion(Usuario $actor, Matricula $modelo, array $datos): Matricula
    {
        return DB::transaction(function () use ($actor, $modelo, $datos): Matricula {
            $matricula = $this->matriculaDelColegio($actor, $modelo, lock: true);
            if (! in_array($matricula->estado, ['pre_inscrito', 'pendiente_documentos', 'matriculado'], true)) {
                throw ValidationException::withMessages([
                    'matricula' => ['Solo se puede cambiar de sección una matrícula vigente.'],
                ]);
            }

            $anio = AnioEscolar::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($matricula->anio_escolar_id);

            if ($anio->cerrado) {
                throw ValidationException::withMessages([
                    'anio_escolar_id' => ['No se puede cambiar de sección en un año escolar cerrado.'],
                ]);
            }

            $seccion = Seccion::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($datos['seccion_id']);
            $grado = Grado::query()
                ->where('colegio_id', $actor->colegio_id)
                ->findOrFail($datos['grado_id']);

            if (
                ! $seccion->activo
                || ! $grado->activo
                || $seccion->anio_escolar_id !== $matricula->anio_escolar_id
                || $seccion->grado_id !== $grado->id
                || $matricula->seccion_id === $seccion->id
            ) {
                throw ValidationException::withMessages([
                    'seccion_id' => ['Selecciona una sección activa distinta del mismo año escolar y grado.'],
                ]);
            }

            $excesoAutorizado = $this->validarVacante($actor, $seccion, $datos, $matricula->id);
            $gradoAnterior = $matricula->grado_id;
            $seccionAnterior = $matricula->seccion_id;
            $matricula->forceFill([
                'grado_id' => $grado->id,
                'seccion_id' => $seccion->id,
                'grado' => $grado->nombre,
                'seccion' => $seccion->nombre,
                'motivo' => $datos['motivo'],
                'exceso_vacantes_motivo' => $excesoAutorizado
                    ? $datos['motivo_exceso']
                    : $matricula->exceso_vacantes_motivo,
                'exceso_vacantes_autorizado_por_id' => $excesoAutorizado
                    ? $actor->id
                    : $matricula->exceso_vacantes_autorizado_por_id,
            ])->save();

            $this->registrarHistorial(
                $actor,
                $matricula,
                'seccion_cambiada',
                gradoAnteriorId: $gradoAnterior,
                seccionAnteriorId: $seccionAnterior,
                gradoNuevoId: $grado->id,
                seccionNuevaId: $seccion->id,
                motivo: $datos['motivo'],
                fecha: $datos['fecha'],
            );

            if ($excesoAutorizado) {
                $this->auditarExceso($actor, $matricula, $seccion, $datos['motivo_exceso']);
            }

            return $matricula->load(['alumno', 'documentos.documentoRequerido']);
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarDocumento(
        Usuario $actor,
        MatriculaDocumento $modelo,
        array $datos,
    ): MatriculaDocumento {
        return DB::transaction(function () use ($actor, $modelo, $datos): MatriculaDocumento {
            $documento = MatriculaDocumento::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($modelo->id);
            $documento->forceFill([
                'estado' => $datos['estado'],
                'fecha_entrega' => $datos['estado'] === 'entregado'
                    ? ($datos['fecha_entrega'] ?? now()->toDateString())
                    : null,
            ])->save();

            return $documento->load('documentoRequerido');
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardarDocumentoRequerido(
        Usuario $actor,
        ?DocumentoRequerido $modelo,
        array $datos,
    ): DocumentoRequerido {
        return DB::transaction(function () use ($actor, $modelo, $datos): DocumentoRequerido {
            if ($modelo === null) {
                $documento = new DocumentoRequerido;
                $documento->fill($datos);
                $documento->forceFill(['colegio_id' => $actor->colegio_id])->save();
            } else {
                $documento = DocumentoRequerido::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->findOrFail($modelo->id);
                $documento->fill($datos)->save();
            }

            if ($documento->activo) {
                $matriculas = Matricula::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado'])
                    ->get();

                foreach ($matriculas as $matricula) {
                    MatriculaDocumento::query()->firstOrCreate([
                        'colegio_id' => $actor->colegio_id,
                        'matricula_id' => $matricula->id,
                        'documento_requerido_id' => $documento->id,
                    ], ['estado' => 'pendiente']);
                }
            }

            return $documento->refresh();
        }, attempts: 5);
    }

    private function validarResponsablePago(Usuario $actor, Alumno $alumno): void
    {
        $tieneResponsable = DB::table('alumno_apoderado')
            ->where('colegio_id', $actor->colegio_id)
            ->where('alumno_id', $alumno->id)
            ->where('responsable_pago', true)
            ->exists();

        if (! $tieneResponsable) {
            throw ValidationException::withMessages([
                'alumno_id' => ['El alumno debe tener al menos un apoderado responsable de pago antes de matricularse.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function validarVacante(
        Usuario $actor,
        Seccion $seccion,
        array $datos,
        ?int $excluirMatriculaId = null,
    ): bool {
        $matriculados = Matricula::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('seccion_id', $seccion->id)
            ->where('anio_escolar_id', $seccion->anio_escolar_id)
            ->whereIn('estado', ['pre_inscrito', 'pendiente_documentos', 'matriculado'])
            ->when($excluirMatriculaId !== null, fn ($query) => $query->where('id', '!=', $excluirMatriculaId))
            ->count();

        if ($matriculados < $seccion->capacidad) {
            return false;
        }

        if (! ($datos['autorizar_exceso'] ?? false)) {
            throw ValidationException::withMessages([
                'seccion_id' => ['La sección no tiene vacantes disponibles.'],
            ]);
        }

        if (! $actor->can('authorizeOverCapacity', Matricula::class)) {
            abort(403, 'Solo el Director puede autorizar un exceso de vacantes.');
        }

        if (trim((string) ($datos['motivo_exceso'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'motivo_exceso' => ['El motivo es obligatorio para autorizar el exceso de vacantes.'],
            ]);
        }

        return true;
    }

    private function sincronizarDocumentos(Usuario $actor, Matricula $matricula): void
    {
        $documentos = DocumentoRequerido::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('activo', true)
            ->get();

        foreach ($documentos as $documento) {
            MatriculaDocumento::query()->firstOrCreate([
                'colegio_id' => $actor->colegio_id,
                'matricula_id' => $matricula->id,
                'documento_requerido_id' => $documento->id,
            ], ['estado' => 'pendiente']);
        }
    }

    private function estadoSegunDocumentos(Matricula $matricula, string $estadoSolicitado): string
    {
        if ($estadoSolicitado !== 'matriculado') {
            return $estadoSolicitado;
        }

        $tienePendientes = DocumentoRequerido::query()
            ->where('colegio_id', $matricula->colegio_id)
            ->where('activo', true)
            ->where('obligatorio', true)
            ->whereDoesntHave('matriculaDocumentos', fn ($query) => $query
                ->where('matricula_id', $matricula->id)
                ->where('estado', 'entregado'))
            ->exists();

        return $tienePendientes ? 'pendiente_documentos' : 'matriculado';
    }

    private function estadoInicial(Usuario $actor, string $estadoSolicitado): string
    {
        if ($estadoSolicitado !== 'matriculado') {
            return $estadoSolicitado;
        }

        return DocumentoRequerido::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('activo', true)
            ->where('obligatorio', true)
            ->exists()
            ? 'pendiente_documentos'
            : 'matriculado';
    }

    private function registrarHistorial(
        Usuario $actor,
        Matricula $matricula,
        string $evento,
        ?int $gradoAnteriorId = null,
        ?int $seccionAnteriorId = null,
        ?int $gradoNuevoId = null,
        ?int $seccionNuevaId = null,
        ?string $estadoAnterior = null,
        ?string $estadoNuevo = null,
        ?string $motivo = null,
        mixed $fecha = null,
    ): void {
        HistorialMatricula::query()->create([
            'colegio_id' => $actor->colegio_id,
            'matricula_id' => $matricula->id,
            'evento' => $evento,
            'grado_anterior_id' => $gradoAnteriorId,
            'seccion_anterior_id' => $seccionAnteriorId,
            'grado_nuevo_id' => $gradoNuevoId,
            'seccion_nueva_id' => $seccionNuevaId,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
            'motivo' => $motivo,
            'fecha' => $fecha ?? now(),
            'usuario_id' => $actor->id,
        ]);
    }

    private function auditarExceso(
        Usuario $actor,
        Matricula $matricula,
        Seccion $seccion,
        string $motivo,
    ): void {
        app(RegistrarAuditoria::class)->registrar(
            'matriculas.exceso_vacantes_autorizado',
            'matriculas',
            $matricula->id,
            valoresNuevos: [
                'seccion_id' => $seccion->id,
                'capacidad' => $seccion->capacidad,
                'motivo' => $motivo,
                'autorizado_por_id' => $actor->id,
            ],
            usuario: $actor,
            colegioId: $actor->colegio_id,
        );
    }

    private function matriculaDelColegio(Usuario $actor, Matricula $matricula, bool $lock = false): Matricula
    {
        $consulta = Matricula::query()->where('colegio_id', $actor->colegio_id);

        if ($lock) {
            $consulta->lockForUpdate();
        }

        return $consulta->findOrFail($matricula->id);
    }
}
