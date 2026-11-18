<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $fecha */
class CierreCaja extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'cierres_caja';

    protected $fillable = [
        'colegio_id',
        'usuario_id',
        'fecha',
        'observacion',
        'total',
        'resumen',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'usuario_id' => 'integer',
            'fecha' => 'date',
            'total' => 'decimal:2',
            'resumen' => 'array',
        ];
    }

    /** @return BelongsTo<Usuario, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /** @return list<string> */
    protected function atributosAuditables(): array
    {
        return ['usuario_id', 'fecha', 'total'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'caja.cierre_registrado';
    }

    protected function entidadAuditable(): string
    {
        return 'cierres_caja';
    }
}
