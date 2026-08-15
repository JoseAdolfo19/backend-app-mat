# MathFlow Backend

API REST de la plataforma educativa MathFlow — gestiona **autenticación** (email + Google OAuth), **lecciones**, **evaluaciones**, **exámenes con anti-trampa**, **progreso estudiantil**, **reportes** (PDF/Excel), **chat IA** (Profesor Euler), **notificaciones push** (FCM) y **administración** (usuarios, config, backups). Trilingüe (español, inglés y quechua).

---

## Stack tecnológico

| Tecnología | Uso |
|------------|-----|
| Laravel 12 (PHP ^8.2) | Framework backend / API |
| Laravel Sanctum 4.x | Autenticación por tokens (Bearer) con expiración |
| MySQL 8.0+ / MariaDB 10.6+ | Base de datos |
| laravel/socialite + google/apiclient | Login con Google (web + móvil) |
| maatwebsite/excel | Exportación de reportes en Excel |
| barryvdh/laravel-dompdf | Exportación de reportes en PDF |
| Firebase Cloud Messaging (FCM v1) | Notificaciones push (Android/iOS/Web) |
| Groq API (OpenAI-compatible) | Chat IA "Profesor Euler" (streaming SSE) |

---

## Requisitos e instalación

- PHP 8.2+
- MySQL 8.0+ / MariaDB 10.6+
- Composer 2.x
- Extensiones: mbstring, xml, ctype, json, bcmath, pdo, tokenizer

```bash
composer install
cp .env.example .env      # (en producción: cp .env.production .env)
php artisan key:generate
php artisan migrate --seed
php artisan serve         # http://localhost:8000
```

### Scripts

```bash
php artisan serve          # Servidor de desarrollo
php artisan migrate --seed # Migraciones + datos demo
php artisan test           # Suite de tests (Feature + Unit)
php artisan mathflow:create-admin # Crear usuario administrador (CLI)
php artisan schedule:run    # Tareas programadas (prune tokens, limpieza)
```

---

## Variables de entorno (`.env`)

| Variable | Descripción | Default |
|----------|-------------|---------|
| `APP_NAME` | Nombre de la aplicación | `MathFlow` |
| `APP_ENV` | Entorno (`local` / `production`) | `local` |
| `APP_KEY` | Clave de encriptación (generar con `key:generate`) | — |
| `APP_DEBUG` | Muestra detalles de error (`false` en producción) | `true` |
| `APP_URL` | URL base del backend | `http://localhost` |
| `APP_LOCALE` | Idioma por defecto | `es` |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` | Conexión MySQL | `mysql` / `127.0.0.1` / `3306` |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Credenciales de BD | `mathlearn_db` / `root` / — |
| `SESSION_DRIVER` / `SESSION_LIFETIME` | Sesiones en BD, 120 min | `database` / `120` |
| `SESSION_ENCRYPT` / `SESSION_SECURE_COOKIE` | Hardening de sesión (prod) | `false` / `false` |
| `QUEUE_CONNECTION` | Cola de trabajos | `database` |
| `CACHE_STORE` | Almacén de caché | `database` |
| `MAIL_MAILER` / `MAIL_*` | Correo (log en local, SMTP Mailgun en prod) | `log` |
| `SANCTUM_STATEFUL_DOMAINS` | Dominios stateful de Sanctum | `localhost:8000,localhost:5173` |
| `SANCTUM_EXPIRATION` | TTL del token (minutos) | `1440` |
| `CORS_ALLOWED_ORIGINS` | Orígenes permitidos por el middleware CORS | `http://localhost:5173,http://localhost:8000,https://frontend-app-mat.vercel.app` |
| `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` / `GOOGLE_REDIRECT_URI` | OAuth de Google (Cloud Console) | — |
| `GROQ_API_KEY` | API key de Groq para el chat IA | — |
| `FCM_SERVER_KEY` / `FCM_PROJECT_ID` | Credenciales de Firebase para push | — |

> ⚠️ `.env` y `.env.production` contienen secretos reales. No versionar; usar `.env.example` como plantilla. Ver `DEPLOY.md` para el despliegue en producción.

---

## Estructura general

```
backend-app-mat/
├── app/
│   ├── Console/Commands/     # Comandos Artisan
│   ├── Exports/              # Exportaciones Excel / PDF
│   ├── Http/
│   │   ├── Controllers/Api/  # 16 controladores API
│   │   ├── Middleware/       # CORS, audit, rate limit, roles, caché
│   │   └── Requests/Auth/    # Form Requests (Login, Register)
│   ├── Models/               # 20 modelos Eloquent (UUID PKs)
│   ├── Policies/             # Autorización (Lesson, Evaluation)
│   ├── Providers/            # AppServiceProvider, AuthServiceProvider
│   ├── Services/             # PushNotificationService, ActivityService
│   └── Traits/               # AuditLoggable
├── bootstrap/app.php         # Aliases de middleware y manejo de excepciones
├── config/                   # Configuración de Laravel + servicios externos
├── database/
│   ├── migrations/           # 35 migraciones
│   ├── seeders/              # Roles, admin, demo, contenido matemático
│   └── factories/            # UserFactory
├── docs/                     # Manuales y guías (español)
├── deploy/                   # supervisor.conf para workers
├── lang/                     # es.php, en.php, qu.php (trilingüe, ~176 claves)
├── resources/views/          # Vistas (welcome + reporte PDF)
├── routes/                   # api.php, web.php, console.php
└── tests/                    # Feature (8) + Unit (2) — 10 archivos
```

---

## Descripción de archivos por ubicación

### Raíz del proyecto

