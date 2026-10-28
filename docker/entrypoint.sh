#!/bin/sh
set -e

cd /var/www/html

# Ejecuta un comando como www-data (mismo UID que el usuario del host)
as_app() {
    su -s /bin/sh www-data -c "$*"
}

if [ ! -f .env ]; then
    echo "[entrypoint] Creando .env desde .env.example"
    as_app "cp .env.example .env"
fi

if [ ! -f vendor/autoload.php ]; then
    echo "[entrypoint] Instalando dependencias de Composer"
    as_app "composer install --no-interaction --prefer-dist"
fi

if ! grep -q '^APP_KEY=base64:' .env; then
    echo "[entrypoint] Generando APP_KEY"
    as_app "php artisan key:generate --force"
fi

if [ ! -d node_modules ]; then
    echo "[entrypoint] Instalando dependencias de npm"
    as_app "npm install"
fi

if [ ! -f public/build/manifest.json ]; then
    echo "[entrypoint] Compilando assets"
    as_app "npm run build"
fi

echo "[entrypoint] Ejecutando migraciones"
as_app "php artisan migrate --force"

if [ ! -L public/storage ]; then
    as_app "php artisan storage:link"
fi

exec "$@"
