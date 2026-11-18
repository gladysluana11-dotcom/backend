<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tarifa extends Model
{
    use Auditable, BelongsToColegio, HasFactory, SoftDeletes;

    protected $table = 'tarifas';

    protected $fillable = [
        'colegio_id',
        'anio_escolar_id',
        'grado_id',
        'monto_matricula',
        'monto_pension',
        'numero_cuotas',
        'dia_vencimiento',
        'mes_primera_pension',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'anio_escolar_id' => 'integer',
            'grado_id' => 'integer',
            'monto_matricula' => 'decimal:2',
            'monto_pension' => 'decimal:2',
            'numero_cuotas' => 'integer',
            'dia_vencimiento' => 'integer',
            'mes_primera_pension' => 'integer',
        ];
    }

    /** @return BelongsTo<AnioEscolar, $this> */
    public function anioEscolar(): BelongsTo
    {
        return $this->belongsTo(AnioEscolar::class, 'anio_escolar_id');
    }

    /** @return BelongsTo<Grado, $this> */
    public function grado(): BelongsTo
    {
        return $this->belongsTo(Grado::class, 'grado_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return [
            'anio_escolar_id',
            'grado_id',
            'monto_matricula',
            'monto_pension',
            'numero_cuotas',
            'dia_vencimiento',
            'mes_primera_pension',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'tarifas.creada';
    }

    protected function entidadAuditable(): string
    {
        return 'tarifas';
    }
}
