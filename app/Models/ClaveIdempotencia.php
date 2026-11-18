<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/** @property Carbon $vence_en */
class ClaveIdempotencia extends Model
{
    use BelongsToColegio;
    use HasFactory;

    protected $table = 'clave_idempotencias';

    protected $fillable = [
        'colegio_id',
        'usuario_id',
        'clave',
        'hash_cuerpo',
        'respuesta',
        'vence_en',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'usuario_id' => 'integer',
            'respuesta' => 'array',
            'vence_en' => 'datetime',
        ];
    }
}
