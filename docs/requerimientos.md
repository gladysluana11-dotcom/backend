# Especificación de requerimientos del SGE - v1.2

Cloud Business Solutions · Sistema de Gestión Escolar (SGE) · Cliente piloto: Colegio Salesian Innova (Huaycán, Ate, Lima)

Fecha: 5 de noviembre de 2026. Reemplaza a las versiones 1.0 y 1.1. **Fuente de verdad para el agente de desarrollo.** Versión completa con redacción formal en el PDF v1.2.

## Control de versiones

| Versión | Cambios |
|---|---|
| 1.0 | Aprobada técnicamente por el Ing. Coelho. Interfaz con Blade. |
| 1.1 | Avance de unidades y app móvil pasan a Fase 1B; asistencia en Fase 2; pagos reportados por apoderados en Fase 2. |
| 1.2 | Frontend web como SPA independiente en React con TypeScript; Laravel como backend API. Nuevos RFW y RAP. |

**Fases:** 1 = núcleo del piloto; 1B = cierre del piloto (avance de unidades y app móvil inicial); 2 = consolidación; 3 = expansión. **Prioridad:** Alta, Media, Baja.

## 1. Alcance

Plataforma SaaS multi-colegio: SPA web (React + TypeScript), API REST (Laravel) y app móvil Android. Gestiona alumnos, matrículas, pensiones y pagos, deudas, avance de unidades didácticas y (Fase 2) asistencia. Hasta 500 alumnos por colegio; el piloto tiene unos 200 alumnos en inicial, primaria y secundaria.

**Fuera de alcance:** comprobantes electrónicos SUNAT (se emiten constancias internas), notas y libretas, pasarela de pagos y conciliación bancaria (Fase 3), iOS (Fase 3), contabilidad y planillas.

**Roles:** Superadministrador de plataforma, Director, Administrador del colegio (en el piloto, el profesor de computación), Tesorería, Coordinación, Docente, Auxiliar y Apoderado (desactivado por defecto).

## 2. Arquitectura

- Repositorio único con dos proyectos: `backend/` (Laravel 12, PHP 8.2+, solo API, MySQL 8) y `frontend/` (React + TypeScript estricto, Vite, React Router, TanStack Query, React Hook Form + Zod, Tailwind CSS + shadcn/ui).
- Las reglas de negocio viven solo en el backend. El frontend nunca duplica cálculos de saldos, vacantes ni permisos.
- API REST en `/api/v1` con Laravel Sanctum: cookie de sesión HttpOnly + CSRF para la SPA y tokens personales para el móvil. Nada de tokens en localStorage.
- Contrato OpenAPI generado desde el código (Scramble); el frontend genera sus tipos TypeScript desde ese contrato.
- Multi-colegio: base de datos compartida con `colegio_id` en toda tabla de datos del colegio y global scope en los modelos. El colegio nunca llega como parámetro del request; lo determina el usuario autenticado.
- La SPA se sirve en el subdominio del colegio, que solo se usa para mostrar su identidad visual; el login valida que el usuario pertenezca a ese colegio.
- CORS restringido a los subdominios autorizados (mismo dominio registrable).
- Frontend compilado a estáticos (`npm run build`), un solo build para todos los subdominios; el servidor no necesita Node.js.
- Hosting cPanel: `api.[dominio]` apunta a `backend/public`; los subdominios de colegios apuntan al build estático con `.htaccess` para las rutas de la SPA; `pruebas.[dominio]` para datos ficticios.

## 3. Requerimientos funcionales

