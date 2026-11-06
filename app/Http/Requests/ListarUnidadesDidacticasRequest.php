<?php

namespace App\Http\Requests;

use App\Models\UnidadDidactica;

class ListarUnidadesDidacticasRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return UnidadDidactica::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'periodo_id', 'nombre', 'orden', 'fecha_inicio', 'fecha_fin', 'activo'];
    }

    protected function reglasAdicionales(): array
    {
        return ['periodo_id' => ['sometimes', 'integer', 'min:1']];
    }
}
