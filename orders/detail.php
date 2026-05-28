<?php
define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$auth->requireAuth();
$currentUser = $auth->getCurrentUser();

$orderId = filter_input(INPUT_GET, 'order', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1]
]);

$order = null;
$orderItems = [];
$billingAddress = [];
$dbError = null;

if (!$orderId) {
    header('Location: /orders?error=' . urlencode('Commande non spécifiée'));
    exit();
}

try {
    $db = Database::getInstance();
    
        $orderQuery = "
        SELECT o.*, 
               pt.status as transaction_status, 
               pt.gateway_transaction_id,
               pa.name as payment_method_name
        FROM orders o
        LEFT JOIN payment_transactions pt ON o.id = pt.order_id
        LEFT JOIN payment_adapters pa ON pt.payment_adapter_id = pa.id
        WHERE o.id = ? AND o.user_id = ?
        ORDER BY pt.created_at DESC
        LIMIT 1
    ";
    
    $order_stmt = $db->prepare($orderQuery);
    $order_stmt->execute([$orderId, $currentUser['id']]);
    $order = $order_stmt->fetch();
    
    if (!$order) {
        header('Location: /orders?error=' . urlencode('Commande non trouvée'));
        exit();
    }
    
        if ($order['payment_status'] === 'pending') {
        header("Location: /orders/pay?order={$orderId}");
        exit();
    }
    
        $itemsQuery = "
        SELECT 
            oi.id,
            oi.quantity,
            oi.unit_price,
            oi.total_price,
            oi.delivery_status,
            oi.delivery_data,
            oi.delivered_at,
            oi.product_id,
            oi.product_offer_id,
            oi.billing_cycle,
            oi.product_name,
            oi.product_sku,
            oi.product_type,
            oi.access_type_name,
            oi.offer_name,
            -- Données de products (pour images, slug, etc.)
            p.slug as product_slug,
            p.images as product_images,
            p.short_description,
            -- Données de product_categories
            pc.name as category_name,
            pc.color as category_color,
            -- Données d'abonnement
            s.subscription_number,
            s.status as subscription_status,
            s.next_billing_date
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        LEFT JOIN subscriptions s ON oi.id = s.order_item_id
        WHERE oi.order_id = ?
        ORDER BY oi.created_at ASC
    ";
    
    $items_stmt = $db->prepare($itemsQuery);
    $items_stmt->execute([$orderId]);
    $rawItems = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $orderItems = [];
    foreach ($rawItems as $rawItem) {
        $item = [];
        
                $item['id'] = $rawItem['id'];
        $item['quantity'] = intval($rawItem['quantity'] ?: 1);
        $item['unit_price'] = floatval($rawItem['unit_price'] ?: 0);
        $item['total_price'] = floatval($rawItem['total_price'] ?: 0);
        $item['delivery_status'] = $rawItem['delivery_status'] ?: 'pending';
        $item['delivered_at'] = $rawItem['delivered_at'];
        
                $productName = $rawItem['product_name'] ?: '';
        $offerName = $rawItem['offer_name'] ?: '';
        
        if (!empty($productName) && !empty($offerName)) {
            $item['display_name'] = $productName . ' - ' . $offerName;
        } elseif (!empty($productName)) {
            $item['display_name'] = $productName;
        } elseif (!empty($offerName)) {
            $item['display_name'] = $offerName;
        } else {
            $item['display_name'] = 'Produit #' . $rawItem['id'];
        }
        
                $item['display_sku'] = $rawItem['product_sku'] ?: 'N/A';
        $item['display_type'] = $rawItem['access_type_name'] ?: $rawItem['product_type'] ?: 'Standard';
        $item['display_category'] = $rawItem['category_name'] ?: 'Général';
        
                $images = [];
        if (!empty($rawItem['product_images'])) {
            $decoded = json_decode($rawItem['product_images'], true);
            if (is_array($decoded)) {
                $images = $decoded;
            }
        }
        $item['images'] = $images;
        $item['main_image'] = !empty($images) ? $images[0] : '/assets/images/product-placeholder.jpg';
        
                $item['product_url'] = !empty($rawItem['product_slug']) ? "/products/{$rawItem['product_slug']}" : '#';
        
                $item['is_delivered'] = ($item['delivery_status'] === 'delivered');
        
                $item['has_access_data'] = false;
        $item['access_type'] = 'unknown';
        
        if ($item['is_delivered'] && !empty($rawItem['delivery_data'])) {
            $deliveryData = json_decode($rawItem['delivery_data'], true);
            if (is_array($deliveryData) && !empty($deliveryData)) {
                $item['has_access_data'] = true;
                $item['access_type'] = $deliveryData['type'] ?? 'custom';
            }
        }
        
                $item['can_access'] = ($item['is_delivered'] && $item['has_access_data']);
        
                $item['is_subscription'] = !empty($rawItem['subscription_number']);
        if ($item['is_subscription']) {
            $item['subscription_info'] = [
                'subscription_number' => $rawItem['subscription_number'],
                'status' => $rawItem['subscription_status'] ?: 'active',
                'billing_cycle' => $rawItem['billing_cycle'] ?: 'monthly',
                'next_billing_date' => $rawItem['next_billing_date']
            ];
        }
        
        $orderItems[] = $item;
    }
    
        $billingAddress = json_decode($order['billing_address'] ?? '{}', true);
    if (!is_array($billingAddress)) {
        $billingAddress = [];
    }
    
} catch (Exception $e) {
    error_log("Orders detail error: " . $e->getMessage());
    $dbError = [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ];
}

