<?php

namespace App\Http\Requests;

use App\Models\Matricula;

class ListarMatriculasRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Matricula::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'anio_escolar_id', 'grado_id', 'seccion_id', 'fecha_matricula', 'estado'];
    }

    protected function reglasAdicionales(): array
    {
        return [
            'anio_escolar_id' => ['sometimes', 'integer', 'min:1'],
            'grado_id' => ['sometimes', 'integer', 'min:1'],
            'seccion_id' => ['sometimes', 'integer', 'min:1'],
            'alumno_id' => ['sometimes', 'integer', 'min:1'],
            'estado' => ['sometimes', 'in:pre_inscrito,pendiente_documentos,matriculado,retirado,anulado'],
        ];
    }
}
