<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HistorialEstadoAlumno extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'historial_estados_alumnos';

    protected $fillable = [
        'colegio_id',
        'alumno_id',
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
            'alumno_id' => 'integer',
            'fecha' => 'datetime',
            'usuario_id' => 'integer',
        ];
    }

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** @return list<string> */
    protected function atributosAuditables(): array
    {
        return ['alumno_id', 'estado_anterior', 'estado_nuevo', 'motivo', 'fecha', 'usuario_id'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'alumnos.estado_cambiado';
    }

    protected function entidadAuditable(): string
    {
        return 'historial_estados_alumnos';
    }
}