### 4.1 Gestión de instituciones (multi-colegio)

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-INS-01 | Registrar un colegio con nombre, logo, dirección, contacto y subdominio único. | Alta | 1 |
| RF-INS-02 | Aislar los datos de cada colegio: un usuario nunca puede leer ni modificar datos de otro colegio. | Alta | 1 |
| RF-INS-03 | Parametrizar por colegio: niveles, número de cuotas, día de vencimiento, series de recibos, periodos y unidades didácticas. | Alta | 1 |
| RF-INS-04 | Activar o desactivar funciones por colegio (portal de apoderados, notificaciones push, recargo por mora). | Alta | 1 |
| RF-INS-05 | Dar de alta un nuevo colegio sin modificar código, a partir de una configuración base. | Alta | 1 |
| RF-INS-06 | Panel de plataforma para suspender colegios y consultar métricas de uso, sin acceso a datos personales. | Media | 3 |
| RF-INS-07 | El inicio de sesión verifica que el usuario pertenezca al colegio del subdominio desde el que accede; el superadministrador accede desde un subdominio de plataforma. | Alta | 1 |

### 4.2 Seguridad y usuarios

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-SEG-01 | Autenticación con usuario y contraseña en web y móvil. | Alta | 1 |
| RF-SEG-02 | Control de acceso por roles y permisos por módulo y acción. | Alta | 1 |
| RF-SEG-03 | Recuperar y cambiar contraseña por correo; cambio obligatorio en el primer ingreso. | Alta | 1 |
| RF-SEG-04 | Bloqueo temporal de la cuenta tras 5 intentos fallidos consecutivos. | Alta | 1 |
| RF-SEG-05 | Cierre automático de sesión web tras 30 minutos de inactividad. | Media | 1 |
| RF-SEG-06 | Crear, editar y desactivar usuarios; no se eliminan para conservar la trazabilidad. | Alta | 1 |
| RF-SEG-07 | Auditoría de operaciones críticas (inicio de sesión, matrícula, pagos, anulaciones, cambios de montos y roles): usuario, fecha y hora, IP y valores anterior y nuevo. | Alta | 1 |
| RF-SEG-08 | Autenticación en dos pasos opcional para Director y Tesorería. | Baja | 2 |

### 4.3 Configuración académica

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-ACA-01 | Gestionar años escolares; solo uno activo por colegio; cierre del año. | Alta | 1 |
| RF-ACA-02 | Gestionar niveles (inicial, primaria, secundaria), grados y secciones con capacidad de vacantes. | Alta | 1 |
| RF-ACA-03 | Gestionar cursos o áreas por nivel y grado. | Alta | 1 |
| RF-ACA-04 | Definir periodos y unidades didácticas del año, con nombre, orden, fecha de inicio y fecha de fin. | Alta | 1 |
| RF-ACA-05 | Asignar docentes a cursos y secciones. | Alta | 1 |
| RF-ACA-06 | Importar la estructura académica desde una plantilla Excel o CSV. | Media | 1 |

### 4.4 Alumnos y apoderados

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-ALU-01 | Registrar alumnos con nombres, apellidos, documento de identidad, fecha de nacimiento, sexo, dirección y foto opcional. | Alta | 1 |
| RF-ALU-02 | Registrar uno o más apoderados por alumno, con parentesco, contacto e indicador de responsable de pago. | Alta | 1 |
| RF-ALU-03 | Buscar y filtrar alumnos por nombre, documento, grado, sección y estado. | Alta | 1 |
| RF-ALU-04 | Impedir alumnos duplicados por documento de identidad dentro del mismo colegio. | Alta | 1 |
| RF-ALU-05 | Importar alumnos desde Excel o CSV con validación y reporte de errores por fila. | Media | 1 |
| RF-ALU-06 | Mantener el historial del alumno (matrículas, grados y secciones por año). | Media | 1 |
| RF-ALU-07 | Gestionar el estado del alumno: activo, retirado o egresado. | Alta | 1 |

