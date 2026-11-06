<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RechazaColegioId;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class ListarEstructuraAcademicaRequest extends FormRequest
{
    use RechazaColegioId;

    protected function prepareForValidation(): void
    {
        foreach (['activo', 'cerrado'] as $campo) {
            $valor = $this->query($campo);

            if (is_string($valor) && in_array(mb_strtolower($valor), ['true', 'false'], true)) {
                $this->merge([$campo => mb_strtolower($valor) === 'true']);
            }
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', $this->modelo()) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'sort' => ['sometimes', 'string', Rule::in($this->columnasOrdenables())],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'buscar' => ['sometimes', 'string', 'max:100'],
            'activo' => ['sometimes', 'boolean'],
            ...$this->reglasAdicionales(),
        ];
    }

    /** @return class-string */
    abstract protected function modelo(): string;

    /** @return list<string> */
    abstract protected function columnasOrdenables(): array;

    /** @return array<string, array<mixed>> */
    protected function reglasAdicionales(): array
    {
        return [];
    }
}
