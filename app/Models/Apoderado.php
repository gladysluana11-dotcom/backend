<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Apoderado extends Model
{
    use BelongsToColegio, HasFactory;

    protected $table = 'apoderados';

    protected $fillable = [
        'alumno_id',
        'colegio_id',
        'nombre',
        'apellido',
        'parentesco',
        'telefono',
        'email',
        'responsable_pago',
    ];

    protected $casts = [
        'responsable_pago' => 'boolean',
    ];

    public function alumno(): BelongsTo
    {
        return $this->belongsTo(Alumno::class, 'alumno_id');
    }

    public function colegio(): BelongsTo
    {
        return $this->belongsTo(Colegio::class, 'colegio_id');
    }
}
