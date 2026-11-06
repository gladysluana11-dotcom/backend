<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matricula extends Model
{
    use BelongsToColegio, HasFactory;

    protected $table = 'matriculas';

    protected $fillable = [
        'colegio_id',
        'alumno_id',
        'anio_escolar',
        'grado',
        'seccion',
        'estado',
        'fecha_matricula',
        'observaciones',
    ];

    protected $casts = [
        'colegio_id' => 'integer',
        'alumno_id' => 'integer',
        'fecha_matricula' => 'date',
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
