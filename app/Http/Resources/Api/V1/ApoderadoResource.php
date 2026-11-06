<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Alumno;
use App\Models\Apoderado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Apoderado */
class ApoderadoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'alumnos' => $this->whenLoaded('alumnos', fn () => $this->alumnos->map(fn (Alumno $alumno): array => [
                'id' => $alumno->id,
                'nombre' => $alumno->nombre,
                'apellido' => $alumno->apellido,
                'parentesco' => $alumno->pivot->getAttribute('parentesco'),
                'telefono' => $alumno->pivot->getAttribute('telefono'),
                'email' => $alumno->pivot->getAttribute('email'),
                'responsable_pago' => (bool) $alumno->pivot->getAttribute('responsable_pago'),
            ])->values()->all()),
        ];
    }
}
