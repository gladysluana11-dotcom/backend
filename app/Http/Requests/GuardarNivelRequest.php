<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Nivel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarNivelRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $nivel = $this->route('nivel');

        return $this->user()?->can(
            $nivel instanceof Nivel ? 'update' : 'create',
            $nivel ?? Nivel::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $nivel = $this->route('nivel');

        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('niveles', 'nombre')
                    ->where('colegio_id', $this->user()?->colegio_id)
                    ->ignore($nivel?->id),
            ],
            'orden' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'activo' => ['prohibited'],
        ];
    }
}
