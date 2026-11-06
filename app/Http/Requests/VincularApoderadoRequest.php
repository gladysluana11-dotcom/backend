<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Alumno;
use App\Models\Apoderado;
use App\Models\Usuario;
use Illuminate\Foundation\Http\FormRequest;

class VincularApoderadoRequest extends FormRequest
{
    use RechazaColegioId;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $alumno = $this->route('alumno');
        $apoderado = $this->route('apoderado');
        $usuario = $this->user();

        return $alumno instanceof Alumno
            && $apoderado instanceof Apoderado
            && $usuario instanceof Usuario
            && $usuario->can('update', $alumno)
            && $usuario->can('update', $apoderado);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'parentesco' => ['required', 'string', 'max:80'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'responsable_pago' => ['required', 'boolean'],
        ];
    }
}
