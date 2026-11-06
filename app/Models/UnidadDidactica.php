<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnidadDidactica extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'unidades_didacticas';

    protected $fillable = [
        'colegio_id',
        'periodo_id',
        'nombre',
        'orden',
        'fecha_inicio',
        'fecha_fin',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'periodo_id' => 'integer',
            'orden' => 'integer',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'activo' => 'boolean',
        ];
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class, 'periodo_id');
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return ['periodo_id', 'nombre', 'orden', 'fecha_inicio', 'fecha_fin', 'activo'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'unidades_didacticas.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'unidades_didacticas';
    }
}
