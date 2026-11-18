<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CierreCaja;
use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CierreCaja */
#[SchemaName('CierreCaja')]
class CierreCajaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toDateString(),
            'observacion' => $this->observacion,
            'total' => $this->total,
            'resumen' => $this->resumen,
            'usuario_id' => $this->usuario_id,
        ];
    }
}
