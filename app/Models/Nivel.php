<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nivel extends Model
{
    use BelongsToColegio, HasFactory;

    protected $table = 'niveles';

    protected $fillable = [
        'colegio_id',
        'nombre',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    public function grados(): HasMany
    {
        return $this->hasMany(Grado::class, 'nivel_id');
    }
}
