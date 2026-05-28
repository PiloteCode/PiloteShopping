<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once __DIR__ . '/../../../includes/CsrfProtection.php';
require_once '../../../includes/AuthMiddleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}


\CsrfProtection::requireValidToken();

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Vous devez être connecté pour gérer votre wishlist']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
            $itemId = isset($input['item_id']) ? intval($input['item_id']) : null;
    $productId = isset($input['product_id']) ? intval($input['product_id']) : null;
    
    if (!$itemId && !$productId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'ID de l\'item ou du produit requis'
        ]);
        exit;
    }
    
    $userId = $currentUser['id'];
    $db = Database::getInstance();
    
    if ($itemId) {
                
                $checkSql = "
            SELECT w.id, p.name as product_name, p.id as product_id
            FROM wishlists w
            JOIN products p ON w.product_id = p.id
            WHERE w.id = ? AND w.user_id = ?
        ";
        
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$itemId, $userId]);
        $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Item non trouvé dans votre wishlist'
            ]);
            exit;
        }
        
                $deleteSql = "DELETE FROM wishlists WHERE id = ? AND user_id = ?";
        $deleteStmt = $db->prepare($deleteSql);
        $success = $deleteStmt->execute([$itemId, $userId]);
        
        if (!$success) {
            throw new Exception('Erreur lors de la suppression de l\'item');
        }
        
        $deletedCount = $deleteStmt->rowCount();
        $productName = $item['product_name'];
        $affectedProductId = $item['product_id'];
        
    } else {
                
                $checkSql = "
            SELECT COUNT(*) as count, p.name as product_name
            FROM wishlists w
            JOIN products p ON w.product_id = p.id
            WHERE w.product_id = ? AND w.user_id = ?
            GROUP BY p.name
        ";
        
        $checkStmt = $db->prepare($checkSql);
        $checkStmt->execute([$productId, $userId]);
        $result = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result || $result['count'] == 0) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Produit non trouvé dans votre wishlist'
            ]);
            exit;
        }
        
                $deleteSql = "DELETE FROM wishlists WHERE product_id = ? AND user_id = ?";
        $deleteStmt = $db->prepare($deleteSql);
        $success = $deleteStmt->execute([$productId, $userId]);
        
        if (!$success) {
            throw new Exception('Erreur lors de la suppression du produit');
        }
        
        $deletedCount = $deleteStmt->rowCount();
        $productName = $result['product_name'];
        $affectedProductId = $productId;
    }
    
        try {
        $db->prepare("
            UPDATE products 
            SET wishlist_count = GREATEST(0, wishlist_count - ?) 
            WHERE id = ?
        ")->execute([$deletedCount, $affectedProductId]);
    } catch (Exception $e) {
                error_log("Wishlist stats update error: " . $e->getMessage());
    }
    
        $countStmt = $db->prepare("SELECT COUNT(*) FROM wishlists WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $remainingCount = $countStmt->fetchColumn();
    
    echo json_encode([
        'success' => true,
        'message' => $deletedCount > 1 ? 
            "Produit et ses variantes supprimés de votre wishlist" : 
            "Produit supprimé de votre wishlist",
        'data' => [
            'deleted_item_id' => $itemId,
            'deleted_product_id' => $affectedProductId,
            'product_name' => $productName,
            'deleted_count' => $deletedCount,
            'remaining_wishlist_count' => intval($remainingCount)
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Remove from wishlist error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la suppression de la wishlist'
    ]);
}
?>