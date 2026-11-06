<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AsignacionDocente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AsignacionDocente */
class AsignacionDocenteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'anio_escolar_id' => $this->anio_escolar_id,
            'anio_escolar' => $this->whenLoaded('anioEscolar', fn (): array => [
                'id' => $this->anioEscolar->id,
                'nombre' => $this->anioEscolar->nombre,
            ]),
            'curso_id' => $this->curso_id,
            'seccion_id' => $this->seccion_id,
            'docente_id' => $this->docente_id,
            'docente' => $this->whenLoaded('docente', fn (): array => [
                'id' => $this->docente->id,
                'nombre' => $this->docente->nombre,
                'apellido' => $this->docente->apellido,
            ]),
            'curso' => $this->whenLoaded('curso', fn (): array => [
                'id' => $this->curso->id,
                'nombre' => $this->curso->nombre,
            ]),
            'seccion' => $this->whenLoaded('seccion', fn (): array => [
                'id' => $this->seccion->id,
                'nombre' => $this->seccion->nombre,
            ]),
            'activo' => $this->activo,
        ];
    }
}
