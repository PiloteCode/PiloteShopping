<?php

define('API_ACCESS', true);
require_once '../../config.php';
require_once __DIR__ . '/../../../../includes/CsrfProtection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


\CsrfProtection::requireValidToken();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

class TwoFactorDisable {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function disable2FA($userId, $password = null, $totpCode = null, $backupCode = null) {
        try {
                        $stmt = $this->db->prepare("
                SELECT secret, is_enabled 
                FROM users_2fa 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            $twofa = $stmt->fetch();
            
            if (!$twofa || !$twofa['is_enabled']) {
                throw new Exception('2FA n\'est pas activé pour ce compte');
            }
            
                        $verified = false;
            
                        if (!empty($password)) {
                $verified = $this->verifyPassword($userId, $password);
                if (!$verified) {
                    throw new Exception('Mot de passe incorrect');
                }
            }
            
                        if (!$verified && !empty($totpCode)) {
                $verified = $this->verifyTOTP($twofa['secret'], $totpCode);
                if (!$verified) {
                    throw new Exception('Code TOTP incorrect');
                }
            }
            
                        if (!$verified && !empty($backupCode)) {
                $verified = $this->verifyBackupCode($userId, $backupCode);
                if (!$verified) {
                    throw new Exception('Code de récupération incorrect ou déjà utilisé');
                }
            }
            
            if (!$verified) {
                throw new Exception('Authentification requise pour désactiver 2FA');
            }
            
                        $this->db->beginTransaction();
            
                        $stmt = $this->db->prepare("
                UPDATE users_2fa 
                SET is_enabled = FALSE, disabled_at = NOW(), updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Impossible de désactiver la 2FA');
            }
            
                        $stmt = $this->db->prepare("
                DELETE FROM users_2fa_backup 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
                        $this->db->commit();
            
                        $this->sendDisableNotification($userId);
            
            logSecurityEvent($userId, '2fa_disabled', [
                'ip' => getRealIP()
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Authentification à deux facteurs désactivée'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            logSecurityEvent($userId, '2fa_disable_failed', [
                'error' => $e->getMessage(),
                'ip' => getRealIP()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function verifyPassword($userId, $password) {
        $stmt = $this->db->prepare("
            SELECT password_hash, password_salt 
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            return false;
        }
        
        return verifyPassword($password, $user['password_hash'], $user['password_salt']);
    }
    
    private function verifyTOTP($secret, $code) {
                $timeSlice = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTOTP($secret, $timeSlice + $i);
            if ($calculatedCode === $code) {
                return true;
            }
        }
        
        return false;
    }
    
    private function generateTOTP($secret, $timeSlice) {
                $key = $this->base32Decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hash = hash_hmac('sha1', $time, $key, true);
        $offset = ord($hash[19]) & 0xf;
        
        $code = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return sprintf("%06d", $code);
    }
    
    private function base32Decode($data) {
                $data = strtoupper($data);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $decoded = '';
        
        for ($i = 0; $i < strlen($data); $i += 8) {
            $chunk = substr($data, $i, 8);
            $chunk = str_pad($chunk, 8, '=', STR_PAD_RIGHT);
            
            $binary = '';
            for ($j = 0; $j < strlen($chunk); $j++) {
                if ($chunk[$j] !== '=') {
                    $pos = strpos($chars, $chunk[$j]);
                    if ($pos === false) continue;
                    $binary .= sprintf('%05b', $pos);
                }
            }
            
            for ($k = 0; $k < strlen($binary); $k += 8) {
                if ($k + 8 <= strlen($binary)) {
                    $decoded .= chr(bindec(substr($binary, $k, 8)));
                }
            }
        }
        
        return $decoded;
    }
    
    private function verifyBackupCode($userId, $code) {
                $stmt = $this->db->prepare("
            SELECT id, code_hash 
            FROM users_2fa_backup 
            WHERE user_id = ? AND is_used = FALSE
        ");
        $stmt->execute([$userId]);
        $backupCodes = $stmt->fetchAll();
        
        foreach ($backupCodes as $backupCode) {
            if (password_verify($code, $backupCode['code_hash'])) {
                                $stmt = $this->db->prepare("
                    UPDATE users_2fa_backup 
                    SET is_used = TRUE, used_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$backupCode['id']]);
                
                return true;
            }
        }
        
        return false;
    }
    
    private function sendDisableNotification($userId) {
        try {
                        $stmt = $this->db->prepare("SELECT email, first_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) return;
            
            $subject = 'Authentification à deux facteurs désactivée - ' . SITE_NAME;
            $message = $this->getDisableEmailTemplate($user['first_name']);
            
            $headers = [
                'From: ' . SITE_NAME . ' <' . SITE_EMAIL . '>',
                'Reply-To: ' . SITE_EMAIL,
                'X-Mailer: PHP/' . phpversion(),
                'Content-Type: text/html; charset=UTF-8'
            ];
            
            mail($user['email'], $subject, $message, implode("\r\n", $headers));
            
        } catch (Exception $e) {
            error_log("Failed to send 2FA disable notification: " . $e->getMessage());
        }
    }
    
    private function getDisableEmailTemplate($firstName) {
        $currentTime = date('d/m/Y à H:i');
        $userIP = getRealIP();
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>2FA Désactivée</title>
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #0A0A0A; color: #FFFFFF; margin: 0; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #1A1A1A; border-radius: 10px; padding: 40px;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <div style="display: inline-block; background: linear-gradient(135deg, #F59E0B, #D97706); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <span style="font-size: 24px; color: white;">⚠️</span>
                    </div>
                    <h1 style="color: #F59E0B; margin: 0; font-size: 28px;">2FA Désactivée</h1>
                </div>
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                        Bonjour ' . htmlspecialchars($firstName) . ',<br><br>
                        L\'authentification à deux facteurs a été désactivée sur votre compte ' . SITE_NAME . '. 
                        Votre compte est maintenant moins protégé.
                    </p>
                    
                    <div style="background-color: #2A2A2A; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left;">
                        <h3 style="color: #F59E0B; margin-top: 0;">Détails de la désactivation</h3>
                        <p style="margin: 10px 0; color: #CCCCCC;">
                            <strong>Date :</strong> ' . $currentTime . '<br>
                            <strong>Adresse IP :</strong> ' . $userIP . '
                        </p>
                    </div>
                    
                    <div style="background-color: #1E40AF; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <p style="font-size: 14px; color: #BFDBFE; margin: 0; font-weight: bold;">
                            💡 Recommandation de sécurité
                        </p>
                        <p style="font-size: 14px; color: #BFDBFE; margin: 10px 0 0 0; text-align: left;">
                            Nous recommandons fortement de réactiver l\'authentification à deux facteurs 
                            pour protéger votre compte contre les accès non autorisés.
                        </p>
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="' . SITE_URL . '/settings?tab=security" 
                               style="display: inline-block; background: #3B82F6; color: white; 
                                      text-decoration: none; padding: 10px 20px; border-radius: 5px; font-weight: bold;">
                                Réactiver la 2FA
                            </a>
                        </div>
                    </div>
                    
                    <div style="margin: 30px 0; padding: 20px; background-color: #2A2A2A; border-radius: 8px; border-left: 4px solid #FF6B6B;">
                        <p style="font-size: 14px; color: #FF6B6B; margin: 0; font-weight: bold;">
                            ⚠️ Vous n\'êtes pas à l\'origine de cette désactivation ?
                        </p>
                        <p style="font-size: 14px; color: #CCCCCC; margin: 10px 0 0 0;">
                            Contactez immédiatement notre support à ' . ADMIN_EMAIL . ' si vous n\'avez pas désactivé la 2FA.
                        </p>
                    </div>
                </div>
                
                <div style="border-top: 1px solid #2A2A2A; padding-top: 20px; margin-top: 30px;">
                    <p style="font-size: 14px; color: #999999; text-align: center; margin: 0;">
                        Cet email a été envoyé par ' . SITE_NAME . '<br>
                        <a href="' . SITE_URL . '" style="color: #7B61FF;">' . SITE_URL . '</a>
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }
}

try {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $password = $input['password'] ?? null;
    $totpCode = $input['totp_code'] ?? null;
    $backupCode = $input['backup_code'] ?? null;
    
    $twoFactorDisable = new TwoFactorDisable();
    $result = $twoFactorDisable->disable2FA($userId, $password, $totpCode, $backupCode);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    logSecurityEvent($userId ?? null, '2fa_disable_api_error', [
        'error' => $e->getMessage()
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
?>