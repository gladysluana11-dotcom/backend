<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialMatricula extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'historial_matriculas';

    protected $fillable = [
        'colegio_id',
        'matricula_id',
        'evento',
        'grado_anterior_id',
        'seccion_anterior_id',
        'grado_nuevo_id',
        'seccion_nueva_id',
        'estado_anterior',
        'estado_nuevo',
        'motivo',
        'fecha',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'matricula_id' => 'integer',
            'grado_anterior_id' => 'integer',
            'seccion_anterior_id' => 'integer',
            'grado_nuevo_id' => 'integer',
            'seccion_nueva_id' => 'integer',
            'usuario_id' => 'integer',
            'fecha' => 'datetime',
        ];
    }

    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    /** @return list<string> */
    protected function atributosAuditables(): array
    {
        return [
            'matricula_id',
            'evento',
            'grado_anterior_id',
            'seccion_anterior_id',
            'grado_nuevo_id',
            'seccion_nueva_id',
            'estado_anterior',
            'estado_nuevo',
            'motivo',
            'fecha',
            'usuario_id',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'historial_matriculas.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'historial_matriculas';
    }
}
