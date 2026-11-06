<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListarUsuariosRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Usuario::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'buscar' => ['sometimes', 'string', 'max:255'],
            'rol' => [
                'sometimes',
                'string',
                Rule::exists('roles', 'name')->where('colegio_id', $this->user()?->colegio_id),
            ],
            'estado' => ['sometimes', Rule::in(['activo', 'inactivo'])],
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
            'rol.exists' => 'El rol seleccionado no pertenece a este colegio.',
            'estado.in' => 'El estado debe ser activo o inactivo.',
            'per_page.max' => 'La cantidad por página no puede superar 100.',
        ];
    }
}
