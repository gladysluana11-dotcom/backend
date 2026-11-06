<?php

namespace App\Http\Requests;

use App\Models\AnioEscolar;

class ListarAniosEscolaresRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return AnioEscolar::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'nombre', 'fecha_inicio', 'fecha_fin', 'activo', 'cerrado'];
    }

    protected function reglasAdicionales(): array
    {
        return ['cerrado' => ['sometimes', 'boolean']];
    }
}
