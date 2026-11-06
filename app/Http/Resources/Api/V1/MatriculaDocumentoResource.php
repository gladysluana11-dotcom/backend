<?php

namespace App\Http\Resources\Api\V1;

use App\Models\MatriculaDocumento;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin MatriculaDocumento */
class MatriculaDocumentoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'documento_requerido_id' => $this->documento_requerido_id,
            'nombre' => $this->whenLoaded('documentoRequerido', fn () => $this->documentoRequerido->nombre),
            'estado' => $this->estado,
            'fecha_entrega' => $this->fecha_entrega?->toDateString(),
        ];
    }
}
