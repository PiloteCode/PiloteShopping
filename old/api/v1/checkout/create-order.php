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

class OrderManager {
    private $db;
    private $userId;
    private $sessionId;
    
    public function __construct() {
        $this->db = Database::getInstance();
        
                if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->userId = $_SESSION['user_id'] ?? null;
        $this->sessionId = session_id();
    }
    
    public function createOrder($orderData) {
        try {
            $this->db->beginTransaction();
            
                        $this->validateOrderData($orderData);
            
                        $cartItems = $this->getValidatedCartItems();
            if (empty($cartItems)) {
                throw new Exception('Panier vide ou invalide');
            }
            
                        $totals = $this->calculateOrderTotals($cartItems);
            
                        $orderNumber = generateOrderNumber();
            $orderId = $this->createOrderRecord($orderNumber, $orderData, $totals);
            
                        $this->createOrderItems($orderId, $cartItems);
            
                        $paymentResult = $this->processPayment($orderId, $orderData, $totals);
            
                        if (!empty($totals['coupon_id']) && $totals['discount_amount'] > 0) {
                $this->recordCouponUsage($totals['coupon_id'], $orderId, $totals['discount_amount']);
            }
            
                        $this->createSubscriptions($orderId, $cartItems);
            
                        if ($orderData['billing_address']['type'] === 'new' && !empty($orderData['save_address']) && $this->userId) {
                $this->saveBillingAddress($orderData['billing_address']);
            }
            
                        $this->clearCart();
            
                        $this->sendOrderNotifications($orderId, $orderNumber);
            
            $this->db->commit();
            
                        logSecurityEvent($this->userId, 'order_created', [
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totals['total'],
                'discount_amount' => $totals['discount_amount'],
                'coupon_code' => $totals['coupon_code'],
                'payment_method' => $orderData['payment_method']
            ], 'success');
            
            return array_merge([
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $totals['total'],
                'discount_applied' => $totals['discount_amount']
            ], $paymentResult);
            
        } catch (Exception $e) {
            $this->db->rollback();
            
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
                $requiredFields = ['billing_address', 'payment_method'];
        foreach ($requiredFields as $field) {
            if (empty($orderData[$field])) {
                throw new Exception("Champ requis manquant: {$field}");
            }
        }
        
                $billing = $orderData['billing_address'];
        if ($billing['type'] === 'new') {
            $requiredBilling = ['first_name', 'last_name', 'address_line1', 'city', 'postal_code', 'country'];
            foreach ($requiredBilling as $field) {
                if (empty($billing[$field])) {
                    throw new Exception("Adresse de facturation incomplète: {$field}");
                }
            }
        }
        
                $paymentStmt = $this->db->prepare("SELECT * FROM payment_adapters WHERE slug = ? AND is_active = 1");
        $paymentStmt->execute([$orderData['payment_method']]);
        if (!$paymentStmt->fetch()) {
            throw new Exception('Méthode de paiement invalide');
        }
    }
    
    private function getValidatedCartItems() {
        $stmt = $this->db->prepare("
            SELECT ci.*, 
                   p.name, p.slug, p.images, p.short_description, p.is_active, p.sku, p.category_id, p.product_type_id,
                   pc.name as category_name,
                   pt.name as type_name, pt.supports_stock, pt.default_delivery_method, pt.icon as type_icon,
                   po.id as offer_id, po.name as offer_name, po.price as offer_price,
                   po.billing_cycle, po.trial_days, po.setup_fee, po.has_own_stock, 
                   po.manage_stock, po.stock_quantity, po.is_active as offer_active,
                   pat.name as access_type_name, pat.has_global_stock, pat.global_stock_quantity, pat.access_method,
                   po.price as unit_price,
                   CASE 
                       WHEN po.billing_cycle IS NOT NULL AND po.billing_cycle != 'one_time' THEN po.price
                       ELSE po.price * ci.quantity
                   END as total_price,
                   CASE 
                       WHEN po.billing_cycle IS NOT NULL AND po.billing_cycle != 'one_time' THEN 1
                       ELSE 0
                   END as is_subscription,
                   CASE 
                       WHEN po.has_own_stock = 1 AND po.manage_stock = 1 THEN po.stock_quantity
                       WHEN pat.has_global_stock = 1 THEN pat.global_stock_quantity
                       ELSE 999
                   END as available_stock
            FROM cart_items ci
            JOIN products p ON ci.product_id = p.id
            JOIN product_categories pc ON p.category_id = pc.id
            JOIN product_types pt ON p.product_type_id = pt.id
            INNER JOIN product_offers po ON ci.product_offer_id = po.id
            INNER JOIN product_access_types pat ON po.access_type_id = pat.id
            WHERE (ci.user_id = ? OR ci.session_id = ?) 
            AND p.is_active = 1 
            AND po.is_active = 1 
            AND pat.is_active = 1
        ");
        
        $stmt->execute([$this->userId, $this->sessionId]);
        $items = $stmt->fetchAll();
        
                foreach ($items as $item) {
            if (!$item['offer_id']) {
                throw new Exception("Produit {$item['name']} sans offre valide");
            }
            
            if (($item['manage_stock'] || $item['has_global_stock']) && $item['available_stock'] < 999) {
                if ($item['available_stock'] < $item['quantity']) {
                    throw new Exception("Stock insuffisant pour {$item['name']}");
                }
            }
            
            if ($item['is_subscription'] && !$this->userId) {
                throw new Exception('Connexion requise pour les abonnements');
            }
            
            if ($item['is_subscription'] && $item['quantity'] > 1) {
                throw new Exception("Les abonnements ne peuvent pas avoir une quantité supérieure à 1");
            }
        }
        
        return $items;
    }
    
    private function calculateOrderTotals($cartItems) {
        $subtotal = 0;
        $setupFees = 0;
        $subscriptionCount = 0;
        $monthlyRecurring = 0;
        
        foreach ($cartItems as $item) {
            $subtotal += $item['total_price'];
            
            if ($item['is_subscription']) {
                $setupFees += $item['setup_fee'] ?? 0;
                $subscriptionCount++;
                $monthlyRecurring += $item['unit_price'];
            } else {
                $setupFees += ($item['setup_fee'] ?? 0) * $item['quantity'];
            }
        }
        
                $couponData = null;
        $discountAmount = 0;
        $couponCode = null;
        
        if (!empty($_SESSION['applied_coupon']['code'])) {
            $sessionCouponCode = $_SESSION['applied_coupon']['code'];
            
                        $couponValidation = $this->validateAndApplyCoupon($sessionCouponCode, $cartItems, $subtotal);
            
            if ($couponValidation['valid']) {
                $couponData = $couponValidation['coupon'];
                $discountAmount = $couponValidation['discount_amount'];
                $couponCode = $sessionCouponCode;
            } else {
                                unset($_SESSION['applied_coupon']);
                
                logSecurityEvent($this->userId, 'coupon_invalidated_at_checkout', [
                    'code' => $sessionCouponCode,
                    'reason' => $couponValidation['error']
                ], 'warning');
            }
        }
        
        $taxAmount = 0;
        $total = max(0, $subtotal + $setupFees - $discountAmount);
        
        return [
            'subtotal' => $subtotal,
            'setup_fees' => $setupFees,
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode,
            'coupon_id' => $couponData['id'] ?? null,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'subscription_count' => $subscriptionCount,
            'monthly_recurring' => $monthlyRecurring,
            'currency' => 'EUR'
        ];
    }
    
        private function validateAndApplyCoupon($couponCode, $cartItems, $subtotal) {
        try {
                        $stmt = $this->db->prepare("
                SELECT *
                FROM coupons
                WHERE code = ?
                AND is_active = 1
                AND (start_date IS NULL OR start_date <= NOW())
                AND (end_date IS NULL OR end_date >= NOW())
                AND (usage_limit IS NULL OR usage_count < usage_limit)
            ");
            $stmt->execute([strtoupper($couponCode)]);
            $coupon = $stmt->fetch();
            
            if (!$coupon) {
                return [
                    'valid' => false,
                    'error' => 'Code promo invalide, expiré ou limite atteinte'
                ];
            }
            
                        if ($this->userId && $coupon['usage_limit_per_user']) {
                $usageStmt = $this->db->prepare("
                    SELECT COUNT(*) as usage_count
                    FROM coupon_usages
                    WHERE coupon_id = ? AND user_id = ?
                ");
                $usageStmt->execute([$coupon['id'], $this->userId]);
                $userUsage = $usageStmt->fetchColumn();
                
                if ($userUsage >= $coupon['usage_limit_per_user']) {
                    return [
                        'valid' => false,
                        'error' => 'Vous avez déjà utilisé ce code le nombre maximum de fois'
                    ];
                }
            }
            
                        if ($coupon['minimum_amount'] && $subtotal < $coupon['minimum_amount']) {
                return [
                    'valid' => false,
                    'error' => sprintf('Montant minimum de %.2f€ requis', $coupon['minimum_amount'])
                ];
            }
            
                        if ($coupon['requires_account'] && !$this->userId) {
                return [
                    'valid' => false,
                    'error' => 'Ce code nécessite un compte utilisateur'
                ];
            }
            
                        if ($coupon['exclude_subscriptions']) {
                foreach ($cartItems as $item) {
                    if ($item['is_subscription']) {
                        return [
                            'valid' => false,
                            'error' => 'Ce code ne s\'applique pas aux abonnements'
                        ];
                    }
                }
            }
            
                        if ($coupon['target_categories']) {
                $targetCategories = json_decode($coupon['target_categories'], true);
                if (!empty($targetCategories)) {
                    $hasValidCategory = false;
                    foreach ($cartItems as $item) {
                        if (in_array($item['category_id'], $targetCategories)) {
                            $hasValidCategory = true;
                            break;
                        }
                    }
                    
                    if (!$hasValidCategory) {
                        return [
                            'valid' => false,
                            'error' => 'Ce code ne s\'applique pas à vos catégories de produits'
                        ];
                    }
                }
            }
            
                        if ($coupon['target_products']) {
                $targetProducts = json_decode($coupon['target_products'], true);
                if (!empty($targetProducts)) {
                    $hasValidProduct = false;
                    foreach ($cartItems as $item) {
                        if (in_array($item['product_id'], $targetProducts)) {
                            $hasValidProduct = true;
                            break;
                        }
                    }
                    
                    if (!$hasValidProduct) {
                        return [
                            'valid' => false,
                            'error' => 'Ce code ne s\'applique pas à vos produits'
                        ];
                    }
                }
            }
            
                        if ($coupon['target_product_types']) {
                $targetTypes = json_decode($coupon['target_product_types'], true);
                if (!empty($targetTypes)) {
                    $hasValidType = false;
                    foreach ($cartItems as $item) {
                        if (in_array($item['product_type_id'], $targetTypes)) {
                            $hasValidType = true;
                            break;
                        }
                    }
                    
                    if (!$hasValidType) {
                        return [
                            'valid' => false,
                            'error' => 'Ce code ne s\'applique pas aux types de vos produits'
                        ];
                    }
                }
            }
            
            $discountAmount = 0;
            $couponValue = max(0, (float)$coupon['discount_value']);
            $maxDiscount = isset($coupon['max_discount_amount']) ? max(0, (float)$coupon['max_discount_amount']) : 0;

            switch ($coupon['discount_type']) {
                case 'percentage':
                    $pct = min(100, $couponValue);
                    $discountAmount = ($subtotal * $pct) / 100;
                    if ($maxDiscount > 0 && $discountAmount > $maxDiscount) {
                        $discountAmount = $maxDiscount;
                    }
                    break;

                case 'fixed':
                    $discountAmount = $couponValue;
                    if ($discountAmount > $subtotal) {
                        $discountAmount = $subtotal;
                    }
                    break;

                case 'free_shipping':
                    $discountAmount = 0;
                    break;
            }

            $discountAmount = max(0, min($subtotal, round($discountAmount, 2)));
            
            return [
                'valid' => true,
                'coupon' => $coupon,
                'discount_amount' => $discountAmount
            ];
            
        } catch (Exception $e) {
            logSecurityEvent($this->userId, 'coupon_validation_error', [
                'code' => $couponCode,
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'valid' => false,
                'error' => 'Erreur lors de la validation du code'
            ];
        }
    }
    
        private function recordCouponUsage($couponId, $orderId, $discountAmount) {
        try {
                        $updateStmt = $this->db->prepare("
                UPDATE coupons 
                SET usage_count = usage_count + 1,
                    last_used_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$couponId]);
            
                        $insertStmt = $this->db->prepare("
                INSERT INTO coupon_usages (
                    coupon_id, user_id, order_id, discount_amount,
                    ip_address, user_agent
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $insertStmt->execute([
                $couponId,
                $this->userId,
                $orderId,
                $discountAmount,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            logSecurityEvent($this->userId, 'coupon_used', [
                'coupon_id' => $couponId,
                'order_id' => $orderId,
                'discount_amount' => $discountAmount
            ], 'success');
            
        } catch (Exception $e) {
            logSecurityEvent($this->userId, 'coupon_usage_recording_error', [
                'coupon_id' => $couponId,
                'order_id' => $orderId,
                'error' => $e->getMessage()
            ], 'failed');
        }
    }
    
    private function createOrderRecord($orderNumber, $orderData, $totals) {
        $stmt = $this->db->prepare("
            INSERT INTO orders (
                order_number, user_id, status, payment_status, payment_method,
                currency, subtotal, tax_amount, discount_amount, total_amount,
                coupon_code, notes, billing_address, shipping_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $billingJson = json_encode($orderData['billing_address']);
        $shippingJson = json_encode($orderData['billing_address']);
        
        $stmt->execute([
            $orderNumber,
            $this->userId,
            'pending',
            'pending',
            $orderData['payment_method'],
            $totals['currency'],
            $totals['subtotal'],
            $totals['tax_amount'],
            $totals['discount_amount'],
            $totals['total'],
            $totals['coupon_code'],
            $orderData['order_notes'] ?? '',
            $billingJson,
            $shippingJson
        ]);
        
        return $this->db->lastInsertId();
    }
    
    private function createOrderItems($orderId, $cartItems) {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_offer_id, product_name, product_sku,
                product_type, quantity, unit_price, total_price, product_data,
                delivery_status, billing_cycle, setup_fee, trial_days,
                access_type_name, offer_name
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($cartItems as $item) {
            $productData = [
                'category_name' => $item['category_name'],
                'delivery_method' => $item['default_delivery_method'],
                'is_subscription' => $item['is_subscription'],
                'custom_data' => $item['custom_data'] ? json_decode($item['custom_data'], true) : null
            ];
            
            $deliveryStatus = $item['default_delivery_method'] === 'automatic' ? 'processing' : 'pending';
            
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
    
    private function processPayment($orderId, $orderData, $totals) {
        $paymentMethod = $orderData['payment_method'];
        
        switch ($paymentMethod) {
            case 'stripe':
                return $this->processStripePayment($orderId, $orderData, $totals);
            case 'paypal':
                return $this->processPayPalPayment($orderId, $orderData, $totals);
            case 'bank_transfer':
                return $this->processBankTransferPayment($orderId, $orderData, $totals);
            case 'bitcoin':
                return $this->processBitcoinPayment($orderId, $orderData, $totals);
            default:
                throw new Exception('Méthode de paiement non supportée');
        }
    }
    
    private function processStripePayment($orderId, $orderData, $totals) {
        $stmt = $this->db->prepare("SELECT config FROM payment_adapters WHERE slug = 'stripe'");
        $stmt->execute();
        $config = json_decode($stmt->fetchColumn(), true);
        
        require_once '../../../plugins/stripe-php/init.php';
        \Stripe\Stripe::setApiKey($config['secret_key']);
        
        try {
            $customerId = $this->getOrCreateStripeCustomer($config);
            
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => round($totals['total'] * 100),
                'currency' => strtolower($totals['currency']),
                'customer' => $customerId,
                'setup_future_usage' => $totals['subscription_count'] > 0 ? 'off_session' : null,
                'metadata' => [
                    'order_id' => $orderId,
                    'user_id' => $this->userId,
                    'coupon_code' => $totals['coupon_code'],
                    'discount_amount' => $totals['discount_amount']
                ]
            ]);
            
            $this->createPaymentTransaction($orderId, 'stripe', [
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'customer_id' => $customerId
            ], $totals);
            
            return [
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            throw new Exception('Erreur Stripe: ' . $e->getMessage());
        }
    }
    
    private function getOrCreateStripeCustomer($config) {
        if (!$this->userId) {
            throw new Exception('Utilisateur requis pour Stripe');
        }
        
        $stmt = $this->db->prepare("SELECT stripe_customer_id FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        $customerId = $stmt->fetchColumn();
        
        if ($customerId) {
            return $customerId;
        }
        
        $user = $this->getUserData();
        $customer = \Stripe\Customer::create([
            'email' => $user['email'],
            'name' => $user['first_name'] . ' ' . $user['last_name'],
            'metadata' => ['user_id' => $this->userId]
        ]);
        
        $updateStmt = $this->db->prepare("UPDATE users SET stripe_customer_id = ? WHERE id = ?");
        $updateStmt->execute([$customer->id, $this->userId]);
        
        return $customer->id;
    }
    
    private function getUserData() {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$this->userId]);
        return $stmt->fetch();
    }
    
    private function processPayPalPayment($orderId, $orderData, $totals) {
        $this->createPaymentTransaction($orderId, 'paypal', [
            'amount' => $totals['total'],
            'currency' => $totals['currency']
        ], $totals);
        
        return [
            'paypal_approval_url' => SITE_URL . "/payments/paypal/redirect?order_id={$orderId}"
        ];
    }
    
    private function processBankTransferPayment($orderId, $orderData, $totals) {
        $stmt = $this->db->prepare("SELECT config FROM payment_adapters WHERE slug = 'bank_transfer'");
        $stmt->execute();
        $config = json_decode($stmt->fetchColumn(), true);
        
        $reference = "PS-{$orderId}-" . date('Ymd');
        
        $this->createPaymentTransaction($orderId, 'bank_transfer', [
            'type' => 'bank_transfer',
            'iban' => $config['iban'],
            'bic' => $config['bic'],
            'account_holder' => $config['account_holder'],
            'amount' => $totals['total'],
            'reference' => $reference
        ], $totals);
        
        return ['payment_reference' => $reference];
    }
    
    private function processBitcoinPayment($orderId, $orderData, $totals) {
        $address = "bc1q" . bin2hex(random_bytes(32));
        
        $this->createPaymentTransaction($orderId, 'bitcoin', [
            'type' => 'bitcoin',
            'address' => $address,
            'amount_eur' => $totals['total'],
            'amount_btc' => $totals['total'] / 45000
        ], $totals);
        
        return ['bitcoin_address' => $address];
    }
    
    private function createPaymentTransaction($orderId, $method, $metadata, $totals) {
        $stmt = $this->db->prepare("SELECT id FROM payment_adapters WHERE slug = ?");
        $stmt->execute([$method]);
        $adapterId = $stmt->fetchColumn();
        
        $transactionId = 'TX_' . generateSecureToken(12);
        
        $stmt = $this->db->prepare("
            INSERT INTO payment_transactions (
                transaction_id, order_id, payment_adapter_id, user_id,
                type, status, amount, currency, net_amount, metadata
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $transactionId,
            $orderId,
            $adapterId,
            $this->userId,
            'payment',
            'pending',
            $totals['total'],
            $totals['currency'],
            $totals['total'],
            json_encode($metadata)
        ]);
        
        return $transactionId;
    }
    
    private function createSubscriptions($orderId, $cartItems) {
        foreach ($cartItems as $item) {
            if ($item['is_subscription']) {
                $stmt = $this->db->prepare("
                    SELECT id FROM order_items 
                    WHERE order_id = ? AND product_id = ? AND product_offer_id = ?
                ");
                $stmt->execute([$orderId, $item['product_id'], $item['product_offer_id']]);
                $orderItemId = $stmt->fetchColumn();
                
                $subscriptionNumber = 'SUB_' . generateSecureToken(8);
                
                $startDate = new DateTime();
                $nextBillingDate = clone $startDate;
                
                if ($item['trial_days'] > 0) {
                    $nextBillingDate->add(new DateInterval("P{$item['trial_days']}D"));
                } else {
                    $nextBillingDate->add(new DateInterval('P1M'));
                }
                
                $stmt = $this->db->prepare("
                    INSERT INTO subscriptions (
                        user_id, order_item_id, product_offer_id, subscription_number,
                        status, billing_cycle, amount, currency, start_date, next_billing_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->execute([
                    $this->userId,
                    $orderItemId,
                    $item['product_offer_id'],
                    $subscriptionNumber,
                    'active',
                    $item['billing_cycle'],
                    $item['unit_price'],
                    'EUR',
                    $startDate->format('Y-m-d H:i:s'),
                    $nextBillingDate->format('Y-m-d H:i:s')
                ]);
            }
        }
    }
    
    private function saveBillingAddress($billingData) {
        $isDefault = 1;
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users_billing WHERE user_id = ?");
        $stmt->execute([$this->userId]);
        if ($stmt->fetchColumn() > 0) {
            $isDefault = 0;
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO users_billing (
                user_id, company_name, first_name, last_name,
                address_line1, address_line2, city, state, postal_code,
                country, phone, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $this->userId,
            $billingData['company_name'] ?? '',
            $billingData['first_name'],
            $billingData['last_name'],
            $billingData['address_line1'],
            $billingData['address_line2'] ?? '',
            $billingData['city'],
            $billingData['state'] ?? '',
            $billingData['postal_code'],
            $billingData['country'],
            $billingData['phone'] ?? '',
            $isDefault
        ]);
    }
    
    private function clearCart() {
        $stmt = $this->db->prepare("
            DELETE FROM cart_items 
            WHERE user_id = ? OR session_id = ?
        ");
        $stmt->execute([$this->userId, $this->sessionId]);
        
                unset($_SESSION['applied_coupon']);
    }
    
    private function sendOrderNotifications($orderId, $orderNumber) {
            }
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide');
    }
    
    $orderManager = new OrderManager();
    $result = $orderManager->createOrder($input);
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logSecurityEvent(null, 'checkout_api_error', [
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