<?php

namespace App\Http\Resources\Api\V1;

use App\Models\DocumentoRequerido;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin DocumentoRequerido */
class DocumentoRequeridoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'obligatorio' => $this->obligatorio,
            'activo' => $this->activo,
        ];
    }
}
