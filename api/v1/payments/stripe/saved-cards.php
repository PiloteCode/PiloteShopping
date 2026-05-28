<?php

define('API_ACCESS', true);
require_once '../../config.php';
require_once __DIR__ . '/../../../../includes/CsrfProtection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, POST, DELETE, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

\CsrfProtection::requireValidToken();

session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Authentification requise'
    ]);
    exit();
}

class StripeCardManager {
    private $db;
    private $userId;
    private $stripeConfig;
    private $stripe;
    
    public function __construct($userId) {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->loadStripeConfig();
    }
    
        private function loadStripeConfig() {
        try {
            $stmt = $this->db->prepare("SELECT config FROM payment_adapters WHERE slug = 'stripe' AND is_active = 1");
            $stmt->execute();
            $config = $stmt->fetchColumn();
            
            if (!$config) {
                throw new Exception('Stripe non configuré');
            }
            
            $this->stripeConfig = json_decode($config, true);
            
            if (empty($this->stripeConfig['secret_key'])) {
                throw new Exception('Clé secrète Stripe manquante');
            }
            
            require_once '../../../../plugins/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($this->stripeConfig['secret_key']);
            
        } catch (Exception $e) {
            error_log("Erreur configuration Stripe: " . $e->getMessage());
            throw new Exception('Configuration Stripe invalide');
        }
    }
    
