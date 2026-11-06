<?php

namespace App\Services;

use App\Models\Colegio;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class GestionarUsuarios
{
    public function __construct(private RegistrarAuditoria $registrarAuditoria) {}

    /**
     * @param  array{buscar?: string, rol?: string, estado?: string, per_page?: int, page?: int}  $filtros
     */
    public function listar(Usuario $actor, array $filtros): LengthAwarePaginator
    {
        $equipoAnterior = getPermissionsTeamId();
        setPermissionsTeamId($actor->colegio_id);

        try {
            $consulta = Usuario::query()
                ->where('colegio_id', $actor->colegio_id)
                ->with('roles')
                ->when($filtros['buscar'] ?? null, function ($consulta, string $buscar): void {
                    $consulta->where(function ($consulta) use ($buscar): void {
                        $consulta->where('nombre', 'like', '%'.$buscar.'%')
                            ->orWhere('apellido', 'like', '%'.$buscar.'%')
                            ->orWhere('email', 'like', '%'.$buscar.'%');
                    });
                })
                ->when($filtros['rol'] ?? null, function ($consulta, string $rol) use ($actor): void {
                    $consulta->whereHas('roles', function ($consulta) use ($rol, $actor): void {
                        $consulta->where('name', $rol)
                            ->where('roles.colegio_id', $actor->colegio_id);
                    });
                })
                ->when($filtros['estado'] ?? null, function ($consulta, string $estado): void {
                    $consulta->where('activo', $estado === 'activo');
                })
                ->orderByDesc('id');

            return $consulta->paginate($filtros['per_page'] ?? 25);
        } finally {
            setPermissionsTeamId($equipoAnterior);
        }
    }

    public function buscar(Usuario $actor, int|string $id): Usuario
    {
        $equipoAnterior = getPermissionsTeamId();
        setPermissionsTeamId($actor->colegio_id);

        try {
            return Usuario::query()
                ->where('colegio_id', $actor->colegio_id)
                ->with('roles')
                ->findOrFail($id);
        } finally {
            setPermissionsTeamId($equipoAnterior);
        }
    }

    /**
     * @param  array{nombre: string, apellido: string, email: string, rol: string}  $datos
     * @return array{usuario: Usuario, contrasena_temporal: string}
     */
    public function crear(Usuario $actor, array $datos): array
    {
        return DB::transaction(function () use ($actor, $datos): array {
            $this->bloquearColegio($actor->colegio_id);
            $rol = $this->obtenerRol($actor->colegio_id, $datos['rol']);
            $this->verificarJerarquia($actor, $rol);
            $contrasenaTemporal = Str::password(16);

            $usuario = new Usuario(Arr::only($datos, ['nombre', 'apellido', 'email']));
            $usuario->forceFill([
                'colegio_id' => $actor->colegio_id,
                'password' => $contrasenaTemporal,
                'activo' => true,
                'requiere_cambio_contrasena' => true,
            ])->save();

            $this->asignarRol($actor, $usuario, $rol, []);

            return [
                'usuario' => $this->buscar($actor, $usuario->getKey()),
                'contrasena_temporal' => $contrasenaTemporal,
            ];
        });
    }

    /**
     * @param  array{nombre?: string, apellido?: string, email?: string, rol?: string}  $datos
     */
    public function actualizar(Usuario $actor, Usuario $usuario, array $datos): Usuario
    {
        return DB::transaction(function () use ($actor, $usuario, $datos): Usuario {
            $this->bloquearColegio($actor->colegio_id);
            $usuario = Usuario::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($usuario->getKey());

            $rolAnterior = $this->rolesDe($usuario, $actor->colegio_id);
            $rolNuevo = null;

            if (isset($datos['rol'])) {
                $rolNuevo = $this->obtenerRol($actor->colegio_id, $datos['rol']);
                $this->verificarJerarquia($actor, $rolNuevo);

                if (
                    in_array('Administrador', $rolAnterior, true)
                    && $rolNuevo->name !== 'Administrador'
                    && $usuario->activo
                    && $this->administradoresActivos($actor->colegio_id) <= 1
                ) {
                    $this->rechazarUltimoAdministrador();
                }
            }

            $usuario->fill(Arr::only($datos, ['nombre', 'apellido', 'email']))->save();

            if ($rolNuevo !== null && $rolAnterior !== [$rolNuevo->name]) {
                $this->asignarRol($actor, $usuario, $rolNuevo, $rolAnterior);
            }

            return $this->buscar($actor, $usuario->getKey());
        });
    }

    public function desactivar(Usuario $actor, Usuario $usuario): Usuario
    {
        return DB::transaction(function () use ($actor, $usuario): Usuario {
            $this->bloquearColegio($actor->colegio_id);
            $usuario = Usuario::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($usuario->getKey());

            if ($actor->is($usuario)) {
                throw ValidationException::withMessages([
                    'usuario' => ['No puedes desactivar tu propia cuenta.'],
                ]);
            }

            if ($usuario->activo && in_array('Administrador', $this->rolesDe($usuario, $actor->colegio_id), true)) {
                if ($this->administradoresActivos($actor->colegio_id) <= 1) {
                    $this->rechazarUltimoAdministrador();
                }
            }

            $usuario->forceFill(['activo' => false])->save();
            $usuario->tokens()->delete();

            return $this->buscar($actor, $usuario->getKey());
        });
    }

    public function reactivar(Usuario $actor, Usuario $usuario): Usuario
    {
        return DB::transaction(function () use ($actor, $usuario): Usuario {
            $this->bloquearColegio($actor->colegio_id);
            $usuario = Usuario::query()
                ->where('colegio_id', $actor->colegio_id)
                ->lockForUpdate()
                ->findOrFail($usuario->getKey());

            $usuario->forceFill(['activo' => true])->save();

            return $this->buscar($actor, $usuario->getKey());
        });
    }

    private function bloquearColegio(?int $colegioId): void
    {
        Colegio::query()->whereKey($colegioId)->lockForUpdate()->firstOrFail();
    }

    private function obtenerRol(?int $colegioId, string $nombre): Role
    {
        return Role::query()
            ->where('colegio_id', $colegioId)
            ->where('guard_name', 'web')
            ->where('name', $nombre)
            ->firstOrFail();
    }

    private function verificarJerarquia(Usuario $actor, Role $rol): void
    {
        $jerarquia = config('sge.jerarquia_roles', []);
        $rangos = array_map(
            static fn (string $nombre): int => (int) ($jerarquia[$nombre] ?? 0),
            $this->rolesDe($actor, $actor->colegio_id),
        );
        $rangoActor = $rangos === [] ? 0 : max($rangos);
        $rangoAsignado = (int) ($jerarquia[$rol->name] ?? 0);

        if ($rangoAsignado > $rangoActor) {
            throw new AuthorizationException('No puedes asignar un rol superior al tuyo.');
        }
    }

    /**
     * @return list<string>
     */
    private function rolesDe(Usuario $usuario, ?int $colegioId): array
    {
        $equipoAnterior = getPermissionsTeamId();
        setPermissionsTeamId($colegioId);

        try {
            $usuario->unsetRelation('roles');

            return $usuario->getRoleNames()->all();
        } finally {
            setPermissionsTeamId($equipoAnterior);
        }
    }

    /**
     * @param  list<string>  $rolesAnteriores
     */
    private function asignarRol(Usuario $actor, Usuario $usuario, Role $rol, array $rolesAnteriores): void
    {
        $equipoAnterior = getPermissionsTeamId();
        setPermissionsTeamId($actor->colegio_id);

        try {
            $usuario->syncRoles([$rol]);
        } finally {
            setPermissionsTeamId($equipoAnterior);
        }

        $this->registrarAuditoria->registrar(
            'usuarios.rol_cambiado',
            'usuarios',
            (int) $usuario->getKey(),
            ['roles' => $rolesAnteriores],
            ['roles' => [$rol->name]],
            $actor,
            $actor->colegio_id,
        );
    }

    private function administradoresActivos(?int $colegioId): int
    {
        return Usuario::query()
            ->where('colegio_id', $colegioId)
            ->where('activo', true)
            ->whereHas('roles', static function ($consulta) use ($colegioId): void {
                $consulta->where('name', 'Administrador')
                    ->where('roles.colegio_id', $colegioId);
            })
            ->count();
    }

    private function rechazarUltimoAdministrador(): never
    {
        throw ValidationException::withMessages([
            'usuario' => ['No se puede desactivar ni quitar el rol del último Administrador activo del colegio.'],
        ]);
    }
}
