<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class AplicacionPago extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'aplicacion_pagos';

    protected $fillable = [
        'colegio_id',
        'pago_id',
        'cuota_id',
        'monto_aplicado',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'pago_id' => 'integer',
            'cuota_id' => 'integer',
            'monto_aplicado' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (): void {
            throw new LogicException('Las aplicaciones de pago no se eliminan.');
        });
    }

    /** @return BelongsTo<Pago, $this> */
    public function pago(): BelongsTo
    {
        return $this->belongsTo(Pago::class, 'pago_id');
    }

    /** @return BelongsTo<Cuota, $this> */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(Cuota::class, 'cuota_id');
    }

    /** @return list<string> */
    protected function atributosAuditables(): array
    {
        return ['pago_id', 'cuota_id', 'monto_aplicado'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'pagos.aplicacion_registrada';
    }

    protected function entidadAuditable(): string
    {
        return 'aplicacion_pagos';
    }
}
