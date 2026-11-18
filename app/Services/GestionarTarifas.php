<?php

namespace App\Services;

use App\Models\AnioEscolar;
use App\Models\ConfiguracionColegio;
use App\Models\Grado;
use App\Models\Tarifa;
use App\Models\Usuario;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarTarifas
{
    public function __construct(private RegistrarAuditoria $auditoria) {}

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crear(Usuario $actor, array $datos): Tarifa
    {
        try {
            return DB::transaction(function () use ($actor, $datos): Tarifa {
                $anio = AnioEscolar::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->findOrFail($datos['anio_escolar_id']);
                $this->validarAnioAbierto($anio);

                Grado::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->findOrFail($datos['grado_id']);

                $configuracion = ConfiguracionColegio::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->firstOrFail();
                $numeroCuotas = $datos['numero_cuotas'] ?? $configuracion->numero_cuotas_pension;
                $diaVencimiento = $datos['dia_vencimiento'] ?? $configuracion->dia_vencimiento;

                if ($numeroCuotas < 1 || $numeroCuotas > 12) {
                    throw ValidationException::withMessages([
                        'numero_cuotas' => ['La configuración debe indicar entre 1 y 12 cuotas de pensión.'],
                    ]);
                }

                if ($diaVencimiento < 1 || $diaVencimiento > 28) {
                    throw ValidationException::withMessages([
                        'dia_vencimiento' => ['La configuración debe indicar un día de vencimiento entre 1 y 28.'],
                    ]);
                }

                return Tarifa::query()->create([
                    'colegio_id' => $actor->colegio_id,
                    'anio_escolar_id' => $anio->id,
                    'grado_id' => $datos['grado_id'],
                    'monto_matricula' => $datos['monto_matricula'],
                    'monto_pension' => $datos['monto_pension'],
                    'numero_cuotas' => $numeroCuotas,
                    'dia_vencimiento' => $diaVencimiento,
                    'mes_primera_pension' => $datos['mes_primera_pension'] ?? 3,
                ]);
            }, attempts: 5);
        } catch (QueryException $exception) {
            $this->rethrowUniqueTarifa($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Usuario $actor, Tarifa $modelo, array $datos): Tarifa
    {
        try {
            return DB::transaction(function () use ($actor, $modelo, $datos): Tarifa {
                $tarifa = Tarifa::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->findOrFail($modelo->id);
                $anioAnterior = AnioEscolar::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->findOrFail($tarifa->anio_escolar_id);
                $this->validarAnioAbierto($anioAnterior);

                $anioId = $datos['anio_escolar_id'] ?? $tarifa->anio_escolar_id;
                $gradoId = $datos['grado_id'] ?? $tarifa->grado_id;
                $anioNuevo = AnioEscolar::query()
                    ->where('colegio_id', $actor->colegio_id)
                    ->lockForUpdate()
                    ->findOrFail($anioId);
                $this->validarAnioAbierto($anioNuevo);
                Grado::query()->where('colegio_id', $actor->colegio_id)->findOrFail($gradoId);

                $tarifa->fill($datos)->save();

                return $tarifa->refresh();
            }, attempts: 5);
        } catch (QueryException $exception) {
            $this->rethrowUniqueTarifa($exception);
        }
    }

    public function eliminar(Usuario $actor, Tarifa $modelo): void
    {
        DB::transaction(function () use ($actor, $modelo): void {
            $tarifa = Tarifa::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($modelo->id);
            $anio = AnioEscolar::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($tarifa->anio_escolar_id);
            $this->validarAnioAbierto($anio);

            $valoresAnteriores = [
                'anio_escolar_id' => $tarifa->anio_escolar_id,
                'grado_id' => $tarifa->grado_id,
                'monto_matricula' => $tarifa->monto_matricula,
                'monto_pension' => $tarifa->monto_pension,
                'numero_cuotas' => $tarifa->numero_cuotas,
                'dia_vencimiento' => $tarifa->dia_vencimiento,
                'mes_primera_pension' => $tarifa->mes_primera_pension,
            ];
            $tarifa->delete();

            $this->auditoria->registrar(
                'tarifas.eliminada',
                'tarifas',
                (int) $tarifa->id,
                valoresAnteriores: $valoresAnteriores,
                usuario: $actor,
                colegioId: (int) $actor->colegio_id,
            );
        }, attempts: 5);
    }

    private function validarAnioAbierto(AnioEscolar $anio): void
    {
        if ($anio->cerrado) {
            throw ValidationException::withMessages([
                'anio_escolar_id' => ['No se pueden modificar tarifas de un año escolar cerrado.'],
            ]);
        }
    }

    private function rethrowUniqueTarifa(QueryException $exception): never
    {
        if (str_contains($exception->getMessage(), 'tarifas_colegio_anio_grado_unique')) {
            throw ValidationException::withMessages([
                'grado_id' => ['Ya existe una tarifa para este año escolar y grado.'],
            ]);
        }

        throw $exception;
    }
}