### 4.5 Matrícula

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-MAT-01 | Registrar la matrícula por año escolar: alumno, grado, sección, fecha, estado y usuario que la registra. | Alta | 1 |
| RF-MAT-02 | Gestionar los estados de matrícula: pre-inscrito, pendiente de documentos, matriculado, retirado y anulado. | Alta | 1 |
| RF-MAT-03 | Controlar vacantes por sección; solo el Director puede autorizar excederlas, indicando el motivo. | Alta | 1 |
| RF-MAT-04 | Mantener una lista configurable de documentos requeridos, con estado entregado o pendiente por alumno. | Media | 1 |
| RF-MAT-05 | Permitir una sola matrícula vigente por alumno y año. | Alta | 1 |
| RF-MAT-06 | Cambiar de sección o retirar al alumno con motivo y fecha, conservando el historial y ajustando las cuotas futuras. | Alta | 1 |
| RF-MAT-07 | Generar la ficha de matrícula en PDF con el logo y los datos del colegio. | Alta | 1 |
| RF-MAT-08 | Generar automáticamente el plan de cuotas al confirmar la matrícula (RF-PEN-03). | Alta | 1 |
| RF-MAT-09 | Realizar matrícula masiva de continuidad al siguiente grado para el nuevo año escolar. | Media | 2 |

### 4.6 Pensiones y pagos

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-PEN-01 | Gestionar conceptos de cobro (matrícula y pensión), con posibilidad de agregar otros. | Alta | 1 |
| RF-PEN-02 | Definir el tarifario por año, nivel y grado: monto de matrícula, monto de pensión, número de cuotas y vencimientos. | Alta | 1 |
| RF-PEN-03 | Generar automáticamente las cuotas de cada alumno (matrícula más N pensiones) con sus fechas de vencimiento. | Alta | 1 |
| RF-PEN-04 | Aplicar descuentos, becas o exoneraciones por alumno, por monto o porcentaje, con motivo y aprobación del Director. | Media | 1 |
| RF-PEN-05 | Registrar pagos con fecha, monto, medio (efectivo, depósito, transferencia, billetera digital), número de operación y observación. | Alta | 1 |
| RF-PEN-06 | Aplicar el pago a cuotas por antigüedad o por selección manual; admitir pagos parciales y pagos de varias cuotas. | Alta | 1 |
| RF-PEN-07 | Actualizar automáticamente el saldo, el estado de la cuota (pendiente, parcial, pagada, vencida) y el estado de cuenta tras cada pago. | Alta | 1 |
| RF-PEN-08 | Emitir constancia interna de pago en PDF, numerada correlativamente por colegio y serie, y reimprimible. | Alta | 1 |
| RF-PEN-09 | Anular o corregir pagos solo con permiso y motivo obligatorio; el pago anulado se conserva y revierte los saldos. | Alta | 1 |
| RF-PEN-10 | Mostrar el estado de cuenta por alumno y apoderado en pantalla y en PDF. | Alta | 1 |
| RF-PEN-11 | Cerrar caja diaria con resumen de pagos por usuario y medio de pago. | Media | 1 |
| RF-PEN-12 | Adjuntar voucher (imagen o PDF de hasta 2 MB) a un pago por depósito o transferencia. | Media | 1 |
| RF-PEN-13 | Recargo por mora opcional y configurable por colegio, desactivado por defecto. | Baja | 2 |
| RF-PEN-14 | Revisar y confirmar, en una cola de verificación, los pagos reportados por apoderados antes de aplicarlos a las cuotas. | Media | 2 |

### 4.7 Seguimiento de deudas

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-DEU-01 | Listar alumnos con deuda, filtrable por nivel, grado, sección, antigüedad y monto. | Alta | 1 |
| RF-DEU-02 | Generar el reporte de deuda por aula, imprimible y exportable, para el seguimiento de coordinación. | Alta | 1 |
| RF-DEU-03 | Registrar gestiones de cobranza: fecha, medio de contacto, compromiso de pago y responsable. | Media | 1 |
| RF-DEU-04 | Mostrar en el panel las cuotas por vencer (días configurables) y las vencidas. | Media | 1 |
| RF-DEU-05 | Preparar mensajes de recordatorio con plantilla, para envío por correo o enlace de WhatsApp. | Baja | 2 |

