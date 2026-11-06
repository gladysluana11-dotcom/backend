<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\Alumno;
use Illuminate\Foundation\Http\FormRequest;

class ImportarAlumnosRequest extends FormRequest
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
            'archivo' => [
                'required',
                'file',
                'max:2048',
                'extensions:csv,xlsx',
                'mimetypes:text/csv,text/plain,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ];
    }
}
