<?php

class RememberMeManager {
    private $db;
    private const TOKEN_LIFETIME = 30 * 24 * 60 * 60;     private const COOKIE_NAME = 'remember_me';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
        public function createRememberToken($userId) {
        try {
                        $token = $this->generateSecureToken();
            $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_LIFETIME);
            
                        $this->cleanupOldTokens($userId);
            
                        $stmt = $this->db->prepare("
                INSERT INTO remember_tokens 
                (user_id, token, expires_at, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                hash('sha256', $token),                 $expiresAt,
                getRealIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
                        $this->setRememberCookie($token);
            
            logSecurityEvent($userId, 'remember_token_created', [
                'expires_at' => $expiresAt,
                'ip' => getRealIP()
            ], 'success');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Remember token creation failed: " . $e->getMessage());
            return false;
        }
    }
    
        public function validateRememberToken($token) {
        try {
            if (empty($token)) {
                return false;
            }
            
            $hashedToken = hash('sha256', $token);
            
                        $stmt = $this->db->prepare("
                SELECT rt.*, u.id, u.username, u.email, u.is_active, u.is_verified 
                FROM remember_tokens rt
                JOIN users u ON rt.user_id = u.id
                WHERE rt.token = ? 
                AND rt.expires_at > NOW() 
                AND rt.is_revoked = FALSE
                AND u.is_active = TRUE
            ");
            
            $stmt->execute([$hashedToken]);
            $tokenData = $stmt->fetch();
            
            if (!$tokenData) {
                                $this->clearRememberCookie();
                return false;
            }
            
                        if (!$this->isTokenSecure($tokenData)) {
                $this->revokeToken($hashedToken);
                $this->clearRememberCookie();
                
                logSecurityEvent($tokenData['user_id'], 'remember_token_security_violation', [
                    'stored_ip' => $tokenData['ip_address'],
                    'current_ip' => getRealIP(),
                    'stored_ua' => substr($tokenData['user_agent'], 0, 100),
                    'current_ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100)
                ], 'blocked');
                
                return false;
            }
            
            $newToken = bin2hex(random_bytes(32));
            $newHashed = hash('sha256', $newToken);
            $rotateStmt = $this->db->prepare("
                UPDATE remember_tokens
                SET token = ?, last_used_at = NOW(), ip_address = ?, user_agent = ?
                WHERE token = ?
            ");
            $rotateStmt->execute([
                $newHashed,
                getRealIP(),
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                $hashedToken
            ]);
            $this->setRememberCookie($newToken);

            logSecurityEvent($tokenData['user_id'], 'remember_token_used_and_rotated', [
                'token_age' => time() - strtotime($tokenData['created_at'])
            ], 'success');

            return [
                'user_id' => $tokenData['user_id'],
                'username' => $tokenData['username'],
                'email' => $tokenData['email']
            ];
            
        } catch (Exception $e) {
            error_log("Remember token validation failed: " . $e->getMessage());
            $this->clearRememberCookie();
            return false;
        }
    }
    
        public function revokeToken($hashedToken) {
        try {
            $stmt = $this->db->prepare("
                UPDATE remember_tokens 
                SET is_revoked = TRUE 
                WHERE token = ?
            ");
            $stmt->execute([$hashedToken]);
            
        } catch (Exception $e) {
            error_log("Token revocation failed: " . $e->getMessage());
        }
    }
    
        public function revokeAllUserTokens($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE remember_tokens 
                SET is_revoked = TRUE 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            logSecurityEvent($userId, 'all_remember_tokens_revoked', [], 'success');
            
        } catch (Exception $e) {
            error_log("All tokens revocation failed: " . $e->getMessage());
        }
    }
    
        public function clearRememberCookie() {
        if (isset($_COOKIE[self::COOKIE_NAME])) {
            setcookie(self::COOKIE_NAME, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'domain' => COOKIE_DOMAIN,
                'secure' => COOKIE_SECURE,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        }
    }
    
        private function isTokenSecure($tokenData) {
        $currentIP = getRealIP();
        $storedIP = $tokenData['ip_address'];

        if ($currentIP !== $storedIP) {
            $sameSubnet = false;
            if (filter_var($currentIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
                && filter_var($storedIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $sameSubnet = (ip2long($currentIP) >> 8) === (ip2long($storedIP) >> 8);
            }
            logSecurityEvent($tokenData['user_id'], 'remember_token_ip_change', [
                'old_ip' => $storedIP,
                'new_ip' => $currentIP,
                'same_subnet' => $sameSubnet
            ], 'warning');
            if (!$sameSubnet) {
                return false;
            }
        }

        $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $storedUA = $tokenData['user_agent'];
        $similarity = 0;
        similar_text($currentUA, $storedUA, $similarity);
        if ($similarity < 80) {
            return false;
        }
        return true;
    }
    
        private function updateTokenUsage($hashedToken) {
        try {
            $stmt = $this->db->prepare("
                UPDATE remember_tokens 
                SET last_used_at = NOW() 
                WHERE token = ?
            ");
            $stmt->execute([$hashedToken]);
            
        } catch (Exception $e) {
            error_log("Token usage update failed: " . $e->getMessage());
        }
    }
    
        private function cleanupOldTokens($userId) {
        try {
                        $stmt = $this->db->prepare("
                DELETE FROM remember_tokens 
                WHERE user_id = ? 
                AND (expires_at < NOW() OR is_revoked = TRUE)
            ");
            $stmt->execute([$userId]);
            
                        $stmt = $this->db->prepare("
                DELETE FROM remember_tokens 
                WHERE user_id = ? 
                AND id NOT IN (
                    SELECT * FROM (
                        SELECT id FROM remember_tokens 
                        WHERE user_id = ? 
                        AND expires_at > NOW() 
                        AND is_revoked = FALSE
                        ORDER BY created_at DESC 
                        LIMIT 5
                    ) t
                )
            ");
            $stmt->execute([$userId, $userId]);
            
        } catch (Exception $e) {
            error_log("Token cleanup failed: " . $e->getMessage());
        }
    }
    
        private function setRememberCookie($token) {
        setcookie(self::COOKIE_NAME, $token, [
            'expires' => time() + self::TOKEN_LIFETIME,
            'path' => '/',
            'domain' => COOKIE_DOMAIN,
            'secure' => COOKIE_SECURE,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    
        private function generateSecureToken() {
        return bin2hex(random_bytes(32));
    }
    
        public function getUserActiveTokens($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, created_at, last_used_at, expires_at, ip_address, 
                       SUBSTRING(user_agent, 1, 100) as user_agent_preview
                FROM remember_tokens 
                WHERE user_id = ? 
                AND expires_at > NOW() 
                AND is_revoked = FALSE
                ORDER BY last_used_at DESC, created_at DESC
            ");
            $stmt->execute([$userId]);
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            error_log("Get user tokens failed: " . $e->getMessage());
            return [];
        }
    }
    
        public static function hasRememberCookie() {
        return isset($_COOKIE[self::COOKIE_NAME]) && !empty($_COOKIE[self::COOKIE_NAME]);
    }
    
        public static function getRememberToken() {
        return $_COOKIE[self::COOKIE_NAME] ?? null;
    }
}
?>