<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once 'RememberMeManager.php';

session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

class AuthManager {
    private $db;
    private $rememberMe;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->rememberMe = new RememberMeManager();
    }
    
    public function login($email, $password, $turnstileToken, $rememberMe = false, $totpCode = null) {
        try {
                        if (empty($email) || empty($password)) {
                throw new Exception('Email et mot de passe requis');
            }
            
            if (!validateEmail($email)) {
                throw new Exception('Format d\'email invalide');
            }
            
                        if (!$this->verifyTurnstile($turnstileToken)) {
                logSecurityEvent(null, 'login_failed_captcha', ['email' => $email], 'failed');
                throw new Exception('Captcha invalide');
            }
            
                        if (!$this->checkRateLimit($email)) {
                logSecurityEvent(null, 'login_rate_limited', ['email' => $email], 'blocked');
                throw new Exception('Trop de tentatives de connexion. Réessayez dans 15 minutes.');
            }
            
                        $user = $this->findUserByEmail($email);
            if (!$user) {
                $this->recordFailedAttempt($email);
                logSecurityEvent(null, 'login_failed_user_not_found', ['email' => $email], 'failed');
                throw new Exception('Identifiants incorrects');
            }
            
                        if (!verifyPassword($password, $user['password_hash'], $user['password_salt'])) {
                $this->recordFailedAttempt($email);
                logSecurityEvent($user['id'], 'login_failed_password', ['email' => $email], 'failed');
                throw new Exception('Identifiants incorrects');
            }
            
                        if (!$user['is_active']) {
                logSecurityEvent($user['id'], 'login_failed_inactive', ['email' => $email], 'failed');
                throw new Exception('Compte désactivé');
            }
            
            
                        $requires2FA = $this->requires2FA($user['id']);
            if ($requires2FA) {
                if (empty($totpCode)) {
                                        return [
                        'success' => false,
                        'requires_2fa' => true,
                        'user_id' => $user['id'],
                        'message' => 'Code d\'authentification à deux facteurs requis'
                    ];
                }
                
                if (!$this->verify2FA($user['id'], $totpCode)) {
                    $this->recordFailedAttempt($email);
                    logSecurityEvent($user['id'], 'login_failed_2fa', ['email' => $email], 'failed');
                    throw new Exception('Code d\'authentification invalide');
                }
            }
            
                        $needsCompletion = $this->needsProfileCompletion($user['id']);
            
                        $sessionId = $this->createSession($user['id']);
            $this->updateLastLogin($user['id']);
            $this->clearFailedAttempts($email);
            
                        if ($rememberMe) {
                $this->rememberMe->createRememberToken($user['id']);
            }
            
                        logSecurityEvent($user['id'], 'login_success', [
                'email' => $email,
                'session_id' => $sessionId,
                'remember_me' => $rememberMe,
                'needs_completion' => $needsCompletion
            ], 'success');
            
            return [
                'success' => true,
                'session_id' => $sessionId,
                'needs_profile_completion' => $needsCompletion,
                'user' => [
                    'id' => $user['id'],
                    'uuid' => $user['uuid'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'avatar_url' => $user['avatar_url'],
                    'balance' => floatval($user['balance']),
                    'profile_completed' => $user['profile_completed']
                ],
                'message' => 'Connexion réussie'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function needsProfileCompletion($userId) {
                $stmt = $this->db->prepare("
            SELECT COUNT(*) as has_billing
            FROM users_billing 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
                $stmt = $this->db->prepare("
            SELECT needs_completion
            FROM users_oauth 
            WHERE user_id = ? AND provider = 'google'
        ");
        $stmt->execute([$userId]);
        $oauthData = $stmt->fetch();
        
        return ($result['has_billing'] == 0) || ($oauthData && $oauthData['needs_completion']);
    }
    
    private function verifyTurnstile($token) {
        if (empty($token)) {
            return false;
        }
        
        $data = [
            'secret' => CLOUDFLARE_TURNSTILE_SECRET_KEY,
            'response' => $token,
            'remoteip' => getRealIP()
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://challenges.cloudflare.com/turnstile/v0/siteverify');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($result === FALSE || $httpCode !== 200) {
            return false;
        }
        
        $response = json_decode($result, true);
        return isset($response['success']) && $response['success'] === true;
    }
    
    private function checkRateLimit($email) {
        $ip = getRealIP();
        
        $stmt = $this->db->prepare("
            SELECT attempts, blocked_until 
            FROM login_attempts 
            WHERE (ip_address = ? OR email = ?) 
            AND last_attempt > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip, $email]);
        $attempt = $stmt->fetch();
        
        if ($attempt) {
                        if ($attempt['blocked_until'] && strtotime($attempt['blocked_until']) > time()) {
                return false;
            }
            
                        if ($attempt['attempts'] >= MAX_LOGIN_ATTEMPTS) {
                                $stmt = $this->db->prepare("
                    UPDATE login_attempts 
                    SET blocked_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) 
                    WHERE (ip_address = ? OR email = ?)
                ");
                $stmt->execute([$ip, $email]);
                return false;
            }
        }
        
        return true;
    }
    
    private function recordFailedAttempt($email) {
        $ip = getRealIP();
        
        $stmt = $this->db->prepare("
            INSERT INTO login_attempts (ip_address, email, attempts, last_attempt) 
            VALUES (?, ?, 1, NOW()) 
            ON DUPLICATE KEY UPDATE 
            attempts = attempts + 1, 
            last_attempt = NOW()
        ");
        $stmt->execute([$ip, $email]);
    }
    
    private function clearFailedAttempts($email) {
        $ip = getRealIP();
        
        $stmt = $this->db->prepare("
            DELETE FROM login_attempts 
            WHERE ip_address = ? OR email = ?
        ");
        $stmt->execute([$ip, $email]);
    }
    
    private function findUserByEmail($email) {
        $stmt = $this->db->prepare("
            SELECT id, uuid, username, email, password_hash, password_salt, 
                   first_name, last_name, avatar_url, balance, 
                   is_active, is_verified, profile_completed
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    private function requires2FA($userId) {
        $stmt = $this->db->prepare("
            SELECT is_enabled 
            FROM users_2fa 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result && $result['is_enabled'];
    }
    
    private function verify2FA($userId, $code) {
        $stmt = $this->db->prepare("
            SELECT secret 
            FROM users_2fa 
            WHERE user_id = ? AND is_enabled = 1
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if (!$result) {
            return false;
        }
        
                require_once '../../../vendor/autoload.php';
        
        $ga = new PHPGangsta_GoogleAuthenticator();
        $tolerance = 2;         
        return $ga->verifyCode($result['secret'], $code, $tolerance);
    }
    
    private function createSession($userId) {
        $sessionId = generateSecureToken(32);
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
        
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isMobile = preg_match('/(android|iphone|ipad|mobile)/i', $userAgent);
        
        $stmt = $this->db->prepare("
            INSERT INTO user_sessions 
            (id, user_id, ip_address, user_agent, is_mobile, expires_at) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            getRealIP(),
            $userAgent,
            $isMobile ? 1 : 0,
            $expiresAt
        ]);
        
                $stmt = $this->db->prepare("
            DELETE FROM user_sessions 
            WHERE user_id = ? 
            AND id NOT IN (
                SELECT * FROM (
                    SELECT id FROM user_sessions 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC 
                    LIMIT 5
                ) t
            )
        ");
        $stmt->execute([$userId, $userId]);
        
        return $sessionId;
    }
    
    private function updateLastLogin($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET last_login_at = NOW(), last_ip = ? 
            WHERE id = ?
        ");
        $stmt->execute([getRealIP(), $userId]);
    }
}

try {
        $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide');
    }
    
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $turnstileToken = $input['turnstile_token'] ?? '';
    $totpCode = $input['totp_code'] ?? '';
    $rememberMe = isset($input['remember_me']) && $input['remember_me'];
    
        $authManager = new AuthManager();
    
        $result = $authManager->login($email, $password, $turnstileToken, $rememberMe, $totpCode);
    
    if ($result['success']) {
                $_SESSION['user_id'] = $result['user']['id'];
        $_SESSION['session_id'] = $result['session_id'];
        $_SESSION['username'] = $result['user']['username'];
        
                session_regenerate_id(true);
        
                $cookieOptions = [
            'expires' => time() + SESSION_LIFETIME,
            'path' => '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => COOKIE_SECURE,
            'httponly' => COOKIE_HTTPONLY,
            'samesite' => COOKIE_SAMESITE
        ];
        
        setcookie(SESSION_NAME, session_id(), $cookieOptions);
        
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logSecurityEvent(null, 'login_error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
?>