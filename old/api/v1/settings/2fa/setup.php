<?php

define('API_ACCESS', true);
require_once '../../config.php';
require_once __DIR__ . '/../../../../includes/CsrfProtection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


\CsrfProtection::requireValidToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit();
}

session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

$userId = intval($_SESSION['user_id']);

class TwoFactorSetup {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function generateSecret($userId) {
        try {
                        $stmt = $this->db->prepare("
                SELECT is_enabled, secret 
                FROM users_2fa 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $existing = $stmt->fetch();
            
            if ($existing && $existing['is_enabled']) {
                throw new Exception('2FA est déjà activé pour ce compte');
            }
            
                        $secret = $this->generateRandomSecret();
            
                        $stmt = $this->db->prepare("
                SELECT email, first_name, last_name 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Utilisateur non trouvé');
            }
            
                        if ($existing) {
                $stmt = $this->db->prepare("
                    UPDATE users_2fa 
                    SET secret = ?, is_enabled = FALSE, updated_at = NOW() 
                    WHERE user_id = ?
                ");
                $stmt->execute([$secret, $userId]);
            } else {
                $stmt = $this->db->prepare("
                    INSERT INTO users_2fa (user_id, secret, is_enabled) 
                    VALUES (?, ?, FALSE)
                ");
                $stmt->execute([$userId, $secret]);
            }
            
                        $qrUrl = $this->generateQRUrl($secret, $user['email']);
            
                        $backupCodes = $this->generateBackupCodes($userId);
            
            logSecurityEvent($userId, '2fa_setup_initiated', [], 'success');
            
            return [
                'success' => true,
                'secret' => $secret,
                'qr_url' => $qrUrl,
                'backup_codes' => $backupCodes
            ];
            
        } catch (Exception $e) {
            logSecurityEvent($userId, '2fa_setup_failed', [
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function generateRandomSecret() {
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        
        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        
        return $secret;
    }
    
    private function generateQRUrl($secret, $email) {
        $issuer = urlencode(SITE_NAME);
        $account = urlencode($email);
        
        return "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}";
    }
    
    private function generateBackupCodes($userId) {
        try {
                        $stmt = $this->db->prepare("
                DELETE FROM users_2fa_backup 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            $backupCodes = [];
            
                        for ($i = 0; $i < 10; $i++) {
                $code = $this->generateBackupCode();
                $backupCodes[] = $code;
                
                                $hashedCode = password_hash($code, PASSWORD_BCRYPT, ['cost' => 12]);
                
                $stmt = $this->db->prepare("
                    INSERT INTO users_2fa_backup (user_id, code_hash, is_used) 
                    VALUES (?, ?, FALSE)
                ");
                $stmt->execute([$userId, $hashedCode]);
            }
            
            return $backupCodes;
            
        } catch (Exception $e) {
            error_log("Failed to generate backup codes: " . $e->getMessage());
            return [];
        }
    }
    
    private function generateBackupCode() {
                $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= random_int(0, 9);
        }
        return $code;
    }
}

try {
    $twoFactorSetup = new TwoFactorSetup();
    $result = $twoFactorSetup->generateSecret($userId);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    logSecurityEvent($userId ?? null, '2fa_setup_api_error', [
        'error' => $e->getMessage()
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
?>