<?php

require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

if (!isset($product_slug)) {
    $request_uri = $_SERVER['REQUEST_URI'];
    $path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));
    
    if (count($path_parts) >= 2 && $path_parts[0] === 'products' && !empty($path_parts[1])) {
        $product_slug = $path_parts[1];
    } else {
        header('HTTP/1.1 404 Not Found');
        header('Location: /products');
        exit();
    }
}

if (!defined('API_ACCESS')) {
    define('API_ACCESS', true);
}

try {
    $db = Database::getInstance();
    
        $stmt = $db->prepare("
        SELECT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        WHERE p.slug = ? AND p.is_active = 1
    ");
    $stmt->execute([$product_slug]);
    $product = $stmt->fetch();
    
    if (!$product) {
        header('HTTP/1.1 404 Not Found');
        header('Location: /products');
        exit();
    }
    
        $view_stmt = $db->prepare("UPDATE products SET view_count = view_count + 1 WHERE id = ?");
    $view_stmt->execute([$product['id']]);
    
        $access_types_stmt = $db->prepare("
        SELECT pat.*,
               CASE 
                   WHEN pat.has_global_stock = 1 THEN pat.global_stock_quantity
                   ELSE COALESCE(SUM(CASE WHEN po.has_own_stock = 1 AND po.manage_stock = 1 THEN po.stock_quantity ELSE 999 END), 0)
               END as total_stock,
               COUNT(po.id) as offers_count,
               MIN(po.price) as min_price
        FROM product_access_types pat
        JOIN product_offers po ON pat.id = po.access_type_id AND po.is_active = 1
        WHERE pat.product_id = ? AND pat.is_active = 1
        GROUP BY pat.id
        HAVING offers_count > 0
        ORDER BY pat.sort_order ASC
    ");
    $access_types_stmt->execute([$product['id']]);
    $access_types = $access_types_stmt->fetchAll();
    
        $access_types_with_offers = [];
    foreach ($access_types as $access_type) {
                $offers_stmt = $db->prepare("
            SELECT po.*,
                   po.billing_duration_months,
                   CASE 
                       WHEN po.billing_cycle = 'one_time' THEN 'Achat unique'
                       WHEN po.billing_cycle = 'monthly' THEN 'Mensuel'
                       WHEN po.billing_cycle = 'quarterly' THEN 'Trimestriel'
                       WHEN po.billing_cycle = 'semi_yearly' THEN 'Semestriel'
                       WHEN po.billing_cycle = 'yearly' THEN 'Annuel'
                       WHEN po.billing_cycle = 'commitment_3' THEN 'Engagement 3 mois'
                       WHEN po.billing_cycle = 'commitment_6' THEN 'Engagement 6 mois'
                       WHEN po.billing_cycle = 'commitment_12' THEN 'Engagement 12 mois'
                       WHEN po.billing_cycle = 'commitment_24' THEN 'Engagement 24 mois'
                       ELSE 'Autre'
                   END as billing_label,
                   CASE 
                       WHEN po.billing_cycle = 'one_time' THEN ''
                       WHEN po.billing_cycle = 'monthly' THEN '/mois'
                       WHEN po.billing_cycle = 'quarterly' THEN '/trimestre'
                       WHEN po.billing_cycle = 'semi_yearly' THEN '/semestre'
                       WHEN po.billing_cycle = 'yearly' THEN '/an'
                       WHEN po.billing_cycle LIKE 'commitment_%' THEN '/mois'
                       ELSE ''
                   END as billing_suffix,
                   po.price as display_price,
                   CASE 
                       WHEN po.billing_cycle = 'one_time' THEN NULL
                       WHEN po.billing_cycle = 'monthly' THEN po.price
                       WHEN po.billing_cycle = 'quarterly' THEN po.price / 3
                       WHEN po.billing_cycle = 'semi_yearly' THEN po.price / 6
                       WHEN po.billing_cycle = 'yearly' THEN po.price / 12
                       WHEN po.billing_cycle LIKE 'commitment_%' THEN po.price
                       ELSE po.price
                   END as monthly_equivalent,
                   CASE 
                       WHEN po.manage_stock = 0 THEN 999
                       WHEN po.has_own_stock = 1 THEN po.stock_quantity
                       WHEN ? = 1 THEN ?
                       ELSE 999
                   END as current_stock
            FROM product_offers po
            WHERE po.access_type_id = ? AND po.is_active = 1
            ORDER BY po.is_default DESC, po.price ASC
        ");
        $offers_stmt->execute([
            $access_type['has_global_stock'], 
            $access_type['global_stock_quantity'],
            $access_type['id']
        ]);
        $offers = $offers_stmt->fetchAll();
        
                $media_stmt = $db->prepare("
            SELECT * FROM product_access_type_media 
            WHERE access_type_id = ? AND is_active = 1 
            ORDER BY display_order ASC
        ");
        $media_stmt->execute([$access_type['id']]);
        $access_type['instruction_media'] = $media_stmt->fetchAll();
        
                $available_offers = array_filter($offers, function($offer) {
            return $offer['current_stock'] > 0 || $offer['manage_stock'] == 0;
        });
        
                if (!empty($available_offers)) {
            $access_type['offers'] = array_values($available_offers);
            $access_types_with_offers[] = $access_type;
        }
    }
    
        $boosters_stmt = $db->prepare("
        SELECT pb.*, 
               pbe.custom_price_percentage, 
               pbe.custom_price_fixed,
               pbe.custom_price_min,
               pbe.custom_price_max
        FROM product_boosters pb
        JOIN product_booster_eligibility pbe ON pb.id = pbe.booster_id
        WHERE (pbe.product_id = ? OR pbe.category_id = ?) 
        AND pb.is_active = 1 AND pbe.is_active = 1
        ORDER BY pb.sort_order ASC
    ");
    $boosters_stmt->execute([$product['id'], $product['category_id']]);
    $boosters = $boosters_stmt->fetchAll();
    
        $reviews_stmt = $db->prepare("
        SELECT pr.*, u.username, u.first_name, u.last_name, u.avatar_url
        FROM product_reviews pr
        JOIN users u ON pr.user_id = u.id
        WHERE pr.product_id = ? AND pr.status = 'approved'
        ORDER BY pr.created_at DESC
        LIMIT 6
    ");
    $reviews_stmt->execute([$product['id']]);
    $reviews = $reviews_stmt->fetchAll();
    
        $similar_stmt = $db->prepare("
        SELECT p.*, pc.color as category_color, pt.icon as type_icon,
               MIN(po.price) as min_price
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        LEFT JOIN product_access_types pat ON p.id = pat.product_id
        LEFT JOIN product_offers po ON pat.id = po.access_type_id AND po.is_active = 1
        WHERE p.category_id = ? AND p.id != ? AND p.is_active = 1
        GROUP BY p.id
        HAVING min_price IS NOT NULL
        ORDER BY p.sale_count DESC
        LIMIT 4
    ");
    $similar_stmt->execute([$product['category_id'], $product['id']]);
    $similar_products = $similar_stmt->fetchAll();
    
        $images = json_decode($product['images'] ?? '[]', true);
    $features = json_decode($product['features'] ?? '[]', true);
    
        $auth = initAuth();
    $currentUser = $auth->getCurrentUser();
    $has_active_subscription = false;
    
    if ($currentUser) {
        $sub_check = $db->prepare("
            SELECT COUNT(*) as count
            FROM subscriptions s
            JOIN order_items oi ON s.order_item_id = oi.id
            WHERE s.user_id = ? AND oi.product_id = ? AND s.status = 'active'
        ");
        $sub_check->execute([$currentUser['id'], $product['id']]);
        $has_active_subscription = $sub_check->fetch()['count'] > 0;
    }
    
} catch (Exception $e) {
    error_log("Product detail error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    header('Location: /products');
    exit();
}

if (empty($access_types_with_offers)) {
    header('Location: /products');
    exit();
}

$seo = [
    'title' => htmlspecialchars($product['name']) . ' - Pilote Shopping',
    'description' => htmlspecialchars($product['short_description'] ?: substr(strip_tags($product['description']), 0, 160)),
    'keywords' => htmlspecialchars($product['name'] . ', ' . $product['category_name']),
    'og_type' => 'product',
    'og_image' => !empty($images) ? SITE_URL . $images[0] : SITE_URL . '/assets/images/product-placeholder.jpg'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>

<main id="main-content" class="min-h-screen">

    <nav class="py-4 border-b border-dark-600/50">
        <div class="container mx-auto px-4">
            <div class="flex items-center space-x-3 text-xs">
                <a href="/" class="text-gray-400 hover:text-white transition-colors flex items-center">
                    <i class="fas fa-home mr-1"></i>Accueil
                </a>
                <i class="fas fa-chevron-right text-gray-600 text-xs"></i>
                <a href="/products" class="text-gray-400 hover:text-white transition-colors">Boutique</a>
                <i class="fas fa-chevron-right text-gray-600 text-xs"></i>
                <a href="/products?category=<?= $product['category_id'] ?>" class="text-gray-400 hover:text-white transition-colors">
<?= htmlspecialchars($product['category_name']) ?>
                </a>
                <i class="fas fa-chevron-right text-gray-600 text-xs"></i>
                <span class="text-white font-medium"><?= htmlspecialchars($product['name']) ?></span>
            </div>
        </div>
    </nav>

    <section class="py-8">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">

                <div class="xl:col-span-4 space-y-4">

                    <div class="relative bg-dark-800/30 rounded-2xl overflow-hidden aspect-square border-2 border-dark-600/50 shadow-xl">
<?php if (!empty($images)): ?>
                        <img id="mainImage" 
                             src="<?= htmlspecialchars($images[0]) ?>" 
                             alt="<?= htmlspecialchars($product['name']) ?>"
                             class="w-full h-full object-cover p-4">
<?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="<?= htmlspecialchars($product['type_icon']) ?> text-4xl text-gray-400"></i>
                        </div>
<?php endif; ?>


                        <div class="absolute top-4 left-4 flex flex-col space-y-2">
<?php if ($product['is_featured']): ?>
                            <div class="bg-gradient-to-r from-yellow-500 to-orange-500 text-white px-3 py-1 rounded-xl shadow-lg text-xs font-bold">
                                <i class="fas fa-star mr-1"></i>Populaire
                            </div>
<?php endif; ?>

                            <div class="backdrop-blur-md rounded-xl px-3 py-1 border shadow-lg text-xs font-semibold" 
                                 style="background-color: <?= htmlspecialchars($product['category_color']) ?>15; border-color: <?= htmlspecialchars($product['category_color']) ?>; color: <?= htmlspecialchars($product['category_color']) ?>">
                                <i class="<?= htmlspecialchars($product['category_icon']) ?> mr-1"></i>
<?= htmlspecialchars($product['category_name']) ?>
                            </div>
                        </div>
                    </div>

<?php if (count($images) > 1): ?>
                    <div class="grid grid-cols-4 gap-2">
<?php foreach (array_slice($images, 0, 4) as $index => $image): ?>
                        <button onclick="changeMainImage('<?= htmlspecialchars($image) ?>', this)" 
                                class="thumbnail-btn relative bg-dark-800/50 rounded-xl overflow-hidden aspect-square border-2 border-transparent hover:border-blue-primary/50 transition-all duration-300 <?= $index === 0 ? 'border-blue-primary' : '' ?>">
                            <img src="<?= htmlspecialchars($image) ?>" 
                                 alt="Image <?= $index + 1 ?>"
                                 class="w-full h-full object-cover p-1">
                        </button>
<?php endforeach; ?>
                    </div>
<?php endif; ?>


                    <div id="access-instructions-section" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-4 backdrop-blur-sm hidden">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <div class="w-7 h-7 bg-cyan-500/20 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-info-circle text-cyan-400 text-sm"></i>
                            </div>
                            <span id="access-instructions-title">Informations importantes</span>
                        </h3>

                        <div id="access-instructions-content" class="space-y-4"></div>
                    </div>

                    <div class="bg-gradient-to-br from-green-500/10 to-emerald-500/10 border border-green-500/30 rounded-2xl p-4 backdrop-blur-sm">
                        <h3 class="text-green-400 font-bold text-lg mb-3 flex items-center">
                            <i class="fas fa-shipping-fast mr-2 text-xl"></i>
                            Livraison & Activation
                        </h3>

                        <div class="space-y-3">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-green-400/20 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-bolt text-green-400 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-green-300 font-semibold text-sm">Livraison sous 72h maximum</p>
                                    <p class="text-green-400/80 text-xs">Souvent plus rapide !</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-blue-400/20 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-envelope text-blue-400 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-blue-300 font-semibold text-sm">Notification par email</p>
                                    <p class="text-blue-400/80 text-xs">Instructions détaillées incluses</p>
                                </div>
                            </div>

                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 bg-purple-400/20 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-headset text-purple-400 text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-purple-300 font-semibold text-sm">Support dédié</p>
                                    <p class="text-purple-400/80 text-xs">Aide personnalisée si besoin</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-5 space-y-4">

                    <div class="space-y-3">
                        <div class="flex items-center space-x-4 flex-wrap">
<?php if ($product['rating_count'] > 0): ?>
                            <div class="flex items-center bg-dark-800/50 rounded-xl px-3 py-2 border border-dark-600/50">
                                <div class="flex text-yellow-400 mr-2 text-sm">
<?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= round($product['rating_average']) ? '' : 'text-gray-600' ?>"></i>
<?php endfor; ?>
                                </div>
                                <span class="text-white font-semibold text-sm"><?= number_format($product['rating_average'], 1) ?></span>
                                <span class="text-gray-400 ml-1 text-xs">(<?= $product['rating_count'] ?> avis)</span>
                            </div>
<?php endif; ?>

                            <div class="flex items-center bg-dark-800/50 rounded-xl px-3 py-2 border border-dark-600/50 text-gray-300 text-sm">
                                <i class="fas fa-eye mr-1 text-blue-primary"></i>
                                <span class="font-medium"><?= number_format($product['view_count']) ?> vues</span>
                            </div>
                        </div>

                        <h1 class="text-2xl lg:text-3xl font-bold text-white leading-tight">
<?= htmlspecialchars($product['name']) ?>
                        </h1>

                        <p class="text-base text-gray-300 leading-relaxed">
<?= htmlspecialchars($product['short_description']) ?>
                        </p>
                    </div>

<?php if ($has_active_subscription): ?>
                    <div class="bg-gradient-to-r from-green-500/10 to-emerald-500/10 border border-green-500/30 rounded-2xl p-4 backdrop-blur-sm">
                        <div class="flex items-center space-x-4">
                            <div class="w-10 h-10 bg-green-500/20 rounded-xl flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-400 text-lg"></i>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-green-400 font-bold text-lg">Abonnement actif</h3>
                                <p class="text-green-300 mb-2 text-sm">Vous avez déjà un abonnement actif pour ce produit</p>
                                <a href="/dashboard/subscriptions" class="text-green-400 hover:text-green-300 font-medium inline-flex items-center group text-sm">
                                    <i class="fas fa-cog mr-1 group-hover:rotate-90 transition-transform"></i>
                                    Gérer mon abonnement
                                </a>
                            </div>
                        </div>
                    </div>
<?php endif; ?>


                    <div class="space-y-4">

                        <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-4 backdrop-blur-sm">
                            <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                                <div class="w-7 h-7 bg-blue-primary/20 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-blue-primary font-bold text-sm">1</span>
                                </div>
                                Type d'accès
                            </h3>

                            <div class="space-y-3">
<?php foreach ($access_types_with_offers as $index => $access_type): ?>
                                <div class="access-type-option-wrapper">
                                    <input type="radio" 
                                           name="access_type" 
                                           value="<?= $access_type['id'] ?>"
                                           id="access_type_<?= $access_type['id'] ?>"
                                           data-name="<?= htmlspecialchars($access_type['name']) ?>"
                                           data-offers='<?= json_encode($access_type['offers']) ?>'
                                           data-delivery-details="<?= htmlspecialchars($access_type['delivery_details'] ?? '') ?>"
                                           data-delivery-time="<?= htmlspecialchars($access_type['estimated_delivery_time'] ?? '') ?>"
                                           data-support-level="<?= $access_type['support_level'] ?>"
                                           data-is-first="<?= $index === 0 ? 'true' : 'false' ?>"
<?= $index === 0 ? 'checked' : '' ?>
                                           class="sr-only access-type-radio">

                                    <label for="access_type_<?= $access_type['id'] ?>" 
                                           class="access-type-option block cursor-pointer transition-all duration-300">
                                        <div class="access-type-container p-4 bg-dark-700/30 rounded-xl border-2 border-dark-600/50 hover:border-blue-primary/50 transition-all duration-300">
                                            <div class="flex items-start justify-between mb-3">
                                                <div class="flex items-center space-x-3">
                                                    <div class="selection-indicator w-5 h-5 border-2 border-gray-400 rounded-full flex items-center justify-center transition-all duration-300">
                                                        <div class="selection-inner w-2.5 h-2.5 bg-blue-primary rounded-full opacity-0 scale-0 transition-all duration-300"></div>
                                                    </div>

                                                    <div class="w-8 h-8 bg-blue-primary/20 rounded-lg flex items-center justify-center">
                                                        <i class="<?= htmlspecialchars($access_type['icon']) ?> text-sm text-blue-primary"></i>
                                                    </div>

                                                    <div>
                                                        <h4 class="font-bold text-sm text-white">
<?= htmlspecialchars($access_type['name']) ?>
                                                        </h4>
<?php if ($access_type['description']): ?>
                                                        <p class="text-xs text-gray-300 mt-1">
<?= htmlspecialchars($access_type['description']) ?>
                                                        </p>
<?php endif; ?>
                                                    </div>
                                                </div>

                                                <div class="text-right">
                                                    <div class="font-bold text-sm text-white">
                                                        À partir de <?= number_format($access_type['min_price'], 2) ?>€
                                                    </div>

<?php
                                                    $support_badges = [
                                                        'basic' => ['text' => 'Support basique', 'color' => 'gray'],
                                                        'standard' => ['text' => 'Support standard', 'color' => 'blue'],
                                                        'premium' => ['text' => 'Support premium', 'color' => 'purple'],
                                                        'white_glove' => ['text' => 'Service VIP', 'color' => 'yellow']
                                                    ];
                                                    $badge = $support_badges[$access_type['support_level']] ?? $support_badges['standard'];
                                                    ?>
                                                    <div class="text-xs text-<?= $badge['color'] ?>-400 mt-1">
                                                        <i class="fas fa-headset mr-1"></i><?= $badge['text'] ?>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="bg-dark-800/30 rounded-lg p-3 border border-dark-600/30">
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                                    <div class="flex items-center text-green-400">
                                                        <i class="fas fa-clock mr-1"></i>
                                                        <span><?= htmlspecialchars($access_type['estimated_delivery_time'] ?? 'Sous 72h') ?></span>
                                                    </div>
                                                    <div class="flex items-center text-blue-400">
                                                        <i class="fas fa-cog mr-1"></i>
                                                        <span><?= getAccessMethodLabel($access_type['access_method']) ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
<?php endforeach; ?>
                            </div>
                        </div>

                        <div id="offers-section" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-4 backdrop-blur-sm">
                            <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                                <div class="w-7 h-7 bg-purple-500/20 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-purple-400 font-bold text-sm">2</span>
                                </div>
                                Choisissez votre offre
                            </h3>

                            <div id="offers-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-3"></div>
                        </div>

                        <div id="custom-fields-section" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-4 backdrop-blur-sm hidden">
                            <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                                <div class="w-7 h-7 bg-cyan-500/20 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-cyan-400 font-bold text-sm">3</span>
                                </div>
                                Informations requises
                                <span class="ml-2 text-red-400 text-xs">*</span>
                            </h3>

                            <div id="custom-fields-container" class="space-y-4"></div>
                        </div>

<?php if (!empty($boosters)): ?>
                        <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-4 backdrop-blur-sm">
                            <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                                <div class="w-7 h-7 bg-orange-500/20 rounded-lg flex items-center justify-center mr-3">
                                    <span class="text-orange-400 font-bold text-sm" id="booster-step-number">4</span>
                                </div>
                                Options supplémentaires
                                <span class="ml-2 text-gray-400 text-sm font-normal">(optionnel)</span>
                            </h3>

                            <div class="space-y-3">
<?php foreach ($boosters as $booster): ?>
                                <div class="booster-option-wrapper">
                                    <input type="checkbox" 
                                           name="boosters[]" 
                                           value="<?= $booster['id'] ?>"
                                           id="booster_<?= $booster['id'] ?>"
                                           data-price-type="<?= $booster['pricing_type'] ?>"
                                           data-price-percentage="<?= $booster['custom_price_percentage'] ?: $booster['price_percentage'] ?>"
                                           data-price-fixed="<?= $booster['custom_price_fixed'] ?: $booster['price_fixed'] ?>"
                                           data-min-price="<?= $booster['custom_price_min'] ?: $booster['price_min'] ?>"
                                           data-max-price="<?= $booster['custom_price_max'] ?: $booster['price_max'] ?>"
                                           class="sr-only">

                                    <label for="booster_<?= $booster['id'] ?>" 
                                           class="booster-option block cursor-pointer transition-all duration-300 hover:transform hover:scale-[1.01]">
                                        <div class="booster-container flex items-center justify-between p-3 bg-dark-700/20 rounded-xl border-2 border-dark-600/50 transition-all duration-300 hover:border-orange-400/50">
                                            <div class="flex items-center space-x-3">
                                                <div class="checkbox-indicator w-5 h-5 border-2 border-gray-400 rounded-lg flex items-center justify-center transition-all duration-300">
                                                    <i class="fas fa-check text-orange-400 opacity-0 scale-0 transition-all duration-300 text-xs"></i>
                                                </div>

                                                <div class="w-8 h-8 bg-orange-400/20 rounded-lg flex items-center justify-center">
                                                    <i class="<?= htmlspecialchars($booster['icon']) ?> text-orange-400 text-sm"></i>
                                                </div>

                                                <div class="flex-1">
                                                    <h5 class="font-bold text-white text-sm"><?= htmlspecialchars($booster['name']) ?></h5>
                                                    <p class="text-xs text-gray-400"><?= htmlspecialchars($booster['description']) ?></p>
                                                </div>
                                            </div>

                                            <div class="text-orange-300 font-bold text-sm ml-2 booster-price-display">
<?php if ($booster['pricing_type'] === 'percentage'): ?>
                                                +<?= ($booster['custom_price_percentage'] ?: $booster['price_percentage']) ?>%
<?php else: ?>
                                                +<?= number_format($booster['custom_price_fixed'] ?: $booster['price_fixed'], 2) ?>€
<?php endif; ?>
                                            </div>
                                        </div>
                                    </label>
                                </div>
<?php endforeach; ?>
                            </div>
                        </div>
<?php endif; ?>
                    </div>
                </div>

                <div class="xl:col-span-3">
                    <div class="sticky top-4">
                        <div class="bg-gradient-to-br from-dark-800/50 to-dark-900/50 border border-dark-600/50 rounded-2xl p-4 backdrop-blur-sm">
                            <h4 class="text-lg font-bold text-white mb-4 flex items-center">
                                <i class="fas fa-receipt text-blue-primary mr-2"></i>
                                Votre sélection
                            </h4>

                            <div id="order-summary" class="space-y-3 mb-4">
                                <div class="text-gray-400 text-center py-6">
                                    <i class="fas fa-spinner fa-spin text-2xl mb-2"></i>
                                    <p class="text-sm">Chargement...</p>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <button id="add-to-cart-btn" 
                                        onclick="addToCart()" 
                                        class="w-full bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white font-bold text-base py-3 px-4 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-xl hover:shadow-2xl disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                        disabled>
                                    <i class="fas fa-shopping-cart mr-2"></i>
                                    <span id="cart-btn-text">Chargement...</span>
                                </button>

                                <button onclick="toggleWishlist(<?= $product['id'] ?>)" 
                                        class="w-full bg-dark-700/50 hover:bg-dark-600/50 text-white font-semibold py-2 px-4 rounded-xl transition-all border border-dark-600/50 hover:border-red-400/50 group text-sm">
                                    <i class="far fa-heart mr-1 group-hover:fas group-hover:text-red-400 transition-all"></i>
                                    Ajouter aux favoris
                                </button>

                                <div class="flex items-center justify-center space-x-4 text-gray-400 text-xs pt-2">
                                    <div class="flex items-center">
                                        <i class="fas fa-shield-check text-green-400 mr-1"></i>
                                        <span>Sécurisé</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-bolt text-yellow-400 mr-1"></i>
                                        <span>Rapide</span>
                                    </div>
                                    <div class="flex items-center">
                                        <i class="fas fa-headset text-blue-400 mr-1"></i>
                                        <span>Support</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-12 border-t border-dark-600/50">
        <div class="container mx-auto px-4">

            <div class="flex flex-wrap justify-center border-b border-dark-600/50 mb-8">
                <button class="tab-btn active px-6 py-3 font-semibold text-white border-b-2 border-blue-primary transition-all relative group text-sm" 
                        onclick="showTab('description')">
                    <i class="fas fa-file-alt mr-1"></i>Description
                </button>
                <button class="tab-btn px-6 py-3 font-semibold text-gray-400 hover:text-white border-b-2 border-transparent hover:border-gray-400 transition-all relative group text-sm" 
                        onclick="showTab('delivery-guide')">
                    <i class="fas fa-shipping-fast mr-1"></i>Livraison & Setup
                </button>
<?php if (!empty($features)): ?>
                <button class="tab-btn px-6 py-3 font-semibold text-gray-400 hover:text-white border-b-2 border-transparent hover:border-gray-400 transition-all relative group text-sm" 
                        onclick="showTab('features')">
                    <i class="fas fa-list-check mr-1"></i>Caractéristiques
                </button>
<?php endif; ?>
<?php if (!empty($reviews)): ?>
                <button class="tab-btn px-6 py-3 font-semibold text-gray-400 hover:text-white border-b-2 border-transparent hover:border-gray-400 transition-all relative group text-sm" 
                        onclick="showTab('reviews')">
                    <i class="fas fa-star mr-1"></i>Avis (<?= count($reviews) ?>)
                </button>
<?php endif; ?>
                <button class="tab-btn px-6 py-3 font-semibold text-gray-400 hover:text-white border-b-2 border-transparent hover:border-gray-400 transition-all relative group text-sm" 
                        onclick="showTab('support')">
                    <i class="fas fa-life-ring mr-1"></i>Support
                </button>
            </div>

            <div class="tab-content max-w-5xl mx-auto">

                <div id="description" class="tab-panel">
<?php if ($product['description']): ?>
                    <div class="prose prose-invert prose-base max-w-none">
                        <div class="bg-dark-800/30 rounded-2xl p-6 border border-dark-600/50">
                            <div class="text-gray-300 leading-relaxed">
<?= nl2br(htmlspecialchars($product['description'])) ?>
                            </div>
                        </div>
                    </div>
<?php else: ?>
                    <div class="text-center py-12">
                        <i class="fas fa-file-alt text-4xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl font-bold text-white mb-3">Description en cours</h3>
                        <p class="text-gray-400">La description détaillée de ce produit sera bientôt disponible.</p>
                    </div>
<?php endif; ?>
                </div>

                <div id="delivery-guide" class="tab-panel hidden">
                    <div class="space-y-6">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-white mb-3">Comment ça marche ?</h3>
                            <p class="text-gray-400">De la commande à l'activation, voici le processus complet</p>
                        </div>

                        <div class="relative">
                            <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gradient-to-b from-blue-primary to-purple-500 rounded-full"></div>

                            <div class="space-y-8">
                                <div class="flex items-start space-x-6">
                                    <div class="w-8 h-8 bg-blue-primary rounded-full flex items-center justify-center relative z-10">
                                        <i class="fas fa-shopping-cart text-white text-sm"></i>
                                    </div>
                                    <div class="bg-dark-800/30 rounded-xl p-4 border border-dark-600/50 flex-1">
                                        <h4 class="text-white font-bold mb-2">1. Commande validée</h4>
                                        <p class="text-gray-300 text-sm">Votre commande est immédiatement prise en compte et notre équipe prépare votre livraison.</p>
                                    </div>
                                </div>

                                <div class="flex items-start space-x-6">
                                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center relative z-10">
                                        <i class="fas fa-cogs text-white text-sm"></i>
                                    </div>
                                    <div class="bg-dark-800/30 rounded-xl p-4 border border-dark-600/50 flex-1">
                                        <h4 class="text-white font-bold mb-2">2. Préparation (automatique ou manuelle)</h4>
                                        <p class="text-gray-300 text-sm mb-3">Selon le type d'accès choisi, votre produit est soit activé automatiquement, soit préparé manuellement par notre équipe.</p>
                                        <div class="bg-dark-700/50 rounded-lg p-3">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                                <div class="flex items-center text-green-400">
                                                    <i class="fas fa-bolt mr-2"></i>
                                                    <span>Automatique : Instantané</span>
                                                </div>
                                                <div class="flex items-center text-yellow-400">
                                                    <i class="fas fa-user-cog mr-2"></i>
                                                    <span>Manuel : Sous 72h max</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-start space-x-6">
                                    <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center relative z-10">
                                        <i class="fas fa-envelope text-white text-sm"></i>
                                    </div>
                                    <div class="bg-dark-800/30 rounded-xl p-4 border border-dark-600/50 flex-1">
                                        <h4 class="text-white font-bold mb-2">3. Livraison par email</h4>
                                        <p class="text-gray-300 text-sm mb-3">Vous recevez un email détaillé avec toutes les informations d'accès et les instructions d'activation.</p>
                                        <div class="bg-dark-700/50 rounded-lg p-3">
                                            <h5 class="text-white font-semibold text-sm mb-2">L'email contient :</h5>
                                            <ul class="text-gray-300 text-xs space-y-1">
                                                <li>• Les identifiants ou codes d'accès</li>
                                                <li>• Instructions étape par étape</li>
                                                <li>• Conseils de sécurité</li>
                                                <li>• Liens vers le support si besoin</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-start space-x-6">
                                    <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center relative z-10">
                                        <i class="fas fa-check-circle text-white text-sm"></i>
                                    </div>
                                    <div class="bg-dark-800/30 rounded-xl p-4 border border-dark-600/50 flex-1">
                                        <h4 class="text-white font-bold mb-2">4. Activation et utilisation</h4>
                                        <p class="text-gray-300 text-sm">Suivez les instructions pour activer votre produit. Notre support est disponible si vous rencontrez des difficultés.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-dark-800/30 rounded-2xl p-6 border border-dark-600/50 mt-8">
                            <h4 class="text-xl font-bold text-white mb-4">Questions fréquentes</h4>
                            <div class="space-y-4">
                                <div class="border-b border-dark-600/30 pb-4">
                                    <h5 class="text-white font-semibold mb-2 text-sm">⏰ Combien de temps pour recevoir ma commande ?</h5>
                                    <p class="text-gray-300 text-sm">La majorité des commandes sont livrées sous 24h. Le délai maximum est de 72h pour tous nos produits.</p>
                                </div>
                                <div class="border-b border-dark-600/30 pb-4">
                                    <h5 class="text-white font-semibold mb-2 text-sm">📧 Et si je ne reçois pas l'email ?</h5>
                                    <p class="text-gray-300 text-sm">Vérifiez votre dossier spam. Si rien après 2h, contactez notre support avec votre numéro de commande.</p>
                                </div>
                                <div>
                                    <h5 class="text-white font-semibold mb-2 text-sm">🔒 Mes données sont-elles sécurisées ?</h5>
                                    <p class="text-gray-300 text-sm">Absolument. Nous utilisons un cryptage SSL 256-bit et ne stockons aucune donnée sensible après livraison.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

<?php if (!empty($features)): ?>
                <div id="features" class="tab-panel hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
<?php foreach ($features as $feature): ?>
                        <div class="bg-dark-800/30 rounded-2xl p-4 border border-dark-600/50 hover:border-green-400/50 transition-all duration-300 group">
                            <div class="flex items-center mb-3">
                                <div class="w-8 h-8 bg-green-400/20 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-check-circle text-green-400 text-sm"></i>
                                </div>
                                <h5 class="text-base font-bold text-white">
<?= htmlspecialchars(is_array($feature) ? $feature['title'] : $feature) ?>
                                </h5>
                            </div>
<?php if (is_array($feature) && isset($feature['description'])): ?>
                            <p class="text-gray-400 leading-relaxed text-sm"><?= htmlspecialchars($feature['description']) ?></p>
<?php endif; ?>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>
<?php endif; ?>


<?php if (!empty($reviews)): ?>
                <div id="reviews" class="tab-panel hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<?php foreach ($reviews as $review): ?>
                        <div class="bg-dark-800/30 rounded-2xl p-4 border border-dark-600/50 hover:border-yellow-400/30 transition-all duration-300">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <img src="<?= $review['avatar_url'] ?: 'https://www.gravatar.com/avatar/' . md5(strtolower($review['username'])) . '?s=32&d=mp' ?>" 
                                         alt="Avatar" 
                                         class="w-8 h-8 rounded-full border-2 border-blue-primary/30">
                                    <div>
                                        <div class="font-bold text-white text-sm">
<?= htmlspecialchars($review['first_name'] ? $review['first_name'] . ' ' . substr($review['last_name'], 0, 1) . '.' : $review['username']) ?>
                                        </div>
                                        <div class="text-xs text-gray-400">
<?= date('d/m/Y', strtotime($review['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex text-yellow-400 text-xs">
<?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star <?= $i <= $review['rating'] ? '' : 'text-gray-600' ?>"></i>
<?php endfor; ?>
                                </div>
                            </div>

<?php if ($review['title']): ?>
                            <h6 class="font-bold text-white mb-2 text-sm"><?= htmlspecialchars($review['title']) ?></h6>
<?php endif; ?>

                            <p class="text-gray-300 leading-relaxed text-sm"><?= nl2br(htmlspecialchars($review['comment'])) ?></p>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>
<?php endif; ?>


                <div id="support" class="tab-panel hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-dark-800/30 rounded-2xl p-6 border border-dark-600/50">
                            <h5 class="text-xl font-bold text-white mb-4 flex items-center">
                                <i class="fas fa-shipping-fast text-blue-primary mr-3 text-2xl"></i>
                                Livraison & Activation
                            </h5>
                            <div class="space-y-4">
                                <div class="flex items-center p-3 bg-dark-700/30 rounded-xl">
                                    <i class="fas fa-bolt text-yellow-400 mr-3"></i>
                                    <div>
                                        <h6 class="text-white font-semibold text-sm">Activation automatique</h6>
                                        <p class="text-gray-300 text-xs">Quand techniquement possible</p>
                                    </div>
                                </div>
                                <div class="flex items-center p-3 bg-dark-700/30 rounded-xl">
                                    <i class="fas fa-clock text-blue-400 mr-3"></i>
                                    <div>
                                        <h6 class="text-white font-semibold text-sm">Délai garanti</h6>
                                        <p class="text-gray-300 text-xs">Maximum 72 heures</p>
                                    </div>
                                </div>
                                <div class="flex items-center p-3 bg-dark-700/30 rounded-xl">
                                    <i class="fas fa-envelope text-green-400 mr-3"></i>
                                    <div>
                                        <h6 class="text-white font-semibold text-sm">Notification</h6>
                                        <p class="text-gray-300 text-xs">Par email + dashboard</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-dark-800/30 rounded-2xl p-6 border border-dark-600/50">
                            <h5 class="text-xl font-bold text-white mb-4 flex items-center">
                                <i class="fas fa-shield-check text-green-400 mr-3 text-2xl"></i>
                                Garanties & Support
                            </h5>
                            <div class="space-y-4">
                                <div class="flex items-center p-3 bg-dark-700/30 rounded-xl">
                                    <i class="fas fa-undo text-blue-400 mr-3"></i>
                                    <div>
                                        <h6 class="text-white font-semibold text-sm">Remboursement</h6>
                                        <p class="text-gray-300 text-xs">Si produit défaillant</p>
                                    </div>
                                </div>
                                <div class="flex items-center p-3 bg-dark-700/30 rounded-xl">
                                    <i class="fas fa-headset text-purple-400 mr-3"></i>
                                    <div>
                                        <h6 class="text-white font-semibold text-sm">Support client</h6>
                                        <p class="text-gray-300 text-xs">Équipe dédiée et réactive</p>
                                    </div>
                                </div>
                                <div class="flex items-center p-3 bg-dark-700/30 rounded-xl">
                                    <i class="fas fa-lock text-red-400 mr-3"></i>
                                    <div>
                                        <h6 class="text-white font-semibold text-sm">Paiement sécurisé</h6>
                                        <p class="text-gray-300 text-xs">Cryptage SSL 256-bit</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

<?php if (!empty($similar_products)): ?>
    <section class="py-12 border-t border-dark-600/50">
        <div class="container mx-auto px-4">
            <h3 class="text-2xl font-bold text-white mb-8 text-center">
                <i class="fas fa-heart text-red-400 mr-3"></i>
                Découvrez aussi
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
<?php foreach ($similar_products as $similar): ?>
                <div class="group bg-dark-800/30 rounded-2xl border border-dark-600/50 overflow-hidden hover:border-blue-primary/50 transition-all duration-300 hover:transform hover:scale-105">
                    <div class="relative h-32 bg-gradient-to-br from-dark-700 to-dark-800">
<?php 
                        $sim_images = json_decode($similar['images'] ?? '[]', true);
                        $sim_image = !empty($sim_images) ? $sim_images[0] : null;
                        ?>

<?php if ($sim_image): ?>
                        <img src="<?= htmlspecialchars($sim_image) ?>" 
                             alt="<?= htmlspecialchars($similar['name']) ?>"
                             class="w-full h-full object-cover p-2">
<?php else: ?>
                        <div class="w-full h-full flex items-center justify-center">
                            <i class="<?= htmlspecialchars($similar['type_icon']) ?> text-2xl text-gray-400"></i>
                        </div>
<?php endif; ?>
                    </div>

                    <div class="p-4">
                        <h4 class="text-sm font-bold text-white mb-2 group-hover:text-blue-primary transition-colors line-clamp-2">
<?= htmlspecialchars($similar['name']) ?>
                        </h4>
                        <p class="text-gray-400 text-xs mb-3 line-clamp-2">
<?= htmlspecialchars($similar['short_description']) ?>
                        </p>
                        <div class="flex items-center justify-between">
                            <span class="text-lg font-bold text-white">
<?= $similar['min_price'] ? number_format($similar['min_price'], 2) . '€' : 'Sur devis' ?>
                            </span>
                            <a href="/products/<?= htmlspecialchars($similar['slug']) ?>" 
                               class="bg-blue-primary hover:bg-blue-secondary text-white font-semibold px-3 py-1 rounded-lg transition-all transform hover:scale-105 text-xs">
                                <i class="fas fa-eye mr-1"></i>Voir
                            </a>
                        </div>
                    </div>
                </div>
<?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

</main>

<?php require_once '../includes/footer.php'; ?>

<script>

const productId = <?= $product['id'] ?>;
let selectedAccessType = null;
let selectedOffer = null;
let selectedBoosters = [];
let currentPrice = 0;
let currentOffers = [];
let customFields = [];
let requiredCustomFields = [];
let accessTypesData = {};

let isInitialized = false;

const accessTypesInstructions = {
<?php foreach ($access_types_with_offers as $access_type): ?>
<?= $access_type['id'] ?>: {
        name: <?= json_encode($access_type['name']) ?>,
        user_instructions: <?= json_encode($access_type['user_instructions'] ?? '') ?>,
        user_instructions_video_url: <?= json_encode($access_type['user_instructions_video_url'] ?? '') ?>,
        important_notes: <?= json_encode($access_type['important_notes'] ?? '') ?>,
        delivery_details: <?= json_encode($access_type['delivery_details'] ?? '') ?>,
        setup_requirements: <?= json_encode($access_type['setup_requirements'] ?? '') ?>,
        compatibility_info: <?= json_encode($access_type['compatibility_info'] ?? '') ?>,
        technical_specs: <?= json_encode($access_type['technical_specs'] ?? '') ?>,
        usage_limitations: <?= json_encode($access_type['usage_limitations'] ?? '') ?>,
        estimated_delivery_time: <?= json_encode($access_type['estimated_delivery_time'] ?? '') ?>,
        activation_method: <?= json_encode($access_type['activation_method'] ?? '') ?>,
        troubleshooting_tips: <?= json_encode($access_type['troubleshooting_tips'] ?? '') ?>,
        instruction_media: <?= json_encode($access_type['instruction_media'] ?? []) ?>
    },
<?php endforeach; ?>
};

document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Initialisation de la page produit...');

    initializeAccessTypes();
    initializeBoosters();

    setTimeout(() => {
        forceCompleteInitialization();
    }, 50);
});

function forceCompleteInitialization() {
    console.log('🔧 Initialisation complète forcée...');

    const accessTypes = document.querySelectorAll('input[name="access_type"]');
    if (accessTypes.length === 0) {
        console.error('❌ Aucun type d\'accès trouvé');
        return;
    }

    const firstAccessType = accessTypes[0];
    if (!firstAccessType.checked) {
        console.log('🔄 Forçage de la sélection du premier type d\'accès');
        firstAccessType.checked = true;
    }

    handleAccessTypeChange(firstAccessType);
    isInitialized = true;
    console.log('✅ Initialisation complète terminée');
}

function initializeAccessTypes() {
    console.log('🎯 Initialisation des types d\'accès...');

    document.querySelectorAll('input[name="access_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('📡 Changement de type d\'accès détecté:', this.value);
            if (this.checked) {
                handleAccessTypeChange(this);
            }
        });
    });

    console.log('✅ Types d\'accès initialisés');
}

function handleAccessTypeChange(radioElement) {
    console.log('🔄 Traitement du changement de type d\'accès:', radioElement.value);

    selectedAccessType = parseInt(radioElement.value);
    currentOffers = JSON.parse(radioElement.dataset.offers);

    selectedOffer = null;
    currentPrice = 0;

    updateAccessTypeSelection();
    showAccessTypeInstructions(selectedAccessType);
    showOffersSection();
    loadCustomFields();
    updateOrderSummary();

    console.log('✅ Type d\'accès traité avec succès');
}

function updateAccessTypeSelection() {
    console.log('🎨 Mise à jour de l\'affichage des types d\'accès...');

    document.querySelectorAll('.access-type-option').forEach(option => {
        const wrapper = option.closest('.access-type-option-wrapper');
        const radio = wrapper.querySelector('input[type="radio"]');
        const container = option.querySelector('.access-type-container');
        const indicator = option.querySelector('.selection-indicator');
        const innerCircle = indicator.querySelector('.selection-inner');

        if (radio && radio.checked) {
            console.log('✅ Activation visuelle pour:', radio.value);
            container.classList.add('border-blue-primary', 'bg-blue-primary/5');
            indicator.classList.add('border-blue-primary');
            innerCircle.classList.remove('opacity-0', 'scale-0');
            innerCircle.classList.add('opacity-100', 'scale-100');
        } else {
            container.classList.remove('border-blue-primary', 'bg-blue-primary/5');
            indicator.classList.remove('border-blue-primary');
            innerCircle.classList.add('opacity-0', 'scale-0');
            innerCircle.classList.remove('opacity-100', 'scale-100');
        }
    });

    console.log('✅ Affichage des types d\'accès mis à jour');
}

function showAccessTypeInstructions(accessTypeId) {
    const instructionsSection = document.getElementById('access-instructions-section');
    const instructionsContent = document.getElementById('access-instructions-content');
    const instructionsTitle = document.getElementById('access-instructions-title');

    const instructions = accessTypesInstructions[accessTypeId];

    if (!instructions || (!instructions.user_instructions && !instructions.important_notes && !instructions.delivery_details)) {
        instructionsSection.classList.add('hidden');
        return;
    }

    instructionsTitle.textContent = `Informations pour "${instructions.name}"`;

    let html = '';

    if (instructions.important_notes) {
        html += `
            <div class="mb-4">
                ${instructions.important_notes}
            </div>
        `;
    }

    if (instructions.user_instructions) {
        html += `
            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30 mb-4">
                <h4 class="text-white font-bold mb-2 flex items-center">
                    <i class="fas fa-list-ol text-cyan-400 mr-2"></i>
                    Instructions d'utilisation
                </h4>
                <div class="text-gray-300 text-sm leading-relaxed">
                    ${instructions.user_instructions}
                </div>
            </div>
        `;
    }

    if (instructions.user_instructions_video_url) {
        html += `
            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30 mb-4">
                <h4 class="text-white font-bold mb-3 flex items-center">
                    <i class="fas fa-play-circle text-red-400 mr-2"></i>
                    Tutoriel vidéo
                </h4>
                <div class="aspect-video bg-dark-800 rounded-lg overflow-hidden">
                    <iframe src="${instructions.user_instructions_video_url}" 
                            class="w-full h-full" 
                            frameborder="0" 
                            allowfullscreen></iframe>
                </div>
            </div>
        `;
    }

    if (instructions.instruction_media && instructions.instruction_media.length > 0) {
        html += `
            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30 mb-4">
                <h4 class="text-white font-bold mb-3 flex items-center">
                    <i class="fas fa-images text-purple-400 mr-2"></i>
                    Ressources supplémentaires
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        `;

        instructions.instruction_media.forEach(media => {
            if (media.media_type === 'video') {
                html += `
                    <div class="bg-dark-800/50 rounded-lg p-3 border border-dark-600/30">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-play text-red-400 mr-2"></i>
                            <span class="text-white font-semibold text-sm">${media.media_title}</span>
                        </div>
                        <p class="text-gray-400 text-xs mb-2">${media.media_description}</p>
                        <a href="${media.media_url}" target="_blank" class="text-blue-400 hover:text-blue-300 text-xs">
                            <i class="fas fa-external-link-alt mr-1"></i>Voir la vidéo
                        </a>
                    </div>
                `;
            } else if (media.media_type === 'image') {
                html += `
                    <div class="bg-dark-800/50 rounded-lg p-3 border border-dark-600/30">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-image text-green-400 mr-2"></i>
                            <span class="text-white font-semibold text-sm">${media.media_title}</span>
                        </div>
                        <p class="text-gray-400 text-xs mb-2">${media.media_description}</p>
                        <a href="${media.media_url}" target="_blank" class="text-blue-400 hover:text-blue-300 text-xs">
                            <i class="fas fa-external-link-alt mr-1"></i>Voir l'image
                        </a>
                    </div>
                `;
            } else if (media.media_type === 'document') {
                html += `
                    <div class="bg-dark-800/50 rounded-lg p-3 border border-dark-600/30">
                        <div class="flex items-center mb-2">
                            <i class="fas fa-file-pdf text-orange-400 mr-2"></i>
                            <span class="text-white font-semibold text-sm">${media.media_title}</span>
                        </div>
                        <p class="text-gray-400 text-xs mb-2">${media.media_description}</p>
                        <a href="${media.media_url}" target="_blank" class="text-blue-400 hover:text-blue-300 text-xs">
                            <i class="fas fa-download mr-1"></i>Télécharger
                        </a>
                    </div>
                `;
            }
        });

        html += `
                </div>
            </div>
        `;
    }

    const specsGrid = [];
    if (instructions.compatibility_info) {
        specsGrid.push({
            icon: 'fas fa-check-circle',
            color: 'green',
            title: 'Compatibilité',
            content: instructions.compatibility_info
        });
    }
    if (instructions.technical_specs) {
        specsGrid.push({
            icon: 'fas fa-cog',
            color: 'blue',
            title: 'Spécifications',
            content: instructions.technical_specs
        });
    }
    if (instructions.setup_requirements) {
        specsGrid.push({
            icon: 'fas fa-tools',
            color: 'yellow',
            title: 'Prérequis',
            content: instructions.setup_requirements
        });
    }
    if (instructions.usage_limitations) {
        specsGrid.push({
            icon: 'fas fa-exclamation-triangle',
            color: 'orange',
            title: 'Limitations',
            content: instructions.usage_limitations
        });
    }

    if (specsGrid.length > 0) {
        html += `
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
        `;

        specsGrid.forEach(spec => {
            html += `
                <div class="bg-dark-700/30 rounded-xl p-3 border border-dark-600/30">
                    <h5 class="text-white font-semibold mb-2 flex items-center text-sm">
                        <i class="${spec.icon} text-${spec.color}-400 mr-2"></i>
                        ${spec.title}
                    </h5>
                    <div class="text-gray-300 text-xs">${spec.content}</div>
                </div>
            `;
        });

        html += `</div>`;
    }

    if (instructions.troubleshooting_tips) {
        html += `
            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                <h4 class="text-white font-bold mb-2 flex items-center">
                    <i class="fas fa-question-circle text-yellow-400 mr-2"></i>
                    En cas de problème
                </h4>
                <div class="text-gray-300 text-sm">
                    ${instructions.troubleshooting_tips}
                </div>
            </div>
        `;
    }

    instructionsContent.innerHTML = html;
    instructionsSection.classList.remove('hidden');
}

function showOffersSection() {
    console.log('🏪 Affichage de la section des offres...');

    const offersSection = document.getElementById('offers-section');
    const offersGrid = document.getElementById('offers-grid');

    if (!currentOffers.length) {
        console.warn('⚠️ Aucune offre disponible');
        return;
    }

    console.log('📦 Génération de', currentOffers.length, 'offres...');

    let html = '';

    currentOffers.forEach((offer, index) => {
        const isAvailable = offer.current_stock > 0 || offer.manage_stock == 0;
        const isSubscription = offer.billing_cycle !== 'one_time';

        const offerName = offer.name;
        const isNameLong = offerName.length > 25;
        const displayName = isNameLong ? offerName.substring(0, 22) + '...' : offerName;

        const displayPrice = parseFloat(offer.display_price);
        const billingSuffix = offer.billing_suffix || '';
        const billingLabel = offer.billing_label || 'Achat unique';

        html += `
            <div class="offer-option-wrapper">
                <input type="radio" 
                       name="selected_offer" 
                       value="${offer.id}" 
                       id="offer_${offer.id}"
                       data-price="${offer.price}"
                       data-display-price="${displayPrice}"
                       data-name="${offer.name}"
                       data-billing="${offer.billing_cycle}"
                       data-billing-label="${billingLabel}"
                       data-billing-suffix="${billingSuffix}"
                       data-monthly-equivalent="${offer.monthly_equivalent || ''}"
                       data-period-total="${offer.period_total_price || displayPrice}"
                       ${index === 0 && isAvailable ? 'checked' : ''}
                       ${isAvailable ? '' : 'disabled'}
                       class="sr-only offer-radio">

                <label for="offer_${offer.id}" 
                       class="offer-option block cursor-pointer transition-all duration-300 ${!isAvailable ? 'opacity-50 cursor-not-allowed' : 'hover:transform hover:scale-[1.01]'}">
                    <div class="offer-container bg-dark-700/20 rounded-xl border-2 border-dark-600/50 p-3 transition-all duration-300 hover:border-purple-400/50 h-full flex flex-col">

                        <div class="flex items-start justify-between mb-2">
                            <div class="selection-indicator w-4 h-4 border-2 border-gray-400 rounded-full flex items-center justify-center transition-all duration-300 mt-1">
                                <div class="selection-inner w-2 h-2 bg-purple-400 rounded-full opacity-0 scale-0 transition-all duration-300"></div>
                            </div>

                            ${isSubscription ? `
                                <span class="bg-purple-500/20 text-purple-400 px-2 py-1 rounded-lg text-xs font-bold">
                                    <i class="fas fa-sync-alt mr-1"></i>${billingLabel}
                                </span>
                            ` : `
                                <span class="bg-green-500/20 text-green-400 px-2 py-1 rounded-lg text-xs font-bold">
                                    <i class="fas fa-shopping-cart mr-1"></i>Unique
                                </span>
                            `}
                        </div>

                        <h5 class="font-bold text-white mb-2 text-sm ${isNameLong ? 'cursor-help' : ''}" 
                            ${isNameLong ? `title="${offer.name}"` : ''}>
                            ${displayName}
                        </h5>

                        ${offer.description ? `<p class="text-gray-400 text-xs mb-2 flex-grow">${offer.description.substring(0, 50)}${offer.description.length > 50 ? '...' : ''}</p>` : '<div class="flex-grow"></div>'}

                        <div class="text-center mb-2">
                            <div class="text-lg font-bold text-white">
                                ${displayPrice.toFixed(2)}€
                            </div>
                            <div class="text-xs ${isSubscription ? 'text-purple-400' : 'text-green-400'}">
                                ${billingSuffix || (isSubscription ? '/période' : 'unique')}
                            </div>
                            ${offer.monthly_equivalent && offer.monthly_equivalent != displayPrice ? `
                            <div class="text-xs text-gray-500 mt-1">
                                (${parseFloat(offer.monthly_equivalent).toFixed(2)}€/mois équiv.)
                            </div>
                            ` : ''}
                        </div>

                        <div class="text-center text-xs ${isAvailable ? 'text-green-400' : 'text-red-400'}">
                            <i class="fas fa-box mr-1"></i>
                            ${isAvailable ? (offer.current_stock >= 999 ? 'Disponible' : offer.current_stock + ' stock') : 'Épuisé'}
                        </div>
                    </div>
                </label>
            </div>
        `;
    });

    offersGrid.innerHTML = html;
    offersSection.classList.remove('hidden');

    console.log('✅ Section des offres affichée');

    initializeOffers();
}

function initializeOffers() {
    console.log('🎯 Initialisation des offres...');

    document.querySelectorAll('input[name="selected_offer"]').forEach(radio => {
        radio.addEventListener('change', function() {
            console.log('📡 Changement d\'offre détecté:', this.value);
            if (this.checked) {
                selectedOffer = parseInt(this.value);
                currentPrice = parseFloat(this.dataset.displayPrice);

                console.log('💰 Prix mis à jour:', currentPrice);

                updateOfferSelection();
                updateBoosterPrices();
                updateOrderSummary();
                updateCartButton();
            }
        });
    });

    setTimeout(() => {
        const firstOffer = document.querySelector('input[name="selected_offer"]:checked');
        if (firstOffer) {
            console.log('🎯 Auto-déclenchement pour l\'offre pré-sélectionnée:', firstOffer.value);
            selectedOffer = parseInt(firstOffer.value);
            currentPrice = parseFloat(firstOffer.dataset.displayPrice);

            updateOfferSelection();
            updateBoosterPrices();
            updateOrderSummary();
            updateCartButton();
        } else {
            const firstAvailableOffer = document.querySelector('input[name="selected_offer"]:not([disabled])');
            if (firstAvailableOffer) {
                console.log('🔄 Sélection forcée de la première offre disponible:', firstAvailableOffer.value);
                firstAvailableOffer.checked = true;
                firstAvailableOffer.dispatchEvent(new Event('change'));
            }
        }
    }, 100);

    console.log('✅ Offres initialisées');
}

function updateOfferSelection() {
    console.log('🎨 Mise à jour de l\'affichage des offres...');

    document.querySelectorAll('.offer-option').forEach(option => {
        const wrapper = option.closest('.offer-option-wrapper');
        const radio = wrapper.querySelector('input[type="radio"]');
        const container = option.querySelector('.offer-container');
        const indicator = option.querySelector('.selection-indicator');
        const innerCircle = indicator.querySelector('.selection-inner');

        if (radio && radio.checked) {
            console.log('✅ Activation visuelle pour l\'offre:', radio.value);
            container.classList.add('border-purple-400', 'bg-purple-400/5');
            indicator.classList.add('border-purple-400');
            innerCircle.classList.remove('opacity-0', 'scale-0');
            innerCircle.classList.add('opacity-100', 'scale-100');
        } else if (radio && !radio.disabled) {
            container.classList.remove('border-purple-400', 'bg-purple-400/5');
            indicator.classList.remove('border-purple-400');
            innerCircle.classList.add('opacity-0', 'scale-0');
            innerCircle.classList.remove('opacity-100', 'scale-100');
        }
    });

    console.log('✅ Affichage des offres mis à jour');
}

function initializeBoosters() {
    document.querySelectorAll('input[name="boosters[]"]').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const boosterId = parseInt(this.value);
            if (this.checked) {
                if (!selectedBoosters.includes(boosterId)) {
                    selectedBoosters.push(boosterId);
                }
            } else {
                selectedBoosters = selectedBoosters.filter(id => id !== boosterId);
            }
            updateBoosterSelection();
            updateOrderSummary();
        });
    });
}

function updateBoosterSelection() {
    document.querySelectorAll('.booster-option').forEach(option => {
        const wrapper = option.closest('.booster-option-wrapper');
        const checkbox = wrapper.querySelector('input[type="checkbox"]');
        const container = option.querySelector('.booster-container');
        const indicator = option.querySelector('.checkbox-indicator');
        const checkIcon = indicator.querySelector('i');

        if (checkbox && checkbox.checked) {
            container.classList.add('border-orange-400', 'bg-orange-400/5');
            indicator.classList.add('border-orange-400');
            checkIcon.classList.remove('opacity-0', 'scale-0');
            checkIcon.classList.add('opacity-100', 'scale-100');
        } else {
            container.classList.remove('border-orange-400', 'bg-orange-400/5');
            indicator.classList.remove('border-orange-400');
            checkIcon.classList.add('opacity-0', 'scale-0');
            checkIcon.classList.remove('opacity-100', 'scale-100');
        }
    });
}

function updateBoosterPrices() {
    if (!currentPrice) return;

    console.log('💸 Mise à jour des prix des boosters avec prix de base:', currentPrice);

    document.querySelectorAll('.booster-option-wrapper').forEach(wrapper => {
        const input = wrapper.querySelector('input[type="checkbox"]');
        const priceDisplay = wrapper.querySelector('.booster-price-display');

        if (input && priceDisplay) {
            const priceType = input.dataset.priceType;
            const pricePercentage = parseFloat(input.dataset.pricePercentage || 0);
            const priceFixed = parseFloat(input.dataset.priceFixed || 0);
            const minPrice = parseFloat(input.dataset.minPrice || 0);
            const maxPrice = parseFloat(input.dataset.maxPrice || 999999);

            let calculatedPrice = 0;

            if (priceType === 'percentage' && pricePercentage > 0) {
                calculatedPrice = currentPrice * (pricePercentage / 100);
                calculatedPrice = Math.max(calculatedPrice, minPrice);
                if (maxPrice < 999999) {
                    calculatedPrice = Math.min(calculatedPrice, maxPrice);
                }
                priceDisplay.textContent = `+${calculatedPrice.toFixed(2)}€`;
            } else if (priceType === 'fixed' && priceFixed > 0) {
                calculatedPrice = Math.max(priceFixed, minPrice);
                if (maxPrice < 999999) {
                    calculatedPrice = Math.min(calculatedPrice, maxPrice);
                }
                priceDisplay.textContent = `+${calculatedPrice.toFixed(2)}€`;
            }
        }
    });
}

function calculateTotalPrice() {
    let total = currentPrice;

    selectedBoosters.forEach(boosterId => {
        const boosterInput = document.querySelector(`input[name="boosters[]"][value="${boosterId}"]`);
        if (boosterInput) {
            const priceType = boosterInput.dataset.priceType;
            const pricePercentage = parseFloat(boosterInput.dataset.pricePercentage || 0);
            const priceFixed = parseFloat(boosterInput.dataset.priceFixed || 0);
            const minPrice = parseFloat(boosterInput.dataset.minPrice || 0);
            const maxPrice = parseFloat(boosterInput.dataset.maxPrice || 999999);

            let boosterPrice = 0;
            if (priceType === 'percentage' && pricePercentage > 0) {
                boosterPrice = currentPrice * (pricePercentage / 100);
            } else if (priceType === 'fixed' && priceFixed > 0) {
                boosterPrice = priceFixed;
            }

            boosterPrice = Math.max(boosterPrice, minPrice);
            if (maxPrice < 999999) {
                boosterPrice = Math.min(boosterPrice, maxPrice);
            }

            total += boosterPrice;
        }
    });

    return total;
}

async function loadCustomFields() {
    if (!selectedOffer) return;

    try {
        const response = await fetch(`/api/v1/products/dynamic-fields.php?mode=required&product_id=${productId}`);
        const result = await response.json();

        if (result.success && result.requires_fields) {
            requiredCustomFields = result.fields;
            showCustomFieldsSection();
        } else {
            hideCustomFieldsSection();
        }
    } catch (error) {
        console.error('Erreur lors du chargement des champs personnalisés:', error);
        hideCustomFieldsSection();
    }
}

function showCustomFieldsSection() {
    const section = document.getElementById('custom-fields-section');
    const container = document.getElementById('custom-fields-container');

    if (!requiredCustomFields.length) {
        hideCustomFieldsSection();
        return;
    }

    let html = '';

    requiredCustomFields.forEach(field => {
        const fieldId = `custom_field_${field.field_name}`;
        const isRequired = field.is_required;

        html += `
            <div class="custom-field-group">
                <label for="${fieldId}" class="block text-white font-semibold mb-2 text-sm">
                    ${field.field_label}
                    ${isRequired ? '<span class="text-red-400 ml-1">*</span>' : ''}
                </label>

                ${field.help_text ? `<p class="text-gray-400 text-xs mb-2">${field.help_text}</p>` : ''}

                ${renderCustomField(field)}
            </div>
        `;
    });

    container.innerHTML = html;
    section.classList.remove('hidden');

    updateStepNumbers();
    initializeCustomFields();
}

function renderCustomField(field) {
    const fieldId = `custom_field_${field.field_name}`;
    const baseClasses = 'w-full px-3 py-2 bg-dark-700 border border-dark-600 text-white placeholder-gray-400 focus:outline-none focus:border-cyan-400 focus:ring-2 focus:ring-cyan-400/20 transition-all rounded-lg text-sm';

    switch (field.field_type) {
        case 'email':
            return `<input type="email" id="${fieldId}" name="${field.field_name}" placeholder="${field.placeholder || 'Votre email'}" class="${baseClasses}" ${field.is_required ? 'required' : ''}>`;

        case 'password':
            return `<input type="password" id="${fieldId}" name="${field.field_name}" placeholder="${field.placeholder || 'Mot de passe'}" class="${baseClasses}" ${field.is_required ? 'required' : ''}>`;

        case 'url':
            return `<input type="url" id="${fieldId}" name="${field.field_name}" placeholder="${field.placeholder || 'https://...'}" class="${baseClasses}" ${field.is_required ? 'required' : ''}>`;

        case 'number':
            return `<input type="number" id="${fieldId}" name="${field.field_name}" placeholder="${field.placeholder || 'Nombre'}" class="${baseClasses}" ${field.is_required ? 'required' : ''}>`;

        case 'date':
            return `<input type="date" id="${fieldId}" name="${field.field_name}" class="${baseClasses}" ${field.is_required ? 'required' : ''}>`;

        case 'textarea':
            return `<textarea id="${fieldId}" name="${field.field_name}" placeholder="${field.placeholder || 'Votre message'}" rows="3" class="${baseClasses}" ${field.is_required ? 'required' : ''}></textarea>`;

        case 'select':
            if (field.field_options) {
                let options = '<option value="">Sélectionnez...</option>';
                field.field_options.forEach(option => {
                    options += `<option value="${option.value}">${option.label}</option>`;
                });
                return `<select id="${fieldId}" name="${field.field_name}" class="${baseClasses}" ${field.is_required ? 'required' : ''}>${options}</select>`;
            }
            break;

        case 'radio':
            if (field.field_options) {
                let html = '<div class="space-y-2">';
                field.field_options.forEach((option, index) => {
                    html += `
                        <label class="flex items-center p-2 bg-dark-700/50 rounded-lg border border-dark-600 hover:border-cyan-400/50 transition-all cursor-pointer">
                            <input type="radio" name="${field.field_name}" value="${option.value}" class="text-cyan-400 focus:ring-cyan-400 mr-2" ${field.is_required ? 'required' : ''}>
                            <span class="text-white text-sm">${option.label}</span>
                        </label>
                    `;
                });
                html += '</div>';
                return html;
            }
            break;

        case 'checkbox':
            return `
                <label class="flex items-center p-2 bg-dark-700/50 rounded-lg border border-dark-600 hover:border-cyan-400/50 transition-all cursor-pointer">
                    <input type="checkbox" id="${fieldId}" name="${field.field_name}" value="1" class="text-cyan-400 focus:ring-cyan-400 mr-2" ${field.is_required ? 'required' : ''}>
                    <span class="text-white text-sm">${field.field_label}</span>
                </label>
            `;

        default:
            return `<input type="text" id="${fieldId}" name="${field.field_name}" placeholder="${field.placeholder || 'Votre réponse'}" class="${baseClasses}" ${field.is_required ? 'required' : ''}>`;
    }

    return '';
}

function hideCustomFieldsSection() {
    const section = document.getElementById('custom-fields-section');
    section.classList.add('hidden');
    updateStepNumbers();
}

function initializeCustomFields() {
    document.querySelectorAll('#custom-fields-container input, #custom-fields-container select, #custom-fields-container textarea').forEach(field => {
        field.addEventListener('change', validateCustomFields);
        field.addEventListener('input', validateCustomFields);
    });
}

function validateCustomFields() {
    customFields = {};
    let allValid = true;

    requiredCustomFields.forEach(field => {
        const fieldElement = document.querySelector(`[name="${field.field_name}"]`);
        if (fieldElement) {
            let value = '';

            if (fieldElement.type === 'checkbox') {
                value = fieldElement.checked ? fieldElement.value : '';
            } else if (fieldElement.type === 'radio') {
                const checkedRadio = document.querySelector(`[name="${field.field_name}"]:checked`);
                value = checkedRadio ? checkedRadio.value : '';
            } else {
                value = fieldElement.value.trim();
            }

            customFields[field.field_name] = value;

            if (field.is_required && !value) {
                allValid = false;
                fieldElement.classList.add('border-red-400');
            } else {
                fieldElement.classList.remove('border-red-400');
            }
        }
    });

    updateCartButton();
    return allValid;
}

function updateStepNumbers() {
    const hasCustomFields = !document.getElementById('custom-fields-section').classList.contains('hidden');
    const boosterStepNumber = document.getElementById('booster-step-number');

    if (boosterStepNumber) {
        boosterStepNumber.textContent = hasCustomFields ? '4' : '3';
    }
}

function updateOrderSummary() {
    const summaryContainer = document.getElementById('order-summary');

    if (!selectedOffer) {
        summaryContainer.innerHTML = `
            <div class="text-gray-400 text-center py-6">
                <i class="fas fa-mouse-pointer text-2xl mb-2"></i>
                <p class="text-sm">Sélectionnez vos options</p>
            </div>
        `;
        return;
    }

    const totalPrice = calculateTotalPrice();
    const selectedOfferData = document.querySelector('input[name="selected_offer"]:checked');
    const selectedAccessName = document.querySelector('input[name="access_type"]:checked').dataset.name;

    const billingCycle = selectedOfferData.dataset.billing;
    const billingLabel = selectedOfferData.dataset.billingLabel;
    const billingSuffix = selectedOfferData.dataset.billingSuffix;
    const monthlyEquivalent = selectedOfferData.dataset.monthlyEquivalent;

    let html = `
        <div class="space-y-3">

            <div class="bg-dark-700/20 rounded-xl p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-gray-300 text-xs">Type d'accès</span>
                    <i class="fas fa-check-circle text-green-400 text-xs"></i>
                </div>
                <span class="text-white font-semibold text-sm">${selectedAccessName}</span>
            </div>

            <div class="bg-dark-700/20 rounded-xl p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-gray-300 text-xs">Offre choisie</span>
                    <i class="fas fa-check-circle text-green-400 text-xs"></i>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-white font-semibold text-sm" title="${selectedOfferData.dataset.name}">${selectedOfferData.dataset.name}</span>
                    <div class="text-right">
                        <span class="text-white font-bold text-sm">${currentPrice.toFixed(2)}€</span>
                        ${billingSuffix ? `<div class="text-xs text-purple-400">${billingSuffix}</div>` : ''}
                    </div>
                </div>
                ${billingLabel !== 'Achat unique' && monthlyEquivalent && monthlyEquivalent != currentPrice ? `
                <div class="text-xs text-gray-500 mt-2 pt-2 border-t border-dark-600/30">
                    <div class="flex justify-between">
                        <span>Type: ${billingLabel}</span>
                        <span>${parseFloat(monthlyEquivalent).toFixed(2)}€/mois équiv.</span>
                    </div>
                </div>
                ` : ''}
            </div>
    `;

    if (requiredCustomFields.length > 0) {
        const isValid = validateCustomFields();
        html += `
            <div class="bg-dark-700/20 rounded-xl p-3">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-gray-300 text-xs">Informations requises</span>
                    <i class="fas fa-${isValid ? 'check-circle text-green-400' : 'exclamation-circle text-yellow-400'} text-xs"></i>
                </div>
                <span class="text-white font-semibold text-sm">
                    ${isValid ? 'Complétées' : 'À compléter'}
                </span>
            </div>
        `;
    }

    if (selectedBoosters.length > 0) {
        html += '<div class="bg-dark-700/20 rounded-xl p-3">';
        html += '<div class="flex items-center justify-between mb-2">';
        html += '<span class="text-gray-300 text-xs">Options supplémentaires</span>';
        html += '<i class="fas fa-plus-circle text-orange-400 text-xs"></i>';
        html += '</div>';

        selectedBoosters.forEach(boosterId => {
            const boosterInput = document.querySelector(`input[name="boosters[]"][value="${boosterId}"]`);
            if (boosterInput) {
                const boosterOption = boosterInput.closest('.booster-option-wrapper');
                const boosterName = boosterOption.querySelector('h5').textContent;

                const priceType = boosterInput.dataset.priceType;
                const pricePercentage = parseFloat(boosterInput.dataset.pricePercentage || 0);
                const priceFixed = parseFloat(boosterInput.dataset.priceFixed || 0);
                const minPrice = parseFloat(boosterInput.dataset.minPrice || 0);
                const maxPrice = parseFloat(boosterInput.dataset.maxPrice || 999999);

                let boosterPrice = 0;
                if (priceType === 'percentage' && pricePercentage > 0) {
                    boosterPrice = currentPrice * (pricePercentage / 100);
                } else if (priceType === 'fixed' && priceFixed > 0) {
                    boosterPrice = priceFixed;
                }

                boosterPrice = Math.max(boosterPrice, minPrice);
                if (maxPrice < 999999) {
                    boosterPrice = Math.min(boosterPrice, maxPrice);
                }

                html += `
                    <div class="flex justify-between items-center border-t border-dark-600/30 pt-2 mt-2 text-xs">
                        <span class="text-orange-300">${boosterName}</span>
                        <span class="text-orange-400 font-bold">+${boosterPrice.toFixed(2)}€</span>
                    </div>
                `;
            }
        });
        html += '</div>';
    }

    html += `
            <div class="bg-gradient-to-r from-blue-primary/20 to-purple-500/20 border border-blue-primary/30 rounded-xl p-4 text-center">
                <div class="text-white text-sm mb-1">Prix total</div>
                <div class="text-2xl font-bold text-transparent bg-gradient-to-r from-blue-primary to-purple-500 bg-clip-text">
                    ${totalPrice.toFixed(2)}€
                </div>
                ${billingSuffix ? `<div class="text-purple-300 text-xs mt-1">${billingSuffix}</div>` : ''}
            </div>
        </div>
    `;

    summaryContainer.innerHTML = html;
}

function updateCartButton() {
    const btn = document.getElementById('add-to-cart-btn');
    const btnText = document.getElementById('cart-btn-text');

    if (!selectedOffer) {
        btn.disabled = true;
        btnText.textContent = 'Sélectionnez d\'abord';
        return;
    }

    if (requiredCustomFields.length > 0) {
        const isValid = validateCustomFields();
        if (!isValid) {
            btn.disabled = true;
            btnText.textContent = 'Complétez les champs requis';
            return;
        }
    }

    btn.disabled = false;
    const selectedOfferData = document.querySelector('input[name="selected_offer"]:checked');
    const billingLabel = selectedOfferData.dataset.billingLabel;
    const isSubscription = selectedOfferData.dataset.billing !== 'one_time';

    if (isSubscription) {
        btnText.textContent = billingLabel.includes('Engagement') ? 'S\'engager maintenant' : 'S\'abonner maintenant';
    } else {
        btnText.textContent = 'Ajouter au panier';
    }
}

async function addToCart() {
    if (!selectedOffer) {
        showNotification('Veuillez configurer votre produit', 'error');
        return;
    }

    if (requiredCustomFields.length > 0 && !validateCustomFields()) {
        showNotification('Veuillez compléter tous les champs requis', 'error');
        return;
    }

    const btn = document.getElementById('add-to-cart-btn');
    const btnText = document.getElementById('cart-btn-text');
    const originalText = btnText.textContent;

    btn.disabled = true;
    btnText.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Ajout...';

    try {
        const response = await fetch('/api/v1/cart/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                product_offer_id: selectedOffer,
                quantity: 1,
                selected_boosters: selectedBoosters,
                custom_data: customFields
            })
        });

        const result = await response.json();

        if (result.success) {
            btnText.innerHTML = '<i class="fas fa-check mr-2"></i>Ajouté !';
            showNotification('Produit ajouté au panier !', 'success');

            setTimeout(() => {
                btnText.textContent = originalText;
                btn.disabled = false;
            }, 3000);
        } else {
            throw new Error(result.error || 'Erreur lors de l\'ajout');
        }
    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
        btnText.textContent = originalText;
        btn.disabled = false;
    }
}

