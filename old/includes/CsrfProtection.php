<?php

if (!class_exists('CsrfProtection')) {
class CsrfProtection {
    const SESSION_KEY = '_csrf_token';
    const HEADER_NAME = 'HTTP_X_CSRF_TOKEN';
    const FORM_FIELD = '_csrf_token';

        public static function token() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

        public static function rotate() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }

        public static function field() {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="' . self::FORM_FIELD . '" value="' . $token . '">';
    }

        public static function metaTag() {
        $token = htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8');
        return '<meta name="csrf-token" content="' . $token . '">';
    }

        public static function validate($candidate = null) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }
        if ($candidate === null) {
            $candidate = self::extractFromRequest();
        }
        if (!is_string($candidate) || $candidate === '') {
            return false;
        }
        return hash_equals($_SESSION[self::SESSION_KEY], $candidate);
    }

        private static function extractFromRequest() {
        if (!empty($_SERVER[self::HEADER_NAME])) {
            return $_SERVER[self::HEADER_NAME];
        }
        if (!empty($_POST[self::FORM_FIELD])) {
            return $_POST[self::FORM_FIELD];
        }
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'application/json') !== false) {
            $raw = file_get_contents('php://input');
            if ($raw) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && !empty($decoded[self::FORM_FIELD])) {
                    return $decoded[self::FORM_FIELD];
                }
            }
        }
        return null;
    }

        public static function requireValidToken() {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        if (!self::validate()) {
            http_response_code(403);
            $isJson = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false
                   || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
            if ($isJson) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => 'CSRF token invalide ou manquant',
                    'error_code' => 'CSRF_INVALID'
                ]);
            } else {
                echo 'CSRF token invalide ou manquant';
            }
            exit;
        }
    }
}
}
