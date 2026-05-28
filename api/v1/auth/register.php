<?php
define('API_ACCESS', true);
require_once '../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

class RegisterManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
        public function processRegistration($userData) {
        try {
                        $this->performPreValidation($userData);
            
                        $userData = $this->normalizeUserData($userData);
            
                        $this->performAdvancedValidation($userData);
            
                        $this->performSecurityChecks($userData);
            
                        $this->checkForDuplicates($userData);
            
                        $bonusAmount = $this->calculateWelcomeBonus($userData);
            
                        $userId = $this->createUserAccount($userData, $bonusAmount);
            
                        $this->finalizeRegistration($userId, $userData, $bonusAmount);
            
            return [
                'success' => true,
                'user_id' => $userId,
                'bonus_amount' => $bonusAmount,
                'message' => 'Compte créé avec succès ! Vérifiez votre email pour l\'activer.',
                'next_step' => 'email_verification'
            ];
            
        } catch (Exception $e) {
            $this->handleRegistrationError($e, $userData);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => method_exists($e, 'getCode') ? $e->getCode() : 'REGISTRATION_ERROR'
            ];
        }
    }
    
        private function performPreValidation($userData) {
                $required = ['email', 'password', 'confirm_password', 'first_name', 'last_name', 'username'];
        foreach ($required as $field) {
            if (empty($userData[$field])) {
                throw new Exception("Le champ '$field' est requis");
            }
        }
        
                if ($userData['password'] !== $userData['confirm_password']) {
            throw new Exception('Les mots de passe ne correspondent pas');
        }
    }
    
        private function normalizeUserData($userData) {
                $userData['email'] = strtolower(trim($userData['email']));
        $userData['username'] = trim($userData['username']);
        $userData['first_name'] = ucwords(strtolower(trim($userData['first_name'])));
        $userData['last_name'] = strtoupper(trim($userData['last_name']));
        
                if (!empty($userData['phone'])) {
            $userData['phone'] = $this->normalizePhoneNumber($userData['phone']);
        }
        
                if (!empty($userData['address_line1'])) {
            $userData['address_line1'] = trim($userData['address_line1']);
            $userData['city'] = ucwords(strtolower(trim($userData['city'])));
            $userData['postal_code'] = str_replace(' ', '', trim($userData['postal_code']));
            $userData['country'] = strtoupper(trim($userData['country']));
        }
        
        return $userData;
    }
    
        private function performAdvancedValidation($userData) {
                if (!$this->validateEmail($userData['email'])) {
            throw new Exception('Format d\'email invalide');
        }
        
                if ($this->isEmailBlacklisted($userData['email'])) {
            throw new Exception('Cette adresse email n\'est pas autorisée');
        }
        
                $this->validatePassword($userData['password']);
        
                $this->validateUsername($userData['username']);
        
                $this->validateName($userData['first_name'], 'Prénom');
        $this->validateName($userData['last_name'], 'Nom');
        
                if (!empty($userData['phone'])) {
            $this->validatePhone($userData['phone']);
        }
        
                if (!empty($userData['address_line1'])) {
            $this->validateAddress($userData);
        }
    }
    
        private function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
        private function validatePassword($password) {
        if (strlen($password) < 8) {
            throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
        }
        
        if (strlen($password) > 128) {
            throw new Exception('Le mot de passe ne peut pas dépasser 128 caractères');
        }
        
        $checks = [
            '/[a-z]/' => 'au moins une minuscule',
            '/[A-Z]/' => 'au moins une majuscule',
            '/\d/' => 'au moins un chiffre',
            '/[@$!%*?&]/' => 'au moins un caractère spécial (@$!%*?&)'
        ];
        
        foreach ($checks as $pattern => $requirement) {
            if (!preg_match($pattern, $password)) {
                throw new Exception("Le mot de passe doit contenir $requirement");
            }
        }
        
                $commonPasswords = [
            'password', 'password123', '123456', '123456789', 'qwerty',
            'azerty', 'motdepasse', 'admin', 'pilote', 'shopping'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            throw new Exception('Ce mot de passe est trop courant. Choisissez un mot de passe plus sécurisé.');
        }
    }
    
        private function validateUsername($username) {
        if (strlen($username) < 3) {
            throw new Exception('Le nom d\'utilisateur doit contenir au moins 3 caractères');
        }
        
        if (strlen($username) > 30) {
            throw new Exception('Le nom d\'utilisateur ne peut pas dépasser 30 caractères');
        }
        
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new Exception('Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores');
        }
        
                $forbidden = [
            'admin', 'administrator', 'root', 'api', 'www', 'mail', 'email', 'support',
            'help', 'info', 'contact', 'pilote', 'piloteshopping', 'shopping', 'test',
            'null', 'undefined', 'system', 'service', 'daemon', 'bot', 'spam',
            'abuse', 'security', 'webmaster', 'hostmaster', 'postmaster', 'noreply'
        ];
        
        if (in_array(strtolower($username), $forbidden)) {
            throw new Exception('Ce nom d\'utilisateur n\'est pas autorisé');
        }
    }
    
        private function normalizePhoneNumber($phone) {
                $clean = preg_replace('/[^0-9+]/', '', $phone);
        
                if (preg_match('/^(\+33|33|0)([1-9]\d{8})$/', $clean, $matches)) {
            return '+33' . $matches[2];
        }
        
                if (preg_match('/^\+[1-9]\d{1,14}$/', $clean)) {
            return $clean;
        }
        
        throw new Exception('Format de téléphone invalide');
    }
    
    private function performSecurityChecks($userData) {
        $ip = $this->getRealIP();
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM users
            WHERE last_ip = ?
            AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute([$ip]);
        if ($stmt->fetchColumn() >= 5) {
            error_log("Registration rate limit exceeded for IP: $ip");
            throw new Exception('Trop d\'inscriptions depuis cette adresse IP. Réessayez dans 1 heure.');
        }
        if (CLOUDFLARE_TURNSTILE_ENABLED && CLOUDFLARE_TURNSTILE_SECRET_KEY) {
            $token = $userData['turnstile_token'] ?? '';
            if (empty($token) || !$this->verifyTurnstileToken($token, $ip)) {
                throw new Exception('Vérification anti-bot échouée. Veuillez réessayer.');
            }
        }
    }

    private function verifyTurnstileToken($token, $ip) {
        $payload = http_build_query([
            'secret' => CLOUDFLARE_TURNSTILE_SECRET_KEY,
            'response' => $token,
            'remoteip' => $ip
        ]);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $payload,
                'timeout' => 5
            ]
        ]);
        $resp = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $ctx);
        if ($resp === false) {
            error_log('Turnstile verification network error');
            return false;
        }
        $data = json_decode($resp, true);
        return is_array($data) && !empty($data['success']);
    }

    private function getRealIP() {
        return getRealIP();
    }
    
        private function checkForDuplicates($userData) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$userData['email']]);
        if ($stmt->fetch()) {
            throw new Exception('Un compte existe déjà avec cette adresse email');
        }
        
                $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$userData['username']]);
        if ($stmt->fetch()) {
            throw new Exception('Ce nom d\'utilisateur est déjà pris');
        }
        
                if (!empty($userData['phone'])) {
            $stmt = $this->db->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$userData['phone']]);
            if ($stmt->fetch()) {
                throw new Exception('Un compte existe déjà avec ce numéro de téléphone');
            }
        }
    }
    
        private function calculateWelcomeBonus($userData) {
                        return 0.00;
    }
    
        private function createUserAccount($userData, $bonusAmount) {
        $this->db->beginTransaction();
        
        try {
                        $userId = $this->insertUser($userData, $bonusAmount);
            
                        if (!empty($userData['address_line1'])) {
                $this->createBillingAddress($userId, $userData);
            }
            
                        if ($bonusAmount > 0) {
                $this->applyWelcomeBonus($userId, $bonusAmount);
            }
            
                        $this->generateVerificationTokens($userId);
            
            $this->db->commit();
            return $userId;
            
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
        private function insertUser($userData, $bonusAmount) {
        $uuid = $this->generateUUID();
        $passwordData = $this->hashPassword($userData['password']);
        
        $stmt = $this->db->prepare("
            INSERT INTO users (
                uuid, username, email, password_hash, password_salt,
                first_name, last_name, phone, balance, 
                is_active, is_verified, profile_completed,
                needs_profile_completion, last_ip, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 0, ?, ?, ?, NOW())
        ");
        
        $profileCompleted = !empty($userData['address_line1']) ? 1 : 0;
        $needsCompletion = $profileCompleted ? 0 : 1;
        
        $stmt->execute([
            $uuid,
            $userData['username'],
            $userData['email'],
            $passwordData['hash'],
            $passwordData['salt'],
            $userData['first_name'],
            $userData['last_name'],
            $userData['phone'] ?? null,
            $bonusAmount,
            $profileCompleted,
            $needsCompletion,
            $this->getRealIP()
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function hashPassword($password) {
        return hashPassword($password);
    }

    private function generateUUID() {
        return generateUUID();
    }
    
        private function applyWelcomeBonus($userId, $amount) {
        if ($amount <= 0) return;
        
        $stmt = $this->db->prepare("
            INSERT INTO balance_transactions (
                user_id, type, amount, balance_before, balance_after,
                source, description, metadata, created_at
            ) VALUES (?, 'credit', ?, 0.00, ?, ?, 'Bonus de bienvenue', ?, NOW())
        ");
        
        $source = 'WELCOME_BONUS_' . $userId . '_' . time();
        $metadata = json_encode([
            'bonus_type' => 'welcome',
            'registration_date' => date('Y-m-d H:i:s'),
            'ip' => $this->getRealIP()
        ]);
        
        $stmt->execute([$userId, $amount, $amount, $source, $metadata]);
    }
    
        private function generateVerificationTokens($userId) {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + 86400);         
        $stmt = $this->db->prepare("
            INSERT INTO verification_tokens (user_id, token, type, expires_at, created_at) 
            VALUES (?, ?, 'email_verification', ?, NOW())
        ");
        $stmt->execute([$userId, $token, $expiresAt]);
    }
    
        private function finalizeRegistration($userId, $userData, $bonusAmount) {
                error_log("User registration completed: ID=$userId, Email={$userData['email']}, Bonus=$bonusAmount");
    }
    
        private function handleRegistrationError($exception, $userData) {
        $errorData = [
            'error' => $exception->getMessage(),
            'email' => $userData['email'] ?? 'unknown',
            'ip' => $this->getRealIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        error_log("Registration error: " . json_encode($errorData));
    }
    
        private function validateName($name, $fieldName) {
        if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', $name)) {
            throw new Exception("$fieldName invalide (2-50 caractères, lettres uniquement)");
        }
    }
    
    private function validatePhone($phone) {
        if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
            throw new Exception('Format de téléphone invalide');
        }
    }
    
    private function validateAddress($userData) {
        if (strlen($userData['address_line1']) < 5) {
            throw new Exception('L\'adresse doit contenir au moins 5 caractères');
        }
        
        if (!preg_match('/^\d{5}$/', $userData['postal_code'])) {
            throw new Exception('Le code postal doit contenir exactement 5 chiffres');
        }
        
        if (!in_array($userData['country'], ['FR', 'BE', 'CH', 'LU', 'CA'])) {
            throw new Exception('Pays non supporté');
        }
    }
    
    private function isEmailBlacklisted($email) {
                $blacklistedDomains = [
            '10minutemail.com', 'mailinator.com', 'guerrillamail.com',
            'tempmail.org', 'throwaway.email', 'maildrop.cc'
        ];
        
        $domain = substr(strrchr($email, "@"), 1);
        return in_array(strtolower($domain), $blacklistedDomains);
    }
    
    private function createBillingAddress($userId, $userData) {
        $stmt = $this->db->prepare("
            INSERT INTO users_billing (
                user_id, first_name, last_name, address_line1, address_line2,
                city, state, postal_code, country, phone, is_default, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        
        $stmt->execute([
            $userId,
            $userData['first_name'],
            $userData['last_name'],
            $userData['address_line1'],
            $userData['address_line2'] ?? null,
            $userData['city'],
            $userData['state'] ?? null,
            $userData['postal_code'],
            $userData['country'],
            $userData['phone'] ?? null
        ]);
    }
}

try {
        $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Aucune donnée reçue');
    }
    
    $requestData = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide: ' . json_last_error_msg());
    }
    
    $userData = [
        'email' => trim($requestData['email'] ?? ''),
        'password' => $requestData['password'] ?? '',
        'confirm_password' => $requestData['confirm_password'] ?? '',
        'first_name' => trim($requestData['first_name'] ?? ''),
        'last_name' => trim($requestData['last_name'] ?? ''),
        'username' => trim($requestData['username'] ?? ''),
        'phone' => trim($requestData['phone'] ?? ''),
        'address_line1' => trim($requestData['address_line1'] ?? ''),
        'address_line2' => trim($requestData['address_line2'] ?? ''),
        'city' => trim($requestData['city'] ?? ''),
        'state' => trim($requestData['state'] ?? ''),
        'postal_code' => trim($requestData['postal_code'] ?? ''),
        'country' => trim($requestData['country'] ?? 'FR'),
        'turnstile_token' => trim($requestData['turnstile_token'] ?? $requestData['cf-turnstile-response'] ?? '')
    ];
    error_log("Registration attempt: " . $userData['email'] . " from " . getRealIP());
    
        $registerManager = new RegisterManager();
    $result = $registerManager->processRegistration($userData);
    
        if ($result['success']) {
        http_response_code(201);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    error_log("Registration system error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur serveur interne',
        'error_code' => 'SYSTEM_ERROR'
    ]);
}
