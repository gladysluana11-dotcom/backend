<?php

namespace App\Http\Resources\Api\V1;

use App\Models\HistorialMatricula;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin HistorialMatricula */
class HistorialMatriculaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'evento' => $this->evento,
            'grado_anterior_id' => $this->grado_anterior_id,
            'seccion_anterior_id' => $this->seccion_anterior_id,
            'grado_nuevo_id' => $this->grado_nuevo_id,
            'seccion_nueva_id' => $this->seccion_nueva_id,
            'estado_anterior' => $this->estado_anterior,
            'estado_nuevo' => $this->estado_nuevo,
            'motivo' => $this->motivo,
            'fecha' => $this->fecha,
        ];
    }
}
