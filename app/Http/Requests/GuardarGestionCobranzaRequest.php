<?php

namespace App\Http\Requests;

use App\Models\GestionCobranza;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GuardarGestionCobranzaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GestionCobranza::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'colegio_id' => ['prohibited'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'medio_contacto' => ['required', Rule::in(['llamada', 'whatsapp', 'presencial', 'correo'])],
            'resultado' => ['required', 'string', 'max:100'],
            'compromiso_pago_fecha' => ['nullable', 'date_format:Y-m-d'],
            'compromiso_monto' => ['nullable', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
