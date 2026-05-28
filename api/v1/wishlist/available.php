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

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentification requise']);
    exit;
}

try {
    $db = Database::getInstance();
    $userId = $currentUser['id'];
    $limit = min(10, max(1, intval($_GET['limit'] ?? 5)));     
        $sql = "
        SELECT w.id,
               w.product_id,
               w.added_at,
               p.name as product_name,
               p.slug as product_slug,
               p.short_description,
               p.images as product_images,
               p.rating_average,
               p.rating_count,
               
               -- Informations de catégorie
               pc.name as category_name,
               pc.color as category_color,
               pc.icon as category_icon,
               
               -- Informations de type
               pt.name as type_name,
               pt.icon as type_icon,
               
               -- Prix minimum et offre par défaut
               MIN(po.price) as min_price,
               (
                   SELECT po2.id 
                   FROM product_offers po2
                   JOIN product_access_types pat2 ON po2.access_type_id = pat2.id
                   WHERE pat2.product_id = p.id 
                   AND po2.is_active = 1 
                   AND po2.price = MIN(po.price)
                   LIMIT 1
               ) as min_offer_id,
               
               -- Vérification de disponibilité
               CASE 
                   WHEN p.is_active = 0 THEN 0
                   WHEN COUNT(CASE WHEN po.is_active = 1 THEN 1 END) = 0 THEN 0
                   ELSE 1
               END as is_available,
               
               -- Stock disponible (approximatif)
               COALESCE(
                   MIN(CASE 
                       WHEN pat.has_global_stock = 1 AND pat.global_stock_quantity > 0 THEN pat.global_stock_quantity
                       WHEN po.manage_stock = 1 AND po.stock_quantity > 0 THEN po.stock_quantity
                       ELSE 999
                   END), 
                   999
               ) as available_stock
               
        FROM wishlists w
        JOIN products p ON w.product_id = p.id
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        JOIN product_access_types pat ON pat.product_id = p.id
        JOIN product_offers po ON po.access_type_id = pat.id
        
        WHERE w.user_id = ?
        AND p.is_active = 1
        AND pat.is_active = 1
        
        GROUP BY w.id, w.product_id, w.added_at, p.id, pc.id, pt.id
        HAVING is_available = 1
        ORDER BY w.added_at DESC
        LIMIT ?
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId, $limit]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
        $processedItems = [];
    foreach ($items as $item) {
                $mainImage = '/assets/images/product-placeholder.jpg';
        if ($item['product_images']) {
            try {
                $images = json_decode($item['product_images'], true);
                if ($images && is_array($images) && count($images) > 0) {
                    $mainImage = $images[0];
                }
            } catch (Exception $e) {
                            }
        }
        
        $processedItems[] = [
            'id' => intval($item['id']),
            'product_id' => intval($item['product_id']),
            'product_name' => $item['product_name'],
            'product_slug' => $item['product_slug'],
            'short_description' => $item['short_description'],
            'main_image' => $mainImage,
            'product_images' => $item['product_images'],
            'rating_average' => floatval($item['rating_average'] ?? 0),
            'rating_count' => intval($item['rating_count'] ?? 0),
            
                        'category_name' => $item['category_name'],
            'category_color' => $item['category_color'],
            'category_icon' => $item['category_icon'],
            
                        'type_name' => $item['type_name'],
            'type_icon' => $item['type_icon'],
            
                        'min_price' => floatval($item['min_price'] ?? 0),
            'base_price' => floatval($item['min_price'] ?? 0),             'min_offer_id' => intval($item['min_offer_id']),
            'default_offer_id' => intval($item['min_offer_id']),             'is_available' => $item['is_available'] == 1,
            'available_stock' => intval($item['available_stock']),
            
                        'added_at' => $item['added_at'],
            'added_ago' => timeAgo($item['added_at'])
        ];
    }
    
    echo json_encode([
        'success' => true,
        'items' => $processedItems,
        'count' => count($processedItems),
        'total_wishlist_items' => getTotalWishlistItems($db, $userId)
    ]);
    
} catch (Exception $e) {
    error_log("Wishlist available error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors du chargement de la wishlist'
    ]);
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'À l\'instant';
    if ($time < 3600) return floor($time/60) . ' min';
    if ($time < 86400) return floor($time/3600) . ' h';
    if ($time < 2592000) return floor($time/86400) . ' j';
    if ($time < 31104000) return floor($time/2592000) . ' mois';
    return floor($time/31104000) . ' ans';
}

function getTotalWishlistItems($db, $userId) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM wishlists w
            JOIN products p ON w.product_id = p.id
            WHERE w.user_id = ? AND p.is_active = 1
        ");
        $stmt->execute([$userId]);
        return intval($stmt->fetchColumn());
    } catch (Exception $e) {
        return 0;
    }
}
?>