<?php

define('API_ACCESS', true);
require_once '../../config.php';

header('Content-Type: application/json');

function logWebhook($event_type, $data, $status = 'received') {
    error_log("Stripe Webhook [{$status}]: {$event_type} - " . json_encode($data));
}

try {
    $db = Database::getInstance();
    
        $stmt = $db->prepare("SELECT config FROM payment_adapters WHERE slug = 'stripe' AND is_active = 1");
    $stmt->execute();
    $config = json_decode($stmt->fetchColumn(), true);
    
    if (!$config) {
        throw new Exception('Stripe non configuré');
    }
    
    require_once '../../../../plugins/stripe-php/init.php';
    \Stripe\Stripe::setApiKey($config['secret_key']);
    
        $payload = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $webhookSecret = $config['webhook_secret'] ?? '';
    
    if (empty($webhookSecret)) {
        logWebhook('config_error', ['error' => 'Webhook secret manquant'], 'error');
        http_response_code(400);
        echo json_encode(['error' => 'Webhook secret manquant']);
        exit();
    }
    
        $event = null;
    try {
        $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
    } catch (\Stripe\Exception\SignatureVerificationException $e) {
        logWebhook('signature_error', ['error' => $e->getMessage()], 'error');
        http_response_code(400);
        echo json_encode(['error' => 'Signature invalide']);
        exit();
    }
    
    $existingStmt = $db->prepare("
        SELECT id, status FROM payment_webhooks
        WHERE webhook_id = ?
        LIMIT 1
    ");
    $existingStmt->execute([$event['id']]);
    $existing = $existingStmt->fetch();
    if ($existing) {
        logWebhook($event['type'], [
            'webhook_id' => $existing['id'],
            'stripe_event_id' => $event['id'],
            'status' => 'duplicate_ignored'
        ], 'duplicate');
        http_response_code(200);
        echo json_encode(['status' => 'duplicate', 'previous_status' => $existing['status']]);
        exit;
    }

    $webhookStmt = $db->prepare("
        INSERT INTO payment_webhooks (payment_adapter_id, webhook_id, event_type, payload, signature, status)
        VALUES ((SELECT id FROM payment_adapters WHERE slug = 'stripe'), ?, ?, ?, ?, 'pending')
    ");
    $webhookStmt->execute([
        $event['id'],
        $event['type'],
        $payload,
        $signature
    ]);
    $webhookId = $db->lastInsertId();
    
    logWebhook($event['type'], [
        'webhook_id' => $webhookId,
        'stripe_event_id' => $event['id']
    ]);
    
        $result = handleStripeEvent($event, $db);
    
        $updateStmt = $db->prepare("
        UPDATE payment_webhooks 
        SET status = ?, processed_at = NOW(), error_message = ?
        WHERE id = ?
    ");
    $updateStmt->execute([
        $result['success'] ? 'processed' : 'failed',
        $result['success'] ? null : $result['error'],
        $webhookId
    ]);
    
    if ($result['success']) {
        logWebhook($event['type'], ['result' => 'success'], 'processed');
        http_response_code(200);
        echo json_encode(['status' => 'success']);
    } else {
        logWebhook($event['type'], ['error' => $result['error']], 'failed');
        http_response_code(400);
        echo json_encode(['error' => $result['error']]);
    }
    
} catch (Exception $e) {
    logWebhook('system_error', ['error' => $e->getMessage()], 'error');
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}

function handleStripeEvent($event, $db) {
    try {
        switch ($event['type']) {
                        case 'payment_intent.succeeded':
                return handlePaymentSucceeded($event['data']['object'], $db);
                
            case 'payment_intent.payment_failed':
                return handlePaymentFailed($event['data']['object'], $db);
                
                        case 'invoice.payment_succeeded':
                return handleInvoicePaymentSucceeded($event['data']['object'], $db);
                
            case 'invoice.payment_failed':
                return handleInvoicePaymentFailed($event['data']['object'], $db);
                
            case 'customer.subscription.created':
                return handleSubscriptionCreated($event['data']['object'], $db);
                
            case 'customer.subscription.updated':
                return handleSubscriptionUpdated($event['data']['object'], $db);
                
            case 'customer.subscription.deleted':
                return handleSubscriptionDeleted($event['data']['object'], $db);
                
                        case 'payment_method.attached':
                return handlePaymentMethodAttached($event['data']['object'], $db);
                
            case 'setup_intent.succeeded':
                return handleSetupIntentSucceeded($event['data']['object'], $db);
                
            default:
                return ['success' => true, 'message' => 'Événement ignoré'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function handlePaymentSucceeded($paymentIntent, $db) {
    $orderId = $paymentIntent['metadata']['order_id'] ?? null;
    
    if (!$orderId) {
        return ['success' => false, 'error' => 'Order ID manquant'];
    }
    
    $db->beginTransaction();
    
    try {
                $stmt = $db->prepare("
            UPDATE payment_transactions 
            SET status = 'completed', 
                gateway_transaction_id = ?, 
                gateway_response = ?,
                processed_at = NOW()
            WHERE order_id = ? AND status = 'pending'
        ");
        $stmt->execute([
            $paymentIntent['id'],
            json_encode($paymentIntent),
            $orderId
        ]);
        
                $orderStmt = $db->prepare("
            UPDATE orders 
            SET payment_status = 'paid', 
                status = 'processing',
                payment_reference = ?
            WHERE id = ?
        ");
        $orderStmt->execute([$paymentIntent['id'], $orderId]);
        
                triggerAutomaticDelivery($orderId, $db);
        
                sendOrderConfirmation($orderId, $db);
        
        $db->commit();
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handlePaymentFailed($paymentIntent, $db) {
    $orderId = $paymentIntent['metadata']['order_id'] ?? null;
    
    if (!$orderId) {
        return ['success' => false, 'error' => 'Order ID manquant'];
    }
    
        $stmt = $db->prepare("
        UPDATE payment_transactions 
        SET status = 'failed', 
            gateway_transaction_id = ?, 
            gateway_response = ?,
            failed_reason = ?,
            processed_at = NOW()
        WHERE order_id = ? AND status = 'pending'
    ");
    $stmt->execute([
        $paymentIntent['id'],
        json_encode($paymentIntent),
        $paymentIntent['last_payment_error']['message'] ?? 'Paiement échoué',
        $orderId
    ]);
    
        $orderStmt = $db->prepare("
        UPDATE orders 
        SET payment_status = 'failed', 
            status = 'failed'
        WHERE id = ?
    ");
    $orderStmt->execute([$orderId]);
    
    return ['success' => true];
}

function handleInvoicePaymentSucceeded($invoice, $db) {
    $subscriptionId = $invoice['subscription'];
    
    if (!$subscriptionId) {
        return ['success' => true, 'message' => 'Pas un abonnement'];
    }
    
        $stmt = $db->prepare("
        SELECT s.*, pt.metadata
        FROM subscriptions s
        LEFT JOIN payment_transactions pt ON s.order_item_id = pt.order_id
        WHERE JSON_EXTRACT(pt.metadata, '$.stripe_subscription_id') = ?
    ");
    $stmt->execute([$subscriptionId]);
    $subscription = $stmt->fetch();
    
    if (!$subscription) {
        return ['success' => false, 'error' => 'Abonnement non trouvé'];
    }
    
        $nextBillingDate = new DateTime();
    $nextBillingDate->add(new DateInterval('P1M'));
    
        $updateStmt = $db->prepare("
        UPDATE subscriptions 
        SET last_billing_date = NOW(),
            next_billing_date = ?,
            billing_attempts = 0,
            status = 'active'
        WHERE id = ?
    ");
    $updateStmt->execute([
        $nextBillingDate->format('Y-m-d H:i:s'),
        $subscription['id']
    ]);
    
        createSubscriptionTransaction($subscription, $invoice, $db);
    
    return ['success' => true];
}

function handleInvoicePaymentFailed($invoice, $db) {
    $subscriptionId = $invoice['subscription'];
    
    if (!$subscriptionId) {
        return ['success' => true, 'message' => 'Pas un abonnement'];
    }
    
        $stmt = $db->prepare("
        SELECT s.*, pt.metadata
        FROM subscriptions s
        LEFT JOIN payment_transactions pt ON s.order_item_id = pt.order_id
        WHERE JSON_EXTRACT(pt.metadata, '$.stripe_subscription_id') = ?
    ");
    $stmt->execute([$subscriptionId]);
    $subscription = $stmt->fetch();
    
    if (!$subscription) {
        return ['success' => false, 'error' => 'Abonnement non trouvé'];
    }
    
        $updateStmt = $db->prepare("
        UPDATE subscriptions 
        SET billing_attempts = billing_attempts + 1
        WHERE id = ?
    ");
    $updateStmt->execute([$subscription['id']]);
    
        if ($subscription['billing_attempts'] >= 3) {
        $suspendStmt = $db->prepare("
            UPDATE subscriptions 
            SET status = 'failed'
            WHERE id = ?
        ");
        $suspendStmt->execute([$subscription['id']]);
        
                sendSubscriptionFailedNotification($subscription['user_id'], $subscription, $db);
    }
    
    return ['success' => true];
}

function handleSubscriptionCreated($stripeSubscription, $db) {
            
    $customerId = $stripeSubscription['customer'];
    
        $userStmt = $db->prepare("
        SELECT id FROM users 
        WHERE JSON_EXTRACT(metadata, '$.stripe_customer_id') = ?
    ");
    $userStmt->execute([$customerId]);
    $userId = $userStmt->fetchColumn();
    
    if ($userId) {
                $updateStmt = $db->prepare("
            UPDATE subscriptions 
            SET metadata = JSON_SET(COALESCE(metadata, '{}'), '$.stripe_subscription_id', ?)
            WHERE user_id = ? AND status = 'active'
            AND metadata IS NULL OR JSON_EXTRACT(metadata, '$.stripe_subscription_id') IS NULL
            ORDER BY created_at DESC LIMIT 1
        ");
        $updateStmt->execute([$stripeSubscription['id'], $userId]);
    }
    
    return ['success' => true];
}

function handleSubscriptionUpdated($stripeSubscription, $db) {
    $subscriptionId = $stripeSubscription['id'];
    
        $stmt = $db->prepare("
        SELECT id FROM subscriptions 
        WHERE JSON_EXTRACT(metadata, '$.stripe_subscription_id') = ?
    ");
    $stmt->execute([$subscriptionId]);
    $localSubscriptionId = $stmt->fetchColumn();
    
    if ($localSubscriptionId) {
                $status = $stripeSubscription['status'];
        $localStatus = 'active';
        
        switch ($status) {
            case 'active':
                $localStatus = 'active';
                break;
            case 'canceled':
                $localStatus = 'cancelled';
                break;
            case 'past_due':
                $localStatus = 'failed';
                break;
            case 'unpaid':
                $localStatus = 'failed';
                break;
        }
        
        $updateStmt = $db->prepare("
            UPDATE subscriptions 
            SET status = ?
            WHERE id = ?
        ");
        $updateStmt->execute([$localStatus, $localSubscriptionId]);
    }
    
    return ['success' => true];
}

function handleSubscriptionDeleted($stripeSubscription, $db) {
    $subscriptionId = $stripeSubscription['id'];
    
        $stmt = $db->prepare("
        UPDATE subscriptions 
        SET status = 'cancelled',
            cancelled_at = NOW(),
            cancellation_reason = 'stripe_cancellation'
        WHERE JSON_EXTRACT(metadata, '$.stripe_subscription_id') = ?
    ");
    $stmt->execute([$subscriptionId]);
    
    return ['success' => true];
}

function handlePaymentMethodAttached($paymentMethod, $db) {
        return ['success' => true];
}

function handleSetupIntentSucceeded($setupIntent, $db) {
    $customerId = $setupIntent['customer'];
    $paymentMethodId = $setupIntent['payment_method'];
    
        logSecurityEvent(null, 'stripe_card_setup_succeeded', [
        'customer_id' => $customerId,
        'payment_method_id' => $paymentMethodId
    ], 'success');
    
    return ['success' => true];
}

function triggerAutomaticDelivery($orderId, $db) {
    $stmt = $db->prepare("
        SELECT oi.*, p.product_type_id, pt.delivery_method
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN product_types pt ON p.product_type_id = pt.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$orderId]);
    $items = $stmt->fetchAll();
    
    foreach ($items as $item) {
        if ($item['delivery_method'] === 'automatic') {
                        $deliveryStmt = $db->prepare("
                UPDATE order_items 
                SET delivery_status = 'delivered',
                    delivered_at = NOW()
                WHERE id = ?
            ");
            $deliveryStmt->execute([$item['id']]);
            
                        reserveInventoryForOrder($item, $db);
        }
    }
}

function reserveInventoryForOrder($orderItem, $db) {
        $stmt = $db->prepare("
        SELECT id FROM product_inventory 
        WHERE product_id = ? 
        AND (product_offer_id = ? OR product_offer_id IS NULL)
        AND status = 'available'
        ORDER BY created_at ASC
        LIMIT ?
    ");
    $stmt->execute([
        $orderItem['product_id'],
        $orderItem['product_offer_id'],
        $orderItem['quantity']
    ]);
    $inventoryItems = $stmt->fetchAll();
    
    foreach ($inventoryItems as $inventoryItem) {
        $updateStmt = $db->prepare("
            UPDATE product_inventory 
            SET status = 'sold',
                sold_in_order_id = ?,
                sold_to_user_id = (SELECT user_id FROM orders WHERE id = ?)
            WHERE id = ?
        ");
        $updateStmt->execute([
            $orderItem['order_id'],
            $orderItem['order_id'],
            $inventoryItem['id']
        ]);
    }
}

function createSubscriptionTransaction($subscription, $invoice, $db) {
    $transactionId = 'SUB_' . generateSecureToken(12);
    
    $stmt = $db->prepare("
        INSERT INTO payment_transactions (
            transaction_id, order_id, payment_adapter_id, user_id,
            type, status, amount, currency, net_amount, 
            gateway_transaction_id, gateway_response
        ) VALUES (?, ?, 
            (SELECT id FROM payment_adapters WHERE slug = 'stripe'),
            ?, 'payment', 'completed', ?, 'EUR', ?, ?, ?)
    ");
    
    $amount = $invoice['amount_paid'] / 100;     
    $stmt->execute([
        $transactionId,
        $subscription['order_item_id'],
        $subscription['user_id'],
        $amount,
        $amount,
        $invoice['id'],
        json_encode($invoice)
    ]);
}

function sendOrderConfirmation($orderId, $db) {
    }

function sendSubscriptionFailedNotification($userId, $subscription, $db) {
        $stmt = $db->prepare("
        INSERT INTO notifications (user_id, type, title, message, data)
        VALUES (?, 'subscription_failed', ?, ?, ?)
    ");
    $stmt->execute([
        $userId,
        'Problème de paiement',
        'Votre abonnement ' . $subscription['subscription_number'] . ' rencontre un problème de paiement.',
        json_encode(['subscription_id' => $subscription['id']])
    ]);
}
?>