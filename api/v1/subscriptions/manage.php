<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once __DIR__ . '/../../../includes/CsrfProtection.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

\CsrfProtection::requireValidToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

session_start();
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentification requise']);
    exit();
}

class SubscriptionManager {
    private $db;
    private $userId;
    private $stripeConfig;
    
    public function __construct($userId) {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->loadStripeConfig();
    }
    
    private function loadStripeConfig() {
        $stmt = $this->db->prepare("SELECT config FROM payment_adapters WHERE slug = 'stripe' AND is_active = 1");
        $stmt->execute();
        $config = $stmt->fetchColumn();
        
        if ($config) {
            $this->stripeConfig = json_decode($config, true);
            require_once '../../../plugins/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($this->stripeConfig['secret_key']);
        }
    }
    
    public function manageSubscription($subscriptionId, $action) {
        try {
                        $subscription = $this->getSubscription($subscriptionId);
            
            if (!$subscription) {
                throw new Exception('Abonnement non trouvé');
            }
            
            if ($subscription['user_id'] != $this->userId) {
                throw new Exception('Accès non autorisé');
            }
            
            switch ($action) {
                case 'pause':
                    return $this->pauseSubscription($subscription);
                    
                case 'resume':
                    return $this->resumeSubscription($subscription);
                    
                case 'cancel':
                    return $this->cancelSubscription($subscription);
                    
                default:
                    throw new Exception('Action non reconnue');
            }
            
        } catch (Exception $e) {
            logSecurityEvent($this->userId, 'subscription_manage_error', [
                'subscription_id' => $subscriptionId,
                'action' => $action,
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function getSubscription($subscriptionId) {
        $stmt = $this->db->prepare("
            SELECT s.*, oi.product_name, o.payment_method, pt.metadata
            FROM subscriptions s
            JOIN order_items oi ON s.order_item_id = oi.id
            JOIN orders o ON oi.order_id = o.id
            LEFT JOIN payment_transactions pt ON o.id = pt.order_id
            WHERE s.id = ?
        ");
        $stmt->execute([$subscriptionId]);
        return $stmt->fetch();
    }
    
    private function pauseSubscription($subscription) {
        try {
            $this->db->beginTransaction();
            
                        if (!in_array($subscription['status'], ['active'])) {
                throw new Exception('Cet abonnement ne peut pas être mis en pause');
            }
            
                        if ($subscription['payment_method'] === 'stripe' && $this->stripeConfig) {
                $metadata = json_decode($subscription['metadata'] ?? '{}', true);
                $stripeSubscriptionId = $metadata['stripe_subscription_id'] ?? null;
                
                if ($stripeSubscriptionId) {
                    $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                    $stripeSubscription->pause_collection = ['behavior' => 'mark_uncollectible'];
                    $stripeSubscription->save();
                }
            }
            
                        $stmt = $this->db->prepare("
                UPDATE subscriptions 
                SET status = 'paused', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subscription['id']]);
            
            $this->db->commit();
            
                        logSecurityEvent($this->userId, 'subscription_paused', [
                'subscription_id' => $subscription['id'],
                'subscription_number' => $subscription['subscription_number']
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Abonnement mis en pause avec succès'
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->db->rollback();
            throw new Exception('Erreur Stripe: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function resumeSubscription($subscription) {
        try {
            $this->db->beginTransaction();
            
                        if ($subscription['status'] !== 'paused') {
                throw new Exception('Cet abonnement ne peut pas être repris');
            }
            
                        if ($subscription['payment_method'] === 'stripe' && $this->stripeConfig) {
                $metadata = json_decode($subscription['metadata'] ?? '{}', true);
                $stripeSubscriptionId = $metadata['stripe_subscription_id'] ?? null;
                
                if ($stripeSubscriptionId) {
                    $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                    unset($stripeSubscription->pause_collection);
                    $stripeSubscription->save();
                }
            }
            
                        $nextBillingDate = $this->calculateNextBillingDate($subscription['billing_cycle']);
            
                        $stmt = $this->db->prepare("
                UPDATE subscriptions 
                SET status = 'active', next_billing_date = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nextBillingDate, $subscription['id']]);
            
            $this->db->commit();
            
                        logSecurityEvent($this->userId, 'subscription_resumed', [
                'subscription_id' => $subscription['id'],
                'subscription_number' => $subscription['subscription_number'],
                'next_billing_date' => $nextBillingDate
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Abonnement repris avec succès'
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->db->rollback();
            throw new Exception('Erreur Stripe: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function cancelSubscription($subscription) {
        try {
            $this->db->beginTransaction();
            
                        if (!in_array($subscription['status'], ['active', 'paused'])) {
                throw new Exception('Cet abonnement ne peut pas être annulé');
            }
            
                        $isCommitment = strpos($subscription['billing_cycle'], 'commitment_') === 0;
            if ($isCommitment) {
                                $commitmentMonths = intval(str_replace('commitment_', '', $subscription['billing_cycle']));
                $startDate = new DateTime($subscription['start_date']);
                $now = new DateTime();
                $monthsDiff = $startDate->diff($now)->m + ($startDate->diff($now)->y * 12);
                
                if ($monthsDiff < $commitmentMonths) {
                                                        }
            }
            
                        if ($subscription['payment_method'] === 'stripe' && $this->stripeConfig) {
                $metadata = json_decode($subscription['metadata'] ?? '{}', true);
                $stripeSubscriptionId = $metadata['stripe_subscription_id'] ?? null;
                
                if ($stripeSubscriptionId) {
                    $stripeSubscription = \Stripe\Subscription::retrieve($stripeSubscriptionId);
                    $stripeSubscription->cancel();
                }
            }
            
                        $stmt = $this->db->prepare("
                UPDATE subscriptions 
                SET status = 'cancelled', 
                    cancellation_reason = 'user_request',
                    cancelled_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subscription['id']]);
            
            $this->db->commit();
            
                        logSecurityEvent($this->userId, 'subscription_cancelled', [
                'subscription_id' => $subscription['id'],
                'subscription_number' => $subscription['subscription_number'],
                'is_commitment' => $isCommitment
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Abonnement annulé avec succès'
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->db->rollback();
            throw new Exception('Erreur Stripe: ' . $e->getMessage());
        } catch (Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }
    
    private function calculateNextBillingDate($billingCycle) {
        $now = new DateTime();
        
        switch ($billingCycle) {
            case 'monthly':
            case 'commitment_3':
            case 'commitment_6':
            case 'commitment_12':
                $now->add(new DateInterval('P1M'));
                break;
                
            default:
                $now->add(new DateInterval('P1M'));
        }
        
        return $now->format('Y-m-d H:i:s');
    }
    
        public function getSubscriptionHistory($subscriptionId) {
        try {
                        $subscription = $this->getSubscription($subscriptionId);
            
            if (!$subscription || $subscription['user_id'] != $this->userId) {
                throw new Exception('Abonnement non trouvé');
            }
            
                        $stmt = $this->db->prepare("
                SELECT pt.*, o.order_number
                FROM payment_transactions pt
                JOIN orders o ON pt.order_id = o.id
                JOIN order_items oi ON o.id = oi.order_id
                WHERE oi.id = ? AND pt.type = 'payment'
                ORDER BY pt.created_at DESC
            ");
            $stmt->execute([$subscription['order_item_id']]);
            $transactions = $stmt->fetchAll();
            
            return [
                'success' => true,
                'subscription' => $subscription,
                'history' => $transactions
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

try {
        $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide');
    }
    
    $subscriptionId = $input['subscription_id'] ?? null;
    $action = $input['action'] ?? null;
    
    if (!$subscriptionId || !$action) {
        throw new Exception('Paramètres manquants');
    }
    
        $subscriptionManager = new SubscriptionManager($userId);
    
        if ($action === 'history') {
        $result = $subscriptionManager->getSubscriptionHistory($subscriptionId);
    } else {
        $result = $subscriptionManager->manageSubscription($subscriptionId, $action);
    }
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logSecurityEvent($userId, 'subscription_manage_api_error', [
        'error' => $e->getMessage(),
        'input' => $input ?? null
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
?>