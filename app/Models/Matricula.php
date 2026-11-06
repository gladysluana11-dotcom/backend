<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Matricula extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'matriculas';

    protected $fillable = [
        'colegio_id',
        'alumno_id',
        'anio_escolar_id',
        'grado_id',
        'seccion_id',
        'usuario_id',
        'anio_escolar',
        'grado',
        'seccion',
        'estado',
        'fecha_matricula',
        'observaciones',
        'motivo',
        'fecha_retiro',
        'exceso_vacantes_motivo',
        'exceso_vacantes_autorizado_por_id',
    ];

    protected $casts = [
        'colegio_id' => 'integer',
        'alumno_id' => 'integer',
        'anio_escolar_id' => 'integer',
        'grado_id' => 'integer',
        'seccion_id' => 'integer',
        'usuario_id' => 'integer',
        'exceso_vacantes_autorizado_por_id' => 'integer',
        'fecha_matricula' => 'date',
        'fecha_retiro' => 'date',
    ];

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** @return BelongsTo<Colegio, $this> */
    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    /** @return BelongsTo<AnioEscolar, $this> */
    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }

    /** @return BelongsTo<Grado, $this> */
    public function gradoRegistro(): BelongsTo
    {
        return $this->belongsTo(Grado::class, 'grado_id');
    }

    /** @return BelongsTo<Seccion, $this> */
    public function seccionRegistro(): BelongsTo
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuarioRegistro(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** @return HasMany<MatriculaDocumento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(MatriculaDocumento::class, 'matricula_id');
    }

    /** @return HasMany<HistorialMatricula, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(HistorialMatricula::class, 'matricula_id')->orderBy('fecha');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return [
            'alumno_id',
            'anio_escolar_id',
            'grado_id',
            'seccion_id',
            'estado',
            'fecha_matricula',
            'motivo',
            'fecha_retiro',
            'exceso_vacantes_motivo',
            'exceso_vacantes_autorizado_por_id',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'matriculas.creada';
    }

    protected function entidadAuditable(): string
    {
        return 'matriculas';
    }
}
