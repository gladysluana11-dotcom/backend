<?php

namespace App\Services;

use App\Models\ConfiguracionColegio;
use App\Models\Cuota;
use App\Models\Usuario;
use App\Support\Monto;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConsultarAlertasCuota
{
    public function __construct(private EstadoDeCuota $estadoDeCuota) {}

    /**
     * @return array{
     *   dias_configurados: int,
     *   por_vencer: array{cantidad: int, monto: string},
     *   vencidas: array{cantidad: int, monto: string}
     * }
     */
    public function consultar(Usuario $actor): array
    {
        $dias = (int) (ConfiguracionColegio::query()
            ->where('colegio_id', $actor->colegio_id)
            ->value('dias_alerta_cuotas') ?? 5);
        $hoy = today();
        $fechaLimite = $hoy->copy()->addDays($dias);
        $cuotas = Cuota::query()
            ->where('colegio_id', $actor->colegio_id)
            ->whereNull('anulada_en')
            ->where('vencimiento', '<=', $fechaLimite->toDateString())
            ->with(['descuentos' => fn (HasMany $query) => $query->whereNull('anulada_en')])
            ->orderBy('id')
            ->get();
        $estados = $this->estadoDeCuota->calcularVarias($cuotas);
        $porVencer = ['cantidad' => 0, 'monto_centimos' => 0];
        $vencidas = ['cantidad' => 0, 'monto_centimos' => 0];

        foreach ($cuotas as $cuota) {
            $saldo = Monto::aCentimos($estados[$cuota->id]['saldo']);

            if ($saldo <= 0) {
                continue;
            }

            if ($cuota->vencimiento->toDateString() < $hoy->toDateString()) {
                $vencidas['cantidad']++;
                $vencidas['monto_centimos'] += $saldo;

                continue;
            }

            $porVencer['cantidad']++;
            $porVencer['monto_centimos'] += $saldo;
        }

        return [
            'dias_configurados' => $dias,
            'por_vencer' => [
                'cantidad' => $porVencer['cantidad'],
                'monto' => Monto::desdeCentimos($porVencer['monto_centimos']),
            ],
            'vencidas' => [
                'cantidad' => $vencidas['cantidad'],
                'monto' => Monto::desdeCentimos($vencidas['monto_centimos']),
            ],
        ];
    }
}