### 4.8 Avance de unidades didácticas

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-AVA-01 | Permitir al docente registrar el avance de cada curso, sección y unidad: porcentaje (0 a 100), sesiones o temas desarrollados frente a los programados, estado (no iniciada, en desarrollo, completada), observaciones y fecha de corte. | Alta | 1 |
| RF-AVA-02 | Definir la frecuencia de registro por colegio (semanal por defecto) e indicar los registros pendientes o atrasados. | Alta | 1 |
| RF-AVA-03 | Permitir que los auxiliares registren o apoyen avances, conservando el autor de cada registro. | Media | 1 |
| RF-AVA-04 | Flujo de revisión: avance registrado, observado o validado por coordinación. | Media | 1 |
| RF-AVA-05 | Consolidar avances por unidad, curso, grado, nivel y docente, comparando el avance real con el esperado según las fechas de la unidad (semáforo). | Alta | 1 |
| RF-AVA-06 | Generar el reporte de avance para la Dirección en PDF y Excel, con filtros y fecha de corte. | Alta | 1 |
| RF-AVA-07 | Mostrar el historial de avances de cada unidad en línea de tiempo. | Media | 1 |
| RF-AVA-08 | Restringir el registro del docente a sus propias asignaciones. | Alta | 1 |
| RF-AVA-09 | Alertar a coordinación sobre cursos con retraso mayor a un umbral configurable. | Media | 2 |
| RF-AVA-10 | Adjuntar evidencias (archivo de hasta 5 MB) a un avance. | Baja | 2 |

### 4.9 Reportes y panel de control

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-REP-01 | Panel del Director: matriculados por nivel, recaudación del mes, deuda total, porcentaje de morosidad y avance global de unidades. | Alta | 1 |
| RF-REP-02 | Reporte de alumnos matriculados y pendientes de completar su matrícula. | Alta | 1 |
| RF-REP-03 | Reporte de pagos por rango de fechas, medio de pago y usuario. | Alta | 1 |
| RF-REP-04 | Reporte mensual de ingresos: cuotas emitidas frente a cuotas cobradas. | Media | 1 |
| RF-REP-05 | Exportar todos los reportes a PDF y Excel, con logo y filtros aplicados. | Alta | 1 |
| RF-REP-06 | Restringir cada reporte según el rol del usuario. | Alta | 1 |

### 4.10 Notificaciones

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-NOT-01 | Mostrar notificaciones dentro del sistema. | Media | 1 |
| RF-NOT-02 | Enviar correos transaccionales (recuperación de contraseña, avisos de avances pendientes). | Alta | 1 |
| RF-NOT-03 | Enviar notificaciones push a la app móvil. | Media | 2 |
| RF-NOT-04 | Permitir que cada usuario configure sus preferencias de notificación. | Baja | 2 |

### 4.11 Portal de apoderados (función activable)

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-APO-01 | Acceso del apoderado con cuenta propia, restringido a sus hijos; desactivado por defecto. | Media | 2 |
| RF-APO-02 | Consultar estado de cuenta, cuotas y constancias de pago. | Media | 2 |
| RF-APO-03 | Recibir comunicados del colegio. | Baja | 3 |
| RF-APO-04 | Pagar en línea mediante una pasarela de pagos. | Baja | 3 |
| RF-APO-05 | Permitir al apoderado subir la captura o voucher de su pago (por ejemplo Yape) para revisión de Tesorería. | Media | 2 |

### 4.12 Datos de prueba y gestión de datos

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-DAT-01 | Disponer de un entorno de pruebas separado de producción, con datos ficticios generados automáticamente. | Alta | 1 |
| RF-DAT-02 | Registrar la autorización de la Dirección antes de cargar datos reales. | Alta | 1 |
| RF-DAT-03 | Exportar todos los datos de un colegio en CSV o Excel a su solicitud. | Media | 2 |
| RF-DAT-04 | Eliminar o anonimizar los datos al terminar el servicio, según política acordada. | Media | 2 |

