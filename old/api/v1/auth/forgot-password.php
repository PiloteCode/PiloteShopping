<?php

define('API_ACCESS', true);
require_once '../config.php';

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

class ForgotPasswordManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function requestPasswordReset($email, $turnstileToken) {
        try {
                        if (empty($email)) {
                throw new Exception('Email requis');
            }
            
            if (!validateEmail($email)) {
                throw new Exception('Format d\'email invalide');
            }
            
                        if (!$this->verifyTurnstile($turnstileToken)) {
                logSecurityEvent(null, 'forgot_password_failed_captcha', ['email' => $email], 'failed');
                throw new Exception('Captcha invalide');
            }
            
                        if (!$this->checkRateLimit($email)) {
                logSecurityEvent(null, 'forgot_password_rate_limited', ['email' => $email], 'blocked');
                throw new Exception('Trop de demandes. Attendez 15 minutes avant de faire une nouvelle demande.');
            }
            
                        $user = $this->findUserByEmail($email);
            
                                    if (!$user) {
                logSecurityEvent(null, 'forgot_password_user_not_found', ['email' => $email], 'failed');
                
                                return [
                    'success' => true,
                    'message' => 'Si cet email existe dans notre base, vous recevrez un lien de réinitialisation.'
                ];
            }
            
                        if (!$user['is_active']) {
                logSecurityEvent($user['id'], 'forgot_password_inactive_account', ['email' => $email], 'failed');
                
                                return [
                    'success' => true,
                    'message' => 'Si cet email existe dans notre base, vous recevrez un lien de réinitialisation.'
                ];
            }
            
                        $this->cleanupOldTokens($user['id']);
            
                        $token = $this->generateResetToken($user['id']);
            
                        $this->sendResetEmail($user, $token);
            
                        logSecurityEvent($user['id'], 'forgot_password_success', [
                'email' => $email,
                'token_preview' => substr($token, 0, 8) . '...'
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Un email de réinitialisation a été envoyé si l\'adresse existe dans notre base.'
            ];
            
        } catch (Exception $e) {
            logSecurityEvent(null, 'forgot_password_error', [
                'email' => $email,
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
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
        
        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        
        if ($result === FALSE) {
            return false;
        }
        
        $response = json_decode($result, true);
        return isset($response['success']) && $response['success'] === true;
    }
    
    private function checkRateLimit($email) {
        $ip = getRealIP();
        
                $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM verification_tokens vt
            JOIN users u ON vt.user_id = u.id
            WHERE vt.type = 'password_reset' 
            AND vt.created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND (u.last_ip = ? OR u.email = ?)
        ");
        $stmt->execute([$ip, $email]);
        $attempts = $stmt->fetch();
        
        return $attempts['count'] < 5;
    }
    
    private function findUserByEmail($email) {
        $stmt = $this->db->prepare("
            SELECT id, email, first_name, last_name, is_active, is_verified 
            FROM users 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    private function cleanupOldTokens($userId) {
                $stmt = $this->db->prepare("
            DELETE FROM verification_tokens 
            WHERE user_id = ? AND type = 'password_reset'
        ");
        $stmt->execute([$userId]);
    }
    
    private function generateResetToken($userId) {
        $token = generateSecureToken(32);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);
        
        $stmt = $this->db->prepare("
            INSERT INTO verification_tokens (user_id, token, type, expires_at) 
            VALUES (?, ?, 'password_reset', ?)
        ");
        $stmt->execute([$userId, $token, $expiresAt]);
        
        return $token;
    }
    
    private function sendResetEmail($user, $token) {
        $resetUrl = SITE_URL . '/auth/reset-password?token=' . $token;
        
        $subject = 'Réinitialisation de votre mot de passe - ' . SITE_NAME;
        $message = $this->getResetEmailTemplate($user['first_name'], $resetUrl);
        
        $headers = [
            'From: ' . SITE_NAME . ' <' . SITE_EMAIL . '>',
            'Reply-To: ' . SITE_EMAIL,
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        $sent = mail($user['email'], $subject, $message, implode("\r\n", $headers));
        
        if (!$sent) {
            throw new Exception('Impossible d\'envoyer l\'email de réinitialisation');
        }
    }
    
    private function getResetEmailTemplate($firstName, $resetUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Réinitialisation mot de passe</title>
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #0A0A0A; color: #FFFFFF; margin: 0; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #1A1A1A; border-radius: 10px; padding: 40px;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <div style="display: inline-block; background: linear-gradient(135deg, #7B61FF, #00D4FF); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <span style="font-size: 24px; color: white;">🔐</span>
                    </div>
                    <h1 style="color: #7B61FF; margin: 0; font-size: 28px;">Réinitialiser votre mot de passe</h1>
                </div>
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                        Bonjour ' . htmlspecialchars($firstName) . ',<br><br>
                        Vous avez demandé la réinitialisation de votre mot de passe sur ' . SITE_NAME . '. 
                        Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe.
                    </p>
                    
                    <a href="' . $resetUrl . '" 
                       style="display: inline-block; background: linear-gradient(135deg, #7B61FF, #00D4FF); 
                              color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; 
                              font-weight: bold; font-size: 16px; margin: 20px 0;">
                        Réinitialiser mon mot de passe
                    </a>
                    
                    <p style="font-size: 14px; color: #CCCCCC; margin-top: 20px;">
                        Ce lien expire dans 1 heure pour votre sécurité.
                    </p>
                    
                    <div style="margin: 30px 0; padding: 20px; background-color: #2A2A2A; border-radius: 8px; border-left: 4px solid #FF6B6B;">
                        <p style="font-size: 14px; color: #FF6B6B; margin: 0; font-weight: bold;">
                            ⚠️ Important
                        </p>
                        <p style="font-size: 14px; color: #CCCCCC; margin: 10px 0 0 0;">
                            Si vous n\'avez pas demandé cette réinitialisation, ignorez cet email. 
                            Votre mot de passe restera inchangé.
                        </p>
                    </div>
                    
                    <p style="font-size: 12px; color: #999999; margin-top: 30px; padding: 15px; background-color: #2A2A2A; border-radius: 5px;">
                        Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :<br>
                        <span style="word-break: break-all;">' . $resetUrl . '</span>
                    </p>
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
    
    $email = trim($input['email'] ?? '');
    $turnstileToken = $input['turnstile_token'] ?? '';
    
        $forgotPasswordManager = new ForgotPasswordManager();
    
        $result = $forgotPasswordManager->requestPasswordReset($email, $turnstileToken);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logSecurityEvent(null, 'forgot_password_system_error', [
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