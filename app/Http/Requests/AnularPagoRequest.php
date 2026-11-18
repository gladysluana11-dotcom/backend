<?php

namespace App\Http\Requests;

use App\Models\Pago;
use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;

#[IgnoreParam('colegio_id', 'body')]
class AnularPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $pago = $this->route('pago');

        return $pago instanceof Pago && ($this->user()?->can('annul', $pago) ?? false);
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'colegio_id' => ['prohibited'],
            'motivo' => ['required', 'string', 'max:1000'],
        ];
    }
}
