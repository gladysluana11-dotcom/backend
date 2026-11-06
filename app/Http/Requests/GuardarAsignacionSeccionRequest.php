<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\AsignacionSeccion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarAsignacionSeccionRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        return $this->user()?->can('createAssignment', AsignacionSeccion::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'anio_escolar_id' => [
                'required',
                'integer',
                Rule::exists('anios_escolares', 'id')->where('colegio_id', $colegioId)->where('cerrado', false),
            ],
            'seccion_id' => [
                'required',
                'integer',
                Rule::exists('secciones', 'id')
                    ->where('colegio_id', $colegioId)
                    ->where('anio_escolar_id', $this->input('anio_escolar_id'))
                    ->where('activo', true),
            ],
            'usuario_id' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id')->where('colegio_id', $colegioId)->where('activo', true),
            ],
        ];
    }
}
