<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once __DIR__ . '/../../../includes/CsrfProtection.php';
require_once __DIR__ . '/../../../includes/AuthMiddleware.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

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

$auth = initAuth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentification requise']);
    exit();
}
$_authedUser = $auth->getCurrentUser();

class OrderCreator {
    private $db;
    private $userId;
    private $sessionId;
    
    public function __construct($userId) {
        $this->db = Database::getInstance();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->userId = $userId;
        $this->sessionId = session_id();
    }
    
    public function createOrder($orderData) {
        try {
                        $this->validateOrderData($orderData);
            
                        $cartItems = $this->getAndValidateCart();
            if (empty($cartItems)) {
                throw new Exception('Panier vide ou invalide');
            }
            
                        $this->db->beginTransaction();
            
            try {
                                $orderId = $this->createOrderRecord($orderData, $cartItems);
                
                                $this->createOrderItems($orderId, $cartItems);
                
                                $paymentResult = $this->processPayment($orderId, $orderData);
                
                                $this->createSubscriptions($orderId, $cartItems);
                
                                $this->processAutomaticDelivery($orderId, $cartItems);
                
                                $this->saveBillingAddress($orderData['billing_address']);
                
                                $this->clearCart();
                
                                $this->db->commit();
                
                                logSecurityEvent($this->userId, 'order_created', [
                    'order_id' => $orderId,
                    'order_number' => $paymentResult['order_number'],
                    'total_amount' => $paymentResult['total_amount'],
                    'payment_method' => $orderData['payment_method']
                ], 'success');
                
                return [
                    'success' => true,
                    'order_id' => $orderId,
                    'order_number' => $paymentResult['order_number'],
                    'total_amount' => $paymentResult['total_amount'],
                    'payment_status' => $paymentResult['payment_status'],
                    'client_secret' => $paymentResult['client_secret'] ?? null,
                    'paypal_approval_url' => $paymentResult['paypal_approval_url'] ?? null,
                    'payment_instructions' => $paymentResult['payment_instructions'] ?? null
                ];
                
            } catch (Exception $e) {
                $this->db->rollback();
                throw $e;
            }
            
        } catch (Exception $e) {
            logSecurityEvent($this->userId, 'order_creation_failed', [
                'error' => $e->getMessage(),
                'payment_method' => $orderData['payment_method'] ?? null
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function validateOrderData($orderData) {
                $required = ['billing_address', 'payment_method'];
        foreach ($required as $field) {
            if (empty($orderData[$field])) {
                throw new Exception("Champ requis manquant: {$field}");
            }
        }
        
                $address = $orderData['billing_address'];
        $addressRequired = ['first_name', 'last_name', 'address_line1', 'postal_code', 'city', 'country'];
        foreach ($addressRequired as $field) {
            if (empty($address[$field])) {
                throw new Exception("Adresse de facturation incomplète: {$field}");
            }
        }
        
                $stmt = $this->db->prepare("SELECT * FROM payment_adapters WHERE slug = ? AND is_active = 1");
        $stmt->execute([$orderData['payment_method']]);
        if (!$stmt->fetch()) {
            throw new Exception('Méthode de paiement invalide');
        }
    }
    
    private function getAndValidateCart() {
                $stmt = $this->db->prepare("
            SELECT ci.*, 
                   p.name, p.slug, p.images, p.short_description, p.is_active, p.sku,
                   pc.name as category_name, pc.color as category_color,
                   pt.name as type_name, pt.supports_stock, pt.default_delivery_method, pt.icon as type_icon,
                   po.id as offer_id, po.name as offer_name, po.price as offer_price,
                   po.billing_cycle, po.trial_days, po.setup_fee, po.has_own_stock,
                   po.manage_stock, po.stock_quantity, po.is_active as offer_active,
                   pat.name as access_type_name, pat.has_global_stock, pat.global_stock_quantity,
                   -- CORRECTION: Utiliser uniquement po.price
                   po.price as unit_price,
                   CASE 
                       WHEN po.billing_cycle IS NOT NULL AND po.billing_cycle != 'one_time' THEN po.price
                       ELSE po.price * ci.quantity
                   END as total_price,
                   CASE 
                       WHEN po.billing_cycle IS NOT NULL AND po.billing_cycle != 'one_time' THEN 1
                       ELSE 0
                   END as is_subscription,
                   -- Calcul du stock disponible
                   CASE 
                       WHEN po.has_own_stock = 1 AND po.manage_stock = 1 THEN po.stock_quantity
                       WHEN pat.has_global_stock = 1 THEN pat.global_stock_quantity
                       ELSE 999
                   END as available_stock
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            JOIN product_categories pc ON p.category_id = pc.id
            JOIN product_types pt ON p.product_type_id = pt.id
            -- CORRECTION: INNER JOIN obligatoire pour s'assurer qu'il y a toujours une offre
            INNER JOIN product_offers po ON ci.product_offer_id = po.id
            INNER JOIN product_access_types pat ON po.access_type_id = pat.id
            WHERE (ci.user_id = ? OR ci.session_id = ?) 
            AND p.is_active = 1 
            AND po.is_active = 1 
            AND pat.is_active = 1
            ORDER BY ci.created_at ASC
        ");
        $stmt->execute([$this->userId, $this->sessionId]);
        $items = $stmt->fetchAll();
        
                foreach ($items as &$item) {
                        if (!$item['offer_id']) {
                throw new Exception("Produit {$item['name']} sans offre valide");
            }
            
                        if (($item['manage_stock'] || $item['has_global_stock']) && $item['available_stock'] < 999) {
                if ($item['available_stock'] < $item['quantity']) {
                    throw new Exception("Stock insuffisant pour {$item['name']}");
                }
            }
            
                        $isSubscription = ($item['billing_cycle'] ?? 'one_time') !== 'one_time';
            if ($isSubscription && !$this->userId) {
                throw new Exception('Connexion requise pour les abonnements');
            }
            
                        if ($isSubscription && $item['quantity'] > 1) {
                throw new Exception("Les abonnements ne peuvent pas avoir une quantité supérieure à 1");
            }
            
                        $item['is_subscription'] = $isSubscription;
            
                        if (empty($item['type_name'])) {
                $item['type_name'] = 'Standard';
            }
        }
        
        return $items;
    }
    
    private function createOrderRecord($orderData, $cartItems) {
                $subtotal = 0;
        $setupFees = 0;
        
        foreach ($cartItems as $item) {
            $subtotal += $item['total_price'];
            if ($item['is_subscription'] && $item['setup_fee'] > 0) {
                $setupFees += $item['setup_fee'];
            } elseif (!$item['is_subscription'] && $item['setup_fee'] > 0) {
                $setupFees += $item['setup_fee'] * $item['quantity'];
            }
        }
        
        $discountAmount = 0;
        $couponCode = null;
        if (!empty($_SESSION['applied_coupon']['code'])) {
            $code = strtoupper(trim($_SESSION['applied_coupon']['code']));
            if (preg_match('/^[A-Z0-9_\-]{1,64}$/', $code)) {
                $cStmt = $this->db->prepare("
                    SELECT discount_type, discount_value, max_discount_amount
                    FROM coupons
                    WHERE code = ?
                      AND is_active = 1
                      AND (start_date IS NULL OR start_date <= NOW())
                      AND (end_date IS NULL OR end_date >= NOW())
                      AND (usage_limit IS NULL OR usage_count < usage_limit)
                    LIMIT 1
                ");
                $cStmt->execute([$code]);
                $coup = $cStmt->fetch(PDO::FETCH_ASSOC);
                if ($coup) {
                    $value = max(0, (float)$coup['discount_value']);
                    $maxDisc = isset($coup['max_discount_amount']) ? max(0, (float)$coup['max_discount_amount']) : 0;
                    $calc = 0;
                    if ($coup['discount_type'] === 'percentage') {
                        $calc = ($subtotal * min(100, $value)) / 100;
                        if ($maxDisc > 0 && $calc > $maxDisc) $calc = $maxDisc;
                    } elseif ($coup['discount_type'] === 'fixed') {
                        $calc = $value;
                    }
                    $discountAmount = max(0, min($subtotal, round($calc, 2)));
                    $couponCode = $code;
                } else {
                    unset($_SESSION['applied_coupon']);
                }
            }
        }
        
                $taxAmount = 0;
        $totalAmount = $subtotal + $setupFees - $discountAmount;
        
                $orderNumber = generateOrderNumber();
        
                $stmt = $this->db->prepare("
            INSERT INTO orders (
                order_number, user_id, status, payment_status, payment_method,
                currency, subtotal, tax_amount, discount_amount, total_amount,
                coupon_code, notes, billing_address, created_at
            ) VALUES (?, ?, 'pending', 'pending', ?, 'EUR', ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $orderNumber,
            $this->userId,
            $orderData['payment_method'],
            $subtotal + $setupFees,
            $taxAmount,             $discountAmount,
            $totalAmount,
            $couponCode,
            $orderData['order_notes'] ?? '',
            json_encode($orderData['billing_address'])
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function createOrderItems($orderId, $cartItems) {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_offer_id, product_name, product_sku,
                product_type, quantity, unit_price, total_price, product_data,
                delivery_status, billing_cycle, setup_fee, trial_days,
                access_type_name, offer_name, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        foreach ($cartItems as $item) {
            $productData = [
                'type_name' => $item['type_name'],
                'delivery_method' => $item['default_delivery_method'],
                'is_subscription' => $item['is_subscription'],
                'billing_cycle' => $item['billing_cycle'] ?? 'one_time',
                'trial_days' => $item['trial_days'] ?? 0,
                'setup_fee' => $item['setup_fee'] ?? 0,
                'category_name' => $item['category_name']
            ];
            
            $deliveryStatus = 'pending';
            if ($item['default_delivery_method'] === 'automatic') {
                $deliveryStatus = 'processing';
            }
            
            $stmt->execute([
                $orderId,
                $item['product_id'],
                $item['product_offer_id'],
                $item['name'],
                $item['sku'],
                $item['type_name'],
                $item['quantity'],
                $item['unit_price'],
                $item['total_price'],
                json_encode($productData),
                $deliveryStatus,
                $item['billing_cycle'] ?? 'one_time',
                $item['setup_fee'] ?? 0,
                $item['trial_days'] ?? 0,
                $item['access_type_name'],
                $item['offer_name']
            ]);
        }
    }
    
    private function processPayment($orderId, $orderData) {
                $orderStmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch();
        
                $adapterStmt = $this->db->prepare("SELECT * FROM payment_adapters WHERE slug = ?");
        $adapterStmt->execute([$orderData['payment_method']]);
        $adapter = $adapterStmt->fetch();
        
        if (!$adapter) {
            throw new Exception('Adapter de paiement non trouvé');
        }
        
                $transactionId = $this->generateTransactionId();
        $stmt = $this->db->prepare("
            INSERT INTO payment_transactions (
                transaction_id, order_id, payment_adapter_id, user_id,
                type, status, amount, currency, net_amount, created_at
            ) VALUES (?, ?, ?, ?, 'payment', 'pending', ?, 'EUR', ?, NOW())
        ");
        $stmt->execute([
            $transactionId,
            $orderId,
            $adapter['id'],
            $this->userId,
            $order['total_amount'],
            $order['total_amount']
        ]);
        
        $transactionDbId = $this->db->lastInsertId();
        
                switch ($orderData['payment_method']) {
            case 'stripe':
                return $this->processStripePayment($order, $adapter, $transactionDbId);
                
            case 'paypal':
                return $this->processPayPalPayment($order, $adapter, $transactionDbId);
                
            case 'bank_transfer':
                return $this->processBankTransferPayment($order, $adapter, $transactionDbId);
                
            case 'bitcoin':
                return $this->processBitcoinPayment($order, $adapter, $transactionDbId);
                
            default:
                throw new Exception('Méthode de paiement non supportée');
        }
    }
    
    private function processStripePayment($order, $adapter, $transactionId) {
        require_once '../../../plugins/stripe-php/init.php';
        
        $config = json_decode($adapter['config'], true);
        \Stripe\Stripe::setApiKey($config['secret_key']);
        
        try {
                        $intent = \Stripe\PaymentIntent::create([
                'amount' => intval($order['total_amount'] * 100),                 'currency' => 'eur',
                'metadata' => [
                    'order_id' => $order['id'],
                    'order_number' => $order['order_number'],
                    'transaction_id' => $transactionId
                ],
                'setup_future_usage' => 'off_session'             ]);
            
                        $stmt = $this->db->prepare("
                UPDATE payment_transactions 
                SET gateway_transaction_id = ?, metadata = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $intent->id,
                json_encode(['stripe_intent_id' => $intent->id]),
                $transactionId
            ]);
            
            return [
                'order_number' => $order['order_number'],
                'total_amount' => $order['total_amount'],
                'payment_status' => 'pending',
                'client_secret' => $intent->client_secret
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Erreur Stripe: ' . $e->getMessage());
        }
    }
    
    private function processPayPalPayment($order, $adapter, $transactionId) {
                return [
            'order_number' => $order['order_number'],
            'total_amount' => $order['total_amount'],
            'payment_status' => 'pending',
            'paypal_approval_url' => SITE_URL . '/checkout/paypal/approve?order=' . $order['order_number']
        ];
    }
    
    private function processBankTransferPayment($order, $adapter, $transactionId) {
        $config = json_decode($adapter['config'], true);
        
        $instructions = [
            'type' => 'bank_transfer',
            'account_holder' => $config['account_holder'] ?? 'Pilote Shopping',
            'iban' => $config['iban'] ?? 'FR76XXXXXXXXXXXXXXXXXXXXXXX',
            'bic' => $config['bic'] ?? 'BNPAFRPP',
            'amount' => $order['total_amount'],
            'reference' => $order['order_number']
        ];
        
                $stmt = $this->db->prepare("
            UPDATE payment_transactions 
            SET metadata = ?
            WHERE id = ?
        ");
        $stmt->execute([json_encode($instructions), $transactionId]);
        
        return [
            'order_number' => $order['order_number'],
            'total_amount' => $order['total_amount'],
            'payment_status' => 'pending',
            'payment_instructions' => $instructions
        ];
    }
    
    private function processBitcoinPayment($order, $adapter, $transactionId) {
                $instructions = [
            'type' => 'bitcoin',
            'address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',             'amount_btc' => $order['total_amount'] / 45000,             'amount_eur' => $order['total_amount']
        ];
        
                $stmt = $this->db->prepare("
            UPDATE payment_transactions 
            SET metadata = ?
            WHERE id = ?
        ");
        $stmt->execute([json_encode($instructions), $transactionId]);
        
        return [
            'order_number' => $order['order_number'],
            'total_amount' => $order['total_amount'],
            'payment_status' => 'pending',
            'payment_instructions' => $instructions
        ];
    }
    
    private function createSubscriptions($orderId, $cartItems) {
        $subscriptionItems = array_filter($cartItems, fn($item) => $item['is_subscription']);
        
        foreach ($subscriptionItems as $item) {
                        $orderItemStmt = $this->db->prepare("
                SELECT id FROM order_items 
                WHERE order_id = ? AND product_id = ? AND product_offer_id = ?
            ");
            $orderItemStmt->execute([$orderId, $item['product_id'], $item['product_offer_id']]);
            $orderItemId = $orderItemStmt->fetchColumn();
            
            if ($orderItemId) {
                $subscriptionNumber = 'SUB' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                
                                $startDate = date('Y-m-d H:i:s');
                $trialDays = $item['trial_days'] ?? 0;
                
                if ($trialDays > 0) {
                    $nextBillingDate = date('Y-m-d H:i:s', strtotime("+{$trialDays} days"));
                } else {
                    $nextBillingDate = date('Y-m-d H:i:s', strtotime('+1 month'));
                }
                
                                $stmt = $this->db->prepare("
                    INSERT INTO subscriptions (
                        user_id, order_item_id, product_offer_id, subscription_number,
                        status, billing_cycle, amount, currency, start_date,
                        next_billing_date, auto_renew, created_at
                    ) VALUES (?, ?, ?, ?, 'active', ?, ?, 'EUR', ?, ?, 1, NOW())
                ");
                
                $stmt->execute([
                    $this->userId,
                    $orderItemId,
                    $item['product_offer_id'],
                    $subscriptionNumber,
                    $item['billing_cycle'],
                    $item['unit_price'],
                    $startDate,
                    $nextBillingDate
                ]);
            }
        }
    }
    
    private function processAutomaticDelivery($orderId, $cartItems) {
        $automaticItems = array_filter($cartItems, fn($item) => $item['default_delivery_method'] === 'automatic');
        
        foreach ($automaticItems as $item) {
                        $this->reserveInventoryItems($orderId, $item);
        }
    }
    
    private function reserveInventoryItems($orderId, $item) {
        $stmt = $this->db->prepare("
            SELECT id FROM product_inventory 
            WHERE offer_id = ? AND status = 'available'
            ORDER BY created_at ASC
            LIMIT ?
        ");
        $stmt->execute([$item['product_offer_id'] ?: $item['product_id'], $item['quantity']]);
        $inventoryIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($inventoryIds) >= $item['quantity']) {
            $reserveStmt = $this->db->prepare("
                UPDATE product_inventory 
                SET status = 'reserved', reserved_until = DATE_ADD(NOW(), INTERVAL 1 HOUR),
                    sold_in_order_id = ?
                WHERE id = ?
            ");
            
            foreach (array_slice($inventoryIds, 0, $item['quantity']) as $inventoryId) {
                $reserveStmt->execute([$orderId, $inventoryId]);
            }
        }
    }
    
    private function saveBillingAddress($addressData) {
        if (!$this->userId || empty($addressData['save_address'])) {
            return;
        }
        
                $checkStmt = $this->db->prepare("
            SELECT id FROM users_billing 
            WHERE user_id = ? AND address_line1 = ? AND postal_code = ? AND city = ?
        ");
        $checkStmt->execute([
            $this->userId,
            $addressData['address_line1'],
            $addressData['postal_code'],
            $addressData['city']
        ]);
        
        if (!$checkStmt->fetch()) {
                        $stmt = $this->db->prepare("
                INSERT INTO users_billing (
                    user_id, company_name, first_name, last_name,
                    address_line1, address_line2, city, postal_code,
                    country, phone, is_default, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
            ");
            
            $stmt->execute([
                $this->userId,
                $addressData['company_name'] ?? '',
                $addressData['first_name'],
                $addressData['last_name'],
                $addressData['address_line1'],
                $addressData['address_line2'] ?? '',
                $addressData['city'],
                $addressData['postal_code'],
                $addressData['country'],
                $addressData['phone'] ?? ''
            ]);
        }
    }
    
    private function clearCart() {
        $stmt = $this->db->prepare("
            DELETE FROM cart_items 
            WHERE user_id = ? OR session_id = ?
        ");
        $stmt->execute([$this->userId, $this->sessionId]);
        
                unset($_SESSION['applied_coupon']);
    }
    
    private function getAvailableStock($productId, $productOfferId = null) {
        $sql = "
            SELECT COUNT(*) as available_stock
            FROM product_inventory
            WHERE offer_id = ? AND status = 'available'
        ";
        $params = [$productOfferId ?: $productId];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    private function generateTransactionId() {
        return 'TXN' . date('Ymd') . '-' . strtoupper(uniqid());
    }
}

try {
        $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide');
    }
    
        $orderCreator = new OrderCreator($_authedUser['id']);
    
        $result = $orderCreator->createOrder($input);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logSecurityEvent(null, 'order_create_error', [
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