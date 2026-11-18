<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListarDeudasRequest;
use App\Http\Resources\Api\V1\DeudaResource;
use App\Http\Resources\Api\V1\ReporteResource;
use App\Models\Colegio;
use App\Models\Usuario;
use App\Services\ConsultarDeudas;
use App\Services\ExportarReporte;
use App\Services\GenerarFichaMatricula;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class DeudaController extends Controller
{
    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{deudas:list<array{alumno_id:int,nombre:string,apellido:string,nivel:string|null,grado:string|null,seccion:string|null,total_vencido:string,total_pendiente:string,cuotas_vencidas:int,dias_atraso:int,apoderado_responsable_pago:array{nombre:string,apellido:string,telefono:string|null,email:string|null}|null}>,paginacion:array{pagina:int,por_pagina:int,total:int,ultima_pagina:int}},errors:null,code:string}',
    )]
    public function index(ListarDeudasRequest $request, ConsultarDeudas $deudas): JsonResponse
    {
        $paginador = $deudas->listar($this->actor($request), $request->validated());

        return response()->json([
            'message' => 'Alumnos con cuotas vencidas.',
            'data' => [
                'deudas' => DeudaResource::collection(collect($paginador->items()))->resolve($request),
                'paginacion' => [
                    'pagina' => $paginador->currentPage(),
                    'por_pagina' => $paginador->perPage(),
                    'total' => $paginador->total(),
                    'ultima_pagina' => $paginador->lastPage(),
                ],
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{secciones:list<array{seccion_id:int,nivel:string|null,grado:string|null,seccion:string|null,cantidad_alumnos:int,total_vencido:string,total_pendiente:string,alumnos:list<array{alumno_id:int,nombre:string,apellido:string,nivel:string|null,grado:string|null,seccion:string|null,total_vencido:string,total_pendiente:string,cuotas_vencidas:int,dias_atraso:int,apoderado_responsable_pago:array{nombre:string,apellido:string,telefono:string|null,email:string|null}|null}>}>,filtros:array<string,mixed>},errors:null,code:string}',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Reporte de deuda por aula en PDF.',
        mediaType: 'application/pdf',
        type: 'string',
        format: 'binary',
    )]
    #[ScrambleResponse(
        status: 200,
        description: 'Reporte de deuda por aula en XLSX.',
        mediaType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        type: 'string',
        format: 'binary',
    )]
    public function porAula(
        ListarDeudasRequest $request,
        ConsultarDeudas $deudas,
        ExportarReporte $exportar,
        GenerarFichaMatricula $ficha,
    ): JsonResponse|BinaryFileResponse|Response {
        $actor = $this->actor($request);
        $filtros = $request->validated();
        $secciones = $deudas->porAula($actor, $filtros);
        $filas = [];

        foreach ($secciones as $seccion) {
            foreach ($seccion['alumnos'] as $alumno) {
                $filas[] = [
                    'nivel' => $seccion['nivel'],
                    'grado' => $seccion['grado'],
                    'seccion' => $seccion['seccion'],
                    'alumno' => trim($alumno['apellido'].' '.$alumno['nombre']),
                    'total_vencido' => $alumno['total_vencido'],
                    'total_pendiente' => $alumno['total_pendiente'],
                    'cuotas_vencidas' => $alumno['cuotas_vencidas'],
                    'dias_atraso' => $alumno['dias_atraso'],
                ];
            }
        }

        $datos = [
            'resumen' => [
                'secciones' => count($secciones),
                'alumnos' => count($filas),
            ],
            'filas' => $filas,
            'filtros' => $this->filtrosAplicados($filtros),
        ];

        if (($filtros['formato'] ?? 'json') === 'json') {
            return response()->json([
                'message' => 'Reporte de deudas por aula.',
                'data' => ReporteResource::make([
                    'secciones' => $secciones,
                    'filtros' => $datos['filtros'],
                ])->resolve($request),
                'errors' => null,
                'code' => 'OK',
            ]);
        }

        $colegio = Colegio::query()
            ->select(['id', 'nombre', 'logo'])
            ->findOrFail($actor->colegio_id);

        return $exportar->descargar(
            $actor,
            'deudas_aula',
            $filtros['formato'],
            'Deuda por aula',
            $colegio->nombre,
            $ficha->logoData($colegio->logo),
            $datos,
            [
                'nivel' => 'Nivel',
                'grado' => 'Grado',
                'seccion' => 'Sección',
                'alumno' => 'Alumno',
                'total_vencido' => 'Total vencido',
                'total_pendiente' => 'Total pendiente',
                'cuotas_vencidas' => 'Cuotas vencidas',
                'dias_atraso' => 'Días de atraso',
            ],
        );
    }

    /** @param array<string, mixed> $filtros
     * @return array<string, mixed>
     */
    private function filtrosAplicados(array $filtros): array
    {
        unset($filtros['formato'], $filtros['colegio_id'], $filtros['page'], $filtros['per_page']);

        return array_filter($filtros, fn (mixed $valor): bool => $valor !== null);
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
