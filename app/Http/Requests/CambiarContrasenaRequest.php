<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarContrasenaRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contrasena_actual' => ['required', 'string', 'current_password:sanctum'],
            'contrasena' => ['required', 'string', 'min:10', 'confirmed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contrasena_actual.current_password' => 'La contraseña actual no es correcta.',
            'contrasena.min' => 'La nueva contraseña debe tener al menos 10 caracteres.',
            'contrasena.confirmed' => 'La confirmación de contraseña no coincide.',
        ];
    }
}
