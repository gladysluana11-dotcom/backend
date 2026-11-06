<?php

namespace App\Http\Requests;

use App\Models\AsignacionDocente;

class ListarAsignacionesDocentesRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return AsignacionDocente::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'anio_escolar_id', 'curso_id', 'seccion_id', 'docente_id', 'activo'];
    }

    protected function reglasAdicionales(): array
    {
        return [
            'anio_escolar_id' => ['sometimes', 'integer', 'min:1'],
            'seccion_id' => ['sometimes', 'integer', 'min:1'],
            'curso_id' => ['sometimes', 'integer', 'min:1'],
            'docente_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
