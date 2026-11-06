<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Matricula;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Matricula */
class MatriculaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alumno' => $this->whenLoaded('alumno', fn (): array => [
                'id' => $this->alumno->id,
                'nombre' => $this->alumno->nombre,
                'apellido' => $this->alumno->apellido,
            ]),
            'anio_escolar_id' => $this->anio_escolar_id,
            'anio_escolar' => $this->anio_escolar,
            'grado_id' => $this->grado_id,
            'grado' => $this->grado,
            'seccion_id' => $this->seccion_id,
            'seccion' => $this->seccion,
            'fecha_matricula' => $this->fecha_matricula->toDateString(),
            'estado' => $this->estado,
            'usuario_id' => $this->usuario_id,
            'usuario_registro' => $this->whenLoaded('usuarioRegistro', fn (): ?array => $this->usuarioRegistro === null
                ? null
                : [
                    'id' => $this->usuarioRegistro->id,
                    'nombre' => $this->usuarioRegistro->nombre,
                    'apellido' => $this->usuarioRegistro->apellido,
                ]),
            'fecha_retiro' => $this->fecha_retiro?->toDateString(),
            'motivo' => $this->motivo,
            'documentos' => MatriculaDocumentoResource::collection($this->whenLoaded('documentos')),
        ];
    }
}
