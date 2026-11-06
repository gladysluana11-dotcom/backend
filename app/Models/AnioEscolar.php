<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnioEscolar extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'anios_escolares';

    protected $fillable = [
        'colegio_id',
        'nombre',
        'fecha_inicio',
        'fecha_fin',
        'activo',
        'cerrado',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activo' => 'boolean',
            'cerrado' => 'boolean',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class, 'anio_escolar_id');
    }

    public function periodos(): HasMany
    {
        return $this->hasMany(Periodo::class, 'anio_escolar_id');
    }

    public function asignaciones(): HasMany
    {
        return $this->hasMany(AsignacionDocente::class, 'anio_escolar_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return ['nombre', 'fecha_inicio', 'fecha_fin', 'activo', 'cerrado'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'anios_escolares.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'anios_escolares';
    }
}
