<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once __DIR__ . '/../../../includes/CsrfProtection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


\CsrfProtection::requireValidToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.'
    ]);
    exit();
}

class CompleteProfileManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function completeProfile($userId, $profileData) {
        try {
            $bonus = 0;
            $this->db->beginTransaction();
            $user = $this->getUserInfo($userId);
            if (!$user) {
                throw new Exception('Utilisateur non trouvé');
            }
            if (!$user['is_active']) {
                throw new Exception('Compte utilisateur inactif');
            }
            $this->validateProfileData($profileData);
            if ($user['profile_completed']) {
                throw new Exception('Le profil est déjà complet');
            }
            $this->updateUserProfile($userId, $profileData);
            $this->updateBillingAddress($userId, $profileData, $user);
            $this->markProfileAsComplete($userId);
        
            
                        $this->db->commit();
            
                        $this->logSecurityEvent($userId, 'profile_completed', [
                'phone' => $profileData['phone'],
                'city' => $profileData['city'],
                'country' => $profileData['country'],
                'bonus_awarded' => $bonus
            ], 'success');
            
            $response = [
                'success' => true,
                'message' => 'Profil complété avec succès',
                'profile_completed' => true
            ];
            
            if ($bonus > 0) {
                $response['bonus_awarded'] = $bonus;
            }
            
            return $response;
            
        } catch (Exception $e) {
                        $this->db->rollBack();
            
                        $this->logSecurityEvent($userId, 'profile_completion_failed', [
                'error' => $e->getMessage(),
                'data_keys' => array_keys($profileData)
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getUserInfo($userId) {
        $stmt = $this->db->prepare("
            SELECT id, username, email, first_name, last_name, phone, 
                   is_active, is_verified, profile_completed, balance
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetch();
    }
    
    private function validateProfileData($data) {
                $required = ['first_name', 'last_name', 'phone', 'address_line1', 'city', 'postal_code', 'country'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Le champ '$field' est requis");
            }
        }
        
                $phone = trim($data['phone']);
        if (!preg_match('/^(\+33[1-9]\d{8}|0[1-9]\d{8})$/', str_replace([' ', '.', '-'], '', $phone))) {
            throw new Exception('Format de téléphone invalide. Utilisez: 0123456789 ou +33123456789');
        }
        
                $postalCode = trim($data['postal_code']);
        if (!preg_match('/^\d{5}$/', $postalCode)) {
            throw new Exception('Le code postal doit contenir exactement 5 chiffres');
        }
        
                if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', trim($data['first_name']))) {
            throw new Exception('Prénom invalide (2-50 caractères, lettres uniquement)');
        }
        
        if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', trim($data['last_name']))) {
            throw new Exception('Nom invalide (2-50 caractères, lettres uniquement)');
        }
        
                if (strlen(trim($data['address_line1'])) < 5) {
            throw new Exception('L\'adresse doit contenir au moins 5 caractères');
        }
        
        if (strlen(trim($data['city'])) < 2) {
            throw new Exception('Le nom de la ville doit contenir au moins 2 caractères');
        }
        
                $allowedCountries = ['FR', 'BE', 'CH', 'LU', 'CA'];
        if (!in_array($data['country'], $allowedCountries)) {
            throw new Exception('Pays non supporté');
        }
    }
    
    private function updateUserProfile($userId, $data) {
                $cleanPhone = str_replace([' ', '.', '-'], '', trim($data['phone']));
        
        $stmt = $this->db->prepare("
            UPDATE users 
            SET first_name = ?, last_name = ?, phone = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([
            trim($data['first_name']),
            trim($data['last_name']),
            $cleanPhone,
            $userId
        ]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Impossible de mettre à jour le profil utilisateur');
        }
    }
    
    private function updateBillingAddress($userId, $data, $user) {
                $stmt = $this->db->prepare("
            SELECT id FROM users_billing WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $existingBilling = $stmt->fetch();
        
        if ($existingBilling) {
                        $stmt = $this->db->prepare("
                UPDATE users_billing 
                SET first_name = ?, last_name = ?, address_line1 = ?, address_line2 = ?,
                    city = ?, postal_code = ?, country = ?, phone = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            
            $stmt->execute([
                trim($data['first_name']),
                trim($data['last_name']),
                trim($data['address_line1']),
                trim($data['address_line2'] ?? ''),
                trim($data['city']),
                trim($data['postal_code']),
                trim($data['country']),
                str_replace([' ', '.', '-'], '', trim($data['phone'])),
                $userId
            ]);
        } else {
                        $stmt = $this->db->prepare("
                INSERT INTO users_billing (
                    user_id, first_name, last_name, address_line1, address_line2,
                    city, postal_code, country, phone, is_default
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            
            $stmt->execute([
                $userId,
                trim($data['first_name']),
                trim($data['last_name']),
                trim($data['address_line1']),
                trim($data['address_line2'] ?? ''),
                trim($data['city']),
                trim($data['postal_code']),
                trim($data['country']),
                str_replace([' ', '.', '-'], '', trim($data['phone']))
            ]);
        }
    }
    
    private function markProfileAsComplete($userId) {
                $stmt = $this->db->prepare("
            UPDATE users 
            SET profile_completed = 1, needs_profile_completion = 0, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception('Impossible de marquer le profil comme complet');
        }
        
                $stmt = $this->db->prepare("
            UPDATE users_oauth 
            SET needs_completion = 0, updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
    }
    
    
    private function logSecurityEvent($userId, $action, $details, $status) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO security_logs (user_id, action, details, ip_address, user_agent, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $action,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $status
            ]);
        } catch (Exception $e) {
                        error_log("Failed to log security event: " . $e->getMessage());
        }
    }
}

try {
        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Non authentifié. Veuillez vous connecter.'
        ]);
        exit();
    }
    
    $userId = intval($_SESSION['user_id']);
    
        $input = file_get_contents('php://input');
    if (empty($input)) {
        throw new Exception('Aucune donnée reçue');
    }
    
    $profileData = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide: ' . json_last_error_msg());
    }
    
        error_log("Complete profile attempt for user ID: $userId");
    error_log("Data received: " . json_encode(array_keys($profileData)));
    
        $profileManager = new CompleteProfileManager();
    
        $result = $profileManager->completeProfile($userId, $profileData);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
        error_log("Complete profile system error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Erreur serveur interne'
    ]);
}
?>