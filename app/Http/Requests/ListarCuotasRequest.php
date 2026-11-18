<?php

namespace App\Http\Requests;

use App\Models\Cuota;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Validation\Rule;

#[QueryParameter('direction', type: "'asc'|'desc'")]
#[QueryParameter('concepto', type: "'matricula'|'pension'")]
#[QueryParameter('estado', type: "'pendiente'|'parcial'|'pagada'|'vencida'|'anulada'")]
#[QueryParameter('orden', type: "'asc'|'desc'")]
class ListarCuotasRequest extends ListarEstructuraAcademicaRequest
{
    protected function modelo(): string
    {
        return Cuota::class;
    }

    protected function columnasOrdenables(): array
    {
        return ['id', 'vencimiento', 'numero', 'monto_original', 'alumno_id', 'anio_escolar_id'];
    }

    protected function reglasAdicionales(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'alumno_id' => [
                'sometimes',
                'integer',
                Rule::exists('alumnos', 'id')->where('colegio_id', $colegioId),
            ],
            'matricula_id' => [
                'sometimes',
                'integer',
                Rule::exists('matriculas', 'id')->where('colegio_id', $colegioId),
            ],
            'anio_escolar_id' => [
                'sometimes',
                'integer',
                Rule::exists('anios_escolares', 'id')->where('colegio_id', $colegioId),
            ],
            'concepto' => ['sometimes', Rule::in(['matricula', 'pension'])],
            'estado' => ['sometimes', Rule::in(['pendiente', 'parcial', 'pagada', 'vencida', 'anulada'])],
            'vencimiento_desde' => ['sometimes', 'date'],
            'vencimiento_hasta' => ['sometimes', 'date', 'after_or_equal:vencimiento_desde'],
            'orden' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
