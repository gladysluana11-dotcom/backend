<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CrearUsuarioRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Usuario::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:usuarios,email'],
            'rol' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where('colegio_id', $this->user()?->colegio_id),
            ],
            'colegio_id' => ['prohibited'],
            'password' => ['prohibited'],
            'activo' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rol.exists' => 'El rol seleccionado no pertenece a este colegio.',
            'colegio_id.prohibited' => 'El colegio se determina según el usuario autenticado.',
            'password.prohibited' => 'La contraseña temporal es generada por el sistema.',
        ];
    }
}
