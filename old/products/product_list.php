<?php

require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

if (!defined('API_ACCESS')) {
    define('API_ACCESS', true);
}

try {
    $db = Database::getInstance();
    $auth = initAuth();
    $currentUser = $auth->getCurrentUser();
    
        $search = $_GET['search'] ?? '';
    $category_id = isset($_GET['category']) ? (int)$_GET['category'] : null;
    $type_id = isset($_GET['type']) ? (int)$_GET['type'] : null;
    $access_method = $_GET['access_method'] ?? '';
    $billing_type = $_GET['billing_type'] ?? '';
    $delivery_speed = $_GET['delivery_speed'] ?? '';
    $sort_by = $_GET['sort'] ?? 'popularity';
    $page = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
    $per_page = 24;
    $offset = ($page - 1) * $per_page;
    
        $where_conditions = ['p.is_active = 1'];
    $params = [];
    
    if ($search) {
        $where_conditions[] = '(p.name LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)';
        $search_param = '%' . escapeLike($search) . '%';
        $params = array_merge($params, [$search_param, $search_param, $search_param]);
    }
    
    if ($category_id) {
        $where_conditions[] = 'p.category_id = ?';
        $params[] = $category_id;
    }
    
    if ($type_id) {
        $where_conditions[] = 'p.product_type_id = ?';
        $params[] = $type_id;
    }
    
    if ($access_method) {
        $where_conditions[] = 'pat.access_method = ?';
        $params[] = $access_method;
    }
    
    if ($billing_type) {
        if ($billing_type === 'one_time') {
            $where_conditions[] = 'po.billing_cycle = "one_time"';
        } elseif ($billing_type === 'subscription') {
            $where_conditions[] = 'po.billing_cycle != "one_time"';
        }
    }
    
    if ($delivery_speed) {
        switch ($delivery_speed) {
            case 'instant':
                $where_conditions[] = '(pat.default_delivery_method = "automatic" OR po.delivery_method = "automatic")';
                break;
            case 'fast':
                $where_conditions[] = '(pat.default_delivery_method IN ("automatic", "semi_automatic", "manual_24h") OR po.delivery_method IN ("automatic", "semi_automatic", "manual_24h"))';
                break;
            case 'standard':
                $where_conditions[] = '(pat.default_delivery_method IN ("manual_48h", "manual_72h") OR po.delivery_method IN ("manual_48h", "manual_72h"))';
                break;
        }
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
        $order_clause = match($sort_by) {
        'price_asc' => 'ORDER BY min_price ASC',
        'price_desc' => 'ORDER BY min_price DESC',
        'name' => 'ORDER BY p.name ASC',
        'rating' => 'ORDER BY p.rating_average DESC, p.rating_count DESC',
        'newest' => 'ORDER BY p.created_at DESC',
        'popularity' => 'ORDER BY p.sale_count DESC, p.view_count DESC',
        default => 'ORDER BY p.is_featured DESC, p.sale_count DESC, p.view_count DESC'
    };
    
        $main_query = "
        SELECT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon,
               MIN(po.price) as min_price,
               MAX(po.price) as max_price,
               COUNT(DISTINCT po.id) as offers_count,
               GROUP_CONCAT(DISTINCT pat.access_method) as access_methods,
               MIN(CASE 
                   WHEN pat.default_delivery_method = 'automatic' OR po.delivery_method = 'automatic' THEN 1
                   WHEN pat.default_delivery_method = 'semi_automatic' OR po.delivery_method = 'semi_automatic' THEN 2
                   WHEN pat.default_delivery_method = 'manual_24h' OR po.delivery_method = 'manual_24h' THEN 3
                   WHEN pat.default_delivery_method = 'manual_48h' OR po.delivery_method = 'manual_48h' THEN 4
                   WHEN pat.default_delivery_method = 'manual_72h' OR po.delivery_method = 'manual_72h' THEN 5
                   ELSE 6
               END) as fastest_delivery_score,
               MIN(CASE 
                   WHEN pat.default_delivery_method = 'automatic' OR po.delivery_method = 'automatic' THEN 'Instantané'
                   WHEN pat.default_delivery_method = 'semi_automatic' OR po.delivery_method = 'semi_automatic' THEN 'Semi-auto'
                   WHEN pat.default_delivery_method = 'manual_24h' OR po.delivery_method = 'manual_24h' THEN '24h max'
                   WHEN pat.default_delivery_method = 'manual_48h' OR po.delivery_method = 'manual_48h' THEN '48h max'
                   WHEN pat.default_delivery_method = 'manual_72h' OR po.delivery_method = 'manual_72h' THEN '72h max'
                   ELSE 'Manuel'
               END) as fastest_delivery_label,
               -- Vérification du stock disponible
               CASE 
                   WHEN COUNT(CASE 
                       WHEN (po.manage_stock = 0) OR 
                            (po.has_own_stock = 1 AND po.stock_quantity > 0) OR 
                            (pat.has_global_stock = 1 AND pat.global_stock_quantity > 0)
                       THEN 1 
                   END) > 0 THEN 1 
                   ELSE 0 
               END as has_stock
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        JOIN product_access_types pat ON p.id = pat.product_id AND pat.is_active = 1
        JOIN product_offers po ON pat.id = po.access_type_id AND po.is_active = 1
        $where_clause
        GROUP BY p.id
        HAVING min_price IS NOT NULL AND has_stock = 1
        $order_clause
        LIMIT $per_page OFFSET $offset
    ";
    
    $stmt = $db->prepare($main_query);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
        $count_query = "
        SELECT COUNT(DISTINCT p.id) as total
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        JOIN product_access_types pat ON p.id = pat.product_id AND pat.is_active = 1
        JOIN product_offers po ON pat.id = po.access_type_id AND po.is_active = 1
        $where_clause
        GROUP BY p.id
        HAVING MIN(po.price) IS NOT NULL AND 
               COUNT(CASE 
                   WHEN (po.manage_stock = 0) OR 
                        (po.has_own_stock = 1 AND po.stock_quantity > 0) OR 
                        (pat.has_global_stock = 1 AND pat.global_stock_quantity > 0)
                   THEN 1 
               END) > 0
    ";
    
    $count_stmt = $db->prepare("SELECT COUNT(*) as total FROM ($count_query) as subquery");
    $count_stmt->execute($params);
    $total_products = $count_stmt->fetch()['total'];
    $total_pages = ceil($total_products / $per_page);
    
        $categories_stmt = $db->prepare("
        SELECT pc.*, COUNT(DISTINCT p.id) as products_count
        FROM product_categories pc
        JOIN products p ON pc.id = p.category_id AND p.is_active = 1
        WHERE pc.is_active = 1
        GROUP BY pc.id
        HAVING products_count > 0
        ORDER BY pc.sort_order ASC, pc.name ASC
    ");
    $categories_stmt->execute();
    $categories = $categories_stmt->fetchAll();
    
        $types_stmt = $db->prepare("
        SELECT pt.*, COUNT(DISTINCT p.id) as products_count
        FROM product_types pt
        JOIN products p ON pt.id = p.product_type_id AND p.is_active = 1
        WHERE pt.is_active = 1
        GROUP BY pt.id
        HAVING products_count > 0
        ORDER BY pt.sort_order ASC, pt.name ASC
    ");
    $types_stmt->execute();
    $types = $types_stmt->fetchAll();
    
        $stats = [
        'total_products' => $total_products,
        'current_page' => $page,
        'total_pages' => $total_pages,
        'showing_from' => $offset + 1,
        'showing_to' => min($offset + $per_page, $total_products)
    ];
    
} catch (Exception $e) {
    error_log("Product list error: " . $e->getMessage());
    $products = [];
    $categories = [];
    $types = [];
    $stats = ['total_products' => 0];
}

$page_title = 'Boutique';
if ($search) $page_title .= ' - ' . htmlspecialchars($search);
if ($category_id) {
    $current_category = array_filter($categories, fn($cat) => $cat['id'] == $category_id);
    if ($current_category) $page_title .= ' - ' . current($current_category)['name'];
}

$seo = [
    'title' => $page_title . ' - Pilote Shopping',
    'description' => 'Découvrez notre sélection de produits numériques : gaming, streaming, productivité et bien plus. Livraison rapide garantie.',
    'keywords' => 'gaming, streaming, discord, netflix, office, adobe, produits numériques',
    'og_type' => 'website'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>

<main id="main-content" class="min-h-screen bg-gradient-to-b from-dark-900 via-dark-800 to-dark-900">

    <section class="py-8 border-b border-dark-600/50">
        <div class="container mx-auto px-4">
            <div class="text-center mb-8">
                <h1 class="text-4xl lg:text-5xl font-bold text-white mb-4">
                    <span class="bg-gradient-to-r from-blue-primary to-purple-500 bg-clip-text text-transparent">
                        Boutique
                    </span>
                </h1>
                <p class="text-xl text-gray-300 max-w-2xl mx-auto">
                    Découvrez nos produits numériques premium avec livraison rapide garantie
                </p>
            </div>

            <div class="max-w-2xl mx-auto">
                <form method="GET" class="relative">
                    <input type="hidden" name="category" value="<?= htmlspecialchars($category_id ?: '') ?>">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($type_id ?: '') ?>">
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort_by) ?>">

                    <div class="relative">
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Rechercher un produit, service ou catégorie..."
                               class="w-full px-6 py-4 pl-12 bg-dark-800/50 border border-dark-600/50 rounded-2xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all backdrop-blur-sm">
                        <div class="absolute left-4 top-1/2 transform -translate-y-1/2">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <button type="submit" 
                                class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-blue-primary hover:bg-blue-secondary text-white px-6 py-2 rounded-xl transition-all font-semibold">
                            <i class="fas fa-search mr-2"></i>Rechercher
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </section>

    <div class="container mx-auto px-4 py-8">
        <div class="flex flex-col lg:flex-row gap-8">

            <aside class="lg:w-80 space-y-6">

                <div class="lg:hidden">
                    <button onclick="toggleFilters()" 
                            class="w-full bg-dark-800/50 border border-dark-600/50 rounded-2xl px-4 py-3 text-white font-semibold flex items-center justify-between backdrop-blur-sm">
                        <span><i class="fas fa-filter mr-2"></i>Filtres & Tri</span>
                        <i class="fas fa-chevron-down transition-transform" id="filters-icon"></i>
                    </button>
                </div>

                <div id="filters-panel" class="space-y-6 lg:block hidden">

                    <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-6 backdrop-blur-sm">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <i class="fas fa-chart-bar text-blue-primary mr-3"></i>
                            Résultats
                        </h3>

                        <div class="space-y-4">
                            <div class="text-sm text-gray-300">
                                <span class="font-semibold text-white"><?= number_format($stats['total_products']) ?></span> produits trouvés
<?php if ($stats['total_products'] > 0): ?>
                                <div class="text-xs text-gray-400 mt-1">
                                    Affichage de <?= $stats['showing_from'] ?> à <?= $stats['showing_to'] ?>
                                </div>
<?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-white mb-2">Trier par</label>
                                <select onchange="updateSort(this.value)" 
                                        class="w-full bg-dark-700 border border-dark-600 text-white rounded-xl px-3 py-2 focus:outline-none focus:border-blue-primary text-sm">
                                    <option value="popularity" <?= $sort_by === 'popularity' ? 'selected' : '' ?>>Popularité</option>
                                    <option value="price_asc" <?= $sort_by === 'price_asc' ? 'selected' : '' ?>>Prix croissant</option>
                                    <option value="price_desc" <?= $sort_by === 'price_desc' ? 'selected' : '' ?>>Prix décroissant</option>
                                    <option value="rating" <?= $sort_by === 'rating' ? 'selected' : '' ?>>Mieux notés</option>
                                    <option value="newest" <?= $sort_by === 'newest' ? 'selected' : '' ?>>Plus récents</option>
                                    <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Nom A-Z</option>
                                </select>
                            </div>
                        </div>
                    </div>

<?php if (!empty($categories)): ?>
                    <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-6 backdrop-blur-sm">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <i class="fas fa-layer-group text-purple-400 mr-3"></i>
                            Catégories
                        </h3>

                        <div class="space-y-3">
                            <a href="<?= buildFilterUrl(['category' => null]) ?>" 
                               class="filter-item flex items-center justify-between p-3 rounded-xl transition-all <?= !$category_id ? 'bg-blue-primary/20 border border-blue-primary/50 text-blue-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <div class="flex items-center">
                                    <i class="fas fa-th-large mr-3 text-sm"></i>
                                    <span class="font-medium text-sm">Toutes les catégories</span>
                                </div>
                                <span class="text-xs"><?= array_sum(array_column($categories, 'products_count')) ?></span>
                            </a>

<?php foreach ($categories as $category): ?>
                            <a href="<?= buildFilterUrl(['category' => $category['id']]) ?>" 
                               class="filter-item flex items-center justify-between p-3 rounded-xl transition-all <?= $category_id == $category['id'] ? 'border border-opacity-50 text-white' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>"
                               style="<?= $category_id == $category['id'] ? "background-color: {$category['color']}15; border-color: {$category['color']}; color: {$category['color']}" : '' ?>">
                                <div class="flex items-center">
                                    <i class="<?= htmlspecialchars($category['icon']) ?> mr-3 text-sm" 
                                       style="<?= $category_id == $category['id'] ? "color: {$category['color']}" : '' ?>"></i>
                                    <span class="font-medium text-sm"><?= htmlspecialchars($category['name']) ?></span>
                                </div>
                                <span class="text-xs"><?= $category['products_count'] ?></span>
                            </a>
<?php endforeach; ?>
                        </div>
                    </div>
<?php endif; ?>


<?php if (!empty($types)): ?>
                    <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-6 backdrop-blur-sm">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <i class="fas fa-tags text-green-400 mr-3"></i>
                            Types de produits
                        </h3>

                        <div class="space-y-2">
                            <a href="<?= buildFilterUrl(['type' => null]) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= !$type_id ? 'bg-green-400/20 border border-green-400/50 text-green-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <i class="fas fa-cube mr-3"></i>
                                        <span class="font-medium">Tous les types</span>
                                    </div>
                                    <span class="text-xs"><?= array_sum(array_column($types, 'products_count')) ?></span>
                                </div>
                            </a>

<?php foreach ($types as $type): ?>
                            <a href="<?= buildFilterUrl(['type' => $type['id']]) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $type_id == $type['id'] ? 'bg-green-400/20 border border-green-400/50 text-green-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <i class="<?= htmlspecialchars($type['icon']) ?> mr-3"></i>
                                        <span class="font-medium"><?= htmlspecialchars($type['name']) ?></span>
                                    </div>
                                    <span class="text-xs"><?= $type['products_count'] ?></span>
                                </div>
                            </a>
<?php endforeach; ?>
                        </div>
                    </div>
<?php endif; ?>


                    <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-6 backdrop-blur-sm">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <i class="fas fa-key text-yellow-400 mr-3"></i>
                            Méthode d'accès
                        </h3>

                        <div class="space-y-2">
                            <a href="<?= buildFilterUrl(['access_method' => null]) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= !$access_method ? 'bg-yellow-400/20 border border-yellow-400/50 text-yellow-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-th-large mr-3"></i>Toutes les méthodes
                            </a>

                            <a href="<?= buildFilterUrl(['access_method' => 'no_connection']) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $access_method === 'no_connection' ? 'bg-yellow-400/20 border border-yellow-400/50 text-yellow-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-bolt mr-3"></i>Sans inscription
                            </a>

                            <a href="<?= buildFilterUrl(['access_method' => 'account_required']) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $access_method === 'account_required' ? 'bg-yellow-400/20 border border-yellow-400/50 text-yellow-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-user-circle mr-3"></i>Compte dédié
                            </a>

                            <a href="<?= buildFilterUrl(['access_method' => 'license_key']) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $access_method === 'license_key' ? 'bg-yellow-400/20 border border-yellow-400/50 text-yellow-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-key mr-3"></i>Clé de licence
                            </a>
                        </div>
                    </div>

                    <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-6 backdrop-blur-sm">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <i class="fas fa-credit-card text-orange-400 mr-3"></i>
                            Type de facturation
                        </h3>

                        <div class="space-y-2">
                            <a href="<?= buildFilterUrl(['billing_type' => null]) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= !$billing_type ? 'bg-orange-400/20 border border-orange-400/50 text-orange-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-th-large mr-3"></i>Tous les types
                            </a>

                            <a href="<?= buildFilterUrl(['billing_type' => 'one_time']) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $billing_type === 'one_time' ? 'bg-orange-400/20 border border-orange-400/50 text-orange-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-shopping-cart mr-3"></i>Achat unique
                            </a>

                            <a href="<?= buildFilterUrl(['billing_type' => 'subscription']) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $billing_type === 'subscription' ? 'bg-orange-400/20 border border-orange-400/50 text-orange-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-sync-alt mr-3"></i>Abonnement
                            </a>
                        </div>
                    </div>

                    <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-6 backdrop-blur-sm">
                        <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                            <i class="fas fa-shipping-fast text-cyan-400 mr-3"></i>
                            Rapidité de livraison
                        </h3>

                        <div class="space-y-2">
                            <a href="<?= buildFilterUrl(['delivery_speed' => null]) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= !$delivery_speed ? 'bg-cyan-400/20 border border-cyan-400/50 text-cyan-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-th-large mr-3"></i>Toutes les vitesses
                            </a>

                            <a href="<?= buildFilterUrl(['delivery_speed' => 'instant']) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $delivery_speed === 'instant' ? 'bg-cyan-400/20 border border-cyan-400/50 text-cyan-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-bolt mr-3"></i>Instantané
                            </a>

                            <a href="<?= buildFilterUrl(['delivery_speed' => 'fast']) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $delivery_speed === 'fast' ? 'bg-cyan-400/20 border border-cyan-400/50 text-cyan-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-tachometer-alt mr-3"></i>Rapide (24h max)
                            </a>

                            <a href="<?= buildFilterUrl(['delivery_speed' => 'standard']) ?>" 
                               class="filter-item block p-3 rounded-xl transition-all text-sm <?= $delivery_speed === 'standard' ? 'bg-cyan-400/20 border border-cyan-400/50 text-cyan-400' : 'hover:bg-dark-700/50 text-gray-300 hover:text-white' ?>">
                                <i class="fas fa-clock mr-3"></i>Standard (48-72h)
                            </a>
                        </div>
                    </div>

<?php if ($search || $category_id || $type_id || $access_method || $billing_type || $delivery_speed): ?>
                    <div class="bg-red-500/10 border border-red-500/30 rounded-2xl p-4 backdrop-blur-sm">
                        <a href="/products" 
                           class="flex items-center justify-center text-red-400 hover:text-red-300 font-semibold transition-colors">
                            <i class="fas fa-times-circle mr-2"></i>
                            Réinitialiser tous les filtres
                        </a>
                    </div>
<?php endif; ?>
                </div>
            </aside>

            <main class="flex-1">

<?php if ($search || $category_id || $type_id || $access_method || $billing_type || $delivery_speed): ?>
                <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-4 mb-6 backdrop-blur-sm">
                    <h4 class="text-sm font-semibold text-white mb-3 flex items-center">
                        <i class="fas fa-filter mr-2"></i>Filtres actifs
                    </h4>

                    <div class="flex flex-wrap gap-2">
<?php if ($search): ?>
                        <span class="bg-blue-primary/20 border border-blue-primary/50 text-blue-400 px-3 py-1 rounded-xl text-sm flex items-center">
                            <i class="fas fa-search mr-2"></i>
                            "<?= htmlspecialchars($search) ?>"
                            <a href="<?= buildFilterUrl(['search' => null]) ?>" class="ml-2 hover:text-blue-300">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
<?php endif; ?>

<?php if ($category_id): ?>
<?php $current_cat = array_filter($categories, fn($cat) => $cat['id'] == $category_id); ?>
<?php if ($current_cat): $current_cat = current($current_cat); ?>
                        <span class="px-3 py-1 rounded-xl text-sm flex items-center border border-opacity-50" 
                              style="background-color: <?= $current_cat['color'] ?>15; border-color: <?= $current_cat['color'] ?>; color: <?= $current_cat['color'] ?>">
                            <i class="<?= $current_cat['icon'] ?> mr-2"></i>
<?= htmlspecialchars($current_cat['name']) ?>
                            <a href="<?= buildFilterUrl(['category' => null]) ?>" class="ml-2 hover:opacity-70">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
<?php endif; ?>
<?php endif; ?>

<?php if ($type_id): ?>
<?php $current_type = array_filter($types, fn($type) => $type['id'] == $type_id); ?>
<?php if ($current_type): $current_type = current($current_type); ?>
                        <span class="bg-green-400/20 border border-green-400/50 text-green-400 px-3 py-1 rounded-xl text-sm flex items-center">
                            <i class="<?= $current_type['icon'] ?> mr-2"></i>
<?= htmlspecialchars($current_type['name']) ?>
                            <a href="<?= buildFilterUrl(['type' => null]) ?>" class="ml-2 hover:text-green-300">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
<?php endif; ?>
<?php endif; ?>

<?php if ($access_method): ?>
                        <span class="bg-yellow-400/20 border border-yellow-400/50 text-yellow-400 px-3 py-1 rounded-xl text-sm flex items-center">
                            <i class="fas fa-key mr-2"></i>
<?= ucfirst(str_replace('_', ' ', $access_method)) ?>
                            <a href="<?= buildFilterUrl(['access_method' => null]) ?>" class="ml-2 hover:text-yellow-300">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
<?php endif; ?>

<?php if ($billing_type): ?>
                        <span class="bg-orange-400/20 border border-orange-400/50 text-orange-400 px-3 py-1 rounded-xl text-sm flex items-center">
                            <i class="fas fa-credit-card mr-2"></i>
<?= $billing_type === 'one_time' ? 'Achat unique' : 'Abonnement' ?>
                            <a href="<?= buildFilterUrl(['billing_type' => null]) ?>" class="ml-2 hover:text-orange-300">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
<?php endif; ?>

<?php if ($delivery_speed): ?>
                        <span class="bg-cyan-400/20 border border-cyan-400/50 text-cyan-400 px-3 py-1 rounded-xl text-sm flex items-center">
                            <i class="fas fa-shipping-fast mr-2"></i>
<?= ucfirst($delivery_speed) ?>
                            <a href="<?= buildFilterUrl(['delivery_speed' => null]) ?>" class="ml-2 hover:text-cyan-300">
                                <i class="fas fa-times"></i>
                            </a>
                        </span>
<?php endif; ?>
                    </div>
                </div>
<?php endif; ?>


<?php if (empty($products)): ?>


                <div class="text-center py-16">
                    <div class="w-32 h-32 mx-auto mb-6 bg-dark-800/50 rounded-full flex items-center justify-center">
                        <i class="fas fa-search text-4xl text-gray-600"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-white mb-4">Aucun produit trouvé</h3>
                    <p class="text-gray-400 mb-6 max-w-md mx-auto">
                        Essayez de modifier vos critères de recherche ou de parcourir nos catégories.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="/products" 
                           class="bg-blue-primary hover:bg-blue-secondary text-white font-semibold px-6 py-3 rounded-xl transition-all">
                            <i class="fas fa-th-large mr-2"></i>Voir tous les produits
                        </a>
                        <a href="/products?sort=newest" 
                           class="bg-dark-700/50 hover:bg-dark-600/50 text-white font-semibold px-6 py-3 rounded-xl transition-all border border-dark-600/50">
                            <i class="fas fa-star mr-2"></i>Nouveautés
                        </a>
                    </div>
                </div>

<?php else: ?>


                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
<?php foreach ($products as $product): ?>
<?php
                    $images = json_decode($product['images'] ?? '[]', true);
                    $main_image = !empty($images) ? $images[0] : null;
                    ?>

                    <article class="product-card group bg-dark-800/30 rounded-2xl border border-dark-600/50 overflow-hidden hover:border-blue-primary/50 transition-all duration-300 hover:transform hover:scale-105 backdrop-blur-sm">
                        <a href="/products/<?= htmlspecialchars($product['slug']) ?>" class="block">

                            <div class="relative aspect-square bg-gradient-to-br from-dark-700 to-dark-800 overflow-hidden">
<?php if ($main_image): ?>
                                <img src="<?= htmlspecialchars($main_image) ?>" 
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     class="w-full h-full object-contain group-hover:scale-110 transition-transform duration-500"
                                     loading="lazy">
<?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <i class="<?= htmlspecialchars($product['type_icon']) ?> text-4xl text-gray-400"></i>
                                </div>
<?php endif; ?>


                                <div class="absolute top-3 left-3 flex flex-col space-y-2">
<?php if ($product['is_featured']): ?>
                                    <div class="bg-yellow-500 text-white px-2 py-1 rounded-lg shadow-lg text-xs font-bold">
                                        <i class="fas fa-star mr-1"></i>TOP
                                    </div>
<?php endif; ?>

                                    <div class="backdrop-blur-md rounded-lg px-2 py-1 border shadow-lg text-xs font-semibold" 
                                         style="background-color: <?= htmlspecialchars($product['category_color']) ?>15; border-color: <?= htmlspecialchars($product['category_color']) ?>; color: <?= htmlspecialchars($product['category_color']) ?>">
                                        <i class="<?= htmlspecialchars($product['category_icon']) ?> mr-1"></i>
<?= htmlspecialchars($product['category_name']) ?>
                                    </div>
                                </div>

                                <div class="absolute top-3 right-3">
<?php 
                                    $delivery_colors = [
                                        'Instantané' => 'bg-green-500',
                                        'Semi-auto' => 'bg-blue-500',
                                        '24h max' => 'bg-yellow-500',
                                        '48h max' => 'bg-orange-500',
                                        '72h max' => 'bg-red-500'
                                    ];
                                    $delivery_color = $delivery_colors[$product['fastest_delivery_label']] ?? 'bg-gray-500';
                                    ?>
                                    <div class="<?= $delivery_color ?> text-white px-2 py-1 rounded-lg shadow-lg text-xs font-bold">
                                        <i class="fas fa-shipping-fast mr-1"></i><?= htmlspecialchars($product['fastest_delivery_label']) ?>
                                    </div>
                                </div>

                                <div class="absolute inset-0 bg-black/60 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                    <div class="bg-white/10 backdrop-blur-md border border-white/20 rounded-xl px-6 py-3 text-white font-semibold transform translate-y-4 group-hover:translate-y-0 transition-transform duration-300">
                                        <i class="fas fa-eye mr-2"></i>Voir le produit
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 space-y-3">

                                <div>
                                    <h3 class="font-bold text-white text-sm line-clamp-2 group-hover:text-blue-primary transition-colors mb-1">
<?= htmlspecialchars($product['name']) ?>
                                    </h3>
                                    <p class="text-gray-400 text-xs line-clamp-2">
<?= htmlspecialchars($product['short_description']) ?>
                                    </p>
                                </div>

                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center space-x-3">
<?php if ($product['rating_count'] > 0): ?>
                                        <div class="flex items-center text-yellow-400">
                                            <i class="fas fa-star mr-1"></i>
                                            <span class="text-white font-semibold"><?= number_format($product['rating_average'], 1) ?></span>
                                            <span class="text-gray-400 ml-1">(<?= $product['rating_count'] ?>)</span>
                                        </div>
<?php endif; ?>

                                        <div class="flex items-center text-gray-400">
                                            <i class="fas fa-eye mr-1"></i>
                                            <span><?= formatNumber($product['view_count']) ?></span>
                                        </div>
                                    </div>

                                    <div class="text-green-400 font-semibold">
                                        <i class="fas fa-check-circle mr-1"></i>Disponible
                                    </div>
                                </div>

                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center text-purple-400">
                                        <i class="<?= htmlspecialchars($product['type_icon']) ?> mr-1"></i>
                                        <span><?= htmlspecialchars($product['type_name']) ?></span>
                                    </div>

                                    <div class="text-gray-400">
<?= $product['offers_count'] ?> offre<?= $product['offers_count'] > 1 ? 's' : '' ?>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-dark-600/30">
                                    <div>
                                        <div class="text-lg font-bold text-white">
<?= number_format($product['min_price'], 2) ?>€
                                        </div>
<?php if ($product['min_price'] != $product['max_price']): ?>
                                        <div class="text-xs text-gray-400">
                                            à partir de
                                        </div>
<?php endif; ?>
                                    </div>

                                    <div class="bg-blue-primary hover:bg-blue-secondary text-white font-semibold px-3 py-2 rounded-lg transition-all transform hover:scale-105 text-xs">
                                        <i class="fas fa-shopping-cart mr-1"></i>
                                        Voir
                                    </div>
                                </div>
                            </div>
                        </a>

                        <div class="px-4 pb-4">
                            <button onclick="toggleWishlist(<?= $product['id'] ?>, event)" 
                                    class="w-full bg-dark-700/50 hover:bg-red-500/20 text-gray-400 hover:text-red-400 font-semibold py-2 rounded-lg transition-all border border-dark-600/50 hover:border-red-400/50 text-xs">
                                <i class="far fa-heart mr-1"></i>Ajouter aux favoris
                            </button>
                        </div>
                    </article>
<?php endforeach; ?>
                </div>

<?php if ($total_pages > 1): ?>
                <nav class="mt-12 flex justify-center">
                    <div class="flex items-center space-x-2">

<?php if ($page > 1): ?>
                        <a href="<?= buildFilterUrl(['page' => $page - 1]) ?>" 
                           class="bg-dark-800/50 hover:bg-blue-primary text-white font-semibold px-4 py-2 rounded-xl transition-all border border-dark-600/50 hover:border-blue-primary">
                            <i class="fas fa-chevron-left mr-1"></i>Précédent
                        </a>
<?php endif; ?>


<?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                        <a href="<?= buildFilterUrl(['page' => 1]) ?>" 
                           class="bg-dark-800/50 hover:bg-dark-700/50 text-white font-semibold px-3 py-2 rounded-xl transition-all border border-dark-600/50">1</a>
<?php if ($start_page > 2): ?>
                        <span class="text-gray-400 px-2">...</span>
<?php endif; ?>
<?php endif; ?>

<?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="<?= buildFilterUrl(['page' => $i]) ?>" 
                           class="font-semibold px-3 py-2 rounded-xl transition-all border <?= $i === $page ? 'bg-blue-primary text-white border-blue-primary' : 'bg-dark-800/50 hover:bg-dark-700/50 text-white border-dark-600/50' ?>">
<?= $i ?>
                        </a>
<?php endfor; ?>

<?php if ($end_page < $total_pages): ?>
<?php if ($end_page < $total_pages - 1): ?>
                        <span class="text-gray-400 px-2">...</span>
<?php endif; ?>
                        <a href="<?= buildFilterUrl(['page' => $total_pages]) ?>" 
                           class="bg-dark-800/50 hover:bg-dark-700/50 text-white font-semibold px-3 py-2 rounded-xl transition-all border border-dark-600/50"><?= $total_pages ?></a>
<?php endif; ?>


<?php if ($page < $total_pages): ?>
                        <a href="<?= buildFilterUrl(['page' => $page + 1]) ?>" 
                           class="bg-dark-800/50 hover:bg-blue-primary text-white font-semibold px-4 py-2 rounded-xl transition-all border border-dark-600/50 hover:border-blue-primary">
                            Suivant<i class="fas fa-chevron-right ml-1"></i>
                        </a>
<?php endif; ?>
                    </div>
                </nav>

                <div class="mt-6 text-center text-sm text-gray-400">
                    Affichage de <?= $stats['showing_from'] ?> à <?= $stats['showing_to'] ?> sur <?= number_format($stats['total_products']) ?> produits
                </div>
<?php endif; ?>

<?php endif; ?>
            </main>
        </div>
    </div>

</main>

<?php require_once '../includes/footer.php'; ?>

<script>
function toggleFilters() {
    const panel = document.getElementById('filters-panel');
    const icon = document.getElementById('filters-icon');

    panel.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}

function updateSort(sortValue) {
    const url = new URL(window.location);
    url.searchParams.set('sort', sortValue);
    url.searchParams.delete('page'); // Reset à la page 1
    window.location.href = url.toString();
}

async function toggleWishlist(productId, event) {
    event.preventDefault();
    event.stopPropagation();

    try {
        const response = await fetch('/api/v1/wishlist/toggle', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ product_id: productId })
        });

        const result = await response.json();

        if (result.success) {
            const button = event.target.closest('button');
            const icon = button.querySelector('i');

            if (result.added) {
                icon.classList.remove('far');
                icon.classList.add('fas', 'text-red-400');
                button.classList.add('bg-red-500/20', 'text-red-400', 'border-red-400/50');
                showNotification('Ajouté aux favoris', 'success');
            } else {
                icon.classList.remove('fas', 'text-red-400');
                icon.classList.add('far');
                button.classList.remove('bg-red-500/20', 'text-red-400', 'border-red-400/50');
                showNotification('Retiré des favoris', 'info');
            }
        } else {
            throw new Error(result.error || 'Erreur lors de la mise à jour');
        }
    } catch (error) {
        console.error('Erreur wishlist:', error);
        showNotification('Erreur lors de la gestion des favoris', 'error');
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-primary'
    };

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        info: 'fa-info-circle'
    };

    notification.className = `fixed top-6 right-6 z-50 px-6 py-4 rounded-2xl text-white font-bold shadow-2xl transform translate-x-full transition-all duration-500 max-w-sm ${colors[type]}`;
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