### 4.13 Asistencia

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RF-ASI-01 | Registrar la asistencia diaria por sección con estados: presente, tardanza, falta y falta justificada. | Alta | 2 |
| RF-ASI-02 | Permitir el registro a docentes y auxiliares solo en las secciones asignadas; coordinación puede corregir con motivo y auditoría. | Alta | 2 |
| RF-ASI-03 | Registrar justificaciones de inasistencia con motivo y documento adjunto opcional (hasta 2 MB). | Media | 2 |
| RF-ASI-04 | Consultar el historial de asistencia por alumno, sección y periodo, con porcentaje de asistencia. | Alta | 2 |
| RF-ASI-05 | Generar reportes de asistencia y de inasistencias frecuentes, exportables a PDF y Excel. | Alta | 2 |
| RF-ASI-06 | Alertar a coordinación cuando un alumno supera un umbral configurable de faltas. | Media | 2 |
| RF-ASI-07 | Bloquear la edición de asistencias de fechas antiguas, salvo permiso de Coordinación o Director. | Media | 2 |
| RF-ASI-08 | Avisar a los apoderados de las inasistencias por notificación o correo. | Baja | 3 |

## 4. Requerimientos de la aplicación móvil

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RM-01 | Aplicación Android nativa (Kotlin, Jetpack Compose, arquitectura MVVM), Android 8.0 (API 26) o superior, sin lógica de negocio duplicada. | Alta | 1B |
| RM-02 | Inicio de sesión con la cuenta del sistema; token guardado en almacenamiento cifrado (Keystore); cierre de sesión; desbloqueo biométrico opcional. | Alta | 1B |
| RM-03 | Perfil docente: ver sus cursos, secciones y unidades; registrar y editar avances; ver registros pendientes. | Alta | 1B |
| RM-04 | Perfil Dirección y Coordinación: panel de indicadores, consulta de avance por curso y docente, validación de avances, resumen de deuda por aula. | Alta | 1B |
| RM-05 | Perfil Tesorería: consultar alumno y estado de cuenta, y compartir la constancia en PDF. | Media | 1B |
| RM-06 | Perfil Tesorería: registrar pagos desde el móvil. | Media | 2 |
| RM-07 | Perfil apoderado: estado de cuenta, constancias y comunicados (función activable por colegio). | Media | 2 |
| RM-08 | Notificaciones push mediante Firebase Cloud Messaging. | Media | 2 |
| RM-09 | Uso con conexión intermitente: caché de lectura y cola de avances pendientes que se sincronizan al reconectar; ante conflicto prevalece la versión del servidor, con aviso al usuario. | Media | 2 |
| RM-10 | Interfaz adaptada a pantallas de teléfono y tablet, en español, con contraste y tamaños de texto accesibles. | Media | 1B |
| RM-11 | Identidad visual por colegio (nombre y logo) según la cuenta del usuario. | Media | 1B |
| RM-12 | Versión mínima soportada controlada desde la API, con aviso de actualización obligatoria. | Media | 1B |
| RM-13 | Distribución por APK firmado en el piloto y publicación en Google Play en Fase 2. | Media | 2 |
| RM-14 | Rendimiento: inicio en menos de 3 segundos y respuestas de pantalla en menos de 1,5 segundos en red 4G. | Media | 1B |
| RM-15 | Versión iOS o PWA: evaluación de factibilidad. | Baja | 3 |
| RM-16 | Registrar la asistencia diaria de sus secciones desde el móvil (docentes y auxiliares). | Alta | 2 |

