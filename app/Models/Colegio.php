<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Colegio extends Model
{
    use HasFactory;

    protected $table = 'colegios';

    protected $fillable = [
        'nombre',
        'subdominio',
        'logo',
        'colores',
        'direccion',
        'telefono',
        'correo_contacto',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'colores' => 'array',
    ];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'colegio_id');
    }

    public function alumnos(): HasMany
    {
        return $this->hasMany(Alumno::class, 'colegio_id');
    }

    public function aniosEscolares(): HasMany
    {
        return $this->hasMany(AnioEscolar::class, 'colegio_id');
    }

    public function niveles(): HasMany
    {
        return $this->hasMany(Nivel::class, 'colegio_id');
    }
}
