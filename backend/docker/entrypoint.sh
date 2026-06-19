#!/bin/sh
set -e

cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Add it in Coolify environment variables."
  echo "Generate one with: php artisan key:generate --show"
  exit 1
fi

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
  exit 1
}

php artisan config:clear

wait_for_database

php artisan storage:link --force 2>/dev/null || true
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