| Archivo | Qué hace |
|---------|----------|
| `artisan` | CLI de Laravel (comandos, migraciones, seeders, scheduler). |
| `composer.json` | Manifiesto PHP: Laravel 12, Sanctum, Socialite, Excel, DomPDF, google/apiclient; autoload PSR-4 de `app/`. |
| `.env` / `.env.example` / `.env.production` | Variables de entorno (dev / plantilla / producción endurecida). ⚠️ Contienen secretos. |
| `DEPLOY.md` | Guía de despliegue en producción (requisitos, pasos, workers Supervisor, cron). |
| `package.json` / `vite.config.js` | Bundling de assets (Vite) para las vistas Blade. |
| `phpunit.xml` | Configuración de la suite de tests (PHPUnit). |
| `.gitignore` / `.gitattributes` / `.editorconfig` | Reglas de versionado y estilo. |
| `.phpunit.result.cache` | Caché de resultados de tests (generada). |

### `bootstrap/app.php` — Arranque de la aplicación

| Archivo | Qué hace |
|---------|----------|
| `bootstrap/app.php` | **Centro de configuración HTTP.** Registra aliases de middleware (`role`, `cors`, `auth.active`, `rate.limit`, `cache.api`, `audit`), los aplica globalmente a todas las peticiones (`Cors`, `SecurityHeaders`, `GlobalRateLimit`), configura redirección de invitados y define el **manejo de excepciones** (oculta detalles en producción y devuelve errores JSON en español para la API: 404/403/401/422/429). |

### `app/Console/Commands/`

| Archivo | Qué hace |
|---------|----------|
| `CreateAdminUser.php` | Comando `php artisan mathflow:create-admin` — crea (o actualiza) interactivamente un usuario admin con `--email`, `--name` y `--password`. |

### `app/Exports/` — Exportaciones de reportes

| Archivo | Qué hace |
|---------|----------|
| `GradesExport.php` | Exportación **Excel** de calificaciones (`FromCollection/WithHeadings/WithMapping/WithStyles`). Encabezados **trilingües** (es/en/qu), filas con estudiante, evaluación, lección, área, puntaje, correctas/total y fecha. |
| `GradesPDFExport.php` | **Datos para el PDF de calificaciones/rendimiento**: join de `evaluation_results` con evaluations/users/lessons, filtros opcionales por docente, estudiante, evaluación o área, y cálculo del resumen (totales, promedio, máx/mín, por área). |
| `StudentProgressExport.php` | Exportación **Excel** del progreso de un estudiante: filas de evaluaciones (puntaje) y lecciones (progreso %/estado). |
| `StudentProgressPDFExport.php` | **Datos para el PDF de progreso estudiantil**: lecciones con estado/progreso y evaluaciones con puntaje, más estadísticas (completadas, en progreso, promedio). |

### `app/Http/Controllers/` — Controladores

#### Base

| Archivo | Qué hace |
|---------|----------|
| `Controller.php` | Clase base abstracta de todos los controladores (vacía). Todos los controladores API la extienden directamente. |

#### `Api/` — Controladores API

