<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once __DIR__ . '/../../../includes/CsrfProtection.php';
require_once '../../../includes/AuthMiddleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}


\CsrfProtection::requireValidToken();

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Vous devez être connecté pour utiliser la wishlist']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['product_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID du produit manquant'
        ]);
        exit;
    }
    
    $productId = intval($input['product_id']);
    $accessTypeId = isset($input['access_type_id']) ? intval($input['access_type_id']) : null;
    $offerId = isset($input['offer_id']) ? intval($input['offer_id']) : null;
    $userId = $currentUser['id'];
    
    $db = Database::getInstance();
    
        $productCheck = $db->prepare("
        SELECT id, name, is_active 
        FROM products 
        WHERE id = ?
    ");
    $productCheck->execute([$productId]);
    $product = $productCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Produit non trouvé'
        ]);
        exit;
    }
    
    if (!$product['is_active']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Ce produit n\'est plus disponible'
        ]);
        exit;
    }
    
        $existingCheck = $db->prepare("
        SELECT id 
        FROM wishlists 
        WHERE user_id = ? AND product_id = ?
        " . ($accessTypeId ? " AND access_type_id = ?" : " AND access_type_id IS NULL") .
        ($offerId ? " AND offer_id = ?" : " AND offer_id IS NULL")
    );
    
    $params = [$userId, $productId];
    if ($accessTypeId) $params[] = $accessTypeId;
    if ($offerId) $params[] = $offerId;
    
    $existingCheck->execute($params);
    $existing = $existingCheck->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo json_encode([
            'success' => true,
            'message' => 'Produit déjà dans votre wishlist',
            'data' => [
                'wishlist_id' => intval($existing['id']),
                'product_name' => $product['name'],
                'already_exists' => true
            ]
        ]);
        exit;
    }
    
        if ($accessTypeId) {
        $accessTypeCheck = $db->prepare("
            SELECT id, name 
            FROM product_access_types 
            WHERE id = ? AND product_id = ? AND is_active = 1
        ");
        $accessTypeCheck->execute([$accessTypeId, $productId]);
        $accessType = $accessTypeCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$accessType) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Type d\'accès invalide pour ce produit'
            ]);
            exit;
        }
    }
    
        if ($offerId) {
        $offerCheck = $db->prepare("
            SELECT po.id, po.name 
            FROM product_offers po
            JOIN product_access_types pat ON po.access_type_id = pat.id
            WHERE po.id = ? AND pat.product_id = ? AND po.is_active = 1
        ");
        $offerCheck->execute([$offerId, $productId]);
        $offer = $offerCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$offer) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Offre invalide pour ce produit'
            ]);
            exit;
        }
    }
    
        $countCheck = $db->prepare("
        SELECT COUNT(*) as count 
        FROM wishlists 
        WHERE user_id = ?
    ");
    $countCheck->execute([$userId]);
    $currentCount = $countCheck->fetchColumn();
    
    $maxWishlistItems = 100;     if ($currentCount >= $maxWishlistItems) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Limite de wishlist atteinte ({$maxWishlistItems} produits maximum)"
        ]);
        exit;
    }
    
        $insertSql = "
        INSERT INTO wishlists (user_id, product_id, access_type_id, offer_id, added_at) 
        VALUES (?, ?, ?, ?, NOW())
    ";
    
    $insertStmt = $db->prepare($insertSql);
    $success = $insertStmt->execute([
        $userId,
        $productId,
        $accessTypeId,
        $offerId
    ]);
    
    if (!$success) {
        throw new Exception('Erreur lors de l\'ajout à la wishlist');
    }
    
    $wishlistId = $db->lastInsertId();
    
        try {
        $db->prepare("
            UPDATE products 
            SET wishlist_count = wishlist_count + 1 
            WHERE id = ?
        ")->execute([$productId]);
    } catch (Exception $e) {
                error_log("Wishlist stats update error: " . $e->getMessage());
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Produit ajouté à votre wishlist',
        'data' => [
            'wishlist_id' => intval($wishlistId),
            'product_id' => $productId,
            'product_name' => $product['name'],
            'access_type_id' => $accessTypeId,
            'offer_id' => $offerId,
            'total_wishlist_count' => $currentCount + 1
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Add to wishlist error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de l\'ajout à la wishlist'
    ]);
}
?>