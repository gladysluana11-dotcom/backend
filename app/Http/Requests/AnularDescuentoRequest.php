<?php

namespace App\Http\Requests;

use App\Models\Descuento;
use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;

#[IgnoreParam('colegio_id', 'body')]
class AnularDescuentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $descuento = $this->route('descuento');

        return $descuento instanceof Descuento
            && ($this->user()?->can('annul', $descuento) ?? false);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'colegio_id' => ['prohibited'],
            'motivo' => ['required', 'string', 'max:5000'],
        ];
    }
}
