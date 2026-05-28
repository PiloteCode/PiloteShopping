<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once '../../../includes/AuthMiddleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        $source = $_GET['source'] ?? 'general';     $productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : null;
    $categoryId = isset($_GET['category_id']) ? intval($_GET['category_id']) : null;
    $limit = isset($_GET['limit']) ? min(20, max(1, intval($_GET['limit']))) : 8;
    $excludeIds = isset($_GET['exclude']) ? array_map('intval', explode(',', $_GET['exclude'])) : [];
    
    $userId = $currentUser ? $currentUser['id'] : null;
    
    $recommendations = [];
    
    switch ($source) {
        case 'cart':
            $recommendations = getCartBasedRecommendations($db, $userId, $limit, $excludeIds);
            break;
            
        case 'product':
            $recommendations = getProductBasedRecommendations($db, $productId, $limit, $excludeIds);
            break;
            
        case 'category':
            $recommendations = getCategoryBasedRecommendations($db, $categoryId, $limit, $excludeIds);
            break;
            
        case 'user':
            $recommendations = getUserBasedRecommendations($db, $userId, $limit, $excludeIds);
            break;
            
        case 'wishlist':
            $recommendations = getWishlistBasedRecommendations($db, $userId, $limit, $excludeIds);
            break;
            
        case 'general':
        default:
            $recommendations = getGeneralRecommendations($db, $userId, $limit, $excludeIds);
            break;
    }
    
    echo json_encode([
        'success' => true,
        'source' => $source,
        'count' => count($recommendations),
        'products' => $recommendations
    ]);
    
} catch (Exception $e) {
    error_log("Recommendations error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur lors du chargement des recommandations'
    ]);
}

