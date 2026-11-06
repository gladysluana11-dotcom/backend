<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seccion extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'secciones';

    protected $fillable = [
        'colegio_id',
        'grado_id',
        'anio_escolar_id',
        'nombre',
        'capacidad',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'grado_id' => 'integer',
            'anio_escolar_id' => 'integer',
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

    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return ['grado_id', 'anio_escolar_id', 'nombre', 'capacidad', 'activo'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'secciones.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'secciones';
    }
}
