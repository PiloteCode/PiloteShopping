<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once __DIR__ . '/../../../includes/CsrfProtection.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

\CsrfProtection::requireValidToken();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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

class PasswordManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
                        $this->validatePasswords($currentPassword, $newPassword);
            
                        $stmt = $this->db->prepare("
                SELECT password_hash, password_salt, email 
                FROM users 
                WHERE id = ? AND is_active = TRUE
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Utilisateur non trouvé ou inactif');
            }
            
                        if (!verifyPassword($currentPassword, $user['password_hash'], $user['password_salt'])) {
                logSecurityEvent($userId, 'password_change_failed_current', [
                    'ip' => getRealIP()
                ], 'failed');
                throw new Exception('Mot de passe actuel incorrect');
            }
            
                        $passwordData = hashPassword($newPassword);
            
                        $stmt = $this->db->prepare("
                UPDATE users 
                SET password_hash = ?, password_salt = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$passwordData['hash'], $passwordData['salt'], $userId]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('Échec de la mise à jour du mot de passe');
            }
            
            $currentSessionId = $_SESSION['session_id'] ?? '';
            $stmt = $this->db->prepare("
                DELETE FROM user_sessions
                WHERE user_id = ? AND id != ?
            ");
            $stmt->execute([$userId, $currentSessionId]);
            
                        $stmt = $this->db->prepare("
                UPDATE remember_tokens 
                SET is_revoked = TRUE 
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
            
                        $this->sendPasswordChangeNotification($user['email']);
            
                        logSecurityEvent($userId, 'password_changed', [
                'sessions_invalidated' => $stmt->rowCount(),
                'ip' => getRealIP()
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Mot de passe modifié avec succès. Vos autres sessions ont été fermées.'
            ];
            
        } catch (Exception $e) {
            logSecurityEvent($userId, 'password_change_error', [
                'error' => $e->getMessage(),
                'ip' => getRealIP()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function validatePasswords($currentPassword, $newPassword) {
                if (empty($currentPassword)) {
            throw new Exception('Mot de passe actuel requis');
        }
        
        if (empty($newPassword)) {
            throw new Exception('Nouveau mot de passe requis');
        }
        
                if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            throw new Exception('Le nouveau mot de passe doit contenir au moins ' . PASSWORD_MIN_LENGTH . ' caractères');
        }
        
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', $newPassword)) {
            throw new Exception('Le nouveau mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre et un caractère spécial');
        }
        
                if ($currentPassword === $newPassword) {
            throw new Exception('Le nouveau mot de passe doit être différent de l\'ancien');
        }
    }
    
    private function sendPasswordChangeNotification($email) {
        try {
            $subject = 'Mot de passe modifié - ' . SITE_NAME;
            $message = $this->getPasswordChangeEmailTemplate();
            
            $headers = [
                'From: ' . SITE_NAME . ' <' . SITE_EMAIL . '>',
                'Reply-To: ' . SITE_EMAIL,
                'X-Mailer: PHP/' . phpversion(),
                'Content-Type: text/html; charset=UTF-8'
            ];
            
            mail($email, $subject, $message, implode("\r\n", $headers));
            
        } catch (Exception $e) {
                        error_log("Failed to send password change notification: " . $e->getMessage());
        }
    }
    
    private function getPasswordChangeEmailTemplate() {
        $currentTime = date('d/m/Y à H:i');
        $userIP = getRealIP();
        
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Mot de passe modifié</title>
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #0A0A0A; color: #FFFFFF; margin: 0; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #1A1A1A; border-radius: 10px; padding: 40px;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <div style="display: inline-block; background: linear-gradient(135deg, #7B61FF, #00D4FF); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <span style="font-size: 24px; color: white;">🔐</span>
                    </div>
                    <h1 style="color: #7B61FF; margin: 0; font-size: 28px;">Mot de passe modifié</h1>
                </div>
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                        Le mot de passe de votre compte ' . SITE_NAME . ' a été modifié avec succès.
                    </p>
                    
                    <div style="background-color: #2A2A2A; border-radius: 8px; padding: 20px; margin: 20px 0; text-align: left;">
                        <h3 style="color: #7B61FF; margin-top: 0;">Détails de la modification</h3>
                        <p style="margin: 10px 0; color: #CCCCCC;">
                            <strong>Date :</strong> ' . $currentTime . '<br>
                            <strong>Adresse IP :</strong> ' . $userIP . '
                        </p>
                    </div>
                    
                    <div style="margin: 30px 0; padding: 20px; background-color: #2A2A2A; border-radius: 8px; border-left: 4px solid #FF6B6B;">
                        <p style="font-size: 14px; color: #FF6B6B; margin: 0; font-weight: bold;">
                            ⚠️ Vous n\'êtes pas à l\'origine de cette modification ?
                        </p>
                        <p style="font-size: 14px; color: #CCCCCC; margin: 10px 0 0 0;">
                            Contactez immédiatement notre support à ' . ADMIN_EMAIL . ' si vous n\'avez pas effectué cette modification.
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
        $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide');
    }
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    
        $passwordManager = new PasswordManager();
    
        $result = $passwordManager->changePassword($userId, $currentPassword, $newPassword);
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    logSecurityEvent($userId ?? null, 'password_api_error', [
        'error' => $e->getMessage()
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
?>