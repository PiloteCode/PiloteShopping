<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once __DIR__ . '/../../../includes/CsrfProtection.php';
require_once '../../../includes/AuthMiddleware.php';

$method = $_SERVER['REQUEST_METHOD'];

if (!in_array($method, ['POST', 'GET', 'DELETE'])) {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}


\CsrfProtection::requireValidToken();

$auth = initAuth();
$currentUser = $auth->getCurrentUser();
$db = Database::getInstance();

$userId = $currentUser ? $currentUser['id'] : null;
$sessionId = session_id();

if ($method === 'GET') {
    try {
        $appliedCoupon = getAppliedCoupon($db, $userId, $sessionId);
        
        if ($appliedCoupon) {
            echo json_encode([
                'success' => true,
                'applied' => true,
                'coupon' => $appliedCoupon
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'applied' => false,
                'coupon' => null
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Get applied coupon error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erreur lors de la récupération du coupon'
        ]);
    }
    exit;
}

if ($method === 'DELETE') {
    try {
        $removed = removeAppliedCoupon($db, $userId, $sessionId);
        
        if ($removed) {
            echo json_encode([
                'success' => true,
                'message' => 'Code promo retiré'
            ]);
        } else {
            throw new Exception('Aucun code promo à retirer');
        }
        
    } catch (Exception $e) {
        error_log("Remove coupon error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

try {
    $rateKey = '_coupon_attempts_' . md5(getRealIP() . '|' . $sessionId);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $now = time();
    $bucket = $_SESSION[$rateKey] ?? ['start' => $now, 'count' => 0];
    if ($now - $bucket['start'] > 60) {
        $bucket = ['start' => $now, 'count' => 0];
    }
    $bucket['count']++;
    $_SESSION[$rateKey] = $bucket;
    if ($bucket['count'] > 10) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => 'Trop de tentatives. Réessayez dans une minute.'
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!isset($input['coupon_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Code promo manquant']);
        exit;
    }
    $couponCode = strtoupper(trim($input['coupon_code']));
    if (empty($couponCode) || strlen($couponCode) > 64 || !preg_match('/^[A-Z0-9_\-]+$/', $couponCode)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Code promo invalide']);
        exit;
    }
    
        removeAppliedCoupon($db, $userId, $sessionId);
    
        $couponSql = "
        SELECT * FROM coupons 
        WHERE code = ? 
        AND is_active = 1 
        AND (start_date IS NULL OR start_date <= NOW())
        AND (end_date IS NULL OR end_date >= NOW())
        AND (usage_limit IS NULL OR usage_count < usage_limit)
    ";
    
    $couponStmt = $db->prepare($couponSql);
    $couponStmt->execute([$couponCode]);
    $coupon = $couponStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$coupon) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Code promo invalide, expiré ou épuisé'
        ]);
        exit;
    }
    
    if ($coupon['usage_limit_per_user'] > 0) {
        if (!$currentUser) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Vous devez être connecté pour utiliser ce code promo (limite par utilisateur)'
            ]);
            exit;
        }
        $usageSql = "
            SELECT COUNT(*) as usage_count
            FROM coupon_usages
            WHERE coupon_id = ? AND user_id = ?
        ";
        $usageStmt = $db->prepare($usageSql);
        $usageStmt->execute([$coupon['id'], $currentUser['id']]);
        $userUsage = $usageStmt->fetchColumn();
        if ($userUsage >= $coupon['usage_limit_per_user']) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Vous avez déjà utilisé ce code promo le nombre maximum de fois'
            ]);
            exit;
        }
    }
    
        if ($coupon['requires_account'] && !$currentUser) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Vous devez être connecté pour utiliser ce code promo'
        ]);
        exit;
    }
    
        if (!$userId && !$sessionId) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Panier vide'
        ]);
        exit;
    }
    
        $cartSql = "
        SELECT 
            ci.*,
            p.category_id, 
            p.product_type_id, 
            po.price, 
            po.billing_cycle,
            po.setup_fee,
            (po.price * ci.quantity) as item_subtotal
        FROM cart_items ci
        JOIN products p ON ci.product_id = p.id
        JOIN product_offers po ON ci.product_offer_id = po.id
        WHERE " . ($userId ? "ci.user_id = ?" : "ci.session_id = ?") . "
        AND p.is_active = 1
    ";
    
    $cartStmt = $db->prepare($cartSql);
    $cartStmt->execute([$userId ?: $sessionId]);
    $cartItems = $cartStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cartItems)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Panier vide'
        ]);
        exit;
    }
    
        $cartSubtotal = 0;
    $eligibleTotal = 0;
    $hasSubscriptions = false;
    $eligibleItems = [];
    $setupFees = 0;
    
    foreach ($cartItems as $item) {
        $itemTotal = floatval($item['item_subtotal']);
        $cartSubtotal += $itemTotal;
        $setupFees += floatval($item['setup_fee']);
        
        if ($item['billing_cycle'] !== 'one_time') {
            $hasSubscriptions = true;
        }
        
                $isEligible = true;
        
                if ($coupon['exclude_subscriptions'] && $item['billing_cycle'] !== 'one_time') {
            $isEligible = false;
        }
        
                if ($coupon['target_categories']) {
            $targetCategories = json_decode($coupon['target_categories'], true);
            if ($targetCategories && !in_array($item['category_id'], $targetCategories)) {
                $isEligible = false;
            }
        }
        
                if ($coupon['target_products']) {
            $targetProducts = json_decode($coupon['target_products'], true);
            if ($targetProducts && !in_array($item['product_id'], $targetProducts)) {
                $isEligible = false;
            }
        }
        
                if ($coupon['target_product_types']) {
            $targetTypes = json_decode($coupon['target_product_types'], true);
            if ($targetTypes && !in_array($item['product_type_id'], $targetTypes)) {
                $isEligible = false;
            }
        }
        
        if ($isEligible) {
            $eligibleTotal += $itemTotal;
            $eligibleItems[] = $item;
        }
    }
    
        if ($coupon['minimum_amount'] && $cartSubtotal < $coupon['minimum_amount']) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => "Montant minimum de " . number_format($coupon['minimum_amount'], 2) . "€ requis (actuel : " . number_format($cartSubtotal, 2) . "€)"
        ]);
        exit;
    }
    
    if ($eligibleTotal == 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Aucun produit éligible à cette réduction dans votre panier'
        ]);
        exit;
    }
    
        $discountAmount = 0;
    
    switch ($coupon['discount_type']) {
        case 'percentage':
            $discountAmount = $eligibleTotal * ($coupon['discount_value'] / 100);
            break;
            
        case 'fixed':
            $discountAmount = $coupon['discount_value'];
            break;
            
        case 'free_shipping':
            $discountAmount = 0;             break;
    }
    
        if ($coupon['max_discount_amount'] && $discountAmount > $coupon['max_discount_amount']) {
        $discountAmount = $coupon['max_discount_amount'];
    }
    
        $discountAmount = min($discountAmount, $eligibleTotal);
    $discountAmount = round($discountAmount, 2);
    
        $storeCouponSql = "
        INSERT INTO applied_coupons 
        (user_id, session_id, coupon_id, coupon_code, discount_amount, cart_subtotal, eligible_total, applied_at, expires_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR))
        ON DUPLICATE KEY UPDATE 
            coupon_id = VALUES(coupon_id),
            coupon_code = VALUES(coupon_code),
            discount_amount = VALUES(discount_amount),
            cart_subtotal = VALUES(cart_subtotal),
            eligible_total = VALUES(eligible_total),
            applied_at = NOW(),
            expires_at = DATE_ADD(NOW(), INTERVAL 24 HOUR)
    ";
    
    $storeStmt = $db->prepare($storeCouponSql);
    $storeStmt->execute([
        $userId,
        $sessionId,
        $coupon['id'],
        $coupon['code'],
        $discountAmount,
        $cartSubtotal,
        $eligibleTotal
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Code promo appliqué avec succès !',
        'data' => [
            'coupon_id' => $coupon['id'],
            'coupon_name' => $coupon['name'],
            'coupon_code' => $coupon['code'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => $coupon['discount_value'],
            'discount_amount' => $discountAmount,
            'cart_subtotal' => round($cartSubtotal, 2),
            'eligible_total' => round($eligibleTotal, 2),
            'new_total' => round($cartSubtotal - $discountAmount, 2),
            'eligible_items_count' => count($eligibleItems),
            'savings_percentage' => $cartSubtotal > 0 ? round(($discountAmount / $cartSubtotal) * 100, 1) : 0
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Apply coupon error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de l\'application du code promo: ' . $e->getMessage()
    ]);
}

function getAppliedCoupon($db, $userId, $sessionId) {
    $sql = "
        SELECT ac.*, c.name as coupon_name, c.description as coupon_description
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

function removeAppliedCoupon($db, $userId, $sessionId) {
    $sql = "
        DELETE FROM applied_coupons 
        WHERE " . ($userId ? "user_id = ?" : "session_id = ?");
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$userId ?: $sessionId]);
    
    return $stmt->rowCount() > 0;
}
?>