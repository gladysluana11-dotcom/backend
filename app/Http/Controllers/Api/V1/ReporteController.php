<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultarReporteRequest;
use App\Http\Resources\Api\V1\ReporteResource;
use App\Models\Colegio;
use App\Models\Usuario;
use App\Services\ConsultarReportes;
use App\Services\ExportarReporte;
use App\Services\GenerarFichaMatricula;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReporteController extends Controller
{
    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{resumen:array{total_matriculas:int,pendientes_completar:int},filas:list<array{matricula_id:int,alumno_id:int,nombre:string,apellido:string,anio_escolar:string|null,nivel:string|null,grado:string|null,seccion:string|null,estado:string,documentos_pendientes:int}>,filtros:array<string,mixed>},errors:null,code:string}',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Reporte de matrículas en PDF.',
        mediaType: 'application/pdf',
        type: 'string',
        format: 'binary',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Reporte de matrículas en XLSX.',
        mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        type: 'string',
        format: 'binary',
    )]
    public function matriculas(
        ConsultarReporteRequest $request,
        ConsultarReportes $reportes,
        ExportarReporte $exportar,
        GenerarFichaMatricula $ficha,
    ): JsonResponse|BinaryFileResponse|Response {
        Gate::authorize('ver-reporte', 'matriculas');

        return $this->responder(
            $request,
            'matriculas',
            'Matrículas y pendientes de completar',
            $reportes->matriculas($this->actor($request), $request->validated()),
            [
                'matricula_id' => 'Matrícula',
                'alumno_id' => 'Alumno ID',
                'nombre' => 'Nombres',
                'apellido' => 'Apellidos',
                'anio_escolar' => 'Año escolar',
                'nivel' => 'Nivel',
                'grado' => 'Grado',
                'seccion' => 'Sección',
                'estado' => 'Estado',
                'documentos_pendientes' => 'Documentos pendientes',
            ],
            $exportar,
            $ficha,
        );
    }

    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{resumen:array{cantidad:int,total_vigente:string},filas:list<array{pago_id:int,fecha_pago:string,alumno:string,usuario:string,medio:string,monto:string,estado:string,constancia:string|null}>,filtros:array<string,mixed>},errors:null,code:string}',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Reporte de pagos en PDF.',
        mediaType: 'application/pdf',
        type: 'string',
        format: 'binary',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Reporte de pagos en XLSX.',
        mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        type: 'string',
        format: 'binary',
    )]
    public function pagos(
        ConsultarReporteRequest $request,
        ConsultarReportes $reportes,
        ExportarReporte $exportar,
        GenerarFichaMatricula $ficha,
    ): JsonResponse|BinaryFileResponse|Response {
        Gate::authorize('ver-reporte', 'pagos');

        return $this->responder(
            $request,
            'pagos',
            'Pagos por rango de fechas',
            $reportes->pagos($this->actor($request), $request->validated()),
            [
                'pago_id' => 'Pago',
                'fecha_pago' => 'Fecha',
                'alumno' => 'Alumno',
                'usuario' => 'Usuario',
                'medio' => 'Medio',
                'monto' => 'Monto',
                'estado' => 'Estado',
                'constancia' => 'Constancia',
            ],
            $exportar,
            $ficha,
        );
    }

    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{resumen:array{total_emitido:string,total_cobrado:string},filas:list<array{mes:string,cuotas_emitidas:string,cuotas_cobradas:string}>,filtros:array<string,mixed>},errors:null,code:string}',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Reporte de ingresos mensuales en PDF.',
        mediaType: 'application/pdf',
        type: 'string',
        format: 'binary',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Reporte de ingresos mensuales en XLSX.',
        mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        type: 'string',
        format: 'binary',
    )]
    public function ingresosMensuales(
        ConsultarReporteRequest $request,
        ConsultarReportes $reportes,
        ExportarReporte $exportar,
        GenerarFichaMatricula $ficha,
    ): JsonResponse|BinaryFileResponse|Response {
        Gate::authorize('ver-reporte', 'ingresos_mensuales');

        return $this->responder(
            $request,
            'ingresos_mensuales',
            'Ingresos mensuales: cuotas emitidas y cobradas',
            $reportes->ingresosMensuales($this->actor($request), $request->validated()),
            [
                'mes' => 'Mes',
                'cuotas_emitidas' => 'Cuotas emitidas',
                'cuotas_cobradas' => 'Cuotas cobradas',
            ],
            $exportar,
            $ficha,
        );
    }

    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{resumen:array{matriculados_por_nivel:list<array{nivel_id:int,nivel:string,matriculados:int}>,recaudacion_mes:string,deuda_total:string,deuda_vencida:string,porcentaje_morosidad:string},filas:list<array{nivel_id:int,nivel:string,matriculados:int}>,filtros:array{mes:string}},errors:null,code:string}',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Dashboard financiero en PDF.',
        mediaType: 'application/pdf',
        type: 'string',
        format: 'binary',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Dashboard financiero en XLSX.',
        mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        type: 'string',
        format: 'binary',
    )]
    public function dashboard(
        ConsultarReporteRequest $request,
        ConsultarReportes $reportes,
        ExportarReporte $exportar,
        GenerarFichaMatricula $ficha,
    ): JsonResponse|BinaryFileResponse|Response {
        Gate::authorize('ver-reporte', 'dashboard');
        $datos = $reportes->dashboard($this->actor($request));

        return $this->responder(
            $request,
            'dashboard',
            'Dashboard de Dirección',
            $datos,
            ['nivel_id' => 'Nivel ID', 'nivel' => 'Nivel', 'matriculados' => 'Matriculados'],
            $exportar,
            $ficha,
        );
    }

    /**
     * @param  array<string, mixed>  $datos
     * @param  array<string, string>  $columnas
     */
    private function responder(
        ConsultarReporteRequest $request,
        string $reporte,
        string $titulo,
        array $datos,
        array $columnas,
        ExportarReporte $exportar,
        GenerarFichaMatricula $ficha,
    ): JsonResponse|BinaryFileResponse|Response {
        $actor = $this->actor($request);
        $filtros = $request->validated();
        $formato = $filtros['formato'] ?? 'json';

        if ($formato === 'json') {
            return response()->json([
                'message' => $titulo.'.',
                'data' => ReporteResource::make($datos)->resolve($request),
                'errors' => null,
                'code' => 'OK',
            ]);
        }

        $colegio = Colegio::query()
            ->select(['id', 'nombre', 'logo'])
            ->findOrFail($actor->colegio_id);

        return $exportar->descargar(
            $actor,
            $reporte,
            $formato,
            $titulo,
            $colegio->nombre,
            $ficha->logoData($colegio->logo),
            $datos,
            $columnas,
        );
    }

    private function actor(Request $request): Usuario
    {
        $usuario = $request->user();

        if (! $usuario instanceof Usuario) {
            throw new AuthenticationException;
        }

        return $usuario;
    }
}
