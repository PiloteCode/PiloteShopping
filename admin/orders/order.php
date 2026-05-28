<?php
define('API_ACCESS', true);
require_once '../../api/v1/config.php';
require_once '../../includes/import.php';
require_once '../../includes/AuthMiddleware.php';
require_once '../includes/AdminAuthMiddleware.php';

require_once '../../api/v1/mail/MailerManager.php';
require_once '../../api/v1/mail/QueueProcessor.php';

$adminAuth = initAdminAuth();
$adminAuth->requireAdminAuth(['admin', 'super_admin', 'moderator']);

$currentAdmin = $adminAuth->getCurrentAdmin();
$currentUser = $adminAuth->getCurrentUser();
function sendDeliveryNotificationEmail($orderData, $itemData, $deliveryData) {
    try {
        $queue = new EmailQueueProcessor();
        
                $variables = [
            'FIRST_NAME' => $orderData['first_name'],
            'ORDER_NUMBER' => $orderData['order_number'],
            'PRODUCT_NAME' => $itemData['product_name'],
            'DELIVERY_TYPE' => $deliveryData['type'],
            'DELIVERY_DATE' => date('d/m/Y à H:i'),
            'DELIVERY_CONTENT_TEXT' => formatDeliveryContentForText($deliveryData),
            'ORDER_URL' => SITE_URL . '/orders/' . $orderData['id'],
            'DASHBOARD_URL' => SITE_URL . '/dashboard',
            'SUPPORT_EMAIL' => SITE_EMAIL
        ];
        
                $emailData = [
            'template' => 'delivery_notification',             'to_email' => $orderData['email'],
            'to_name' => trim($orderData['first_name'] . ' ' . $orderData['last_name']),
            'variables' => $variables,
            'options' => [
                'category' => 'transactional',
                'priority' => 1,                 'tracking' => true,
                'from_email' => SITE_EMAIL,
                'from_name' => SITE_NAME
            ]
        ];
        
                $result = $queue->addToQueue($emailData, 1);
        
        if ($result['success']) {
            error_log("Delivery notification email queued for order #{$orderData['order_number']} - Queue ID: {$result['queue_id']} - User: {$orderData['email']}");
            return [
                'success' => true,
                'message' => 'Email de notification de livraison mis en queue',
                'queue_id' => $result['queue_id']
            ];
        } else {
            error_log("Failed to queue delivery notification email: " . $result['error']);
            return [
                'success' => false,
                'error' => 'Impossible d\'ajouter l\'email à la queue: ' . $result['error']
            ];
        }
        
    } catch (Exception $e) {
        error_log("Delivery email queue error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Erreur lors de la mise en queue de l\'email: ' . $e->getMessage()
        ];
    }
}

function formatDeliveryContentForText($deliveryData) {
    $content = $deliveryData['content'];
    $type = $deliveryData['type'];
    
        $content = preg_replace('/\*\*(.*?)\*\*/', '$1', $content);
    $content = preg_replace('/\*(.*?)\*/', '$1', $content);
    $content = preg_replace('/`(.*?)`/', '$1', $content);
    $content = preg_replace('/^#{1,6}\s+(.*$)/m', '$1', $content);
    $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '$1 ($2)', $content);
    
    $typeName = ucfirst(str_replace('_', ' ', $type));
    
    $formattedContent = "=== " . strtoupper($typeName) . " ===\n\n";
    $formattedContent .= $content;
    
    if (!empty($deliveryData['notes'])) {
        $formattedContent .= "\n\n--- NOTES IMPORTANTES ---\n" . $deliveryData['notes'];
    }
    
    return $formattedContent;
}

function generateDeliveryEmailTemplate($orderData, $itemData, $deliveryData) {
    $deliveredDate = date('d/m/Y à H:i');
    $loginUrl = SITE_URL . '/auth/login';
    $dashboardUrl = SITE_URL . '/dashboard';
    $orderUrl = SITE_URL . '/dashboard/orders/' . $orderData['id'];
    
        $formattedContent = formatDeliveryContent($deliveryData);
    
    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Votre commande a été livrée</title>
    </head>
    <body style="font-family: Arial, sans-serif; background-color: #0A0A0A; color: #FFFFFF; margin: 0; padding: 20px;">
        <div style="max-width: 700px; margin: 0 auto; background-color: #1A1A1A; border-radius: 16px; padding: 40px; border: 1px solid #2A2A2A;">
            
            
            <div style="text-align: center; margin-bottom: 40px;">
                <div style="display: inline-block; background: linear-gradient(135deg, #10B981, #059669); padding: 20px; border-radius: 50%; margin-bottom: 20px; width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                    <span style="font-size: 32px; color: white;">📦</span>
                </div>
                <h1 style="color: #10B981; margin: 0; font-size: 32px; font-weight: bold;">Commande livrée !</h1>
                <p style="color: #9CA3AF; margin: 10px 0 0 0; font-size: 16px;">Votre article est maintenant disponible</p>
            </div>
            
            
            <div style="background-color: #2A2A2A; border-radius: 12px; padding: 24px; margin-bottom: 30px; border-left: 4px solid #10B981;">
                <h2 style="color: #FFFFFF; margin: 0 0 16px 0; font-size: 20px;">Détails de la commande</h2>
                <div style="display: grid; gap: 12px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #374151;">
                        <span style="color: #9CA3AF; font-size: 14px;">Numéro de commande:</span>
                        <span style="color: #FFFFFF; font-weight: bold; font-family: monospace;">#' . htmlspecialchars($orderData['order_number']) . '</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #374151;">
                        <span style="color: #9CA3AF; font-size: 14px;">Article livré:</span>
                        <span style="color: #FFFFFF; font-weight: bold;">' . htmlspecialchars($itemData['product_name']) . '</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid #374151;">
                        <span style="color: #9CA3AF; font-size: 14px;">Type de livraison:</span>
                        <span style="color: #10B981; font-weight: bold; text-transform: capitalize;">' . htmlspecialchars($deliveryData['type']) . '</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0;">
                        <span style="color: #9CA3AF; font-size: 14px;">Date de livraison:</span>
                        <span style="color: #FFFFFF; font-weight: bold;">' . $deliveredDate . '</span>
                    </div>
                </div>
            </div>
            
            
            <div style="background: linear-gradient(135deg, #1E3A8A, #1E40AF); border-radius: 12px; padding: 24px; margin-bottom: 30px; position: relative; overflow: hidden;">
                <div style="position: absolute; top: 0; right: 0; width: 100px; height: 100px; background: rgba(255,255,255,0.05); border-radius: 50%; transform: translate(30px, -30px);"></div>
                <div style="position: absolute; bottom: 0; left: 0; width: 80px; height: 80px; background: rgba(255,255,255,0.03); border-radius: 50%; transform: translate(-20px, 20px);"></div>
                
                <h3 style="color: #FFFFFF; margin: 0 0 20px 0; font-size: 18px; display: flex; align-items: center; position: relative; z-index: 1;">
                    <span style="background: rgba(255,255,255,0.2); padding: 8px; border-radius: 8px; margin-right: 12px;">🔐</span>
                    Vos informations d\'accès
                </h3>
                
                <div style="background: rgba(0,0,0,0.3); border-radius: 8px; padding: 20px; position: relative; z-index: 1;">
                    ' . $formattedContent . '
                </div>
                
                <div style="margin-top: 16px; padding: 12px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 8px; position: relative; z-index: 1;">
                    <p style="margin: 0; color: #FCA5A5; font-size: 14px; display: flex; align-items: center;">
                        <span style="margin-right: 8px;">⚠️</span>
                        <strong>Important:</strong> Conservez ces informations en lieu sûr. Ne les partagez jamais avec des tiers.
                    </p>
                </div>
            </div>
            
            
            <div style="background-color: #2A2A2A; border-radius: 12px; padding: 24px; margin-bottom: 30px;">
                <h3 style="color: #FFFFFF; margin: 0 0 16px 0; font-size: 18px;">📋 Prochaines étapes</h3>
                <div style="space-y: 12px;">
                    <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
                        <span style="background: #10B981; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; margin-right: 12px; flex-shrink: 0;">1</span>
                        <p style="margin: 0; color: #E5E7EB; line-height: 1.5;">Utilisez les informations ci-dessus pour accéder à votre produit</p>
                    </div>
                    <div style="display: flex; align-items: flex-start; margin-bottom: 12px;">
                        <span style="background: #10B981; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; margin-right: 12px; flex-shrink: 0;">2</span>
                        <p style="margin: 0; color: #E5E7EB; line-height: 1.5;">Consultez votre espace client pour plus de détails</p>
                    </div>
                    <div style="display: flex; align-items: flex-start;">
                        <span style="background: #10B981; color: white; border-radius: 50%; width: 24px; height: 24px; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: bold; margin-right: 12px; flex-shrink: 0;">3</span>
                        <p style="margin: 0; color: #E5E7EB; line-height: 1.5;">Contactez le support si vous rencontrez des difficultés</p>
                    </div>
                </div>
            </div>
            
            
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . $orderUrl . '" style="display: inline-block; background: linear-gradient(135deg, #10B981, #059669); color: white; text-decoration: none; padding: 16px 32px; border-radius: 12px; font-weight: bold; font-size: 16px; margin: 0 8px 16px 8px; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);">
                    📋 Voir ma commande
                </a>
                <br>
                <a href="' . $dashboardUrl . '" style="display: inline-block; background: rgba(75, 85, 99, 0.8); color: white; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; margin: 0 8px;">
                    🏠 Mon espace client
                </a>
            </div>
            
            
            <div style="background: linear-gradient(135deg, #7C3AED, #5B21B6); border-radius: 12px; padding: 20px; margin: 30px 0; text-align: center;">
                <h4 style="color: #FFFFFF; margin: 0 0 12px 0; font-size: 16px;">Besoin d\'aide ?</h4>
                <p style="color: #E0E7FF; margin: 0 0 16px 0; font-size: 14px;">
                    Notre équipe support est là pour vous accompagner
                </p>
                <a href="mailto:' . SITE_EMAIL . '" style="color: #FFFFFF; text-decoration: none; font-weight: bold; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 6px; display: inline-block;">
                    📧 Contacter le support
                </a>
            </div>
            
            
            <div style="border-top: 1px solid #374151; padding-top: 24px; margin-top: 40px; text-align: center;">
                <p style="color: #6B7280; margin: 0 0 8px 0; font-size: 14px;">
                    Cet email concerne votre commande sur <strong>' . SITE_NAME . '</strong>
                </p>
                <p style="color: #6B7280; margin: 0; font-size: 12px;">
                    ' . SITE_URL . ' | Support: ' . SITE_EMAIL . '
                </p>
                <div style="margin-top: 16px;">
                    <a href="' . SITE_URL . '" style="color: #10B981; text-decoration: none; font-size: 12px; margin: 0 8px;">Accueil</a>
                    <span style="color: #4B5563;">|</span>
                    <a href="' . SITE_URL . '/legal/terms" style="color: #10B981; text-decoration: none; font-size: 12px; margin: 0 8px;">CGV</a>
                    <span style="color: #4B5563;">|</span>
                    <a href="' . SITE_URL . '/legal/privacy" style="color: #10B981; text-decoration: none; font-size: 12px; margin: 0 8px;">Confidentialité</a>
                </div>
            </div>
            
        </div>
    </body>
    </html>';
}

