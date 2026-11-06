<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Alumno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarAlumnoRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Alumno::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'apellido' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', 'string', 'max:30'],
            'documento_identidad' => [
                'required',
                'string',
                'max:30',
                Rule::unique('alumnos', 'documento_identidad')->where('colegio_id', $this->user()?->colegio_id),
            ],
            'fecha_nacimiento' => ['required', 'date', 'before_or_equal:today'],
            'sexo' => ['required', 'string', 'max:20'],
            'direccion' => ['required', 'string', 'max:255'],
        ];
    }
}
