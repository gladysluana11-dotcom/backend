<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periodo extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'periodos';

    protected $fillable = [
        'colegio_id',
        'anio_escolar_id',
        'nombre',
        'orden',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'anio_escolar_id' => 'integer',
            'orden' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(UnidadDidactica::class, 'periodo_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return ['anio_escolar_id', 'nombre', 'orden', 'fecha_inicio', 'fecha_fin', 'activo'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'periodos.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'periodos';
    }
}
