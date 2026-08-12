#!/usr/bin/env bash
# Preparacion del proyecto en un servidor Ubuntu nuevo.
# El codigo ya es multiplataforma; esto solo hace la configuracion propia de cada maquina.
set -euo pipefail

if [ ! -f artisan ]; then
    echo "Ejecuta este script desde la raiz del proyecto (donde esta 'artisan')." >&2
    exit 1
fi

echo "== Dependencias de PHP =="
composer install --no-dev --optimize-autoloader

echo "== Dependencias de Node y build de assets =="
npm install
npm run build

if [ ! -f .env ]; then
    echo "== Creando .env desde .env.example =="
    cp .env.example .env
    php artisan key:generate
    echo "Recuerda editar .env: DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD, APP_URL."
fi

echo "== Enlace de almacenamiento publico =="
# El symlink de Windows no sirve aqui; hay que recrearlo para esta maquina.
rm -f public/storage
php artisan storage:link

echo "== Migraciones =="
php artisan migrate --force

echo "== Permisos de escritura (necesarios en Linux, no en Windows) =="
sudo chown -R "$USER":www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

echo "== Cache de configuracion/rutas/vistas para produccion =="
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Listo. Configura tu servidor web (nginx/Apache + PHP-FPM) apuntando a 'public/'."
