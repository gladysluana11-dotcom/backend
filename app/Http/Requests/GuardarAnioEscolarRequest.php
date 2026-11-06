<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\AnioEscolar;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class GuardarAnioEscolarRequest extends FormRequest
{
    use RechazaColegioId;

    protected function prepareForValidation(): void
    {
        $anio = $this->route('anio_escolar');

        if ($anio instanceof AnioEscolar) {
            $this->merge([
                'fecha_inicio' => $this->input(
                    'fecha_inicio',
                    $anio->fecha_inicio === null ? null : Carbon::parse($anio->fecha_inicio)->toDateString(),
                ),
                'fecha_fin' => $this->input(
                    'fecha_fin',
                    $anio->fecha_fin === null ? null : Carbon::parse($anio->fecha_fin)->toDateString(),
                ),
            ]);
        }
    }

    public function authorize(): bool
    {
        $anio = $this->route('anio_escolar');

        return $this->user()?->can(
            $anio instanceof AnioEscolar ? 'update' : 'create',
            $anio ?? AnioEscolar::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $anio = $this->route('anio_escolar');

        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'regex:/^\d{4}$/',
                Rule::unique('anios_escolares', 'nombre')
                    ->where('colegio_id', $this->user()?->colegio_id)
                    ->ignore($anio?->id),
            ],
            'fecha_inicio' => ['sometimes', 'required', 'date'],
            'fecha_fin' => ['sometimes', 'required', 'date', 'after:fecha_inicio'],
            'activo' => ['prohibited'],
            'cerrado' => ['prohibited'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'nombre.regex' => 'El nombre del año escolar debe tener cuatro dígitos.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
        ];
    }
}
