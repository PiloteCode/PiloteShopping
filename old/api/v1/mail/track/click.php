<?php

define('API_ACCESS', true);
require_once '../../config.php';
require_once 'TrackingManager.php';

$linkToken = $_GET['token'] ?? null;

if (empty($linkToken) || !preg_match('/^[a-zA-Z0-9_\-]{16,128}$/', $linkToken)) {
    header('Location: ' . SITE_URL);
    exit();
}

function isSafeRedirectUrl($url) {
    if (!is_string($url) || $url === '') {
        return false;
    }
    if (strpos($url, "\r") !== false || strpos($url, "\n") !== false || strpos($url, "\0") !== false) {
        return false;
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
        return false;
    }
    $scheme = strtolower($parts['scheme']);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return false;
    }
    return true;
}

try {
    $trackingManager = new TrackingManager();
    $result = $trackingManager->trackClick($linkToken);
    if ($result['success'] && !empty($result['redirect_url'])) {
        $redirectUrl = $result['redirect_url'];
        if (isSafeRedirectUrl($redirectUrl)) {
            header('X-Robots-Tag: noindex, nofollow');
            header('Referrer-Policy: no-referrer');
            header("Location: $redirectUrl", true, 302);
            exit();
        } else {
            error_log("Invalid redirect URL in click tracking: $redirectUrl");
            header('Location: ' . SITE_URL);
            exit();
        }
    } else {
        header('Location: ' . SITE_URL . '?error=invalid_link');
        exit();
    }
} catch (Exception $e) {
    error_log("Email click tracking error: " . $e->getMessage());
    header('Location: ' . SITE_URL . '?error=tracking_error');
    exit();
}
