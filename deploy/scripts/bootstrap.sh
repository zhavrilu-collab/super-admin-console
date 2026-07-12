#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
ADMIN_PATH="${ADMIN_APP_PATH:-$ROOT/..}"
SAAS_PATH="${SAAS_APP_PATH:-$ROOT/../../udruga-siletici/udruga-saas}"

echo "==> Admin konzola: $ADMIN_PATH"
echo "==> Udruga SaaS:   $SAAS_PATH"

if [ ! -f "$ADMIN_PATH/artisan" ]; then
  echo "Admin konzola nije pronađena na: $ADMIN_PATH" >&2
  exit 1
fi

if [ ! -f "$SAAS_PATH/artisan" ]; then
  echo "Udruga SaaS nije pronađen na: $SAAS_PATH" >&2
  exit 1
fi

cd "$ROOT"

if [ ! -f .env.docker ]; then
  cp .env.docker.example .env.docker
  echo "Kreiran deploy/.env.docker — prilagodi lozinke prije produkcije."
fi

set -a
# shellcheck disable=SC1091
source .env.docker
set +a

export ADMIN_APP_PATH="$ADMIN_PATH"
export SAAS_APP_PATH="$SAAS_PATH"

echo "==> docker compose build"
docker compose --env-file .env.docker build

echo "==> docker compose up -d mysql"
docker compose --env-file .env.docker up -d mysql

echo "==> Čekam MySQL..."
sleep 15

bootstrap_app() {
  local service=$1
  local path=$2
  local extra=${3:-}

  docker compose --env-file .env.docker run --rm --no-deps \
    -v "$path:/var/www/html" \
    -w /var/www/html \
    $extra \
    "$service" sh -lc "
      composer install --no-dev --optimize-autoloader --no-interaction
      if [ ! -f .env ]; then cp .env.example .env; fi
      php artisan key:generate --force
      touch database/database.sqlite 2>/dev/null || true
      php artisan migrate --force
    "
}

echo "==> Bootstrap admin konzola"
bootstrap_app admin-php "$ADMIN_PATH" \
  "-e APP_ENV=production -e DB_CONNECTION=sqlite -e DB_DATABASE=/var/www/html/database/database.sqlite"

docker compose --env-file .env.docker run --rm --no-deps \
  -v "$ADMIN_PATH:/var/www/html" -w /var/www/html \
  -e APP_ENV=production \
  admin-php php artisan db:seed --class=AdminConsoleSeeder --force || true

echo "==> Bootstrap udruga-saas"
docker compose --env-file .env.docker run --rm --no-deps \
  -v "$SAAS_PATH:/var/www/html" -w /var/www/html \
  -e APP_ENV=production \
  -e DB_CONNECTION=mysql \
  -e DB_HOST=mysql \
  -e DB_DATABASE="${MYSQL_DATABASE:-udruga_saas}" \
  -e DB_USERNAME="${MYSQL_USER:-udruga}" \
  -e DB_PASSWORD="${MYSQL_PASSWORD:-change-me}" \
  --network udruga-platform_default \
  saas-php sh -lc "
    composer install --no-dev --optimize-autoloader --no-interaction
    if [ ! -f .env ]; then cp .env.example .env; fi
    php artisan key:generate --force
    php artisan migrate --force
  "

echo "==> docker compose up -d"
docker compose --env-file .env.docker up -d

cat <<EOF

Gotovo.

  Admin konzola: http://localhost:${ADMIN_HTTP_PORT:-8001}
  Udruga SaaS:   http://localhost:${SAAS_HTTP_PORT:-8000}

Super-admin (seeder): admin@example.com / password

EOF
