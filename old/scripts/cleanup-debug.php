<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = realpath(__DIR__ . '/..');
$exclusions = [
    $root . '/plugins',
    $root . '/vendor',
    $root . '/node_modules',
    $root . '/.git',
    $root . '/scripts',
    $root . '/api/v1/config.php',
    $root . '/api/config.php',
];

$patterns = [
    '/^\s*ini_set\s*\(\s*[\'"]display_errors[\'"]\s*,\s*[\'"]?[01][\'"]?\s*\)\s*;\s*\n/m',
    '/^\s*ini_set\s*\(\s*[\'"]display_startup_errors[\'"]\s*,\s*[\'"]?[01][\'"]?\s*\)\s*;\s*\n/m',
    '/^\s*ini_set\s*\(\s*[\'"]log_errors[\'"]\s*,\s*[\'"]?[01][\'"]?\s*\)\s*;\s*\n/m',
    '/^\s*error_reporting\s*\([^)]+\)\s*;\s*\n/m',
];

$processed = 0;
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    foreach ($exclusions as $ex) {
        if (strpos($path, $ex) === 0) continue 2;
    }
    $orig = file_get_contents($path);
    $new = $orig;
    foreach ($patterns as $pat) {
        $new = preg_replace($pat, '', $new);
    }
    $new = preg_replace('/^\s*if\s*\(\s*defined\s*\(\s*[\'"]ENVIRONMENT[\'"]\s*\)\s*&&\s*ENVIRONMENT\s*===\s*[\'"]production[\'"]\s*\)\s*\{\s*\}\s*else\s*\{\s*\}\s*\n/m', '', $new);
    if ($new !== $orig) {
        file_put_contents($path, $new);
        $processed++;
    }
}
echo "Cleaned debug calls in $processed file(s).\n";
