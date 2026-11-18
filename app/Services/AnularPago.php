<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Pago;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnularPago
{
    public function __construct(private EstadoDeCuota $estadoDeCuota) {}

    /**
     * @return array{
     *   pago: Pago,
     *   saldos: list<array{cuota_id: int, saldo: string, estado: string}>
     * }
     */
    public function anular(Usuario $actor, Pago $pago, string $motivo): array
    {
        return DB::transaction(function () use ($actor, $pago, $motivo): array {
            $pagoBloqueado = Pago::query()
                ->where('colegio_id', $actor->colegio_id)
                ->whereKey($pago->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($pagoBloqueado->estado !== 'vigente') {
                throw ValidationException::withMessages(['pago' => 'El pago ya está anulado.']);
            }

            $cuotas = Cuota::query()
                ->where('colegio_id', $actor->colegio_id)
                ->whereIn('id', $pagoBloqueado->aplicaciones()->pluck('cuota_id'))
                ->with('descuentos')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $pagoBloqueado->forceFill([
                'estado' => 'anulado',
                'anulada_en' => now(),
                'anulado_por_usuario_id' => $actor->id,
                'motivo_anulacion' => $motivo,
            ])->save();

            $estados = $this->estadoDeCuota->calcularVarias($cuotas);
            $saldos = [];

            foreach ($cuotas as $cuota) {
                $saldos[] = [
                    'cuota_id' => (int) $cuota->id,
                    'saldo' => $estados[$cuota->id]['saldo'],
                    'estado' => $estados[$cuota->id]['estado'],
                ];
            }

            return ['pago' => $pagoBloqueado->load('aplicaciones.cuota'), 'saldos' => $saldos];
        }, attempts: 3);
    }
}
