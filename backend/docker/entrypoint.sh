#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Add it in Coolify environment variables."
  echo "Generate one with: php artisan key:generate --show"
  exit 1
fi

if [ "$APP_ENV" = "production" ] && [ "${DB_CONNECTION:-mysql}" = "sqlite" ]; then
  echo "ERROR: DB_CONNECTION=sqlite is not valid in production."
  echo "Set DB_CONNECTION=mysql and MySQL credentials in Coolify environment variables."
  exit 1
fi

prepare_storage() {
  echo "Preparing storage directories..."
  mkdir -p \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
  chown -R www-data:www-data storage bootstrap/cache
  chmod -R ug+rwx storage bootstrap/cache
}

wait_for_database() {
  if [ "$DB_CONNECTION" != "mysql" ]; then
    return 0
  fi

  echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
  attempts=0
  max_attempts=60

  while [ "$attempts" -lt "$max_attempts" ]; do
    if php artisan db:show >/dev/null 2>&1; then
      echo "Database is ready."
      return 0
    fi

    attempts=$((attempts + 1))
    sleep 2
  done

  echo "ERROR: Database not reachable after ${max_attempts} attempts."
  echo "Check DB_HOST, DB_DATABASE, DB_USERNAME, and DB_PASSWORD match the mysql service."
  exit 1
}

prepare_storage

echo "Clearing cached bootstrap files..."
php artisan config:clear
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

wait_for_database

php artisan storage:link --force 2>/dev/null || true

echo "Running database migrations..."
if ! php artisan migrate --force; then
  echo "ERROR: Database migration failed."
  exit 1
fi

echo "Optimizing for production..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Backend setup complete."
touch /tmp/app-ready
exec "$@"
