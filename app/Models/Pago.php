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
 * @property 'vigente'|'anulado' $estado
 * @property Carbon $fecha_pago
 * @property Carbon|null $anulada_en
 */
class Pago extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'pagos';

    protected $fillable = [
        'colegio_id',
        'matricula_id',
        'alumno_id',
        'usuario_id',
        'fecha_pago',
        'monto',
        'medio',
        'numero_operacion',
        'observacion',
        'estado',
        'serie_constancia',
        'numero_constancia',
        'constancia',
        'anulada_en',
        'anulado_por_usuario_id',
        'motivo_anulacion',
        'voucher_path',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'matricula_id' => 'integer',
            'alumno_id' => 'integer',
            'usuario_id' => 'integer',
            'fecha_pago' => 'date',
            'monto' => 'decimal:2',
            'numero_constancia' => 'integer',
            'anulada_en' => 'datetime',
            'anulado_por_usuario_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Los pagos no se eliminan; deben anularse con motivo.');
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

    /** @return BelongsTo<Colegio, $this> */
    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function anulador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'anulado_por_usuario_id');
    }

    /** @return HasMany<AplicacionPago, $this> */
    public function aplicaciones(): HasMany
    {
        return $this->hasMany(AplicacionPago::class, 'pago_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return [
            'matricula_id',
            'alumno_id',
            'fecha_pago',
            'monto',
            'medio',
            'estado',
            'serie_constancia',
            'numero_constancia',
            'constancia',
            'anulada_en',
            'anulado_por_usuario_id',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'pagos.registrado';
    }

    protected function entidadAuditable(): string
    {
        return 'pagos';
    }
}