| Archivo | Qué hace |
|---------|----------|
| `AuthController.php` | **Autenticación** email+password: `register` (crea StudentProfile/TeacherProfile según rol, token con habilidad de plataforma), `login`, `profile`, `updateProfile`, `changePassword` (invalida todos los tokens salvo el actual), `logout`/`logoutPlatform`/`logoutAll`, `devices` (sesiones activas por token), `refreshToken`, `connectGoogle`/`disconnectGoogle`, `sendVerificationEmail`/`verifyEmail` (código 6 dígitos por caché). |
| `GoogleAuthController.php` | **OAuth Google**: `redirectToGoogle`/`handleGoogleCallback` (flujo web, entrega un auth_code corto en caché), `loginWithGoogleToken` (login móvil con ID token verificado por google/apiclient) y `exchangeCode` (intercambia el auth_code por el token Bearer). Crea el usuario como estudiante si no existe. |
| `PasswordResetController.php` | **Recuperación de contraseña**: `sendResetLink` (usa el broker de Laravel) y `resetPassword` (valida token + resetea). |
| `LessonController.php` | **CRUD de lecciones**: listar con filtros (dificultad, unidad, tema, búsqueda, tag; estudiantes solo ven publicadas), `show` (crea progreso inicial y cuenta vistas), `content` (solo contenido, optimizado para móvil), crear/editar (con **sanitización de HTML**), soft-delete, `publish`/`unpublish` (notifican a estudiantes), `duplicate`, recursos (`addResource`/`removeResource`), `getByUnit`, `getStats` (vistas, completados, tasa) y `recommended` (según promedio del estudiante). |
| `EvaluationController.php` | **CRUD de evaluaciones** con tipos (exam/quiz/homework/practice): listar con filtros, CRUD, `publish`/`unpublish` (notifica en masa), `duplicate` (replica evaluación + preguntas), preguntas (`addQuestion`/`updateQuestion`/`deleteQuestion` con actualización de totales), `submit` (corrige respuestas con **normalización matemática** que también mapea `Verdadero`/`Falso`/`true`/`false` a canónicos, escala 0–20, controla intentos y `time_taken` anti-trampa), `getResults`/`getStudentResult`, `getStats` (distribución, mediana, tasa de aprobación) y `adaptive` (sugiere práctica según rendimiento). |
| `ExamController.php` | **Exámenes separados de las evaluaciones**: CRUD con preguntas en lote, `activate`/`deactivate`, `startAttempt` (retoma intento en curso o valida máximos), `submitAttempt` (corrige y calcula nota 0–20; `normalizeAnswer` mapea `Verdadero`/`Falso`/`true`/`false`/`v`/`f` a canónicos para el auto-cálculo), `reportCheating` (registra eventos anti-trampa, marca `CHEATING_DETECTED` tras 3 cambios de pestaña y notifica al docente) y `getExamStats` (intentos, distribución, incidentes). |
| `ProgressController.php` | **Progreso y estadísticas**: `studentDashboard` (completo, web), `studentStats` (ligero, móvil), `teacherDashboard`, `getLessonProgress`/`updateLessonProgress` (avance %, tiempo, estado, **lógica de rachas** y promedio), `getMyStats` (resumen + actividad reciente), `getBadges` (6 insignias disponibles) y `studentLevel` (beginner/intermediate/advanced según promedio). |
| `ReportController.php` | **Reportes** para docente/admin: `performanceReport`, `gradesReport`, `studentReport` (fortalezas/debilidades/recomendaciones), `filteredPerformanceReport`, `studentDetailReport` (por área con ranking), `courseDetailReport` (por unidad con estadísticas), `participationReport` (30 días de activity_log) y **exportaciones PDF/Excel** (`exportPDF`, `exportExcel`, `exportPerformancePDF/Excel`, `exportStudentReportPDF/Excel`, `exportGradesPDF/Excel`) con HTML builders de dompdf. |
| `AdminController.php` | **Panel admin**: dashboard (stats del sistema), **usuarios** (listar/filtrar, ver, crear, editar, soft-delete con protecciones — no eliminar admin, no autoeliminarse, no eliminar último admin — activar/desactivar, **import CSV** con sanitización anti-fórmulas, **export CSV**), `getConfig`/`updateConfig` (InstitutionConfig), **períodos académicos** (CRUD con única activa) y **backups** (`mysqldump` con validación de nombre y descarga segura). |
| `NotificationController.php` | **Notificaciones in-app**: listar (filtros, paginado), `unreadCount`, `markAsRead`/`markAllAsRead`, `destroy`/`deleteRead`. Además expone **métodos estáticos** reutilizados en toda la app: `createNotification`, `createAndPush` (in-app + push), `createBulkNotifications` y `createBulkAndPush`. |
| `DeviceController.php` | **Registro de dispositivos para push**: `register` (updateOrCreate por usuario+token+plataforma), `unregister` (desactiva) y `list`. |
| `AiController.php` | **IA "Profesor Euler" (Groq)**: proxy seguro que protege la API key. Límite diario de 50 requests por token (`daily_requests`), prompt de sistema que restringe el asistente a matemáticas y prohíbe resolver tareas, y **respuesta en streaming SSE**. Incluye `generateLesson` (genera una lección completa en JSON desde el editor del docente) y `callGroq`/`extractJson` como helpers internos. |
| `ParentController.php` | **Padres de familia**: `index` (hijos vinculados), `childProgress` (lecciones y evaluaciones del hijo con validación de vínculo) y `childReport` (resumen, desempeño por tema, tasa de aprobación). |
| `GuestStudentController.php` | **Consulta pública de notas por DNI**: `generateCaptcha` (código en sesión) y `lookup` (valida captcha + DNI de 8 dígitos, devuelve promedio, racha, insignias, resultados por área sin exponer datos sensibles). |
| `SubmittedWorkController.php` | **Trabajos estudiantiles / tablero**: listar (filtros por estudiante/lección/evaluación/examen/estado/tipo), `store` (asocia puntaje automático desde EvaluationResult/LessonProgress/ExamAttempt), `show`, `grade` (nota 0–20 + feedback, rol docente), `returnWork`, `studentSummary` (estadísticas por área) y `autoGenerateFromCompleted` (genera trabajos desde progreso completado). |
| `RankingController.php` | **Rankings**: `courseRanking` (por unidad/área, con período), `overallRanking` (global) y `myPosition` (posición del estudiante). Combina promedios de evaluaciones y trabajos. |

### `app/Http/Middleware/` — Middleware

| Archivo | Qué hace |
|---------|----------|
| `Cors.php` | **CORS personalizado** (no usa el paquete): responde 204 a OPTIONS, aplica orígenes de `config('app.cors_origins')`, credenciales, métodos y headers. Se salta headers para apps móviles (sin Origin). Alias `cors`. |
| `SecurityHeaders.php` | Cabeceras de seguridad: `X-Content-Type-Options`, `X-Frame-Options DENY`, `Referrer-Policy`, `Permissions-Policy`, y HSTS en HTTPS. |
| `GlobalRateLimit.php` | Límite global de 60 req/min para todas las peticiones (clave por token o IP). |
| `ApiRateLimit.php` | Límite de 60 req/min por petición autenticada (`api_token_{userId}` / `api_ip_{ip}`); devuelve 429 con `Retry-After` y headers `X-RateLimit`. Alias `rate.limit`. |
| `AuditLog.php` | Registra POST/PUT/PATCH/DELETE en `audit_logs` (usuario, acción, IP, user-agent, plataforma `X-Platform`, status code). Alias `audit`. |
| `Authenticate.php` | Guard de autenticación: devuelve `null` para peticiones JSON/API (sin redirección) y redirige a `/` en web. Alias `auth.active`. |
| `CacheResponse.php` | **Caché HTTP** para GET exitosos: ETag (md5), Cache-Control private y `304` si `If-None-Match` coincide. Alias `cache.api`. |
| `RoleMiddleware.php` | **RBAC**: recibe roles permitidos como argumentos (`role:teacher,admin`); 401 sin sesión, 403 si el rol no coincide. Alias `role`. |

### `app/Http/Requests/Auth/`

| Archivo | Qué hace |
|---------|----------|
| `LoginRequest.php` | Valida `email` (requerido, formato) y `password` (requerido, min 8). Mensajes de error en español vía `__()`. |
| `RegisterRequest.php` | Valida registro: `full_name`, `email` único, `password` confirmado (min 8), `role` (**solo student/parent** — docente y admin se crean vía panel/CLI), `academic_level` condicionado a estudiante. |

### `app/Models/` — 20 modelos Eloquent (UUID PK)

