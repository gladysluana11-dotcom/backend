<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Apoderado;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarApoderadoRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $apoderado = $this->route('apoderado');

        return $apoderado instanceof Apoderado
            && ($this->user()?->can('update', $apoderado) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['sometimes', 'required', 'string', 'max:255'],
            'apellido' => ['sometimes', 'required', 'string', 'max:255'],
        ];
    }
}
