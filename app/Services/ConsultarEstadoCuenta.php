<?php

namespace App\Services;

use App\Http\Resources\Api\V1\CuotaResource;
use App\Http\Resources\Api\V1\PagoResource;
use App\Models\Alumno;
use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Usuario;
use App\Support\Monto;

class ConsultarEstadoCuenta
{
    public function __construct(private EstadoDeCuota $estadoDeCuota) {}

    /** @return array<string, mixed> */
    public function consultar(Usuario $actor, Alumno $alumno): array
    {
        abort_unless($alumno->colegio_id === $actor->colegio_id, 404);

        $cuotas = Cuota::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('alumno_id', $alumno->id)
            ->with('descuentos')
            ->orderBy('vencimiento')
            ->orderBy('id')
            ->get();
        $estados = $this->estadoDeCuota->calcularVarias($cuotas);
        $emitido = 0;
        $pagado = 0;
        $saldo = 0;
        $vencido = 0;

        foreach ($cuotas as $cuota) {
            $estado = $estados[$cuota->id];
            $cuota->setRelation('estadoCalculado', $estado);

            if ($estado['estado'] === 'anulada') {
                continue;
            }

            $montoNeto = Monto::aCentimos($cuota->monto_original)
                - Monto::aCentimos($estado['total_descuentos']);
            $emitido += $montoNeto;
            $pagado += Monto::aCentimos($estado['total_pagos']);
            $saldo += Monto::aCentimos($estado['saldo']);

            if ($estado['estado'] === 'vencida') {
                $vencido += Monto::aCentimos($estado['saldo']);
            }
        }

        $pagos = Pago::query()
            ->where('colegio_id', $actor->colegio_id)
            ->where('alumno_id', $alumno->id)
            ->with('aplicaciones.cuota')
            ->orderByDesc('fecha_pago')
            ->orderByDesc('id')
            ->get();

        return [
            'alumno' => [
                'id' => $alumno->id,
                'nombre' => $alumno->nombre,
                'apellido' => $alumno->apellido,
            ],
            'cuotas' => CuotaResource::collection($cuotas)->resolve(request()),
            'pagos' => PagoResource::collection($pagos)->resolve(request()),
            'totales' => [
                'emitido' => Monto::desdeCentimos($emitido),
                'pagado' => Monto::desdeCentimos($pagado),
                'saldo' => Monto::desdeCentimos($saldo),
                'vencido' => Monto::desdeCentimos($vencido),
            ],
        ];
    }
}