Todos los modelos de dominio usan `HasUuids` con `$keyType = 'string'` y `$incrementing = false` (claves primarias UUID). La excepción es `roles` (auto-increment).

| Modelo | Tabla | Qué hace |
|--------|-------|----------|
| `User` | `users` | Cuenta de usuario: email, password, full_name, dni, role_id, is_active, last_login, google_id/provider, soft-deletes. Relaciones con role, studentProfile, teacherProfile, lessons, evaluations, children/parents (pivot `parent_student`). Helpers `isAdmin()/isTeacher()/isStudent()/isParent()`. Audita create/update/delete. |
| `Role` | `roles` | Catálogo de roles (admin, teacher, student, parent) con constantes. |
| `AcademicPeriod` | `academic_periods` | Período académico (nombre, fechas, activo). |
| `ActivityLog` | `activity_log` | Feed genérico de actividad (tipo, sujeto, metadata). |
| `AuditLog` | `audit_logs` | Auditoría de peticiones y modelos (acción, old/new values, IP, user-agent, plataforma). |
| `DeviceToken` | `device_tokens` | Dispositivo registrado para push (token, plataforma, activo, last_used_at). |
| `Evaluation` | `evaluations` | Evaluación (tipo, dificultad, límite de tiempo, fecha límite, intentos máx, publicada). Constantes de tipo/dificultad; scopes `published/byType/byDifficulty`. |
| `EvaluationResult` | `evaluation_results` | Resultado de evaluación por estudiante (puntaje 0–20, correctas, tiempo, intento). |
| `Exam` | `exams` | Examen separado de evaluaciones (activo/publicado, preguntas aleatorias). |
| `ExamAttempt` | `exam_attempts` | Intento de examen (respuestas JSON, time_spent, tab_switch_count, cheat_log). |
| `ExamQuestion` | `exam_questions` | Pregunta de examen (opción múltiple, verdadero-falso, abierta). |
| `InstitutionConfig` | `institution_configs` | Config institucional de una sola fila (colores, logo, notificaciones, backups). |
| `Lesson` | `lessons` | Lección de contenido (HTML, unidad, tema, dificultad, tags, recursos, vistas). |
| `LessonProgress` | `lesson_progress` | Progreso del estudiante por lección (%, estado, tiempo, última posición). |
| `Notification` | `notifications` | Notificación in-app (título, mensaje, tipo, leída, link). |
| `Question` | `questions` | Pregunta de evaluación (opción múltiple, completar, drag&drop, fórmula). |
| `StudentAnswer` | `student_answers` | Respuesta del estudiante por pregunta (correcta, puntos). |
| `StudentProfile` | `student_profiles` | Métricas del estudiante (nivel, lecciones completadas, promedio, racha, insignias). |
| `SubmittedWork` | `submitted_works` | Trabajo entregado (tipo, estado, puntaje, feedback del docente). |
| `TeacherProfile` | `teacher_profiles` | Datos del docente (departamento, especialización, experiencia). |

### `app/Policies/`

| Archivo | Qué hace |
|---------|----------|
| `LessonPolicy.php` | Autorización de lecciones (ver/crear/editar/eliminar/publicar) según admin/propietario/estudiante. |
| `EvaluationPolicy.php` | Autorización de evaluaciones (ver/crear/editar/enviar) según admin/propietario/estudiante. |

### `app/Providers/`

| Archivo | Qué hace |
|---------|----------|
| `AppServiceProvider.php` | Provider base (register/boot por defecto). |
| `AuthServiceProvider.php` | Registra las políticas: `Lesson::class => LessonPolicy::class` y `Evaluation::class => EvaluationPolicy::class`. |

### `app/Services/`

| Archivo | Qué hace |
|---------|----------|
| `PushNotificationService.php` | **Cliente FCM v1** (Firebase Cloud Messaging): `sendToUser` (todos los dispositivos activos, desactiva tokens inválidos), `sendToDevice` (payload Android/APNS), `sendToUsers` (masivo con conteos) y `sendAndStore` (crea la notificación in-app y luego envía el push). |
| `ActivityService.php` | Registro de actividad: `log($type, $subject, $metadata)` escribe en `activity_log` con el usuario autenticado. |

### `app/Traits/`

| Archivo | Qué hace |
|---------|----------|
| `AuditLoggable.php` | Trait que expone `logAudit($action, $old, $new)` para escribir en `audit_logs` (usado por los hooks de boot de User/Lesson/Evaluation). |

### `config/` — Configuración

| Archivo | Qué hace |
|---------|----------|
| `app.php` | **Personalizado**: añade `cors_origins` desde `CORS_ALLOWED_ORIGINS` (usado por el middleware Cors). |
| `auth.php` | Guardas y brokers de autenticación. |
| `cache.php` | Almacén de caché (database por defecto). |
| `cors.php` | Config del paquete CORS de Laravel (no usado; el middleware `Cors` la reemplaza). |
| `database.php` | Conexión MySQL y Redis. |
| `filesystems.php` | Discos local/public/S3. |
| `logging.php` | Canales de log (stack/daily). |
| `mail.php` | Configuración de correo. |
| `queue.php` | Conexión de colas (database). |
| `sanctum.php` | **Personalizado**: dominios stateful, expiración de tokens (`SANCTUM_EXPIRATION`), prune days. |
| `services.php` | **Personalizado**: credenciales de `google` (OAuth), `fcm` (key/project_id) y `groq` (key, url, model `llama-3.3-70b-versatile`). |
| `session.php` | Sesiones en BD (driver database, lifetime 120). |

### `database/migrations/` — 35 migraciones

**Patrón UUID:** todas las tablas de dominio usan `$table->uuid('id')->primary()` y FKs `uuid(...)`. Las tablas del framework (cache, jobs, sessions, personal_access_tokens, password_reset_tokens) usan tipos por defecto. `roles` es la única con id auto-increment.

