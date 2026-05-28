<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = realpath(__DIR__ . '/..');
$targets = [
    'api/v1/settings/password.php',
    'api/v1/settings/profile.php',
    'api/v1/settings/avatar.php',
    'api/v1/settings/sessions.php',
    'api/v1/checkout/create-order.php',
    'api/v1/orders/access-product.php',
    'api/v1/cart/add.php',
    'api/v1/cart/remove.php',
    'api/v1/cart/update.php',
    'api/v1/wishlist/add.php',
    'api/v1/wishlist/remove.php',
    'api/v1/notifications/actions.php',
    'api/v1/auth/logout.php',
    'api/v1/payments/stripe/saved-cards.php',
    'api/v1/subscriptions/manage.php',
];

$snippet = "require_once __DIR__ . '/../../../includes/CsrfProtection.php';\n";
$callSnippet = "\\CsrfProtection::requireValidToken();\n";

$processed = 0;
foreach ($targets as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        echo "MISSING: $rel\n";
        continue;
    }
    $src = file_get_contents($path);
    if (strpos($src, 'CsrfProtection') !== false) {
        echo "ALREADY: $rel\n";
        continue;
    }
    $new = preg_replace(
        "#(require_once\s+['\"]\\.\\./config\\.php['\"];\\s*\\n)#",
        "$1" . $snippet,
        $src,
        1
    );
    $new = preg_replace(
        "#(if\s*\\(\\s*\\\$_SERVER\\['REQUEST_METHOD'\\]\\s*===\\s*'OPTIONS'\\s*\\)\\s*\\{[^}]*\\}\\s*\\n)#s",
        "$1\n" . $callSnippet . "\n",
        $new,
        1
    );
    if ($new === $src || $new === null) {
        echo "NO-CHANGE: $rel\n";
        continue;
    }
    file_put_contents($path, $new);
    $processed++;
    echo "PATCHED: $rel\n";
}
echo "\nDone. Patched $processed file(s).\n";
