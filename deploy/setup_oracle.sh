#!/usr/bin/env bash
# =============================================================
# KawsayMath - Script de instalacion para Oracle Cloud Free Tier
# Sistemas soportados: Oracle Linux 8/9, Ubuntu 22.04/24.04
#
# Uso: sudo bash setup_oracle.sh
# Antes de ejecutar, edita las variables de configuracion abajo.
# =============================================================
set -euo pipefail

# ---------- CONFIGURACION (editar) ----------
APP_DOMAIN="mathflow.example.com"        # Tu dominio o IP publica de Oracle
APP_REPO_URL="https://github.com/JoseAdolfo19/backend-app-mat.git"
APP_DIR="/var/www/mathflow"
DB_NAME="mathlearn_db"
DB_USER="mathflow"
DB_PASSWORD="CHANGE_ME_STRONG_PASSWORD"   # CAMBIAR
MYSQL_ROOT_PASSWORD="CHANGE_ME_ROOT_PASSWORD" # CAMBIAR
# -------------------------------------------

echo "==> Detectando sistema operativo..."
if [ -f /etc/redhat-release ]; then
    OS="rhel"
elif [ -f /etc/lsb-release ] || [ -f /etc/debian_version ]; then
    OS="ubuntu"
else
    echo "Sistema operativo no soportado por este script."; exit 1
fi
echo "OS detectado: $OS"

echo "==> Actualizando sistema..."
if [ "$OS" = "rhel" ]; then
    sudo dnf update -y
    sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-$(rpm -E %rhel).rpm || true
    sudo dnf module reset php -y || true
    sudo dnf module enable php:remi-8.3 -y || true
    PHPFPM_SOCK="unix:/var/run/php-fpm/www.sock"
else
    sudo apt-get update -y
    sudo apt-get install -y software-properties-common
    sudo add-apt-repository -y ppa:ondrej/php || true
    sudo apt-get update -y
    PHPFPM_SOCK="unix:/var/run/php/php8.3-fpm.sock"
fi

echo "==> Instalando PHP 8.3, Nginx, MySQL, Composer..."
PHP_PKGS="php8.3 php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-gd php8.3-bcmath php8.3-intl php8.3-mysql php8.3-tokenizer php8.3-ctype php8.3-fileinfo php8.3-dom php8.3-simplexml"
if [ "$OS" = "rhel" ]; then
    PHP_PKGS="php php-fpm php-cli php-mbstring php-xml php-curl php-zip php-gd php-bcmath php-intl php-mysqlnd php-tokenizer php-ctype php-fileinfo php-dom php-simplexml"
    sudo dnf install -y nginx mysql-server composer $PHP_PKGS
else
    sudo apt-get install -y nginx mysql-server composer $PHP_PKGS
fi

echo "==> Configurando MySQL..."
sudo systemctl enable --now mysql || sudo systemctl enable --now mysqld
sudo mysql <<SQL
ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}';
CREATE DATABASE IF NOT EXISTS ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "==> Clonando repositorio..."
if [ ! -d "$APP_DIR" ]; then
    sudo git clone "$APP_REPO_URL" "$APP_DIR"
else
    echo "Directorio $APP_DIR ya existe, actualizando..."
    sudo git -C "$APP_DIR" pull
fi
cd "$APP_DIR"

echo "==> Instalando dependencias de Composer..."
sudo composer install --no-dev --optimize-autoloader

echo "==> Configurando .env..."
if [ ! -f .env ]; then
    sudo cp .env.production .env
fi
sudo sed -i "s|^APP_URL=.*|APP_URL=http://${APP_DOMAIN}|" .env
sudo sed -i "s|^APP_ENV=.*|APP_ENV=production|" .env
sudo sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" .env
sudo sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" .env
sudo sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" .env
sudo sed -i "s|^DB_HOST=.*|DB_HOST=127.0.0.1|" .env
sudo sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=database|" .env

echo "==> Generando APP_KEY y migrando..."
sudo -u www-data php artisan key:generate --force
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --class=RolesSeeder --force
sudo -u www-data php artisan db:seed --class=AdminUserSeeder --force

echo "==> Optimizando..."
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan storage:link

echo "==> Permisos..."
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

echo "==> Configurando Nginx..."
sudo cp deploy/nginx.conf /etc/nginx/sites-available/mathflow
sudo sed -i "s/mathflow.example.com/${APP_DOMAIN}/g" /etc/nginx/sites-available/mathflow
if [ "$OS" = "rhel" ]; then
    sudo sed -i "s|php8.3-fpm.sock|php-fpm/www.sock|" /etc/nginx/sites-available/mathflow
    sudo ln -sf /etc/nginx/sites-available/mathflow /etc/nginx/conf.d/mathflow.conf
    sudo rm -f /etc/nginx/conf.d/default.conf 2>/dev/null || true
else
    sudo ln -sf /etc/nginx/sites-available/mathflow /etc/nginx/sites-enabled/
    sudo rm -f /etc/nginx/sites-enabled/default 2>/dev/null || true
fi
sudo nginx -t
sudo systemctl enable --now nginx
sudo systemctl restart php8.3-fpm || sudo systemctl restart php-fpm
sudo systemctl reload nginx

echo "==> Configurando Supervisor (queue worker)..."
sudo apt-get install -y supervisor 2>/dev/null || sudo dnf install -y supervisor 2>/dev/null || true
sudo cp deploy/supervisor.conf /etc/supervisor/conf.d/mathflow-worker.conf 2>/dev/null || \
    sudo cp deploy/supervisor.conf /etc/supervisord.d/mathflow-worker.ini 2>/dev/null || true
sudo supervisorctl reread 2>/dev/null || true
sudo supervisorctl update 2>/dev/null || true

echo "==> Configurando Cron (scheduler)..."
(crontab -l 2>/dev/null; echo "* * * * * cd ${APP_DIR} && php artisan schedule:run >> /dev/null 2>&1") | crontab -

echo ""
echo "=============================================================="
echo "INSTALACION COMPLETADA"
echo "=============================================================="
echo "Backend:      http://${APP_DOMAIN}/api/v1/health"
echo "Base de datos: ${DB_NAME} (usuario: ${DB_USER})"
echo ""
echo "PENDIENTES (manual):"
echo "  1) Abrir puertos 80/443 en Oracle Cloud:"
echo "     Networking -> Virtual Cloud Networks -> Security List ->"
echo "     Add Ingress Rule: 80 y 443 (0.0.0.0/0)"
echo "  2) Si usas dominio: configurar DNS y TLS con:"
echo "     sudo certbot --nginx -d ${APP_DOMAIN}"
echo "  3) Editar .env con valores reales: GOOGLE_CLIENT_ID/SECRET,"
echo "     GROQ_API_KEY, CORS_ALLOWED_ORIGINS (dominio del frontend)"
echo "     y luego: sudo -u www-data php artisan config:cache"
echo "=============================================================="
