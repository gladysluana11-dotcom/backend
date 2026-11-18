<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Pago;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Pago */
#[SchemaName('Pago')]
class PagoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricula_id' => $this->matricula_id,
            'alumno_id' => $this->alumno_id,
            'usuario_id' => $this->usuario_id,
            'fecha_pago' => $this->fecha_pago->toDateString(),
            'monto' => $this->monto,
            'medio' => $this->medio,
            'numero_operacion' => $this->numero_operacion,
            'observacion' => $this->observacion,
            'estado' => $this->estado,
            'constancia' => $this->constancia,
            'anulada_en' => $this->anulada_en?->toISOString(),
            'motivo_anulacion' => $this->motivo_anulacion,
            'voucher_disponible' => $this->voucher_path !== null,
            'aplicaciones' => AplicacionPagoResource::collection($this->whenLoaded('aplicaciones')),
        ];
    }
}
