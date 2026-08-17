# KawsayMath — Quick Start / Guía Rápida

> Guía de instalación y configuración del backend de KawsayMath.

---

## Requisitos / Requirements

| Componente | Versión mínima |
|------------|----------------|
| PHP | 8.2+ |
| MySQL / MariaDB | 8.0+ / 10.6+ |
| Composer | 2.x |
| Node.js (frontend) | 18+ |
| npm | 9+ |

### Extensiones PHP requeridas

`mbstring`, `xml`, `ctype`, `json`, `bcmath`, `pdo`, `tokenizer`, `openssl`, `curl`

---

## 1. Instalación del Backend

```bash
# Clonar el repositorio
git clone <repository-url>
cd backend-app-mat

# Instalar dependencias PHP
composer install

# Configurar variables de entorno
cp .env.example .env
php artisan key:generate

# Crear la base de datos y ejecutar migraciones con seeds
php artisan migrate --seed

# Iniciar el servidor de desarrollo
php artisan serve
```

El backend estará disponible en `http://localhost:8000`.

### Datos del usuario admin (post-seed)

| Campo | Valor |
|-------|-------|
| Email | Admin se crea con el comando `php artisan create-admin` o desde el seed |
| Rol | admin |

---

## 2. Instalación del Frontend

```bash
cd frontend  # Directorio del frontend (si está separado)

# Instalar dependencias
npm install

# Configurar variables de entorno
cp .env.example .env

# Iniciar en modo desarrollo
npm run dev
```

---

## 3. Variables de Entorno / Environment Variables

Edita el archivo `.env` con la siguiente configuración:

```env
# App
APP_NAME=KawsayMath
APP_ENV=local
APP_KEY=
APP_URL=http://localhost:8000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mathflow_db
DB_USERNAME=root
DB_PASSWORD=

# Authentication
SANCTUM_EXPIRATION=1440

# AI (Groq)
GROQ_API_KEY=tu-api-key-de-groq
GROQ_MODEL=llama-3.3-70b-versatile
GROQ_URL=https://api.groq.com/openai/v1/chat/completions

# Google OAuth (opcional)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

# CORS
CORS_ALLOWED_ORIGINS=http://localhost:5173

# Firebase Push Notifications (opcional)
FCM_SERVER_KEY=
```

### Tabla de variables principales

| Variable | Descripción | Default |
|----------|-------------|---------|
| `APP_KEY` | Clave de encriptación Laravel | Se genera con `key:generate` |
| `APP_URL` | URL base del backend | `http://localhost:8000` |
| `DB_DATABASE` | Nombre de la base de datos | `mathflow_db` |
| `SANCTUM_EXPIRATION` | Tiempo de vida del token (minutos) | `1440` (24h) |
| `GROQ_API_KEY` | API key para el chat AI (Profesor Euler) | — |
| `CORS_ALLOWED_ORIGINS` | Orígenes permitidos para CORS | — |

---

## 4. Primer Uso / First Use

### 4.1 Crear usuario administrador

```bash
php artisan create-admin
```

Sigue las indicaciones para configurar nombre, email y contraseña del admin.

### 4.2 Iniciar sesión como admin

1. Abre `http://localhost:8000` (o la URL del frontend).
2. Ingresa las credenciales del administrador.
3. Accede al **Dashboard de Administrador**.

### 4.3 Configurar la plataforma

1. **Crear docentes**: Administration > Usuarios > Crear Usuario (rol: docente).
2. **Crear estudiantes**: Administration > Usuarios > Crear Usuario (rol: estudiante), o importar desde CSV.
3. **Configurar período académico**: Administration > Períodos > Crear Período.
4. **Personalizar**: Administration > Configuración > Ajustar nombre, colores y logo.

---

## 5. Desarrollo Local / Local Development

### Base de datos

```bash
# Crear la base de datos
mysql -u root -e "CREATE DATABASE mathflow_db"

# Ejecutar migraciones
php artisan migrate

# Poblar con datos de prueba
php artisan db:seed
```

### Servidor de desarrollo

```bash
# Backend
php artisan serve

# Con ngrok (para probar webhooks o mobile)
ngrok http 8000
```

### Tests

```bash
# Ejecutar todos los tests
php artisan test

# Ejecutar tests específicos
php artisan test --filter=AuthTest
```

### Comandos útiles

```bash
php artisan cache:clear       # Limpiar caché
php artisan config:clear      # Limpiar configuración cacheada
php artisan migrate:fresh     # Recrear base de datos (CUIDADO: borra datos)
php artisan migrate:fresh --seed  # Recrear y poblar con datos de prueba
```

---

## Estructura del Proyecto

```
backend-app-mat/
├── app/
│   ├── Console/Commands/      # Comandos Artisan
│   ├── Exports/               # Exportaciones Excel
│   ├── Http/Controllers/Api/  # 11 controladores API
│   ├── Http/Middleware/        # Cors, AuditLog, SecurityHeaders, etc.
│   ├── Models/                # 14 modelos Eloquent (UUIDs)
│   └── Services/              # Servicios externos
├── config/                    # Configuración de Laravel
├── database/migrations/       # 25 migraciones
├── lang/                      # es.php, en.php, qu.php (trilingüe)
├── routes/api.php             # Rutas de la API
├── storage/                   # Archivos, logs, backups
└── tests/Feature/             # 7 archivos de test (~54 tests)
```

---

## Solución de Problemas / Troubleshooting

| Problema | Solución |
|----------|----------|
| `Class not found` | Ejecuta `composer dump-autoload` |
| `CSRF token mismatch` | Asegúrate de enviar el header `Authorization` en requests API |
| `403 Forbidden` | Verifica que el token no haya expirado |
| `SQLSTATE connection refused` | Verifica que MySQL esté corriendo y las credenciales en `.env` sean correctas |
| Chat AI no responde | Verifica que `GROQ_API_KEY` esté configurada en `.env` |
