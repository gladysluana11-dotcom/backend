<?php

namespace App\Providers;

use App\Contracts\ConsultarAplicacionesPago;
use App\Models\Alumno;
use App\Models\AnioEscolar;
use App\Models\Apoderado;
use App\Models\AsignacionDocente;
use App\Models\AsignacionSeccion;
use App\Models\CierreCaja;
use App\Models\ConfiguracionColegio;
use App\Models\Cuota;
use App\Models\Curso;
use App\Models\Descuento;
use App\Models\DocumentoRequerido;
use App\Models\FuncionColegio;
use App\Models\GestionCobranza;
use App\Models\Grado;
use App\Models\ImportacionAcademica;
use App\Models\Matricula;
use App\Models\Nivel;
use App\Models\Pago;
use App\Models\Periodo;
use App\Models\Seccion;
use App\Models\Tarifa;
use App\Models\UnidadDidactica;
use App\Models\Usuario;
use App\Policies\AcademicoPolicy;
use App\Policies\AlumnoPolicy;
use App\Policies\ApoderadoPolicy;
use App\Policies\CierreCajaPolicy;
use App\Policies\ConfiguracionColegioPolicy;
use App\Policies\CuotaPolicy;
use App\Policies\DescuentoPolicy;
use App\Policies\DeudaPolicy;
use App\Policies\DocumentoRequeridoPolicy;
use App\Policies\FuncionColegioPolicy;
use App\Policies\GestionCobranzaPolicy;
use App\Policies\MatriculaPolicy;
use App\Policies\PagoPolicy;
use App\Policies\ReportePolicy;
use App\Policies\TarifaPolicy;
use App\Services\ConsultarAplicacionesPagoPersistido;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ConsultarAplicacionesPago::class, ConsultarAplicacionesPagoPersistido::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach ([
            AnioEscolar::class,
            AsignacionDocente::class,
            Curso::class,
            Grado::class,
            ImportacionAcademica::class,
            Nivel::class,
            Periodo::class,
            Seccion::class,
            UnidadDidactica::class,
        ] as $modelo) {
            Gate::policy($modelo, AcademicoPolicy::class);
        }

        Gate::policy(ConfiguracionColegio::class, ConfiguracionColegioPolicy::class);
        Gate::policy(FuncionColegio::class, FuncionColegioPolicy::class);
        Gate::policy(Alumno::class, AlumnoPolicy::class);
        Gate::policy(Apoderado::class, ApoderadoPolicy::class);
        Gate::policy(Matricula::class, MatriculaPolicy::class);
        Gate::policy(Tarifa::class, TarifaPolicy::class);
        Gate::policy(Cuota::class, CuotaPolicy::class);
        Gate::policy(Descuento::class, DescuentoPolicy::class);
        Gate::policy(Pago::class, PagoPolicy::class);
        Gate::policy(CierreCaja::class, CierreCajaPolicy::class);
        Gate::policy(AsignacionSeccion::class, AcademicoPolicy::class);
        Gate::policy(DocumentoRequerido::class, DocumentoRequeridoPolicy::class);
        Gate::policy(GestionCobranza::class, GestionCobranzaPolicy::class);
        Gate::define('ver-deudas', [DeudaPolicy::class, 'viewAny']);
        Gate::define('ver-reporte', [ReportePolicy::class, 'view']);

        ResetPassword::createUrlUsing(function (Usuario $usuario, string $token): string {
            $query = http_build_query([
                'token' => $token,
                'email' => $usuario->getEmailForPasswordReset(),
            ]);

            return rtrim(config('sge.frontend_url'), '/').'/restablecer-contrasena?'.$query;
        });

        RateLimiter::for('login', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->toString());

            return Limit::perMinute(10)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request): Limit {
            $email = Str::lower($request->string('email')->toString());

            return Limit::perMinute(3)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('pagos-escritura', function (Request $request): Limit {
            $usuarioId = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return Limit::perMinute(30)->by($request->user()?->colegio_id.'|'.$usuarioId);
        });
    }
}
