<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Matricula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarSeccionMatriculaRequest extends FormRequest
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
        $matricula = $this->route('matricula');

        return [
            'grado_id' => [
                'required',
                'integer',
                Rule::exists('grados', 'id')->where('colegio_id', $this->user()?->colegio_id),
            ],
            'seccion_id' => [
                'required',
                'integer',
                Rule::exists('secciones', 'id')
                    ->where('colegio_id', $this->user()?->colegio_id)
                    ->where('anio_escolar_id', $matricula instanceof Matricula ? $matricula->anio_escolar_id : null)
                    ->where('grado_id', $this->input('grado_id'))
                    ->where('activo', true),
            ],
            'motivo' => ['required', 'string', 'max:1000'],
            'fecha' => ['required', 'date', 'before_or_equal:today'],
            'autorizar_exceso' => ['sometimes', 'boolean'],
            'motivo_exceso' => ['required_if:autorizar_exceso,true', 'nullable', 'string', 'max:1000'],
        ];
    }
}
