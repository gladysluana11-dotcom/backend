<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Model;

class SecuenciaConstancia extends Model
{
    use BelongsToColegio, \Illuminate\Database\Eloquent\Factories\HasFactory;

    protected $table = 'secuencia_constancias';

    protected $fillable = ['colegio_id', 'serie', 'ultimo_numero'];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'ultimo_numero' => 'integer',
        ];
    }
}
