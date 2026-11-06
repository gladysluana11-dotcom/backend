<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacionAlumno extends Model
{
    use BelongsToColegio, HasFactory;

    protected $table = 'importaciones_alumnos';

    protected $fillable = [
        'colegio_id',
        'usuario_id',
        'token_hash',
        'ruta_archivo',
        'sha256',
        'expira_en',
        'confirmada_en',
    ];

    protected $hidden = ['token_hash', 'ruta_archivo', 'sha256'];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'usuario_id' => 'integer',
            'expira_en' => 'datetime',
            'confirmada_en' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
