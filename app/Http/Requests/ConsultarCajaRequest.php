<?php

namespace App\Http\Requests;

use App\Models\CierreCaja;
use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;

#[IgnoreParam('colegio_id', 'query')]
class ConsultarCajaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CierreCaja::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'colegio_id' => ['prohibited'],
            'fecha' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }
}
