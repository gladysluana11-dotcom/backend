<?php

namespace App\Http\Requests;

use App\Models\Pago;
use App\Support\Monto;
use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

#[IgnoreParam('idempotency_key', 'body')]
#[IgnoreParam('colegio_id', 'body')]
class RegistrarPagoRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Pago::class) ?? false;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $colegioId = $this->user()?->colegio_id;

        return [
            'colegio_id' => ['prohibited'],
            'idempotency_key' => ['required', 'string', 'max:255'],
            'matricula_id' => [
                'required',
                'integer',
                Rule::exists('matriculas', 'id')->where('colegio_id', $colegioId),
            ],
            'fecha_pago' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'medio' => ['required', Rule::in(['efectivo', 'deposito', 'transferencia', 'billetera_digital'])],
            'numero_operacion' => [
                Rule::requiredIf(in_array($this->input('medio'), ['deposito', 'transferencia'], true)),
                'nullable',
                'string',
                'max:100',
            ],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'aplicaciones' => ['sometimes', 'array'],
            'aplicaciones.*.cuota_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('cuotas', 'id')->where('colegio_id', $colegioId),
            ],
            'aplicaciones.*.monto' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()
                    || ! is_array($this->input('aplicaciones'))
                    || $this->input('aplicaciones') === []) {
                    return;
                }

                $totalAplicaciones = array_sum(array_map(
                    fn (array $aplicacion): int => Monto::aCentimos($aplicacion['monto']),
                    $this->input('aplicaciones'),
                ));

                if ($totalAplicaciones !== Monto::aCentimos($this->input('monto'))) {
                    $validator->errors()->add('aplicaciones', 'La suma de las aplicaciones debe ser igual al monto del pago.');
                }
            },
        ];
    }
}