| Archivo | Crea/Modifica | Propósito y columnas clave |
|---------|---------------|----------------------------|
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` | Caché del framework. |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` | Colas del framework. |
| `2014_10_12_000000_create_roles_table.php` | `roles` | Catálogo de roles (admin/teacher/student, luego +parent). |
| `2014_10_12_100000_create_users_table.php` | `users` | Usuarios: uuid PK, email único, password (nullable para Google), full_name, role_id FK, is_active, google_id/provider. |
| `2024_01_01_000001_create_student_profiles_table.php` | `student_profiles` | Métricas del estudiante (nivel, promedio, racha, insignias). |
| `2024_01_01_000002_create_teacher_profiles_table.php` | `teacher_profiles` | Datos del docente. |
| `2024_01_01_000003_create_lessons_table.php` | `lessons` | Contenido HTML, unidad, tema, dificultad, tags, recursos, soft-deletes. |
| `2024_01_01_000004_create_lesson_progress_table.php` | `lesson_progress` | Progreso % por lección, estado, tiempo. |
| `2024_01_01_000005_create_evaluations_table.php` | `evaluations` | Tipo, dificultad, time_limit, due_date, intentos, publicado. |
| `2024_01_01_000006_create_questions_table.php` | `questions` | Banco de preguntas por evaluación. |
| `2024_01_01_000007_create_evaluation_results_table.php` | `evaluation_results` | Puntajes, correctas, intento. |
| `2024_01_01_000008_create_student_answers_table.php` | `student_answers` | Respuestas por pregunta. |
| `2024_01_01_000009_create_academic_periods_table.php` | `academic_periods` | Períodos académicos. |
| `2024_01_01_000010_create_institution_configs_table.php` | `institution_configs` | Branding institucional (colores, logo, backups). |
| `2024_01_01_000011_create_notifications_table.php` | `notifications` | Notificaciones in-app. |
| `2024_01_01_000012_create_personal_access_tokens_table.php` | `personal_access_tokens` | Tokens Sanctum (uuidMorphs tokenable). |
| `2024_01_01_000013_create_password_reset_tokens_table.php` | `password_reset_tokens` | Tokens de reset de contraseña. |
| `2024_01_01_000014_create_audit_logs_table.php` | `audit_logs` | Auditoría de peticiones (IP, user-agent, plataforma). |
| `2024_01_01_000015_add_model_auditing_columns_to_audit_logs_table.php` | `audit_logs` | Columnas de auditoría de modelos (action, auditable, old/new values). |
| `2024_01_01_000015_create_device_tokens_table.php` | `device_tokens` | Registro de dispositivos push. |
| `2024_01_01_000016_add_last_used_at_to_device_tokens_table.php` | `device_tokens` | Columna `last_used_at`. |
| `2026_06_19_220504_create_sessions_table.php` | `sessions` | Sesiones en BD. |
| `2026_07_23_020955_add_missing_columns_to_lessons_and_evaluations_tables.php` | `lessons`, `evaluations` | `published_at`, `views_count`, `total_questions`, `total_points`. |
| `2026_07_23_030000_add_last_activity_date_to_student_profiles_table.php` | `student_profiles` | `last_activity_date` (para rachas). |
| `2026_07_23_040000_add_indexes_to_performance_tables.php` | `lesson_progress`, `evaluation_results`, `student_answers` | Índices de rendimiento. |
| `2026_07_23_050000_add_soft_deletes_and_fix_audit_ip.php` | `audit_logs`, `users`, `questions` | Soft deletes en users/questions. |
| `2026_07_23_060000_add_daily_requests_to_personal_access_tokens.php` | `personal_access_tokens` | `daily_requests` + `daily_reset_at` (límite del chat IA). |
| `2026_07_23_070000_add_parent_role_and_parent_student_table.php` | `parent_student` | Pivot padres↔estudiantes (many-to-many). |
| `2026_07_23_080000_create_activity_log_table.php` | `activity_log` | Feed de actividad. |
| `2026_07_24_000000_add_parent_to_roles_enum.php` | `roles` | ALTER del enum para incluir `parent`. |
| `2026_07_24_090000_add_dni_to_users_table.php` | `users` | `dni` (8 dígitos, único) — DNI peruano. |
| `2026_07_24_100000_create_exams_table.php` | `exams` | Exámenes (activo/publicado, intentos). |
| `2026_07_24_100001_create_exam_questions_table.php` | `exam_questions` | Preguntas de examen. |
| `2026_07_24_100002_create_exam_attempts_table.php` | `exam_attempts` | Intentos con anti-trampa (tab_switch_count, cheat_log). |
| `2026_07_24_110000_create_submitted_works_table.php` | `submitted_works` | Trabajos entregados (tipo, estado, puntaje, feedback). |

### `database/seeders/` y `database/factories/`

| Archivo | Qué hace |
|---------|----------|
| `DatabaseSeeder.php` | Seeder maestro: ejecuta RolesSeeder → AdminUserSeeder → TestDataSeeder → MathContentSeeder → DniSeeder → ExamSeeder → RankingSeeder. |
| `RolesSeeder.php` | Crea los 4 roles (admin/teacher/student/parent) con descripciones. |
| `AdminUserSeeder.php` | Usuario admin `admin@mathflow.com` / `admin123456`. |
| `TestDataSeeder.php` | Dataset demo grande: 3 docentes, 10 estudiantes, 12 lecciones, 8 evaluaciones, 40 preguntas, progreso, resultados, 2 períodos, notificaciones y config. |
| `MathContentSeeder.php` | 28 lecciones de matemáticas en español (Álgebra/Geometría/Trigonometría) con contenido HTML + 5 preguntas cada una; crea al docente `profesor.math@mathflow.com`. |
| `DniSeeder.php` | Asigna DNIs secuenciales a los estudiantes. |
| `ExamSeeder.php` | Crea 3 exámenes con preguntas para el docente de contenido. |
| `RankingSeeder.php` | Backfill de `submitted_works` desde progreso/results/attempts para alimentar los rankings. |
| `factories/UserFactory.php` | Factory por defecto de usuario. |

