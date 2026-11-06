<?php

namespace App\Providers;

use App\Models\Usuario;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
