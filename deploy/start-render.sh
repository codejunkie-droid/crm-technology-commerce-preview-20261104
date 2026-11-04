#!/bin/sh
set -eu

port="${PORT:-10000}"
sed -i "s/^Listen 80$/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:80>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf
export APP_URL="${RENDER_EXTERNAL_URL:-${APP_URL:-http://localhost:${port}}}"

php bin/migrate.php --seed-demo
chown -R www-data:www-data storage
exec apache2-foreground
