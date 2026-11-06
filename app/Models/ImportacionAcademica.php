<?php

namespace App\Models;

use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImportacionAcademica extends Model
{
    use BelongsToColegio, HasFactory;

    protected $table = 'importaciones_academicas';

    protected $fillable = [
        'colegio_id',
        'usuario_id',
        'anio_escolar_id',
        'token_hash',
        'ruta_archivo',
        'sha256',
        'expira_en',
        'confirmada_en',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'usuario_id' => 'integer',
            'anio_escolar_id' => 'integer',
            'expira_en' => 'datetime',
            'confirmada_en' => 'datetime',
        ];
    }
}
