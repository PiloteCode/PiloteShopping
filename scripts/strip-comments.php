<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = realpath(__DIR__ . '/..');
if (!$root) {
    fwrite(STDERR, "Cannot resolve project root\n");
    exit(1);
}

$exclusions = [
    $root . '/plugins',
    $root . '/vendor',
    $root . '/node_modules',
    $root . '/.git',
    $root . '/scripts',
];

$processed = 0;
$skipped = 0;
$errors = [];

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iter as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    foreach ($exclusions as $ex) {
        if (strpos($path, $ex) === 0) {
            $skipped++;
            continue 2;
        }
    }
    try {
        $original = file_get_contents($path);
        $tokens = @token_get_all($original);
        if ($tokens === false) {
            throw new RuntimeException('Tokenization failed');
        }
        $output = '';
        $prevWasComment = false;
        foreach ($tokens as $i => $tok) {
            if (is_array($tok)) {
                [$id, $text] = $tok;
                if ($id === T_COMMENT || $id === T_DOC_COMMENT) {
                    $prevWasComment = true;
                    continue;
                }
                if ($prevWasComment && $id === T_WHITESPACE) {
                    $text = preg_replace("/^[ \t]*\n/", '', $text);
                    if ($text === '') {
                        continue;
                    }
                }
                $output .= $text;
                $prevWasComment = false;
            } else {
                $output .= $tok;
                $prevWasComment = false;
            }
        }
        $output = preg_replace("/\n{3,}/", "\n\n", $output);
        if ($output !== $original) {
            file_put_contents($path, $output);
            $processed++;
        }
    } catch (Throwable $e) {
        $errors[] = "$path: " . $e->getMessage();
    }
}

echo "Stripped comments from $processed file(s). Skipped $skipped (excluded).\n";
if ($errors) {
    echo "Errors:\n";
    foreach ($errors as $err) {
        echo "  - $err\n";
    }
    exit(1);
}