## 5. Frontend web (React + TypeScript)

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RFW-01 | SPA en React con TypeScript en modo estricto, construida con Vite; sin uso injustificado de any. | Alta | 1 |
| RFW-02 | Estructura por funcionalidades (una carpeta por módulo), componentes reutilizables compartidos y una única capa de acceso a la API con cliente HTTP tipado. | Alta | 1 |
| RFW-03 | Enrutamiento con rutas protegidas por rol y permiso, y carga diferida por módulo. | Alta | 1 |
| RFW-04 | Autenticación: inicio y cierre de sesión, recuperación de contraseña y manejo de sesión vencida, que lleva al inicio de sesión y regresa a la ruta anterior. | Alta | 1 |
| RFW-05 | Estado del servidor con TanStack Query (caché, reintentos e invalidación tras cada cambio); estado local mínimo. | Alta | 1 |
| RFW-06 | Formularios con React Hook Form y validación Zod alineada con las reglas del backend, mostrando también los errores que devuelve el servidor por campo. | Alta | 1 |
| RFW-07 | Tablas con paginación, orden, filtros y búsqueda en el servidor; 25 filas por defecto. | Alta | 1 |
| RFW-08 | Menú y pantallas según el rol del usuario; las acciones sin permiso se ocultan, aunque la autorización real está en el backend. | Alta | 1 |
| RFW-09 | Identidad visual por colegio (nombre y logo) obtenida del subdominio mediante un endpoint público. | Media | 1 |
| RFW-10 | Estados de carga, vacío y error en todas las pantallas; avisos al usuario; confirmación en acciones de dinero o irreversibles, como anular un pago. | Alta | 1 |
| RFW-11 | Evitar el doble envío en operaciones de dinero: botón deshabilitado durante el envío y envío de Idempotency-Key. | Alta | 1 |
| RFW-12 | Descarga de reportes, fichas y constancias en PDF y Excel generados por el servidor. | Alta | 1 |
| RFW-13 | Impresión de constancias y reportes con estilos de impresión. | Media | 1 |
| RFW-14 | Diseño responsive desde 360 px de ancho, navegable con teclado, con etiquetas y contraste adecuados. | Alta | 1 |
| RFW-15 | Textos en español centralizados y formatos es-PE: soles y fecha dd/mm/aaaa. | Media | 1 |
| RFW-16 | Panel del Director con gráficos de matrícula, recaudación, deuda y avance. | Media | 1 |
| RFW-17 | Tipos TypeScript generados automáticamente desde el contrato OpenAPI de la API. | Media | 1 |
| RFW-18 | Manejo global de errores con una pantalla de recuperación y registro de errores del frontend. | Media | 1 |
| RFW-19 | Instalación como aplicación (PWA) como alternativa a una app para iOS. | Baja | 3 |

## 6. API (Laravel)

| ID | Requerimiento | Prioridad | Fase |
|---|---|---|---|
| RAP-01 | API REST en JSON bajo /api/v1, con API Resources; respuestas con estructura uniforme (data, meta, links) y errores uniformes (message, errors, code). | Alta | 1 |
| RAP-02 | Autenticación con Laravel Sanctum: cookie de sesión para la SPA y tokens personales con vencimiento para la app móvil; el token se revoca al cerrar sesión. | Alta | 1 |
| RAP-03 | Autorización por rol y por colegio en cada endpoint mediante Policies; el colegio nunca se recibe como parámetro libre. | Alta | 1 |
| RAP-04 | Paginación, filtros y orden estandarizados (page, per_page con máximo de 100, sort y filtros por campo). | Alta | 1 |
| RAP-05 | Idempotencia en el registro de pagos mediante la cabecera Idempotency-Key, para que un reintento no duplique el pago. | Alta | 1 |
| RAP-06 | Validación con Form Requests, mensajes en español y respuesta 422 con errores por campo. | Alta | 1 |
| RAP-07 | Límite de peticiones por usuario e IP, más estricto en el inicio de sesión y la recuperación de contraseña. | Alta | 1 |
| RAP-08 | CORS restringido a los subdominios autorizados y cabeceras de seguridad. | Alta | 1 |
| RAP-09 | Documentación OpenAPI generada desde el código y publicada solo en el entorno de pruebas. | Media | 1 |
| RAP-10 | Versionado en la ruta; los cambios incompatibles generan una nueva versión; la API informa la versión mínima soportada de la app móvil. | Media | 1 |
| RAP-11 | Generación de PDF y Excel en el servidor; los reportes pesados se procesan en cola. | Alta | 1 |
| RAP-12 | Endpoint público mínimo con la identidad del colegio (nombre, logo, colores) según el subdominio, sin datos sensibles y con límite de peticiones. | Media | 1 |
| RAP-13 | Endpoint de estado del servicio para monitoreo. | Baja | 1 |
| RAP-14 | Preparada para integraciones futuras: pasarelas de pago, comprobantes electrónicos y mensajería. | Baja | 3 |

