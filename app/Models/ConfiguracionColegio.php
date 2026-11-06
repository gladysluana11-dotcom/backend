<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConfiguracionColegio extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'configuraciones_colegio';

    protected $fillable = [
        'colegio_id',
        'numero_cuotas_pension',
        'dia_vencimiento',
        'serie_recibos',
        'frecuencia_registro_avance',
        'recargo_mora_activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'numero_cuotas_pension' => 'integer',
            'dia_vencimiento' => 'integer',
            'recargo_mora_activo' => 'boolean',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return [
            'numero_cuotas_pension',
            'dia_vencimiento',
            'serie_recibos',
            'frecuencia_registro_avance',
            'recargo_mora_activo',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'configuracion.creada';
    }

    protected function entidadAuditable(): string
    {
        return 'configuraciones_colegio';
    }
}
