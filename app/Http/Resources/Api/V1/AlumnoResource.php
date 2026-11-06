<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Alumno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Alumno */
class AlumnoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'tipo_documento' => $this->tipo_documento,
            'documento_identidad' => $this->documento_identidad,
            'fecha_nacimiento' => $this->fecha_nacimiento?->toDateString(),
            'sexo' => $this->sexo,
            'direccion' => $this->direccion,
            'foto_url' => $this->foto_path === null ? null : route('api.v1.alumnos.foto', $this->id),
            'estado' => $this->estado,
        ];
    }
}
