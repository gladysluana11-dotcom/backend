<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Cuota;
use App\Services\EstadoDeCuota;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cuota */
#[SchemaName('Cuota')]
class CuotaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $estado = $this->estadoCalculado();

        return [
            'id' => $this->id,
            'matricula_id' => $this->matricula_id,
            'alumno_id' => $this->alumno_id,
            'alumno' => $this->whenLoaded('alumno', fn (): array => [
                'id' => $this->alumno->id,
                'nombre' => $this->alumno->nombre,
                'apellido' => $this->alumno->apellido,
            ]),
            'anio_escolar_id' => $this->anio_escolar_id,
            'concepto' => $this->concepto,
            'numero' => $this->numero,
            'descripcion' => $this->descripcion,
            'monto_original' => $this->monto_original,
            'vencimiento' => $this->vencimiento->toDateString(),
            'anulada_en' => $this->anulada_en?->toISOString(),
            'saldo' => $estado['saldo'],
            'total_descuentos' => $estado['total_descuentos'],
            'total_pagos' => $estado['total_pagos'],
            'estado' => $estado['estado'],
        ];
    }

    /**
     * @return array{
     *   saldo: string,
     *   total_descuentos: string,
     *   total_pagos: string,
     *   estado: 'pagada'|'parcial'|'vencida'|'anulada'|'pendiente'
     * }
     */
    private function estadoCalculado(): array
    {
        if ($this->resource->relationLoaded('estadoCalculado')) {
            return $this->resource->getRelation('estadoCalculado');
        }

        return app(EstadoDeCuota::class)->calcular($this->resource);
    }
}
