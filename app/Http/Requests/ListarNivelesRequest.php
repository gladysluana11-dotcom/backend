<?php

namespace App\Http\Requests;

use App\Models\Nivel;

class ListarNivelesRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Nivel::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'nombre', 'orden', 'activo'];
    }
}