### `lang/` — Traducciones

| Archivo | Qué hace |
|---------|----------|
| `lang/es.php` | Traducciones en español (~176 claves). |
| `lang/en.php` | Traducciones en inglés (~176 claves). |
| `lang/qu.php` | Traducciones en **quechua** (~176 claves). |

Los tres archivos son estructuralmente paralelos (auth, mensajes de validación, notificaciones, reportes). El locale por defecto es `es`.

### `resources/views/`

| Archivo | Qué hace |
|---------|----------|
| `welcome.blade.php` | Página de bienvenida de Laravel (ruta `/`). |
| `reports/report-pdf.blade.php` | **Vista del reporte PDF** usada por dompdf: encabezado MathFlow, tarjetas de resumen (promedio /20, máximo, mínimo, total, calificación literal AD/A/B/C) y tabla de resultados por estudiante×evaluación. |

### `routes/`

| Archivo | Qué hace |
|---------|----------|
| `api.php` | **Todas las rutas API** bajo el prefijo `/api/v1` (ver mapa de endpoints abajo). |
| `web.php` | Ruta `GET /` que devuelve la vista welcome. |
| `console.php` | Tareas programadas: `sanctum:prune` diario, limpieza de device tokens inactivos >90 días (02:00) y de audit_logs >180 días (02:30). |

### `tests/`

| Archivo | Qué hace |
|---------|----------|
| `TestCase.php` | Base abstracta de los tests. |
| `Feature/AdminApiTest.php` | Endpoints admin: CRUD de usuarios, denegaciones por rol, protecciones (no admin/autoeliminar/último admin), config, dashboard, períodos. |
| `Feature/AuthApiTest.php` | Registro, login, errores 401, perfil, health check. |
| `Feature/EvaluationApiTest.php` | Listado por rol, creación, preguntas, publicación, envío y resultados con estadísticas. |
| `Feature/LessonApiTest.php` | Listado, CRUD, publicar/despublicar, soft-delete, 404. |
| `Feature/NotificationApiTest.php` | Listado, marcar leídas, eliminar, paginación. |
| `Feature/ProgressApiTest.php` | Dashboards, progreso por lección, insignias, rachas. |
| `Feature/ReportApiTest.php` | Reportes de docente/admin, denegaciones de estudiante. |
| `Feature/ExampleTest.php` | Test de humo (GET / → 200). |
| `Unit/UserRolesTest.php` | Helpers de rol (`isAdmin/isTeacher/isStudent`) y relación con StudentProfile. |
| `Unit/ExampleTest.php` | Placeholder trivial. |

### `docs/` y `deploy/`

| Archivo | Qué hace |
|---------|----------|
| `docs/QUICK_START.md` | Guía rápida de instalación del backend. |
| `docs/GUIDE_STUDENT.md` | Guía de inicio para estudiantes. |
| `docs/USER_MANUAL_ADMIN.md` / `USER_MANUAL_TEACHER.md` / `USER_MANUAL_STUDENT.md` / `USER_MANUAL_PARENT.md` | Manuales de usuario por rol. |
| `docs/TRAINING_ADMIN.md` / `TRAINING_TEACHER.md` / `TRAINING_PARENT.md` | Programas de capacitación por rol. |
| `deploy/supervisor.conf` | Programa de Supervisor `mathflow-worker`: 2 workers de `queue:work` con autostart. |

---

## Mapa de endpoints API (`routes/api.php` — prefijo `/api/v1`)

Todas las rutas autenticadas pasan por `auth:sanctum` + `auth.active` + `rate.limit` (60 req/min). Los escritores usan el middleware `audit`; los GET de lectura usan `cache.api` (ETag/304) y `throttle:30,1`.

### Autenticación (`/auth`) — Público
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/auth/register` | Registro (throttle 5/min) |
| POST | `/auth/login` | Login (throttle 10/min) |
| GET | `/auth/google/redirect` | Redirección OAuth Google |
| GET | `/auth/google/callback` | Callback OAuth Google (devuelve `?auth_code=`) |
| POST | `/auth/google/exchange-code` | Cambia auth_code por token |
| POST | `/auth/google/login` | Login móvil con ID token |
| POST | `/auth/forgot-password` | Enviar link de reset |
| POST | `/auth/reset-password` | Resetear contraseña |

### Perfil (`/user`) — Todos los roles
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET/PUT | `/user/profile` | Ver / actualizar perfil |
| PUT | `/user/change-password` | Cambiar contraseña (revoca otras sesiones) |
| POST | `/user/send-verification-email` | Enviar código de verificación |
| POST | `/user/verify-email` | Verificar email con código |
| POST | `/user/connect-google` / `/user/disconnect-google` | Vincular / desvincular Google |
| POST | `/user/logout` / `/user/logout-platform` / `/user/logout-all` | Cerrar sesión (actual / por plataforma / todas) |
| GET | `/user/devices` | Sesiones activas |
| POST | `/user/refresh-token` | Refrescar token |

### Dispositivos y notificaciones
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/devices/register` / `/devices/unregister` | Registrar / quitar dispositivo push |
| GET | `/devices` | Listar dispositivos |
| GET | `/notifications` | Listar notificaciones (filtros) |
| GET | `/notifications/unread-count` | Conteo de no leídas |
| PUT | `/notifications/read-all` / `/{id}/read` | Marcar leídas |
| DELETE | `/notifications/read/delete` / `/{id}` | Eliminar |

