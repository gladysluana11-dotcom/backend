<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\UnidadDidactica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class GuardarUnidadDidacticaRequest extends FormRequest
{
    use RechazaColegioId;

    protected function prepareForValidation(): void
    {
        $unidad = $this->route('unidad_didactica');

        if ($unidad instanceof UnidadDidactica) {
            $this->merge([
                'fecha_inicio' => $this->input('fecha_inicio', Carbon::parse($unidad->fecha_inicio)->toDateString()),
                'fecha_fin' => $this->input('fecha_fin', Carbon::parse($unidad->fecha_fin)->toDateString()),
            ]);
        }
    }

    public function authorize(): bool
    {
        $unidad = $this->route('unidad_didactica');

        return $this->user()?->can(
            $unidad instanceof UnidadDidactica ? 'update' : 'create',
            $unidad ?? UnidadDidactica::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'periodo_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('periodos', 'id')->where('colegio_id', $this->user()?->colegio_id),
            ],
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'orden' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
            'fecha_inicio' => ['sometimes', 'required', 'date'],
            'fecha_fin' => ['sometimes', 'required', 'date', 'after:fecha_inicio'],
            'activo' => ['prohibited'],
        ];
    }
}
