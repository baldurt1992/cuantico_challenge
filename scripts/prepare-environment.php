<?php

require __DIR__.'/../vendor/autoload.php';

use Dotenv\Dotenv;

$root = dirname(__DIR__);
$filename = $root.'/.env';
if (! is_file($filename)) {
    copy($root.'/.env.example', $filename);
}
chmod($filename, 0600);
$content = file_get_contents($filename);
try {
    $values = Dotenv::parse($content);
} catch (Throwable) {
    fwrite(STDERR, "El archivo .env no es válido. Revisa su sintaxis sin publicar sus valores.\n");
    exit(1);
}
$value = static fn (string $key, string $fallback = ''): string => getenv($key) !== false ? getenv($key) : (string) ($values[$key] ?? $fallback);
if ($value('APP_ENV', 'local') !== 'local' || $value('DB_CONNECTION', 'sqlite') !== 'sqlite') {
    fwrite(STDERR, "El script de arranque requiere APP_ENV=local y DB_CONNECTION=sqlite. No modificó la base configurada.\n");
    exit(1);
}
$additions = [];
foreach (Dotenv::parse(file_get_contents($root.'/.env.example')) as $key => $default) {
    if (! array_key_exists($key, $values)) {
        $additions[$key] = $default;
    }
}
if ($value('APP_KEY') === '') {
    $additions['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
}
if ($value('DB_DATABASE') === '') {
    $additions['DB_DATABASE'] = $root.'/database/database.sqlite';
}
if ($value('GOOGLE_API_KEY') === '' && getenv('NON_INTERACTIVE') !== '1' && defined('STDIN') && stream_isatty(STDIN)) {
    fwrite(STDOUT, 'API key de Gemini (Enter para iniciar sin chat): ');
    $terminal = trim((string) shell_exec('stty -g'));
    try {
        shell_exec('stty -echo');
        $key = trim(fgets(STDIN) ?: '');
    } finally {
        if ($terminal !== '') {
            shell_exec('stty '.escapeshellarg($terminal));
        }
        fwrite(STDOUT, "\n");
    }
    if ($key !== '') {
        $additions['GOOGLE_API_KEY'] = $key;
    }
}
foreach ($additions as $key => $newValue) {
    $encoded = '"'.str_replace(['\\', '"', '$', "\r", "\n"], ['\\\\', '\\"', '\\$', '', ''], (string) $newValue).'"';
    $line = $key.'='.$encoded;
    if (preg_match('/^'.preg_quote($key, '/').'=/m', $content)) {
        $content = preg_replace_callback('/^'.preg_quote($key, '/').'=.*$/m', fn (): string => $line, $content);
    } else {
        $content = rtrim($content)."\n".$line."\n";
    }
}
file_put_contents($filename, $content);
$database = $value('DB_DATABASE') ?: ($additions['DB_DATABASE'] ?? $root.'/database/database.sqlite');
if ($database !== ':memory:' && ! is_file($database)) {
    if (! is_dir(dirname($database))) {
        mkdir(dirname($database), 0700, true);
    }
    touch($database);
    chmod($database, 0600);
}
fwrite(STDOUT, "Configuración local preparada; claves existentes conservadas.\n");
