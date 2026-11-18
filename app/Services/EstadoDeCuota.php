<?php

namespace App\Services;

use App\Contracts\ConsultarAplicacionesPago;
use App\Models\Cuota;
use App\Support\Monto;
use LogicException;

class EstadoDeCuota
{
    public function __construct(private ConsultarAplicacionesPago $aplicacionesPago) {}

    /**
     * @param  iterable<array{monto_aplicado: int|float|string, anulada_en: mixed}>|null  $aplicacionesPago
     * @return array{
     *   saldo: string,
     *   total_descuentos: string,
     *   total_pagos: string,
     *   estado: 'pagada'|'parcial'|'vencida'|'anulada'|'pendiente'
     * }
     */
    public function calcular(Cuota $cuota, ?iterable $aplicacionesPago = null): array
    {
        $aplicacionesPago ??= $this->aplicacionesPago->paraCuotas([(int) $cuota->id])[$cuota->id] ?? [];

        return $this->calcularConAplicaciones($cuota, $aplicacionesPago);
    }

    /**
     * @param  iterable<Cuota>  $cuotas
     * @return array<int, array{
     *   saldo: string,
     *   total_descuentos: string,
     *   total_pagos: string,
     *   estado: 'pagada'|'parcial'|'vencida'|'anulada'|'pendiente'
     * }>
     */
    public function calcularVarias(iterable $cuotas): array
    {
        $registros = collect($cuotas);
        $aplicacionesPorCuota = $this->aplicacionesPago->paraCuotas(
            $registros->map(fn (Cuota $cuota): int => (int) $cuota->id)->all(),
        );
        $estados = [];

        foreach ($registros as $cuota) {
            $estados[$cuota->id] = $this->calcularConAplicaciones(
                $cuota,
                $aplicacionesPorCuota[$cuota->id] ?? [],
            );
        }

        return $estados;
    }

    /**
     * @param  iterable<array{monto_aplicado: int|float|string, anulada_en: mixed}>  $aplicacionesPago
     * @return array{
     *   saldo: string,
     *   total_descuentos: string,
     *   total_pagos: string,
     *   estado: 'pagada'|'parcial'|'vencida'|'anulada'|'pendiente'
     * }
     */
    private function calcularConAplicaciones(Cuota $cuota, iterable $aplicacionesPago): array
    {
        $montoOriginal = Monto::aCentimos($cuota->monto_original);
        $totalDescuentos = $this->totalDescuentos($cuota);
        $totalPagos = 0;

        foreach ($aplicacionesPago as $aplicacion) {
            if (($aplicacion['anulada_en'] ?? null) === null) {
                $totalPagos += Monto::aCentimos($aplicacion['monto_aplicado']);
            }
        }

        if ($totalDescuentos > $montoOriginal) {
            throw new LogicException('Los descuentos aplicados superan el monto original de la cuota.');
        }

        $montoNeto = $montoOriginal - $totalDescuentos;

        if ($totalPagos > $montoNeto) {
            throw new LogicException('Los pagos aplicados superan el saldo de la cuota.');
        }

        $saldo = $montoNeto - $totalPagos;
        $estado = match (true) {
            $cuota->anulada_en !== null => 'anulada',
            $saldo === 0 => 'pagada',
            $totalPagos > 0 => 'parcial',
            $cuota->vencimiento->toDateString() < today()->toDateString() => 'vencida',
            default => 'pendiente',
        };

        return [
            'saldo' => Monto::desdeCentimos($saldo),
            'total_descuentos' => Monto::desdeCentimos($totalDescuentos),
            'total_pagos' => Monto::desdeCentimos($totalPagos),
            'estado' => $estado,
        ];
    }

    private function totalDescuentos(Cuota $cuota): int
    {
        $descuentos = $cuota->relationLoaded('descuentos')
            ? $cuota->getRelation('descuentos')
            : $cuota->descuentos()->get();

        return $descuentos
            ->whereNull('anulada_en')
            ->sum(fn ($descuento): int => Monto::aCentimos($descuento->monto_calculado));
    }
}
