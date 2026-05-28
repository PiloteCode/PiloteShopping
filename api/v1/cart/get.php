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
    
    $userId = $currentUser ? $currentUser['id'] : null;
    $sessionId = session_id();
    
    if (!$userId && !$sessionId) {
        echo json_encode([
            'success' => true,
            'items' => [],
            'summary' => createEmptySummary(),
            'applied_coupon' => null
        ]);
        exit;
    }
    
        $appliedCoupon = getAppliedCoupon($db, $userId, $sessionId);
    
    $whereConditions = [];
    $params = [];
    
    if ($userId) {
        $whereConditions[] = 'ci.user_id = ?';
        $params[] = $userId;
    } else {
        $whereConditions[] = 'ci.session_id = ?';
        $params[] = $sessionId;
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    $sql = "
        SELECT ci.*,
               p.name as product_name,
               p.slug as product_slug, 
               p.images as product_images,
               p.short_description,
               p.is_active as product_is_active,
               p.category_id,
               p.product_type_id,
               pc.name as category_name,
               pc.color as category_color,
               pc.icon as category_icon,
               pt.name as type_name,
               pt.icon as type_icon,
               pt.default_delivery_method as type_default_delivery,
               pat.name as access_type_name,
               pat.icon as access_type_icon,
               pat.access_method,
               pat.default_delivery_method,
               pat.has_global_stock,
               pat.global_stock_quantity,
               pat.is_active as access_type_active,
               po.name as offer_name,
               po.price as offer_price,
               po.compare_at_price as offer_compare_price,
               po.setup_fee as offer_setup_fee,
               po.trial_days as offer_trial_days,
               po.billing_cycle as offer_billing_cycle,
               po.billing_duration_months as offer_billing_duration,
               po.auto_renew as offer_auto_renew,
               po.has_own_stock,
               po.manage_stock,
               po.stock_quantity as offer_stock_quantity,
               po.is_active as offer_active,
               po.is_featured as offer_featured,
               po.is_default as offer_default,
               CASE 
                   WHEN po.has_own_stock = 1 AND po.manage_stock = 1 THEN po.stock_quantity
                   WHEN pat.has_global_stock = 1 THEN pat.global_stock_quantity  
                   ELSE 999
               END as available_stock,
               CASE 
                   WHEN p.is_active = 0 THEN 0
                   WHEN pat.is_active = 0 THEN 0
                   WHEN po.is_active = 0 THEN 0
                   WHEN po.has_own_stock = 1 AND po.manage_stock = 1 AND po.stock_quantity < ci.quantity THEN 0
                   WHEN pat.has_global_stock = 1 AND pat.global_stock_quantity < ci.quantity THEN 0
                   ELSE 1
               END as is_available
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        LEFT JOIN product_offers po ON ci.product_offer_id = po.id
        LEFT JOIN product_access_types pat ON po.access_type_id = pat.id
        WHERE {$whereClause}
        ORDER BY ci.created_at DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $validItems = [];
    foreach ($cartItems as $item) {
        if ($item['offer_active'] && $item['offer_billing_cycle']) {
            $validItems[] = $item;
        }
    }
    
    $processedItems = [];
    foreach ($validItems as $item) {
        $processedItem = processCartItem($item);
        if ($processedItem) {
            $processedItems[] = $processedItem;
        }
    }
    
    $summary = calculateCartSummary($processedItems, $appliedCoupon);
    
    echo json_encode([
        'success' => true,
        'items' => $processedItems,
        'summary' => $summary,
        'applied_coupon' => $appliedCoupon ? formatAppliedCoupon($appliedCoupon) : null
    ]);
    
} catch (Exception $e) {
    error_log("Cart get error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => 'Erreur lors du chargement du panier'
    ]);
}

