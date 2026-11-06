<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Alumno;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarAlumnoRequest extends FormRequest
{
    use RechazaColegioId;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $alumno = Alumno::query()->find($this->route('id'));

        if ($alumno instanceof Alumno) {
            return $this->user()?->can('update', $alumno) ?? false;
        }

        $usuario = $this->user();

        return $usuario instanceof Usuario
            && $usuario->can('update', new Alumno(['colegio_id' => $usuario->colegio_id]));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $alumno = Alumno::query()->find($this->route('id'));

        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'max:255'],
            'tipo_documento' => ['sometimes', 'required', 'string', 'max:30'],
            'documento_identidad' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('alumnos', 'documento_identidad')
                    ->where('colegio_id', $this->user()?->colegio_id)
                    ->ignore($alumno instanceof Alumno ? $alumno->id : null),
            ],
            'fecha_nacimiento' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'sexo' => ['sometimes', 'required', 'string', 'max:20'],
            'direccion' => ['sometimes', 'required', 'string', 'max:255'],
            'estado' => ['sometimes', 'required', 'in:activo,retirado,egresado'],
            'motivo' => [
                Rule::requiredIf(
                    $alumno instanceof Alumno
                    && $this->has('estado')
                    && $this->input('estado') !== $alumno->estado,
                ),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }
}
