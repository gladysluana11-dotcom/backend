<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionSeccion extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'asignaciones_secciones';

    protected $fillable = [
        'colegio_id',
        'anio_escolar_id',
        'seccion_id',
        'usuario_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'anio_escolar_id' => 'integer',
            'seccion_id' => 'integer',
            'usuario_id' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** @return list<string> */
    protected function atributosAuditables(): array
    {
        return ['anio_escolar_id', 'seccion_id', 'usuario_id', 'activo'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'asignaciones_secciones.creada';
    }

    protected function entidadAuditable(): string
    {
        return 'asignaciones_secciones';
    }
}
