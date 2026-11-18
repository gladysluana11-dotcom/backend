<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property 'matricula'|'pension' $concepto
 * @property Carbon $vencimiento
 * @property Carbon|null $anulada_en
 * @property-read Alumno $alumno
 * @property-read Matricula $matricula
 */
class Cuota extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'cuotas';

    protected $fillable = [
        'colegio_id',
        'matricula_id',
        'alumno_id',
        'anio_escolar_id',
        'concepto',
        'numero',
        'descripcion',
        'monto_original',
        'vencimiento',
        'anulada_en',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'matricula_id' => 'integer',
            'alumno_id' => 'integer',
            'anio_escolar_id' => 'integer',
            'numero' => 'integer',
            'monto_original' => 'decimal:2',
            'vencimiento' => 'date',
            'anulada_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Las cuotas no se eliminan; deben anularse con motivo.');
        });
    }

    /** @return BelongsTo<Matricula, $this> */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** @return BelongsTo<AnioEscolar, $this> */
    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }

    /** @return HasMany<Descuento, $this> */
    public function descuentos(): HasMany
    {
        return $this->hasMany(Descuento::class, 'cuota_id');
    }

    /** @return HasMany<AplicacionPago, $this> */
    public function aplicacionesPago(): HasMany
    {
        return $this->hasMany(AplicacionPago::class, 'cuota_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return [
            'concepto',
            'numero',
            'descripcion',
            'monto_original',
            'vencimiento',
            'anulada_en',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'cuotas.creada';
    }

    protected function entidadAuditable(): string
    {
        return 'cuotas';
    }
}
