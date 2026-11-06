<?php

namespace App\Http\Requests;

use App\Models\Periodo;

class ListarPeriodosRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Periodo::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'anio_escolar_id', 'nombre', 'orden', 'fecha_inicio', 'fecha_fin', 'activo'];
    }

    protected function reglasAdicionales(): array
    {
        return ['anio_escolar_id' => ['sometimes', 'integer', 'min:1']];
    }
}
