<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\FuncionColegio;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarFuncionesColegioRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        return $this->user()?->can('update', FuncionColegio::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'portal_apoderados' => ['sometimes', 'required', 'boolean'],
            'notificaciones_push' => ['sometimes', 'required', 'boolean'],
            'recargo_mora' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
