<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $nombre
 * @property-read Nivel|null $nivel
 */
class Grado extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'grados';

    protected $fillable = [
        'colegio_id',
        'nivel_id',
        'nombre',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'nivel_id' => 'integer',
            'orden' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class, 'nivel_id');
    }

    public function secciones(): HasMany
    {
        return $this->hasMany(Seccion::class, 'grado_id');
    }

    /** @return HasMany<Tarifa, $this> */
    public function tarifas(): HasMany
    {
        return $this->hasMany(Tarifa::class, 'grado_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return ['nivel_id', 'nombre', 'orden', 'activo'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'grados.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'grados';
    }
}