function formatDeliveryContent($deliveryData) {
    $content = $deliveryData['content'];
    $type = $deliveryData['type'];
    $markdownEnabled = $deliveryData['markdown_enabled'] ?? false;
    
        if ($markdownEnabled) {
                $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
        $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
        $content = preg_replace('/`(.*?)`/', '<code style="background: rgba(255,255,255,0.1); padding: 2px 4px; border-radius: 3px; font-family: monospace;">$1</code>', $content);
        $content = preg_replace('/^### (.*$)/m', '<h4 style="color: #FFFFFF; margin: 16px 0 8px 0;">$1</h4>', $content);
        $content = preg_replace('/^## (.*$)/m', '<h3 style="color: #FFFFFF; margin: 20px 0 12px 0;">$1</h3>', $content);
        $content = preg_replace('/^# (.*$)/m', '<h2 style="color: #FFFFFF; margin: 24px 0 16px 0;">$1</h2>', $content);
        $content = preg_replace('/^- (.*$)/m', '<div style="margin: 4px 0; color: #E5E7EB;">• $1</div>', $content);
        $content = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" style="color: #60A5FA; text-decoration: underline;">$1</a>', $content);
        $content = nl2br($content);
    } else {
                $content = nl2br(htmlspecialchars($content));
    }
    
        $typeIcon = '';
    $typeColor = '#10B981';
    
    switch ($type) {
        case 'account':
            $typeIcon = '👤';
            $typeColor = '#3B82F6';
            break;
        case 'license_key':
            $typeIcon = '🔑';
            $typeColor = '#F59E0B';
            break;
        case 'download_link':
            $typeIcon = '📥';
            $typeColor = '#8B5CF6';
            break;
        case 'access_code':
            $typeIcon = '🎫';
            $typeColor = '#EF4444';
            break;
        case 'gift_card':
            $typeIcon = '🎁';
            $typeColor = '#EC4899';
            break;
        case 'credentials':
            $typeIcon = '🔐';
            $typeColor = '#10B981';
            break;
        default:
            $typeIcon = '📄';
            $typeColor = '#6B7280';
    }
    
    $formattedContent = '
    <div style="margin-bottom: 16px;">
        <div style="display: flex; align-items: center; margin-bottom: 12px;">
            <span style="font-size: 20px; margin-right: 8px;">' . $typeIcon . '</span>
            <span style="color: ' . $typeColor . '; font-weight: bold; text-transform: uppercase; font-size: 12px; letter-spacing: 1px;">' . htmlspecialchars($type) . '</span>
        </div>
        <div style="color: #FFFFFF; font-size: 14px; line-height: 1.6; font-family: monospace; background: rgba(0,0,0,0.2); padding: 16px; border-radius: 6px; border-left: 3px solid ' . $typeColor . ';">
            ' . $content . '
        </div>
    </div>';
    
        if (!empty($deliveryData['notes'])) {
        $formattedContent .= '
        <div style="margin-top: 16px; padding: 12px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px;">
            <p style="margin: 0; color: #93C5FD; font-size: 13px;">
                <strong>📝 Notes:</strong> ' . htmlspecialchars($deliveryData['notes']) . '
            </p>
        </div>';
    }
    
    return $formattedContent;
}

