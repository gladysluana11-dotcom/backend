<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\IgnoreParam;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[QueryParameter('orden', type: "'nombre'|'total_vencido'|'total_pendiente'|'dias_atraso'|'cuotas_vencidas'")]
#[QueryParameter('direccion', type: "'asc'|'desc'")]
#[IgnoreParam('colegio_id', 'query')]
class ListarDeudasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('ver-deudas') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'colegio_id' => ['prohibited'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'formato' => ['sometimes', Rule::in(['json', 'pdf', 'xlsx'])],
            'nivel_id' => ['sometimes', 'integer', Rule::exists('niveles', 'id')->where('colegio_id', $colegioId)],
            'grado_id' => ['sometimes', 'integer', Rule::exists('grados', 'id')->where('colegio_id', $colegioId)],
            'seccion_id' => ['sometimes', 'integer', Rule::exists('secciones', 'id')->where('colegio_id', $colegioId)],
            'anio_escolar_id' => [
                'sometimes',
                'integer',
                Rule::exists('anios_escolares', 'id')->where('colegio_id', $colegioId),
            ],
            'dias_atraso_min' => ['sometimes', 'integer', 'min:1'],
            'monto_min' => ['sometimes', 'numeric', 'min:0', 'decimal:0,2'],
            'buscar' => ['sometimes', 'string', 'max:100'],
            'orden' => [
                'sometimes',
                Rule::in(['nombre', 'total_vencido', 'total_pendiente', 'dias_atraso', 'cuotas_vencidas']),
            ],
            'direccion' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
