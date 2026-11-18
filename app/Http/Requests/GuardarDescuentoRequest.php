<?php

namespace App\Http\Requests;

use App\Models\Cuota;
use App\Models\Descuento;
use App\Models\Matricula;
use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[IgnoreParam('colegio_id', 'body')]
class GuardarDescuentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $cuota = $this->route('cuota');
        $matricula = $this->route('matricula');

        if ($cuota instanceof Cuota) {
            return $this->user()?->can('applyTo', [Descuento::class, $cuota]) ?? false;
        }

        return $matricula instanceof Matricula
            && ($this->user()?->can('applyToMatricula', [Descuento::class, $matricula]) ?? false);
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        return [
            'colegio_id' => ['prohibited'],
            'tipo' => ['required', 'string', 'in:beca,descuento,exoneracion'],
            'modo' => ['required', 'string', 'in:monto,porcentaje'],
            'valor' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'motivo' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    ! $validator->errors()->has('valor')
                    && $this->input('modo') === 'porcentaje'
                    && (float) $this->input('valor') > 100
                ) {
                    $validator->errors()->add('valor', 'El porcentaje no puede superar 100.');
                }
            },
        ];
    }
}
