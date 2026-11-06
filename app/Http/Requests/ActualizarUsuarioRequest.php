<?php

namespace App\Http\Requests;

use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarUsuarioRequest extends FormRequest
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
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('usuarios', 'email')->ignore($this->route('usuario')),
            ],
            'rol' => [
                'sometimes',
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
            'colegio_id.prohibited' => 'El colegio no se puede cambiar desde esta solicitud.',
            'password.prohibited' => 'Usa el endpoint de cambio de contraseña.',
        ];
    }
}
