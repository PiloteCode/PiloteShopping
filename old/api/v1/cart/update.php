<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once __DIR__ . '/../../../includes/CsrfProtection.php';
require_once '../../../includes/AuthMiddleware.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}


\CsrfProtection::requireValidToken();

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['cart_item_id']) || !isset($input['quantity'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Données manquantes: cart_item_id et quantity requis'
        ]);
        exit;
    }
    
    $cartItemId = intval($input['cart_item_id']);
    $newQuantity = intval($input['quantity']);
    
    if ($newQuantity < 0 || $newQuantity > 10) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Quantité invalide (0-10)'
        ]);
        exit;
    }
    
    $db = Database::getInstance();
    
        $userId = $currentUser ? $currentUser['id'] : null;
    $sessionId = session_id();
    
    $checkSql = "
        SELECT ci.*, 
               po.price as offer_price,
               po.setup_fee,
               po.billing_cycle,
               po.billing_duration_months,
               pat.has_global_stock, 
               pat.global_stock_quantity,
               po.has_own_stock, 
               po.manage_stock, 
               po.stock_quantity,
               p.name as product_name,
               -- Stock disponible
               CASE 
                   WHEN po.has_own_stock = 1 AND po.manage_stock = 1 THEN po.stock_quantity
                   WHEN pat.has_global_stock = 1 THEN pat.global_stock_quantity
                   ELSE 999
               END as available_stock
        FROM cart_items ci
        JOIN product_offers po ON ci.product_offer_id = po.id
        JOIN product_access_types pat ON po.access_type_id = pat.id
        JOIN products p ON ci.product_id = p.id
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
    
    if ($newQuantity <= 0) {
                $deleteSql = "DELETE FROM cart_items WHERE id = ?";
        $deleteStmt = $db->prepare($deleteSql);
        $deleteStmt->execute([$cartItemId]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Item supprimé du panier',
            'data' => [
                'item_id' => $cartItemId,
                'removed' => true
            ]
        ]);
        exit;
    }
    
        if (($item['manage_stock'] || $item['has_global_stock']) && $item['available_stock'] < 999) {
        if ($item['available_stock'] < $newQuantity) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => "Stock insuffisant. Disponible: {$item['available_stock']}"
            ]);
            exit;
        }
    }
    
        $selectedBoosters = json_decode($item['selected_boosters'], true) ?: [];
    $totalBoosterPrice = 0;
    
    foreach ($selectedBoosters as $booster) {
        $totalBoosterPrice += floatval($booster['calculated_price'] ?? 0);
    }
    
        $billingCycle = $item['billing_cycle'] ?: 'one_time';
    $billingInfo = getBillingCycleInfo($billingCycle, $item['offer_price']);
    
    $unitPrice = floatval($item['offer_price']);
    $setupFee = floatval($item['setup_fee'] ?: 0);
    
        $totalPrice = ($unitPrice + $totalBoosterPrice) * $newQuantity + $setupFee;
    
        $updateSql = "
        UPDATE cart_items SET 
            quantity = ?,
            unit_price = ?,
            setup_fee = ?,
            billing_cycle = ?,
            billing_duration_months = ?,
            total_price = ?,
            updated_at = NOW()
        WHERE id = ?
    ";
    
    $updateStmt = $db->prepare($updateSql);
    $updateStmt->execute([
        $newQuantity, 
        $unitPrice,
        $setupFee,
        $billingCycle,
        $billingInfo['duration_months'],
        $totalPrice, 
        $cartItemId
    ]);
    
        $displayPrice = $unitPrice;     $monthlyEquivalent = $billingInfo['monthly_equivalent_price'];
    
    echo json_encode([
        'success' => true,
        'message' => 'Quantité mise à jour',
        'data' => [
            'item_id' => $cartItemId,
            'new_quantity' => $newQuantity,
            'unit_price' => $unitPrice,
            'display_price' => $displayPrice,
            'setup_fee' => $setupFee,
            'booster_amount' => $totalBoosterPrice,
            'total_price' => $totalPrice,
            'product_name' => $item['product_name'],
            'billing_info' => [
                'cycle' => $billingCycle,
                'label' => $billingInfo['label'],
                'suffix' => $billingInfo['suffix'],
                'is_subscription' => $billingInfo['is_subscription'],
                'is_commitment' => $billingInfo['is_commitment'],
                'duration_months' => $billingInfo['duration_months'],
                'monthly_equivalent_price' => $monthlyEquivalent,
                'period_total_price' => $billingInfo['period_total_price']
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Update cart error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur lors de la mise à jour'
    ]);
}
?>