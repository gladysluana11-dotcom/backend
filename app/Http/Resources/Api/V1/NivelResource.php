<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Nivel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Nivel */
class NivelResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'orden' => $this->orden,
            'activo' => $this->activo,
        ];
    }
}
