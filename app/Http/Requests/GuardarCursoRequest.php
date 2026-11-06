<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Curso;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarCursoRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $curso = $this->route('curso');

        return $this->user()?->can(
            $curso instanceof Curso ? 'update' : 'create',
            $curso ?? Curso::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $curso = $this->route('curso');
        $colegioId = $this->user()?->colegio_id;
        $nivelId = $this->input('nivel_id', $curso?->nivel_id);
        $gradoId = $this->input('grado_id', $curso?->grado_id);

        return [
            'nivel_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('niveles', 'id')->where('colegio_id', $colegioId),
            ],
            'grado_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('grados', 'id')->where('colegio_id', $colegioId)->where('nivel_id', $nivelId),
            ],
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('cursos', 'nombre')
                    ->where('colegio_id', $colegioId)
                    ->where('nivel_id', $nivelId)
                    ->where('grado_id', $gradoId)
                    ->ignore($curso?->id),
            ],
            'activo' => ['prohibited'],
        ];
    }
}
