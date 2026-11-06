<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Curso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Curso */
class CursoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nivel_id' => $this->nivel_id,
            'grado_id' => $this->grado_id,
            'nombre' => $this->nombre,
            'activo' => $this->activo,
        ];
    }
}
