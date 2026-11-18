<?php

namespace App\Support;

use InvalidArgumentException;

final class Monto
{
    public static function aCentimos(int|float|string $monto): int
    {
        $valor = is_float($monto) ? number_format($monto, 2, '.', '') : (string) $monto;

        if (! preg_match('/^(\d+)(?:\.(\d{1,2}))?$/D', $valor, $partes)) {
            throw new InvalidArgumentException('El monto debe tener hasta dos decimales.');
        }

        return ((int) $partes[1] * 100) + (int) str_pad($partes[2] ?? '', 2, '0');
    }

    public static function desdeCentimos(int $centimos): string
    {
        return intdiv($centimos, 100).'.'.str_pad((string) ($centimos % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function calcularPorcentaje(int $montoCentimos, int|float|string $porcentaje): int
    {
        $porcentajeEnCentimos = self::aCentimos($porcentaje);

        return intdiv(($montoCentimos * $porcentajeEnCentimos) + 5000, 10000);
    }
}
