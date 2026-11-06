<?php

namespace App\Http\Requests;

use App\Models\Auditoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarAuditoriasRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Auditoria::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'usuario_id' => [
                'sometimes',
                'integer',
                Rule::exists('usuarios', 'id')->where('colegio_id', $this->user()?->colegio_id),
            ],
            'evento' => ['sometimes', 'string', 'max:100'],
            'entidad' => ['sometimes', 'string', 'max:100'],
            'fecha_desde' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:fecha_hasta'],
            'fecha_hasta' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:fecha_desde'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'usuario_id.exists' => 'El usuario seleccionado no pertenece a este colegio.',
            'fecha_desde.before_or_equal' => 'La fecha inicial debe ser anterior o igual a la fecha final.',
            'fecha_hasta.after_or_equal' => 'La fecha final debe ser posterior o igual a la fecha inicial.',
            'per_page.max' => 'La cantidad por página no puede superar 100.',
        ];
    }
}