$seo = [
    'title' => $order ? "Commande #{$order['order_number']} - Pilote Shopping" : "Erreur - Pilote Shopping",
    'description' => 'Détails de votre commande et accès à vos produits.',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen bg-dark-900 py-8">
    <div class="container mx-auto px-4 lg:px-6">

<?php if ($dbError): ?>

        <div class="mb-8 bg-red-500/10 border border-red-500/30 rounded-2xl p-6">
            <div class="flex items-center text-red-400 mb-4">
                <i class="fas fa-exclamation-triangle mr-3 text-xl"></i>
                <h3 class="text-xl font-bold">Erreur de chargement</h3>
            </div>
            <div class="text-red-200 mb-4"><?= htmlspecialchars($dbError['message']) ?></div>
            <div class="text-red-300 text-sm">
                Fichier: <?= htmlspecialchars($dbError['file']) ?> (ligne <?= $dbError['line'] ?>)
            </div>
            <div class="mt-6">
                <a href="/orders" class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Retour aux commandes
                </a>
            </div>
        </div>

<?php elseif (!$order): ?>

        <div class="text-center py-16">
            <div class="w-24 h-24 bg-gray-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-search text-gray-400 text-4xl"></i>
            </div>
            <h1 class="text-3xl font-bold text-white mb-4">Commande non trouvée</h1>
            <p class="text-gray-400 mb-8">Cette commande n'existe pas ou vous n'y avez pas accès.</p>
            <a href="/orders" class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-6 rounded-xl transition-colors">
                <i class="fas fa-arrow-left mr-2"></i>Retour aux commandes
            </a>
        </div>

<?php else: ?>


        <nav class="mb-8">
            <div class="flex items-center space-x-2 text-sm">
                <a href="/dashboard" class="text-gray-400 hover:text-white transition-colors">Dashboard</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <a href="/orders" class="text-gray-400 hover:text-white transition-colors">Mes commandes</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <span class="text-white font-medium">Commande #<?= htmlspecialchars($order['order_number']) ?></span>
            </div>
        </nav>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4 flex items-center">
                    <i class="fas fa-receipt text-blue-primary mr-3"></i>
                    Commande #<?= htmlspecialchars($order['order_number']) ?>
                </h1>

                <div class="flex flex-wrap gap-3">
                    <span class="px-4 py-2 rounded-full text-sm font-medium <?= 
                        $order['status'] === 'completed' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
                        ($order['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' :
                        ($order['status'] === 'processing' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' :
                        ($order['status'] === 'cancelled' ? 'bg-red-500/20 text-red-400 border border-red-500/30' :
                        'bg-gray-500/20 text-gray-400 border border-gray-500/30')))
                    ?>">
                        <i class="fas fa-shopping-bag mr-2"></i>
                        Commande <?= ucfirst($order['status']) ?>
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

<?php
                    $subscriptionCount = count(array_filter($orderItems, fn($item) => $item['is_subscription']));
                    if ($subscriptionCount > 0): ?>
                    <span class="px-4 py-2 rounded-full text-sm font-medium bg-purple-500/20 text-purple-400 border border-purple-500/30">
                        <i class="fas fa-sync-alt mr-2"></i>
<?= $subscriptionCount ?> abonnement<?= $subscriptionCount > 1 ? 's' : '' ?>
                    </span>
<?php endif; ?>
                </div>
            </div>

            <div class="mt-6 lg:mt-0 flex flex-col sm:flex-row gap-3">
                <a href="/orders" 
                   class="bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-6 rounded-xl transition-colors text-center">
                    <i class="fas fa-arrow-left mr-2"></i>Retour aux commandes
                </a>

<?php if ($subscriptionCount > 0): ?>
                <a href="/dashboard/subscriptions" 
                   class="bg-purple-500 hover:bg-purple-600 text-white font-medium py-3 px-6 rounded-xl transition-colors text-center">
                    <i class="fas fa-cog mr-2"></i>Gérer abonnements
                </a>
<?php endif; ?>

                <button onclick="window.print()" 
                        class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-print mr-2"></i>Imprimer
                </button>
            </div>
        </div>

<?php if (isset($_GET['payment']) && $_GET['payment'] === 'success'): ?>
        <div class="mb-6 bg-green-500/10 border border-green-500/30 rounded-xl p-4">
            <div class="flex items-center text-green-400">
                <i class="fas fa-check-circle mr-3 text-xl"></i>
                <div>
                    <div class="font-semibold">Paiement confirmé !</div>
                    <div class="text-sm text-green-300">Votre commande a été payée avec succès.</div>
                </div>
            </div>
        </div>
<?php endif; ?>


<?php if (empty($orderItems)): ?>
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-8 text-center">
            <div class="w-20 h-20 bg-orange-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-orange-400 text-3xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Aucun produit trouvé</h3>
            <p class="text-gray-400 mb-6">Cette commande ne contient aucun produit.</p>
            <a href="/support" class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-6 rounded-xl transition-colors">
                <i class="fas fa-headset mr-2"></i>Contacter le support
            </a>
        </div>

<?php else: ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-6">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center">
                        <i class="fas fa-box text-blue-primary mr-3"></i>
                        Produits commandés
                        <span class="bg-blue-primary/20 text-blue-400 text-sm px-2 py-1 rounded-full ml-3">
<?= count($orderItems) ?> article<?= count($orderItems) > 1 ? 's' : '' ?>
                        </span>
                    </h3>

                    <div class="space-y-4">
<?php foreach ($orderItems as $item): ?>
                        <div class="bg-dark-700/50 rounded-xl p-4 border border-dark-600 hover:border-blue-primary/30 transition-colors group">
                            <div class="flex items-start space-x-4">
                                <img src="<?= htmlspecialchars($item['main_image']) ?>" 
                                     alt="<?= htmlspecialchars($item['display_name']) ?>"
                                     class="w-20 h-20 object-cover rounded-lg"
                                     onerror="this.src='/assets/images/product-placeholder.jpg'">

                                <div class="flex-1">
                                    <div class="flex items-start justify-between mb-2">
                                        <div>
                                            <h4 class="font-bold text-white text-lg mb-1">
<?= htmlspecialchars($item['display_name']) ?>
                                            </h4>

<?php if ($item['display_sku'] !== 'N/A'): ?>
                                            <div class="text-sm text-gray-400 font-mono mb-2">
                                                SKU: <?= htmlspecialchars($item['display_sku']) ?>
                                            </div>
<?php endif; ?>

                                            <div class="flex items-center space-x-4 text-sm text-gray-400">
                                                <span>
                                                    <i class="fas fa-tag text-blue-primary mr-1"></i>
<?= htmlspecialchars($item['display_category']) ?>
                                                </span>
                                                <span>
                                                    <i class="fas fa-cube text-blue-primary mr-1"></i>
<?= htmlspecialchars($item['display_type']) ?>
                                                </span>
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

                                    <div class="flex items-center justify-between mt-3">
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

<?php if ($item['is_subscription']): ?>
                                            <span class="px-3 py-1 rounded-full text-xs font-medium bg-purple-500/20 text-purple-400 border border-purple-500/30">
                                                <i class="fas fa-sync-alt mr-1"></i>
                                                Abonnement
                                            </span>
<?php endif; ?>

<?php if ($item['delivered_at']): ?>
                                            <span class="text-xs text-gray-500">
                                                Livré le <?= date('d/m/Y à H:i', strtotime($item['delivered_at'])) ?>
                                            </span>
<?php endif; ?>
                                        </div>

                                        <div class="flex items-center space-x-2">
<?php if ($item['can_access']): ?>
                                            <button onclick="accessProduct(<?= $item['id'] ?>)" 
                                                    class="bg-green-500 hover:bg-green-600 text-white font-medium py-2 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 hover:shadow-lg">
                                                <i class="fas fa-unlock mr-2"></i>Accéder au contenu
                                            </button>
<?php elseif ($item['is_delivered']): ?>
                                            <span class="text-gray-400 text-sm">
                                                <i class="fas fa-check mr-1"></i>Livré (pas de contenu)
                                            </span>
<?php else: ?>
                                            <span class="text-orange-400 text-sm flex items-center">
                                                <i class="fas fa-clock mr-1"></i>
<?php if ($item['delivery_status'] === 'processing'): ?>
                                                    En cours de préparation
<?php else: ?>
                                                    En attente de livraison
<?php endif; ?>
                                            </span>
<?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>

<?php if (!empty($billingAddress)): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center">
                        <i class="fas fa-map-marker-alt text-blue-primary mr-3"></i>
                        Adresse de facturation
                    </h3>

                    <div class="bg-dark-700/50 rounded-xl p-4">
                        <div class="text-sm text-gray-300 space-y-1">
<?php if (!empty($billingAddress['company_name'])): ?>
                                <div class="font-medium"><?= htmlspecialchars($billingAddress['company_name']) ?></div>
<?php endif; ?>

                            <div class="font-medium">
<?= htmlspecialchars(($billingAddress['first_name'] ?? '') . ' ' . ($billingAddress['last_name'] ?? '')) ?>
                            </div>

                            <div><?= htmlspecialchars($billingAddress['address_line1'] ?? '') ?></div>

<?php if (!empty($billingAddress['address_line2'])): ?>
                                <div><?= htmlspecialchars($billingAddress['address_line2']) ?></div>
<?php endif; ?>

                            <div>
<?= htmlspecialchars(($billingAddress['postal_code'] ?? '') . ' ' . ($billingAddress['city'] ?? '')) ?>
                            </div>

                            <div><?= htmlspecialchars($billingAddress['country'] ?? '') ?></div>

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
            </div>

            <div class="space-y-6">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 sticky top-24">
                    <h3 class="text-xl font-bold text-white mb-6 flex items-center">
                        <i class="fas fa-receipt text-blue-primary mr-3"></i>
                        Résumé
                    </h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Sous-total:</span>
                            <span class="text-white"><?= number_format(floatval($order['subtotal']), 2) ?>€</span>
                        </div>

<?php if (floatval($order['discount_amount']) > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Réduction:</span>
                            <span class="text-green-400">-<?= number_format(floatval($order['discount_amount']), 2) ?>€</span>
                        </div>
<?php endif; ?>

<?php if (floatval($order['tax_amount']) > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Taxes:</span>
                            <span class="text-white"><?= number_format(floatval($order['tax_amount']), 2) ?>€</span>
                        </div>
<?php endif; ?>

                        <div class="border-t border-dark-600 pt-3 mt-3">
                            <div class="flex justify-between text-lg font-bold">
                                <span class="text-white">Total:</span>
                                <span class="text-blue-primary"><?= number_format(floatval($order['total_amount']), 2) ?>€</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">Informations</h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Numéro:</span>
                            <span class="text-white font-mono"><?= htmlspecialchars($order['order_number']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Date:</span>
                            <span class="text-white"><?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></span>
                        </div>
<?php if (!empty($order['payment_method_name'])): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Paiement:</span>
                            <span class="text-white"><?= htmlspecialchars($order['payment_method_name']) ?></span>
                        </div>
<?php endif; ?>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-500/10 to-purple-500/10 border border-blue-500/20 rounded-2xl p-6">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-headset text-blue-400 mr-2"></i>
                        Besoin d'aide ?
                    </h3>
                    <p class="text-gray-300 text-sm mb-4">
                        Notre équipe support est là pour vous aider avec vos commandes et produits.
                    </p>
                    <a href="/support" class="w-full bg-blue-primary hover:bg-blue-secondary text-white font-medium py-2 px-4 rounded-lg transition-colors text-center block">
                        Contacter le support
                    </a>
                </div>
            </div>
        </div>
<?php endif; ?>

<?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>


<div id="product-access-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 w-full max-w-5xl max-h-[95vh] overflow-y-auto mx-4">
        <div class="sticky top-0 bg-dark-800 p-6 border-b border-dark-600 rounded-t-2xl">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-unlock text-green-400 mr-2"></i>
                    <span id="modal-product-name">Accès au produit</span>
                </h3>
                <div class="flex items-center space-x-3">

                    <div class="flex bg-dark-700 rounded-lg p-1">
                        <button id="view-rendered" onclick="setViewMode('rendered')" 
                                class="px-3 py-1 text-sm rounded-md transition-colors bg-blue-primary text-white">
                            <i class="fas fa-eye mr-1"></i>Rendu
                        </button>
                        <button id="view-raw" onclick="setViewMode('raw')" 
                                class="px-3 py-1 text-sm rounded-md transition-colors text-gray-400 hover:text-white">
                            <i class="fas fa-code mr-1"></i>Source
                        </button>
                    </div>
                    <button onclick="closeProductModal()" class="text-gray-400 hover:text-white transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            <div id="modal-type-description" class="text-sm text-gray-400 mt-1"></div>
        </div>

        <div class="p-6">

            <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-4 mb-6">
                <div class="text-red-400 text-sm mb-2 flex items-center">
                    <i class="fas fa-shield-alt mr-2"></i>
                    <strong>Informations confidentielles</strong>
                </div>
                <div class="text-red-300 text-xs">
                    Ces données sont strictement personnelles. Ne les partagez jamais avec des tiers.
                </div>
            </div>

            <div class="mb-6">

                <div id="rendered-content" class="bg-dark-700/50 rounded-lg p-6 prose prose-invert max-w-none">

                </div>

                <div id="raw-content-container" class="hidden bg-dark-700/50 rounded-lg p-4">
                    <pre id="raw-content" class="text-white text-sm whitespace-pre-wrap overflow-x-auto font-mono"></pre>
                </div>
            </div>

            <div class="flex justify-end space-x-3">
                <button onclick="copyContent()" 
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-copy mr-2"></i>Copier
                </button>
                <button onclick="printContent()" 
                        class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-print mr-2"></i>Imprimer
                </button>
                <button onclick="downloadContent()" 
                        class="bg-purple-600 hover:bg-purple-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    <i class="fas fa-download mr-2"></i>Télécharger
                </button>
                <button onclick="closeProductModal()" 
                        class="bg-gray-600 hover:bg-gray-700 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<div id="loading-modal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-primary mx-auto mb-4"></div>
        <h3 class="text-lg font-semibold text-white mb-2">Chargement...</h3>
        <p class="text-gray-400">Récupération des données d'accès</p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/marked/4.3.0/marked.min.js" integrity="sha512-2WJqIxLAB7N+a3LtkjvL317SQ0slR1Y4QaqI6jXh+SqxgyqcKqJOhAyKWyTUaxv+ZyR9Ix6FzWPYa6mlx2y9Mw==" crossorigin="anonymous"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/dompurify/3.0.6/purify.min.js" integrity="sha512-R0i+CTeSP5g1Sh3aT72MNo9/AKD/UM/htOgY3eJp9PpfNXrl1uutqpJiC0YIYJtcjbOEMzGDB6yFEpZWLcwGgw==" crossorigin="anonymous"></script>

<script>
let currentProductData = null;
let currentViewMode = 'rendered';

if (typeof marked !== 'undefined') {
    marked.setOptions({
        breaks: true,
        gfm: true,
        headerIds: false,
        mangle: false
    });
}

function safeRenderMarkdown(content) {
    if (typeof marked === 'undefined') {
        return null;
    }
    const html = marked.parse(content);
    if (typeof DOMPurify !== 'undefined') {
        return DOMPurify.sanitize(html, {
            USE_PROFILES: { html: true },
            FORBID_TAGS: ['script', 'style', 'iframe', 'object', 'embed', 'form'],
            FORBID_ATTR: ['onerror', 'onload', 'onclick', 'onmouseover', 'onfocus', 'onblur', 'srcdoc']
        });
    }
    return null;
}

async function accessProduct(orderItemId) {
    try {
        showLoadingModal(true);

        const response = await fetch('/api/v1/orders/access-product', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                order_item_id: orderItemId
            })
        });

        const data = await response.json();

        if (data.success) {
            currentProductData = data;
            showProductAccessWithMarkdown(data);
        } else {
            showNotification(data.error || 'Erreur lors de l\'accès au produit', 'error');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'accès au produit', 'error');
    } finally {
        showLoadingModal(false);
    }
}

function showProductAccessWithMarkdown(data) {
    const modal = document.getElementById('product-access-modal');
    const productName = document.getElementById('modal-product-name');
    const typeDescription = document.getElementById('modal-type-description');

    productName.textContent = `Accès à ${data.product_name}`;
    typeDescription.textContent = data.type_info ? `${data.type_info.name} - ${data.type_info.description}` : '';

    let contentToShow = '';
    if (data.product_data.content) {
        contentToShow = data.product_data.content;
    } else {
        contentToShow = JSON.stringify(data.product_data, null, 2);
    }

    updateContentViews(contentToShow);

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function updateContentViews(content) {
    const rawContent = document.getElementById('raw-content');
    const renderedContent = document.getElementById('rendered-content');

    rawContent.textContent = content;

    if (isMarkdownContent(content)) {
        try {
            const safeHtml = safeRenderMarkdown(content);
            if (safeHtml !== null) {
                renderedContent.innerHTML = safeHtml;
            } else {
                renderedContent.innerHTML = `<div class="text-yellow-400 mb-4">Bibliothèque markdown indisponible</div><pre class="text-white">${escapeHtml(content)}</pre>`;
            }
        } catch (error) {
            console.error('Erreur lors du rendu markdown:', error);
            renderedContent.innerHTML = `<div class="text-red-400 mb-4">Erreur de rendu markdown</div><pre class="text-white">${escapeHtml(content)}</pre>`;
        }
    } else {
        renderedContent.innerHTML = `<pre class="text-white font-mono text-sm whitespace-pre-wrap">${escapeHtml(content)}</pre>`;
    }
}

function isMarkdownContent(content) {

    const markdownPatterns = [
        /^#{1,6}\s/m,           
        /\*\*.*\*\*/,           
        /\*.*\*/,               
        /`.*`/,                 
        /```[\s\S]*```/,        
        /^\-\s/m,               
        /^\*\s/m,               
        /^\d+\.\s/m,            
        /\[.*\]\(.*\)/,         
        /!\[.*\]\(.*\)/         
    ];

    return markdownPatterns.some(pattern => pattern.test(content));
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

function setViewMode(mode) {
    currentViewMode = mode;

    const renderedContent = document.getElementById('rendered-content');
    const rawContentContainer = document.getElementById('raw-content-container');
    const viewRenderedBtn = document.getElementById('view-rendered');
    const viewRawBtn = document.getElementById('view-raw');

    if (mode === 'rendered') {
        renderedContent.classList.remove('hidden');
        rawContentContainer.classList.add('hidden');

        viewRenderedBtn.classList.add('bg-blue-primary', 'text-white');
        viewRenderedBtn.classList.remove('text-gray-400');

        viewRawBtn.classList.remove('bg-blue-primary', 'text-white');
        viewRawBtn.classList.add('text-gray-400');
    } else {
        renderedContent.classList.add('hidden');
        rawContentContainer.classList.remove('hidden');

        viewRawBtn.classList.add('bg-blue-primary', 'text-white');
        viewRawBtn.classList.remove('text-gray-400');

        viewRenderedBtn.classList.remove('bg-blue-primary', 'text-white');
        viewRenderedBtn.classList.add('text-gray-400');
    }
}

function copyContent() {
    let content = '';

    if (currentViewMode === 'raw') {
        content = document.getElementById('raw-content').textContent;
    } else {

        content = document.getElementById('raw-content').textContent;
    }

    navigator.clipboard.writeText(content).then(() => {
        showNotification('Contenu copié dans le presse-papier', 'success');
    }).catch(() => {
        showNotification('Erreur lors de la copie', 'error');
    });
}

function printContent() {
    const printWindow = window.open('', '_blank');
    let content = '';
    let contentType = '';

    if (currentViewMode === 'rendered') {
        content = document.getElementById('rendered-content').innerHTML;
        contentType = 'HTML rendu';
    } else {
        content = escapeHtml(document.getElementById('raw-content').textContent);
        contentType = 'Code source';
    }

    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Accès Produit - ${currentProductData?.product_name || 'Produit'}</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; color: #333; line-height: 1.6; }
                .header { border-bottom: 2px solid #ccc; padding-bottom: 10px; margin-bottom: 20px; }
                .warning { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
                .content { background: #f8f9fa; padding: 20px; border-radius: 5px; }
                pre { background: #f1f3f4; padding: 15px; border-radius: 5px; overflow-x: auto; font-family: 'Courier New', monospace; }
                code { background: #f1f3f4; padding: 2px 4px; border-radius: 3px; font-family: 'Courier New', monospace; }
                h1, h2, h3, h4, h5, h6 { color: #2c3e50; margin-top: 1.5em; }
                blockquote { border-left: 4px solid #3498db; margin: 0; padding-left: 1em; font-style: italic; }
                table { border-collapse: collapse; width: 100%; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Accès Produit: ${currentProductData?.product_name || 'Produit'}</h1>
                <p>Mode d'affichage: ${contentType}</p>
                <p>Date d'impression: ${new Date().toLocaleDateString('fr-FR')}</p>
            </div>
            <div class="warning">
                <strong>⚠️ Informations confidentielles</strong><br>
                Ces données sont strictement personnelles. Ne les partagez jamais avec des tiers.
            </div>
            <div class="content">
                ${currentViewMode === 'rendered' ? content : `<pre>${content}</pre>`}
            </div>
        </body>
        </html>
    `);

    printWindow.document.close();
    printWindow.print();
}

function downloadContent() {
    const content = document.getElementById('raw-content').textContent;
    const productName = currentProductData?.product_name || 'produit';
    const timestamp = new Date().toISOString().slice(0, 19).replace(/:/g, '-');

    let extension = '.txt';
    if (isMarkdownContent(content)) {
        extension = '.md';
    } else if (content.trim().startsWith('{') || content.trim().startsWith('[')) {
        extension = '.json';
    }

    const filename = `${productName.toLowerCase().replace(/[^a-z0-9]/g, '-')}_${timestamp}${extension}`;

    const blob = new Blob([content], { type: 'text/plain;charset=utf-8' });
    const url = URL.createObjectURL(blob);

    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);

    showNotification(`Fichier téléchargé: ${filename}`, 'success');
}

function closeProductModal() {
    document.getElementById('product-access-modal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentProductData = null;
    currentViewMode = 'rendered';

    setViewMode('rendered');
}

function showLoadingModal(show) {
    const modal = document.getElementById('loading-modal');
    modal.classList.toggle('hidden', !show);
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl text-white font-medium transition-all duration-300 transform translate-x-full max-w-sm`;

    switch (type) {
        case 'success':
            notification.className += ' bg-green-500';
            break;
        case 'error':
            notification.className += ' bg-red-500';
            break;
        default:
            notification.className += ' bg-blue-primary';
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
    const modal = document.getElementById('product-access-modal');
    if (e.target === modal) {
        closeProductModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeProductModal();
    }
});
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }

    #main-content,
    #main-content * {
        visibility: visible;
    }

    #main-content {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
}

.group:hover .bg-green-500 {
    transform: scale(1.05);
    box-shadow: 0 10px 25px rgba(34, 197, 94, 0.2);
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #1F2937;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #4B5563;
}

#raw-content {
    font-family: 'Courier New', 'Monaco', 'Menlo', monospace;
    font-size: 14px;
    line-height: 1.5;
}

.prose {
    color: #e5e7eb;
}

.prose h1, .prose h2, .prose h3, .prose h4, .prose h5, .prose h6 {
    color: #ffffff;
    font-weight: bold;
    margin-top: 1.5em;
    margin-bottom: 0.5em;
}

.prose h1 { font-size: 2em; border-bottom: 2px solid #374151; padding-bottom: 0.5em; }
.prose h2 { font-size: 1.5em; border-bottom: 1px solid #374151; padding-bottom: 0.3em; }
.prose h3 { font-size: 1.3em; }
.prose h4 { font-size: 1.1em; }

.prose p {
    margin-bottom: 1em;
    line-height: 1.7;
}

.prose ul, .prose ol {
    margin: 1em 0;
    padding-left: 2em;
}

.prose li {
    margin-bottom: 0.5em;
}

.prose blockquote {
    border-left: 4px solid #3b82f6;
    margin: 1.5em 0;
    padding-left: 1em;
    font-style: italic;
    background: rgba(59, 130, 246, 0.1);
    padding: 1em;
    border-radius: 0.5em;
}

.prose pre {
    background: #1f2937;
    border: 1px solid #374151;
    border-radius: 0.5em;
    padding: 1em;
    overflow-x: auto;
    font-family: 'Courier New', 'Monaco', 'Menlo', monospace;
    font-size: 0.9em;
    margin: 1em 0;
}

.prose code {
    background: #374151;
    color: #fbbf24;
    padding: 0.2em 0.4em;
    border-radius: 0.3em;
    font-family: 'Courier New', 'Monaco', 'Menlo', monospace;
    font-size: 0.9em;
}

.prose pre code {
    background: none;
    color: #e5e7eb;
    padding: 0;
}

.prose a {
    color: #3b82f6;
    text-decoration: underline;
}

.prose a:hover {
    color: #60a5fa;
}

.prose strong {
    color: #ffffff;
    font-weight: 600;
}

.prose em {
    font-style: italic;
    color: #d1d5db;
}

.prose table {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
    border: 1px solid #374151;
}

.prose th, .prose td {
    border: 1px solid #374151;
    padding: 0.5em;
    text-align: left;
}

.prose th {
    background: #374151;
    color: #ffffff;
    font-weight: 600;
}

.prose hr {
    border: none;
    border-top: 1px solid #374151;
    margin: 2em 0;
}

.prose .token.comment { color: #6b7280; }
.prose .token.string { color: #10b981; }
.prose .token.number { color: #f59e0b; }
.prose .token.keyword { color: #8b5cf6; }
.prose .token.operator { color: #ef4444; }
.prose .token.function { color: #3b82f6; }

#rendered-content, #raw-content-container {
    transition: opacity 0.2s ease-in-out;
}

.view-toggle-button {
    transition: all 0.2s ease-in-out;
}

.view-toggle-button:hover {
    background: rgba(59, 130, 246, 0.1);
}
</style>

<?php
endHTMLPage(); 
?>