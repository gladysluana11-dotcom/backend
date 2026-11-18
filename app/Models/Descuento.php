<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property 'beca'|'descuento'|'exoneracion' $tipo
 * @property 'monto'|'porcentaje' $modo
 * @property Carbon|null $anulada_en
 */
class Descuento extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'descuentos';

    protected $fillable = [
        'colegio_id',
        'cuota_id',
        'tipo',
        'modo',
        'valor',
        'monto_calculado',
        'motivo',
        'usuario_id',
        'anulada_en',
        'motivo_anulacion',
        'anulado_por_usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'cuota_id' => 'integer',
            'valor' => 'decimal:2',
            'monto_calculado' => 'decimal:2',
            'usuario_id' => 'integer',
            'anulada_en' => 'datetime',
            'anulado_por_usuario_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Los descuentos se anulan con motivo y no se eliminan.');
        });
    }

    /** @return BelongsTo<Cuota, $this> */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'cuota_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return [
            'cuota_id',
            'tipo',
            'modo',
            'valor',
            'monto_calculado',
            'anulada_en',
            'anulado_por_usuario_id',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'descuentos.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'descuentos';
    }
}