async function toggleWishlist(productId) {
    try {
        const response = await fetch('/api/v1/wishlist/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        });

        const result = await response.json();
        showNotification(result.added ? 'Ajouté aux favoris' : 'Retiré des favoris', 'success');
    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la gestion des favoris', 'error');
    }
}

function changeMainImage(src, btn) {
    document.getElementById('mainImage').src = src;

    document.querySelectorAll('.thumbnail-btn').forEach(thumb => {
        thumb.classList.remove('border-blue-primary');
        thumb.classList.add('border-transparent');
    });
    btn.classList.add('border-blue-primary');
    btn.classList.remove('border-transparent');
}

function showTab(tabName) {
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active', 'text-white', 'border-blue-primary');
        btn.classList.add('text-gray-400', 'border-transparent');
    });

    document.getElementById(tabName)?.classList.remove('hidden');
    event.target.classList.add('active', 'text-white', 'border-blue-primary');
    event.target.classList.remove('text-gray-400', 'border-transparent');
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const colors = {
        success: 'from-green-500 to-emerald-500',
        error: 'from-red-500 to-pink-500',
        info: 'from-blue-primary to-purple-500'
    };

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };

    notification.className = `fixed top-6 right-6 z-50 px-6 py-4 rounded-2xl text-white font-bold shadow-2xl transform translate-x-full transition-all duration-500 max-w-sm bg-gradient-to-r ${colors[type]}`;
    notification.innerHTML = `
        <div class="flex items-center space-x-3">
            <div class="w-8 h-8 bg-white/20 rounded-xl flex items-center justify-center">
                <i class="fas ${icons[type]} text-sm"></i>
            </div>
            <div>
                <div class="font-bold text-sm">${type === 'success' ? 'Succès' : type === 'error' ? 'Erreur' : 'Information'}</div>
                <div class="text-white/90 text-xs">${message}</div>
            </div>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.remove('translate-x-full'), 100);

    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => document.body.removeChild(notification), 500);
    }, 5000);
}
</script>

<style>
.line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

.access-type-option:hover,
.offer-option:hover,
.booster-option:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

@media (max-width: 1280px) {
    .xl\:col-span-3 {
        position: static !important;
    }
    .sticky {
        position: static !important;
    }
}

@media (max-width: 768px) {
    .container { 
        padding-left: 0.75rem; 
        padding-right: 0.75rem; 
    }

    .grid { 
        gap: 1rem; 
    }

    .xl\:col-span-4, 
    .xl\:col-span-5, 
    .xl\:col-span-3 {
        grid-column: span 1;
    }

    .space-y-4 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 1rem;
    }

    .p-4 {
        padding: 0.75rem;
    }

    .p-6 {
        padding: 1rem;
    }
}

input[type="radio"]:checked + .access-type-option .access-type-container {
    border-color: #3b82f6 !important;
    background-color: rgba(59, 130, 246, 0.05) !important;
}

input[type="radio"]:checked + .access-type-option .selection-indicator {
    border-color: #3b82f6 !important;
}

input[type="radio"]:checked + .access-type-option .selection-inner {
    opacity: 1 !important;
    transform: scale(1) !important;
}

input[type="radio"]:checked + .offer-option .offer-container {
    border-color: #a855f7 !important;
    background-color: rgba(168, 85, 247, 0.05) !important;
}

input[type="radio"]:checked + .offer-option .selection-indicator {
    border-color: #a855f7 !important;
}

input[type="radio"]:checked + .offer-option .selection-inner {
    opacity: 1 !important;
    transform: scale(1) !important;
}

input[type="checkbox"]:checked + .booster-option .booster-container {
    border-color: #f97316 !important;
    background-color: rgba(249, 115, 22, 0.05) !important;
}

input[type="checkbox"]:checked + .booster-option .checkbox-indicator {
    border-color: #f97316 !important;
}

input[type="checkbox"]:checked + .booster-option .checkbox-indicator i {
    opacity: 1 !important;
    transform: scale(1) !important;
}

.custom-field-group input:focus,
.custom-field-group select:focus,
.custom-field-group textarea:focus {
    border-color: #06b6d4;
    box-shadow: 0 0 0 2px rgba(6, 182, 212, 0.1);
}

.custom-field-group input.border-red-400 {
    border-color: #f87171;
    animation: shake 0.3s ease-in-out;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-3px); }
    75% { transform: translateX(3px); }
}
</style>

<?php 
endHTMLPage(); 
?>