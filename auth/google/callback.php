<?php

function logDebug($message, $data = []) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [GOOGLE_OAUTH] $message";
    if (!empty($data)) {
        $logEntry .= " | " . json_encode($data);
    }
    error_log($logEntry);
}

logDebug("Google OAuth Callback started", [
    'GET' => $_GET,
    'SESSION' => isset($_SESSION) ? array_keys($_SESSION) : 'no session',
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? 'unknown'
]);

try {
        $configPaths = [
        __DIR__ . '/../../api/v1/config.php',
        __DIR__ . '/../../api/config.php',
        dirname(__DIR__) . '/api/v1/config.php',
        dirname(dirname(__DIR__)) . '/api/v1/config.php'
    ];
    
    $configFound = false;
    foreach ($configPaths as $path) {
        if (file_exists($path)) {
            logDebug("Config found at: $path");
            define('API_ACCESS', true);
            require_once $path;
            $configFound = true;
            break;
        }
    }
    
    if (!$configFound) {
        throw new Exception("Configuration file not found in any expected location");
    }
    
        require_once dirname(dirname(__DIR__)) . '/api/v1/mail/MailerManager.php';
    require_once dirname(dirname(__DIR__)) . '/api/v1/mail/QueueProcessor.php';
    
        $requiredConstants = ['GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET', 'SITE_URL'];
    foreach ($requiredConstants as $constant) {
        if (!defined($constant)) {
            throw new Exception("Required constant $constant not defined");
        }
    }
    
    logDebug("Configuration loaded successfully");
    
} catch (Exception $e) {
    logDebug("Critical error during initialization", ['error' => $e->getMessage()]);
    
        ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur de configuration - Pilote Shopping</title>
        <style>
            body { font-family: Arial, sans-serif; background: #1a1a1a; color: #fff; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; text-align: center; padding: 40px; }
            .error-box { background: #2d1b1b; border: 1px solid #ff4444; border-radius: 8px; padding: 30px; }
            .error-title { color: #ff4444; font-size: 24px; margin-bottom: 20px; }
            .error-message { margin-bottom: 20px; line-height: 1.6; }
            .back-link { display: inline-block; background: #0ea5e9; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="error-box">
                <div class="error-title">Erreur de Configuration</div>
                <div class="error-message">
                    Une erreur de configuration empêche le bon fonctionnement de l'authentification Google.
                </div>
                <div class="error-message">
                    Veuillez contacter l'administrateur du site.
                </div>
                <a href="/" class="back-link">Retour à l'accueil</a>
            </div>
        </div>
    </body>
    </html>
<?php
    exit();
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class GoogleOAuthCallback {
    private $db;
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $mailer;
    private $queue;
    
    public function __construct() {
        try {
                        if (class_exists('Database')) {
                $this->db = Database::getInstance();
            } else {
                throw new Exception("Database class not available");
            }
            
                        $this->mailer = new MailerManager();
            $this->queue = new EmailQueueProcessor();
            
                        $this->clientId = GOOGLE_CLIENT_ID;
            $this->clientSecret = GOOGLE_CLIENT_SECRET;
            $this->redirectUri = SITE_URL . '/auth/google/callback.php';
            
            logDebug("OAuth configuration initialized", [
                'client_id' => substr($this->clientId, 0, 10) . '...',
                'redirect_uri' => $this->redirectUri
            ]);
            
        } catch (Exception $e) {
            logDebug("Error initializing OAuth callback", ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    public function handleCallback() {
        try {
                        $code = $_GET['code'] ?? '';
            $state = $_GET['state'] ?? '';
            $error = $_GET['error'] ?? '';
            
            logDebug("Processing callback", [
                'has_code' => !empty($code),
                'has_state' => !empty($state),
                'has_error' => !empty($error)
            ]);
            
                        if (!empty($error)) {
                $errorDescription = $_GET['error_description'] ?? $error;
                throw new Exception("Google OAuth error: $errorDescription");
            }
            
                        if (empty($code)) {
                throw new Exception("Authorization code is missing");
            }
            
                        if (empty($state) || empty($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
                throw new Exception("Invalid OAuth state - possible CSRF attack");
            }
            
                        $tokenData = $this->exchangeCodeForToken($code);
            
                        $userInfo = $this->getUserInfo($tokenData['access_token']);
            
                        $result = $this->processUser($userInfo, $tokenData);
            
                        $this->createUserSession($result['user_id']);
            
                        $this->sendLoginNotificationEmail($result['user_id'], $userInfo, $result['is_new_user']);
            
                        if ($result['needs_completion']) {
                $this->redirectTo('/auth/complete-profile?google=true');
            } else {
                $this->redirectTo('/dashboard?google_login=success');
            }
            
        } catch (Exception $e) {
            logDebug("Error handling callback", ['error' => $e->getMessage()]);
            $this->redirectTo('/auth/login?error=' . urlencode($e->getMessage()));
        }
    }
    
        private function sendLoginNotificationEmail($userId, $userInfo, $isNewUser = false) {
        try {
                        $stmt = $this->db->prepare("
                SELECT first_name, last_name, email 
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                logDebug("User not found for login notification", ['user_id' => $userId]);
                return;
            }
            
                        $deviceInfo = $this->getDeviceInfo();
            $locationInfo = $this->getLocationInfo();
            
                        $emailData = [
                'template' => $isNewUser ? 'google_first_login_notification' : 'google_login_notification',
                'to_email' => $user['email'],
                'to_name' => trim($user['first_name'] . ' ' . $user['last_name']),
                'variables' => [
                    'FIRST_NAME' => $user['first_name'],
                    'FULL_NAME' => trim($user['first_name'] . ' ' . $user['last_name']),
                    'LOGIN_DATE' => date('d/m/Y à H:i'),
                    'IP_ADDRESS' => $this->getRealIP(),
                    'DEVICE_TYPE' => $deviceInfo['type'],
                    'BROWSER' => $deviceInfo['browser'],
                    'OS' => $deviceInfo['os'],
                    'LOCATION' => $locationInfo['city'] ? $locationInfo['city'] . ', ' . $locationInfo['country'] : $locationInfo['country'],
                    'COUNTRY' => $locationInfo['country'],
                    'CITY' => $locationInfo['city'],
                    'IS_NEW_USER' => $isNewUser,
                    'GOOGLE_EMAIL' => $userInfo['email'],
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
                logDebug("Login notification email queued", [
                    'user_id' => $userId,
                    'email' => $user['email'],
                    'queue_id' => $result['queue_id'],
                    'is_new_user' => $isNewUser
                ]);
                
                                logSecurityEvent($userId, 'google_login_notification_sent', [
                    'email' => $user['email'],
                    'device' => $deviceInfo['type'],
                    'browser' => $deviceInfo['browser'],
                    'location' => $locationInfo['city'] . ', ' . $locationInfo['country'],
                    'ip' => $this->getRealIP(),
                    'is_new_user' => $isNewUser
                ], 'success');
            } else {
                logDebug("Failed to queue login notification email", [
                    'user_id' => $userId,
                    'error' => $result['error']
                ]);
            }
            
        } catch (Exception $e) {
            logDebug("Error sending login notification email", [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
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
        $ip = $this->getRealIP();
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
            logDebug("Error getting location info", ['error' => $e->getMessage()]);
        }
        
        return [
            'country' => $country,
            'city' => $city
        ];
    }
    
        
    private function exchangeCodeForToken($code) {
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        
        $data = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code'
        ];
        
        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($tokenUrl, false, $context);
        
        if ($result === FALSE) {
            throw new Exception('Failed to exchange code for token');
        }
        
        $tokenData = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Google');
        }
        
        if (isset($tokenData['error'])) {
            throw new Exception('Google token error: ' . ($tokenData['error_description'] ?? $tokenData['error']));
        }
        
        if (!isset($tokenData['access_token'])) {
            throw new Exception('Access token missing from response');
        }
        
        logDebug("Token exchange successful");
        return $tokenData;
    }
    
    private function getUserInfo($accessToken) {
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';
        
        $options = [
            'http' => [
                'header' => "Authorization: Bearer $accessToken\r\n",
                'method' => 'GET',
                'timeout' => 30
            ]
        ];
        
        $context = stream_context_create($options);
        $result = file_get_contents($userInfoUrl, false, $context);
        
        if ($result === FALSE) {
            throw new Exception('Failed to get user info');
        }
        
        $userInfo = json_decode($result, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response from Google user info');
        }
        
        if (!isset($userInfo['id']) || !isset($userInfo['email'])) {
            throw new Exception('Incomplete user information');
        }
        
        if (!isset($userInfo['verified_email']) || !$userInfo['verified_email']) {
            throw new Exception('Google email must be verified');
        }
        
        logDebug("User info retrieved", ['email' => $userInfo['email']]);
        return $userInfo;
    }
    
    private function processUser($userInfo, $tokenData) {
                $stmt = $this->db->prepare("
            SELECT u.*, uo.provider_id
            FROM users u 
            JOIN users_oauth uo ON u.id = uo.user_id 
            WHERE uo.provider = 'google' AND uo.provider_id = ?
        ");
        $stmt->execute([$userInfo['id']]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
                        $this->updateOAuthTokens($existingUser['id'], $tokenData);
            $needsCompletion = $this->checkProfileCompletion($existingUser['id']);
            
            logDebug("Existing user logged in", ['user_id' => $existingUser['id']]);
            
            return [
                'user_id' => $existingUser['id'],
                'needs_completion' => $needsCompletion,
                'is_new_user' => false
            ];
        } else {
                        $result = $this->createOrLinkUser($userInfo, $tokenData);
            $result['is_new_user'] = true;             return $result;
        }
    }
    
    private function createOrLinkUser($userInfo, $tokenData) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userInfo['email']]);
        $existingUser = $stmt->fetch();
        
        if ($existingUser) {
                        $this->linkGoogleAccount($existingUser['id'], $userInfo, $tokenData);
            $needsCompletion = $this->checkProfileCompletion($existingUser['id']);
            
            logDebug("Google account linked to existing user", ['user_id' => $existingUser['id']]);
            
            return [
                'user_id' => $existingUser['id'],
                'needs_completion' => $needsCompletion,
                'is_new_user' => false
            ];
        } else {
                        $userId = $this->createNewUser($userInfo, $tokenData);
            
            logDebug("New user created", ['user_id' => $userId]);
            
            return [
                'user_id' => $userId,
                'needs_completion' => true,
                'is_new_user' => true
            ];
        }
    }
    
    private function createNewUser($userInfo, $tokenData) {
        $this->db->beginTransaction();
        
        try {
                        $username = $this->generateUsername($userInfo['email']);
            
                        $stmt = $this->db->prepare("
                INSERT INTO users (
                    uuid, username, email, password_hash, password_salt,
                    first_name, last_name, avatar_url, balance, 
                    is_active, is_verified, email_verified_at, profile_completed
                ) VALUES (?, ?, ?, '', '', ?, ?, ?, 0.00, 1, 1, NOW(), FALSE)
            ");
            
            $uuid = $this->generateUUID();
            $firstName = $userInfo['given_name'] ?? '';
            $lastName = $userInfo['family_name'] ?? '';
            
            if (empty($firstName) && !empty($userInfo['name'])) {
                $nameParts = explode(' ', trim($userInfo['name']), 2);
                $firstName = $nameParts[0];
                $lastName = $nameParts[1] ?? '';
            }
            
            $stmt->execute([
                $uuid,
                $username,
                $userInfo['email'],
                $firstName ?: 'Utilisateur',
                $lastName ?: 'Google',
                $userInfo['picture'] ?? null
            ]);
            
            $userId = $this->db->lastInsertId();
            
                        $this->createOAuthRecord($userId, $userInfo, $tokenData);
            
                        $stmt = $this->db->prepare("
                INSERT INTO users_2fa (user_id, secret, is_enabled) 
                VALUES (?, '', 0)
            ");
            $stmt->execute([$userId]);
            
            $this->db->commit();
            return $userId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function linkGoogleAccount($userId, $userInfo, $tokenData) {
                $stmt = $this->db->prepare("
            SELECT id FROM users_oauth 
            WHERE user_id = ? AND provider = 'google'
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->fetch()) {
                        $this->updateOAuthTokens($userId, $tokenData);
        } else {
                        $this->createOAuthRecord($userId, $userInfo, $tokenData);
        }
    }
    
    private function createOAuthRecord($userId, $userInfo, $tokenData) {
        $expiresAt = isset($tokenData['expires_in']) ? 
                     date('Y-m-d H:i:s', time() + $tokenData['expires_in']) : null;
        
        $stmt = $this->db->prepare("
            INSERT INTO users_oauth (
                user_id, provider, provider_id, provider_email,
                access_token, refresh_token, expires_at
            ) VALUES (?, 'google', ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $userInfo['id'],
            $userInfo['email'],
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $expiresAt
        ]);
    }
    
    private function updateOAuthTokens($userId, $tokenData) {
        $expiresAt = isset($tokenData['expires_in']) ? 
                     date('Y-m-d H:i:s', time() + $tokenData['expires_in']) : null;
        
        $stmt = $this->db->prepare("
            UPDATE users_oauth 
            SET access_token = ?, refresh_token = ?, expires_at = ?, updated_at = NOW()
            WHERE user_id = ? AND provider = 'google'
        ");
        
        $stmt->execute([
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $expiresAt,
            $userId
        ]);
    }
    
    private function checkProfileCompletion($userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as has_billing
            FROM users_billing 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result['has_billing'] == 0;
    }
    
    private function createUserSession($userId) {
        $sessionId = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + (defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 86400));
        
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
            $this->getRealIP(),
            $userAgent,
            $isMobile ? 1 : 0,
            $expiresAt
        ]);
        
                $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
                $_SESSION['user_id'] = $userId;
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['username'] = $user['username'] ?? 'user_' . $userId;
        
        session_regenerate_id(true);
        
        logDebug("User session created", ['user_id' => $userId, 'session_id' => substr($sessionId, 0, 8) . '...']);
    }
    
    private function generateUsername($email) {
        $baseUsername = strtolower(explode('@', $email)[0]);
        $baseUsername = preg_replace('/[^a-z0-9]/', '', $baseUsername);
        $baseUsername = substr($baseUsername, 0, 20);
        
        $username = $baseUsername;
        $counter = 1;
        
        while (true) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if (!$stmt->fetch()) {
                return $username;
            }
            
            $username = $baseUsername . $counter;
            $counter++;
            
            if ($counter > 9999) {
                $username = $baseUsername . mt_rand(10000, 99999);
                break;
            }
        }
        
        return $username;
    }
    
    private function generateUUID() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    private function getRealIP() {
        $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        
        foreach ($ipKeys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    private function redirectTo($url) {
                unset($_SESSION['oauth_state'], $_SESSION['oauth_provider'], $_SESSION['oauth_remember_me']);
        
        logDebug("Redirecting to: $url");
        
        header('Location: ' . $url);
        exit();
    }
}

try {
    logDebug("Starting main processing");
    
    $callback = new GoogleOAuthCallback();
    $callback->handleCallback();
    
} catch (Exception $e) {
    logDebug("Main processing error", ['error' => $e->getMessage()]);
    
        unset($_SESSION['oauth_state'], $_SESSION['oauth_provider'], $_SESSION['oauth_remember_me']);
    
        $errorMessage = urlencode('Erreur de connexion Google: ' . $e->getMessage());
    header('Location: /auth/login?error=' . $errorMessage);
    exit();
}
?>