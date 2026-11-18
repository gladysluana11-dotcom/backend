<?php

namespace App\Services;

use App\Models\Pago;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class GenerarConstanciaPago
{
    public function __construct(private GenerarFichaMatricula $fichaMatricula) {}

    public function descargar(Pago $modelo): Response
    {
        $pago = Pago::query()
            ->where('colegio_id', $modelo->colegio_id)
            ->with([
                'colegio:id,nombre,direccion,logo,telefono,correo_contacto',
                'alumno:id,nombre,apellido',
                'usuario:id,nombre,apellido',
                'aplicaciones.cuota:id,descripcion,vencimiento',
            ])
            ->findOrFail($modelo->id);

        return Pdf::loadView('pdf.constancia-pago', [
            'pago' => $pago,
            'logoData' => $this->fichaMatricula->logoData($pago->colegio->logo),
        ])->setPaper('a4')->download('constancia-'.$pago->constancia.'.pdf');
    }
}
