<?php

namespace App\Http\Requests;

use App\Models\Apoderado;

class ListarApoderadosRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Apoderado::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'nombre', 'apellido', 'created_at'];
    }

    protected function reglasAdicionales(): array
    {
        return [];
    }
}
