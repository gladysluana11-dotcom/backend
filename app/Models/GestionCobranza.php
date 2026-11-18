<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $fecha
 * @property Carbon|null $compromiso_pago_fecha
 */
class GestionCobranza extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'gestiones_cobranza';

    protected $fillable = [
        'colegio_id',
        'alumno_id',
        'usuario_id',
        'fecha',
        'medio_contacto',
        'resultado',
        'compromiso_pago_fecha',
        'compromiso_monto',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'alumno_id' => 'integer',
            'usuario_id' => 'integer',
            'fecha' => 'date',
            'compromiso_pago_fecha' => 'date',
            'compromiso_monto' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Alumno, $this> */
    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** @return list<string> */
    protected function atributosAuditables(): array
    {
        return [
            'alumno_id',
            'usuario_id',
            'fecha',
            'medio_contacto',
            'resultado',
            'compromiso_pago_fecha',
            'compromiso_monto',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'cobranza.gestion_registrada';
    }

    protected function entidadAuditable(): string
    {
        return 'gestiones_cobranza';
    }
}
