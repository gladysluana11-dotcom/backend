<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuncionColegio extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'funciones_colegio';

    protected $fillable = [
        'colegio_id',
        'portal_apoderados',
        'notificaciones_push',
        'recargo_mora',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'portal_apoderados' => 'boolean',
            'notificaciones_push' => 'boolean',
            'recargo_mora' => 'boolean',
        ];
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }

    public static function estaActiva(string $nombre, int $colegioId): bool
    {
        if (! in_array($nombre, ['portal_apoderados', 'notificaciones_push', 'recargo_mora'], true)) {
            return false;
        }

        return (bool) static::query()
            ->where('colegio_id', $colegioId)
            ->value($nombre);
    }

    /**
     * @return list<string>
     */
    protected function atributosAuditables(): array
    {
        return ['portal_apoderados', 'notificaciones_push', 'recargo_mora'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'funciones.actualizadas';
    }

    protected function entidadAuditable(): string
    {
        return 'funciones_colegio';
    }
}
