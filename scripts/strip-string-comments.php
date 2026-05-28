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

function stripCommentsInHtmlContent($content) {
    $content = preg_replace('/<!--(?!\[if\b)[\s\S]*?-->/m', '', $content);
    $content = preg_replace_callback('/(<script\b[^>]*>)([\s\S]*?)(<\/script>)/i', function($m) {
        $tag = $m[1];
        $body = $m[2];
        if (stripos($tag, 'application/ld+json') !== false) return $tag . $body . $m[3];
        if (preg_match('/\bsrc\s*=/i', $tag)) return $tag . $body . $m[3];
        $body = preg_replace('#/\*[\s\S]*?\*/#', '', $body);
        $body = preg_replace('#^\s*//(?!/).*$#m', '', $body);
        $body = preg_replace("/\n{3,}/", "\n\n", $body);
        return $tag . $body . $m[3];
    }, $content);
    $content = preg_replace_callback('/(<style\b[^>]*>)([\s\S]*?)(<\/style>)/i', function($m) {
        return $m[1] . preg_replace('#/\*[\s\S]*?\*/#', '', $m[2]) . $m[3];
    }, $content);
    $content = preg_replace("/\n{3,}/", "\n\n", $content);
    return $content;
}

function processString($text) {
    if (strpos($text, '<!--') === false && strpos($text, '<script') === false && strpos($text, '<style') === false) {
        return $text;
    }
    return stripCommentsInHtmlContent($text);
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
    $tokens = @token_get_all($orig);
    if ($tokens === false) continue;
    $out = '';
    foreach ($tokens as $tok) {
        if (is_array($tok)) {
            $id = $tok[0];
            $text = $tok[1];
            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $first = $text[0] ?? '';
                $inner = substr($text, 1, -1);
                $newInner = processString($inner);
                if ($newInner !== $inner) {
                    $out .= $first . $newInner . $first;
                } else {
                    $out .= $text;
                }
            } elseif ($id === T_ENCAPSED_AND_WHITESPACE || $id === T_INLINE_HTML) {
                $out .= processString($text);
            } else {
                $out .= $text;
            }
        } else {
            $out .= $tok;
        }
    }
    if ($out !== $orig) {
        file_put_contents($path, $out);
        $processed++;
    }
}
echo "Processed strings in $processed file(s).\n";
