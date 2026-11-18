<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[IgnoreParam('colegio_id', 'query')]
class ConsultarReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reportes.ver') ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'colegio_id' => ['prohibited'],
            'formato' => ['sometimes', Rule::in(['json', 'pdf', 'xlsx'])],
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'medio' => ['sometimes', Rule::in(['efectivo', 'deposito', 'transferencia', 'billetera_digital'])],
            'usuario_id' => [
                'sometimes',
                'integer',
                Rule::exists('usuarios', 'id')->where('colegio_id', $colegioId),
            ],
            'anio_escolar_id' => [
                'sometimes',
                'integer',
                Rule::exists('anios_escolares', 'id')->where('colegio_id', $colegioId),
            ],
            'nivel_id' => ['sometimes', 'integer', Rule::exists('niveles', 'id')->where('colegio_id', $colegioId)],
            'grado_id' => ['sometimes', 'integer', Rule::exists('grados', 'id')->where('colegio_id', $colegioId)],
            'seccion_id' => ['sometimes', 'integer', Rule::exists('secciones', 'id')->where('colegio_id', $colegioId)],
            'estado' => [
                'sometimes',
                Rule::in(['pre_inscrito', 'pendiente_documentos', 'matriculado', 'retirado']),
            ],
            'buscar' => ['sometimes', 'string', 'max:100'],
        ];
    }
}
