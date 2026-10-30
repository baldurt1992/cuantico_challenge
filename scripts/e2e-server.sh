#!/usr/bin/env bash
set -euo pipefail
export COMPILED_ASSETS=true
cd "$(dirname "$0")/.."
export APP_ENV=testing
export APP_DEBUG=false
export DB_CONNECTION=sqlite
export DB_DATABASE="$PWD/storage/framework/e2e.sqlite"
export CACHE_STORE=array
export SESSION_DRIVER=file
export GOOGLE_API_KEY=e2e-not-a-real-key
if [[ -f "$DB_DATABASE" ]]; then rm "$DB_DATABASE"; fi
touch "$DB_DATABASE"
php artisan app:prepare-demo --no-interaction --no-ansi
exec php artisan serve --host=127.0.0.1 --port=8765 --no-reload