### Dashboards y progreso
| Método | Ruta | Acceso | Descripción |
|--------|------|--------|-------------|
| GET | `/dashboard/student` | student | Dashboard completo |
| GET | `/dashboard/student/stats` | student | Dashboard ligero (móvil) |
| GET | `/dashboard/teacher` | teacher | Dashboard docente |
| GET | `/dashboard/admin` | admin | Dashboard admin |
| GET | `/progress/my-stats` | Todos | Estadísticas y actividad |
| GET | `/progress/badges` | Todos | Insignias |
| GET | `/progress/level` | Todos | Nivel del estudiante |

### Lecciones (`/lessons`) — Todos los roles (escritores: teacher)
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/lessons` | Listar con filtros |
| GET | `/lessons/recommended` | Recomendadas según rendimiento |
| GET | `/lessons/unit/{unit}` | Por unidad (antes de `/{id}`) |
| GET | `/lessons/{id}` | Detalle (crea progreso, cuenta vista) |
| GET | `/lessons/{id}/content` | Solo contenido (móvil) |
| GET | `/lessons/{id}/resources` | Recursos |
| GET/POST | `/lessons/{id}/progress` | Ver / actualizar progreso |
| POST/PUT/DELETE | `/lessons` `/lessons/{id}` | CRUD (teacher, audit) |
| POST | `/lessons/{id}/publish` `/unpublish` `/duplicate` | Publicar / despublicar / duplicar |
| POST/DELETE | `/lessons/{id}/resources` `/resources/{resourceId}` | Gestionar recursos |
| GET | `/lessons/{id}/stats` | Estadísticas de la lección |

### Evaluaciones (`/evaluations`) — Todos los roles (escritores: teacher)
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/evaluations` `/evaluations/adaptive` | Listar / práctica adaptativa |
| GET | `/evaluations/{id}` `/questions` `/results` | Ver, preguntas, resultados |
| POST | `/evaluations/{evaluationId}/submit` | Enviar respuestas (estudiante) |
| GET | `/evaluations/{id}/result/{userId}` `/stats` | Resultado individual / stats (teacher,admin) |
| POST/PUT/DELETE | `/evaluations` `/evaluations/{id}` | CRUD (teacher, audit) |
| POST | `/evaluations/{id}/publish` `/unpublish` `/duplicate` | Publicar / despublicar / duplicar |
| POST/PUT/DELETE | `/evaluations/{evaluationId}/questions` | Gestionar preguntas |

### Exámenes (`/exams`)
| Método | Ruta | Acceso | Descripción |
|--------|------|--------|-------------|
| GET | `/exams` | Todos | Listar |
| GET | `/exams/{id}` | Todos | Detalle |
| POST/PUT/DELETE | `/exams` `/exams/{id}` | teacher,admin | CRUD (audit) |
| POST | `/exams/{id}/activate` `/deactivate` | teacher,admin | Activar / desactivar |
| POST | `/exams/{id}/start` | student | Iniciar intento |
| POST | `/exams/attempts/{attemptId}/submit` | student | Enviar intento |
| POST | `/exams/attempts/{attemptId}/cheat` | student | Reportar evento anti-trampa |
| GET | `/exams/{id}/stats` | teacher,admin | Estadísticas |

### Trabajos y rankings
| Método | Ruta | Acceso | Descripción |
|--------|------|--------|-------------|
| GET/POST | `/submitted-works` | Todos | Listar / crear trabajo |
| GET | `/submitted-works/student/summary` | Todos | Resumen por estudiante |
| GET | `/submitted-works/{id}` | Todos | Detalle |
| POST | `/submitted-works/{id}/grade` `/return` | teacher,admin | Calificar / devolver |
| POST | `/submitted-works/auto-generate` | teacher,admin | Generar desde progreso completado |
| GET | `/rankings/course` `/overall` `/my-position` | Todos | Rankings |

### Reportes (`/reports`) — teacher,admin
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/reports/performance` `/filtered-performance` | Rendimiento general / filtrado |
| GET | `/reports/student-detail/{studentId}` `/course-detail/{unit}` | Detalle por estudiante / curso |
| GET | `/reports/grades` `/student/{userId}` `/participation` | Calificaciones / estudiante / participación |
| GET | `/reports/export/pdf` `/export/excel` | Exportar reporte |
| GET | `/reports/export/performance/pdf` `/export/performance/excel` | Exportar rendimiento |
| GET | `/reports/export/student/{id}/pdf` `/excel` | Exportar estudiante |
| GET | `/reports/export/grades/pdf` `/excel` | Exportar calificaciones |

### Padres (`/parent`) — parent
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/parent/children` | Hijos vinculados |
| GET | `/parent/children/{studentId}/progress` `/report` | Progreso / reporte del hijo |

### Admin (`/admin`) — admin
| Método | Ruta | Descripción |
|--------|------|-------------|
| GET/POST/PUT/DELETE | `/admin/users` `/users/{id}` | CRUD de usuarios |
| POST | `/admin/users/{id}/activate` `/deactivate` | Activar / desactivar |
| POST/GET | `/admin/users/import` `/export` | Importar / exportar CSV |
| GET/PUT | `/admin/config` | Config institucional |
| GET/POST/PUT/DELETE | `/admin/periods` `/periods/{id}` | Períodos académicos |
| POST/GET/GET | `/admin/backup` `/backup/last` `/backup/download/{filename}` | Backups |

