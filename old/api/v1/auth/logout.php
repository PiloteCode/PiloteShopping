<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once __DIR__ . '/../../../includes/CsrfProtection.php';
session_start();
\CsrfProtection::requireValidToken();


class LogoutManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function logout() {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $sessionId = $_SESSION['session_id'] ?? session_id();
            
                        if ($userId) {
                logSecurityEvent($userId, 'logout_initiated', [
                    'session_id' => $sessionId
                ], 'success');
                
                                $this->removeSessionFromDatabase($sessionId);
                
                                            }
            
                        $this->cleanupSession();
            
                        $this->removeCookies();
            
                        logSecurityEvent($userId, 'logout_success', [
                'session_id' => $sessionId
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Déconnexion réussie'
            ];
            
        } catch (Exception $e) {
            logSecurityEvent($userId ?? null, 'logout_error', [
                'error' => $e->getMessage()
            ], 'failed');
            
                        $this->cleanupSession();
            $this->removeCookies();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function removeSessionFromDatabase($sessionId) {
        if (!empty($sessionId)) {
            $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
        }
    }
    
    private function removeAllUserSessions($userId) {
        $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);
    }
    
    private function cleanupSession() {
                $_SESSION = [];
        
                if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
    
    private function removeCookies() {
                if (isset($_COOKIE[SESSION_NAME])) {
            setcookie(SESSION_NAME, '', time() - 3600, '/', COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
        }
        
                $cookiesToRemove = ['remember_me', 'user_preferences'];
        
        foreach ($cookiesToRemove as $cookieName) {
            if (isset($_COOKIE[$cookieName])) {
                setcookie($cookieName, '', time() - 3600, '/', COOKIE_DOMAIN, COOKIE_SECURE, COOKIE_HTTPONLY);
            }
        }
    }
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

$isApiRequest = strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

try {
    $logoutManager = new LogoutManager();
    $result = $logoutManager->logout();
    
        if ($isAjax || $isApiRequest) {
        header('Content-Type: application/json');
        
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
        echo json_encode($result);
        exit();
    }
    
        if ($result['success']) {
        header('Location: /auth/login?logout=true');
    } else {
        header('Location: /auth/login?error=' . urlencode($result['error']));
    }
    exit();
    
} catch (Exception $e) {
        error_log("Logout error: " . $e->getMessage());
    
    if ($isAjax || $isApiRequest) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
        ]);
    } else {
        header('Location: /auth/login?error=' . urlencode('Erreur de déconnexion'));
    }
    exit();
}
?>