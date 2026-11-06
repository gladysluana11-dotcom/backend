<?php

namespace App\Http\Requests;

use App\Models\Seccion;

class ListarSeccionesRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Seccion::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'anio_escolar_id', 'grado_id', 'nombre', 'capacidad', 'activo'];
    }

    protected function reglasAdicionales(): array
    {
        return [
            'anio_escolar_id' => ['sometimes', 'integer', 'min:1'],
            'grado_id' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
