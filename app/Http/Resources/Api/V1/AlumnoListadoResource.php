<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Alumno;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Alumno */
class AlumnoListadoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $documento = (string) $this->documento_identidad;
        $longitud = mb_strlen($documento);

        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'apellido' => $this->apellido,
            'documento' => $documento === ''
                ? null
                : ($longitud <= 4 ? str_repeat('*', $longitud) : str_repeat('*', $longitud - 4).mb_substr($documento, -4)),
            'estado' => $this->estado,
            'matricula_actual' => $this->whenLoaded('matriculas', function (): ?array {
                $matricula = $this->matriculas->first();

                if ($matricula === null) {
                    return null;
                }

                return [
                    'anio_escolar_id' => $matricula->anio_escolar_id,
                    'grado_id' => $matricula->grado_id,
                    'seccion_id' => $matricula->seccion_id,
                    'anio_escolar' => $matricula->anio_escolar,
                    'grado' => $matricula->grado,
                    'seccion' => $matricula->seccion,
                    'estado' => $matricula->estado,
                ];
            }),
        ];
    }
}
