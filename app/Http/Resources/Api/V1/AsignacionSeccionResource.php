<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AsignacionSeccion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AsignacionSeccion */
class AsignacionSeccionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'anio_escolar_id' => $this->anio_escolar_id,
            'seccion_id' => $this->seccion_id,
            'usuario_id' => $this->usuario_id,
            'activo' => $this->activo,
        ];
    }
}