function processCartItem($item) {
    try {
        $selectedBoosters = [];
        if ($item['selected_boosters']) {
            $selectedBoosters = json_decode($item['selected_boosters'], true) ?: [];
        }
        
        $customData = [];
        if ($item['custom_data']) {
            $customData = json_decode($item['custom_data'], true) ?: [];
        }
        
        $billingCycle = $item['offer_billing_cycle'] ?: 'one_time';
        $isSubscription = $billingCycle !== 'one_time';
        
        $billingInfo = getBillingCycleInfo($billingCycle, $item['offer_price']);
        
        $deliveryMethod = $item['default_delivery_method'] ?: $item['type_default_delivery'] ?: 'automatic';
        $deliveryInfo = [
            'method' => $deliveryMethod,
            'label' => getDeliveryMethodLabel($deliveryMethod)
        ];
        
        $unitPrice = floatval($item['offer_price']);
        $setupFee = floatval($item['offer_setup_fee'] ?: 0);
        $boosterAmount = floatval($item['booster_amount'] ?: 0);
        $quantity = intval($item['quantity']);
        
        if ($isSubscription && $quantity > 1) {
            $quantity = 1;
        }
        
        $itemSubtotal = $unitPrice * $quantity;
        $totalPrice = $itemSubtotal + ($boosterAmount * $quantity) + $setupFee;
        
        $mainImage = extractMainImage($item['product_images']);
        
        $monthlyEquivalent = null;
        if ($isSubscription) {
            $monthlyEquivalent = calculateMonthlyEquivalentPrice($unitPrice, $billingCycle);
        }
        
        $subscriptionInfo = [
            'is_subscription' => $isSubscription,
            'billing_cycle' => $billingCycle,
            'billing_label' => $billingInfo['label'],
            'billing_suffix' => $billingInfo['suffix'],
            'duration_months' => $billingInfo['duration_months'],
            'is_commitment' => $billingInfo['is_commitment'],
            'monthly_equivalent_price' => $monthlyEquivalent,
            'period_total_price' => $billingInfo['period_total_price'],
            'has_trial' => intval($item['offer_trial_days']) > 0,
            'trial_days' => intval($item['offer_trial_days'] ?: 0),
            'auto_renew' => intval($item['offer_auto_renew'] ?: 1) === 1
        ];
        
        return [
            'id' => intval($item['id']),
            'product_id' => intval($item['product_id']),
            'product_name' => $item['product_name'],
            'product_slug' => $item['product_slug'],
            'product_url' => '/products/' . $item['product_slug'],
            'product_images' => $item['product_images'],
            'main_image' => $mainImage,
            'quantity' => $quantity,
            'short_description' => $item['short_description'],
            
            'category_id' => intval($item['category_id']),
            'category_name' => $item['category_name'],
            'category_color' => $item['category_color'],
            'category_icon' => $item['category_icon'],
            'product_type_id' => intval($item['product_type_id']),
            'type_name' => $item['type_name'],
            'type_icon' => $item['type_icon'],
            
            'access_type_name' => $item['access_type_name'],
            'access_type_icon' => $item['access_type_icon'],
            'access_method' => $item['access_method'],
            'offer_name' => $item['offer_name'],
            'default_delivery_method' => $deliveryMethod,
            
            'billing_cycle' => $billingCycle,
            'billing_info' => $subscriptionInfo,
            
            'unit_price' => $unitPrice,
            'display_price' => $unitPrice,
            'setup_fee' => $setupFee,
            'booster_amount' => $boosterAmount,
            'total_price' => $totalPrice,
            'item_subtotal' => $itemSubtotal,
            
            'available_stock' => intval($item['available_stock'] ?: 0),
            'is_available' => intval($item['is_available']) === 1,
            'available' => intval($item['is_available']) === 1,
            
            'selected_boosters' => $selectedBoosters,
            'custom_data' => $customData,
            'delivery_info' => $deliveryInfo,
            
            'is_subscription' => $isSubscription,
            'subscription_info' => $subscriptionInfo,
            
            'error' => intval($item['is_available']) !== 1 ? 'Produit non disponible' : null
        ];
        
    } catch (Exception $e) {
        error_log("Error processing cart item {$item['id']}: " . $e->getMessage());
        return null;
    }
}

