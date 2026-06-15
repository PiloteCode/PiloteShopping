<?php
define('API_ACCESS', true);
require_once '../../api/v1/config.php';
require_once '../../includes/import.php';
require_once '../../includes/AuthMiddleware.php';
require_once '../includes/AdminAuthMiddleware.php';

$adminAuth = initAdminAuth();
$adminAuth->requireAdminAuth(['admin', 'super_admin', 'moderator']);

$currentAdmin = $adminAuth->getCurrentAdmin();
$currentUser = $adminAuth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 25);
    $search = trim($_GET['search'] ?? '');
    $category_id = !empty($_GET['category']) ? intval($_GET['category']) : null;
    $product_type_id = !empty($_GET['type']) ? intval($_GET['type']) : null;
    $status = $_GET['status'] ?? '';
    $featured = $_GET['featured'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at';
    $order = $_GET['order'] ?? 'DESC';
    $min_price = !empty($_GET['min_price']) ? floatval($_GET['min_price']) : null;
    $max_price = !empty($_GET['max_price']) ? floatval($_GET['max_price']) : null;
    
        $page = max(1, $page);
    $limit = min(100, max(10, $limit));
    $offset = ($page - 1) * $limit;
    
    $allowedSorts = ['created_at', 'updated_at', 'name', 'category_name', 'sale_count', 'view_count', 'rating_average', 'min_price'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
    $order = in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';
    
        
    $whereConditions = [];
    $params = [];
    
        if (!empty($search)) {
        $whereConditions[] = "(p.name LIKE ? OR p.sku LIKE ? OR p.short_description LIKE ? OR p.description LIKE ?)";
        $searchParam = "%" . escapeLike($search) . "%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
        if ($category_id) {
        $whereConditions[] = "p.category_id = ?";
        $params[] = $category_id;
    }
    
        if ($product_type_id) {
        $whereConditions[] = "p.product_type_id = ?";
        $params[] = $product_type_id;
    }
    
        if ($status === 'active') {
        $whereConditions[] = "p.is_active = 1";
    } elseif ($status === 'inactive') {
        $whereConditions[] = "p.is_active = 0";
    }
    
        if ($featured === 'featured') {
        $whereConditions[] = "p.is_featured = 1";
    } elseif ($featured === 'not_featured') {
        $whereConditions[] = "p.is_featured = 0";
    }
    
        if ($min_price !== null || $max_price !== null) {
        if ($min_price !== null && $max_price !== null) {
            $whereConditions[] = "min_price BETWEEN ? AND ?";
            $params[] = $min_price;
            $params[] = $max_price;
        } elseif ($min_price !== null) {
            $whereConditions[] = "min_price >= ?";
            $params[] = $min_price;
        } else {
            $whereConditions[] = "min_price <= ?";
            $params[] = $max_price;
        }
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
        
    $stmt = $db->prepare("
        SELECT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon,
               u.username as created_by_username,
               -- Prix minimum des offres
               (SELECT MIN(po.price) 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
               ) as min_price,
               -- Nombre d'offres actives
               (SELECT COUNT(DISTINCT po.id) 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
               ) as offers_count,
               -- Stock total
               (SELECT COALESCE(SUM(
                   CASE 
                       WHEN pat.has_global_stock = 1 THEN pat.global_stock_quantity
                       WHEN po.has_own_stock = 1 AND po.manage_stock = 1 THEN po.stock_quantity
                       ELSE 999
                   END
               ), 0)
                FROM product_access_types pat
                LEFT JOIN product_offers po ON pat.id = po.access_type_id AND po.is_active = 1
                WHERE pat.product_id = p.id AND pat.is_active = 1
               ) as total_stock
        FROM products p
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        LEFT JOIN product_types pt ON p.product_type_id = pt.id
        LEFT JOIN users u ON p.created_by = u.id
        $whereClause
        ORDER BY p.$sort $order
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $products = $stmt->fetchAll();
    
        
    $countStmt = $db->prepare("
        SELECT COUNT(DISTINCT p.id)
        FROM products p
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        LEFT JOIN product_types pt ON p.product_type_id = pt.id
        LEFT JOIN product_access_types pat ON p.id = pat.product_id
        LEFT JOIN product_offers po ON pat.id = po.access_type_id AND po.is_active = 1
        $whereClause
    ");
    $countStmt->execute($params);
    $totalProducts = $countStmt->fetchColumn();
    $totalPages = ceil($totalProducts / $limit);
    
        
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_products,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
            COUNT(CASE WHEN is_featured = 1 THEN 1 END) as featured_products,
            COUNT(CASE WHEN is_digital = 1 THEN 1 END) as digital_products,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_24h,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_7d,
            COALESCE(SUM(sale_count), 0) as total_sales,
            COALESCE(AVG(rating_average), 0) as avg_rating
        FROM products
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
    
        
    $categoriesStmt = $db->prepare("
        SELECT pc.*, COUNT(p.id) as product_count
        FROM product_categories pc
        LEFT JOIN products p ON pc.id = p.category_id
        WHERE pc.is_active = 1
        GROUP BY pc.id
        ORDER BY pc.name ASC
    ");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll();
    
    $typesStmt = $db->prepare("
        SELECT pt.*, COUNT(p.id) as product_count
        FROM product_types pt
        LEFT JOIN products p ON pt.id = p.product_type_id
        WHERE pt.is_active = 1
        GROUP BY pt.id
        ORDER BY pt.name ASC
    ");
    $typesStmt->execute();
    $types = $typesStmt->fetchAll();
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $productId = intval($_POST['product_id'] ?? 0);
        
        if ($productId > 0) {
            try {
                switch ($action) {
                    case 'toggle_status':
                        $stmt = $db->prepare("UPDATE products SET is_active = NOT is_active WHERE id = ?");
                        $stmt->execute([$productId]);
                        
                        $adminAuth->logAction('product_status_toggled', 'product', $productId);
                        $success = "Statut du produit modifié avec succès";
                        break;
                        
                    case 'toggle_featured':
                        $stmt = $db->prepare("UPDATE products SET is_featured = NOT is_featured WHERE id = ?");
                        $stmt->execute([$productId]);
                        
                        $adminAuth->logAction('product_featured_toggled', 'product', $productId);
                        $success = "Statut featured modifié avec succès";
                        break;
                        
                    case 'duplicate':
                                                $originalStmt = $db->prepare("SELECT * FROM products WHERE id = ?");
                        $originalStmt->execute([$productId]);
                        $original = $originalStmt->fetch();
                        
                        if ($original) {
                            $db->beginTransaction();
                            
                                                        $insertStmt = $db->prepare("
                                INSERT INTO products (category_id, product_type_id, name, slug, short_description, description, features, images, weight, sku, barcode, meta_title, meta_description, meta_keywords, is_active, is_featured, is_digital, requires_shipping, created_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, ?, ?)
                            ");
                            
                            $newName = $original['name'] . ' (Copie)';
                            $newSlug = $original['slug'] . '-copie-' . time();
                            $newSku = $original['sku'] ? $original['sku'] . '-COPY' : null;
                            
                            $insertStmt->execute([
                                $original['category_id'],
                                $original['product_type_id'],
                                $newName,
                                $newSlug,
                                $original['short_description'],
                                $original['description'],
                                $original['features'],
                                $original['images'],
                                $original['weight'],
                                $newSku,
                                $original['barcode'],
                                $original['meta_title'],
                                $original['meta_description'],
                                $original['meta_keywords'],
                                $original['is_digital'],
                                $original['requires_shipping'],
                                $currentUser['id']
                            ]);
                            
                            $newProductId = $db->lastInsertId();
                            $db->commit();
                            
                            $adminAuth->logAction('product_duplicated', 'product', $productId, null, ['new_product_id' => $newProductId]);
                            $success = "Produit dupliqué avec succès (ID: $newProductId)";
                        }
                        break;
                        
                    case 'delete':
                                                $orderCheck = $db->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
                        $orderCheck->execute([$productId]);
                        
                        if ($orderCheck->fetchColumn() > 0) {
                            $error = "Impossible de supprimer ce produit car il a des commandes associées";
                        } else {
                            $db->beginTransaction();
                            
                                                        $db->prepare("DELETE FROM product_tag_relations WHERE product_id = ?")->execute([$productId]);
                            $db->prepare("DELETE FROM product_offers WHERE access_type_id IN (SELECT id FROM product_access_types WHERE product_id = ?)")->execute([$productId]);
                            $db->prepare("DELETE FROM product_access_types WHERE product_id = ?")->execute([$productId]);
                            $db->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);
                            
                            $db->commit();
                            
                            $adminAuth->logAction('product_deleted', 'product', $productId, null, null, 'high');
                            $success = "Produit supprimé avec succès";
                        }
                        break;
                        
                    default:
                        $error = "Action non reconnue";
                }
                
                if (isset($success)) {
                    header("Location: /admin/products/?success=" . urlencode($success));
                    exit();
                }
                
            } catch (Exception $e) {
                if (isset($db) && $db->inTransaction()) {
                    $db->rollback();
                }
                $error = "Erreur lors de l'action: " . $e->getMessage();
                $adminAuth->logAction('product_action_failed', 'product', $productId, null, ['error' => $e->getMessage()], 'critical');
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Admin products error: " . $e->getMessage());
    $error = "Erreur lors du chargement des produits";
    $products = [];
    $stats = ['total_products' => 0, 'active_products' => 0, 'featured_products' => 0, 'digital_products' => 0, 'new_24h' => 0, 'new_7d' => 0, 'total_sales' => 0, 'avg_rating' => 0];
    $totalProducts = 0;
    $totalPages = 1;
    $categories = [];
    $types = [];
}

$seo = [
    'title' => 'Gestion des produits - Admin Pilote Shopping',
    'description' => 'Administration des produits',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 lg:px-6 py-8">

        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                        <i class="fas fa-boxes text-blue-400 mr-3"></i>
                        Gestion des produits
                    </h1>
                    <p class="text-gray-400">Administration complète du catalogue produits</p>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="/admin/products/categories.php" class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                        <i class="fas fa-folder mr-2"></i>Catégories
                    </a>
                    <a href="/admin/products/boosters.php" class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                        <i class="fas fa-rocket mr-2"></i>Boosters
                    </a>
                    <a href="/admin/products/add.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>Nouveau produit
                    </a>
                </div>
            </div>
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


        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-8">
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-blue-400 text-2xl font-bold"><?= number_format($stats['total_products']) ?></div>
                <div class="text-gray-400 text-sm">Total</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-green-400 text-2xl font-bold"><?= number_format($stats['active_products']) ?></div>
                <div class="text-gray-400 text-sm">Actifs</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-yellow-400 text-2xl font-bold"><?= number_format($stats['featured_products']) ?></div>
                <div class="text-gray-400 text-sm">Vedettes</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-purple-400 text-2xl font-bold"><?= number_format($stats['digital_products']) ?></div>
                <div class="text-gray-400 text-sm">Numériques</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-cyan-400 text-2xl font-bold"><?= number_format($stats['new_24h']) ?></div>
                <div class="text-gray-400 text-sm">Nouveaux 24h</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-teal-400 text-2xl font-bold"><?= number_format($stats['new_7d']) ?></div>
                <div class="text-gray-400 text-sm">Nouveaux 7j</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-emerald-400 text-2xl font-bold"><?= number_format($stats['total_sales']) ?></div>
                <div class="text-gray-400 text-sm">Ventes</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-orange-400 text-2xl font-bold"><?= number_format($stats['avg_rating'], 1) ?>/5</div>
                <div class="text-gray-400 text-sm">Note moy.</div>
            </div>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">

                    <div class="lg:col-span-2">
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Rechercher (nom, SKU, description...)" 
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <select name="category" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Toutes catégories</option>
<?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['name']) ?> (<?= $cat['product_count'] ?>)
                            </option>
<?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <select name="type" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Tous types</option>
<?php foreach ($types as $type): ?>
                            <option value="<?= $type['id'] ?>" <?= $product_type_id == $type['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($type['name']) ?> (<?= $type['product_count'] ?>)
                            </option>
<?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <select name="status" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Tous statuts</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actifs</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                        </select>
                    </div>

                    <div>
                        <select name="featured" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Tous</option>
                            <option value="featured" <?= $featured === 'featured' ? 'selected' : '' ?>>Vedettes</option>
                            <option value="not_featured" <?= $featured === 'not_featured' ? 'selected' : '' ?>>Non vedettes</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-4">

                    <div>
                        <input type="number" 
                               name="min_price" 
                               value="<?= $min_price ?? '' ?>"
                               placeholder="Prix min"
                               step="0.01"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <input type="number" 
                               name="max_price" 
                               value="<?= $max_price ?? '' ?>"
                               placeholder="Prix max"
                               step="0.01"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <select name="sort" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date création</option>
                            <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Date modification</option>
                            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Nom</option>
                            <option value="category_name" <?= $sort === 'category_name' ? 'selected' : '' ?>>Catégorie</option>
                            <option value="sale_count" <?= $sort === 'sale_count' ? 'selected' : '' ?>>Ventes</option>
                            <option value="view_count" <?= $sort === 'view_count' ? 'selected' : '' ?>>Vues</option>
                            <option value="rating_average" <?= $sort === 'rating_average' ? 'selected' : '' ?>>Note</option>
                            <option value="min_price" <?= $sort === 'min_price' ? 'selected' : '' ?>>Prix</option>
                        </select>
                    </div>

                    <div>
                        <select name="order" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>↓ Décroissant</option>
                            <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>↑ Croissant</option>
                        </select>
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i>Rechercher
                        </button>
                        <a href="/admin/products/" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-400 text-sm">Afficher</span>
                        <select name="limit" onchange="this.form.submit()" class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-1 text-white text-sm focus:outline-none focus:border-blue-500">
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <span class="text-gray-400 text-sm">résultats</span>
                    </div>

                    <div class="text-gray-400 text-sm">
<?= number_format($totalProducts) ?> produit(s) trouvé(s)
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 overflow-hidden">

<?php if (empty($products)): ?>
            <div class="p-12 text-center text-gray-400">
                <i class="fas fa-boxes text-6xl mb-4 opacity-50"></i>
                <p class="text-xl">Aucun produit trouvé</p>
                <p class="text-sm mt-2">Essayez de modifier vos critères de recherche</p>
                <a href="/admin/products/add.php" class="inline-block mt-4 bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>Créer un produit
                </a>
            </div>
<?php else: ?>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-dark-700/50">
                        <tr>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Produit</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Catégorie</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Prix & Offres</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Performance</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Statut</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($products as $product): ?>
                        <tr class="border-t border-dark-600 hover:bg-dark-700/30 transition-colors">

                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-4">
<?php 
                                    $images = json_decode($product['images'] ?? '[]', true);
                                    $image = !empty($images) ? $images[0] : '/assets/images/product-placeholder.jpg';
                                    ?>
                                    <div class="w-16 h-16 bg-dark-700 rounded-xl overflow-hidden flex-shrink-0">
                                        <img src="<?= htmlspecialchars($image) ?>" 
                                             alt="<?= htmlspecialchars($product['name']) ?>"
                                             class="w-full h-full object-cover">
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <a href="/admin/products/edit.php?id=<?= $product['id'] ?>" 
                                               class="text-white font-medium hover:text-blue-400 transition-colors truncate">
<?= htmlspecialchars($product['name']) ?>
                                            </a>

<?php if ($product['is_featured']): ?>
                                            <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 text-xs font-bold rounded-full">
                                                VEDETTE
                                            </span>
<?php endif; ?>
                                        </div>

                                        <p class="text-gray-400 text-sm truncate">
<?= htmlspecialchars($product['short_description']) ?>
                                        </p>

                                        <div class="flex items-center space-x-4 mt-2 text-xs text-gray-500">
                                            <span>ID: <?= $product['id'] ?></span>
<?php if ($product['sku']): ?>
                                            <span>SKU: <?= htmlspecialchars($product['sku']) ?></span>
<?php endif; ?>
                                            <span>Créé: <?= date('d/m/Y', strtotime($product['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 rounded-full" style="background-color: <?= htmlspecialchars($product['category_color']) ?>"></div>
                                        <span class="text-white text-sm font-medium">
<?= htmlspecialchars($product['category_name']) ?>
                                        </span>
                                    </div>

                                    <div class="flex items-center space-x-2 text-gray-400 text-xs">
                                        <i class="<?= htmlspecialchars($product['type_icon']) ?>"></i>
                                        <span><?= htmlspecialchars($product['type_name']) ?></span>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="space-y-1">
                                    <div class="text-white font-bold">
<?= $product['min_price'] ? number_format($product['min_price'], 2) . '€' : 'Sur devis' ?>
                                    </div>

                                    <div class="text-gray-400 text-sm">
<?= $product['offers_count'] ?> offre<?= $product['offers_count'] > 1 ? 's' : '' ?>
                                    </div>

                                    <div class="text-gray-500 text-xs">
                                        Stock: <?= $product['total_stock'] >= 999 ? '∞' : $product['total_stock'] ?>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="space-y-1 text-sm">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-shopping-cart text-green-400 text-xs"></i>
                                        <span class="text-white"><?= number_format($product['sale_count']) ?> ventes</span>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-eye text-blue-400 text-xs"></i>
                                        <span class="text-gray-400"><?= number_format($product['view_count']) ?> vues</span>
                                    </div>

<?php if ($product['rating_count'] > 0): ?>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-star text-yellow-400 text-xs"></i>
                                        <span class="text-gray-400"><?= number_format($product['rating_average'], 1) ?>/5 (<?= $product['rating_count'] ?>)</span>
                                    </div>
<?php endif; ?>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-2 h-2 rounded-full <?= $product['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                        <span class="text-sm <?= $product['is_active'] ? 'text-green-400' : 'text-red-400' ?>">
<?= $product['is_active'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </div>

<?php if ($product['is_digital']): ?>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-cloud text-blue-500 text-xs"></i>
                                        <span class="text-xs text-blue-400">Numérique</span>
                                    </div>
<?php endif; ?>

<?php if ($product['created_by_username']): ?>
                                    <div class="text-xs text-gray-500">
                                        Par: <?= htmlspecialchars($product['created_by_username']) ?>
                                    </div>
<?php endif; ?>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-2">
                                    <a href="/admin/products/edit.php?id=<?= $product['id'] ?>" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-sm transition-colors"
                                       title="Éditer">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="/admin/products/product.php?id=<?= $product['id'] ?>" 
                                       class="bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded-lg text-sm transition-colors"
                                       title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <a href="/products/<?= htmlspecialchars($product['slug']) ?>" 
                                       target="_blank"
                                       class="bg-cyan-600 hover:bg-cyan-700 text-white px-3 py-1 rounded-lg text-sm transition-colors"
                                       title="Voir sur le site">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>

                                    <div class="relative group">
                                        <button class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded-lg text-sm transition-colors"
                                                onclick="toggleActionMenu(<?= $product['id'] ?>)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>

                                        <div id="action-menu-<?= $product['id'] ?>" class="hidden absolute right-0 mt-2 w-56 bg-dark-700 rounded-xl border border-dark-600 shadow-xl z-50">
                                            <div class="py-2">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="action" value="toggle_status" 
                                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                                        <i class="fas fa-<?= $product['is_active'] ? 'ban' : 'check' ?> mr-3"></i>
<?= $product['is_active'] ? 'Désactiver' : 'Activer' ?>
                                                    </button>
                                                </form>

                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="action" value="toggle_featured" 
                                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                                        <i class="fas fa-star mr-3"></i>
<?= $product['is_featured'] ? 'Retirer vedette' : 'Mettre en vedette' ?>
                                                    </button>
                                                </form>

                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="action" value="duplicate" 
                                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                                        <i class="fas fa-copy mr-3"></i>
                                                        Dupliquer
                                                    </button>
                                                </form>

                                                <div class="border-t border-dark-600 my-2"></div>

                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                                                    <button type="submit" name="action" value="delete" 
                                                            class="block w-full text-left px-4 py-2 text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors"
                                                            onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')">
                                                        <i class="fas fa-trash mr-3"></i>
                                                        Supprimer
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>

<?php if ($totalPages > 1): ?>
            <div class="bg-dark-700/50 px-6 py-4 border-t border-dark-600">
                <div class="flex items-center justify-between">
                    <div class="text-gray-400 text-sm">
                        Page <?= $page ?> sur <?= $totalPages ?> (<?= number_format($totalProducts) ?> résultats)
                    </div>

                    <div class="flex items-center space-x-2">
<?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="bg-dark-600 hover:bg-dark-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
<?php endif; ?>

<?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="px-3 py-2 rounded-lg text-sm transition-colors <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-dark-600 hover:bg-dark-500 text-gray-300' ?>">
<?= $i ?>
                        </a>
<?php endfor; ?>

<?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="bg-dark-600 hover:bg-dark-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
<?php endif; ?>
                    </div>
                </div>
            </div>
<?php endif; ?>

<?php endif; ?>
        </div>
    </div>
</main>

<script>

function toggleActionMenu(productId) {
    const menu = document.getElementById('action-menu-' + productId);
    const allMenus = document.querySelectorAll('[id^="action-menu-"]');

    allMenus.forEach(m => {
        if (m !== menu) {
            m.classList.add('hidden');
        }
    });

    menu.classList.toggle('hidden');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('[onclick*="toggleActionMenu"]') && !e.target.closest('[id^="action-menu-"]')) {
        document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }
});
</script>

<style>

@media (max-width: 768px) {
    .overflow-x-auto {
        font-size: 0.875rem;
    }

    .overflow-x-auto th,
    .overflow-x-auto td {
        padding: 0.5rem;
    }
}

.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #1F2937;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 3px;
}

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #4B5563;
}
</style>

<?php endHTMLPage(); ?>