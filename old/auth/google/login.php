<?php

define('API_ACCESS', true);

require_once '../../api/v1/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('getRealIP')) {
    function getRealIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
}

if (!function_exists('generateSecureToken')) {
    function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
}

if (!function_exists('logSecurityEvent')) {
    function logSecurityEvent($userId, $event, $data, $status) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'event' => $event,
            'data' => $data,
            'status' => $status,
            'ip' => getRealIP()
        ];
        error_log("Security Event: " . json_encode($logData));
    }
}

try {
        error_log("Google OAuth Login initiated from: " . $_SERVER['REQUEST_URI']);
    
        $rememberMe = isset($_GET['remember_me']) && $_GET['remember_me'] === 'true';
    
        $state = generateSecureToken(32);
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_provider'] = 'google';
    $_SESSION['oauth_remember_me'] = $rememberMe;
    
    error_log("OAuth state generated: " . substr($state, 0, 8) . "... Remember me: " . ($rememberMe ? 'true' : 'false'));
    
        if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET) || empty(GOOGLE_REDIRECT_URI)) {
        throw new Exception('Configuration Google OAuth manquante');
    }
    
        $redirectUri = str_replace('/api/v1/auth/google/callback.php', '/auth/google/callback.php', GOOGLE_REDIRECT_URI);
    
        $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => $redirectUri,
        'scope' => 'openid email profile',
        'response_type' => 'code',
        'state' => $state,
        'access_type' => 'offline',
        'prompt' => 'select_account',
        'include_granted_scopes' => 'true'
    ];
    
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    
    error_log("Redirecting to Google: " . $authUrl);
    
        logSecurityEvent(null, 'google_oauth_initiated', [
        'state' => substr($state, 0, 8) . '...',
        'redirect_uri' => $redirectUri,
        'remember_me' => $rememberMe,
        'ip' => getRealIP(),
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)
    ], 'success');
    
        header('Location: ' . $authUrl);
    exit();
    
} catch (Exception $e) {
    error_log("Google OAuth Login Error: " . $e->getMessage());
    
    logSecurityEvent(null, 'google_oauth_login_error', [
        'error' => $e->getMessage(),
        'ip' => getRealIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ], 'failed');
    
        $encodedError = urlencode('Erreur Google OAuth: ' . $e->getMessage());
    header('Location: /auth/login?error=' . $encodedError);
    exit();
}
?>