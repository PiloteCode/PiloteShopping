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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['cart_item_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'ID de l\'item manquant'
        ]);
        exit;
    }
    
    $cartItemId = intval($input['cart_item_id']);
    
    $db = Database::getInstance();
    
        $userId = $currentUser ? $currentUser['id'] : null;
    $sessionId = session_id();
    
    $checkSql = "
        SELECT ci.id, ci.quantity, p.name as product_name, po.name as offer_name
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN product_offers po ON ci.product_offer_id = po.id
        WHERE ci.id = ? AND " . ($userId ? "ci.user_id = ?" : "ci.session_id = ?");
    
    $checkParams = [$cartItemId];
    $checkParams[] = $userId ?: $sessionId;
    
    $checkStmt = $db->prepare($checkSql);
    $checkStmt->execute($checkParams);
    $item = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'error' => 'Item non trouvé'
        ]);
        exit;
    }
    
        $deleteSql = "DELETE FROM cart_items WHERE id = ?";
    $deleteStmt = $db->prepare($deleteSql);
    $deleteStmt->execute([$cartItemId]);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Produit supprimé du panier',
        'data' => [
            'item_id' => $cartItemId,
            'product_name' => $item['product_name'],
            'offer_name' => $item['offer_name'],
            'quantity_removed' => $item['quantity']
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Remove cart item error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur lors de la suppression'
    ]);
}
?>