<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use App\Models\MatriculaDocumento;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarDocumentoMatriculaRequest extends FormRequest
{
    use RechazaColegioId;

    public function authorize(): bool
    {
        $documento = $this->route('documento');

        return $documento instanceof MatriculaDocumento
            && ($this->user()?->can('manage', $documento->matricula) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'estado' => ['required', 'in:pendiente,entregado'],
            'fecha_entrega' => ['required_if:estado,entregado', 'nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
