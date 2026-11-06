<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Matricula;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarMatriculaRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Matricula::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'alumno_id' => [
                'required',
                'integer',
                Rule::exists('alumnos', 'id')->where('colegio_id', $colegioId)->where('estado', 'activo'),
            ],
            'anio_escolar_id' => [
                'required',
                'integer',
                Rule::exists('anios_escolares', 'id')
                    ->where('colegio_id', $colegioId)
                    ->where('cerrado', false),
            ],
            'grado_id' => [
                'required',
                'integer',
                Rule::exists('grados', 'id')->where('colegio_id', $colegioId)->where('activo', true),
            ],
            'seccion_id' => [
                'required',
                'integer',
                Rule::exists('secciones', 'id')
                    ->where('colegio_id', $colegioId)
                    ->where('anio_escolar_id', $this->input('anio_escolar_id'))
                    ->where('grado_id', $this->input('grado_id'))
                    ->where('activo', true),
            ],
            'fecha_matricula' => ['required', 'date'],
            'estado' => [
                'sometimes',
                'in:pre_inscrito,pendiente_documentos,matriculado',
            ],
            'autorizar_exceso' => ['sometimes', 'boolean'],
            'motivo_exceso' => ['required_if:autorizar_exceso,true', 'nullable', 'string', 'max:1000'],
        ];
    }
}
