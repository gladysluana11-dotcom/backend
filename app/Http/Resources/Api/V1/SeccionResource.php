<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Seccion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Seccion */
class SeccionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'anio_escolar_id' => $this->anio_escolar_id,
            'grado_id' => $this->grado_id,
            'nombre' => $this->nombre,
            'capacidad' => $this->capacidad,
            'activo' => $this->activo,
        ];
    }
}
