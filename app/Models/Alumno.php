<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property-read Pivot $pivot
 */
class Alumno extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'alumnos';

    protected $fillable = [
        'colegio_id',
        'nombre',
        'apellido',
        'tipo_documento',
        'documento_identidad',
        'fecha_nacimiento',
        'sexo',
        'direccion',
        'foto_path',
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

    /** @return BelongsToMany<Apoderado, $this> */
    public function apoderados(): BelongsToMany
    {
        return $this->belongsToMany(Apoderado::class, 'alumno_apoderado', 'alumno_id', 'apoderado_id')
            ->wherePivot('colegio_id', $this->colegio_id)
            ->withPivot(['colegio_id', 'parentesco', 'telefono', 'email', 'responsable_pago'])
            ->withTimestamps();
    }

    /** @return HasMany<Matricula, $this> */
    public function matriculas(): HasMany
    {
        return $this->hasMany(Matricula::class, 'alumno_id');
    }

    /** @return HasMany<HistorialEstadoAlumno, $this> */
    public function historialEstados(): HasMany
    {
        return $this->hasMany(HistorialEstadoAlumno::class, 'alumno_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return [
            'nombre',
            'apellido',
            'tipo_documento',
            'documento_identidad',
            'fecha_nacimiento',
            'sexo',
            'direccion',
            'estado',
            'activo',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'alumnos.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'alumnos';
    }
}
