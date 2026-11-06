<?php

namespace App\Services;

use App\Models\Matricula;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GenerarFichaMatricula
{
    public function descargar(Matricula $modelo): Response
    {
        $matricula = Matricula::query()
            ->where('colegio_id', $modelo->colegio_id)
            ->with([
                'alumno:id,colegio_id,nombre,apellido,tipo_documento,documento_identidad,fecha_nacimiento,sexo,direccion',
                'colegio:id,nombre,direccion,logo,telefono,correo_contacto',
                'anioEscolar:id,nombre',
                'gradoRegistro:id,nombre',
                'seccionRegistro:id,nombre',
                'usuarioRegistro:id,nombre,apellido',
                'documentos.documentoRequerido:id,nombre',
            ])
            ->findOrFail($modelo->id);
        $logoData = $this->logoData($matricula->colegio->logo);

        return Pdf::loadView('pdf.ficha-matricula', [
            'matricula' => $matricula,
            'logoData' => $logoData,
        ])->setPaper('a4')->download('ficha-matricula-'.$matricula->id.'.pdf');
    }

    private function logoData(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '') {
            return null;
        }

        foreach (['local', 'public'] as $disco) {
            if (! Storage::disk($disco)->exists($ruta)) {
                continue;
            }

            $contenido = Storage::disk($disco)->get($ruta);
            $tipo = Storage::disk($disco)->mimeType($ruta);

            if ($contenido !== null && is_string($tipo) && str_starts_with($tipo, 'image/')) {
                return 'data:'.$tipo.';base64,'.base64_encode($contenido);
            }

            throw new RuntimeException('El logo del colegio no es una imagen JPG o PNG válida.');
        }

        throw new RuntimeException('No se pudo encontrar el logo configurado del colegio.');
    }
}
