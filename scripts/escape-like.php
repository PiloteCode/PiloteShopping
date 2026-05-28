<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = realpath(__DIR__ . '/..');
$targets = [
    'admin/users/index.php',
    'admin/users/connect.php',
    'admin/orders/index.php',
    'admin/orders/export.php',
    'admin/products/index.php',
    'orders/index.php',
    'products/product_list.php',
];

$replaced = 0;
foreach ($targets as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) continue;
    $src = file_get_contents($path);
    $orig = $src;

    $src = preg_replace_callback(
        '/(\$\w+)\s*=\s*"%\$(\w+)%"\s*;/',
        function($m) {
            return $m[1] . ' = "%" . escapeLike($' . $m[2] . ') . "%";';
        },
        $src
    );
    $src = preg_replace_callback(
        '/(\$\w+)\s*=\s*\'%\'\s*\.\s*\$(\w+)\s*\.\s*\'%\'\s*;/',
        function($m) {
            return $m[1] . " = '%' . escapeLike(\$" . $m[2] . ") . '%';";
        },
        $src
    );
    $src = preg_replace_callback(
        '/\$params\[\]\s*=\s*"%\$(\w+)%"\s*;/',
        function($m) {
            return '$params[] = "%" . escapeLike($' . $m[1] . ') . "%";';
        },
        $src
    );

    if ($src !== $orig) {
        file_put_contents($path, $src);
        $replaced++;
        echo "PATCHED: $rel\n";
    }
}
echo "Patched $replaced file(s).\n";
