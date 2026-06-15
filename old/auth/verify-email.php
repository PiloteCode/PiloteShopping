<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';

class EmailVerificationManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function verifyEmail($token) {
        try {
            if (empty($token)) {
                throw new Exception('Token de vérification manquant');
            }
            
                        $stmt = $this->db->prepare("
                SELECT vt.*, u.email, u.first_name 
                FROM verification_tokens vt
                JOIN users u ON vt.user_id = u.id
                WHERE vt.token = ? 
                AND vt.type = 'email_verification' 
                AND vt.used_at IS NULL 
                AND vt.expires_at > NOW()
            ");
            $stmt->execute([$token]);
            $tokenData = $stmt->fetch();
            
            if (!$tokenData) {
                throw new Exception('Token de vérification invalide ou expiré');
            }
            
            $this->db->beginTransaction();
            
            try {
                                $stmt = $this->db->prepare("
                    UPDATE users 
                    SET is_verified = 1, email_verified_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$tokenData['user_id']]);
                
                                $stmt = $this->db->prepare("
                    UPDATE verification_tokens 
                    SET used_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$tokenData['id']]);
                
                $this->db->commit();
                
                                logSecurityEvent($tokenData['user_id'], 'email_verified', [
                    'email' => $tokenData['email'],
                    'token' => substr($token, 0, 8) . '...'
                ], 'success');
                
                return [
                    'success' => true,
                    'message' => 'Email vérifié avec succès !',
                    'user_name' => $tokenData['first_name']
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            logSecurityEvent(null, 'email_verification_failed', [
                'token' => substr($token, 0, 8) . '...',
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function resendVerification($email) {
        try {
            if (empty($email) || !validateEmail($email)) {
                throw new Exception('Email invalide');
            }
            
                        $stmt = $this->db->prepare("
                SELECT id, email, first_name, is_verified 
                FROM users 
                WHERE email = ? AND is_active = 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            $genericResponse = function() {
                return [
                    'success' => true,
                    'message' => 'Si un compte non vérifié existe pour cet email, un nouveau lien de vérification a été envoyé.'
                ];
            };
            if (!$user) {
                return $genericResponse();
            }
            if ($user['is_verified']) {
                return $genericResponse();
            }
            
                        $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM verification_tokens 
                WHERE user_id = ? 
                AND type = 'email_verification' 
                AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$user['id']]);
            $recentTokens = $stmt->fetch();
            
            if ($recentTokens['count'] >= 3) {
                throw new Exception('Trop de demandes. Attendez une heure avant de renvoyer un email.');
            }
            
                        $stmt = $this->db->prepare("
                DELETE FROM verification_tokens 
                WHERE user_id = ? 
                AND type = 'email_verification' 
                AND used_at IS NULL
            ");
            $stmt->execute([$user['id']]);
            
                        $token = generateSecureToken(32);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400);             
            $stmt = $this->db->prepare("
                INSERT INTO verification_tokens (user_id, token, type, expires_at) 
                VALUES (?, ?, 'email_verification', ?)
            ");
            $stmt->execute([$user['id'], $token, $expiresAt]);
            
                        $this->sendVerificationEmail($user, $token);
            
            logSecurityEvent($user['id'], 'email_verification_resent', [
                'email' => $email
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Email de vérification renvoyé avec succès'
            ];
            
        } catch (Exception $e) {
            logSecurityEvent(null, 'email_verification_resend_failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function sendVerificationEmail($user, $token) {
        $verificationUrl = SITE_URL . '/auth/verify-email?token=' . $token;
        
        $subject = 'Vérifiez votre adresse email - ' . SITE_NAME;
        $message = $this->getVerificationEmailTemplate($user['first_name'], $verificationUrl);
        
        $headers = [
            'From: ' . SITE_NAME . ' <' . SITE_EMAIL . '>',
            'Reply-To: ' . SITE_EMAIL,
            'X-Mailer: PHP/' . phpversion(),
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        $sent = mail($user['email'], $subject, $message, implode("\r\n", $headers));
        
        if (!$sent) {
            throw new Exception('Impossible d\'envoyer l\'email de vérification');
        }
    }
    
    private function getVerificationEmailTemplate($firstName, $verificationUrl) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Vérification Email</title>
        </head>
        <body style="font-family: Arial, sans-serif; background-color: #0A0A0A; color: #FFFFFF; margin: 0; padding: 20px;">
            <div style="max-width: 600px; margin: 0 auto; background-color: #1A1A1A; border-radius: 10px; padding: 40px;">
                <div style="text-align: center; margin-bottom: 30px;">
                    <div style="display: inline-block; background: linear-gradient(135deg, #7B61FF, #00D4FF); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                        <span style="font-size: 24px; color: white;">✈️</span>
                    </div>
                    <h1 style="color: #7B61FF; margin: 0; font-size: 28px;">Vérifiez votre email</h1>
                </div>
                
                <div style="text-align: center; margin-bottom: 30px;">
                    <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                        Bonjour ' . htmlspecialchars($firstName) . ',<br><br>
                        Pour finaliser votre inscription sur ' . SITE_NAME . ' et activer votre compte, 
                        veuillez vérifier votre adresse email en cliquant sur le bouton ci-dessous.
                    </p>
                    
                    <a href="' . $verificationUrl . '" 
                       style="display: inline-block; background: linear-gradient(135deg, #7B61FF, #00D4FF); 
                              color: white; text-decoration: none; padding: 15px 30px; border-radius: 8px; 
                              font-weight: bold; font-size: 16px; margin: 20px 0;">
                        Vérifier mon email
                    </a>
                    
                    <p style="font-size: 14px; color: #CCCCCC; margin-top: 20px;">
                        Ce lien expire dans 24 heures.
                    </p>
                    
                    <p style="font-size: 12px; color: #999999; margin-top: 30px; padding: 15px; background-color: #2A2A2A; border-radius: 5px;">
                        Si le bouton ne fonctionne pas, copiez et collez ce lien dans votre navigateur :<br>
                        <span style="word-break: break-all;">' . $verificationUrl . '</span>
                    </p>
                </div>
                
                <div style="border-top: 1px solid #2A2A2A; padding-top: 20px; margin-top: 30px;">
                    <p style="font-size: 14px; color: #999999; text-align: center; margin: 0;">
                        Si vous n\'avez pas créé de compte sur ' . SITE_NAME . ', ignorez cet email.<br>
                        <a href="' . SITE_URL . '" style="color: #7B61FF;">' . SITE_URL . '</a>
                    </p>
                </div>
            </div>
        </body>
        </html>';
    }
}

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? 'verify';
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
    $email = $input['email'] ?? '';
    
    $manager = new EmailVerificationManager();
    $result = $manager->resendVerification($email);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit();
} else if (!empty($token)) {
        $manager = new EmailVerificationManager();
    $result = $manager->verifyEmail($token);
}

$seo = [
    'title' => 'Vérification d\'email',
    'description' => 'Vérifiez votre adresse email pour activer votre compte Pilote Shopping.',
    'keywords' => 'vérification email, activation compte, pilote shopping',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<section class="min-h-screen flex items-center justify-center bg-gradient-to-br from-gray-900 via-gray-800 to-black relative overflow-hidden">

    <div class="absolute inset-0 opacity-20">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-purple-500 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500 rounded-full blur-3xl animate-pulse delay-2000"></div>
    </div>

    <div class="relative z-10 w-full max-w-md px-6">

<?php if ($result): ?>

<?php if ($result['success']): ?>

                <div class="text-center" data-aos="fade-down">
                    <div class="inline-block p-6 bg-gradient-to-r from-green-500 to-blue-500 rounded-2xl mb-6 glow">
                        <i class="fas fa-check-circle text-white text-4xl"></i>
                    </div>
                    <h1 class="text-4xl font-bold text-white mb-4">Email vérifié !</h1>
                    <p class="text-gray-400 text-lg mb-8">
                        Félicitations <?= isset($result['user_name']) ? htmlspecialchars($result['user_name']) : '' ?> ! 
                        Votre email a été vérifié avec succès.
                    </p>

                    <script>
                        confetti({
                            particleCount: 100,
                            spread: 70,
                            origin: { y: 0.6 },
                            colors: ['#10B981', '#7B61FF', '#00D4FF']
                        });
                    </script>

                    <div class="space-y-4">
                        <a href="/auth/login?verified=true" 
                           class="block w-full py-3 px-6 bg-gradient-to-r from-purple-500 to-blue-500 text-white font-semibold rounded-lg transition-all duration-300 hover:scale-105 glow">
                            Se connecter maintenant
                        </a>
                        <a href="/" 
                           class="block w-full py-3 px-6 bg-gray-700 text-white font-semibold rounded-lg transition-all duration-300 hover:bg-gray-600">
                            Retour à l'accueil
                        </a>
                    </div>
                </div>

<?php else: ?>

                <div class="text-center" data-aos="fade-down">
                    <div class="inline-block p-6 bg-gradient-to-r from-red-500 to-orange-500 rounded-2xl mb-6">
                        <i class="fas fa-exclamation-triangle text-white text-4xl"></i>
                    </div>
                    <h1 class="text-4xl font-bold text-white mb-4">Erreur de vérification</h1>
                    <p class="text-gray-400 text-lg mb-8">
<?= htmlspecialchars($result['error']) ?>
                    </p>

                    <div class="bg-gray-900 bg-opacity-80 backdrop-blur-lg rounded-2xl border border-gray-700 p-6 shadow-2xl">
                        <h3 class="text-xl font-semibold text-white mb-4">Renvoyer l'email de vérification</h3>

                        <div id="resend-alert" class="hidden mb-4">
                            <div id="resend-alert-content" class="p-3 rounded-lg text-sm"></div>
                        </div>

                        <form id="resendForm" class="space-y-4">
                            <div>
                                <input type="email" 
                                       id="resend-email" 
                                       name="email" 
                                       required
                                       class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 transition-all duration-300"
                                       placeholder="votre@email.com">
                            </div>

                            <button type="submit" 
                                    id="resendBtn"
                                    class="w-full py-3 px-6 bg-gradient-to-r from-purple-500 to-blue-500 text-white font-semibold rounded-lg transition-all duration-300 hover:scale-105 glow disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="resendBtnText">Renvoyer l'email</span>
                                <i id="resendBtnSpinner" class="fas fa-spinner fa-spin hidden ml-2"></i>
                            </button>
                        </form>
                    </div>

                    <div class="mt-8">
                        <a href="/auth/login" 
                           class="text-purple-400 hover:text-purple-300 transition-colors duration-300">
                            Retour à la connexion
                        </a>
                    </div>
                </div>
<?php endif; ?>

<?php else: ?>

            <div class="text-center" data-aos="fade-down">
                <div class="inline-block p-6 bg-gradient-to-r from-purple-500 to-blue-500 rounded-2xl mb-6 glow">
                    <i class="fas fa-envelope-open text-white text-4xl"></i>
                </div>
                <h1 class="text-4xl font-bold text-white mb-4">Vérification d'email</h1>
                <p class="text-gray-400 text-lg mb-8">
                    Cliquez sur le lien dans l'email que nous vous avons envoyé pour vérifier votre compte.
                </p>

                <div class="bg-gray-900 bg-opacity-80 backdrop-blur-lg rounded-2xl border border-gray-700 p-6 shadow-2xl">
                    <h3 class="text-xl font-semibold text-white mb-4">Renvoyer l'email de vérification</h3>

                    <div id="resend-alert" class="hidden mb-4">
                        <div id="resend-alert-content" class="p-3 rounded-lg text-sm"></div>
                    </div>

                    <form id="resendForm" class="space-y-4">
                        <div>
                            <input type="email" 
                                   id="resend-email" 
                                   name="email" 
                                   required
                                   class="w-full px-4 py-3 bg-gray-800 border border-gray-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 transition-all duration-300"
                                   placeholder="votre@email.com">
                        </div>

                        <button type="submit" 
                                id="resendBtn"
                                class="w-full py-3 px-6 bg-gradient-to-r from-purple-500 to-blue-500 text-white font-semibold rounded-lg transition-all duration-300 hover:scale-105 glow disabled:opacity-50 disabled:cursor-not-allowed">
                            <span id="resendBtnText">Envoyer l'email</span>
                            <i id="resendBtnSpinner" class="fas fa-spinner fa-spin hidden ml-2"></i>
                        </button>
                    </form>
                </div>

                <div class="mt-8 space-y-4">
                    <a href="/auth/login" 
                       class="block text-purple-400 hover:text-purple-300 transition-colors duration-300">
                        Retour à la connexion
                    </a>
                    <a href="/" 
                       class="block text-gray-400 hover:text-white transition-colors duration-300">
                        Retour à l'accueil
                    </a>
                </div>
            </div>
<?php endif; ?>
    </div>
</section>

<style>
.glow:hover {
    box-shadow: 0 0 30px rgba(123, 97, 255, 0.4);
}
</style>

<script>

document.getElementById('resendForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('resendBtn');
    const btnText = document.getElementById('resendBtnText');
    const btnSpinner = document.getElementById('resendBtnSpinner');
    const email = document.getElementById('resend-email').value;

    btn.disabled = true;
    btnText.textContent = 'Envoi...';
    btnSpinner.classList.remove('hidden');

    try {
        const response = await fetch('/auth/verify-email', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ email: email })
        });

        const result = await response.json();

        if (result.success) {
            showResendAlert(result.message, 'success');
        } else {
            showResendAlert(result.error, 'error');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showResendAlert('Erreur de connexion. Veuillez réessayer.', 'error');
    } finally {

        btn.disabled = false;
        btnText.textContent = 'Renvoyer l\'email';
        btnSpinner.classList.add('hidden');
    }
});

function showResendAlert(message, type) {
    const container = document.getElementById('resend-alert');
    const content = document.getElementById('resend-alert-content');

    const classes = {
        success: 'bg-green-500 bg-opacity-20 border border-green-500 text-green-400',
        error: 'bg-red-500 bg-opacity-20 border border-red-500 text-red-400'
    };

    content.className = 'p-3 rounded-lg text-sm ' + classes[type];
    content.textContent = message;

    container.classList.remove('hidden');

    setTimeout(() => {
        container.classList.add('hidden');
    }, 5000);
}
</script>

<?php
endHTMLPage();
?>