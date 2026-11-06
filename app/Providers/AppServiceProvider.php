<?php

namespace App\Providers;

use App\Models\AnioEscolar;
use App\Models\AsignacionDocente;
use App\Models\ConfiguracionColegio;
use App\Models\Curso;
use App\Models\FuncionColegio;
use App\Models\Grado;
use App\Models\ImportacionAcademica;
use App\Models\Nivel;
use App\Models\Periodo;
use App\Models\Seccion;
use App\Models\UnidadDidactica;
use App\Models\Usuario;
use App\Policies\AcademicoPolicy;
use App\Policies\ConfiguracionColegioPolicy;
use App\Policies\FuncionColegioPolicy;
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
        //
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
    }
}