function calculateCartSummary($items, $appliedCoupon = null) {
    $totalItems = count($items);
    $availableItems = 0;
    $subtotal = 0;
    $setupFees = 0;
    $boostersAmount = 0;
    $hasSubscriptions = false;
    $hasUnavailableItems = false;
    $subscriptionCount = 0;
    $oneTimeTotal = 0;
    
    $recurringMonthlyTotal = 0;
    $recurringQuarterlyTotal = 0;
    $recurringSemiYearlyTotal = 0;
    $recurringYearlyTotal = 0;
    $commitmentTotal = 0;
    
        $eligibleSubtotal = 0;
    
    foreach ($items as $item) {
        if ($item['available']) {
            $availableItems++;
            
            $itemSubtotal = $item['item_subtotal'];
            $subtotal += $itemSubtotal;
            $setupFees += $item['setup_fee'];
            $boostersAmount += ($item['booster_amount'] * $item['quantity']);
            
                        $isItemEligible = isItemEligibleForCoupon($item, $appliedCoupon);
            if ($isItemEligible) {
                $eligibleSubtotal += $itemSubtotal;
            }
            
            $billingCycle = $item['billing_cycle'];
            $totalWithBoosters = $itemSubtotal + ($item['booster_amount'] * $item['quantity']);
            
            if ($item['billing_info']['is_subscription']) {
                $hasSubscriptions = true;
                $subscriptionCount++;
                
                switch ($billingCycle) {
                    case 'monthly':
                        $recurringMonthlyTotal += $totalWithBoosters;
                        break;
                    case 'quarterly':
                        $recurringQuarterlyTotal += $totalWithBoosters;
                        break;
                    case 'semi_yearly':
                        $recurringSemiYearlyTotal += $totalWithBoosters;
                        break;
                    case 'yearly':
                        $recurringYearlyTotal += $totalWithBoosters;
                        break;
                    case 'commitment_3':
                    case 'commitment_6':
                    case 'commitment_12':
                    case 'commitment_24':
                        $commitmentTotal += $totalWithBoosters;
                        break;
                }
            } else {
                $oneTimeTotal += $totalWithBoosters;
            }
        } else {
            $hasUnavailableItems = true;
        }
    }
    
    $totalMonthlyRecurring = $recurringMonthlyTotal + 
                           ($recurringQuarterlyTotal / 3) + 
                           ($recurringSemiYearlyTotal / 6) + 
                           ($recurringYearlyTotal / 12) + 
                           $commitmentTotal;
    
        $discountAmount = 0;
    if ($appliedCoupon && $eligibleSubtotal > 0) {
        $discountAmount = floatval($appliedCoupon['discount_amount']);
                $discountAmount = min($discountAmount, $eligibleSubtotal);
    }
    
    $taxAmount = 0;     $totalAmount = $subtotal + $setupFees + $boostersAmount + $taxAmount - $discountAmount;
    
    return [
        'total_items' => $totalItems,
        'available_items' => $availableItems,
        'subtotal' => round($subtotal, 2),
        'setup_fees' => round($setupFees, 2),
        'boosters_amount' => round($boostersAmount, 2),
        'tax_amount' => round($taxAmount, 2),
        'discount_amount' => round($discountAmount, 2),
        'total_amount' => round($totalAmount, 2),
        'initial_total' => round($totalAmount, 2),
        
        'recurring_monthly_total' => round($recurringMonthlyTotal, 2),
        'recurring_quarterly_total' => round($recurringQuarterlyTotal, 2),
        'recurring_semi_yearly_total' => round($recurringSemiYearlyTotal, 2),
        'recurring_yearly_total' => round($recurringYearlyTotal, 2),
        'commitment_total' => round($commitmentTotal, 2),
        'one_time_total' => round($oneTimeTotal, 2),
        
        'recurring_total' => round($totalMonthlyRecurring, 2),
        
        'has_subscriptions' => $hasSubscriptions,
        'has_unavailable_items' => $hasUnavailableItems,
        'subscription_count' => $subscriptionCount,
        'can_checkout' => $availableItems > 0 && !$hasUnavailableItems,
        
        'billing_breakdown' => [
            'one_time_count' => count(array_filter($items, fn($item) => !$item['billing_info']['is_subscription'] && $item['available'])),
            'monthly_count' => count(array_filter($items, fn($item) => $item['billing_cycle'] === 'monthly' && $item['available'])),
            'quarterly_count' => count(array_filter($items, fn($item) => $item['billing_cycle'] === 'quarterly' && $item['available'])),
            'semi_yearly_count' => count(array_filter($items, fn($item) => $item['billing_cycle'] === 'semi_yearly' && $item['available'])),
            'yearly_count' => count(array_filter($items, fn($item) => $item['billing_cycle'] === 'yearly' && $item['available'])),
            'commitment_count' => count(array_filter($items, fn($item) => str_starts_with($item['billing_cycle'], 'commitment_') && $item['available']))
        ],
        
        'coupon_applied' => $appliedCoupon !== null,
        'eligible_for_discount' => $eligibleSubtotal,
        'savings_percentage' => $subtotal > 0 && $discountAmount > 0 ? round(($discountAmount / $subtotal) * 100, 1) : 0
    ];
}

