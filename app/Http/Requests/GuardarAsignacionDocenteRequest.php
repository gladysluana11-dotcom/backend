<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\AsignacionDocente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarAsignacionDocenteRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $asignacion = $this->route('asignacion');

        return $this->user()?->can(
            $asignacion instanceof AsignacionDocente ? 'update' : 'create',
            $asignacion ?? AsignacionDocente::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'anio_escolar_id' => [
                'required',
                'integer',
                Rule::exists('anios_escolares', 'id')
                    ->where('colegio_id', $colegioId)
                    ->where('cerrado', false),
            ],
            'curso_id' => [
                'required',
                'integer',
                Rule::exists('cursos', 'id')->where('colegio_id', $colegioId)->where('activo', true),
            ],
            'seccion_id' => [
                'required',
                'integer',
                Rule::exists('secciones', 'id')
                    ->where('colegio_id', $colegioId)
                    ->where('anio_escolar_id', $this->input('anio_escolar_id'))
                    ->where('activo', true),
            ],
            'docente_id' => [
                'required',
                'integer',
                Rule::exists('usuarios', 'id')->where('colegio_id', $colegioId)->where('activo', true),
            ],
            'activo' => ['prohibited'],
        ];
    }
}