function getCartBasedRecommendations($db, $userId, $limit, $excludeIds) {
        $cartQuery = "
        SELECT DISTINCT p.category_id, p.product_type_id, p.id as product_id
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        WHERE " . ($userId ? "ci.user_id = ?" : "ci.session_id = ?") . "
        AND p.is_active = 1
    ";
    
    $cartStmt = $db->prepare($cartQuery);
    $cartStmt->execute([$userId ?: session_id()]);
    $cartData = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cartData)) {
        return getGeneralRecommendations($db, $userId, $limit, $excludeIds);
    }
    
        $categoryIds = array_unique(array_column($cartData, 'category_id'));
    $typeIds = array_unique(array_column($cartData, 'product_type_id'));
    $cartProductIds = array_column($cartData, 'product_id');
    
        $excludeIds = array_merge($excludeIds, $cartProductIds);
    
        $excludePlaceholders = !empty($excludeIds) ? str_repeat('?,', count($excludeIds) - 1) . '?' : '';
    $categoryPlaceholders = str_repeat('?,', count($categoryIds) - 1) . '?';
    $typePlaceholders = str_repeat('?,', count($typeIds) - 1) . '?';
    
    $recommendQuery = "
        SELECT DISTINCT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon,
               -- Prix minimum des offres actives
               (SELECT MIN(po.price) 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
               ) as min_price,
               -- Offre par défaut
               (SELECT po.id 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
                ORDER BY po.is_default DESC, po.price ASC LIMIT 1
               ) as default_offer_id,
               -- Score de recommandation
               (CASE 
                   WHEN p.category_id IN ($categoryPlaceholders) THEN 3
                   ELSE 0
               END +
               CASE 
                   WHEN p.product_type_id IN ($typePlaceholders) THEN 2
                   ELSE 0
               END +
               CASE 
                   WHEN p.is_featured = 1 THEN 1
                   ELSE 0
               END +
               (p.rating_average * 0.5) +
               (LOG(p.sale_count + 1) * 0.3)
               ) as recommendation_score
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        WHERE p.is_active = 1 
        AND pc.is_active = 1
        " . (!empty($excludeIds) ? "AND p.id NOT IN ($excludePlaceholders)" : "") . "
        ORDER BY recommendation_score DESC, p.rating_average DESC, p.sale_count DESC
        LIMIT ?
    ";
    
    $params = array_merge($categoryIds, $typeIds);
    if (!empty($excludeIds)) {
        $params = array_merge($params, $excludeIds);
    }
    $params[] = $limit;
    
    $stmt = $db->prepare($recommendQuery);
    $stmt->execute($params);
    
    return processProductResults($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getProductBasedRecommendations($db, $productId, $limit, $excludeIds) {
    if (!$productId) {
        return [];
    }
    
        $productStmt = $db->prepare("
        SELECT category_id, product_type_id FROM products 
        WHERE id = ? AND is_active = 1
    ");
    $productStmt->execute([$productId]);
    $baseProduct = $productStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$baseProduct) {
        return [];
    }
    
    $excludeIds[] = $productId;     $excludePlaceholders = str_repeat('?,', count($excludeIds) - 1) . '?';
    
    $query = "
        SELECT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon,
               (SELECT MIN(po.price) 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
               ) as min_price,
               (SELECT po.id 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
                ORDER BY po.is_default DESC, po.price ASC LIMIT 1
               ) as default_offer_id,
               -- Score basé sur similarité
               (CASE 
                   WHEN p.category_id = ? THEN 5
                   ELSE 0
               END +
               CASE 
                   WHEN p.product_type_id = ? THEN 3
                   ELSE 0
               END +
               (p.rating_average * 0.8) +
               (LOG(p.sale_count + 1) * 0.4)
               ) as similarity_score
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        WHERE p.is_active = 1 
        AND pc.is_active = 1
        AND p.id NOT IN ($excludePlaceholders)
        ORDER BY similarity_score DESC, p.rating_average DESC
        LIMIT ?
    ";
    
    $params = array_merge([$baseProduct['category_id'], $baseProduct['product_type_id']], $excludeIds, [$limit]);
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return processProductResults($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getCategoryBasedRecommendations($db, $categoryId, $limit, $excludeIds) {
    if (!$categoryId) {
        return [];
    }
    
    $excludePlaceholders = !empty($excludeIds) ? str_repeat('?,', count($excludeIds) - 1) . '?' : '';
    
    $query = "
        SELECT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon,
               (SELECT MIN(po.price) 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
               ) as min_price,
               (SELECT po.id 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
                ORDER BY po.is_default DESC, po.price ASC LIMIT 1
               ) as default_offer_id
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        WHERE p.category_id = ? 
        AND p.is_active = 1 
        AND pc.is_active = 1
        " . (!empty($excludeIds) ? "AND p.id NOT IN ($excludePlaceholders)" : "") . "
        ORDER BY p.is_featured DESC, p.rating_average DESC, p.sale_count DESC
        LIMIT ?
    ";
    
    $params = [$categoryId];
    if (!empty($excludeIds)) {
        $params = array_merge($params, $excludeIds);
    }
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return processProductResults($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getUserBasedRecommendations($db, $userId, $limit, $excludeIds) {
    if (!$userId) {
        return getGeneralRecommendations($db, $userId, $limit, $excludeIds);
    }
    
        $historyStmt = $db->prepare("
        SELECT DISTINCT p.category_id, p.product_type_id, COUNT(*) as frequency
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE o.user_id = ? AND o.status = 'completed'
        GROUP BY p.category_id, p.product_type_id
        ORDER BY frequency DESC
        LIMIT 5
    ");
    $historyStmt->execute([$userId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($history)) {
        return getGeneralRecommendations($db, $userId, $limit, $excludeIds);
    }
    
        $categoryIds = array_column($history, 'category_id');
    $typeIds = array_column($history, 'product_type_id');
    
    $excludePlaceholders = !empty($excludeIds) ? str_repeat('?,', count($excludeIds) - 1) . '?' : '';
    $categoryPlaceholders = str_repeat('?,', count($categoryIds) - 1) . '?';
    $typePlaceholders = str_repeat('?,', count($typeIds) - 1) . '?';
    
    $query = "
        SELECT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon,
               (SELECT MIN(po.price) 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
               ) as min_price,
               (SELECT po.id 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
                ORDER BY po.is_default DESC, po.price ASC LIMIT 1
               ) as default_offer_id
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        WHERE p.is_active = 1 
        AND pc.is_active = 1
        AND (p.category_id IN ($categoryPlaceholders) OR p.product_type_id IN ($typePlaceholders))
        " . (!empty($excludeIds) ? "AND p.id NOT IN ($excludePlaceholders)" : "") . "
        ORDER BY p.is_featured DESC, p.rating_average DESC, p.sale_count DESC
        LIMIT ?
    ";
    
    $params = array_merge($categoryIds, $typeIds);
    if (!empty($excludeIds)) {
        $params = array_merge($params, $excludeIds);
    }
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return processProductResults($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getWishlistBasedRecommendations($db, $userId, $limit, $excludeIds) {
    if (!$userId) {
        return [];
    }
    
        $wishlistStmt = $db->prepare("
        SELECT DISTINCT p.category_id, p.product_type_id
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        WHERE w.user_id = ? AND p.is_active = 1
    ");
    $wishlistStmt->execute([$userId]);
    $wishlistData = $wishlistStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($wishlistData)) {
        return getUserBasedRecommendations($db, $userId, $limit, $excludeIds);
    }
    
        $categoryIds = array_unique(array_column($wishlistData, 'category_id'));
    $typeIds = array_unique(array_column($wishlistData, 'product_type_id'));
    
    $excludePlaceholders = !empty($excludeIds) ? str_repeat('?,', count($excludeIds) - 1) . '?' : '';
    $categoryPlaceholders = str_repeat('?,', count($categoryIds) - 1) . '?';
    $typePlaceholders = str_repeat('?,', count($typeIds) - 1) . '?';
    
    $query = "
        SELECT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon,
               (SELECT MIN(po.price) 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
               ) as min_price,
               (SELECT po.id 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
                ORDER BY po.is_default DESC, po.price ASC LIMIT 1
               ) as default_offer_id
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        WHERE p.is_active = 1 
        AND pc.is_active = 1
        AND (p.category_id IN ($categoryPlaceholders) OR p.product_type_id IN ($typePlaceholders))
        " . (!empty($excludeIds) ? "AND p.id NOT IN ($excludePlaceholders)" : "") . "
        ORDER BY p.is_featured DESC, p.rating_average DESC, p.sale_count DESC
        LIMIT ?
    ";
    
    $params = array_merge($categoryIds, $typeIds);
    if (!empty($excludeIds)) {
        $params = array_merge($params, $excludeIds);
    }
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return processProductResults($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function getGeneralRecommendations($db, $userId, $limit, $excludeIds) {
    $excludePlaceholders = !empty($excludeIds) ? str_repeat('?,', count($excludeIds) - 1) . '?' : '';
    
    $query = "
        SELECT p.*, 
               pc.name as category_name, pc.color as category_color, pc.icon as category_icon,
               pt.name as type_name, pt.icon as type_icon,
               (SELECT MIN(po.price) 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
               ) as min_price,
               (SELECT po.id 
                FROM product_offers po 
                JOIN product_access_types pat ON po.access_type_id = pat.id 
                WHERE pat.product_id = p.id AND po.is_active = 1 AND pat.is_active = 1
                ORDER BY po.is_default DESC, po.price ASC LIMIT 1
               ) as default_offer_id
        FROM products p
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        WHERE p.is_active = 1 
        AND pc.is_active = 1
        " . (!empty($excludeIds) ? "AND p.id NOT IN ($excludePlaceholders)" : "") . "
        ORDER BY p.is_featured DESC, p.rating_average DESC, p.sale_count DESC, p.view_count DESC
        LIMIT ?
    ";
    
    $params = [];
    if (!empty($excludeIds)) {
        $params = $excludeIds;
    }
    $params[] = $limit;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    
    return processProductResults($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function processProductResults($products) {
    foreach ($products as &$product) {
                if ($product['images']) {
            try {
                $images = json_decode($product['images'], true);
                $product['main_image'] = $images && is_array($images) && count($images) > 0 
                    ? $images[0] 
                    : '/assets/images/product-placeholder.jpg';
            } catch (Exception $e) {
                $product['main_image'] = '/assets/images/product-placeholder.jpg';
            }
        } else {
            $product['main_image'] = '/assets/images/product-placeholder.jpg';
        }
        
                if ($product['features']) {
            try {
                $product['features'] = json_decode($product['features'], true);
            } catch (Exception $e) {
                $product['features'] = [];
            }
        } else {
            $product['features'] = [];
        }
        
                if (!$product['min_price']) {
            $product['min_price'] = 0;
        }
        
                if (!$product['default_offer_id']) {
            $product['default_offer_id'] = null;
        }
        
                $product['base_price'] = $product['min_price'];
        $product['formatted_price'] = number_format($product['min_price'], 2);
        
                $product['url'] = '/products/' . $product['slug'];
        
                $product['available'] = $product['is_active'] == 1;
        
                unset($product['created_by']);
        unset($product['meta_title']);
        unset($product['meta_description']);
        unset($product['meta_keywords']);
        unset($product['barcode']);
        unset($product['weight']);
    }
    
    return $products;
}
?>