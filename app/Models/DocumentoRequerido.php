<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentoRequerido extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'documentos_requeridos';

    protected $fillable = ['colegio_id', 'nombre', 'obligatorio', 'activo'];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'obligatorio' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public function matriculaDocumentos(): HasMany
    {
        return $this->hasMany(MatriculaDocumento::class, 'documento_requerido_id');
    }

    /** @return list<string> */
    protected function atributosAuditables(): array
    {
        return ['nombre', 'obligatorio', 'activo'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'documentos_requeridos.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'documentos_requeridos';
    }
}
