# SGE - Sistema de Gestión Escolar (Cloud Business Solutions)
SaaS multi-colegio. Especificación en docs/requerimientos.md (leerla antes de cada tarea).

## Estructura
backend/ = Laravel 12 solo API. frontend/ = React + TypeScript (Vite).

## Backend - reglas obligatorias
- API REST bajo /api/v1, respuestas con API Resources. Sin vistas Blade para la interfaz (Blade solo para correos y PDF).
- Autenticación: Sanctum con cookie de sesión para la SPA y tokens personales para móvil.
- Multi-colegio: toda tabla de datos del colegio lleva colegio_id (FK, indexado). Trait BelongsToColegio con global scope. Nunca recibir colegio_id desde el request.
- Nombres de tablas, modelos y campos en español.
- Dinero DECIMAL(10,2) en PEN. Zona horaria America/Lima. Idioma es.
- Pagos, constancias y auditorías nunca se eliminan: se anulan con motivo. Saldos y estados de cuota se derivan de los pagos.
- Registro de pagos idempotente con cabecera Idempotency-Key.
- Lógica en app/Services, validación con Form Requests, autorización con Policies y roles.
- Estilo PSR-12 (Pint) y análisis con Larastan.
- Nunca leer ni modificar .env ni subir credenciales. Datos de prueba solo ficticios (seeders).
- Cada funcionalidad incluye pruebas Pest: caso feliz, permisos y aislamiento entre colegios.

## Forma de trabajar
Antes de programar, escribe un plan corto y espera mi aprobación. Trabaja en pasos pequeños, ejecuta las pruebas y resume los cambios.
