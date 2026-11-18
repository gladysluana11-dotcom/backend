<?php

namespace App\Contracts;

interface ConsultarAplicacionesPago
{
    /**
     * @param  list<int>  $cuotaIds
     * @return array<int, list<array{monto_aplicado: int|float|string, anulada_en: mixed}>>
     */
    public function paraCuotas(array $cuotaIds): array;
}