## 7. Reglas de datos y de negocio

- Toda tabla de datos del colegio lleva `colegio_id` (FK e índices). Montos DECIMAL(10,2) en PEN. Zona horaria America/Lima.
- Eliminación lógica en entidades de negocio. Pagos, constancias y auditorías nunca se eliminan: se anulan con motivo, usuario y fecha.
- Nombres de tablas, modelos y campos en español.

| ID | Regla |
|---|---|
| RN-01 | Saldo de una cuota = monto neto (monto menos descuentos) menos la suma de pagos aplicados no anulados. |
| RN-02 | Cuota pagada si saldo 0; parcial si tiene pagos y saldo mayor a 0; vencida si pasó su vencimiento y el saldo es mayor a 0. |
| RN-03 | Un pago no puede aplicarse por más que el saldo pendiente de las cuotas elegidas. |
| RN-04 | El estado de una cuota nunca se modifica a mano; se deriva de los pagos. |
| RN-05 | Pagos y constancias no se eliminan; se anulan con motivo. |
| RN-06 | Una sección no admite más matriculados que su capacidad, salvo autorización del Director con motivo. |
| RN-07 | El avance de una unidad está entre 0 y 100; reducirlo exige un motivo registrado. |
| RN-08 | Un docente solo registra avances y asistencia en los cursos y secciones que tiene asignados. |
| RN-09 | Constancias numeradas de forma correlativa e ininterrumpida por colegio y serie. |
| RN-10 | Los datos ficticios se identifican como tales y no se mezclan con datos reales en el mismo entorno. |
| RN-11 | Un alumno tiene un solo registro de asistencia por fecha y sección; las correcciones quedan auditadas. |

## 8. Requerimientos no funcionales

