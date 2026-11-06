<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Grado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarGradoRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $grado = $this->route('grado');

        return $this->user()?->can(
            $grado instanceof Grado ? 'update' : 'create',
            $grado ?? Grado::class,
        ) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $grado = $this->route('grado');
        $colegioId = $this->user()?->colegio_id;

        return [
            'nivel_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('niveles', 'id')->where('colegio_id', $colegioId),
            ],
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('grados', 'nombre')
                    ->where('colegio_id', $colegioId)
                    ->where('nivel_id', $this->input('nivel_id', $grado?->nivel_id))
                    ->ignore($grado?->id),
            ],
            'orden' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'activo' => ['prohibited'],
        ];
    }
}
