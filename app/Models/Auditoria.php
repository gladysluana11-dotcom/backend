<?php

namespace App\Models;

use Database\Factories\AuditoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class Auditoria extends Model
{
    /** @use HasFactory<AuditoriaFactory> */
    use HasFactory;

    protected $table = 'auditorias';

    public $timestamps = false;

    protected $fillable = [
        'colegio_id',
        'usuario_id',
        'evento',
        'entidad',
        'entidad_id',
        'valores_anteriores',
        'valores_nuevos',
        'ip',
        'user_agent',
        'fecha',
    ];

    protected $casts = [
        'colegio_id' => 'integer',
        'usuario_id' => 'integer',
        'entidad_id' => 'integer',
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
        'fecha' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Los registros de auditoría son inmutables.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Los registros de auditoría no se pueden eliminar.');
        });
    }

    /**
     * @return BelongsTo<Usuario, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
