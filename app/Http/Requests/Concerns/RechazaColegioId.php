<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait RechazaColegioId
{
    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (array_key_exists('colegio_id', $this->all())) {
                    $validator->errors()->add(
                        'colegio_id',
                        'El colegio se determina según el usuario autenticado.',
                    );
                }
            },
        ];
    }
}
