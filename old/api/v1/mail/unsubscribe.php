<?php

define('API_ACCESS', true);
require_once '../config.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

class UnsubscribeManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
        public function processUnsubscribe($email, $source = 'manual', $token = null) {
        try {
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Adresse email invalide');
            }
            
                        if ($this->isAlreadyUnsubscribed($email)) {
                return [
                    'success' => true,
                    'message' => 'Cette adresse est déjà désabonnée',
                    'already_unsubscribed' => true
                ];
            }
            
                        $emailId = null;
            if ($token && $source === 'email') {
                $emailId = $this->validateUnsubscribeToken($token, $email);
                if (!$emailId) {
                    throw new Exception('Token de désabonnement invalide ou expiré');
                }
            }
            
                        $this->addToSuppressionList($email, 'unsubscribe', $source, $emailId);
            
                        $this->deactivateEmailSubscriptions($email);
            
                        logSecurityEvent(
                $this->getUserIdFromEmail($email),
                'email_unsubscribed',
                [
                    'email' => $email,
                    'source' => $source,
                    'ip' => getRealIP(),
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
                ],
                'success'
            );
            
                        $this->sendUnsubscribeConfirmation($email);
            
            return [
                'success' => true,
                'message' => 'Désabonnement effectué avec succès'
            ];
            
        } catch (Exception $e) {
            error_log("Unsubscribe error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        public function processOneClickUnsubscribe($email, $messageId) {
        try {
                        $stmt = $this->db->prepare("
                SELECT id, to_email FROM email_sends 
                WHERE message_id = ? AND to_email = ?
            ");
            $stmt->execute([$messageId, $email]);
            $emailData = $stmt->fetch();
            
            if (!$emailData) {
                throw new Exception('Email ou message-id invalide');
            }
            
            return $this->processUnsubscribe($email, 'one_click', $messageId);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        private function isAlreadyUnsubscribed($email) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM email_suppressions 
            WHERE email = ? AND type = 'unsubscribe'
        ");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }
    
        private function validateUnsubscribeToken($token, $email) {
                $stmt = $this->db->prepare("
            SELECT id FROM email_sends 
            WHERE tracking_token = ? AND to_email = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$token, $email]);
        return $stmt->fetchColumn();
    }
    
        private function addToSuppressionList($email, $type, $reason, $sourceEmailId = null) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO email_suppressions 
            (email, type, reason, source_email_id, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $email,
            $type,
            "Unsubscribed via $reason",
            $sourceEmailId,
            getRealIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
        private function deactivateEmailSubscriptions($email) {
                $stmt = $this->db->prepare("
            UPDATE users 
            SET email_marketing_consent = 0, email_notifications = 0 
            WHERE email = ?
        ");
        $stmt->execute([$email]);
        
                $stmt = $this->db->prepare("
            INSERT INTO user_email_preferences (email, marketing, notifications, transactional)
            VALUES (?, 0, 0, 1) 
            ON DUPLICATE KEY UPDATE 
            marketing = 0, notifications = 0, updated_at = NOW()
        ");
        $stmt->execute([$email]);
    }
    
        private function sendUnsubscribeConfirmation($email) {
                $subject = 'Confirmation de désabonnement - ' . SITE_NAME;
        $message = $this->getUnsubscribeConfirmationTemplate($email);
        
        $headers = [
            'From: ' . SITE_NAME . ' <' . SITE_EMAIL . '>',
            'Reply-To: ' . SITE_EMAIL,
            'Content-Type: text/html; charset=UTF-8',
            'List-Unsubscribe: <mailto:unsubscribe@' . parse_url(SITE_URL, PHP_URL_HOST) . '>',
            'X-Auto-Response-Suppress: All'
        ];
        
                @mail($email, $subject, $message, implode("\r\n", $headers));
    }
    
        private function getUnsubscribeConfirmationTemplate($email) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Désabonnement confirmé</title>
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #f4f4f4; margin: 0; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 10px; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <div style="width: 64px; height: 64px; background: linear-gradient(135deg, #10B981, #059669); border-radius: 50%; margin: 0 auto 20px; display: flex; align-items: center; justify-content: center;">
                        <span style="color: white; font-size: 24px;">✓</span>
                    </div>
                    <h1 style="color: #1F2937; margin: 0; font-size: 24px;">Désabonnement confirmé</h1>
                </div>
                
                <div style="margin-bottom: 30px; color: #4B5563; line-height: 1.6;">
                    <p>Bonjour,</p>
                    
                    <p>Nous confirmons que l\'adresse email <strong>' . htmlspecialchars($email) . '</strong> a été retirée de nos listes de diffusion.</p>
                    
                    <p>Vous ne recevrez plus d\'emails marketing de notre part. Cependant, vous pourriez encore recevoir :</p>
                    
                    <ul style="margin: 15px 0; padding-left: 20px;">
                        <li>Des emails transactionnels (confirmations de commande, factures, etc.)</li>
                        <li>Des communications importantes concernant votre compte</li>
                        <li>Des notifications de sécurité</li>
                    </ul>
                    
                    <div style="background-color: #F3F4F6; padding: 15px; border-radius: 8px; margin: 20px 0;">
                        <p style="margin: 0; font-size: 14px; color: #6B7280;">
                            <strong>Changé d\'avis ?</strong> Vous pouvez vous réabonner à tout moment depuis votre espace client ou en nous contactant directement.
                        </p>
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 40px;">
                    <a href="' . SITE_URL . '" style="display: inline-block; background: linear-gradient(135deg, #3B82F6, #1D4ED8); color: white; text-decoration: none; padding: 12px 24px; border-radius: 6px; font-weight: bold;">
                        Retourner sur ' . SITE_NAME . '
                    </a>
                </div>
                
                <div style="border-top: 1px solid #E5E7EB; padding-top: 20px; margin-top: 40px; text-align: center;">
                    <p style="margin: 0; font-size: 12px; color: #9CA3AF;">
                        Cet email de confirmation ne contient aucun tracking.<br>
                        ' . SITE_NAME . ' - ' . SITE_URL . '
                    </p>
                </div>
                
            </div>
        </body>
        </html>';
    }
    
        private function getUserIdFromEmail($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() ?: null;
    }
    
        public function getUnsubscribeStats($dateFrom = null, $dateTo = null) {
        $whereClause = "WHERE type = 'unsubscribe'";
        $params = [];
        
        if ($dateFrom) {
            $whereClause .= " AND created_at >= ?";
            $params[] = $dateFrom;
        }
        
        if ($dateTo) {
            $whereClause .= " AND created_at <= ?";
            $params[] = $dateTo;
        }
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_unsubscribes,
                COUNT(CASE WHEN reason LIKE '%manual%' THEN 1 END) as manual,
                COUNT(CASE WHEN reason LIKE '%one_click%' THEN 1 END) as one_click,
                COUNT(CASE WHEN reason LIKE '%email%' THEN 1 END) as from_email,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as last_24h,
                COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as last_7d
            FROM email_suppressions 
            {$whereClause}
        ");
        
        $stmt->execute($params);
        return $stmt->fetch();
    }
    
        public function resubscribe($email, $confirmationToken = null) {
        try {
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Adresse email invalide');
            }
            
                        $stmt = $this->db->prepare("
                DELETE FROM email_suppressions 
                WHERE email = ? AND type = 'unsubscribe'
            ");
            $stmt->execute([$email]);
            
                        $stmt = $this->db->prepare("
                UPDATE users 
                SET email_marketing_consent = 1, email_notifications = 1 
                WHERE email = ?
            ");
            $stmt->execute([$email]);
            
                        logSecurityEvent(
                $this->getUserIdFromEmail($email),
                'email_resubscribed',
                ['email' => $email],
                'success'
            );
            
            return ['success' => true, 'message' => 'Réabonnement effectué avec succès'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

require_once __DIR__ . '/../../../includes/CsrfProtection.php';

$unsubscribeManager = new UnsubscribeManager();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $isOneClick = isset($_SERVER['HTTP_LIST_UNSUBSCRIBE_POST']);
    if (!$isOneClick) {
        CsrfProtection::requireValidToken();
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $email = $input['email'] ?? '';
    $token = $input['token'] ?? '';
    if ($isOneClick) {
        $messageId = $input['message_id'] ?? '';
        $result = $unsubscribeManager->processOneClickUnsubscribe($email, $messageId);
    } else {
        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Token de désabonnement requis']);
            exit;
        }
        $result = $unsubscribeManager->processUnsubscribe($email, 'email', $token);
    }
    echo json_encode($result);
} else {
    $email = $_GET['email'] ?? '';
    $token = $_GET['token'] ?? '';
    $success = false;
    $message = '';
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Désabonnement - <?= SITE_NAME ?></title>
        <style>
            body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .success { color: #10B981; background: #ECFDF5; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .error { color: #EF4444; background: #FEF2F2; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .btn { display: inline-block; background: #3B82F6; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
            .btn-secondary { background: #6B7280; }
            input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin: 10px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Gestion de votre abonnement</h1>

<?php if ($success): ?>
                <div class="success">
                    ✓ <?= htmlspecialchars($message) ?>
                </div>
                <p>Vous pouvez fermer cette page. Si vous changez d'avis, vous pourrez vous réabonner depuis votre espace client.</p>
<?php elseif (!empty($message)): ?>
                <div class="error">
                    ❌ <?= htmlspecialchars($message) ?>
                </div>
<?php endif; ?>

<?php if (!$success): ?>
<?php if (empty($token)): ?>
                    <div class="error">
                        Lien de désabonnement invalide ou expiré. Pour vous désabonner, utilisez le lien fourni dans votre dernier email reçu, ou contactez le support.
                    </div>
<?php else: ?>
                    <form method="POST" action="">
                        <p>Pour vous désabonner de nos communications, veuillez confirmer votre adresse email :</p>
                        <input type="email" name="email" placeholder="Votre adresse email" value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
<?= CsrfProtection::field() ?>
                        <br>
                        <button type="submit" class="btn">Confirmer le désabonnement</button>
                    </form>
<?php endif; ?>
<?php endif; ?>

            <div style="margin-top: 40px; text-align: center;">
                <a href="<?= SITE_URL ?>" class="btn btn-secondary">Retour à l'accueil</a>
            </div>
        </div>
    </body>
    </html>
<?php
}
?>