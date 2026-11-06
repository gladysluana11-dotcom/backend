<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Curso extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'cursos';

    protected $fillable = [
        'colegio_id',
        'nivel_id',
        'grado_id',
        'nombre',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'nivel_id' => 'integer',
            'grado_id' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function nivel(): BelongsTo
    {
        return $this->belongsTo(Nivel::class, 'nivel_id');
    }

    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class, 'grado_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return ['nivel_id', 'grado_id', 'nombre', 'activo'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'cursos.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'cursos';
    }
}
