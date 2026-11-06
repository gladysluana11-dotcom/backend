<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seccion extends Model
{
    use BelongsToColegio, HasFactory;

    protected $table = 'secciones';

    protected $fillable = [
        'colegio_id',
        'grado_id',
        'nombre',
        'capacidad',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'grado_id' => 'integer',
            'capacidad' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class, 'grado_id');
    }
}
