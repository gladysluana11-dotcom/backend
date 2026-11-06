<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Alumno;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmarImportacionAlumnosRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        return $this->user()?->can('import', Alumno::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
        ];
    }
}
