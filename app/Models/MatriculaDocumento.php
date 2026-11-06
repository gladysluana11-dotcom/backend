<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToColegio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon|null $fecha_entrega */
class MatriculaDocumento extends Model
{
    use Auditable, BelongsToColegio, HasFactory;

    protected $table = 'matricula_documentos';

    protected $fillable = [
        'colegio_id',
        'matricula_id',
        'documento_requerido_id',
        'estado',
        'fecha_entrega',
    ];

    protected function casts(): array
    {
        return [
            'colegio_id' => 'integer',
            'matricula_id' => 'integer',
            'documento_requerido_id' => 'integer',
            'fecha_entrega' => 'date',
        ];
    }

    /** @return BelongsTo<Matricula, $this> */
    public function matricula(): BelongsTo
    {
        return $this->belongsTo(Matricula::class, 'matricula_id');
    }

    /** @return BelongsTo<DocumentoRequerido, $this> */
    public function documentoRequerido(): BelongsTo
    {
        return $this->belongsTo(DocumentoRequerido::class, 'documento_requerido_id');
    }

    /** @return list<string> */
    protected function atributosAuditables(): array
    {
        return ['matricula_id', 'documento_requerido_id', 'estado', 'fecha_entrega'];
    }

    protected function eventoAuditableCreado(): string
    {
        return 'matricula_documentos.creado';
    }

    protected function entidadAuditable(): string
    {
        return 'matricula_documentos';
    }
}
