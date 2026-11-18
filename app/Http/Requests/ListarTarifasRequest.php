<?php

namespace App\Http\Requests;

use App\Models\Tarifa;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Validation\Rule;

#[QueryParameter('direction', type: "'asc'|'desc'")]
class ListarTarifasRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Tarifa::class;
    }

    protected function columnasOrdenables(): array
    {
        return [
            'id',
            'anio_escolar_id',
            'grado_id',
            'monto_matricula',
            'monto_pension',
            'numero_cuotas',
            'dia_vencimiento',
            'mes_primera_pension',
        ];
    }

    protected function reglasAdicionales(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'anio_escolar_id' => [
                'sometimes',
                'integer',
                Rule::exists('anios_escolares', 'id')->where('colegio_id', $colegioId),
            ],
            'grado_id' => [
                'sometimes',
                'integer',
                Rule::exists('grados', 'id')->where('colegio_id', $colegioId),
            ],
        ];
    }
}