        public function getSavedCards() {
        try {
                        $stmt = $this->db->prepare("
                SELECT id, stripe_payment_method_id, type, brand, last4, 
                       exp_month, exp_year, country, fingerprint, nickname, 
                       is_default, created_at
                FROM user_payment_methods 
                WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL
                ORDER BY is_default DESC, created_at DESC
            ");
            $stmt->execute([$this->userId]);
            $cards = $stmt->fetchAll();
            
                        $validCards = [];
            foreach ($cards as $card) {
                $stripeCard = $this->validateStripeCard($card['stripe_payment_method_id']);
                
                if ($stripeCard) {
                    $validCards[] = [
                        'id' => $card['stripe_payment_method_id'],
                        'local_id' => $card['id'],
                        'brand' => ucfirst($card['brand']),
                        'last4' => $card['last4'],
                        'exp_month' => sprintf('%02d', $card['exp_month']),
                        'exp_year' => $card['exp_year'],
                        'country' => $card['country'],
                        'nickname' => $card['nickname'],
                        'is_default' => (bool)$card['is_default'],
                        'created' => date('d/m/Y H:i', strtotime($card['created_at'])),
                        'status' => 'active'
                    ];
                } else {
                                        $this->markCardAsDeleted($card['id']);
                }
            }
            
            logSecurityEvent($this->userId, 'stripe_cards_retrieved', [
                'cards_count' => count($validCards)
            ], 'success');
            
            return [
                'success' => true,
                'cards' => $validCards,
                'total_cards' => count($validCards)
            ];
            
        } catch (Exception $e) {
            logSecurityEvent($this->userId, 'stripe_get_cards_error', [
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => 'Erreur lors de la récupération des cartes'
            ];
        }
    }
    
        public function saveCard($paymentMethodId, $setAsDefault = false, $nickname = null) {
        try {
            $this->db->beginTransaction();
            
                        if (empty($paymentMethodId) || !preg_match('/^pm_[a-zA-Z0-9]+$/', $paymentMethodId)) {
                throw new Exception('ID de méthode de paiement invalide');
            }
            
            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            if (!$paymentMethod || $paymentMethod->type !== 'card') {
                throw new Exception('Méthode de paiement invalide ou non supportée');
            }
            $expectedCustomerId = $this->getOrCreateStripeCustomer();
            if (!empty($paymentMethod->customer) && $paymentMethod->customer !== $expectedCustomerId) {
                logSecurityEvent($this->userId, 'stripe_pm_hijack_attempt', [
                    'pm_id' => $paymentMethodId,
                    'pm_customer' => $paymentMethod->customer,
                    'expected_customer' => $expectedCustomerId
                ], 'blocked');
                throw new Exception('Cette méthode de paiement n\'appartient pas à votre compte');
            }
            
                        $existingStmt = $this->db->prepare("
                SELECT id FROM user_payment_methods 
                WHERE user_id = ? AND stripe_payment_method_id = ? AND deleted_at IS NULL
            ");
            $existingStmt->execute([$this->userId, $paymentMethodId]);
            
            if ($existingStmt->fetch()) {
                throw new Exception('Cette carte est déjà enregistrée');
            }
            
                        $countStmt = $this->db->prepare("
                SELECT COUNT(*) FROM user_payment_methods 
                WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL
            ");
            $countStmt->execute([$this->userId]);
            $cardCount = $countStmt->fetchColumn();
            
            if ($cardCount >= 5) {
                throw new Exception('Limite de cartes atteinte (maximum 5 cartes)');
            }
            
                        $customerId = $this->getOrCreateStripeCustomer();
            
                        if ($paymentMethod->customer !== $customerId) {
                $paymentMethod->attach(['customer' => $customerId]);
                
                                $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            }
            
                        $isDefault = $setAsDefault || $cardCount == 0;
            
                        if ($isDefault) {
                $updateStmt = $this->db->prepare("
                    UPDATE user_payment_methods 
                    SET is_default = 0 
                    WHERE user_id = ? AND is_active = 1
                ");
                $updateStmt->execute([$this->userId]);
            }
            
                        $insertStmt = $this->db->prepare("
                INSERT INTO user_payment_methods (
                    user_id, stripe_payment_method_id, stripe_customer_id,
                    type, brand, last4, exp_month, exp_year, country,
                    fingerprint, nickname, is_default, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $card = $paymentMethod->card;
            $insertStmt->execute([
                $this->userId,
                $paymentMethodId,
                $customerId,
                'card',
                $card->brand,
                $card->last4,
                $card->exp_month,
                $card->exp_year,
                $card->country,
                $card->fingerprint,
                $nickname,
                $isDefault ? 1 : 0
            ]);
            
            $localCardId = $this->db->lastInsertId();
            
            $this->db->commit();
            
            logSecurityEvent($this->userId, 'stripe_card_saved', [
                'payment_method_id' => $paymentMethodId,
                'customer_id' => $customerId,
                'is_default' => $isDefault,
                'brand' => $card->brand,
                'last4' => $card->last4
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Carte sauvegardée avec succès',
                'card' => [
                    'id' => $paymentMethodId,
                    'local_id' => $localCardId,
                    'brand' => ucfirst($card->brand),
                    'last4' => $card->last4,
                    'exp_month' => sprintf('%02d', $card->exp_month),
                    'exp_year' => $card->exp_year,
                    'is_default' => $isDefault,
                    'nickname' => $nickname
                ]
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->db->rollback();
            
            logSecurityEvent($this->userId, 'stripe_save_card_error', [
                'payment_method_id' => $paymentMethodId,
                'stripe_error' => $e->getMessage(),
                'stripe_code' => $e->getStripeCode()
            ], 'failed');
            
                        $errorMessage = match($e->getStripeCode()) {
                'resource_missing' => 'Carte non trouvée',
                'card_declined' => 'Carte refusée par votre banque',
                'expired_card' => 'Cette carte a expiré',
                'incorrect_cvc' => 'Code de sécurité incorrect',
                'processing_error' => 'Erreur de traitement, veuillez réessayer',
                default => 'Erreur lors de la sauvegarde de la carte'
            };
            
            return [
                'success' => false,
                'error' => $errorMessage
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        public function deleteCard($paymentMethodId) {
        try {
            $this->db->beginTransaction();
            
                        $stmt = $this->db->prepare("
                SELECT id, is_default, brand, last4 FROM user_payment_methods 
                WHERE user_id = ? AND stripe_payment_method_id = ? 
                AND is_active = 1 AND deleted_at IS NULL
            ");
            $stmt->execute([$this->userId, $paymentMethodId]);
            $localCard = $stmt->fetch();
            
            if (!$localCard) {
                throw new Exception('Carte non trouvée ou déjà supprimée');
            }
            
                        try {
                $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
                if ($paymentMethod && !$paymentMethod->deleted) {
                    $paymentMethod->detach();
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                                error_log("Erreur Stripe lors suppression carte: " . $e->getMessage());
            }
            
                        $deleteStmt = $this->db->prepare("
                UPDATE user_payment_methods 
                SET is_active = 0, deleted_at = NOW()
                WHERE id = ?
            ");
            $deleteStmt->execute([$localCard['id']]);
            
                        if ($localCard['is_default']) {
                $newDefaultStmt = $this->db->prepare("
                    UPDATE user_payment_methods 
                    SET is_default = 1 
                    WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $newDefaultStmt->execute([$this->userId]);
            }
            
            $this->db->commit();
            
            logSecurityEvent($this->userId, 'stripe_card_deleted', [
                'payment_method_id' => $paymentMethodId,
                'was_default' => (bool)$localCard['is_default'],
                'brand' => $localCard['brand'],
                'last4' => $localCard['last4']
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Carte supprimée avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            logSecurityEvent($this->userId, 'stripe_delete_card_error', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        public function setDefaultCard($paymentMethodId) {
        try {
            $this->db->beginTransaction();
            
                        $stmt = $this->db->prepare("
                SELECT id, brand, last4 FROM user_payment_methods 
                WHERE user_id = ? AND stripe_payment_method_id = ? 
                AND is_active = 1 AND deleted_at IS NULL
            ");
            $stmt->execute([$this->userId, $paymentMethodId]);
            $localCard = $stmt->fetch();
            
            if (!$localCard) {
                throw new Exception('Carte non trouvée');
            }
            
                        $updateAllStmt = $this->db->prepare("
                UPDATE user_payment_methods 
                SET is_default = 0 
                WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL
            ");
            $updateAllStmt->execute([$this->userId]);
            
                        $updateStmt = $this->db->prepare("
                UPDATE user_payment_methods 
                SET is_default = 1, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$localCard['id']]);
            
            $this->db->commit();
            
            logSecurityEvent($this->userId, 'stripe_card_set_default', [
                'payment_method_id' => $paymentMethodId,
                'brand' => $localCard['brand'],
                'last4' => $localCard['last4']
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Carte définie comme carte par défaut'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        public function createSetupIntent() {
        try {
            $customerId = $this->getOrCreateStripeCustomer();
            
            $setupIntent = \Stripe\SetupIntent::create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => [
                    'user_id' => $this->userId,
                    'purpose' => 'save_card'
                ]
            ]);
            
            logSecurityEvent($this->userId, 'stripe_setup_intent_created', [
                'setup_intent_id' => $setupIntent->id,
                'customer_id' => $customerId
            ], 'success');
            
            return [
                'success' => true,
                'client_secret' => $setupIntent->client_secret,
                'setup_intent_id' => $setupIntent->id
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            logSecurityEvent($this->userId, 'stripe_setup_intent_error', [
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => 'Erreur lors de la création du Setup Intent: ' . $e->getMessage()
            ];
        }
    }
    
        public function updateCardNickname($paymentMethodId, $nickname) {
        try {
                        $nickname = trim($nickname);
            if (strlen($nickname) > 50) {
                throw new Exception('Le surnom ne peut pas dépasser 50 caractères');
            }
            
                        $stmt = $this->db->prepare("
                SELECT id FROM user_payment_methods 
                WHERE user_id = ? AND stripe_payment_method_id = ? 
                AND is_active = 1 AND deleted_at IS NULL
            ");
            $stmt->execute([$this->userId, $paymentMethodId]);
            $localCard = $stmt->fetch();
            
            if (!$localCard) {
                throw new Exception('Carte non trouvée');
            }
            
                        $updateStmt = $this->db->prepare("
                UPDATE user_payment_methods 
                SET nickname = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$nickname ?: null, $localCard['id']]);
            
            return [
                'success' => true,
                'message' => 'Surnom mis à jour'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        private function getOrCreateStripeCustomer() {
                $stmt = $this->db->prepare("SELECT stripe_customer_id FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $customerId = $stmt->fetchColumn();
        
        if ($customerId) {
                        try {
                $customer = \Stripe\Customer::retrieve($customerId);
                if ($customer && !$customer->deleted) {
                    return $customerId;
                }
            } catch (\Stripe\Exception\ApiErrorException $e) {
                            }
        }
        
                $user = $this->getUserData();
        
        $customer = \Stripe\Customer::create([
            'email' => $user['email'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'metadata' => [
                'user_id' => $this->userId,
                'username' => $user['username']
            ]
        ]);
        
                $updateStmt = $this->db->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
        $updateStmt->execute([$customer->id, $this->userId]);
        
        logSecurityEvent($this->userId, 'stripe_customer_created', [
            'customer_id' => $customer->id
        ], 'success');
        
        return $customer->id;
    }
    
        private function validateStripeCard($paymentMethodId) {
        try {
            $paymentMethod = \Stripe\PaymentMethod::retrieve($paymentMethodId);
            return $paymentMethod && !$paymentMethod->deleted;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            return false;
        }
    }
    
        private function markCardAsDeleted($localCardId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE user_payment_methods 
                SET is_active = 0, deleted_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$localCardId]);
        } catch (Exception $e) {
            error_log("Erreur marquage carte supprimée: " . $e->getMessage());
        }
    }
    
        private function getUserData() {
        $stmt = $this->db->prepare("
            SELECT id, username, email, first_name, last_name, stripe_customer_id 
            FROM users WHERE id = ?
        ");
        $stmt->execute([$this->userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('Utilisateur non trouvé');
        }
        
        return $user;
    }
}

try {
    $cardManager = new StripeCardManager($userId);
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
                        $result = $cardManager->getSavedCards();
            break;
            
        case 'POST':
                        $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Format JSON invalide');
            }
            
            $action = $input['action'] ?? 'save_card';
            
            switch ($action) {
                case 'save_card':
                    if (empty($input['payment_method_id'])) {
                        throw new Exception('ID de méthode de paiement requis');
                    }
                    $result = $cardManager->saveCard(
                        $input['payment_method_id'], 
                        $input['set_as_default'] ?? false,
                        $input['nickname'] ?? null
                    );
                    break;
                    
                case 'setup_intent':
                    $result = $cardManager->createSetupIntent();
                    break;
                    
                case 'set_default':
                    if (empty($input['payment_method_id'])) {
                        throw new Exception('ID de méthode de paiement requis');
                    }
                    $result = $cardManager->setDefaultCard($input['payment_method_id']);
                    break;
                    
                case 'update_nickname':
                    if (empty($input['payment_method_id'])) {
                        throw new Exception('ID de méthode de paiement requis');
                    }
                    $result = $cardManager->updateCardNickname(
                        $input['payment_method_id'],
                        $input['nickname'] ?? ''
                    );
                    break;
                    
                default:
                    throw new Exception('Action non reconnue: ' . $action);
            }
            break;
            
        case 'PUT':
                        $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Format JSON invalide');
            }
            
            if (empty($input['payment_method_id'])) {
                throw new Exception('ID de méthode de paiement requis');
            }
            
            if (isset($input['set_as_default']) && $input['set_as_default']) {
                $result = $cardManager->setDefaultCard($input['payment_method_id']);
            } elseif (isset($input['nickname'])) {
                $result = $cardManager->updateCardNickname(
                    $input['payment_method_id'],
                    $input['nickname']
                );
            } else {
                throw new Exception('Action de mise à jour non spécifiée');
            }
            break;
            
        case 'DELETE':
                        $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Format JSON invalide');
            }
            
            if (empty($input['payment_method_id'])) {
                throw new Exception('ID de méthode de paiement requis');
            }
            
            $result = $cardManager->deleteCard($input['payment_method_id']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Méthode HTTP non autorisée'
            ]);
            exit();
    }
    
        $httpCode = $result['success'] ? 200 : 400;
    http_response_code($httpCode);
    echo json_encode($result);
    
} catch (Exception $e) {
    logSecurityEvent($userId, 'stripe_cards_api_error', [
        'error' => $e->getMessage(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'input' => $input ?? null
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur interne'
    ]);
}
?>