try {
    $db = Database::getInstance();
    
        
    $orderId = intval($_GET['id'] ?? 0);
    if (!$orderId) {
        header('Location: /admin/orders/?error=ID commande requis');
        exit();
    }
    
        $orderStmt = $db->prepare("
        SELECT o.*, 
               u.uuid as user_uuid, u.username, u.email, u.first_name, u.last_name, u.phone,
               u.balance as user_balance, u.created_at as user_created_at,
               pt.status as transaction_status, pt.gateway_transaction_id, pt.metadata as transaction_metadata,
               pt.amount as transaction_amount, pt.fees as transaction_fees,
               pa.name as payment_method_name, pa.slug as payment_method_slug
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN payment_transactions pt ON o.id = pt.order_id
        LEFT JOIN payment_adapters pa ON pt.payment_adapter_id = pa.id
        WHERE o.id = ?
        ORDER BY pt.created_at DESC
        LIMIT 1
    ");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    
    if (!$order) {
        header('Location: /admin/orders/?error=Commande non trouvée');
        exit();
    }
    
        $itemsStmt = $db->prepare("
        SELECT oi.*, 
               p.name as product_name, p.slug as product_slug, p.images as product_images, p.sku as product_sku,
               pc.name as category_name, pc.color as category_color,
               pt.name as type_name, pt.icon as type_icon,
               po.name as offer_name, po.billing_cycle, po.trial_days,
               pat.name as access_type_name, pat.access_method,
               s.subscription_number, s.status as subscription_status, s.next_billing_date,
               pi.inventory_data, pi.status as inventory_status, pi.access_count, pi.last_accessed_at
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        LEFT JOIN product_types pt ON p.product_type_id = pt.id
        LEFT JOIN product_offers po ON oi.product_offer_id = po.id
        LEFT JOIN product_access_types pat ON po.access_type_id = pat.id
        LEFT JOIN subscriptions s ON oi.id = s.order_item_id
        LEFT JOIN product_inventory pi ON (oi.id = pi.sold_in_order_id AND pi.status = 'sold')
        WHERE oi.order_id = ?
        ORDER BY oi.created_at ASC
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll();
    
        foreach ($orderItems as &$item) {
        $item['images'] = json_decode($item['product_images'] ?? '[]', true);
        $item['main_image'] = !empty($item['images']) ? $item['images'][0] : '/assets/images/product-placeholder.jpg';
        $item['has_subscription'] = !empty($item['subscription_number']);
        $item['has_inventory'] = !empty($item['inventory_data']);
        $item['inventory_decoded'] = $item['has_inventory'] ? json_decode($item['inventory_data'], true) : [];
    }
    
        $balanceTransactionsStmt = $db->prepare("
        SELECT * FROM balance_transactions 
        WHERE reference_type = 'order' AND reference_id = ?
        ORDER BY created_at DESC
    ");
    $balanceTransactionsStmt->execute([$orderId]);
    $balanceTransactions = $balanceTransactionsStmt->fetchAll();
    
        $paymentTransactionsStmt = $db->prepare("
        SELECT pt.*, pa.name as adapter_name
        FROM payment_transactions pt
        LEFT JOIN payment_adapters pa ON pt.payment_adapter_id = pa.id
        WHERE pt.order_id = ?
        ORDER BY pt.created_at DESC
    ");
    $paymentTransactionsStmt->execute([$orderId]);
    $paymentTransactions = $paymentTransactionsStmt->fetchAll();
    
        try {
        $adminLogsStmt = $db->prepare("
            SELECT al.*, au.user_id, u.username as admin_username
            FROM admin_logs al
            LEFT JOIN admin_users au ON al.admin_id = au.id
            LEFT JOIN users u ON au.user_id = u.id
            WHERE al.resource_type = 'order' AND al.resource_id = ?
            ORDER BY al.created_at DESC
            LIMIT 20
        ");
        $adminLogsStmt->execute([$orderId]);
        $adminLogs = $adminLogsStmt->fetchAll();
    } catch (Exception $e) {
        $adminLogs = [];
    }
    
        $billingAddress = json_decode($order['billing_address'] ?? '{}', true);
    $shippingAddress = json_decode($order['shipping_address'] ?? '{}', true);
    
    if (!is_array($billingAddress)) $billingAddress = [];
    if (!is_array($shippingAddress)) $shippingAddress = [];
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'update_status':
                    $newStatus = $_POST['new_status'] ?? '';
                    $validStatuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];
                    
                    if (in_array($newStatus, $validStatuses)) {
                        $oldStatus = $order['status'];
                        
                        $updateStmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                        $updateStmt->execute([$newStatus, $orderId]);
                        
                                                if ($newStatus === 'completed' && $oldStatus !== 'completed') {
                            $updateStmt = $db->prepare("UPDATE orders SET delivered_at = NOW() WHERE id = ? AND delivered_at IS NULL");
                            $updateStmt->execute([$orderId]);
                        } elseif ($newStatus === 'cancelled' && $oldStatus !== 'cancelled') {
                            $updateStmt = $db->prepare("UPDATE orders SET cancelled_at = NOW() WHERE id = ? AND cancelled_at IS NULL");
                            $updateStmt->execute([$orderId]);
                        }
                        
                        $adminAuth->logAction('order_status_updated', 'order', $orderId, 
                            ['old_status' => $oldStatus], 
                            ['new_status' => $newStatus]);
                        
                        $success = "Statut mis à jour: {$oldStatus} → {$newStatus}";
                        $order['status'] = $newStatus;
                    } else {
                        $error = "Statut invalide";
                    }
                    break;
                    
                case 'update_payment_status':
                    $newPaymentStatus = $_POST['new_payment_status'] ?? '';
                    $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];
                    
                    if (in_array($newPaymentStatus, $validPaymentStatuses)) {
                        $oldPaymentStatus = $order['payment_status'];
                        
                        $updateStmt = $db->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
                        $updateStmt->execute([$newPaymentStatus, $orderId]);
                        
                        if ($newPaymentStatus === 'refunded' && $oldPaymentStatus !== 'refunded') {
                            $updateStmt = $db->prepare("UPDATE orders SET refunded_at = NOW() WHERE id = ? AND refunded_at IS NULL");
                            $updateStmt->execute([$orderId]);
                        }
                        
                        $adminAuth->logAction('order_payment_status_updated', 'order', $orderId, 
                            ['old_payment_status' => $oldPaymentStatus], 
                            ['new_payment_status' => $newPaymentStatus]);
                        
                        $success = "Statut de paiement mis à jour: {$oldPaymentStatus} → {$newPaymentStatus}";
                        $order['payment_status'] = $newPaymentStatus;
                    } else {
                        $error = "Statut de paiement invalide";
                    }
                    break;
                    
                case 'update_item_delivery':
                    $itemId = intval($_POST['item_id']);
                    $newDeliveryStatus = $_POST['new_delivery_status'] ?? '';
                    $validDeliveryStatuses = ['pending', 'processing', 'delivered', 'failed', 'cancelled'];
                    
                    if ($itemId > 0 && in_array($newDeliveryStatus, $validDeliveryStatuses)) {
                        $updateStmt = $db->prepare("UPDATE order_items SET delivery_status = ?, updated_at = NOW() WHERE id = ? AND order_id = ?");
                        $updateStmt->execute([$newDeliveryStatus, $itemId, $orderId]);
                        
                        if ($newDeliveryStatus === 'delivered') {
                            $updateStmt = $db->prepare("UPDATE order_items SET delivered_at = NOW() WHERE id = ? AND delivered_at IS NULL");
                            $updateStmt->execute([$itemId]);
                        }
                        
                        $adminAuth->logAction('order_item_delivery_updated', 'order', $orderId, 
                            null, ['item_id' => $itemId, 'new_delivery_status' => $newDeliveryStatus]);
                        
                        $success = "Statut de livraison mis à jour";
                        
                                                foreach ($orderItems as &$item) {
                            if ($item['id'] == $itemId) {
                                $item['delivery_status'] = $newDeliveryStatus;
                                if ($newDeliveryStatus === 'delivered') {
                                    $item['delivered_at'] = date('Y-m-d H:i:s');
                                }
                                break;
                            }
                        }
                    } else {
                        $error = "Paramètres invalides pour la livraison";
                    }
                    break;
                    
                case 'deliver_item':
                    $itemId = intval($_POST['item_id']);
                    $deliveryType = $_POST['delivery_type'] ?? '';
                    $deliveryContent = trim($_POST['delivery_content'] ?? '');
                    $deliveryNotes = trim($_POST['delivery_notes'] ?? '');
                    $enableMarkdown = isset($_POST['enable_markdown']) ? 1 : 0;
                    
                    if ($itemId > 0 && !empty($deliveryType) && !empty($deliveryContent)) {
                        $transactionStarted = false;
                        
                        try {
                            $db->beginTransaction();
                            $transactionStarted = true;
                            
                                                        $inventoryData = [
                                'type' => $deliveryType,
                                'content' => $deliveryContent,
                                'notes' => $deliveryNotes,
                                'markdown_enabled' => $enableMarkdown,
                                'delivered_by' => $currentUser['username'],
                                'delivered_at' => date('Y-m-d H:i:s'),
                                'admin_id' => $currentAdmin['id']
                            ];
                            
                                                        $itemStmt = $db->prepare("
                                SELECT oi.*, p.name as product_name, p.slug as product_slug 
                                FROM order_items oi
                                LEFT JOIN products p ON oi.product_id = p.id
                                WHERE oi.id = ? AND oi.order_id = ?
                            ");
                            $itemStmt->execute([$itemId, $orderId]);
                            $currentItem = $itemStmt->fetch();
                            
                            if (!$currentItem) {
                                throw new Exception('Article non trouvé');
                            }
                            
                                                        $existingInventoryStmt = $db->prepare("
                                SELECT id FROM product_inventory 
                                WHERE sold_in_order_id = ? AND sold_to_user_id = ?
                                ORDER BY created_at DESC LIMIT 1
                            ");
                            $existingInventoryStmt->execute([$orderId, $order['user_id']]);
                            $existingInventory = $existingInventoryStmt->fetch();
                            
                            if ($existingInventory) {
                                                                $updateInventoryStmt = $db->prepare("
                                    UPDATE product_inventory 
                                    SET inventory_data = ?, status = 'sold', updated_at = NOW()
                                    WHERE id = ?
                                ");
                                $updateInventoryStmt->execute([json_encode($inventoryData), $existingInventory['id']]);
                            } else {
                                                                $itemOfferStmt = $db->prepare("SELECT product_offer_id FROM order_items WHERE id = ?");
                                $itemOfferStmt->execute([$itemId]);
                                $itemOffer = $itemOfferStmt->fetch();
                                $offerId = $itemOffer['product_offer_id'] ?? 1;
                                
                                                                $createInventoryStmt = $db->prepare("
                                    INSERT INTO product_inventory 
                                    (offer_id, inventory_type, inventory_data, status, sold_to_user_id, sold_in_order_id, created_at, updated_at)
                                    VALUES (?, ?, ?, 'sold', ?, ?, NOW(), NOW())
                                ");
                                
                                $createInventoryStmt->execute([
                                    $offerId, 
                                    $deliveryType, 
                                    json_encode($inventoryData), 
                                    $order['user_id'], 
                                    $orderId
                                ]);
                            }
                            
                                                        $updateItemStmt = $db->prepare("
                                UPDATE order_items 
                                SET delivery_status = 'delivered', delivered_at = NOW(), 
                                    delivery_data = ?, updated_at = NOW()
                                WHERE id = ? AND order_id = ?
                            ");
                            $updateItemStmt->execute([json_encode($inventoryData), $itemId, $orderId]);
                            
                            $db->commit();
                            $transactionStarted = false;
                            
                                                        
                                                        $orderDataForEmail = [
                                'id' => $order['id'],
                                'order_number' => $order['order_number'],
                                'email' => $order['email'],
                                'first_name' => $order['first_name'],
                                'last_name' => $order['last_name']
                            ];
                            
                            $itemDataForEmail = [
                                'id' => $currentItem['id'],
                                'product_name' => $currentItem['product_name'],
                                'product_slug' => $currentItem['product_slug']
                            ];
                            
                                                        $emailResult = sendDeliveryNotificationEmail($orderDataForEmail, $itemDataForEmail, $inventoryData);
                            
                            if ($emailResult['success']) {
                                error_log("Delivery notification email sent successfully for order #{$order['order_number']}");
                            } else {
                                error_log("Failed to send delivery notification email: " . $emailResult['error']);
                            }
                            
                                                        
                            $adminAuth->logAction('order_item_delivered', 'order', $orderId, 
                                null, [
                                    'item_id' => $itemId, 
                                    'delivery_type' => $deliveryType,
                                    'delivered_by' => $currentUser['username'],
                                    'email_sent' => $emailResult['success']
                                ], 'medium');
                            
                            $success = "Article livré avec succès" . ($emailResult['success'] ? " et email de notification envoyé" : " (email de notification non envoyé: " . $emailResult['error'] . ")");
                            
                                                        foreach ($orderItems as &$item) {
                                if ($item['id'] == $itemId) {
                                    $item['delivery_status'] = 'delivered';
                                    $item['delivered_at'] = date('Y-m-d H:i:s');
                                    $item['has_inventory'] = true;
                                    $item['inventory_decoded'] = $inventoryData;
                                    break;
                                }
                            }
                            
                        } catch (Exception $e) {
                            if ($transactionStarted) {
                                $db->rollback();
                            }
                            throw $e;
                        }
                    } else {
                        $error = "Paramètres de livraison invalides";
                    }
                    break;
                    
                case 'add_note':
                    $note = trim($_POST['note'] ?? '');
                    if (!empty($note)) {
                        $currentNotes = $order['notes'] ?? '';
                        $timestamp = date('Y-m-d H:i:s');
                        $adminUsername = $currentUser['username'];
                        $newNote = "[{$timestamp}] {$adminUsername}: {$note}";
                        
                        $updatedNotes = !empty($currentNotes) ? $currentNotes . "\n" . $newNote : $newNote;
                        
                        $updateStmt = $db->prepare("UPDATE orders SET notes = ?, updated_at = NOW() WHERE id = ?");
                        $updateStmt->execute([$updatedNotes, $orderId]);
                        
                        $adminAuth->logAction('order_note_added', 'order', $orderId, null, ['note' => $note]);
                        
                        $success = "Note ajoutée à la commande";
                        $order['notes'] = $updatedNotes;
                    } else {
                        $error = "La note ne peut pas être vide";
                    }
                    break;
                    
                case 'refund_order':
                    $refundAmount = floatval($_POST['refund_amount'] ?? 0);
                    $refundReason = trim($_POST['refund_reason'] ?? '');
                    
                    if ($refundAmount > 0 && $refundAmount <= $order['total_amount'] && !empty($refundReason)) {
                        $transactionStarted = false;
                        
                        try {
                            $db->beginTransaction();
                            $transactionStarted = true;
                            
                                                        $newStatus = ($refundAmount == $order['total_amount']) ? 'refunded' : 'completed';
                            $newPaymentStatus = ($refundAmount == $order['total_amount']) ? 'refunded' : 'partially_refunded';
                            
                            $updateStmt = $db->prepare("
                                UPDATE orders 
                                SET status = ?, payment_status = ?, updated_at = NOW(), refunded_at = NOW()
                                WHERE id = ?
                            ");
                            $updateStmt->execute([$newStatus, $newPaymentStatus, $orderId]);
                            
                                                        $userBalanceStmt = $db->prepare("SELECT balance FROM users WHERE id = ?");
                            $userBalanceStmt->execute([$order['user_id']]);
                            $currentBalance = floatval($userBalanceStmt->fetchColumn());
                            
                                                        $newBalance = $currentBalance + $refundAmount;
                            $userStmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
                            $userStmt->execute([$newBalance, $order['user_id']]);
                            
                                                        $transactionStmt = $db->prepare("
                                INSERT INTO balance_transactions 
                                (user_id, type, amount, balance_before, balance_after, source, reference_type, reference_id, description, metadata, created_at)
                                VALUES (?, 'credit', ?, ?, ?, ?, 'order', ?, ?, ?, NOW())
                            ");
                            
                            $source = 'ADMIN_REFUND_' . $currentAdmin['id'] . '_' . time();
                            $description = "Remboursement commande #{$order['order_number']} - {$refundReason}";
                            $metadata = json_encode([
                                'admin_id' => $currentAdmin['id'],
                                'admin_username' => $currentUser['username'],
                                'refund_amount' => $refundAmount,
                                'reason' => $refundReason,
                                'ip' => getRealIP()
                            ]);
                            
                            $transactionStmt->execute([
                                $order['user_id'], $refundAmount, $currentBalance, $newBalance,
                                $source, $orderId, $description, $metadata
                            ]);
                            
                            $db->commit();
                            $transactionStarted = false;
                            
                            $adminAuth->logAction('order_refunded', 'order', $orderId, 
                                ['old_status' => $order['status'], 'old_payment_status' => $order['payment_status']], 
                                ['new_status' => $newStatus, 'new_payment_status' => $newPaymentStatus, 'refund_amount' => $refundAmount, 'reason' => $refundReason], 
                                'high');
                            
                            $success = "Remboursement de {$refundAmount}€ effectué avec succès";
                            $order['status'] = $newStatus;
                            $order['payment_status'] = $newPaymentStatus;
                            
                        } catch (Exception $e) {
                            if ($transactionStarted) {
                                $db->rollback();
                            }
                            throw $e;
                        }
                    } else {
                        $error = "Montant de remboursement ou raison invalide";
                    }
                    break;
                    
                default:
                    $error = "Action non reconnue";
            }
            
            if (isset($success)) {
                header("Location: /admin/orders/order.php?id={$orderId}&success=" . urlencode($success));
                exit();
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
            $adminAuth->logAction('order_action_failed', 'order', $orderId, null, ['error' => $e->getMessage()], 'critical');
        }
    }
    
} catch (Exception $e) {
    error_log("Admin order detail error: " . $e->getMessage());
    $error = "Erreur lors du chargement des informations de commande";
    header('Location: /admin/orders/?error=' . urlencode($error));
    exit();
}

$seo = [
    'title' => 'Commande #' . $order['order_number'] . ' - Admin Pilote Shopping',
    'description' => 'Détails administrateur de la commande',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 lg:px-6 py-8">

        <div class="mb-6">
            <nav class="flex items-center space-x-2 text-sm text-gray-400">
                <a href="/admin/" class="hover:text-white transition-colors">Admin</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <a href="/admin/orders/" class="hover:text-white transition-colors">Commandes</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <span class="text-white">#<?= htmlspecialchars($order['order_number']) ?></span>
            </nav>
        </div>

<?php if (isset($_GET['success'])): ?>
        <div class="mb-6 bg-green-500/20 border border-green-500/50 text-green-300 px-4 py-3 rounded-xl">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_GET['success']) ?>
        </div>
<?php endif; ?>

<?php if (isset($error)): ?>
        <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-xl">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-8 mb-8">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <div class="flex items-center space-x-4 mb-4">
                        <h1 class="text-3xl font-bold text-white">
                            Commande #<?= htmlspecialchars($order['order_number']) ?>
                        </h1>

                        <div class="flex space-x-3">
                            <span class="px-4 py-2 rounded-full text-sm font-medium <?= 
                                $order['status'] === 'completed' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
                                ($order['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' :
                                ($order['status'] === 'processing' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' :
                                ($order['status'] === 'cancelled' ? 'bg-red-500/20 text-red-400 border border-red-500/30' :
                                'bg-gray-500/20 text-gray-400 border border-gray-500/30')))
                            ?>">
                                <i class="fas fa-shopping-bag mr-2"></i>
<?= ucfirst($order['status']) ?>
                            </span>

                            <span class="px-4 py-2 rounded-full text-sm font-medium <?= 
                                $order['payment_status'] === 'paid' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
                                ($order['payment_status'] === 'pending' ? 'bg-orange-500/20 text-orange-400 border border-orange-500/30' :
                                ($order['payment_status'] === 'failed' ? 'bg-red-500/20 text-red-400 border border-red-500/30' :
                                'bg-gray-500/20 text-gray-400 border border-gray-500/30'))
                            ?>">
                                <i class="fas fa-credit-card mr-2"></i>
                                Paiement <?= $order['payment_status'] === 'paid' ? 'confirmé' : $order['payment_status'] ?>
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center space-x-6 text-sm text-gray-400">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-user text-blue-400"></i>
                            <a href="/admin/users/user.php?uuid=<?= htmlspecialchars($order['user_uuid']) ?>" class="text-blue-400 hover:text-blue-300 transition-colors">
<?= htmlspecialchars($order['username']) ?>
                            </a>
                            <span>-</span>
                            <span><?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?></span>
                        </div>

                        <div class="flex items-center space-x-2">
                            <i class="fas fa-envelope text-blue-400"></i>
                            <span><?= htmlspecialchars($order['email']) ?></span>
                        </div>

                        <div class="flex items-center space-x-2">
                            <i class="fas fa-calendar text-blue-400"></i>
                            <span><?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></span>
                        </div>

<?php if ($order['payment_method_name']): ?>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-credit-card text-blue-400"></i>
                            <span><?= htmlspecialchars($order['payment_method_name']) ?></span>
                        </div>
<?php endif; ?>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <a href="/admin/orders/edit.php?id=<?= $orderId ?>" 
                       class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                        <i class="fas fa-edit mr-2"></i>Éditer
                    </a>

                    <div class="relative">
                        <button onclick="toggleActionsMenu()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                            <i class="fas fa-cog mr-2"></i>Actions
                            <i class="fas fa-chevron-down ml-2 text-sm"></i>
                        </button>

                        <div id="actions-menu" class="hidden absolute right-0 mt-2 w-64 bg-dark-700 rounded-xl border border-dark-600 shadow-xl z-[9999]">
                            <div class="py-2">
                                <button onclick="showStatusModal(); hideActionsMenu();" 
                                        class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                    <i class="fas fa-edit mr-3"></i>Changer statut commande
                                </button>

                                <button onclick="showPaymentStatusModal(); hideActionsMenu();" 
                                        class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                    <i class="fas fa-credit-card mr-3"></i>Changer statut paiement
                                </button>

                                <button onclick="showNoteModal(); hideActionsMenu();" 
                                        class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                    <i class="fas fa-sticky-note mr-3"></i>Ajouter une note
                                </button>

<?php if ($order['payment_status'] === 'paid'): ?>
                                <button onclick="showRefundModal(); hideActionsMenu();" 
                                        class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                    <i class="fas fa-undo mr-3"></i>Rembourser
                                </button>
<?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-8">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h2 class="text-xl font-bold text-white mb-6">
                        <i class="fas fa-box text-green-400 mr-2"></i>
                        Articles commandés
                        <span class="bg-green-400/20 text-green-400 text-sm px-2 py-1 rounded-full ml-3">
<?= count($orderItems) ?> article<?= count($orderItems) > 1 ? 's' : '' ?>
                        </span>
                    </h2>

                    <div class="space-y-4">
<?php foreach ($orderItems as $item): ?>
                        <div class="bg-dark-700/50 rounded-xl p-4 border border-dark-600">
                            <div class="flex items-start space-x-4">
                                <img src="<?= htmlspecialchars($item['main_image']) ?>" 
                                     alt="<?= htmlspecialchars($item['product_name']) ?>"
                                     class="w-20 h-20 object-cover rounded-lg"
                                     onerror="this.src='/assets/images/product-placeholder.jpg'">

                                <div class="flex-1">
                                    <div class="flex items-start justify-between mb-3">
                                        <div>
                                            <h4 class="font-bold text-white text-lg mb-1">
<?= htmlspecialchars($item['product_name']) ?>
<?php if ($item['offer_name']): ?>
                                                - <?= htmlspecialchars($item['offer_name']) ?>
<?php endif; ?>
                                            </h4>

                                            <div class="flex items-center space-x-4 text-sm text-gray-400 mb-2">
<?php if ($item['product_sku']): ?>
                                                <span class="font-mono">SKU: <?= htmlspecialchars($item['product_sku']) ?></span>
<?php endif; ?>

<?php if ($item['category_name']): ?>
                                                <span>
                                                    <i class="fas fa-tag text-blue-400 mr-1"></i>
<?= htmlspecialchars($item['category_name']) ?>
                                                </span>
<?php endif; ?>

<?php if ($item['access_type_name']): ?>
                                                <span>
                                                    <i class="fas fa-key text-purple-400 mr-1"></i>
<?= htmlspecialchars($item['access_type_name']) ?>
                                                </span>
<?php endif; ?>

                                                <span>
                                                    <i class="fas fa-times text-gray-500 mr-1"></i>
                                                    Quantité: <?= $item['quantity'] ?>
                                                </span>
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <div class="text-xl font-bold text-white">
<?= number_format($item['total_price'], 2) ?>€
                                            </div>
<?php if ($item['quantity'] > 1): ?>
                                            <div class="text-sm text-gray-400">
<?= number_format($item['unit_price'], 2) ?>€ / unité
                                            </div>
<?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium <?= 
                                                $item['delivery_status'] === 'delivered' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
                                                ($item['delivery_status'] === 'processing' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' :
                                                ($item['delivery_status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' :
                                                'bg-gray-500/20 text-gray-400 border border-gray-500/30'))
                                            ?>">
                                                <i class="fas fa-truck mr-1"></i>
<?= ucfirst($item['delivery_status']) ?>
                                            </span>

<?php if ($item['has_subscription']): ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-purple-500/20 text-purple-400 border border-purple-500/30">
                                                <i class="fas fa-sync-alt mr-1"></i>
                                                Abonnement: <?= htmlspecialchars($item['subscription_status']) ?>
                                            </span>
<?php endif; ?>

<?php if ($item['has_inventory']): ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                                                <i class="fas fa-database mr-1"></i>
                                                Livré + Email envoyé
                                            </span>
<?php endif; ?>
                                        </div>

                                        <div class="flex items-center space-x-2">
<?php if ($item['delivery_status'] !== 'delivered'): ?>
                                            <button onclick="showDeliveryContentModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['product_name']) ?>')" 
                                                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-1 px-3 rounded-lg transition-colors text-sm">
                                                <i class="fas fa-paper-plane mr-1"></i>Livrer + Email
                                            </button>
<?php endif; ?>

                                            <button onclick="showDeliveryModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['delivery_status']) ?>')" 
                                                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-1 px-3 rounded-lg transition-colors text-sm">
                                                <i class="fas fa-truck mr-1"></i>Statut
                                            </button>

<?php if ($item['has_inventory']): ?>
                                            <button onclick="showInventoryModal(<?= htmlspecialchars(json_encode($item['inventory_decoded'])) ?>)" 
                                                    class="bg-cyan-600 hover:bg-cyan-700 text-white font-medium py-1 px-3 rounded-lg transition-colors text-sm">
                                                <i class="fas fa-eye mr-1"></i>Voir
                                            </button>
<?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>

<?php if (!empty($paymentTransactions)): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h2 class="text-xl font-bold text-white mb-6">
                        <i class="fas fa-credit-card text-orange-400 mr-2"></i>
                        Transactions de paiement
                    </h2>

                    <div class="space-y-3">
<?php foreach ($paymentTransactions as $transaction): ?>
                        <div class="bg-dark-700/50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= 
                                        $transaction['status'] === 'completed' ? 'bg-green-500/20 text-green-400' :
                                        ($transaction['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                                        ($transaction['status'] === 'processing' ? 'bg-blue-500/20 text-blue-400' :
                                        'bg-red-500/20 text-red-400'))
                                    ?>">
<?= ucfirst($transaction['status']) ?>
                                    </span>
                                    <span class="text-white font-medium ml-3"><?= htmlspecialchars($transaction['adapter_name']) ?></span>
                                </div>
                                <div class="text-white font-bold">
<?= number_format($transaction['amount'], 2) ?>€
                                </div>
                            </div>

                            <div class="text-sm text-gray-400 space-y-1">
<?php if ($transaction['gateway_transaction_id']): ?>
                                <div>ID Gateway: <span class="font-mono"><?= htmlspecialchars($transaction['gateway_transaction_id']) ?></span></div>
<?php endif; ?>

<?php if ($transaction['fees'] > 0): ?>
                                <div>Frais: <?= number_format($transaction['fees'], 2) ?>€</div>
<?php endif; ?>

                                <div>Date: <?= date('d/m/Y H:i:s', strtotime($transaction['created_at'])) ?></div>
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>
<?php endif; ?>


<?php if (!empty($order['notes']) || !empty($adminLogs)): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h2 class="text-xl font-bold text-white mb-6">
                        <i class="fas fa-history text-purple-400 mr-2"></i>
                        Notes et historique
                    </h2>

<?php if (!empty($order['notes'])): ?>
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-white mb-3">Notes administrateur</h3>
                        <div class="bg-dark-700/50 rounded-lg p-4">
                            <pre class="text-gray-300 text-sm whitespace-pre-wrap"><?= htmlspecialchars($order['notes']) ?></pre>
                        </div>
                    </div>
<?php endif; ?>

<?php if (!empty($adminLogs)): ?>
                    <div>
                        <h3 class="text-lg font-semibold text-white mb-3">Historique des actions</h3>
                        <div class="space-y-2 max-h-64 overflow-y-auto">
<?php foreach ($adminLogs as $log): ?>
                            <div class="bg-dark-700/30 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= 
                                        $log['severity'] === 'low' ? 'bg-gray-500/20 text-gray-400' :
                                        ($log['severity'] === 'medium' ? 'bg-blue-500/20 text-blue-400' :
                                        ($log['severity'] === 'high' ? 'bg-orange-500/20 text-orange-400' :
                                        'bg-red-500/20 text-red-400'))
                                    ?>">
<?= htmlspecialchars($log['action']) ?>
                                    </span>
                                    <span class="text-gray-500 text-xs">
<?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                    </span>
                                </div>
                                <div class="text-sm text-gray-300">
                                    Par: <?= htmlspecialchars($log['admin_username'] ?? 'Système') ?>
                                </div>
<?php if ($log['new_data']): ?>
                                <div class="text-xs text-gray-400 mt-1">
<?= htmlspecialchars(substr($log['new_data'], 0, 100)) ?><?= strlen($log['new_data']) > 100 ? '...' : '' ?>
                                </div>
<?php endif; ?>
                            </div>
<?php endforeach; ?>
                        </div>
                    </div>
<?php endif; ?>
                </div>
<?php endif; ?>
            </div>

            <div class="space-y-6">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 sticky top-24 z-10">
                    <h3 class="text-xl font-bold text-white mb-6">
                        <i class="fas fa-calculator text-green-400 mr-2"></i>
                        Résumé financier
                    </h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Sous-total:</span>
                            <span class="text-white"><?= number_format($order['subtotal'], 2) ?>€</span>
                        </div>

<?php if ($order['discount_amount'] > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Réduction:</span>
                            <span class="text-green-400">-<?= number_format($order['discount_amount'], 2) ?>€</span>
                        </div>
<?php endif; ?>

<?php if ($order['tax_amount'] > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Taxes:</span>
                            <span class="text-white"><?= number_format($order['tax_amount'], 2) ?>€</span>
                        </div>
<?php endif; ?>

                        <div class="border-t border-dark-600 pt-3 mt-3">
                            <div class="flex justify-between text-lg font-bold">
                                <span class="text-white">Total:</span>
                                <span class="text-green-400"><?= number_format($order['total_amount'], 2) ?>€</span>
                            </div>
                        </div>

<?php if ($order['coupon_code']): ?>
                        <div class="mt-3 p-2 bg-green-500/10 rounded-lg">
                            <div class="text-xs text-green-400">
                                <i class="fas fa-ticket-alt mr-1"></i>
                                Coupon utilisé: <span class="font-mono"><?= htmlspecialchars($order['coupon_code']) ?></span>
                            </div>
                        </div>
<?php endif; ?>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-user text-blue-400 mr-2"></i>
                        Client
                    </h3>

                    <div class="space-y-3 text-sm">
                        <div>
                            <a href="/admin/users/user.php?uuid=<?= htmlspecialchars($order['user_uuid']) ?>" 
                               class="text-blue-400 hover:text-blue-300 font-medium transition-colors">
<?= htmlspecialchars($order['username']) ?>
                            </a>
                        </div>

                        <div class="text-white">
<?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                        </div>

                        <div class="text-gray-400">
                            <i class="fas fa-envelope mr-2"></i>
<?= htmlspecialchars($order['email']) ?>
                        </div>

<?php if ($order['phone']): ?>
                        <div class="text-gray-400">
                            <i class="fas fa-phone mr-2"></i>
<?= htmlspecialchars($order['phone']) ?>
                        </div>
<?php endif; ?>

                        <div class="text-gray-400">
                            <i class="fas fa-wallet mr-2"></i>
                            Solde: <?= number_format($order['user_balance'], 2) ?>€
                        </div>

                        <div class="text-gray-400">
                            <i class="fas fa-calendar mr-2"></i>
                            Client depuis: <?= date('d/m/Y', strtotime($order['user_created_at'])) ?>
                        </div>
                    </div>
                </div>

<?php if (!empty($billingAddress)): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-map-marker-alt text-orange-400 mr-2"></i>
                        Adresse de facturation
                    </h3>

                    <div class="bg-dark-700/50 rounded-lg p-3">
                        <div class="text-sm text-gray-300 space-y-1">
<?php if (!empty($billingAddress['company_name'])): ?>
                                <div class="font-medium"><?= htmlspecialchars($billingAddress['company_name']) ?></div>
<?php endif; ?>

                            <div class="font-medium">
<?= htmlspecialchars($billingAddress['first_name'] . ' ' . $billingAddress['last_name']) ?>
                            </div>

                            <div><?= htmlspecialchars($billingAddress['address_line1']) ?></div>

<?php if (!empty($billingAddress['address_line2'])): ?>
                                <div><?= htmlspecialchars($billingAddress['address_line2']) ?></div>
<?php endif; ?>

                            <div>
<?= htmlspecialchars($billingAddress['postal_code'] . ' ' . $billingAddress['city']) ?>
                            </div>

                            <div><?= htmlspecialchars($billingAddress['country']) ?></div>

<?php if (!empty($billingAddress['phone'])): ?>
                                <div class="mt-2 text-blue-400">
                                    <i class="fas fa-phone mr-1"></i>
<?= htmlspecialchars($billingAddress['phone']) ?>
                                </div>
<?php endif; ?>
                        </div>
                    </div>
                </div>
<?php endif; ?>


                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        Informations techniques
                    </h3>

                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">ID:</span>
                            <span class="text-white font-mono"><?= $order['id'] ?></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-400">Numéro:</span>
                            <span class="text-white font-mono"><?= htmlspecialchars($order['order_number']) ?></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-400">Devise:</span>
                            <span class="text-white"><?= htmlspecialchars($order['currency']) ?></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-400">Créée:</span>
                            <span class="text-white"><?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span>
                        </div>

                        <div class="flex justify-between">
                            <span class="text-gray-400">MàJ:</span>
                            <span class="text-white"><?= date('d/m/Y H:i', strtotime($order['updated_at'])) ?></span>
                        </div>

<?php if ($order['delivered_at']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Livrée:</span>
                            <span class="text-green-400"><?= date('d/m/Y H:i', strtotime($order['delivered_at'])) ?></span>
                        </div>
<?php endif; ?>

<?php if ($order['cancelled_at']): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Annulée:</span>
                            <span class="text-red-400"><?= date('d/m/Y H:i', strtotime($order['cancelled_at'])) ?></span>
                        </div>
<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="status-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Changer le statut</h3>
            <button onclick="hideStatusModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update_status">

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Nouveau statut</label>
                <select name="new_status" required class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>En cours</option>
                    <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Terminée</option>
                    <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                    <option value="refunded" <?= $order['status'] === 'refunded' ? 'selected' : '' ?>>Remboursée</option>
                </select>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Confirmer
                </button>
                <button type="button" onclick="hideStatusModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<div id="payment-status-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Changer le statut de paiement</h3>
            <button onclick="hidePaymentStatusModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="update_payment_status">

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Nouveau statut de paiement</label>
                <select name="new_payment_status" required class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Payé</option>
                    <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Échoué</option>
                    <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                    <option value="partially_refunded" <?= $order['payment_status'] === 'partially_refunded' ? 'selected' : '' ?>>Partiellement remboursé</option>
                </select>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Confirmer
                </button>
                <button type="button" onclick="hidePaymentStatusModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<div id="delivery-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Statut de livraison</h3>
            <button onclick="hideDeliveryModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="delivery-form">
            <input type="hidden" name="action" value="update_item_delivery">
            <input type="hidden" name="item_id" id="delivery-item-id">

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Statut de livraison</label>
                <select name="new_delivery_status" id="delivery-status-select" required class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    <option value="pending">En attente</option>
                    <option value="processing">En cours</option>
                    <option value="delivered">Livré</option>
                    <option value="failed">Échec</option>
                    <option value="cancelled">Annulé</option>
                </select>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Mettre à jour
                </button>
                <button type="button" onclick="hideDeliveryModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<div id="delivery-content-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-6xl max-h-[95vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-paper-plane text-green-400 mr-2"></i>
                Livrer l'article avec Email automatique
            </h3>
            <button onclick="hideDeliveryContentModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

            <div>
                <form method="POST" id="delivery-content-form">
                    <input type="hidden" name="action" value="deliver_item">
                    <input type="hidden" name="item_id" id="delivery-content-item-id">

                    <div class="mb-4 p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                        <div class="text-blue-400 text-sm mb-1">
                            <i class="fas fa-info-circle mr-1"></i>
                            Article à livrer
                        </div>
                        <div class="text-white font-medium" id="delivery-product-name"></div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm mb-2">Type de livraison *</label>
                        <select name="delivery_type" id="delivery-type-select" required 
                                class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500"
                                onchange="updateDeliveryContentPlaceholder()">
                            <option value="">Sélectionnez le type...</option>
                            <option value="account">Compte (identifiants de connexion)</option>
                            <option value="license_key">Clé de licence / Numéro de série</option>
                            <option value="download_link">Lien de téléchargement</option>
                            <option value="access_code">Code d'accès / Code d'activation</option>
                            <option value="gift_card">Carte cadeau / Bon d'achat</option>
                            <option value="instructions">Instructions d'utilisation</option>
                            <option value="text">Texte simple</option>
                            <option value="markdown">Contenu formaté (Markdown)</option>
                            <option value="url">URL simple</option>
                            <option value="credentials">Identifiants complets</option>
                            <option value="serial">Numéro de série</option>
                            <option value="activation">Code d'activation</option>
                            <option value="subscription">Détails d'abonnement</option>
                            <option value="file">Fichier / Ressource</option>
                            <option value="tutorial">Tutoriel / Guide</option>
                            <option value="custom">Contenu personnalisé</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <div class="flex items-center justify-between mb-2">
                            <label class="text-gray-400 text-sm">Contenu de livraison *</label>
                            <div class="flex items-center space-x-2">
                                <label class="flex items-center text-sm text-gray-400">
                                    <input type="checkbox" name="enable_markdown" id="enable-markdown" 
                                           class="mr-2 rounded bg-dark-700 border-dark-600 text-blue-500 focus:ring-blue-500">
                                    Activer Markdown
                                </label>
                                <button type="button" onclick="showMarkdownHelp()" class="text-blue-400 hover:text-blue-300">
                                    <i class="fas fa-question-circle"></i>
                                </button>
                            </div>
                        </div>

                        <div id="editor-toolbar" class="bg-dark-700 border border-dark-600 rounded-t-xl p-2 flex items-center space-x-2 text-sm">
                            <button type="button" onclick="insertText('**', '**')" title="Gras" 
                                    class="px-2 py-1 bg-dark-600 hover:bg-dark-500 text-white rounded">
                                <i class="fas fa-bold"></i>
                            </button>
                            <button type="button" onclick="insertText('*', '*')" title="Italique"
                                    class="px-2 py-1 bg-dark-600 hover:bg-dark-500 text-white rounded">
                                <i class="fas fa-italic"></i>
                            </button>
                            <button type="button" onclick="insertText('`', '`')" title="Code"
                                    class="px-2 py-1 bg-dark-600 hover:bg-dark-500 text-white rounded">
                                <i class="fas fa-code"></i>
                            </button>
                            <button type="button" onclick="insertText('## ', '')" title="Titre"
                                    class="px-2 py-1 bg-dark-600 hover:bg-dark-500 text-white rounded">
                                <i class="fas fa-heading"></i>
                            </button>
                            <button type="button" onclick="insertText('- ', '')" title="Liste"
                                    class="px-2 py-1 bg-dark-600 hover:bg-dark-500 text-white rounded">
                                <i class="fas fa-list"></i>
                            </button>
                            <button type="button" onclick="insertText('[', '](url)')" title="Lien"
                                    class="px-2 py-1 bg-dark-600 hover:bg-dark-500 text-white rounded">
                                <i class="fas fa-link"></i>
                            </button>
                            <button type="button" onclick="insertTemplate()" title="Insérer template"
                                    class="px-2 py-1 bg-blue-600 hover:bg-blue-700 text-white rounded">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>

                        <textarea name="delivery_content" id="delivery-content-textarea" rows="12" required
                                  placeholder="Saisissez le contenu de livraison..."
                                  class="w-full bg-dark-700 border border-dark-600 rounded-b-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 font-mono text-sm resize-vertical"
                                  oninput="updatePreview()"></textarea>
                        <div class="text-xs text-gray-400 mt-1" id="delivery-content-help">
                            Saisissez les informations nécessaires pour l'accès au produit
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-400 text-sm mb-2">Notes internes (optionnel)</label>
                        <textarea name="delivery_notes" rows="3"
                                  placeholder="Notes pour l'équipe admin..."
                                  class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"></textarea>
                    </div>

                    <div class="mb-6 p-4 bg-green-500/10 border border-green-500/30 rounded-lg">
                        <div class="text-green-400 text-sm">
                            <i class="fas fa-check-circle mr-2"></i>
                            <strong>Nouvelles fonctionnalités :</strong>
                        </div>
                        <ul class="text-green-200 text-sm mt-2 space-y-1">
                            <li>✅ Email de notification automatique envoyé au client</li>
                            <li>✅ Template professionnel avec design moderne</li>
                            <li>✅ Formatage intelligent selon le type de contenu</li>
                            <li>✅ Support complet du Markdown dans les emails</li>
                            <li>✅ Traçabilité complète de l'envoi d'email</li>
                        </ul>
                    </div>

                    <div class="mb-6 p-4 bg-orange-500/10 border border-orange-500/30 rounded-lg">
                        <div class="text-orange-400 text-sm">
                            <i class="fas fa-exclamation-triangle mr-2"></i>
                            <strong>Important :</strong>
                        </div>
                        <ul class="text-orange-200 text-sm mt-2 space-y-1">
                            <li>• Le contenu sera enregistré de façon sécurisée</li>
                            <li>• Le statut de l'article passera automatiquement à "Livré"</li>
                            <li>• Un email de notification sera automatiquement envoyé au client</li>
                            <li>• Cette action sera tracée dans les logs admin</li>
                        </ul>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="hideDeliveryContentModal()" 
                                class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-xl transition-colors">
                            Annuler
                        </button>
                        <button type="submit" 
                                class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-6 rounded-xl transition-colors">
                            <i class="fas fa-paper-plane mr-2"></i>Livrer + Envoyer Email
                        </button>
                    </div>
                </form>
            </div>

            <div>
                <div class="sticky top-0">
                    <h4 class="text-white font-semibold mb-3 flex items-center">
                        <i class="fas fa-eye text-blue-400 mr-2"></i>
                        Aperçu du rendu
                    </h4>
                    <div id="content-preview" class="bg-dark-700/50 border border-dark-600 rounded-xl p-4 min-h-[400px] max-h-[500px] overflow-y-auto">
                        <div class="text-gray-400 text-sm italic">
                            L'aperçu apparaîtra ici...
                        </div>
                    </div>

                    <div class="mt-4 p-3 bg-blue-500/10 border border-blue-500/30 rounded-lg">
                        <div class="text-blue-400 text-xs">
                            <i class="fas fa-lightbulb mr-1"></i>
                            Astuce : Activez le Markdown pour utiliser la mise en forme (gras, italique, listes, liens, etc.)
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="note-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Ajouter une note</h3>
            <button onclick="hideNoteModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST">
            <input type="hidden" name="action" value="add_note">

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Note admin</label>
                <textarea name="note" rows="4" required placeholder="Ajoutez une note à cette commande..."
                          class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Ajouter
                </button>
                <button type="button" onclick="hideNoteModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<div id="refund-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Rembourser la commande</h3>
            <button onclick="hideRefundModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="refund-form">
            <input type="hidden" name="action" value="refund_order">

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Montant à rembourser (€)</label>
                <input type="number" name="refund_amount" step="0.01" min="0.01" max="<?= $order['total_amount'] ?>" 
                       value="<?= $order['total_amount'] ?>" required
                       class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                <div class="text-xs text-gray-400 mt-1">Maximum: <?= number_format($order['total_amount'], 2) ?>€</div>
            </div>

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Raison du remboursement</label>
                <textarea name="refund_reason" rows="3" required placeholder="Expliquez la raison du remboursement..."
                          class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="mb-4 p-3 bg-orange-500/10 border border-orange-500/30 rounded-lg">
                <div class="text-orange-400 text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    Le montant sera ajouté au solde du client
                </div>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-xl font-medium transition-colors"
                        onclick="return confirm('Êtes-vous sûr de vouloir effectuer ce remboursement ?')">
                    Rembourser
                </button>
                <button type="button" onclick="hideRefundModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<div id="inventory-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-4xl max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-key text-green-400 mr-2"></i>
                Données d'accès produit
            </h3>
            <button onclick="hideInventoryModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="space-y-4">
            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-4">
                <div class="text-green-400 text-sm mb-2">
                    <i class="fas fa-info-circle mr-1"></i>
                    Informations confidentielles
                </div>
                <div class="text-green-300 text-xs">
                    Ces données ne doivent pas être partagées publiquement
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                <div>
                    <h4 class="text-white font-medium mb-2">Données brutes</h4>
                    <div class="bg-dark-700/50 rounded-lg p-4 max-h-80 overflow-y-auto">
                        <pre id="inventory-data" class="text-white text-sm whitespace-pre-wrap"></pre>
                    </div>
                </div>

                <div>
                    <h4 class="text-white font-medium mb-2">Rendu formaté</h4>
                    <div id="inventory-rendered" class="bg-dark-700/50 rounded-lg p-4 max-h-80 overflow-y-auto">

                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <button onclick="copyInventoryData()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-copy mr-2"></i>Copier les données
                </button>
                <button onclick="hideInventoryModal()" 
                        class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<div id="markdown-help-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-4xl max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">
                <i class="fab fa-markdown text-blue-400 mr-2"></i>
                Guide Markdown
            </h3>
            <button onclick="hideMarkdownHelp()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <h4 class="text-white font-semibold mb-3">Syntaxe</h4>
                <div class="bg-dark-700/50 rounded-lg p-4 space-y-3 text-sm">
                    <div>
                        <div class="text-gray-400 mb-1">Gras et italique :</div>
                        <code class="text-green-400">**gras** *italique* ***gras italique***</code>
                    </div>
                    <div>
                        <div class="text-gray-400 mb-1">Titres :</div>
                        <code class="text-green-400"># Titre 1<br>## Titre 2<br>### Titre 3</code>
                    </div>
                    <div>
                        <div class="text-gray-400 mb-1">Listes :</div>
                        <code class="text-green-400">- Item 1<br>- Item 2<br>  - Sous-item</code>
                    </div>
                    <div>
                        <div class="text-gray-400 mb-1">Liens :</div>
                        <code class="text-green-400">[Texte du lien](https://url.com)</code>
                    </div>
                    <div>
                        <div class="text-gray-400 mb-1">Code :</div>
                        <code class="text-green-400">`code en ligne`<br>```<br>bloc de code<br>```</code>
                    </div>
                </div>
            </div>

            <div>
                <h4 class="text-white font-semibold mb-3">Rendu</h4>
                <div class="bg-dark-700/50 rounded-lg p-4 space-y-3 text-sm">
                    <div>
                        <strong>gras</strong> <em>italique</em> <strong><em>gras italique</em></strong>
                    </div>
                    <div>
                        <div class="text-xl font-bold">Titre 1</div>
                        <div class="text-lg font-semibold">Titre 2</div>
                        <div class="text-base font-medium">Titre 3</div>
                    </div>
                    <div>
                        <ul class="list-disc list-inside">
                            <li>Item 1</li>
                            <li>Item 2
                                <ul class="list-disc list-inside ml-4">
                                    <li>Sous-item</li>
                                </ul>
                            </li>
                        </ul>
                    </div>
                    <div>
                        <a href="#" class="text-blue-400 underline">Texte du lien</a>
                    </div>
                    <div>
                        <code class="bg-gray-600 px-1 rounded">code en ligne</code>
                        <pre class="bg-gray-600 p-2 rounded mt-1">bloc de code</pre>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <button onclick="hideMarkdownHelp()" 
                    class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                Compris
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js" integrity="sha512-R0i+CTeSP5g1Sh3aT72MNo9/AKD/UM/htOgY3eJp9PpfNXrl1uutqpJiC0YIYJtcjbOEMzGDB6yFEpZWLcwGgw==" crossorigin="anonymous"></script>

<script>

const CONTENT_TEMPLATES = {
    'account': `## Informations de connexion

**Nom d'utilisateur :** utilisateur123
**Mot de passe :** motdepasse123
**URL de connexion :** https://service.com/login

### Instructions
1. Connectez-vous avec les identifiants ci-dessus
2. Activez votre compte si nécessaire
3. Contactez le support en cas de problème`,

    'license_key': `## Clé de licence

**Clé :** XXXX-XXXX-XXXX-XXXX
**Produit :** [Nom du produit]
**Validité :** Permanente

### Activation
1. Ouvrez le logiciel
2. Allez dans "Aide" > "Activer"
3. Saisissez la clé ci-dessus`,

    'download_link': `## Téléchargement

**Lien :** https://download.com/file
**Mot de passe archive :** password123
**Taille :** 150 MB
**Validité :** 7 jours

### Instructions
1. Cliquez sur le lien de téléchargement
2. Téléchargez le fichier
3. Décompressez avec le mot de passe fourni`,

    'access_code': `## Code d'accès

**Code :** ABC123DEF
**URL :** https://service.com/redeem
**Validité :** 30 jours

### Utilisation
1. Rendez-vous sur l'URL
2. Saisissez le code d'accès
3. Suivez les instructions à l'écran`,

    'gift_card': `## Carte cadeau

**Numéro :** 1234-5678-9012-3456
**Code PIN :** 789
**Montant :** 50€
**Validité :** 12 mois

### Utilisation
1. Présentez ce code lors du paiement
2. Le montant sera déduit automatiquement`,

    'instructions': `## Instructions

Votre commande est prête.

### Étapes à suivre
1. Contactez-nous au 01 23 45 67 89
2. Donnez votre numéro de commande
3. Présentez-vous avec une pièce d'identité

**Horaires :** 9h-17h du lundi au vendredi`,

    'credentials': `## Identifiants complets

**Service :** [Nom du service]
**Email :** user@example.com
**Nom d'utilisateur :** username123
**Mot de passe :** password456
**URL :** https://service.com
**PIN :** 1234

### Informations supplémentaires
- Date d'expiration : [Date]
- Niveau d'accès : Premium
- Support : support@service.com`,

    'tutorial': `## Guide d'utilisation

### Étape 1 : Préparation
Avant de commencer, assurez-vous d'avoir :
- [Prérequis 1]
- [Prérequis 2]

### Étape 2 : Installation
1. Téléchargez le fichier
2. Exécutez l'installation
3. Suivez les instructions

### Étape 3 : Configuration
- Paramètre 1 : [Valeur]
- Paramètre 2 : [Valeur]

### Support
En cas de problème, contactez : support@example.com`
};

function toggleActionsMenu() {
    const menu = document.getElementById('actions-menu');
    menu.classList.toggle('hidden');
}

function hideActionsMenu() {
    document.getElementById('actions-menu').classList.add('hidden');
}

function showStatusModal() {
    document.getElementById('status-modal').classList.remove('hidden');
}

function hideStatusModal() {
    document.getElementById('status-modal').classList.add('hidden');
}

function showPaymentStatusModal() {
    document.getElementById('payment-status-modal').classList.remove('hidden');
}

function hidePaymentStatusModal() {
    document.getElementById('payment-status-modal').classList.add('hidden');
}

function showDeliveryModal(itemId, currentStatus) {
    document.getElementById('delivery-item-id').value = itemId;
    document.getElementById('delivery-status-select').value = currentStatus;
    document.getElementById('delivery-modal').classList.remove('hidden');
}

function hideDeliveryModal() {
    document.getElementById('delivery-modal').classList.add('hidden');
}

function showDeliveryContentModal(itemId, productName) {
    document.getElementById('delivery-content-item-id').value = itemId;
    document.getElementById('delivery-product-name').textContent = productName;
    document.getElementById('delivery-content-modal').classList.remove('hidden');

    document.getElementById('delivery-content-form').reset();
    document.getElementById('delivery-content-item-id').value = itemId;
    updateDeliveryContentPlaceholder();
    updatePreview();
}

function hideDeliveryContentModal() {
    document.getElementById('delivery-content-modal').classList.add('hidden');
}

function updateDeliveryContentPlaceholder() {
    const typeSelect = document.getElementById('delivery-type-select');
    const contentTextarea = document.getElementById('delivery-content-textarea');
    const helpText = document.getElementById('delivery-content-help');

    const placeholders = {
        'account': 'Nom d\'utilisateur, mot de passe et instructions de connexion',
        'license_key': 'Clé de licence et instructions d\'activation',
        'download_link': 'Lien de téléchargement et instructions',
        'access_code': 'Code d\'accès et instructions d\'utilisation',
        'gift_card': 'Numéro de carte, code PIN et informations',
        'instructions': 'Instructions détaillées pour le client',
        'text': 'Texte simple sans formatage',
        'markdown': 'Contenu avec formatage Markdown (gras, listes, liens, etc.)',
        'url': 'URL simple vers la ressource',
        'credentials': 'Identifiants complets (login, mot de passe, URL, etc.)',
        'serial': 'Numéro de série ou code produit',
        'activation': 'Code d\'activation et instructions',
        'subscription': 'Détails de l\'abonnement et accès',
        'file': 'Lien vers le fichier et informations d\'accès',
        'tutorial': 'Guide étape par étape',
        'custom': 'Contenu personnalisé adapté au produit'
    };

    const helps = {
        'account': 'Identifiants de connexion et URL de service',
        'license_key': 'Clé de licence et procédure d\'activation',
        'download_link': 'Lien de téléchargement sécurisé',
        'access_code': 'Code d\'accès unique et instructions',
        'gift_card': 'Informations de carte cadeau',
        'instructions': 'Instructions détaillées d\'utilisation',
        'text': 'Texte brut sans formatage spécial',
        'markdown': 'Texte avec mise en forme (titres, gras, listes, etc.)',
        'url': 'Lien direct vers la ressource',
        'credentials': 'Ensemble complet d\'identifiants',
        'serial': 'Numéro de série produit',
        'activation': 'Code d\'activation logiciel',
        'subscription': 'Informations d\'abonnement',
        'file': 'Accès fichier téléchargeable',
        'tutorial': 'Guide d\'utilisation complet',
        'custom': 'Contenu adapté spécifiquement'
    };

    if (typeSelect.value && placeholders[typeSelect.value]) {
        contentTextarea.placeholder = placeholders[typeSelect.value];
        helpText.textContent = helps[typeSelect.value];
    } else {
        contentTextarea.placeholder = 'Saisissez le contenu de livraison...';
        helpText.textContent = 'Saisissez les informations nécessaires pour l\'accès au produit';
    }
}

function insertText(before, after) {
    const textarea = document.getElementById('delivery-content-textarea');
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const selectedText = textarea.value.substring(start, end);

    const newText = before + selectedText + after;
    textarea.value = textarea.value.substring(0, start) + newText + textarea.value.substring(end);

    const newPosition = start + before.length + selectedText.length;
    textarea.setSelectionRange(newPosition, newPosition);
    textarea.focus();

    updatePreview();
}

function insertTemplate() {
    const typeSelect = document.getElementById('delivery-type-select');
    const textarea = document.getElementById('delivery-content-textarea');

    if (typeSelect.value && CONTENT_TEMPLATES[typeSelect.value]) {
        textarea.value = CONTENT_TEMPLATES[typeSelect.value];
        document.getElementById('enable-markdown').checked = true;
        updatePreview();
    } else {
        showNotification('Sélectionnez d\'abord un type de livraison', 'info');
    }
}

function updatePreview() {
    const content = document.getElementById('delivery-content-textarea').value;
    const enableMarkdown = document.getElementById('enable-markdown').checked;
    const preview = document.getElementById('content-preview');

    if (!content.trim()) {
        preview.innerHTML = '<div class="text-gray-400 text-sm italic">L\'aperçu apparaîtra ici...</div>';
        return;
    }

    let rendered;
    if (enableMarkdown && typeof marked !== 'undefined') {
        marked.setOptions({
            breaks: true,
            gfm: true
        });
        const raw = marked.parse(content);
        rendered = (typeof DOMPurify !== 'undefined')
            ? DOMPurify.sanitize(raw, { FORBID_TAGS: ['script','style','iframe','object','embed','form'], FORBID_ATTR: ['onerror','onload','onclick','onmouseover','onfocus','onblur','srcdoc'] })
            : '<pre class="whitespace-pre-wrap text-sm">' + content.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])) + '</pre>';
    } else {
        rendered = '<pre class="whitespace-pre-wrap text-sm">' + content.replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])) + '</pre>';
    }

    preview.innerHTML = rendered;
}

function _admEscapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}
function _admSanitizeMarkdown(md) {
    if (typeof marked === 'undefined') return _admEscapeHtml(md);
    const html = marked.parse(md);
    if (typeof DOMPurify === 'undefined') return _admEscapeHtml(md);
    return DOMPurify.sanitize(html, { FORBID_TAGS: ['script','style','iframe','object','embed','form'], FORBID_ATTR: ['onerror','onload','onclick','onmouseover','onfocus','onblur','srcdoc'] });
}

function showMarkdownHelp() {
    document.getElementById('markdown-help-modal').classList.remove('hidden');
}

function hideMarkdownHelp() {
    document.getElementById('markdown-help-modal').classList.add('hidden');
}

function showNoteModal() {
    document.getElementById('note-modal').classList.remove('hidden');
}

function hideNoteModal() {
    document.getElementById('note-modal').classList.add('hidden');
}

function showRefundModal() {
    document.getElementById('refund-modal').classList.remove('hidden');
}

function hideRefundModal() {
    document.getElementById('refund-modal').classList.add('hidden');
}

function showInventoryModal(inventoryData) {
    const rawDataElement = document.getElementById('inventory-data');
    const renderedElement = document.getElementById('inventory-rendered');

    let displayText = JSON.stringify(inventoryData, null, 2);
    rawDataElement.textContent = displayText;

    let renderedContent = '';

    if (inventoryData.type) {
        renderedContent += `<div class="mb-3"><span class="text-blue-400 font-medium">Type:</span> ${_admEscapeHtml(inventoryData.type)}</div>`;
    }

    if (inventoryData.content) {
        renderedContent += '<div class="mb-3"><span class="text-blue-400 font-medium">Contenu:</span></div>';
        if (inventoryData.markdown_enabled) {
            renderedContent += '<div class="bg-dark-600 p-3 rounded">' + _admSanitizeMarkdown(inventoryData.content) + '</div>';
        } else {
            renderedContent += '<pre class="bg-dark-600 p-3 rounded whitespace-pre-wrap text-sm">' + _admEscapeHtml(inventoryData.content) + '</pre>';
        }
    }

    if (inventoryData.notes) {
        renderedContent += `<div class="mt-3"><span class="text-yellow-400 font-medium">Notes:</span></div>`;
        renderedContent += `<div class="text-gray-300 text-sm">${_admEscapeHtml(inventoryData.notes)}</div>`;
    }

    if (inventoryData.delivered_by) {
        renderedContent += `<div class="mt-3 text-xs text-gray-500">Livré par: ${_admEscapeHtml(inventoryData.delivered_by)}</div>`;
    }

    if (inventoryData.delivered_at) {
        renderedContent += `<div class="text-xs text-gray-500">Date: ${_admEscapeHtml(inventoryData.delivered_at)}</div>`;
    }

    renderedElement.innerHTML = renderedContent || '<div class="text-gray-400 italic">Aucun contenu à afficher</div>';
    document.getElementById('inventory-modal').classList.remove('hidden');
}

function hideInventoryModal() {
    document.getElementById('inventory-modal').classList.add('hidden');
}

function copyInventoryData() {
    const data = document.getElementById('inventory-data').textContent;
    navigator.clipboard.writeText(data).then(() => {
        showNotification('Données copiées dans le presse-papier', 'success');
    }).catch(() => {
        showNotification('Erreur de copie', 'error');
    });
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-[10000] px-6 py-4 rounded-xl text-white font-medium transition-all duration-300 transform translate-x-full max-w-sm`;

    switch (type) {
        case 'success':
            notification.className += ' bg-green-500';
            break;
        case 'error':
            notification.className += ' bg-red-500';
            break;
        default:
            notification.className += ' bg-blue-600';
    }

    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.remove('translate-x-full'), 100);

    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#actions-menu') && !e.target.closest('[onclick="toggleActionsMenu()"]')) {
        hideActionsMenu();
    }

    const modals = [
        'status-modal', 'payment-status-modal', 'delivery-modal', 
        'delivery-content-modal', 'note-modal', 'refund-modal', 
        'inventory-modal', 'markdown-help-modal'
    ];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (modal && !modal.classList.contains('hidden') && e.target === modal) {
            modal.classList.add('hidden');
        }
    });
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modals = [
            'status-modal', 'payment-status-modal', 'delivery-modal', 
            'delivery-content-modal', 'note-modal', 'refund-modal', 
            'inventory-modal', 'markdown-help-modal'
        ];
        modals.forEach(modalId => {
            document.getElementById(modalId)?.classList.add('hidden');
        });
        hideActionsMenu();
    }
});

document.getElementById('delivery-content-form').addEventListener('submit', function(e) {
    const deliveryType = document.getElementById('delivery-type-select').value;
    const deliveryContent = document.getElementById('delivery-content-textarea').value.trim();

    if (!deliveryType) {
        e.preventDefault();
        showNotification('Veuillez sélectionner un type de livraison', 'error');
        return false;
    }

    if (!deliveryContent) {
        e.preventDefault();
        showNotification('Veuillez saisir le contenu de livraison', 'error');
        return false;
    }

    if (deliveryContent.length < 10) {
        e.preventDefault();
        showNotification('Le contenu de livraison doit contenir au moins 10 caractères', 'error');
        return false;
    }

    return confirm('Êtes-vous sûr de vouloir livrer cet article ? Cette action va également envoyer un email de notification au client.');
});

document.addEventListener('DOMContentLoaded', function() {

    updatePreview();

    document.getElementById('delivery-content-textarea').addEventListener('input', updatePreview);
    document.getElementById('enable-markdown').addEventListener('change', updatePreview);

    document.getElementById('delivery-type-select').addEventListener('change', function() {
        updateDeliveryContentPlaceholder();

        if (CONTENT_TEMPLATES[this.value] && !document.getElementById('delivery-content-textarea').value.trim()) {
            setTimeout(() => {
                if (confirm('Voulez-vous insérer un template pour ce type de contenu ?')) {
                    insertTemplate();
                }
            }, 100);
        }
    });
});
</script>

<style>

.z-\[9999\] {
    z-index: 9999;
}

.z-\[10000\] {
    z-index: 10000;
}

#actions-menu {
    z-index: 9999 !important;
}

.z-10 {
    z-index: 10;
}

#content-preview h1, #inventory-rendered h1 { font-size: 1.5em; font-weight: bold; margin: 0.5em 0; }
#content-preview h2, #inventory-rendered h2 { font-size: 1.3em; font-weight: bold; margin: 0.5em 0; }
#content-preview h3, #inventory-rendered h3 { font-size: 1.1em; font-weight: bold; margin: 0.5em 0; }
#content-preview strong, #inventory-rendered strong { font-weight: bold; }
#content-preview em, #inventory-rendered em { font-style: italic; }
#content-preview ul, #inventory-rendered ul { list-style-type: disc; padding-left: 1.5em; margin: 0.5em 0; }
#content-preview ol, #inventory-rendered ol { list-style-type: decimal; padding-left: 1.5em; margin: 0.5em 0; }
#content-preview a, #inventory-rendered a { color: #60a5fa; text-decoration: underline; }
#content-preview code, #inventory-rendered code { background: #374151; padding: 0.2em 0.4em; border-radius: 0.2em; font-family: monospace; }
#content-preview pre, #inventory-rendered pre { background: #374151; padding: 1em; border-radius: 0.5em; overflow-x: auto; }
#content-preview blockquote, #inventory-rendered blockquote { border-left: 4px solid #60a5fa; padding-left: 1em; margin: 1em 0; font-style: italic; }

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #1F2937;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #4B5563;
}

.modal-enter {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\\:grid-cols-3 {
        grid-template-columns: 1fr;
    }

    .text-3xl {
        font-size: 1.5rem;
    }

    #actions-menu {
        position: fixed !important;
        right: 1rem !important;
        top: 5rem !important;
        left: 1rem !important;
        width: auto !important;
    }

    #delivery-content-modal .grid.grid-cols-1.lg\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
}

#delivery-content-textarea {
    min-height: 200px;
    resize: vertical;
}

.transition-all {
    transition: all 0.3s ease;
}

button:hover {
    transform: translateY(-1px);
}

input:focus, textarea:focus, select:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

#editor-toolbar button:hover {
    transform: translateY(-1px);
}

#content-preview {
    line-height: 1.6;
}

#content-preview p {
    margin: 0.5em 0;
}

.email-success-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}

.delivery-email-indicator {
    background: linear-gradient(45deg, #10B981, #34D399);
    animation: glow 2s ease-in-out infinite alternate;
}

@keyframes glow {
    from {
        box-shadow: 0 0 5px rgba(16, 185, 129, 0.4);
    }
    to {
        box-shadow: 0 0 20px rgba(16, 185, 129, 0.8);
    }
}

.delivery-with-email-btn {
    background: linear-gradient(135deg, #10B981, #059669);
    position: relative;
    overflow: hidden;
}

.delivery-with-email-btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.delivery-with-email-btn:hover::before {
    left: 100%;
}

.email-feature-highlight {
    border-left: 4px solid #10B981;
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(52, 211, 153, 0.05));
}
</style>

<?php
endHTMLPage();
?>