#!/bin/sh
# Starts ProcureMS: prepares the data volume, migrates the database, runs the daily scheduler and the web server.
set -e
cd /app

DB="${DB_DATABASE:-/data/database.sqlite}"
if ! mountpoint -q /data 2>/dev/null; then
    echo "WARNING: /data is not a mounted volume. The database and uploaded files will be lost on every deploy." >&2
fi

mkdir -p /data/public "$(dirname "$DB")"
[ -f "$DB" ] || touch "$DB"

# Uploaded logos and files live on the volume too.
rm -rf storage/app/public
ln -s /data/public storage/app/public
php artisan storage:link --force

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Daily jobs (unanswered transfer requests, handover end) run inside the same container.
php artisan schedule:work &

exec frankenphp run --config /etc/caddy/Caddyfile --adapter caddyfile
