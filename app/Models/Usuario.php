<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class Usuario extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'apellido',
        'email',
        'password',
        'activo',
        'requiere_cambio_contrasena',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'colegio_id' => 'integer',
        'activo' => 'boolean',
        'requiere_cambio_contrasena' => 'boolean',
        'email_verified_at' => 'datetime',
        'bloqueado_hasta' => 'datetime',
        'password' => 'hashed',
    ];

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
            'nombre',
            'apellido',
            'email',
            'activo',
            'requiere_cambio_contrasena',
        ];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'usuarios.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'usuarios';
    }
}
