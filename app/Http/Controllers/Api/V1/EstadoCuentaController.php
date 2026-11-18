<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Alumno;
use App\Models\Pago;
use App\Models\Usuario;
use App\Services\ConsultarEstadoCuenta;
use App\Services\GenerarFichaMatricula;
use Barryvdh\DomPDF\Facade\Pdf;
use Dedoc\Scramble\Attributes\Response as ScrambleResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EstadoCuentaController extends Controller
{
    #[ScrambleResponse(
        status: 200,
        type: 'array{message:string,data:array{alumno:array{id:int,nombre:string,apellido:string},cuotas:list<CuotaResource>,pagos:list<PagoResource>,totales:array{emitido:string,pagado:string,saldo:string,vencido:string}},errors:null,code:string}',
    )]
    public function show(Request $request, Alumno $alumno, ConsultarEstadoCuenta $consulta): JsonResponse
    {
        Gate::authorize('viewAny', Pago::class);
        $estadoCuenta = $consulta->consultar($this->actor($request), $alumno);

        return response()->json([
            'message' => 'Estado de cuenta del alumno.',
            'data' => $estadoCuenta,
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    #[ScrambleResponse(
        status: 200,
        description: 'Estado de cuenta del alumno en formato PDF.',
        mediaType: 'application/pdf',
        type: 'string',
        format: 'binary',
    )]
    public function pdf(
        Request $request,
        Alumno $alumno,
        ConsultarEstadoCuenta $consulta,
        GenerarFichaMatricula $fichaMatricula,
    ): Response {
        Gate::authorize('viewAny', Pago::class);
        $estadoCuenta = $consulta->consultar($this->actor($request), $alumno);

        return Pdf::loadView('pdf.estado-cuenta', [
            ...$estadoCuenta,
            'logoData' => $fichaMatricula->logoData($alumno->colegio?->logo),
        ])->setPaper('a4')->download('estado-cuenta-'.$alumno->id.'.pdf');
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
