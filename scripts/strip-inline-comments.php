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

function stripHtmlComments($html) {
    return preg_replace('/<!--(?!\[if\b)[\s\S]*?-->/m', '', $html);
}

function stripJsBlock($block) {
    $out = '';
    $len = strlen($block);
    $i = 0;
    $inSingle = false;
    $inDouble = false;
    $inBacktick = false;
    $inRegex = false;
    $prevSig = '';
    while ($i < $len) {
        $c = $block[$i];
        $n = $i + 1 < $len ? $block[$i + 1] : '';
        if (!$inSingle && !$inDouble && !$inBacktick && !$inRegex) {
            if ($c === '/' && $n === '/') {
                while ($i < $len && $block[$i] !== "\n") { $i++; }
                continue;
            }
            if ($c === '/' && $n === '*') {
                $i += 2;
                while ($i < $len - 1 && !($block[$i] === '*' && $block[$i + 1] === '/')) { $i++; }
                $i += 2;
                continue;
            }
        }
        if ($c === "'" && !$inDouble && !$inBacktick && !$inRegex) {
            $bs = 0; $k = $i - 1;
            while ($k >= 0 && $block[$k] === '\\') { $bs++; $k--; }
            if ($bs % 2 === 0) { $inSingle = !$inSingle; }
        } elseif ($c === '"' && !$inSingle && !$inBacktick && !$inRegex) {
            $bs = 0; $k = $i - 1;
            while ($k >= 0 && $block[$k] === '\\') { $bs++; $k--; }
            if ($bs % 2 === 0) { $inDouble = !$inDouble; }
        } elseif ($c === '`' && !$inSingle && !$inDouble && !$inRegex) {
            $bs = 0; $k = $i - 1;
            while ($k >= 0 && $block[$k] === '\\') { $bs++; $k--; }
            if ($bs % 2 === 0) { $inBacktick = !$inBacktick; }
        }
        $out .= $c;
        if (!ctype_space($c)) { $prevSig = $c; }
        $i++;
    }
    return $out;
}

function stripScriptTags($html) {
    return preg_replace_callback('/(<script\b[^>]*>)([\s\S]*?)(<\/script>)/i', function($m) {
        $tag = $m[1];
        $code = $m[2];
        $closing = $m[3];
        if (stripos($tag, 'application/ld+json') !== false) {
            return $tag . $code . $closing;
        }
        if (preg_match('/\bsrc\s*=/i', $tag)) {
            return $tag . $code . $closing;
        }
        $stripped = stripJsBlock($code);
        $stripped = preg_replace("/\n{3,}/", "\n\n", $stripped);
        return $tag . $stripped . $closing;
    }, $html);
}

function stripStyleComments($html) {
    return preg_replace_callback('/(<style\b[^>]*>)([\s\S]*?)(<\/style>)/i', function($m) {
        $stripped = preg_replace('#/\*[\s\S]*?\*/#', '', $m[2]);
        return $m[1] . $stripped . $m[3];
    }, $html);
}

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
    $tokens = @token_get_all($orig);
    if ($tokens === false) continue;
    $out = '';
    foreach ($tokens as $tok) {
        if (is_array($tok) && $tok[0] === T_INLINE_HTML) {
            $chunk = $tok[1];
            $chunk = stripHtmlComments($chunk);
            $chunk = stripScriptTags($chunk);
            $chunk = stripStyleComments($chunk);
            $chunk = preg_replace("/^[ \t]+$/m", '', $chunk);
            $chunk = preg_replace("/\n{3,}/", "\n\n", $chunk);
            $out .= $chunk;
        } elseif (is_array($tok)) {
            $out .= $tok[1];
        } else {
            $out .= $tok;
        }
    }
    if ($out !== $orig) {
        file_put_contents($path, $out);
        $processed++;
    }
}
echo "Stripped HTML/JS/CSS comments in $processed file(s).\n";
