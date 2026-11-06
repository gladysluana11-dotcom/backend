<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HistorialEstadoAlumno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HistorialEstadoAlumno */
class HistorialEstadoAlumnoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'estado_anterior' => $this->estado_anterior,
            'estado_nuevo' => $this->estado_nuevo,
            'motivo' => $this->motivo,
            'fecha' => $this->fecha,
        ];
    }
}