### IA y otros
| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/ai/chat` | Chat con Profesor Euler (SSE, 20/min, 50/día) |
| POST | `/ai/generate-lesson` | Genera una lección completa con IA (Groq) |
| POST | `/guest/student-lookup` | Consulta de notas por DNI (público) |
| GET | `/guest/captcha` | Generar captcha (público) |
| GET | `/config` | Config pública (throttle 30/min) |
| GET | `/health` | Health check de la API |

---

## Roles del sistema

- **Estudiante**: lecciones, evaluaciones, exámenes (con anti-trampa), tablero de trabajos, ranking, insignias y nivel.
- **Docente**: crear/editar/duplicar/publicar lecciones, evaluaciones y exámenes; calificar y devolver trabajos; ver progreso de estudiantes y reportes; exportar PDF/Excel.
- **Administrador**: gestión de usuarios (CRUD + CSV), configuración institucional y colores, períodos académicos, backups de BD y panel de trabajos institucional.
- **Padre de familia**: monitoreo de progreso y reportes de sus hijos; consulta pública de notas por DNI.

---

## Seguridad y middleware

- **Rate limiting** en capas: global (60/min), por petición autenticada (60/min) y específico por endpoint (login/register/OAuth/exportaciones).
- **CORS** personalizado con orígenes configurables; **SecurityHeaders** (X-Frame-Options DENY, Referrer-Policy, Permissions-Policy, HSTS).
- **Auditoría** de todas las escrituras en `audit_logs` con IP, user-agent y plataforma.
- **RBAC** con el middleware `role` aplicado por grupo de rutas.
- **Anti-trampa** en exámenes: `time_taken` acotado, conteo de cambios de pestaña y `cheat_log`.
- **Protección de secretos**: la API key de Groq y las credenciales de FCM/Google nunca salen del backend (proxy).

---

## Auditoría QA / Seguridad (2026-08)

Auditoría realizada siguiendo el rol de **QA Engineer** (QA/security) sobre el backend. Hallazgos y correcciones aplicadas, en orden de severidad:

| Severidad | Hallazgo | Archivo | Corrección |
|-----------|----------|---------|------------|
| **Critical** | El captcha devolvía el código en texto plano en el JSON → bypass total + enumeración de notas por DNI (datos de menores expuestos) | `GuestStudentController.php` | Captcha ahora devuelve **imagen SVG** + **token firmado** (`Crypt`) con expiración (5 min) e intentos máximos; `lookup` valida el token. |
| **Critical** | Auto-registro permitía rol `teacher` → escalada de privilegios (acceso a lecciones, evaluaciones, notas) | `RegisterRequest.php` | Solo `student`/`parent` en auto-registro; docente/admin se crean vía panel/CLI. |
| High | **IDOR** en `reportCheating`: un estudiante marcaba como "trampa" el intento de otro | `ExamController.php` | Verificación de propiedad (`attempt->student_id === Auth::id()`), 403 en caso contrario. |
| High | Rol **parent** accedía a trabajos de todos los estudiantes (sin filtro por hijos) | `SubmittedWorkController.php` | `index`/`show`/`studentSummary` acotados a los hijos vinculados del parent. |
| High | Reportes de docente no acotados → veía datos de toda la institución | `ReportController.php` | `filteredPerformanceReport`, `studentDetailReport` y `courseDetailReport` ahora se acotan por `teacher_id` vía helpers `teacherScoped*`. |
| High | Respuestas correctas expuestas al rol **parent** | `ExamController.php`, `EvaluationController.php` | `correct_answer` oculto también para parent en `show`/`index`/`getQuestions`; `adaptive` restringido a estudiantes. |
| Medium | CSV-injection sanitizada también en **import** (corrompía datos legítimos y el dedupe por email) | `AdminController.php` | La sanitización anti-fórmulas solo se aplica en **export**; el import ingresa los valores limpios. |
| Medium | Password de BD en la línea de comandos de `mysqldump` (visible en `ps`) | `AdminController.php` | Password vía variable de entorno `MYSQL_PWD` en lugar de argumento. |
| Medium | `childReport` usaba umbral 60 sobre escala 0-20 → `passed_evaluations`/`pass_rate` siempre 0 | `ParentController.php` | Umbral corregido a **12**. |
| Medium | Rutas sensibles sin throttle | `routes/api.php` | Throttle en `student-lookup` (5/min), captcha (10/min), `submitted-works` (20/min), `auto-generate` (10/min), `users/import` (5/min) y `backup` (3/60 min). |
| Medium | Rol `parent` inexistente en instalaciones frescas (migración de enum solo-MySQL) | `2014_10_12_000000_create_roles_table.php` | `parent` añadido al enum base (funciona en todos los drivers, incl. tests SQLite). |

### Tests de regresión

Nuevo `tests/Feature/SecurityFixesTest.php` (5 tests) que cubren: captcha sin código en claro, auto-registro sin rol teacher, IDOR en `reportCheating` (403), respuestas ocultas al parent, y rechazo de roles no permitidos.

**Estado:** 71 tests ✓ (66 previos + 5 nuevos) · 442 aserciones. Ver también la auditoría del **frontend** en `README` de `frontend-app-mat`.

---

## Funcionalidades destacadas

- **Autenticación** tradicional + Google OAuth (web con auth_code en caché y móvil con ID token), tokens Sanctum con expiración y por plataforma.
- **Chat IA "Profesor Euler"** con streaming SSE, límites diarios y prompt restrictivo a matemáticas.
- **Evaluaciones y exámenes** con autocorrección matemática (normalización de respuestas), intentos máximos y publicación/notificación.
- **Insignias, rachas y niveles** calculados en el backend según progreso y rendimiento.
- **Reportes ricos** (rendimiento, calificaciones, estudiante, curso, participación) con exportación **PDF y Excel** trilingüe.
- **Notificaciones** in-app + push (FCM) con registro de dispositivos.
- **Trilingüe** (español / inglés / quechua) en respuestas y exportaciones.
- **Backups** de base de datos (`mysqldump`) desde el panel admin con descarga segura.
