<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\DocumentoRequerido;
use Illuminate\Foundation\Http\FormRequest;

class GuardarDocumentoRequeridoRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $documento = $this->route('documento');

        return $documento instanceof DocumentoRequerido
            ? ($this->user()?->can('update', $documento) ?? false)
            : ($this->user()?->can('create', DocumentoRequerido::class) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:120'],
            'obligatorio' => ['required', 'boolean'],
            'activo' => ['sometimes', 'boolean'],
        ];
    }
}
