<?php

namespace App\Services;

use App\Exceptions\ConflictoIdempotencia;
use App\Http\Resources\Api\V1\PagoResource;
use App\Models\AplicacionPago;
use App\Models\ClaveIdempotencia;
use App\Models\ConfiguracionColegio;
use App\Models\Cuota;
use App\Models\Matricula;
use App\Models\Pago;
use App\Models\SecuenciaConstancia;
use App\Models\Usuario;
use App\Support\Monto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrarPago
{
    public function __construct(private EstadoDeCuota $estadoDeCuota) {}

    /**
     * @param  array<string, mixed>  $datos
     * @return array{respuesta: array<string, mixed>}
     */
    public function registrar(Usuario $actor, array $datos, string $clave): array
    {
        return DB::transaction(function () use ($actor, $datos, $clave): array {
            $colegioId = (int) $actor->colegio_id;
            $configuracion = ConfiguracionColegio::query()
                ->where('colegio_id', $colegioId)
                ->lockForUpdate()
                ->firstOrFail();
            ClaveIdempotencia::query()
                ->where('colegio_id', $colegioId)
                ->where('vence_en', '<=', now())
                ->delete();
            $claveExistente = ClaveIdempotencia::query()
                ->where('colegio_id', $colegioId)
                ->where('usuario_id', $actor->id)
                ->where('clave', $clave)
                ->lockForUpdate()
                ->first();

            if ($claveExistente !== null && $claveExistente->vence_en->isFuture()) {
                if (! hash_equals($claveExistente->hash_cuerpo, $this->hashCuerpo($datos))) {
                    throw new ConflictoIdempotencia;
                }

                return ['respuesta' => $claveExistente->respuesta];
            }

            $claveExistente?->delete();
            $serie = $configuracion->serie_recibos;

            if ($serie === '') {
                throw ValidationException::withMessages([
                    'configuracion' => 'El colegio debe tener una serie de constancias configurada.',
                ]);
            }

            $registroIdempotencia = ClaveIdempotencia::query()->create([
                'colegio_id' => $colegioId,
                'usuario_id' => $actor->id,
                'clave' => $clave,
                'hash_cuerpo' => $this->hashCuerpo($datos),
                'respuesta' => [],
                'vence_en' => now()->addDay(),
            ]);

            $matricula = Matricula::query()
                ->where('colegio_id', $colegioId)
                ->whereKey($datos['matricula_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $cuotas = $this->bloquearCuotas($colegioId, $matricula, $datos['aplicaciones'] ?? []);

            if ($cuotas->isEmpty()) {
                throw ValidationException::withMessages([
                    'matricula_id' => 'La matrícula no tiene cuotas disponibles para aplicar el pago.',
                ]);
            }

            $estados = $this->estadoDeCuota->calcularVarias($cuotas);
            $montoCentimos = Monto::aCentimos($datos['monto']);
            $asignaciones = $this->asignarMontos($cuotas, $estados, $datos['aplicaciones'] ?? [], $montoCentimos);

            $secuencia = SecuenciaConstancia::query()
                ->where('colegio_id', $colegioId)
                ->where('serie', $serie)
                ->lockForUpdate()
                ->first();

            if ($secuencia === null) {
                $secuencia = SecuenciaConstancia::query()->create([
                    'colegio_id' => $colegioId,
                    'serie' => $serie,
                    'ultimo_numero' => 0,
                ]);
            }

            $numeroConstancia = $secuencia->ultimo_numero + 1;
            $secuencia->update(['ultimo_numero' => $numeroConstancia]);
            $numeroFormateado = $serie.'-'.str_pad((string) $numeroConstancia, 6, '0', STR_PAD_LEFT);
            $pago = Pago::query()->create([
                'colegio_id' => $colegioId,
                'matricula_id' => $matricula->id,
                'alumno_id' => $matricula->alumno_id,
                'usuario_id' => $actor->id,
                'fecha_pago' => $datos['fecha_pago'],
                'monto' => Monto::desdeCentimos($montoCentimos),
                'medio' => $datos['medio'],
                'numero_operacion' => $datos['numero_operacion'] ?? null,
                'observacion' => $datos['observacion'] ?? null,
                'estado' => 'vigente',
                'serie_constancia' => $serie,
                'numero_constancia' => $numeroConstancia,
                'constancia' => $numeroFormateado,
            ]);

            foreach ($asignaciones as $cuotaId => $montoAplicado) {
                AplicacionPago::query()->create([
                    'colegio_id' => $colegioId,
                    'pago_id' => $pago->id,
                    'cuota_id' => $cuotaId,
                    'monto_aplicado' => Monto::desdeCentimos($montoAplicado),
                ]);
            }

            $estadosResultantes = $this->estadoDeCuota->calcularVarias($cuotas);
            $saldos = [];

            foreach ($asignaciones as $cuotaId => $_montoAplicado) {
                $saldos[] = [
                    'cuota_id' => (int) $cuotaId,
                    'saldo' => $estadosResultantes[$cuotaId]['saldo'],
                    'estado' => $estadosResultantes[$cuotaId]['estado'],
                ];
            }

            $pago->load(['aplicaciones.cuota']);
            $respuesta = [
                'message' => 'Pago registrado.',
                'data' => [
                    'pago' => (new PagoResource($pago))->resolve(request()),
                    'saldos' => $saldos,
                    'constancia' => [
                        'numero' => $numeroFormateado,
                        'url' => route('api.v1.pagos.constancia', $pago->id),
                    ],
                ],
                'errors' => null,
                'code' => 'OK',
            ];
            $registroIdempotencia->update(['respuesta' => $respuesta]);

            return ['respuesta' => $respuesta];
        }, attempts: 3);
    }

    /**
     * @param  array<int, array{cuota_id: int|string, monto: int|float|string}>  $aplicaciones
     * @return Collection<int, Cuota>
     */
    private function bloquearCuotas(int $colegioId, Matricula $matricula, array $aplicaciones): Collection
    {
        $consulta = Cuota::query()
            ->where('colegio_id', $colegioId)
            ->where('matricula_id', $matricula->id)
            ->whereNull('anulada_en')
            ->when(
                $aplicaciones !== [],
                fn ($query) => $query->whereIn('id', array_column($aplicaciones, 'cuota_id')),
            );
        $cuotas = $consulta
            ->with('descuentos')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($aplicaciones !== [] && $cuotas->count() !== count($aplicaciones)) {
            throw ValidationException::withMessages([
                'aplicaciones' => 'Todas las cuotas deben pertenecer a la matrícula y estar vigentes.',
            ]);
        }

        return $cuotas;
    }

    /**
     * @param  Collection<int, Cuota>  $cuotas
     * @param  array<int, array{saldo: string, total_descuentos: string, total_pagos: string, estado: string}>  $estados
     * @param  array<int, array{cuota_id: int|string, monto: int|float|string}>  $aplicaciones
     * @return array<int, int>
     */
    private function asignarMontos(
        Collection $cuotas,
        array $estados,
        array $aplicaciones,
        int $montoCentimos,
    ): array {
        $asignaciones = [];

        if ($aplicaciones !== []) {
            foreach ($aplicaciones as $aplicacion) {
                $cuotaId = (int) $aplicacion['cuota_id'];
                $montoAplicado = Monto::aCentimos($aplicacion['monto']);
                $saldo = Monto::aCentimos($estados[$cuotaId]['saldo'] ?? '0.00');

                if ($montoAplicado > $saldo) {
                    throw ValidationException::withMessages([
                        'aplicaciones' => 'El monto aplicado no puede superar el saldo pendiente de una cuota.',
                    ]);
                }

                $asignaciones[$cuotaId] = $montoAplicado;
            }

            if (array_sum($asignaciones) !== $montoCentimos) {
                throw ValidationException::withMessages([
                    'aplicaciones' => 'La suma de las aplicaciones debe ser igual al monto del pago.',
                ]);
            }

            return $asignaciones;
        }

        $restante = $montoCentimos;
        $cuotasOrdenadas = $cuotas->sortBy([
            ['vencimiento', 'asc'],
            ['id', 'asc'],
        ]);

        foreach ($cuotasOrdenadas as $cuota) {
            $saldo = Monto::aCentimos($estados[$cuota->id]['saldo']);
            $aplicar = min($saldo, $restante);

            if ($aplicar > 0) {
                $asignaciones[$cuota->id] = $aplicar;
                $restante -= $aplicar;
            }

            if ($restante === 0) {
                break;
            }
        }

        if ($restante > 0) {
            throw ValidationException::withMessages([
                'monto' => 'El monto del pago supera el saldo pendiente de las cuotas de la matrícula.',
            ]);
        }

        return $asignaciones;
    }

    /** @param array<string, mixed> $datos */
    private function hashCuerpo(array $datos): string
    {
        unset($datos['idempotency_key']);

        return hash('sha256', json_encode($this->ordenarRecursivamente($datos), JSON_THROW_ON_ERROR));
    }

    private function ordenarRecursivamente(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }

        foreach ($valor as $clave => $elemento) {
            $valor[$clave] = $this->ordenarRecursivamente($elemento);
        }

        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return $valor;
    }
}
