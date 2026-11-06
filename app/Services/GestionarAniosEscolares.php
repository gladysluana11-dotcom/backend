<?php

namespace App\Services;

use App\Models\AnioEscolar;
use App\Models\Colegio;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarAniosEscolares
{
    /**
     * @param  array{nombre: string, fecha_inicio: string, fecha_fin: string}  $datos
     */
    public function crear(Usuario $actor, array $datos): AnioEscolar
    {
        $this->validarFechas($datos['fecha_inicio'], $datos['fecha_fin']);

        return DB::transaction(function () use ($actor, $datos): AnioEscolar {
            $this->bloquearColegio($actor);

            return AnioEscolar::query()->create([
                ...$datos,
                'colegio_id' => $actor->colegio_id,
                'activo' => false,
                'cerrado' => false,
            ]);
        }, attempts: 5);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Usuario $actor, AnioEscolar $anio, array $datos): AnioEscolar
    {
        return DB::transaction(function () use ($actor, $anio, $datos): AnioEscolar {
            $this->bloquearColegio($actor);
            $registro = $this->bloquearAnio($actor, $anio);

            if ($registro->cerrado) {
                throw ValidationException::withMessages([
                    'anio_escolar' => ['No se puede modificar un año escolar cerrado.'],
                ]);
            }

            $fechaInicio = $datos['fecha_inicio'] ?? Carbon::parse($registro->fecha_inicio)->toDateString();
            $fechaFin = $datos['fecha_fin'] ?? Carbon::parse($registro->fecha_fin)->toDateString();
            $this->validarFechas($fechaInicio, $fechaFin);
            $registro->fill($datos)->save();

            return $registro->refresh();
        }, attempts: 5);
    }

    public function abrir(Usuario $actor, AnioEscolar $anio): AnioEscolar
    {
        return DB::transaction(function () use ($actor, $anio): AnioEscolar {
            $this->bloquearColegio($actor);
            $registro = $this->bloquearAnio($actor, $anio);

            if ($registro->cerrado) {
                throw ValidationException::withMessages([
                    'anio_escolar' => ['Un año escolar cerrado no se puede volver a abrir.'],
                ]);
            }

            if ($registro->fecha_inicio === null || $registro->fecha_fin === null) {
                throw ValidationException::withMessages([
                    'anio_escolar' => ['El año escolar requiere fechas válidas para poder abrirse.'],
                ]);
            }

            $otroActivo = AnioEscolar::query()
                ->where('colegio_id', $actor->colegio_id)
                ->where('activo', true)
                ->whereKeyNot($registro->getKey())
                ->exists();

            if ($otroActivo) {
                throw ValidationException::withMessages([
                    'anio_escolar' => ['Ya existe otro año escolar activo en este colegio.'],
                ]);
            }

            $registro->forceFill(['activo' => true, 'cerrado' => false])->save();

            return $registro->refresh();
        }, attempts: 5);
    }

    public function cerrar(Usuario $actor, AnioEscolar $anio): AnioEscolar
    {
        return DB::transaction(function () use ($actor, $anio): AnioEscolar {
            $this->bloquearColegio($actor);
            $registro = $this->bloquearAnio($actor, $anio);
            $registro->forceFill(['activo' => false, 'cerrado' => true])->save();

            return $registro->refresh();
        }, attempts: 5);
    }

    private function bloquearColegio(Usuario $actor): void
    {
        if ($actor->colegio_id === null) {
            throw new ModelNotFoundException;
        }

        Colegio::query()->whereKey($actor->colegio_id)->lockForUpdate()->firstOrFail();
    }

    private function bloquearAnio(Usuario $actor, AnioEscolar $anio): AnioEscolar
    {
        return AnioEscolar::query()
            ->where('colegio_id', $actor->colegio_id)
            ->lockForUpdate()
            ->findOrFail($anio->getKey());
    }

    private function validarFechas(string $fechaInicio, string $fechaFin): void
    {
        if ($fechaFin <= $fechaInicio) {
            throw ValidationException::withMessages([
                'fecha_fin' => ['La fecha de fin debe ser posterior a la fecha de inicio.'],
            ]);
        }
    }
}
