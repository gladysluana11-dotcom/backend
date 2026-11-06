<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Periodo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class GuardarPeriodoRequest extends FormRequest
{
    use RechazaColegioId;

    protected function prepareForValidation(): void
    {
        $periodo = $this->route('periodo');

        if ($periodo instanceof Periodo) {
            $this->merge([
                'fecha_inicio' => $this->input('fecha_inicio', Carbon::parse($periodo->fecha_inicio)->toDateString()),
                'fecha_fin' => $this->input('fecha_fin', Carbon::parse($periodo->fecha_fin)->toDateString()),
            ]);
        }
    }

    public function authorize(): bool
    {
        $periodo = $this->route('periodo');

        return $this->user()?->can(
            $periodo instanceof Periodo ? 'update' : 'create',
            $periodo ?? Periodo::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $periodo = $this->route('periodo');
        $colegioId = $this->user()?->colegio_id;
        $anioEscolarId = $this->input('anio_escolar_id', $periodo?->anio_escolar_id);

        return [
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
                'max:100',
                Rule::unique('periodos', 'nombre')
                    ->where('colegio_id', $colegioId)
                    ->where('anio_escolar_id', $anioEscolarId)
                    ->ignore($periodo?->id),
            ],
            'orden' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'fecha_inicio' => ['sometimes', 'required', 'date'],
            'fecha_fin' => ['sometimes', 'required', 'date', 'after:fecha_inicio'],
            'activo' => ['prohibited'],
        ];
    }
}
