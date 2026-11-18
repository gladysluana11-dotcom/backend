<?php

namespace App\Services;

use App\Models\CierreCaja;
use App\Models\Usuario;
use App\Support\Monto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GestionarCaja
{
    /**
     * @return array{
     *   fecha: string,
     *   total: string,
     *   cantidad_pagos: int,
     *   por_usuario: list<array{usuario_id: int, usuario: string, cantidad: int, total: string}>,
     *   por_medio: list<array{medio: string, cantidad: int, total: string}>
     * }
     */
    public function resumen(Usuario $actor, string $fecha, ?int $usuarioId = null): array
    {
        $consultaBase = DB::table('pagos')
            ->where('pagos.colegio_id', $actor->colegio_id)
            ->whereDate('pagos.fecha_pago', $fecha)
            ->where('pagos.estado', 'vigente')
            ->when($usuarioId !== null, fn ($consulta) => $consulta->where('pagos.usuario_id', $usuarioId));

        $porUsuario = (clone $consultaBase)
            ->join('usuarios', 'usuarios.id', '=', 'pagos.usuario_id')
            ->select([
                'usuarios.id as usuario_id',
                'usuarios.nombre',
                'usuarios.apellido',
            ])
            ->selectRaw('COUNT(pagos.id) as cantidad, SUM(pagos.monto) as total')
            ->groupBy('usuarios.id', 'usuarios.nombre', 'usuarios.apellido')
            ->orderBy('usuarios.apellido')
            ->orderBy('usuarios.nombre')
            ->get()
            ->map(fn ($fila): array => [
                'usuario_id' => (int) $fila->usuario_id,
                'usuario' => trim($fila->nombre.' '.$fila->apellido),
                'cantidad' => (int) $fila->cantidad,
                'total' => Monto::desdeCentimos(Monto::aCentimos($fila->total)),
            ])->all();

        $porMedio = (clone $consultaBase)
            ->select('pagos.medio')
            ->selectRaw('COUNT(pagos.id) as cantidad, SUM(pagos.monto) as total')
            ->groupBy('pagos.medio')
            ->orderBy('pagos.medio')
            ->get()
            ->map(fn ($fila): array => [
                'medio' => $fila->medio,
                'cantidad' => (int) $fila->cantidad,
                'total' => Monto::desdeCentimos(Monto::aCentimos($fila->total)),
            ])->all();

        $total = (clone $consultaBase)->sum('pagos.monto');
        $cantidad = (clone $consultaBase)->count('pagos.id');

        return [
            'fecha' => $fecha,
            'total' => Monto::desdeCentimos(Monto::aCentimos($total ?: '0.00')),
            'cantidad_pagos' => $cantidad,
            'por_usuario' => $porUsuario,
            'por_medio' => $porMedio,
        ];
    }

    public function cerrar(Usuario $actor, string $fecha, ?string $observacion): CierreCaja
    {
        return DB::transaction(function () use ($actor, $fecha, $observacion): CierreCaja {
            Usuario::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $existente = CierreCaja::query()
                ->where('colegio_id', $actor->colegio_id)
                ->where('usuario_id', $actor->id)
                ->whereDate('fecha', $fecha)
                ->first();

            if ($existente !== null) {
                throw ValidationException::withMessages([
                    'fecha' => 'La caja de este usuario ya fue cerrada para la fecha indicada.',
                ]);
            }

            $resumen = $this->resumen($actor, $fecha, (int) $actor->id);

            return CierreCaja::query()->create([
                'colegio_id' => $actor->colegio_id,
                'usuario_id' => $actor->id,
                'fecha' => $fecha,
                'observacion' => $observacion,
                'total' => $resumen['total'],
                'resumen' => $resumen,
            ]);
        }, attempts: 3);
    }
}
