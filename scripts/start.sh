#!/usr/bin/env bash
set -euo pipefail
export COMPILED_ASSETS=true
cd "$(dirname "$0")/.."
started=$SECONDS
for command in php composer node npm; do
  if ! command -v "$command" >/dev/null 2>&1; then
    printf 'Falta %s. Revisa los prerrequisitos del README e instálalo antes de continuar.\n' "$command" >&2
    exit 1
  fi
done
php -r 'if (PHP_VERSION_ID < 80300) { fwrite(STDERR, "Se necesita PHP 8.3 o superior.\n"); exit(1); } foreach (["pdo_sqlite", "mbstring", "openssl", "dom", "xml", "ctype", "fileinfo", "curl", "tokenizer", "session"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Falta la extensión PHP: $extension\n"); exit(1); } }'
node -e 'const [major, minor] = process.versions.node.split(".").map(Number); if (major < 22 || (major === 22 && minor < 12)) { console.error("Se necesita Node 22.12 o superior."); process.exit(1); }'
mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
composer_hash=$(php -r 'echo hash_file("sha256", "composer.lock");')
if [[ ! -f vendor/autoload.php || ! -f storage/framework/composer.hash || "$(cat storage/framework/composer.hash)" != "$composer_hash" ]]; then
  composer install --prefer-dist --no-interaction --no-ansi
  printf '%s' "$composer_hash" > storage/framework/composer.hash
fi
npm_hash=$(php -r 'echo hash_file("sha256", "package-lock.json");')
if [[ ! -d node_modules || ! -f storage/framework/npm.hash || "$(cat storage/framework/npm.hash)" != "$npm_hash" ]]; then
  npm ci --ignore-scripts --no-audit --no-fund
  printf '%s' "$npm_hash" > storage/framework/npm.hash
fi
php scripts/prepare-environment.php
php artisan config:clear --no-ansi
php artisan app:prepare-demo --no-interaction --no-ansi
source_hash=$(php scripts/source-hash.php)
if [[ ! -f public/build/manifest.json || ! -f storage/framework/assets.hash || "$(cat storage/framework/assets.hash)" != "$source_hash" ]]; then
  npm run build
  npm run typecheck
  printf '%s' "$source_hash" > storage/framework/assets.hash
fi
printf '\nPreparación terminada en %s segundos.\n' "$((SECONDS-started))"
printf 'Usuarios locales iniciales: lector@cuantico.test / cuantico-demo y operador@cuantico.test / cuantico-operator\n'
printf 'Si configuraste contraseñas propias, se conservaron.\n'
if [[ "${PREPARE_ONLY:-0}" == '1' ]]; then exit 0; fi
port=${PORT:-8000}
host=${HOST:-0.0.0.0}
if ! php -r '$s = @stream_socket_server("tcp://".$argv[1].":".$argv[2], $e, $m); if (!$s) { exit(1); } fclose($s);' "$host" "$port"; then
  printf 'El puerto %s no está disponible. Ejecuta PORT=8001 ./scripts/start.sh para usar otro.\n' "$port" >&2
  exit 1
fi
printf 'Abrir: http://localhost:%s o http://127.0.0.1:%s\n' "$port" "$port"
exec php artisan serve --host="$host" --port="$port" --no-reload
