<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = realpath(__DIR__ . '/..');

$targets = [
    'api/v1/cart/add.php',
    'api/v1/cart/update.php',
    'api/v1/cart/remove.php',
    'api/v1/cart/coupon.php',
    'api/v1/wishlist/add.php',
    'api/v1/wishlist/remove.php',
    'api/v1/settings/2fa/setup.php',
    'api/v1/settings/2fa/verify.php',
    'api/v1/settings/2fa/disable.php',
    'api/v1/auth/complete-profile.php',
    'api/v1/auth/logout.php',
    'api/v1/orders/create.php',
];

$processed = 0;
foreach ($targets as $rel) {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        echo "MISSING: $rel\n";
        continue;
    }
    $src = file_get_contents($path);
    if (strpos($src, 'CsrfProtection::requireValidToken') !== false || strpos($src, '\CsrfProtection::requireValidToken') !== false) {
        echo "ALREADY: $rel\n";
        continue;
    }
    if (strpos($src, 'CsrfProtection') === false) {
        $relDepth = substr_count(trim($rel, '/'), '/');
        $upPath = str_repeat('../', $relDepth);
        $includeLine = "require_once __DIR__ . '/" . $upPath . "includes/CsrfProtection.php';\n";
        $src = preg_replace(
            "#(require_once\s+['\"]\\.\\./config\\.php['\"];\\s*\\n)#",
            "$1" . $includeLine,
            $src,
            1
        );
    }
    $callLine = "\\CsrfProtection::requireValidToken();\n\n";
    $patterns = [
        "#(if\s*\\(\\s*\\\$_SERVER\\['REQUEST_METHOD'\\]\\s*===\\s*'OPTIONS'\\s*\\)\\s*\\{[^}]*\\}\\s*\\n)#s",
        "#(if\s*\\(\\s*\\\$_SERVER\\['REQUEST_METHOD'\\]\\s*!==\\s*'[A-Z]+'\\s*\\)\\s*\\{[^}]*\\}\\s*\\n)#s",
        "#(if\s*\\(\\s*!\\s*in_array\\s*\\(\\s*\\\$method[^)]*\\)\\s*\\)\\s*\\{[^}]*\\}\\s*\\n)#s",
        "#(require_once\s+['\"][^'\"]*CsrfProtection\\.php['\"];\\s*\\n)#",
    ];
    $patched = false;
    foreach ($patterns as $pat) {
        $new = preg_replace($pat, "$1\n" . $callLine, $src, 1);
        if ($new !== null && $new !== $src) {
            $src = $new;
            $patched = true;
            break;
        }
    }
    if (!$patched) {
        echo "NO-PATTERN-MATCH: $rel\n";
        continue;
    }
    file_put_contents($path, $src);
    $processed++;
    echo "PATCHED: $rel\n";
}
echo "\nDone. Patched $processed file(s).\n";
