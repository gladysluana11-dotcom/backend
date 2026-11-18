<?php

namespace App\Exceptions;

use RuntimeException;

class ConflictoIdempotencia extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('La clave Idempotency-Key ya fue utilizada con un cuerpo diferente.');
    }
}
