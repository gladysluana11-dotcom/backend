<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Grado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Grado */
class GradoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nivel_id' => $this->nivel_id,
            'nombre' => $this->nombre,
            'orden' => $this->orden,
            'activo' => $this->activo,
        ];
    }
}
