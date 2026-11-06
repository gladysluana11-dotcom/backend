<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Matricula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoMatriculaRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $matricula = $this->route('matricula');

        return $matricula instanceof Matricula
            && ($this->user()?->can('manage', $matricula) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => [
                'required',
                Rule::in(['pre_inscrito', 'pendiente_documentos', 'matriculado', 'retirado', 'anulado']),
            ],
            'motivo' => ['required_if:estado,retirado,anulado', 'nullable', 'string', 'max:1000'],
            'fecha_retiro' => ['required_if:estado,retirado', 'nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
