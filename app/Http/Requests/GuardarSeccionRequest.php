<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Seccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarSeccionRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $seccion = $this->route('seccion');

        return $this->user()?->can(
            $seccion instanceof Seccion ? 'update' : 'create',
            $seccion ?? Seccion::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $seccion = $this->route('seccion');
        $colegioId = $this->user()?->colegio_id;
        $gradoId = $this->input('grado_id', $seccion?->grado_id);
        $anioEscolarId = $this->input('anio_escolar_id', $seccion?->anio_escolar_id);

        return [
            'grado_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('grados', 'id')->where('colegio_id', $colegioId),
            ],
            'anio_escolar_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('anios_escolares', 'id')
                    ->where('colegio_id', $colegioId)
                    ->where('cerrado', false),
            ],
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('secciones', 'nombre')
                    ->where('colegio_id', $colegioId)
                    ->where('grado_id', $gradoId)
                    ->where('anio_escolar_id', $anioEscolarId)
                    ->ignore($seccion?->id),
            ],
            'capacidad' => ['sometimes', 'required', 'integer', 'min:1', 'max:500'],
            'activo' => ['prohibited'],
        ];
    }
}
