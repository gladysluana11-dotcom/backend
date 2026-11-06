<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\ConfiguracionColegio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActualizarConfiguracionColegioRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        return $this->user()?->can('update', ConfiguracionColegio::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'numero_cuotas_pension' => ['sometimes', 'required', 'integer', 'min:1', 'max:12'],
            'dia_vencimiento' => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'serie_recibos' => ['sometimes', 'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
            'frecuencia_registro_avance' => [
                'sometimes',
                'required',
                Rule::in(['semanal', 'quincenal', 'mensual']),
            ],
            'recargo_mora_activo' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
