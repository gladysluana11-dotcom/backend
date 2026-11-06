<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Apoderado extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'apoderados';

    protected $fillable = [
        'colegio_id',
        'nombre',
        'apellido',
    ];

    /** @return BelongsToMany<Alumno, $this> */
    public function alumnos(): BelongsToMany
    {
        return $this->belongsToMany(Alumno::class, 'alumno_apoderado', 'apoderado_id', 'alumno_id')
            ->wherePivot('colegio_id', $this->colegio_id)
            ->withPivot(['colegio_id', 'parentesco', 'telefono', 'email', 'responsable_pago'])
            ->withTimestamps();
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
        return ['nombre', 'apellido'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'apoderados.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'apoderados';
    }
}
