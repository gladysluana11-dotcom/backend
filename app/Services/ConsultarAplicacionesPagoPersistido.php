<?php

namespace App\Services;

use App\Contracts\ConsultarAplicacionesPago;
use Illuminate\Support\Facades\DB;

class ConsultarAplicacionesPagoPersistido implements ConsultarAplicacionesPago
{
    /**
     * @param  list<int>  $cuotaIds
     * @return array<int, list<array{monto_aplicado: int|float|string, anulada_en: mixed}>>
     */
    public function paraCuotas(array $cuotaIds): array
    {
        if ($cuotaIds === []) {
            return [];
        }

        $aplicaciones = DB::table('aplicacion_pagos')
            ->join('pagos', 'pagos.id', '=', 'aplicacion_pagos.pago_id')
            ->whereIn('aplicacion_pagos.cuota_id', $cuotaIds)
            ->where('pagos.estado', 'vigente')
            ->select([
                'aplicacion_pagos.cuota_id',
                'aplicacion_pagos.monto_aplicado',
                'pagos.anulada_en',
            ])
            ->get();
        $porCuota = [];

        foreach ($aplicaciones as $aplicacion) {
            $porCuota[$aplicacion->cuota_id][] = [
                'monto_aplicado' => $aplicacion->monto_aplicado,
                'anulada_en' => $aplicacion->anulada_en,
            ];
        }

        return $porCuota;
    }
}
