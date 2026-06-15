<?php
define('ROOT_PATH', dirname(__DIR__)); 

require_once ROOT_PATH . '/api/v1/auth/RememberMeManager.php';
class AuthMiddleware {
    private static $instance = null;
    private $db;
    private $rememberMe;
    private $isAuthenticated = false;
    private $currentUser = null;
    
    private function __construct() {
        $this->db = Database::getInstance();
        $this->rememberMe = new RememberMeManager();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
        public function initialize() {
                if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
                if ($this->checkCurrentSession()) {
            return true;
        }
        
                if (RememberMeManager::hasRememberCookie()) {
            return $this->attemptRememberMeLogin();
        }
        
        return false;
    }
    
        private function checkCurrentSession() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
            return false;
        }
        
        try {
                        $stmt = $this->db->prepare("
                SELECT us.*, u.username, u.email, u.first_name, u.last_name, 
                       u.avatar_url, u.balance, u.is_active, u.is_verified, u.profile_completed
                FROM user_sessions us
                JOIN users u ON us.user_id = u.id
                WHERE us.id = ? AND us.user_id = ? AND us.expires_at > NOW()
            ");
            
            $stmt->execute([$_SESSION['session_id'], $_SESSION['user_id']]);
            $sessionData = $stmt->fetch();
            
            if (!$sessionData || !$sessionData['is_active']) {
                $this->clearSession();
                return false;
            }
            
                        $this->updateSessionActivity($_SESSION['session_id']);
            
            $this->isAuthenticated = true;
            $this->currentUser = [
                'id' => $sessionData['user_id'],
                'username' => $sessionData['username'],
                'email' => $sessionData['email'],
                'first_name' => $sessionData['first_name'],
                'last_name' => $sessionData['last_name'],
                'avatar_url' => $sessionData['avatar_url'],
                'balance' => floatval($sessionData['balance']),
                'profile_completed' => $sessionData['profile_completed']
            ];
            
            return true;
            
        } catch (Exception $e) {
            error_log("Session check failed: " . $e->getMessage());
            $this->clearSession();
            return false;
        }
    }
    
        private function attemptRememberMeLogin() {
        $token = RememberMeManager::getRememberToken();
        $userData = $this->rememberMe->validateRememberToken($token);
        
        if (!$userData) {
            return false;
        }
        
        try {
                        $stmt = $this->db->prepare("
                SELECT id, username, email, first_name, last_name, avatar_url, 
                       balance, is_active, is_verified, profile_completed
                FROM users 
                WHERE id = ? AND is_active = 1 AND is_verified = 1
            ");
            
            $stmt->execute([$userData['user_id']]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $this->rememberMe->clearRememberCookie();
                return false;
            }
            
            session_regenerate_id(true);
            $sessionId = $this->createSession($user['id']);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['username'] = $user['username'];
            $_SESSION['auto_login'] = true;
            
            $this->isAuthenticated = true;
            $this->currentUser = [
                'id' => $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'avatar_url' => $user['avatar_url'],
                'balance' => floatval($user['balance']),
                'profile_completed' => $user['profile_completed']
            ];
            
            logSecurityEvent($user['id'], 'auto_login_remember_me', [
                'session_id' => $sessionId
            ], 'success');
            
            return true;
            
        } catch (Exception $e) {
            error_log("Remember me login failed: " . $e->getMessage());
            $this->rememberMe->clearRememberCookie();
            return false;
        }
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
        
        return $sessionId;
    }
    
        private function updateSessionActivity($sessionId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_sessions 
                SET updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$sessionId]);
            
        } catch (Exception $e) {
            error_log("Session update failed: " . $e->getMessage());
        }
    }
    
        private function clearSession() {
        unset($_SESSION['user_id'], $_SESSION['session_id'], $_SESSION['username'], $_SESSION['auto_login']);
        $this->isAuthenticated = false;
        $this->currentUser = null;
    }
    
        public function isAuthenticated() {
        return $this->isAuthenticated;
    }
    
        public function getCurrentUser() {
        return $this->currentUser;
    }
    
        public function requireAuth($redirectUrl = '/auth/login') {
        if (!$this->isAuthenticated()) {
            $intended = $_SERVER['REQUEST_URI'] ?? '/';
            if (self::isSafeInternalPath($intended)) {
                $_SESSION['intended_url'] = $intended;
            }
            header('Location: ' . $redirectUrl);
            exit();
        }
    }

    public function redirectToIntended($defaultUrl = '/dashboard') {
        $url = $_SESSION['intended_url'] ?? $defaultUrl;
        unset($_SESSION['intended_url']);
        if (!self::isSafeInternalPath($url)) {
            $url = $defaultUrl;
        }
        header('Location: ' . $url);
        exit();
    }

    private static function isSafeInternalPath($path) {
        if (!is_string($path) || $path === '') {
            return false;
        }
        if ($path[0] !== '/' || (isset($path[1]) && ($path[1] === '/' || $path[1] === '\\'))) {
            return false;
        }
        if (strpos($path, "\r") !== false || strpos($path, "\n") !== false || strpos($path, "\0") !== false) {
            return false;
        }
        return true;
    }
    
        public function logout() {
        try {
            $userId = $this->currentUser['id'] ?? null;
            $sessionId = $_SESSION['session_id'] ?? null;
            
            if ($userId && $sessionId) {
                                $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ?");
                $stmt->execute([$sessionId]);
                
                                if (isset($_POST['revoke_all_devices'])) {
                    $this->rememberMe->revokeAllUserTokens($userId);
                }
                
                logSecurityEvent($userId, 'logout_middleware', [
                    'session_id' => $sessionId
                ], 'success');
            }
            
                        $this->rememberMe->clearRememberCookie();
            
                        session_destroy();
            
                        if (isset($_COOKIE[session_name()])) {
                setcookie(session_name(), '', time() - 3600, '/');
            }
            
        } catch (Exception $e) {
            error_log("Logout failed: " . $e->getMessage());
        }
    }
    
        public function needsProfileCompletion() {
        if (!$this->isAuthenticated()) {
            return false;
        }
        
        return !$this->currentUser['profile_completed'];
    }
    
        public function markProfileCompleted($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET profile_completed = TRUE, needs_profile_completion = FALSE
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
                        $stmt = $this->db->prepare("
                UPDATE users_oauth 
                SET needs_completion = FALSE, profile_completed = TRUE
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
                        if ($this->currentUser && $this->currentUser['id'] == $userId) {
                $this->currentUser['profile_completed'] = true;
            }
            
            logSecurityEvent($userId, 'profile_completed', [], 'success');
            
        } catch (Exception $e) {
            error_log("Profile completion update failed: " . $e->getMessage());
        }
    }
}

function initAuth() {
    $auth = AuthMiddleware::getInstance();
    $auth->initialize();
    return $auth;
}

function getCurrentUser() {
    $auth = AuthMiddleware::getInstance();
    return $auth->getCurrentUser();
}

function isLoggedIn() {
    $auth = AuthMiddleware::getInstance();
    return $auth->isAuthenticated();
}
?>