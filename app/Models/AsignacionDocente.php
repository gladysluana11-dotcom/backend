<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsignacionDocente extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'asignaciones_docentes';

    protected $fillable = [
        'colegio_id',
        'anio_escolar_id',
        'curso_id',
        'seccion_id',
        'docente_id',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'anio_escolar_id' => 'integer',
            'curso_id' => 'integer',
            'seccion_id' => 'integer',
            'docente_id' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<AnioEscolar, $this> */
    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }

    /** @return BelongsTo<Curso, $this> */
    public function curso(): BelongsTo
    {
        return $this->belongsTo(Curso::class, 'curso_id');
    }

    /** @return BelongsTo<Seccion, $this> */
    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function docente(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'docente_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return ['anio_escolar_id', 'curso_id', 'seccion_id', 'docente_id', 'activo'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'asignaciones_docentes.creada';
    }

    protected function entidadAuditable(): string
    {
        return 'asignaciones_docentes';
    }
}
