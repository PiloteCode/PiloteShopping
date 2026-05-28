<?php
define('API_ACCESS', true);
require_once '../../api/v1/config.php';
require_once '../../includes/import.php';
require_once '../../includes/AuthMiddleware.php';
require_once '../includes/AdminAuthMiddleware.php';

$adminAuth = initAdminAuth();
$adminAuth->requireAdminAuth(['admin', 'super_admin']);

$currentAdmin = $adminAuth->getCurrentAdmin();
$currentUser = $adminAuth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        
    $orderId = intval($_GET['id'] ?? 0);
    if (!$orderId) {
        header('Location: /admin/orders/?error=ID commande requis');
        exit();
    }
    
        $orderStmt = $db->prepare("
        SELECT o.*, 
               u.username, u.email, u.first_name, u.last_name, u.uuid as user_uuid
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
    ");
    $orderStmt->execute([$orderId]);
    $order = $orderStmt->fetch();
    
    if (!$order) {
        header('Location: /admin/orders/?error=Commande non trouvée');
        exit();
    }
    
        $itemsStmt = $db->prepare("
        SELECT oi.*, 
               p.name as product_name, p.slug as product_slug, p.images as product_images,
               po.name as offer_name, po.billing_cycle
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_offers po ON oi.product_offer_id = po.id
        WHERE oi.order_id = ?
        ORDER BY oi.created_at ASC
    ");
    $itemsStmt->execute([$orderId]);
    $orderItems = $itemsStmt->fetchAll();
    
        foreach ($orderItems as &$item) {
        $item['images'] = json_decode($item['product_images'] ?? '[]', true);
        $item['main_image'] = !empty($item['images']) ? $item['images'][0] : '/assets/images/product-placeholder.jpg';
    }
    
        $couponsStmt = $db->prepare("
        SELECT code, name, discount_type, discount_value, minimum_amount, is_active
        FROM coupons 
        WHERE is_active = 1 AND (end_date IS NULL OR end_date > NOW())
        ORDER BY name ASC
    ");
    $couponsStmt->execute();
    $availableCoupons = $couponsStmt->fetchAll();
    
        $billingAddress = json_decode($order['billing_address'] ?? '{}', true);
    $shippingAddress = json_decode($order['shipping_address'] ?? '{}', true);
    
    if (!is_array($billingAddress)) $billingAddress = [];
    if (!is_array($shippingAddress)) $shippingAddress = [];
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db->beginTransaction();
            
                        $oldData = [
                'status' => $order['status'],
                'payment_status' => $order['payment_status'],
                'subtotal' => $order['subtotal'],
                'tax_amount' => $order['tax_amount'],
                'discount_amount' => $order['discount_amount'],
                'total_amount' => $order['total_amount'],
                'coupon_code' => $order['coupon_code'],
                'notes' => $order['notes']
            ];
            
                        $status = $_POST['status'] ?? $order['status'];
            $paymentStatus = $_POST['payment_status'] ?? $order['payment_status'];
            $subtotal = floatval($_POST['subtotal'] ?? $order['subtotal']);
            $taxAmount = floatval($_POST['tax_amount'] ?? $order['tax_amount']);
            $discountAmount = floatval($_POST['discount_amount'] ?? $order['discount_amount']);
            $totalAmount = floatval($_POST['total_amount'] ?? $order['total_amount']);
            $couponCode = trim($_POST['coupon_code'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $currency = $_POST['currency'] ?? $order['currency'];
            
                        $validStatuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];
            $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];
            
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Statut de commande invalide');
            }
            
            if (!in_array($paymentStatus, $validPaymentStatuses)) {
                throw new Exception('Statut de paiement invalide');
            }
            
                        if ($subtotal < 0 || $taxAmount < 0 || $discountAmount < 0 || $totalAmount < 0) {
                throw new Exception('Les montants ne peuvent pas être négatifs');
            }
            
                        if (!empty($couponCode)) {
                $couponStmt = $db->prepare("
                    SELECT id, discount_type, discount_value, minimum_amount, is_active
                    FROM coupons 
                    WHERE code = ? AND is_active = 1 
                    AND (end_date IS NULL OR end_date > NOW())
                ");
                $couponStmt->execute([$couponCode]);
                $coupon = $couponStmt->fetch();
                
                if (!$coupon) {
                    throw new Exception('Code coupon invalide ou expiré');
                }
                
                if ($coupon['minimum_amount'] > 0 && $subtotal < $coupon['minimum_amount']) {
                    throw new Exception("Montant minimum de {$coupon['minimum_amount']}€ requis pour ce coupon");
                }
            }
            
                        $newBillingAddress = [
                'first_name' => trim($_POST['billing_first_name'] ?? ''),
                'last_name' => trim($_POST['billing_last_name'] ?? ''),
                'company_name' => trim($_POST['billing_company_name'] ?? ''),
                'address_line1' => trim($_POST['billing_address_line1'] ?? ''),
                'address_line2' => trim($_POST['billing_address_line2'] ?? ''),
                'city' => trim($_POST['billing_city'] ?? ''),
                'state' => trim($_POST['billing_state'] ?? ''),
                'postal_code' => trim($_POST['billing_postal_code'] ?? ''),
                'country' => trim($_POST['billing_country'] ?? 'FR'),
                'phone' => trim($_POST['billing_phone'] ?? '')
            ];
            
                        if (empty($newBillingAddress['first_name']) || empty($newBillingAddress['last_name']) || 
                empty($newBillingAddress['address_line1']) || empty($newBillingAddress['city']) || 
                empty($newBillingAddress['postal_code'])) {
                throw new Exception('Les champs obligatoires de l\'adresse de facturation doivent être remplis');
            }
            
                        $useShippingAddress = isset($_POST['use_different_shipping']);
            $newShippingAddress = $newBillingAddress;             
            if ($useShippingAddress) {
                $newShippingAddress = [
                    'first_name' => trim($_POST['shipping_first_name'] ?? ''),
                    'last_name' => trim($_POST['shipping_last_name'] ?? ''),
                    'company_name' => trim($_POST['shipping_company_name'] ?? ''),
                    'address_line1' => trim($_POST['shipping_address_line1'] ?? ''),
                    'address_line2' => trim($_POST['shipping_address_line2'] ?? ''),
                    'city' => trim($_POST['shipping_city'] ?? ''),
                    'state' => trim($_POST['shipping_state'] ?? ''),
                    'postal_code' => trim($_POST['shipping_postal_code'] ?? ''),
                    'country' => trim($_POST['shipping_country'] ?? 'FR'),
                    'phone' => trim($_POST['shipping_phone'] ?? '')
                ];
                
                if (empty($newShippingAddress['first_name']) || empty($newShippingAddress['last_name']) || 
                    empty($newShippingAddress['address_line1']) || empty($newShippingAddress['city']) || 
                    empty($newShippingAddress['postal_code'])) {
                    throw new Exception('Les champs obligatoires de l\'adresse de livraison doivent être remplis');
                }
            }
            
                        $updateStmt = $db->prepare("
                UPDATE orders 
                SET status = ?, payment_status = ?, currency = ?,
                    subtotal = ?, tax_amount = ?, discount_amount = ?, total_amount = ?,
                    coupon_code = ?, notes = ?,
                    billing_address = ?, shipping_address = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            $updateStmt->execute([
                $status, $paymentStatus, $currency,
                $subtotal, $taxAmount, $discountAmount, $totalAmount,
                $couponCode ?: null, $notes,
                json_encode($newBillingAddress), json_encode($newShippingAddress),
                $orderId
            ]);
            
                        if (isset($_POST['items'])) {
                foreach ($_POST['items'] as $itemId => $itemData) {
                    $itemId = intval($itemId);
                    $quantity = intval($itemData['quantity'] ?? 1);
                    $unitPrice = floatval($itemData['unit_price'] ?? 0);
                    $totalPrice = floatval($itemData['total_price'] ?? 0);
                    $deliveryStatus = $itemData['delivery_status'] ?? 'pending';
                    
                    if ($quantity < 1) $quantity = 1;
                    if ($unitPrice < 0) $unitPrice = 0;
                    if ($totalPrice < 0) $totalPrice = 0;
                    
                    $validDeliveryStatuses = ['pending', 'processing', 'delivered', 'failed', 'cancelled'];
                    if (!in_array($deliveryStatus, $validDeliveryStatuses)) {
                        $deliveryStatus = 'pending';
                    }
                    
                    $updateItemStmt = $db->prepare("
                        UPDATE order_items 
                        SET quantity = ?, unit_price = ?, total_price = ?, delivery_status = ?, updated_at = NOW()
                        WHERE id = ? AND order_id = ?
                    ");
                    $updateItemStmt->execute([$quantity, $unitPrice, $totalPrice, $deliveryStatus, $itemId, $orderId]);
                    
                                        if ($deliveryStatus === 'delivered') {
                        $updateItemStmt = $db->prepare("
                            UPDATE order_items 
                            SET delivered_at = NOW() 
                            WHERE id = ? AND delivered_at IS NULL
                        ");
                        $updateItemStmt->execute([$itemId]);
                    }
                }
            }
            
                        if ($status === 'completed' && $order['status'] !== 'completed') {
                                $updateStmt = $db->prepare("UPDATE orders SET delivered_at = NOW() WHERE id = ? AND delivered_at IS NULL");
                $updateStmt->execute([$orderId]);
            } elseif ($status === 'cancelled' && $order['status'] !== 'cancelled') {
                                $updateStmt = $db->prepare("UPDATE orders SET cancelled_at = NOW() WHERE id = ? AND cancelled_at IS NULL");
                $updateStmt->execute([$orderId]);
            } elseif ($paymentStatus === 'refunded' && $order['payment_status'] !== 'refunded') {
                                $updateStmt = $db->prepare("UPDATE orders SET refunded_at = NOW() WHERE id = ? AND refunded_at IS NULL");
                $updateStmt->execute([$orderId]);
            }
            
            $db->commit();
            
                        $newData = [
                'status' => $status,
                'payment_status' => $paymentStatus,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'coupon_code' => $couponCode,
                'notes' => $notes
            ];
            
                        $adminAuth->logAction('order_updated', 'order', $orderId, $oldData, $newData, 'medium');
            
            $success = "Commande modifiée avec succès";
            
                        header("Location: /admin/orders/order.php?id={$orderId}&success=" . urlencode($success));
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error = $e->getMessage();
            $adminAuth->logAction('order_update_failed', 'order', $orderId, null, ['error' => $e->getMessage()], 'high');
        }
    }
    
} catch (Exception $e) {
    error_log("Admin order edit error: " . $e->getMessage());
    $error = "Erreur lors du chargement de la commande";
    header('Location: /admin/orders/?error=' . urlencode($error));
    exit();
}

$seo = [
    'title' => 'Éditer commande #' . $order['order_number'] . ' - Admin Pilote Shopping',
    'description' => 'Édition administrateur de la commande',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-7xl">

        <div class="mb-4 sm:mb-6">
            <nav class="flex items-center space-x-2 text-xs sm:text-sm text-gray-400">
                <a href="/admin/" class="hover:text-white transition-colors">Admin</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <a href="/admin/orders/" class="hover:text-white transition-colors">Commandes</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <a href="/admin/orders/order.php?id=<?= $orderId ?>" class="hover:text-white transition-colors">
                    #<?= htmlspecialchars($order['order_number']) ?>
                </a>
                <i class="fas fa-chevron-right text-xs"></i>
                <span class="text-white">Éditer</span>
            </nav>
        </div>

        <div class="mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-2 sm:mb-4">
                <i class="fas fa-edit text-orange-400 mr-2 sm:mr-3"></i>
                Éditer la commande
            </h1>
            <p class="text-sm sm:text-base text-gray-400">
                Modification de la commande #<?= htmlspecialchars($order['order_number']) ?>
                de <?= htmlspecialchars($order['username']) ?>
            </p>
        </div>

<?php if (isset($error)): ?>
        <div class="mb-4 sm:mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-3 sm:px-4 py-2 sm:py-3 rounded-lg sm:rounded-xl text-sm sm:text-base">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <form method="POST" class="space-y-6 sm:space-y-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                    Informations générales
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Statut commande <span class="text-red-400">*</span>
                        </label>
                        <select name="status" required
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>En cours</option>
                            <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Terminée</option>
                            <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                            <option value="refunded" <?= $order['status'] === 'refunded' ? 'selected' : '' ?>>Remboursée</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Statut paiement <span class="text-red-400">*</span>
                        </label>
                        <select name="payment_status" required
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Payé</option>
                            <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Échoué</option>
                            <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                            <option value="partially_refunded" <?= $order['payment_status'] === 'partially_refunded' ? 'selected' : '' ?>>Partiellement remboursé</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Devise</label>
                        <select name="currency"
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="EUR" <?= $order['currency'] === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                            <option value="USD" <?= $order['currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                            <option value="GBP" <?= $order['currency'] === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Code coupon</label>
                        <input type="text" name="coupon_code"
                               value="<?= htmlspecialchars($order['coupon_code'] ?? '') ?>"
                               placeholder="Code coupon (optionnel)"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-box text-green-400 mr-2"></i>
                    Articles de la commande
                </h2>

                <div class="space-y-3 sm:space-y-4">
<?php foreach ($orderItems as $index => $item): ?>
                    <div class="bg-dark-700/50 rounded-lg sm:rounded-xl p-3 sm:p-4 border border-dark-600">
                        <div class="flex flex-col lg:flex-row lg:items-start space-y-4 lg:space-y-0 lg:space-x-4">
                            <img src="<?= htmlspecialchars($item['main_image']) ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                 class="w-16 h-16 sm:w-20 sm:h-20 object-cover rounded-lg mx-auto lg:mx-0"
                                 onerror="this.src='/assets/images/product-placeholder.jpg'">

                            <div class="flex-1">
                                <h4 class="font-bold text-white text-base sm:text-lg mb-2 sm:mb-3 text-center lg:text-left">
<?= htmlspecialchars($item['product_name']) ?>
<?php if ($item['offer_name']): ?>
                                    - <?= htmlspecialchars($item['offer_name']) ?>
<?php endif; ?>
                                </h4>

                                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">

                                    <div>
                                        <label class="block text-gray-400 text-xs sm:text-sm mb-1">Quantité</label>
                                        <input type="number" name="items[<?= $item['id'] ?>][quantity]" 
                                               value="<?= $item['quantity'] ?>" min="1" required
                                               class="w-full bg-dark-600 border border-dark-500 rounded-md sm:rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500"
                                               onchange="updateItemTotal(<?= $item['id'] ?>)">
                                    </div>

                                    <div>
                                        <label class="block text-gray-400 text-xs sm:text-sm mb-1">Prix unitaire (€)</label>
                                        <input type="number" name="items[<?= $item['id'] ?>][unit_price]" 
                                               value="<?= number_format($item['unit_price'], 2, '.', '') ?>" step="0.01" min="0" required
                                               class="w-full bg-dark-600 border border-dark-500 rounded-md sm:rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500"
                                               onchange="updateItemTotal(<?= $item['id'] ?>)">
                                    </div>

                                    <div>
                                        <label class="block text-gray-400 text-xs sm:text-sm mb-1">Prix total (€)</label>
                                        <input type="number" name="items[<?= $item['id'] ?>][total_price]" 
                                               value="<?= number_format($item['total_price'], 2, '.', '') ?>" step="0.01" min="0" required
                                               class="w-full bg-dark-600 border border-dark-500 rounded-md sm:rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500"
                                               id="total-price-<?= $item['id'] ?>"
                                               onchange="updateOrderTotals()">
                                    </div>

                                    <div>
                                        <label class="block text-gray-400 text-xs sm:text-sm mb-1">Statut livraison</label>
                                        <select name="items[<?= $item['id'] ?>][delivery_status]"
                                                class="w-full bg-dark-600 border border-dark-500 rounded-md sm:rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500">
                                            <option value="pending" <?= $item['delivery_status'] === 'pending' ? 'selected' : '' ?>>En attente</option>
                                            <option value="processing" <?= $item['delivery_status'] === 'processing' ? 'selected' : '' ?>>En cours</option>
                                            <option value="delivered" <?= $item['delivery_status'] === 'delivered' ? 'selected' : '' ?>>Livré</option>
                                            <option value="failed" <?= $item['delivery_status'] === 'failed' ? 'selected' : '' ?>>Échec</option>
                                            <option value="cancelled" <?= $item['delivery_status'] === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-calculator text-purple-400 mr-2"></i>
                    Totaux financiers
                </h2>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Sous-total (€) <span class="text-red-400">*</span>
                        </label>
                        <input type="number" name="subtotal" step="0.01" min="0" required
                               value="<?= number_format($order['subtotal'], 2, '.', '') ?>"
                               id="subtotal-input"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Taxes (€)</label>
                        <input type="number" name="tax_amount" step="0.01" min="0"
                               value="<?= number_format($order['tax_amount'], 2, '.', '') ?>"
                               id="tax-input"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors"
                               onchange="updateTotal()">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Réduction (€)</label>
                        <input type="number" name="discount_amount" step="0.01" min="0"
                               value="<?= number_format($order['discount_amount'], 2, '.', '') ?>"
                               id="discount-input"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors"
                               onchange="updateTotal()">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Total (€) <span class="text-red-400">*</span>
                        </label>
                        <input type="number" name="total_amount" step="0.01" min="0" required
                               value="<?= number_format($order['total_amount'], 2, '.', '') ?>"
                               id="total-input"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>
                </div>

<?php if (!empty($availableCoupons)): ?>
                <div class="mt-4 sm:mt-6">
                    <label class="block text-gray-300 font-medium mb-3 text-sm sm:text-base">Coupons disponibles</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
<?php foreach ($availableCoupons as $coupon): ?>
                        <button type="button" onclick="applyCoupon('<?= htmlspecialchars($coupon['code']) ?>')"
                                class="text-left p-2 sm:p-3 bg-dark-700/50 border border-dark-600 rounded-lg hover:border-blue-500 transition-colors">
                            <div class="font-medium text-white text-sm sm:text-base"><?= htmlspecialchars($coupon['code']) ?></div>
                            <div class="text-xs sm:text-sm text-gray-400"><?= htmlspecialchars($coupon['name']) ?></div>
                            <div class="text-xs sm:text-sm text-green-400">
<?php if ($coupon['discount_type'] === 'percentage'): ?>
                                    -<?= $coupon['discount_value'] ?>%
<?php else: ?>
                                    -<?= number_format($coupon['discount_value'], 2) ?>€
<?php endif; ?>
                            </div>
                        </button>
<?php endforeach; ?>
                    </div>
                </div>
<?php endif; ?>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-map-marker-alt text-blue-400 mr-2"></i>
                    Adresse de facturation
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Prénom <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_first_name" required
                               value="<?= htmlspecialchars($billingAddress['first_name'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Nom <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_last_name" required
                               value="<?= htmlspecialchars($billingAddress['last_name'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Entreprise</label>
                        <input type="text" name="billing_company_name"
                               value="<?= htmlspecialchars($billingAddress['company_name'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Adresse <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_address_line1" required
                               value="<?= htmlspecialchars($billingAddress['address_line1'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Complément d'adresse</label>
                        <input type="text" name="billing_address_line2"
                               value="<?= htmlspecialchars($billingAddress['address_line2'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Ville <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_city" required
                               value="<?= htmlspecialchars($billingAddress['city'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Code postal <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_postal_code" required
                               value="<?= htmlspecialchars($billingAddress['postal_code'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">État/Région</label>
                        <input type="text" name="billing_state"
                               value="<?= htmlspecialchars($billingAddress['state'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Pays <span class="text-red-400">*</span>
                        </label>
                        <select name="billing_country" required
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="FR" <?= ($billingAddress['country'] ?? 'FR') === 'FR' ? 'selected' : '' ?>>France</option>
                            <option value="BE" <?= ($billingAddress['country'] ?? '') === 'BE' ? 'selected' : '' ?>>Belgique</option>
                            <option value="CH" <?= ($billingAddress['country'] ?? '') === 'CH' ? 'selected' : '' ?>>Suisse</option>
                            <option value="LU" <?= ($billingAddress['country'] ?? '') === 'LU' ? 'selected' : '' ?>>Luxembourg</option>
                            <option value="CA" <?= ($billingAddress['country'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Téléphone</label>
                        <input type="tel" name="billing_phone"
                               value="<?= htmlspecialchars($billingAddress['phone'] ?? '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-sticky-note text-yellow-400 mr-2"></i>
                    Notes administrateur
                </h2>

                <div>
                    <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Notes internes</label>
                    <textarea name="notes" rows="6"
                              placeholder="Notes visibles seulement par les administrateurs..."
                              class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors"><?= htmlspecialchars($order['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6 space-y-4 sm:space-y-0">
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                    <a href="/admin/orders/order.php?id=<?= $orderId ?>" 
                       class="w-full sm:w-auto bg-gray-600 hover:bg-gray-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg sm:rounded-xl font-medium transition-colors text-center text-sm sm:text-base">
                        <i class="fas fa-arrow-left mr-2"></i>Annuler
                    </a>

                    <button type="button" onclick="resetForm()" 
                            class="w-full sm:w-auto bg-orange-600 hover:bg-orange-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg sm:rounded-xl font-medium transition-colors text-sm sm:text-base">
                        <i class="fas fa-undo mr-2"></i>Réinitialiser
                    </button>
                </div>

                <button type="submit" 
                        class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-6 sm:px-8 py-2 sm:py-3 rounded-lg sm:rounded-xl font-medium transition-colors text-sm sm:text-base">
                    <i class="fas fa-save mr-2"></i>Sauvegarder les modifications
                </button>
            </div>
        </form>
    </div>
</main>

<script>

function updateItemTotal(itemId) {
    const quantityInput = document.querySelector(`input[name="items[${itemId}][quantity]"]`);
    const unitPriceInput = document.querySelector(`input[name="items[${itemId}][unit_price]"]`);
    const totalPriceInput = document.getElementById(`total-price-${itemId}`);

    const quantity = parseFloat(quantityInput.value) || 0;
    const unitPrice = parseFloat(unitPriceInput.value) || 0;
    const total = quantity * unitPrice;

    totalPriceInput.value = total.toFixed(2);
    updateOrderTotals();
}

function updateOrderTotals() {
    let itemsSubtotal = 0;

    document.querySelectorAll('input[name*="[total_price]"]').forEach(input => {
        itemsSubtotal += parseFloat(input.value) || 0;
    });

    document.getElementById('subtotal-input').value = itemsSubtotal.toFixed(2);

    updateTotal();
}

function updateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal-input').value) || 0;
    const tax = parseFloat(document.getElementById('tax-input').value) || 0;
    const discount = parseFloat(document.getElementById('discount-input').value) || 0;

    const total = Math.max(0, subtotal + tax - discount);
    document.getElementById('total-input').value = total.toFixed(2);
}

function applyCoupon(code) {
    document.querySelector('input[name="coupon_code"]').value = code;

    showNotification(`Coupon "${code}" appliqué. Ajustez manuellement la réduction si nécessaire.`, 'info');
}

function resetForm() {
    if (confirm('Êtes-vous sûr de vouloir réinitialiser tous les changements ?')) {
        location.reload();
    }
}

document.querySelector('form').addEventListener('submit', function(e) {
    const requiredFields = document.querySelectorAll('input[required], select[required]');
    let isValid = true;

    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.style.borderColor = '#EF4444';
            isValid = false;
        } else {
            field.style.borderColor = '';
        }
    });

    if (!isValid) {
        e.preventDefault();
        showNotification('Veuillez remplir tous les champs obligatoires', 'error');
        return false;
    }

    const subtotal = parseFloat(document.getElementById('subtotal-input').value) || 0;
    const total = parseFloat(document.getElementById('total-input').value) || 0;

    if (subtotal < 0 || total < 0) {
        e.preventDefault();
        showNotification('Les montants ne peuvent pas être négatifs', 'error');
        return false;
    }
});

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-4 sm:px-6 py-3 sm:py-4 rounded-lg sm:rounded-xl text-white font-medium transition-all duration-300 transform translate-x-full max-w-sm text-sm sm:text-base`;

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

document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('input[name*="[quantity]"], input[name*="[unit_price]"]').forEach(input => {
        input.addEventListener('input', function() {
            const itemId = this.name.match(/\[(\d+)\]/)[1];
            updateItemTotal(itemId);
        });
    });

    document.getElementById('tax-input').addEventListener('input', updateTotal);
    document.getElementById('discount-input').addEventListener('input', updateTotal);
});
</script>

<style>

form > div {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

input:valid {
    border-color: #10B981;
}

input:invalid:not(:placeholder-shown) {
    border-color: #EF4444;
}

@media (max-width: 640px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .text-2xl.sm\\:text-3xl.lg\\:text-4xl {
        font-size: 1.5rem;
    }

    .grid.grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }

    .grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 640px) and (max-width: 1024px) {
    .grid.grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    button, select, input, textarea {
        min-height: 44px;
    }

    .fas {
        font-size: 1rem;
    }
}

button[onclick^="applyCoupon"] {
    transition: all 0.2s ease;
}

button[onclick^="applyCoupon"]:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
}

@media (max-width: 640px) {
    .space-y-6 > * + * {
        margin-top: 1rem;
    }

    .space-y-8 > * + * {
        margin-top: 1.5rem;
    }
}

@media (max-width: 1024px) {
    .w-16.h-16.sm\\:w-20.sm\\:h-20 {
        margin: 0 auto;
    }
}

@media (max-width: 1024px) {
    .grid.grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 640px) {
    .grid.grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
endHTMLPage();
?>