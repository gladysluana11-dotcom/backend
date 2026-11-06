<?php

namespace App\Http\Requests;

use App\Models\Curso;

class ListarCursosRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Curso::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'nivel_id', 'grado_id', 'nombre', 'activo'];
    }

    protected function reglasAdicionales(): array
    {
        return [
            'nivel_id' => ['sometimes', 'integer', 'min:1'],
            'grado_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
