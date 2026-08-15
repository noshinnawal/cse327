<?php

/**
 * Cross-platform PHP syntax linter (works on Windows cmd and Unix shells).
 * Recursively lints every .php file, excluding vendor/var/.git.
 */

$failed = false;
$count = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . '/..', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    foreach (['/vendor/', '/var/', '/.git/'] as $excluded) {
        if (strpos($path, $excluded) !== false) {
            continue 2;
        }
    }
    $count++;
    exec(PHP_BINARY . ' -l ' . escapeshellarg($path) . ' 2>&1', $output, $code);
    if ($code !== 0) {
        $failed = true;
        echo implode("\n", $output) . "\n";
    }
    $output = [];
}

if ($failed) {
    echo "Lint FAILED ($count files checked)\n";
    exit(1);
}
echo "All $count PHP files lint clean\n";
