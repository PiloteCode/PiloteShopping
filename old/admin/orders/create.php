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
    
        
        $usersStmt = $db->prepare("
        SELECT id, uuid, username, email, first_name, last_name, balance
        FROM users 
        WHERE is_active = 1 
        ORDER BY username ASC
        LIMIT 1000
    ");
    $usersStmt->execute();
    $users = $usersStmt->fetchAll();
    
        $productsStmt = $db->prepare("
        SELECT p.id, p.name, p.slug, p.images, p.sku,
               pc.name as category_name, pc.color as category_color,
               pt.name as type_name
        FROM products p
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        LEFT JOIN product_types pt ON p.product_type_id = pt.id
        WHERE p.is_active = 1
        ORDER BY p.name ASC
    ");
    $productsStmt->execute();
    $products = $productsStmt->fetchAll();
    
        $couponsStmt = $db->prepare("
        SELECT code, name, discount_type, discount_value, minimum_amount
        FROM coupons 
        WHERE is_active = 1 AND (end_date IS NULL OR end_date > NOW())
        ORDER BY name ASC
    ");
    $couponsStmt->execute();
    $coupons = $couponsStmt->fetchAll();
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db->beginTransaction();
            
                        $userId = intval($_POST['user_id'] ?? 0);
            $status = $_POST['status'] ?? 'pending';
            $paymentStatus = $_POST['payment_status'] ?? 'pending';
            $paymentMethod = trim($_POST['payment_method'] ?? '');
            $currency = $_POST['currency'] ?? 'EUR';
            $couponCode = trim($_POST['coupon_code'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            
                        if ($userId <= 0) {
                throw new Exception('Veuillez sélectionner un utilisateur valide');
            }
            
            $userStmt = $db->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();
            
            if (!$user) {
                throw new Exception('Utilisateur non trouvé ou inactif');
            }
            
                        $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
            $validPaymentStatuses = ['pending', 'paid', 'failed'];
            
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Statut de commande invalide');
            }
            
            if (!in_array($paymentStatus, $validPaymentStatuses)) {
                throw new Exception('Statut de paiement invalide');
            }
            
                        $items = $_POST['items'] ?? [];
            if (empty($items)) {
                throw new Exception('Ajoutez au moins un article à la commande');
            }
            
            $subtotal = 0;
            $validatedItems = [];
            
            foreach ($items as $itemData) {
                $productId = intval($itemData['product_id'] ?? 0);
                $offerId = intval($itemData['offer_id'] ?? 0);
                $quantity = intval($itemData['quantity'] ?? 1);
                $unitPrice = floatval($itemData['unit_price'] ?? 0);
                
                if ($productId <= 0 || $quantity <= 0 || $unitPrice < 0) {
                    continue;
                }
                
                                $productStmt = $db->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
                $productStmt->execute([$productId]);
                $product = $productStmt->fetch();
                
                if (!$product) {
                    throw new Exception("Produit ID {$productId} non trouvé ou inactif");
                }
                
                                $offer = null;
                if ($offerId > 0) {
                    $offerStmt = $db->prepare("
                        SELECT po.*, pat.name as access_type_name
                        FROM product_offers po
                        LEFT JOIN product_access_types pat ON po.access_type_id = pat.id
                        WHERE po.id = ? AND po.is_active = 1
                    ");
                    $offerStmt->execute([$offerId]);
                    $offer = $offerStmt->fetch();
                    
                    if (!$offer) {
                        throw new Exception("Offre ID {$offerId} non trouvée ou inactive");
                    }
                }
                
                $totalPrice = $quantity * $unitPrice;
                $subtotal += $totalPrice;
                
                $validatedItems[] = [
                    'product' => $product,
                    'offer' => $offer,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice
                ];
            }
            
            if (empty($validatedItems)) {
                throw new Exception('Aucun article valide dans la commande');
            }
            
                        $taxAmount = floatval($_POST['tax_amount'] ?? 0);
            $discountAmount = floatval($_POST['discount_amount'] ?? 0);
            $totalAmount = max(0, $subtotal + $taxAmount - $discountAmount);
            
                        if (!empty($couponCode)) {
                $couponStmt = $db->prepare("
                    SELECT * FROM coupons 
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
            
                        $billingAddress = [
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
            
                        if (empty($billingAddress['first_name']) || empty($billingAddress['last_name']) || 
                empty($billingAddress['address_line1']) || empty($billingAddress['city']) || 
                empty($billingAddress['postal_code'])) {
                throw new Exception('Les champs obligatoires de l\'adresse de facturation doivent être remplis');
            }
            
            $shippingAddress = $billingAddress;             
                        $orderNumber = 'PS' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
            
                        $orderStmt = $db->prepare("
                INSERT INTO orders (
                    order_number, user_id, status, payment_status, payment_method, currency,
                    subtotal, tax_amount, discount_amount, total_amount, coupon_code,
                    billing_address, shipping_address, notes, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $orderStmt->execute([
                $orderNumber, $userId, $status, $paymentStatus, $paymentMethod, $currency,
                $subtotal, $taxAmount, $discountAmount, $totalAmount, $couponCode ?: null,
                json_encode($billingAddress), json_encode($shippingAddress), $notes
            ]);
            
            $orderId = $db->lastInsertId();
            
                        foreach ($validatedItems as $item) {
                $itemStmt = $db->prepare("
                    INSERT INTO order_items (
                        order_id, product_id, product_name, product_sku, product_type,
                        quantity, unit_price, total_price, product_data, delivery_status,
                        product_offer_id, access_type_name, offer_name, billing_cycle,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $productData = json_encode([
                    'category_name' => $item['product']['category_name'] ?? '',
                    'delivery_method' => $item['offer']['delivery_method'] ?? 'manual_1week',
                    'is_subscription' => $item['offer'] ? ($item['offer']['billing_cycle'] !== 'one_time' ? 1 : 0) : 0,
                    'custom_data' => []
                ]);
                
                $itemStmt->execute([
                    $orderId,
                    $item['product']['id'],
                    $item['product']['name'],
                    $item['product']['sku'],
                    $item['product']['type_name'] ?? 'Produit',
                    $item['quantity'],
                    $item['unit_price'],
                    $item['total_price'],
                    $productData,
                    'pending',
                    $item['offer']['id'] ?? null,
                    $item['offer']['access_type_name'] ?? null,
                    $item['offer']['name'] ?? null,
                    $item['offer']['billing_cycle'] ?? 'one_time'
                ]);
                
                                $updateProductStmt = $db->prepare("UPDATE products SET sale_count = sale_count + ? WHERE id = ?");
                $updateProductStmt->execute([$item['quantity'], $item['product']['id']]);
                
                if ($item['offer']) {
                    $updateOfferStmt = $db->prepare("UPDATE product_offers SET sale_count = sale_count + ? WHERE id = ?");
                    $updateOfferStmt->execute([$item['quantity'], $item['offer']['id']]);
                }
            }
            
                        if (!empty($couponCode) && $discountAmount > 0) {
                $couponUsageStmt = $db->prepare("
                    INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount, used_at, ip_address)
                    VALUES ((SELECT id FROM coupons WHERE code = ?), ?, ?, ?, NOW(), ?)
                ");
                $couponUsageStmt->execute([$couponCode, $userId, $orderId, $discountAmount, getRealIP()]);
                
                                $updateCouponStmt = $db->prepare("UPDATE coupons SET usage_count = usage_count + 1, last_used_at = NOW() WHERE code = ?");
                $updateCouponStmt->execute([$couponCode]);
            }
            
            $db->commit();
            
                        $adminAuth->logAction('order_created_manually', 'order', $orderId, null, [
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'total_amount' => $totalAmount,
                'items_count' => count($validatedItems)
            ], 'medium');
            
            $success = "Commande #{$orderNumber} créée avec succès";
            
                        header("Location: /admin/orders/order.php?id={$orderId}&success=" . urlencode($success));
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error = $e->getMessage();
            $adminAuth->logAction('order_creation_failed', 'order', null, null, ['error' => $e->getMessage()], 'critical');
        }
    }
    
} catch (Exception $e) {
    error_log("Admin order create error: " . $e->getMessage());
    $error = "Erreur lors du chargement des données";
    $users = [];
    $products = [];
    $coupons = [];
}

$seo = [
    'title' => 'Créer une commande - Admin Pilote Shopping',
    'description' => 'Création manuelle d\'une commande',
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
                <span class="text-white">Créer</span>
            </nav>
        </div>

        <div class="mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-2 sm:mb-4">
                <i class="fas fa-plus text-green-400 mr-2 sm:mr-3"></i>
                Créer une nouvelle commande
            </h1>
            <p class="text-sm sm:text-base text-gray-400">
                Création manuelle d'une commande pour un client
            </p>
        </div>

<?php if (isset($error)): ?>
        <div class="mb-4 sm:mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-3 sm:px-4 py-2 sm:py-3 rounded-lg sm:rounded-xl text-sm sm:text-base">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <form method="POST" id="order-form" class="space-y-6 sm:space-y-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-user text-blue-400 mr-2"></i>
                    Informations client
                </h2>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">

                    <div class="lg:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Client <span class="text-red-400">*</span>
                        </label>
                        <select name="user_id" id="user-select" required
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors"
                                onchange="loadUserData()">
                            <option value="">Sélectionnez un client...</option>
<?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>" 
                                    data-email="<?= htmlspecialchars($user['email']) ?>"
                                    data-name="<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>"
                                    data-balance="<?= $user['balance'] ?>">
<?= htmlspecialchars($user['username']) ?> - <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['email']) ?>)
                            </option>
<?php endforeach; ?>
                        </select>
                    </div>

                    <div id="user-info" class="lg:col-span-2 hidden p-3 sm:p-4 bg-dark-700/50 rounded-lg sm:rounded-xl">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                            <div>
                                <span class="text-gray-400 text-xs sm:text-sm">Nom complet :</span>
                                <div class="text-white font-medium text-sm sm:text-base" id="user-fullname"></div>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs sm:text-sm">Email :</span>
                                <div class="text-white font-medium text-sm sm:text-base break-all" id="user-email"></div>
                            </div>
                            <div>
                                <span class="text-gray-400 text-xs sm:text-sm">Solde :</span>
                                <div class="text-green-400 font-bold text-sm sm:text-base" id="user-balance"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 sm:mb-6 space-y-4 sm:space-y-0">
                    <h2 class="text-lg sm:text-xl font-bold text-white">
                        <i class="fas fa-shopping-cart text-green-400 mr-2"></i>
                        Articles de la commande
                    </h2>
                    <button type="button" onclick="addOrderItem()" 
                            class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white px-4 py-2 sm:py-2 rounded-lg sm:rounded-xl font-medium transition-colors text-sm sm:text-base">
                        <i class="fas fa-plus mr-2"></i>Ajouter un article
                    </button>
                </div>

                <div id="order-items" class="space-y-3 sm:space-y-4">

                </div>

                <div id="no-items-message" class="text-center py-6 sm:py-8 text-gray-400">
                    <i class="fas fa-shopping-cart text-3xl sm:text-4xl mb-3 sm:mb-4 opacity-50"></i>
                    <p class="text-sm sm:text-base">Aucun article ajouté. Cliquez sur "Ajouter un article" pour commencer.</p>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-calculator text-purple-400 mr-2"></i>
                    Totaux financiers
                </h2>

                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Sous-total (€)</label>
                        <input type="number" name="subtotal" step="0.01" readonly
                               id="subtotal-display"
                               class="w-full bg-dark-600 border border-dark-500 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Taxes (€)</label>
                        <input type="number" name="tax_amount" step="0.01" min="0" value="0"
                               id="tax-amount"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors"
                               onchange="calculateTotal()">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Réduction (€)</label>
                        <input type="number" name="discount_amount" step="0.01" min="0" value="0"
                               id="discount-amount"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors"
                               onchange="calculateTotal()">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Total (€)</label>
                        <input type="number" name="total_amount" step="0.01" readonly
                               id="total-display"
                               class="w-full bg-dark-600 border border-dark-500 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none font-bold">
                    </div>
                </div>

                <div class="mt-4 sm:mt-6">
                    <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Code coupon (optionnel)</label>
                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                        <select name="coupon_code" id="coupon-select"
                                class="flex-1 bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors"
                                onchange="applyCoupon()">
                            <option value="">Aucun coupon</option>
<?php foreach ($coupons as $coupon): ?>
                            <option value="<?= htmlspecialchars($coupon['code']) ?>"
                                    data-type="<?= $coupon['discount_type'] ?>"
                                    data-value="<?= $coupon['discount_value'] ?>"
                                    data-minimum="<?= $coupon['minimum_amount'] ?>">
<?= htmlspecialchars($coupon['code']) ?> - <?= htmlspecialchars($coupon['name']) ?>
                                (<?= $coupon['discount_type'] === 'percentage' ? $coupon['discount_value'] . '%' : $coupon['discount_value'] . '€' ?>)
                            </option>
<?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-cog text-orange-400 mr-2"></i>
                    Paramètres de la commande
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6">

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Statut commande <span class="text-red-400">*</span>
                        </label>
                        <select name="status" required
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="pending">En attente</option>
                            <option value="processing">En cours</option>
                            <option value="completed">Terminée</option>
                            <option value="cancelled">Annulée</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Statut paiement <span class="text-red-400">*</span>
                        </label>
                        <select name="payment_status" required
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="pending">En attente</option>
                            <option value="paid">Payé</option>
                            <option value="failed">Échoué</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Méthode de paiement</label>
                        <select name="payment_method"
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="">Aucune</option>
                            <option value="stripe">Stripe</option>
                            <option value="paypal">PayPal</option>
                            <option value="bank_transfer">Virement bancaire</option>
                            <option value="cash">Espèces</option>
                            <option value="admin">Admin manuel</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Devise</label>
                        <select name="currency"
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="EUR">EUR (€)</option>
                            <option value="USD">USD ($)</option>
                            <option value="GBP">GBP (£)</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6">
                <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6">
                    <i class="fas fa-map-marker-alt text-red-400 mr-2"></i>
                    Adresse de facturation
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 sm:gap-6">

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Prénom <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_first_name" required
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Nom <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_last_name" required
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Entreprise</label>
                        <input type="text" name="billing_company_name"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Adresse <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_address_line1" required
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Complément d'adresse</label>
                        <input type="text" name="billing_address_line2"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Ville <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_city" required
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Code postal <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="billing_postal_code" required
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">État/Région</label>
                        <input type="text" name="billing_state"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">
                            Pays <span class="text-red-400">*</span>
                        </label>
                        <select name="billing_country" required
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="FR">France</option>
                            <option value="BE">Belgique</option>
                            <option value="CH">Suisse</option>
                            <option value="LU">Luxembourg</option>
                            <option value="CA">Canada</option>
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2 text-sm sm:text-base">Téléphone</label>
                        <input type="tel" name="billing_phone"
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
                    <textarea name="notes" rows="4"
                              placeholder="Notes visibles seulement par les administrateurs..."
                              class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors"></textarea>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6 space-y-4 sm:space-y-0">
                <a href="/admin/orders/" 
                   class="w-full sm:w-auto bg-gray-600 hover:bg-gray-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg sm:rounded-xl font-medium transition-colors text-center text-sm sm:text-base">
                    <i class="fas fa-arrow-left mr-2"></i>Annuler
                </a>

                <button type="submit" 
                        class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white px-6 sm:px-8 py-2 sm:py-3 rounded-lg sm:rounded-xl font-medium transition-colors text-sm sm:text-base">
                    <i class="fas fa-save mr-2"></i>Créer la commande
                </button>
            </div>
        </form>
    </div>
</main>

<template id="order-item-template">
    <div class="order-item bg-dark-700/50 rounded-lg sm:rounded-xl p-3 sm:p-4 border border-dark-600">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between mb-3 sm:mb-4 space-y-2 sm:space-y-0">
            <h4 class="text-white font-medium text-sm sm:text-base">Article <span class="item-number"></span></h4>
            <button type="button" onclick="removeOrderItem(this)" 
                    class="text-red-400 hover:text-red-300 transition-colors self-end sm:self-auto">
                <i class="fas fa-trash text-sm sm:text-base"></i>
            </button>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">

            <div>
                <label class="block text-gray-400 text-xs sm:text-sm mb-1">Produit *</label>
                <select name="items[][product_id]" required onchange="loadProductOffers(this)"
                        class="w-full bg-dark-600 border border-dark-500 rounded-md sm:rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500">
                    <option value="">Sélectionnez un produit...</option>
<?php foreach ($products as $product): ?>
                    <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?></option>
<?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-gray-400 text-xs sm:text-sm mb-1">Offre/Variante</label>
                <select name="items[][offer_id]" onchange="updateItemPrice(this)"
                        class="offer-select w-full bg-dark-600 border border-dark-500 rounded-md sm:rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500">
                    <option value="">Aucune offre spécifique</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-400 text-xs sm:text-sm mb-1">Quantité *</label>
                <input type="number" name="items[][quantity]" value="1" min="1" required
                       class="w-full bg-dark-600 border border-dark-500 rounded-md sm:rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500"
                       onchange="calculateItemTotal(this)">
            </div>

            <div>
                <label class="block text-gray-400 text-xs sm:text-sm mb-1">Prix unitaire (€) *</label>
                <input type="number" name="items[][unit_price]" step="0.01" min="0" required
                       class="w-full bg-dark-600 border border-dark-500 rounded-md sm:rounded-lg px-2 sm:px-3 py-1.5 sm:py-2 text-white text-xs sm:text-sm focus:outline-none focus:border-blue-500"
                       onchange="calculateItemTotal(this)">
            </div>
        </div>

        <div class="mt-3 flex justify-end">
            <div class="text-white font-medium text-sm sm:text-base">
                Total article: <span class="item-total">0.00</span>€
            </div>
        </div>
    </div>
</template>

<script>
let itemCounter = 0;

function loadUserData() {
    const select = document.getElementById('user-select');
    const userInfo = document.getElementById('user-info');

    if (select.value) {
        const option = select.selectedOptions[0];
        const name = option.dataset.name;
        const email = option.dataset.email;
        const balance = parseFloat(option.dataset.balance);

        document.getElementById('user-fullname').textContent = name;
        document.getElementById('user-email').textContent = email;
        document.getElementById('user-balance').textContent = balance.toFixed(2) + '€';

        userInfo.classList.remove('hidden');

        const names = name.split(' ');
        if (names.length >= 2) {
            document.querySelector('input[name="billing_first_name"]').value = names[0];
            document.querySelector('input[name="billing_last_name"]').value = names.slice(1).join(' ');
        }
    } else {
        userInfo.classList.add('hidden');
    }
}

function addOrderItem() {
    const template = document.getElementById('order-item-template');
    const clone = template.content.cloneNode(true);

    itemCounter++;
    clone.querySelector('.item-number').textContent = itemCounter;

    const container = document.getElementById('order-items');
    container.appendChild(clone);

    document.getElementById('no-items-message').style.display = 'none';
    updateOrderNumbers();
}

function removeOrderItem(button) {
    const item = button.closest('.order-item');
    item.remove();

    const container = document.getElementById('order-items');
    if (container.children.length === 0) {
        document.getElementById('no-items-message').style.display = 'block';
    }

    updateOrderNumbers();
    calculateSubtotal();
}

function updateOrderNumbers() {
    const items = document.querySelectorAll('.order-item');
    items.forEach((item, index) => {
        item.querySelector('.item-number').textContent = index + 1;
    });
}

async function loadProductOffers(productSelect) {
    const offerSelect = productSelect.closest('.order-item').querySelector('.offer-select');
    const productId = productSelect.value;

    offerSelect.innerHTML = '<option value="">Aucune offre spécifique</option>';

    if (!productId) return;

    try {
        const response = await fetch(`/api/v1/admin/products/${productId}/offers`);
        const data = await response.json();

        if (data.success) {
            data.offers.forEach(offer => {
                const option = document.createElement('option');
                option.value = offer.id;
                option.textContent = `${offer.name} - ${offer.price}€`;
                option.dataset.price = offer.price;
                offerSelect.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Erreur lors du chargement des offres:', error);
    }
}

function updateItemPrice(offerSelect) {
    const priceInput = offerSelect.closest('.order-item').querySelector('input[name*="unit_price"]');
    const selectedOption = offerSelect.selectedOptions[0];

    if (selectedOption && selectedOption.dataset.price) {
        priceInput.value = parseFloat(selectedOption.dataset.price).toFixed(2);
        calculateItemTotal(priceInput);
    }
}

function calculateItemTotal(input) {
    const item = input.closest('.order-item');
    const quantity = parseFloat(item.querySelector('input[name*="quantity"]').value) || 0;
    const unitPrice = parseFloat(item.querySelector('input[name*="unit_price"]').value) || 0;
    const total = quantity * unitPrice;

    item.querySelector('.item-total').textContent = total.toFixed(2);
    calculateSubtotal();
}

function calculateSubtotal() {
    let subtotal = 0;
    document.querySelectorAll('.item-total').forEach(span => {
        subtotal += parseFloat(span.textContent) || 0;
    });

    document.getElementById('subtotal-display').value = subtotal.toFixed(2);
    calculateTotal();
}

function calculateTotal() {
    const subtotal = parseFloat(document.getElementById('subtotal-display').value) || 0;
    const tax = parseFloat(document.getElementById('tax-amount').value) || 0;
    const discount = parseFloat(document.getElementById('discount-amount').value) || 0;

    const total = Math.max(0, subtotal + tax - discount);
    document.getElementById('total-display').value = total.toFixed(2);
}

function applyCoupon() {
    const couponSelect = document.getElementById('coupon-select');
    const selectedOption = couponSelect.selectedOptions[0];

    if (!selectedOption || !selectedOption.value) {
        document.getElementById('discount-amount').value = '0';
        calculateTotal();
        return;
    }

    const subtotal = parseFloat(document.getElementById('subtotal-display').value) || 0;
    const discountType = selectedOption.dataset.type;
    const discountValue = parseFloat(selectedOption.dataset.value) || 0;
    const minimumAmount = parseFloat(selectedOption.dataset.minimum) || 0;

    if (minimumAmount > 0 && subtotal < minimumAmount) {
        alert(`Montant minimum de ${minimumAmount}€ requis pour ce coupon`);
        couponSelect.value = '';
        document.getElementById('discount-amount').value = '0';
        calculateTotal();
        return;
    }

    let discount = 0;
    if (discountType === 'percentage') {
        discount = subtotal * (discountValue / 100);
    } else {
        discount = discountValue;
    }

    discount = Math.min(discount, subtotal); 
    document.getElementById('discount-amount').value = discount.toFixed(2);
    calculateTotal();
}

document.getElementById('order-form').addEventListener('submit', function(e) {
    const items = document.querySelectorAll('.order-item');
    if (items.length === 0) {
        e.preventDefault();
        alert('Veuillez ajouter au moins un article à la commande');
        return false;
    }

    let valid = true;
    items.forEach(item => {
        const productSelect = item.querySelector('select[name*="product_id"]');
        const quantityInput = item.querySelector('input[name*="quantity"]');
        const priceInput = item.querySelector('input[name*="unit_price"]');

        if (!productSelect.value || !quantityInput.value || !priceInput.value) {
            valid = false;
        }
    });

    if (!valid) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires des articles');
        return false;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    addOrderItem();
});
</script>

<style>

.order-item {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

input[readonly] {
    background-color: #374151 !important;
    color: #9CA3AF;
}

@media (max-width: 640px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .grid.grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }

    .grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }

    .text-2xl.sm\\:text-3xl.lg\\:text-4xl {
        font-size: 1.5rem;
    }
}

@media (min-width: 640px) and (max-width: 1024px) {
    .grid.grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    button, select, input {
        min-height: 44px;
    }

    .fas {
        font-size: 1rem;
    }
}

@media (max-width: 640px) {
    .space-y-6 > * + * {
        margin-top: 1rem;
    }

    .space-y-8 > * + * {
        margin-top: 1.5rem;
    }
}
</style>

<?php
endHTMLPage();
?>