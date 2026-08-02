# MathFlow — Deployment Guide

## Requisitos del servidor

- PHP 8.2+ (con extensiones: mbstring, xml, ctype, json, bcmath, pdo, tokenizer)
- MySQL 8.0+ o MariaDB 10.6+
- Composer 2.x
- Nginx o Apache (document root apuntando a `public/`)
- Supervisor (para queue workers)
- Cron (para scheduler)

## Pasos de deploy

```bash
# 1. Clonar el repositorio
git clone <repo-url> /var/www/mathflow
cd /var/www/mathflow

# 2. Instalar dependencias (sin dev)
composer install --no-dev --optimize-autoloader

# 3. Configurar entorno
cp .env.production .env
# Editar .env con valores reales:
#   APP_KEY (generar abajo)
#   APP_URL, SESSION_DOMAIN, CORS_ALLOWED_ORIGINS
#   GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET
#   GROQ_API_KEY
#   MAIL_USERNAME, MAIL_PASSWORD
#   FCM_SERVER_KEY, FCM_PROJECT_ID

# 4. Generar APP_KEY
php artisan key:generate --force

# 5. Ejecutar migraciones
php artisan migrate --force

# 6. Seedear datos iniciales (solo primera vez)
php artisan db:seed --class=RolesSeeder
php artisan db:seed --class=AdminUserSeeder

# 7. Crear admin desde CLI (opcional)
php artisan mathflow:create-admin --email=admin@mathflow.com --name="Admin" --password=your-secure-password

# 8. Optimizar para producción
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Crear link de storage
php artisan storage:link

# 10. Permisos
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

## Configurar Supervisor (queue worker)

```bash
# Copiar configuración
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/mathflow-worker.conf

# Recargar Supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start "mathflow-worker:*"
```

## Configurar Cron (scheduler)

```bash
# Abrir crontab del usuario www-data
sudo crontab -u www-data -e

# Agregar esta línea:
* * * * * cd /var/www/mathflow && php artisan schedule:run >> /dev/null 2>&1
```

## Configurar Nginx

```nginx
server {
    listen 80;
    server_name mathflow.example.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name mathflow.example.com;
    root /var/www/mathflow/public;

    ssl_certificate /etc/letsencrypt/live/mathflow.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/mathflow.example.com/privkey.pem;

    index index.php;

    charset utf-8;
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

## Comandos útiles

```bash
# Verificar configuración
php artisan about

# Limpiar cache en desarrollo
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Ver logs
tail -f storage/logs/laravel.log

# Reiniciar queue worker
sudo supervisorctl restart "mathflow-worker:*"

# Verificar health check
curl https://mathflow.example.com/api/v1/health
```

## Variables de entorno obligatorias

| Variable | Descripción |
|----------|-------------|
| `APP_KEY` | Generar con `php artisan key:generate --force` |
| `APP_URL` | URL completa del backend (ej: `https://api.mathflow.com`) |
| `GOOGLE_CLIENT_ID` | De Google Cloud Console |
| `GOOGLE_CLIENT_SECRET` | De Google Cloud Console |
| `GROQ_API_KEY` | De Groq para AI chat |
| `MAIL_USERNAME` | SMTP username |
| `MAIL_PASSWORD` | SMTP password |
| `FCM_SERVER_KEY` | De Firebase Console (opcional, para push) |
| `FCM_PROJECT_ID` | De Firebase Console (opcional, para push) |
