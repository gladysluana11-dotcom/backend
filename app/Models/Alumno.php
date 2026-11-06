<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Alumno extends Model
{
    use BelongsToColegio, HasFactory;

    protected $table = 'alumnos';

    protected $fillable = [
        'colegio_id',
        'nombre',
        'apellido',
        'documento_identidad',
        'fecha_nacimiento',
        'sexo',
        'direccion',
        'estado',
        'activo',
    ];

    protected $casts = [
        'colegio_id' => 'integer',
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    public function apoderados(): HasMany
    {
        return $this->hasMany(Apoderado::class, 'alumno_id');
    }
}
