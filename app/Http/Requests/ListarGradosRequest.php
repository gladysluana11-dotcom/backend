<?php

namespace App\Http\Requests;

use App\Models\Grado;

class ListarGradosRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Grado::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'nivel_id', 'nombre', 'orden', 'activo'];
    }

    protected function reglasAdicionales(): array
    {
        return ['nivel_id' => ['sometimes', 'integer', 'min:1']];
    }
}
