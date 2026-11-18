<?php

namespace App\Services;

use App\Models\Cuota;
use App\Models\Matricula;
use App\Models\Usuario;
use App\Support\Monto;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnularCuotasPorRetiro
{
    public function __construct(
        private EstadoDeCuota $estadoDeCuota,
        private RegistrarAuditoria $auditoria,
    ) {}

    /**
     * @return Collection<int, Cuota>
     */
    public function anular(Matricula $modelo, ?Usuario $actor = null): Collection
    {
        return DB::transaction(function () use ($modelo, $actor): Collection {
            $matricula = Matricula::query()
                ->where('colegio_id', $modelo->colegio_id)
                ->lockForUpdate()
                ->findOrFail($modelo->id);
            $fechaRetiro = $matricula->fecha_retiro?->toDateString() ?? today()->toDateString();
            $cuotas = Cuota::query()
                ->where('colegio_id', $matricula->colegio_id)
                ->where('matricula_id', $matricula->id)
                ->whereDate('vencimiento', '>', $fechaRetiro)
                ->whereNull('anulada_en')
                ->with('descuentos')
                ->lockForUpdate()
                ->get();
            $anuladas = new Collection;
            $estados = $this->estadoDeCuota->calcularVarias($cuotas);

            foreach ($cuotas as $cuota) {
                $estado = $estados[$cuota->id];

                if (Monto::aCentimos($estado['total_pagos']) > 0) {
                    continue;
                }

                $cuota->forceFill([
                    'anulada_en' => now(),
                    'motivo_anulacion' => $matricula->motivo === null
                        ? 'Matrícula retirada.'
                        : 'Matrícula retirada: '.$matricula->motivo,
                ])->save();
                $anuladas->push($cuota);

                if (! Auth::user() instanceof Usuario) {
                    $this->auditoria->registrar(
                        'cuotas.anulada_por_retiro',
                        'cuotas',
                        (int) $cuota->id,
                        valoresAnteriores: ['anulada_en' => null],
                        valoresNuevos: ['anulada_en' => $cuota->anulada_en?->toISOString()],
                        usuario: $actor,
                        colegioId: (int) $cuota->colegio_id,
                    );
                }
            }

            return $anuladas;
        }, attempts: 5);
    }
}
