<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once __DIR__ . '/../../../includes/CsrfProtection.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

\CsrfProtection::requireValidToken();

session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

$userId = intval($_SESSION['user_id']);

class ProfileManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function getProfile($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, uuid, username, email, first_name, last_name, phone, 
                       avatar_url, balance, is_active, is_verified, profile_completed, 
                       created_at, updated_at
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception('Utilisateur non trouvé');
            }
            
            return [
                'success' => true,
                'profile' => $user
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function updateProfile($userId, $data) {
        try {
                        $this->validateProfileData($data);
            
                        $this->db->beginTransaction();
            
                        $updateFields = [];
            $updateValues = [];
            
            if (isset($data['first_name'])) {
                $updateFields[] = 'first_name = ?';
                $updateValues[] = trim($data['first_name']);
            }
            
            if (isset($data['last_name'])) {
                $updateFields[] = 'last_name = ?';
                $updateValues[] = trim($data['last_name']);
            }
            
            if (isset($data['phone'])) {
                $cleanPhone = $this->normalizePhone($data['phone']);
                $updateFields[] = 'phone = ?';
                $updateValues[] = $cleanPhone;
            }
            
            if (!empty($updateFields)) {
                $updateFields[] = 'updated_at = NOW()';
                $updateValues[] = $userId;
                
                $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($updateValues);
                
                if ($stmt->rowCount() === 0) {
                    throw new Exception('Aucune modification effectuée');
                }
            }
            
                        $this->db->commit();
            
                        logSecurityEvent($userId, 'profile_updated', [
                'fields' => array_keys($data)
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Profil mis à jour avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            logSecurityEvent($userId, 'profile_update_failed', [
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function validateProfileData($data) {
                if (isset($data['first_name'])) {
            if (empty(trim($data['first_name']))) {
                throw new Exception('Le prénom est requis');
            }
            
            if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', trim($data['first_name']))) {
                throw new Exception('Prénom invalide (2-50 caractères, lettres uniquement)');
            }
        }
        
                if (isset($data['last_name'])) {
            if (empty(trim($data['last_name']))) {
                throw new Exception('Le nom est requis');
            }
            
            if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-\']{2,50}$/u', trim($data['last_name']))) {
                throw new Exception('Nom invalide (2-50 caractères, lettres uniquement)');
            }
        }
        
                if (isset($data['phone']) && !empty($data['phone'])) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', trim($data['phone']));
            
            if (!preg_match('/^(\+33[1-9]\d{8}|0[1-9]\d{8}|33[1-9]\d{8})$/', $cleanPhone)) {
                throw new Exception('Format de téléphone invalide');
            }
        }
    }
    
    private function normalizePhone($phone) {
        if (empty($phone)) {
            return null;
        }
        
        $cleanPhone = preg_replace('/[^0-9+]/', '', trim($phone));
        
                if (preg_match('/^33[1-9]\d{8}$/', $cleanPhone)) {
            return '+' . $cleanPhone;
        } elseif (preg_match('/^0[1-9]\d{8}$/', $cleanPhone)) {
            return '+33' . substr($cleanPhone, 1);
        } else {
            return $cleanPhone;         }
    }
}

try {
    $profileManager = new ProfileManager();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $result = $profileManager->getProfile($userId);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Format JSON invalide');
            }
            
            $result = $profileManager->updateProfile($userId, $input);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit();
    }
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    logSecurityEvent($userId ?? null, 'profile_api_error', [
        'error' => $e->getMessage()
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
?>