function isItemEligibleForCoupon($item, $appliedCoupon) {
    if (!$appliedCoupon) {
        return false;
    }
    
    $couponId = $appliedCoupon['coupon_id'];
    
        global $db;
    $couponStmt = $db->prepare("
        SELECT exclude_subscriptions, target_categories, target_products, target_product_types 
        FROM coupons 
        WHERE id = ?
    ");
    $couponStmt->execute([$couponId]);
    $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        return false;
    }
    
        if ($coupon['exclude_subscriptions'] && $item['billing_info']['is_subscription']) {
        return false;
    }
    
        if ($coupon['target_categories']) {
        $targetCategories = json_decode($coupon['target_categories'], true);
        if ($targetCategories && !in_array($item['category_id'], $targetCategories)) {
            return false;
        }
    }
    
        if ($coupon['target_products']) {
        $targetProducts = json_decode($coupon['target_products'], true);
        if ($targetProducts && !in_array($item['product_id'], $targetProducts)) {
            return false;
        }
    }
    
        if ($coupon['target_product_types']) {
        $targetTypes = json_decode($coupon['target_product_types'], true);
        if ($targetTypes && !in_array($item['product_type_id'], $targetTypes)) {
            return false;
        }
    }
    
    return true;
}

function getAppliedCoupon($db, $userId, $sessionId) {
    $sql = "
        SELECT ac.*, c.name as coupon_name, c.description as coupon_description,
               c.discount_type, c.discount_value
        FROM applied_coupons ac
        JOIN coupons c ON ac.coupon_id = c.id
        WHERE " . ($userId ? "ac.user_id = ?" : "ac.session_id = ?") . "
        AND ac.expires_at > NOW()
        AND c.is_active = 1
        LIMIT 1
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId ?: $sessionId]);
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function formatAppliedCoupon($coupon) {
    if (!$coupon) {
        return null;
    }
    
    return [
        'id' => intval($coupon['id']),
        'code' => $coupon['coupon_code'],
        'name' => $coupon['coupon_name'],
        'description' => $coupon['coupon_description'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => floatval($coupon['discount_value']),
        'discount_amount' => floatval($coupon['discount_amount']),
        'applied_at' => $coupon['applied_at']
    ];
}

function createEmptySummary() {
    return [
        'total_items' => 0,
        'available_items' => 0,
        'subtotal' => 0,
        'setup_fees' => 0,
        'boosters_amount' => 0,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'total_amount' => 0,
        'initial_total' => 0,
        'recurring_monthly_total' => 0,
        'recurring_quarterly_total' => 0,
        'recurring_semi_yearly_total' => 0,
        'recurring_yearly_total' => 0,
        'commitment_total' => 0,
        'one_time_total' => 0,
        'recurring_total' => 0,
        'has_subscriptions' => false,
        'has_unavailable_items' => false,
        'subscription_count' => 0,
        'can_checkout' => false,
        'billing_breakdown' => [
            'one_time_count' => 0,
            'monthly_count' => 0,
            'quarterly_count' => 0,
            'semi_yearly_count' => 0,
            'yearly_count' => 0,
            'commitment_count' => 0
        ],
        'coupon_applied' => false,
        'eligible_for_discount' => 0,
        'savings_percentage' => 0
    ];
}

function extractMainImage($imagesJson) {
    if (!$imagesJson) {
        return '/assets/images/product-placeholder.jpg';
    }
    
    try {
        $images = json_decode($imagesJson, true);
        if ($images && is_array($images) && count($images) > 0) {
            return $images[0];
        }
    } catch (Exception $e) {
            }
    
    return '/assets/images/product-placeholder.jpg';
}
?>