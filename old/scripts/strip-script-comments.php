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
];

function stripCommentsInJs($body) {
    $body = preg_replace('#/\*(?![\s\S]*?<\?)[\s\S]*?\*/#', '', $body);
    $lines = explode("\n", $body);
    $out = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (strpos($trimmed, '//') === 0 && strpos($trimmed, '<?') === false) {
            if (strpos($trimmed, '://') !== false && preg_match('#^//\s*(https?|ftp|file)://#', $trimmed)) {
                $out[] = $line;
            } else {
                continue;
            }
        } else {
            $out[] = $line;
        }
    }
    $result = implode("\n", $out);
    $result = preg_replace("/\n{3,}/", "\n\n", $result);
    return $result;
}

$processed = 0;
$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);
foreach ($iter as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
    $path = $file->getPathname();
    foreach ($exclusions as $ex) {
        if (strpos($path, $ex) === 0) continue 2;
    }
    $orig = file_get_contents($path);
    $new = preg_replace_callback(
        '#(<script\b[^>]*>)([\s\S]*?)(</script>)#i',
        function($m) {
            $tag = $m[1];
            $body = $m[2];
            $closing = $m[3];
            if (stripos($tag, 'application/ld+json') !== false) return $tag . $body . $closing;
            if (preg_match('/\bsrc\s*=/i', $tag)) return $tag . $body . $closing;
            return $tag . stripCommentsInJs($body) . $closing;
        },
        $orig
    );
    if ($new !== null && $new !== $orig) {
        file_put_contents($path, $new);
        $processed++;
    }
}
echo "Processed $processed file(s).\n";
