<?php

namespace App\Services;

use App\Models\Usuario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExportarReporte
{
    public function __construct(private RegistrarAuditoria $registrarAuditoria) {}

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $columnas
     */
    public function descargar(
        Usuario $actor,
        string $reporte,
        string $formato,
        string $titulo,
        string $colegio,
        ?string $logoData,
        array $datos,
        array $columnas,
    ): BinaryFileResponse|Response {
        $nombreArchivo = 'reporte-'.$reporte;

        if ($formato === 'pdf') {
            $respuesta = Pdf::loadView('pdf.reporte', [
                'titulo' => $titulo,
                'colegio' => $colegio,
                'logoData' => $logoData,
                'resumen' => $datos['resumen'],
                'filtros' => $datos['filtros'],
                'columnas' => $columnas,
                'filas' => $datos['filas'],
            ])->setPaper('a4', 'landscape')->download($nombreArchivo.'.pdf');
        } else {
            $respuesta = $this->descargarExcel($nombreArchivo, $titulo, $colegio, $datos, $columnas);
        }

        $this->registrarAuditoria->registrar(
            'reportes.exportados',
            'reportes',
            null,
            valoresNuevos: ['reporte' => $reporte, 'formato' => $formato],
            usuario: $actor,
            colegioId: (int) $actor->colegio_id,
        );

        return $respuesta;
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $columnas
     */
    private function descargarExcel(
        string $nombreArchivo,
        string $titulo,
        string $colegio,
        array $datos,
        array $columnas,
    ): BinaryFileResponse {
        $ruta = tempnam(sys_get_temp_dir(), 'sge_reporte_');

        if ($ruta === false) {
            throw new HttpException(500, 'No se pudo crear el archivo Excel del reporte.');
        }

        $generado = false;
        $escritor = new Writer;

        try {
            $escritor->openToFile($ruta);
            $escritor->addRow(Row::fromValues([$titulo]));
            $escritor->addRow(Row::fromValues(['Colegio', $colegio]));
            $escritor->addRow(Row::fromValues([]));
            $escritor->addRow(Row::fromValues(['Resumen']));

            foreach ($datos['resumen'] as $campo => $valor) {
                $escritor->addRow(Row::fromValues([$campo, $this->valorCelda($valor)]));
            }

            $escritor->addRow(Row::fromValues([]));
            $escritor->addRow(Row::fromValues(['Filtros aplicados']));

            foreach ($datos['filtros'] as $campo => $valor) {
                $escritor->addRow(Row::fromValues([$campo, $this->valorCelda($valor)]));
            }

            $escritor->addRow(Row::fromValues([]));
            $escritor->addRow(Row::fromValues(array_values($columnas)));

            foreach ($datos['filas'] as $fila) {
                $escritor->addRow(Row::fromValues(array_map(
                    fn (string $campo): string|int|float|null => $this->valorCelda($fila[$campo] ?? null),
                    array_keys($columnas),
                )));
            }

            $escritor->close();
            $generado = true;
        } finally {
            if (! $generado && is_file($ruta)) {
                unlink($ruta);
            }
        }

        return response()->download($ruta, $nombreArchivo.'.xlsx')->deleteFileAfterSend(true);
    }

    private function valorCelda(mixed $valor): string|int|float|null
    {
        if (is_string($valor) || is_int($valor) || is_float($valor) || $valor === null) {
            return $valor;
        }

        if (is_bool($valor)) {
            return $valor ? 'Sí' : 'No';
        }

        return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
