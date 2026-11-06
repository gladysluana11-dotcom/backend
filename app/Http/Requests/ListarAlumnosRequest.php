<?php

namespace App\Http\Requests;

use App\Models\Alumno;

class ListarAlumnosRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Alumno::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'nombre', 'apellido', 'estado', 'created_at'];
    }

    protected function reglasAdicionales(): array
    {
        return [
            'grado_id' => ['sometimes', 'integer', 'min:1'],
            'seccion_id' => ['sometimes', 'integer', 'min:1'],
            'anio_escolar_id' => ['sometimes', 'integer', 'min:1'],
            'estado' => ['sometimes', 'in:activo,retirado,egresado'],
        ];
    }
}
