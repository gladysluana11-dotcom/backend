<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Desarrollo del backend SGE

El backend requiere PHP 8.3 o superior y MySQL 8. Crea las bases de datos `sge_dev` y `sge_test`, copia `.env.example` a `.env`, configura las credenciales de MySQL y ejecuta:

```powershell
if (!(Test-Path .env)) { Copy-Item .env.example .env }
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

La API queda disponible en `http://localhost:8000/api/v1`. La SPA local debe usar `http://localhost:5173`, que está configurado como origen stateful de Sanctum. La documentación OpenAPI está disponible en `/docs/api` solo cuando `APP_ENV=local`.
En desarrollo local, el login debe enviar `subdominio` (por ejemplo, `salesian-innova`); en los subdominios reales se valida contra el origen de la SPA.

Los usuarios ficticios del seeder usan la contraseña `Password123!`; sus cuentas están en `database/seeders/DatosFicticiosSeeder.php`. No se deben cargar datos reales en este entorno.

### Usuarios, roles y auditoría (B2)

Todos los endpoints requieren autenticación, salvo los de acceso y recuperación. Los usuarios solo pueden ser gestionados por Director y Administrador dentro de su colegio:

| Método | Endpoint | Descripción |
|---|---|---|
| GET | `/api/v1/usuarios?buscar=&rol=&estado=activo&per_page=25&page=1` | Lista paginada; `estado` acepta `activo` o `inactivo` y `per_page` no supera 100. |
| POST | `/api/v1/usuarios` | Crea usuario con `nombre`, `apellido`, `email` y `rol`. Devuelve una contraseña temporal de un solo uso; el primer ingreso exige cambiarla. |
| GET | `/api/v1/usuarios/{id}` | Consulta un usuario del colegio autenticado. |
| PATCH | `/api/v1/usuarios/{id}` | Actualiza datos o rol, respetando la jerarquía. |
| POST | `/api/v1/usuarios/{id}/desactivar` | Desactiva sin eliminar y revoca sus tokens móviles; no permite auto-desactivación ni dejar al colegio sin Administrador activo. |
| POST | `/api/v1/usuarios/{id}/reactivar` | Reactiva una cuenta desactivada. |
| POST | `/api/v1/auth/cambiar-contrasena` | Recibe `contrasena_actual`, `contrasena` y `contrasena_confirmation`; la nueva clave requiere al menos 10 caracteres. |
| GET | `/api/v1/roles` | Catálogo de roles y permisos asignados en el colegio. |
| GET | `/api/v1/permisos` | Catálogo de permisos por módulo y acción disponibles para el colegio. |
| GET | `/api/v1/auditoria?usuario_id=&evento=&entidad=&fecha_desde=AAAA-MM-DD&fecha_hasta=AAAA-MM-DD` | Auditoría paginada del colegio; acceso restringido a Director y Administrador. |

No existe endpoint para eliminar usuarios ni para modificar o eliminar auditorías. Los registros de auditoría omiten contraseñas y tokens. La sesión SPA vence tras 30 minutos de inactividad por defecto; se configura con `SESSION_LIFETIME` sin afectar los tokens móviles.

Las pruebas usan la base `sge_test` configurada en `phpunit.xml`. Ejecuta las verificaciones con:

```powershell
vendor\bin\pest.bat
vendor\bin\pint.bat --test
vendor\bin\phpstan.bat analyse
```

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
