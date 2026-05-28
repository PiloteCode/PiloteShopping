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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
        if (!isset($input['product_id']) || !isset($input['product_offer_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Données manquantes: product_id et product_offer_id requis'
        ]);
        exit;
    }
    
    $productId = intval($input['product_id']);
    $productOfferId = intval($input['product_offer_id']);
    $quantity = max(1, intval($input['quantity'] ?? 1));
    $selectedBoosters = $input['selected_boosters'] ?? [];
    $customData = $input['custom_data'] ?? [];
    
    $db = Database::getInstance();
    
        $offerCheck = $db->prepare("
        SELECT po.*, 
               po.billing_cycle as actual_billing_cycle,
               po.billing_duration_months as actual_duration_months,
               po.price as actual_price,
               po.setup_fee as actual_setup_fee,
               pat.product_id, 
               pat.name as access_type_name, 
               pat.has_global_stock, 
               pat.global_stock_quantity,
               pat.is_active as access_type_active,
               p.name as product_name,
               p.category_id,
               p.is_active as product_active,
               -- Stock disponible
               CASE 
                   WHEN po.has_own_stock = 1 AND po.manage_stock = 1 THEN po.stock_quantity
                   WHEN pat.has_global_stock = 1 THEN pat.global_stock_quantity
                   ELSE 999
               END as available_stock,
               -- Validation de l'offre
               CASE 
                   WHEN p.is_active = 0 THEN 'product_inactive'
                   WHEN pat.is_active = 0 THEN 'access_type_inactive'
                   WHEN po.is_active = 0 THEN 'offer_inactive'
                   WHEN po.has_own_stock = 1 AND po.manage_stock = 1 AND po.stock_quantity <= 0 THEN 'out_of_stock'
                   WHEN pat.has_global_stock = 1 AND pat.global_stock_quantity <= 0 THEN 'out_of_stock'
                   ELSE 'valid'
               END as validation_status
        FROM product_offers po
        JOIN product_access_types pat ON po.access_type_id = pat.id
        JOIN products p ON pat.product_id = p.id
        WHERE po.id = ? 
        AND pat.product_id = ? 
    ");
    $offerCheck->execute([$productOfferId, $productId]);
    $offer = $offerCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$offer) {
        http_response_code(404);
        echo json_encode([
            'success' => false, 
            'error' => 'Produit ou offre non trouvé'
        ]);
        exit;
    }
    
        if ($offer['validation_status'] !== 'valid') {
        $errorMessages = [
            'product_inactive' => 'Ce produit n\'est plus disponible',
            'access_type_inactive' => 'Ce type d\'accès n\'est plus disponible',
            'offer_inactive' => 'Cette offre n\'est plus disponible',
            'out_of_stock' => 'Plus de stock disponible'
        ];
        
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => $errorMessages[$offer['validation_status']] ?? 'Offre non valide'
        ]);
        exit;
    }
    
        $isSubscription = $offer['actual_billing_cycle'] !== 'one_time';
    if ($isSubscription && $quantity > 1) {
        http_response_code(400);
        echo json_encode([
            'success' => false, 
            'error' => 'Les abonnements ne peuvent pas avoir une quantité supérieure à 1. Quantité ajustée à 1.',
            'adjusted_quantity' => 1
        ]);
                $quantity = 1;
    }
    
        if (($offer['manage_stock'] || $offer['has_global_stock']) && $offer['available_stock'] < 999) {
        if ($offer['available_stock'] < $quantity) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => "Stock insuffisant. Disponible: {$offer['available_stock']}"
            ]);
            exit;
        }
    }
    
        $totalBoosterPrice = 0;
    $boostersData = [];
    
    if (!empty($selectedBoosters)) {
        foreach ($selectedBoosters as $boosterId) {
            $booster = getBoosterInfo($db, intval($boosterId), $productId, $offer['category_id']);
            if ($booster) {
                $basePrice = floatval($offer['actual_price']);
                $boosterPrice = calculateBoosterPrice($booster, $basePrice, $offer['actual_billing_cycle']);
                if ($boosterPrice > 0) {
                    $totalBoosterPrice += $boosterPrice;
                    
                    $boostersData[] = [
                        'id' => intval($boosterId),
                        'name' => $booster['name'],
                        'calculated_price' => round($boosterPrice, 2)
                    ];
                }
            }
        }
    }
    
        $actualBillingCycle = $offer['actual_billing_cycle'];
    $actualDurationMonths = intval($offer['actual_duration_months'] ?? 0);
    $actualPrice = floatval($offer['actual_price']);
    $actualSetupFee = floatval($offer['actual_setup_fee'] ?? 0);
    
        $billingInfo = getBillingCycleInfo($actualBillingCycle, $actualPrice);
    
        $totalPrice = ($actualPrice + $totalBoosterPrice) * $quantity + $actualSetupFee;
    
        $userId = $currentUser ? $currentUser['id'] : null;
    $sessionId = session_id();
    
        $existingItemSql = "
        SELECT id, quantity, booster_amount, total_price, billing_cycle, is_subscription
        FROM cart_items 
        WHERE product_id = ? AND product_offer_id = ? AND " .
        ($userId ? "user_id = ?" : "session_id = ?");
    
    $existingParams = [$productId, $productOfferId];
    $existingParams[] = $userId ?: $sessionId;
    
    $existingStmt = $db->prepare($existingItemSql);
    $existingStmt->execute($existingParams);
    $existingItem = $existingStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingItem) {
                if ($isSubscription) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'error' => 'Cet abonnement est déjà dans votre panier. Les abonnements ne peuvent pas être dupliqués.',
                'existing_item_id' => $existingItem['id']
            ]);
            exit;
        }
        
                $newQuantity = intval($existingItem['quantity']) + $quantity;
        
                if (($offer['manage_stock'] || $offer['has_global_stock']) && $offer['available_stock'] < 999) {
            if ($offer['available_stock'] < $newQuantity) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => "Stock insuffisant pour la quantité totale demandée. Stock disponible: {$offer['available_stock']}, quantité déjà dans le panier: {$existingItem['quantity']}"
                ]);
                exit;
            }
        }
        
        $newTotalPrice = ($actualPrice + $totalBoosterPrice) * $newQuantity + $actualSetupFee;
        
                $updateSql = "
            UPDATE cart_items SET 
                quantity = ?,
                selected_boosters = ?,
                custom_data = ?,
                booster_amount = ?,
                unit_price = ?,
                setup_fee = ?,
                total_price = ?,
                billing_cycle = ?,
                is_subscription = ?,
                updated_at = NOW()
            WHERE id = ?
        ";
        
        $updateStmt = $db->prepare($updateSql);
        $success = $updateStmt->execute([
            $newQuantity,
            json_encode($boostersData),
            json_encode($customData),
            $totalBoosterPrice,
            $actualPrice,
            $actualSetupFee,
            $newTotalPrice,
            $actualBillingCycle,
            $isSubscription ? 1 : 0,
            $existingItem['id']
        ]);
        
        if (!$success) {
            throw new Exception('Erreur lors de la mise à jour de l\'article existant');
        }
        
        $itemId = $existingItem['id'];
        $message = 'Quantité mise à jour dans le panier';
        $finalQuantity = $newQuantity;
        $finalTotalPrice = $newTotalPrice;
        
    } else {
                $insertSql = "
            INSERT INTO cart_items (
                user_id, session_id, product_id, product_offer_id, quantity,
                custom_data, selected_boosters, booster_amount,
                unit_price, setup_fee, billing_cycle,
                is_subscription, total_price,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ";
        
        $insertStmt = $db->prepare($insertSql);
        $success = $insertStmt->execute([
            $userId,
            $sessionId,
            $productId,
            $productOfferId,
            $quantity,
            json_encode($customData),
            json_encode($boostersData),
            $totalBoosterPrice,
            $actualPrice,
            $actualSetupFee,
            $actualBillingCycle,
            $isSubscription ? 1 : 0,
            $totalPrice
        ]);
        
        if (!$success) {
            throw new Exception('Erreur lors de l\'ajout de l\'article au panier');
        }
        
        $itemId = $db->lastInsertId();
        $message = 'Produit ajouté au panier';
        $finalQuantity = $quantity;
        $finalTotalPrice = $totalPrice;
    }
    
        error_log("Cart item added/updated: item_id={$itemId}, product_id={$productId}, offer_id={$productOfferId}, billing_cycle={$actualBillingCycle}, quantity={$finalQuantity}, price={$actualPrice}");
    
        echo json_encode([
        'success' => true,
        'message' => $message,
        'data' => [
            'item_id' => intval($itemId),
            'product_name' => $offer['product_name'],
            'access_type_name' => $offer['access_type_name'],
            'offer_name' => $offer['name'],
            'quantity' => $finalQuantity,
            'unit_price' => $actualPrice,
            'display_price' => $actualPrice,
            'setup_fee' => $actualSetupFee,
            'booster_amount' => $totalBoosterPrice,
            'total_price' => $finalTotalPrice,
            'boosters_count' => count($boostersData),
            'billing_cycle' => $actualBillingCycle,
            'is_subscription' => $isSubscription,
            'billing_info' => [
                'cycle' => $actualBillingCycle,
                'label' => $billingInfo['label'],
                'suffix' => $billingInfo['suffix'],
                'is_subscription' => $billingInfo['is_subscription'],
                'is_commitment' => $billingInfo['is_commitment'],
                'duration_months' => $billingInfo['duration_months'],
                'period_total_price' => $billingInfo['period_total_price'],
                'monthly_equivalent_price' => $billingInfo['monthly_equivalent_price']
            ],
            'validation' => [
                'quantity_adjusted_for_subscription' => $isSubscription && $input['quantity'] > 1,
                'original_quantity_requested' => intval($input['quantity'] ?? 1),
                'final_quantity' => $finalQuantity
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Add to cart error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur lors de l\'ajout au panier: ' . $e->getMessage()
    ]);
}

function getBoosterInfo($db, $boosterId, $productId, $categoryId) {
    $sql = "
        SELECT pb.*, 
               pbe.custom_price_percentage,
               pbe.custom_price_fixed,
               pbe.custom_price_min,
               pbe.custom_price_max
        FROM product_boosters pb
        LEFT JOIN product_booster_eligibility pbe ON pb.id = pbe.booster_id 
            AND (pbe.product_id = ? OR pbe.category_id = ?)
        WHERE pb.id = ? AND pb.is_active = 1
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$productId, $categoryId, $boosterId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function calculateBoosterPrice($booster, $basePrice, $billingCycle) {
    if (!$booster) return 0;
    
        $isSubscription = $billingCycle !== 'one_time';
    
    if ($isSubscription && !intval($booster['applies_to_subscriptions'])) {
        return 0;
    }
    
    if (!$isSubscription && !intval($booster['applies_to_one_time'])) {
        return 0;
    }
    
    $price = 0;
    
    if ($booster['pricing_type'] === 'percentage') {
        $percentage = floatval($booster['custom_price_percentage'] ?: $booster['price_percentage']);
        $price = $basePrice * ($percentage / 100);
    } elseif ($booster['pricing_type'] === 'fixed') {
        $price = floatval($booster['custom_price_fixed'] ?: $booster['price_fixed']);
    }
    
        $minPrice = floatval($booster['custom_price_min'] ?: $booster['price_min'] ?: 0);
    $maxPrice = floatval($booster['custom_price_max'] ?: $booster['price_max'] ?: 0);
    
    $price = max($price, $minPrice);
    if ($maxPrice > 0) {
        $price = min($price, $maxPrice);
    }
    
    return round($price, 2);
}
?>