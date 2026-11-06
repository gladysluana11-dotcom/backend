<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Colegio;
use App\Models\Usuario;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController
{
    public function login(Request $request): JsonResponse
    {
        return $this->authenticate($request, false);
    }

    public function createToken(Request $request): JsonResponse
    {
        return $this->authenticate($request, true);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Usuario autenticado.',
            'data' => ['usuario' => $this->usuarioData($request->user())],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        if ($request->bearerToken() !== null) {
            $request->user()->currentAccessToken()->delete();
        } else {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
            'data' => null,
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => 'Espera antes de solicitar otro enlace.',
                'data' => null,
                'errors' => null,
                'code' => 'TOO_MANY_REQUESTS',
            ], 429);
        }

        return response()->json([
            'message' => 'Si el correo está registrado, recibirás instrucciones para recuperar tu contraseña.',
            'data' => null,
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $status = Password::reset($validated, function (CanResetPassword $usuario, string $password): void {
            if (! $usuario instanceof Usuario) {
                throw new \LogicException('El proveedor de contraseñas debe devolver un usuario del sistema.');
            }

            $usuario->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();

            event(new PasswordReset($usuario));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => 'La contraseña se restableció correctamente.',
            'data' => null,
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    private function authenticate(Request $request, bool $issueToken): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'subdominio' => ['nullable', 'string', 'alpha_dash', 'max:63'],
        ]);

        $email = Str::lower($validated['email']);
        $usuario = Usuario::query()->where('email', $email)->first();

        if ($usuario?->bloqueado_hasta?->isFuture()) {
            return $this->error('La cuenta está bloqueada temporalmente. Intenta nuevamente más tarde.', 'ACCOUNT_LOCKED', 423);
        }

        if ($usuario?->bloqueado_hasta !== null) {
            $usuario->forceFill([
                'intentos_fallidos' => 0,
                'bloqueado_hasta' => null,
            ])->save();
        }

        if (! $usuario || ! Hash::check($validated['password'], $usuario->password)) {
            if ($usuario && $this->registrarIntentoFallido($usuario)) {
                return $this->error('La cuenta está bloqueada temporalmente. Intenta nuevamente más tarde.', 'ACCOUNT_LOCKED', 423);
            }

            return $this->error('Credenciales inválidas.', 'INVALID_CREDENTIALS', 401);
        }

        if (! $usuario->activo) {
            return $this->error('El usuario está desactivado.', 'USER_INACTIVE', 403);
        }

        if (! $this->perteneceAlSubdominio($request, $usuario, $validated['subdominio'] ?? null)) {
            return $this->error('Credenciales inválidas.', 'INVALID_CREDENTIALS', 401);
        }

        $usuario->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        if ($issueToken) {
            $token = $usuario->createToken('movil', ['*'], now()->addDays(30))->plainTextToken;
        } else {
            Auth::guard('web')->login($usuario, $request->boolean('remember'));
            $request->session()->regenerate();
            $token = null;
        }

        return response()->json([
            'message' => 'Inicio de sesión correcto.',
            'data' => [
                'usuario' => $this->usuarioData($usuario),
                'token' => $token,
            ],
            'errors' => null,
            'code' => 'OK',
        ]);
    }

    private function registrarIntentoFallido(Usuario $usuario): bool
    {
        return DB::transaction(function () use ($usuario): bool {
            $usuario = Usuario::query()->lockForUpdate()->findOrFail($usuario->getKey());
            $intentos = $usuario->intentos_fallidos + 1;

            $usuario->forceFill([
                'intentos_fallidos' => $intentos,
                'bloqueado_hasta' => $intentos >= 5 ? now()->addMinutes(15) : null,
            ])->save();

            return $intentos >= 5;
        });
    }

    private function perteneceAlSubdominio(Request $request, Usuario $usuario, ?string $subdominio): bool
    {
        $subdominioOrigen = $this->subdominioDesdeOrigen($request);

        if ($subdominioOrigen !== null && $subdominio !== null && $subdominioOrigen !== $subdominio) {
            return false;
        }

        $subdominio ??= $subdominioOrigen;

        if ($subdominio === null) {
            return false;
        }

        if ($usuario->colegio_id === null) {
            return $subdominio === config('sge.subdominio_plataforma')
                && $usuario->hasRole('Superadministrador');
        }

        return Colegio::query()
            ->whereKey($usuario->colegio_id)
            ->where('subdominio', $subdominio)
            ->where('activo', true)
            ->exists();
    }

    private function subdominioDesdeOrigen(Request $request): ?string
    {
        $origin = $request->headers->get('origin');
        $host = $origin ? parse_url($origin, PHP_URL_HOST) : $request->getHost();

        if (! is_string($host) || in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return null;
        }

        $subdominio = Str::before($host, '.');

        return in_array($subdominio, ['api', 'www'], true) ? null : $subdominio;
    }

    /**
     * @return array{id: int, nombre: string, apellido: string, email: string, colegio_id: ?int, roles: list<string>}
     */
    private function usuarioData(Usuario $usuario): array
    {
        setPermissionsTeamId($usuario->colegio_id);

        try {
            return [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'apellido' => $usuario->apellido,
                'email' => $usuario->email,
                'colegio_id' => $usuario->colegio_id,
                'roles' => $usuario->getRoleNames()->all(),
            ];
        } finally {
            setPermissionsTeamId(null);
        }
    }

    private function error(string $message, string $code, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'data' => null,
            'errors' => null,
            'code' => $code,
        ], $status);
    }
}
