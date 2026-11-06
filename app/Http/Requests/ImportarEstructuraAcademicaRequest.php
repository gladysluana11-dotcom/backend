<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\ImportacionAcademica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportarEstructuraAcademicaRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        return $this->user()?->can('import', ImportacionAcademica::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'anio_escolar_id' => [
                'required',
                'integer',
                Rule::exists('anios_escolares', 'id')
                    ->where('colegio_id', $this->user()?->colegio_id)
                    ->where('cerrado', false),
            ],
            'archivo' => [
                'required',
                'file',
                'max:2048',
                'extensions:csv,xlsx',
                'mimetypes:text/csv,text/plain,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        ];
    }
}
