<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once '../mail/MailerManager.php';
require_once '../mail/QueueProcessor.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

class ResetPasswordManager {
    private $db;
    private $mailer;
    private $queue;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->mailer = new MailerManager();
        $this->queue = new EmailQueueProcessor();
    }
    
    public function resetPassword($token, $password, $confirmPassword, $turnstileToken) {
        try {
                        $this->validateInput($token, $password, $confirmPassword, $turnstileToken);
            
                        if (!$this->verifyTurnstile($turnstileToken)) {
                logSecurityEvent(null, 'reset_password_failed_captcha', [
                    'token_preview' => substr($token, 0, 8) . '...',
                    'ip' => getRealIP()
                ], 'failed');
                throw new Exception('Captcha invalide');
            }
            
                        $tokenData = $this->validateToken($token);
            
                        $this->validatePassword($password);
            
                        if ($password !== $confirmPassword) {
                throw new Exception('Les mots de passe ne correspondent pas');
            }
            
                        if (!$this->checkRateLimit($tokenData['user_id'])) {
                logSecurityEvent($tokenData['user_id'], 'reset_password_rate_limited', [
                    'email' => $tokenData['email'],
                    'ip' => getRealIP()
                ], 'blocked');
                throw new Exception('Trop de tentatives. Attendez 15 minutes avant de réessayer.');
            }
            
                        $this->updatePassword($tokenData['user_id'], $password);
            
                        $this->markTokenAsUsed($tokenData['id']);
            
                        $this->revokeAllUserSessions($tokenData['user_id']);
            
                        $this->sendPasswordChangeNotification($tokenData);
            
                        logSecurityEvent($tokenData['user_id'], 'password_reset_success', [
                'email' => $tokenData['email'],
                'ip' => getRealIP(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Mot de passe réinitialisé avec succès'
            ];
            
        } catch (Exception $e) {
            $this->handleResetError($e, $token);
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function validateInput($token, $password, $confirmPassword, $turnstileToken) {
        if (empty($token)) {
            throw new Exception('Token de réinitialisation requis');
        }
        
        if (empty($password)) {
            throw new Exception('Nouveau mot de passe requis');
        }
        
        if (empty($confirmPassword)) {
            throw new Exception('Confirmation du mot de passe requise');
        }
        
        if (strlen($token) !== 64) {             throw new Exception('Format de token invalide');
        }
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
    
    private function validateToken($token) {
        $stmt = $this->db->prepare("
            SELECT vt.*, u.email, u.first_name, u.last_name, u.is_active
            FROM verification_tokens vt
            JOIN users u ON vt.user_id = u.id
            WHERE vt.token = ? 
            AND vt.type = 'password_reset' 
            AND vt.used_at IS NULL 
            AND vt.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();
        
        if (!$tokenData) {
                        $stmt = $this->db->prepare("
                SELECT used_at, expires_at 
                FROM verification_tokens 
                WHERE token = ? AND type = 'password_reset'
            ");
            $stmt->execute([$token]);
            $invalidToken = $stmt->fetch();
            
            if ($invalidToken) {
                if ($invalidToken['used_at']) {
                    throw new Exception('Ce lien de réinitialisation a déjà été utilisé');
                } elseif (strtotime($invalidToken['expires_at']) < time()) {
                    throw new Exception('Ce lien de réinitialisation a expiré');
                }
            }
            
            throw new Exception('Token de réinitialisation invalide');
        }
        
                if (!$tokenData['is_active']) {
            throw new Exception('Compte utilisateur désactivé');
        }
        
        return $tokenData;
    }
    
    private function validatePassword($password) {
        if (strlen($password) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Le mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères');
        }
        
        if (strlen($password) > 128) {
            throw new Exception('Le mot de passe ne peut pas dépasser 128 caractères');
        }
        
        $checks = [
            '/[a-z]/' => 'au moins une minuscule',
            '/[A-Z]/' => 'au moins une majuscule',
            '/\d/' => 'au moins un chiffre',
            '/[@$!%*?&]/' => 'au moins un caractère spécial (@$!%*?&)'
        ];
        
        foreach ($checks as $pattern => $requirement) {
            if (!preg_match($pattern, $password)) {
                throw new Exception("Le mot de passe doit contenir $requirement");
            }
        }
        
                $commonPasswords = [
            'password', 'password123', '123456', '123456789', 'qwerty',
            'azerty', 'motdepasse', 'admin', 'pilote', 'shopping'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            throw new Exception('Ce mot de passe est trop courant. Choisissez un mot de passe plus sécurisé.');
        }
    }
    
    private function checkRateLimit($userId) {
        $ip = getRealIP();
        
                $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM verification_tokens 
            WHERE user_id = ? 
            AND type = 'password_reset' 
            AND used_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$userId]);
        $userAttempts = $stmt->fetch();
        
        if ($userAttempts['count'] >= 3) {
            return false;
        }
        
                $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM security_logs 
            WHERE ip_address = ? 
            AND action = 'reset_password_failed_captcha' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip]);
        $ipAttempts = $stmt->fetch();
        
        return $ipAttempts['count'] < 5;
    }
    
    private function updatePassword($userId, $password) {
        $passwordData = hashPassword($password);
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = ?, password_salt = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $result = $stmt->execute([
            $passwordData['hash'],
            $passwordData['salt'],
            $userId
        ]);
        
        if (!$result || $stmt->rowCount() === 0) {
            throw new Exception('Impossible de mettre à jour le mot de passe');
        }
    }
    
    private function markTokenAsUsed($tokenId) {
        $stmt = $this->db->prepare("
            UPDATE verification_tokens 
            SET used_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$tokenId]);
    }
    
    private function revokeAllUserSessions($userId) {
                $stmt = $this->db->prepare("
            DELETE FROM user_sessions 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        
                if (class_exists('RememberMeManager')) {
            $rememberMe = new RememberMeManager();
            $rememberMe->revokeAllUserTokens($userId);
        }
    }
    
    private function sendPasswordChangeNotification($userData) {
        try {
                        $deviceInfo = $this->getDeviceInfo();
            $locationInfo = $this->getLocationInfo();
            
                        $emailData = [
                'template' => 'password_change_notification',
                'to_email' => $userData['email'],
                'to_name' => trim($userData['first_name'] . ' ' . $userData['last_name']),
                'variables' => [
                    'FIRST_NAME' => $userData['first_name'],
                    'FULL_NAME' => trim($userData['first_name'] . ' ' . $userData['last_name']),
                    'CHANGE_DATE' => date('d/m/Y à H:i'),
                    'IP_ADDRESS' => getRealIP(),
                    'DEVICE_TYPE' => $deviceInfo['type'],
                    'BROWSER' => $deviceInfo['browser'],
                    'OS' => $deviceInfo['os'],
                    'LOCATION' => $locationInfo['city'] ? $locationInfo['city'] . ', ' . $locationInfo['country'] : $locationInfo['country'],
                    'COUNTRY' => $locationInfo['country'],
                    'CITY' => $locationInfo['city'],
                    'LOGIN_URL' => SITE_URL . '/auth/login',
                    'SECURITY_URL' => SITE_URL . '/dashboard/security',
                    'SUPPORT_EMAIL' => SITE_EMAIL
                ],
                'options' => [
                    'category' => 'security',
                    'priority' => 1,
                    'tracking' => true
                ]
            ];
            
                        $result = $this->queue->addToQueue($emailData, 1);
            
            if ($result['success']) {
                error_log("Password change notification email queued for user ID: {$userData['user_id']}");
            } else {
                error_log("Failed to queue password change notification: " . $result['error']);
            }
            
        } catch (Exception $e) {
            error_log("Error sending password change notification: " . $e->getMessage());
                    }
    }
    
    private function getDeviceInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        
        $deviceType = 'Ordinateur';
        $browser = 'Navigateur inconnu';
        $os = 'Système inconnu';
        
                if (preg_match('/Mobile|Android|iPhone|iPod/i', $userAgent)) {
            $deviceType = 'Mobile';
        } elseif (preg_match('/iPad|Tablet/i', $userAgent)) {
            $deviceType = 'Tablette';
        }
        
                if (preg_match('/Chrome\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Chrome ' . $matches[1];
        } elseif (preg_match('/Firefox\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Firefox ' . $matches[1];
        } elseif (preg_match('/Safari\/([0-9.]+)/i', $userAgent, $matches)) {
            if (preg_match('/Version\/([0-9.]+)/i', $userAgent, $versionMatches)) {
                $browser = 'Safari ' . $versionMatches[1];
            } else {
                $browser = 'Safari';
            }
        } elseif (preg_match('/Edg\/([0-9.]+)/i', $userAgent, $matches)) {
            $browser = 'Edge ' . $matches[1];
        }
        
                if (preg_match('/Windows NT ([0-9.]+)/i', $userAgent, $matches)) {
            $osVersions = [
                '10.0' => '10/11',
                '6.3' => '8.1',
                '6.2' => '8',
                '6.1' => '7'
            ];
            $os = 'Windows ' . ($osVersions[$matches[1]] ?? $matches[1]);
        } elseif (preg_match('/Mac OS X ([0-9_]+)/i', $userAgent, $matches)) {
            $os = 'macOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Android ([0-9.]+)/i', $userAgent, $matches)) {
            $os = 'Android ' . $matches[1];
        } elseif (preg_match('/iPhone OS ([0-9_]+)/i', $userAgent, $matches)) {
            $os = 'iOS ' . str_replace('_', '.', $matches[1]);
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        }
        
        return [
            'type' => $deviceType,
            'browser' => $browser,
            'os' => $os,
            'user_agent' => $userAgent
        ];
    }
    
    private function getLocationInfo() {
        $ip = getRealIP();
        $country = 'Inconnu';
        $city = null;
        
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['country' => 'IP locale', 'city' => null];
        }
        
        try {
                        $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                    'user_agent' => 'PiloteShopping/1.0'
                ]
            ]);
            
            $response = @file_get_contents("http://ipapi.co/{$ip}/json/", false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['country_name']) && $data['country_code'] !== 'XX') {
                    $country = $data['country_name'];
                    $city = $data['city'] ?? null;
                }
            }
        } catch (Exception $e) {
            error_log("Error getting location info: " . $e->getMessage());
        }
        
        return [
            'country' => $country,
            'city' => $city
        ];
    }
    
    private function handleResetError($exception, $token) {
        $errorData = [
            'error' => $exception->getMessage(),
            'token_preview' => substr($token, 0, 8) . '...',
            'ip' => getRealIP(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)
        ];
        
                if (strpos($exception->getMessage(), 'expiré') !== false || 
            strpos($exception->getMessage(), 'utilisé') !== false) {
            logSecurityEvent(null, 'reset_password_invalid_token', $errorData, 'failed');
        } elseif (strpos($exception->getMessage(), 'Captcha') !== false) {
            logSecurityEvent(null, 'reset_password_captcha_failed', $errorData, 'failed');
        } elseif (strpos($exception->getMessage(), 'correspondent pas') !== false) {
            logSecurityEvent(null, 'reset_password_mismatch', $errorData, 'failed');
        } else {
            logSecurityEvent(null, 'reset_password_error', $errorData, 'failed');
        }
    }
}

try {
        $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Aucune donnée reçue');
    }
    
    $requestData = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide: ' . json_last_error_msg());
    }
    
        $token = trim($requestData['token'] ?? '');
    $password = $requestData['password'] ?? '';
    $confirmPassword = $requestData['confirm_password'] ?? '';
    $turnstileToken = $requestData['turnstile_token'] ?? '';
    
        error_log("Password reset attempt with token: " . substr($token, 0, 8) . '... from ' . getRealIP());
    
        $resetManager = new ResetPasswordManager();
    $result = $resetManager->resetPassword($token, $password, $confirmPassword, $turnstileToken);
    
        if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
        error_log("Password reset system error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
        http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur interne'
    ]);
}
?>