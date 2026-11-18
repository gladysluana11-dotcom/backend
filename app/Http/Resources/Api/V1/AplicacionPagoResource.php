<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AplicacionPago;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AplicacionPago */
#[SchemaName('AplicacionPago')]
class AplicacionPagoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cuota_id' => $this->cuota_id,
            'monto_aplicado' => $this->monto_aplicado,
            'cuota' => $this->whenLoaded('cuota', fn (): array => [
                'descripcion' => $this->cuota->descripcion,
                'vencimiento' => $this->cuota->vencimiento->toDateString(),
            ]),
        ];
    }
}
