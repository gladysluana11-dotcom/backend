<?php

namespace App\Http\Requests;

use App\Models\Pago;
use Dedoc\Scramble\Attributes\IgnoreParam;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[QueryParameter('direction', type: "'asc'|'desc'")]
#[QueryParameter('estado', type: "'vigente'|'anulado'")]
#[QueryParameter('medio', type: "'efectivo'|'deposito'|'transferencia'|'billetera_digital'")]
#[QueryParameter('orden', type: "'asc'|'desc'")]
#[IgnoreParam('colegio_id', 'query')]
class ListarPagosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Pago::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'colegio_id' => ['prohibited'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'alumno_id' => ['sometimes', 'integer', Rule::exists('alumnos', 'id')->where('colegio_id', $colegioId)],
            'matricula_id' => ['sometimes', 'integer', Rule::exists('matriculas', 'id')->where('colegio_id', $colegioId)],
            'desde' => ['sometimes', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'medio' => ['sometimes', Rule::in(['efectivo', 'deposito', 'transferencia', 'billetera_digital'])],
            'usuario_id' => ['sometimes', 'integer', Rule::exists('usuarios', 'id')->where('colegio_id', $colegioId)],
            'estado' => ['sometimes', Rule::in(['vigente', 'anulado'])],
            'sort' => ['sometimes', Rule::in(['id', 'fecha_pago', 'monto', 'medio', 'estado'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'orden' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
