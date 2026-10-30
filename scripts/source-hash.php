<?php

$root = dirname(__DIR__);
$context = hash_init('sha256');
$paths = [$root.'/package-lock.json', $root.'/vite.config.ts', $root.'/tsconfig.json'];
foreach (['resources/js', 'resources/css', 'resources/views'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $paths[] = $file->getPathname();
        }
    }
}
sort($paths);
foreach ($paths as $file) {
    hash_update($context, substr($file, strlen($root)));
    hash_update_file($context, $file);
}
echo hash_final($context);
