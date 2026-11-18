<?php

namespace App\Http\Requests;

use App\Models\Tarifa;
use Dedoc\Scramble\Attributes\IgnoreParam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

#[IgnoreParam('colegio_id', 'body')]
class GuardarTarifaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tarifa = $this->route('tarifa');

        return $tarifa instanceof Tarifa
            ? $this->user()?->can('update', $tarifa) ?? false
            : $this->user()?->can('create', Tarifa::class) ?? false;
    }

    /**
     * @return array<string, array<mixed>>
     */
    public function rules(): array
    {
        $colegioId = $this->user()?->colegio_id;
        $actualizar = $this->route('tarifa') instanceof Tarifa;
        $requerido = $actualizar ? 'sometimes' : 'required';

        return [
            'colegio_id' => ['prohibited'],
            'anio_escolar_id' => [
                $requerido,
                'integer',
                Rule::exists('anios_escolares', 'id')->where('colegio_id', $colegioId),
            ],
            'grado_id' => [
                $requerido,
                'integer',
                Rule::exists('grados', 'id')->where('colegio_id', $colegioId),
            ],
            'monto_matricula' => [$requerido, 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'monto_pension' => [$requerido, 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'numero_cuotas' => ['sometimes', 'required', 'integer', 'between:1,12'],
            'dia_vencimiento' => ['sometimes', 'required', 'integer', 'between:1,28'],
            'mes_primera_pension' => ['sometimes', 'required', 'integer', 'between:1,12'],
        ];
    }
}