let isLoading = false;
let hasMorePages = <?= $page < $total_pages ? 'true' : 'false' ?>;

function handleScroll() {
    if (isLoading || !hasMorePages) return;

    const scrollPosition = window.innerHeight + window.scrollY;
    const threshold = document.documentElement.offsetHeight - 1000;

    if (scrollPosition >= threshold) {
        loadMoreProducts();
    }
}

async function loadMoreProducts() {
    if (isLoading || !hasMorePages) return;

    isLoading = true;
    const nextPage = <?= $page ?> + Math.floor(document.querySelectorAll('.product-card').length / <?= $per_page ?>);

    try {
        const url = new URL(window.location);
        url.searchParams.set('page', nextPage);
        url.searchParams.set('ajax', '1');

        const response = await fetch(url.toString());
        const html = await response.text();

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newProducts = doc.querySelectorAll('.product-card');

        if (newProducts.length > 0) {
            const grid = document.querySelector('.grid');
            newProducts.forEach(product => {
                grid.appendChild(product);
            });
        } else {
            hasMorePages = false;
        }
    } catch (error) {
        console.error('Erreur lors du chargement des produits:', error);
    } finally {
        isLoading = false;
    }
}

let searchTimeout;
const searchInput = document.querySelector('input[name="search"]');

if (searchInput) {
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (this.value.length >= 3 || this.value.length === 0) {
                this.form.submit();
            }
        }, 500);
    });
}
</script>

