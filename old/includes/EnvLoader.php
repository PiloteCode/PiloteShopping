<?php

if (!class_exists('EnvLoader')) {
class EnvLoader {
    private static $loaded = false;

    public static function load($path) {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $name = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

                        $len = strlen($value);
            if ($len >= 2) {
                $first = $value[0];
                $last = $value[$len - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                    if ($first === '"') {
                        $value = str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $value);
                    }
                }
            }

            if (getenv($name) === false) {
                putenv("$name=$value");
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

        public static function get($name, $default = null, $type = 'string') {
        $value = getenv($name);
        if ($value === false || $value === '') {
            return $default;
        }
        switch ($type) {
            case 'bool':
                $v = strtolower(trim($value));
                return in_array($v, ['1', 'true', 'yes', 'on'], true);
            case 'int':
                return (int)$value;
            case 'float':
                return (float)$value;
            default:
                return $value;
        }
    }

        public static function required($name) {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new RuntimeException(sprintf(
                "Variable d'environnement requise manquante: %s. Vérifier le fichier .env.",
                $name
            ));
        }
        return $value;
    }
}
}
