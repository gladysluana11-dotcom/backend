<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Descuento;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Descuento */
#[SchemaName('Descuento')]
class DescuentoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cuota_id' => $this->cuota_id,
            'tipo' => $this->tipo,
            'modo' => $this->modo,
            'valor' => $this->valor,
            'monto_calculado' => $this->monto_calculado,
            'motivo' => $this->motivo,
            'usuario_id' => $this->usuario_id,
            'anulada_en' => $this->anulada_en?->toISOString(),
            'motivo_anulacion' => $this->motivo_anulacion,
        ];
    }
}