| Categoría | ID | Requerimiento |
|---|---|---|
| Rendimiento | RNF-01 | El 95 % de las respuestas de la API toma menos de 1 s con 30 usuarios simultáneos; los listados se paginan; los reportes se generan en menos de 5 s; la API móvil responde en menos de 1,5 s. |
| Capacidad | RNF-02 | Hasta 500 alumnos activos y 60 usuarios por colegio. La plataforma soporta 10 colegios (5 000 alumnos) sin rediseñar la arquitectura. |
| Disponibilidad | RNF-03 | Disponibilidad mensual de 99 %. Mantenimientos fuera del horario escolar y avisados con anticipación. |
| Seguridad | RNF-04 | HTTPS obligatorio (TLS 1.2 o superior), con redirección automática y HSTS. |
|  | RNF-05 | Contraseñas con hash bcrypt o Argon2; longitud mínima de 10 caracteres. |
|  | RNF-06 | Protección frente a OWASP Top 10: ORM con consultas parametrizadas, escape de salida, protección CSRF, validación en servidor, límite de peticiones y cabeceras de seguridad. |
|  | RNF-07 | Mínimo privilegio. Credenciales en archivo .env fuera del directorio público; depuración desactivada en producción. |
|  | RNF-08 | El aislamiento entre colegios se verifica con pruebas automatizadas en cada versión. |
|  | RNF-09 | Los registros de acceso y errores se conservan 90 días. |
| Protección de datos | RNF-10 | Cumplimiento de la Ley N.° 29733: el colegio es titular de los datos y Cloud Business Solutions actúa como encargado, formalizado en un acuerdo de confidencialidad y tratamiento de datos. Se recolectan solo los datos necesarios, con especial cuidado por tratarse de datos de menores. |
| Respaldo | RNF-11 | Respaldo diario automático de base de datos y archivos; retención de 14 copias diarias y 4 semanales; copia fuera del servidor; prueba de restauración al menos cada trimestre. RPO máximo de 24 h y RTO máximo de 8 h. |
| Usabilidad | RNF-12 | Interfaz responsive desde 360 px de ancho; compatible con las dos últimas versiones de Chrome, Edge, Firefox y Safari. Registrar un pago toma menos de 1 minuto. Un usuario nuevo domina los flujos básicos con una capacitación de 4 horas. |
| Localización | RNF-13 | Español de Perú, soles, fechas dd/mm/aaaa. Textos centralizados para permitir otros idiomas. |
| Mantenibilidad | RNF-14 | Backend: Laravel con arquitectura por capas y servicios de dominio, estilo PSR-12. Frontend: TypeScript en modo estricto, ESLint y Prettier, estructura por funcionalidades. Git con ramas y revisión de código, migraciones de base de datos versionadas, README y documentación técnica. |
|  | RNF-15 | Backend: cobertura mínima del 60 % en servicios críticos (pagos, saldos, matrícula, aislamiento entre colegios). Frontend: pruebas de componentes y formularios con Vitest y Testing Library, y pruebas de extremo a extremo con Playwright de login, matrícula y registro de pago. |
|  | RNF-16 | API versionada (/api/v1), documentada con OpenAPI y con formato de errores uniforme. |
| Escalabilidad | RNF-17 | Frontend estático sin estado en el servidor; API sin estado de sesión en disco local, tareas pesadas (reportes, correos) en cola, índices con colegio_id, y ruta de migración a VPS o nube sin reescritura. |
| Compatibilidad | RNF-18 | PHP 8.2 o superior y MySQL 8 o MariaDB 10.6 o superior, disponibles en cPanel. Node.js 20 o 22 LTS solo en desarrollo y para compilar el frontend; no se requiere en el servidor. |
| Frontend | RNF-19 | Carga inicial de la SPA en menos de 3 s en red 4G; JavaScript inicial menor a 300 KB comprimido, con carga diferida por módulo; archivos con nombre versionado para el caché. |
|  | RNF-20 | Sin tokens en localStorage; cookies HttpOnly, Secure y SameSite; política de seguridad de contenido (CSP); no usar contenido HTML sin sanitizar; npm audit sin vulnerabilidades altas o críticas antes de cada despliegue. |
|  | RNF-21 | Accesibilidad básica WCAG 2.1 nivel AA en los flujos críticos: inicio de sesión, matrícula y registro de pagos. |

## 9. Fases de entrega

| Fase | Contenido |
|---|---|
| 1, núcleo | API y SPA: instituciones, seguridad, configuración académica, alumnos, matrícula, pensiones y pagos, deudas, reportes, entorno de pruebas, hosting y dominio. |
| 1B, cierre | Avance de unidades didácticas y versión inicial de la app móvil. |
| 2 | Asistencia, portal y app de apoderados, pagos reportados con captura (Yape), push, sin conexión, pagos desde el móvil, mora, matrícula masiva, Google Play, exportación de datos. |
| 3 | Pasarela de pagos, comprobantes electrónicos, notas, iOS o PWA, panel de plataforma, modelo comercial. |