<style>

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-3 {
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.product-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.product-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
}

.filter-item {
    transition: all 0.2s ease-in-out;
}

.filter-item:hover {
    transform: translateX(4px);
}

@media (max-width: 1024px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .grid {
        gap: 1rem;
    }
}

@media (max-width: 640px) {
    .container {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }

    .grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 0.75rem;
    }

    .product-card:hover {
        transform: translateY(-4px) scale(1.01);
    }

    .space-y-6 > :not([hidden]) ~ :not([hidden]) {
        margin-top: 1rem;
    }

    .p-6 {
        padding: 1rem;
    }

    .p-4 {
        padding: 0.75rem;
    }
}

.product-card img {
    will-change: transform;
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #3b82f6;
    border-radius: 50%;
    border-top: 2px solid transparent;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.product-card {
    animation: fadeInUp 0.6s ease-out forwards;
}

.product-card:nth-child(even) {
    animation-delay: 0.1s;
}

.product-card:nth-child(3n) {
    animation-delay: 0.2s;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.filter-item:focus,
button:focus,
a:focus {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
}

@media (prefers-color-scheme: dark) {
    .backdrop-blur-sm {
        backdrop-filter: blur(4px) saturate(1.2);
    }
}
</style>

<?php
function buildFilterUrl($changes = []) {
    global $search, $category_id, $type_id, $access_method, $billing_type, $delivery_speed, $sort_by, $page;
    
    $params = [];
    
    $current = [
        'search' => $search,
        'category' => $category_id,
        'type' => $type_id,
        'access_method' => $access_method,
        'billing_type' => $billing_type,
        'delivery_speed' => $delivery_speed,
        'sort' => $sort_by,
        'page' => $page
    ];
    
        foreach ($changes as $key => $value) {
        $current[$key] = $value;
    }
    
        foreach ($current as $key => $value) {
        if ($value !== null && $value !== '' && $value !== 1) {
            $params[] = $key . '=' . urlencode($value);
        }
    }
    
    return '/products' . (!empty($params) ? '?' . implode('&', $params) : '');
}

function formatNumber($number) {
    if ($number >= 1000000) {
        return round($number / 1000000, 1) . 'M';
    } elseif ($number >= 1000) {
        return round($number / 1000, 1) . 'k';
    }
    return $number;
}

endHTMLPage();
?>