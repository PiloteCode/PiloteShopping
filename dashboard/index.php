<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';
require_once '../includes/NotificationManager.php';

$auth = initAuth();

$auth->requireAuth();

$currentUser = $auth->getCurrentUser();

if ($auth->needsProfileCompletion()) {
    header('Location: /auth/complete-profile');
    exit();
}

try {
    $db = Database::getInstance();
    
        $stats = [
        'total_orders' => 0,
        'pending_orders' => 0,
        'total_spent' => 0.00,
        'favorite_items' => 0,
        'active_subscriptions' => 0,
        'last_order_date' => null
    ];
    
        $stats_stmt = $db->prepare("
        SELECT
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT CASE WHEN o.status IN ('pending', 'processing') THEN o.id END) as pending_orders,
            COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spent,
            MAX(o.created_at) as last_order_date
        FROM orders o
        WHERE o.user_id = ?
    ");
    $stats_stmt->execute([$currentUser['id']]);
    $order_stats = $stats_stmt->fetch();
    
    if ($order_stats) {
        $stats['total_orders'] = $order_stats['total_orders'];
        $stats['pending_orders'] = $order_stats['pending_orders'];
        $stats['total_spent'] = $order_stats['total_spent'];
        $stats['last_order_date'] = $order_stats['last_order_date'];
    }
    
        $subscriptions_stmt = $db->prepare("
        SELECT COUNT(*) as active_subscriptions
        FROM subscriptions s
        WHERE s.user_id = ? AND s.status = 'active'
    ");
    $subscriptions_stmt->execute([$currentUser['id']]);
    $sub_result = $subscriptions_stmt->fetch();
    $stats['active_subscriptions'] = $sub_result['active_subscriptions'];
    
        try {
        $favorites_stmt = $db->prepare("
            SELECT COUNT(*) as favorite_items 
            FROM user_favorites 
            WHERE user_id = ?
        ");
        $favorites_stmt->execute([$currentUser['id']]);
        $fav_result = $favorites_stmt->fetch();
        $stats['favorite_items'] = $fav_result['favorite_items'];
    } catch (Exception $e) {
        $stats['favorite_items'] = 0;
    }
    
        $orders_stmt = $db->prepare("
        SELECT
            id,
            order_number,
            status,
            payment_status,
            total_amount,
            created_at,
            currency
        FROM orders
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $orders_stmt->execute([$currentUser['id']]);
    $recentOrders = $orders_stmt->fetchAll();

        foreach ($recentOrders as &$order) {
        $items_stmt = $db->prepare("
            SELECT product_name, quantity
            FROM order_items
            WHERE order_id = ?
        ");
        $items_stmt->execute([$order['id']]);
        $items = $items_stmt->fetchAll();

        $productsList = [];
        $totalItems = 0;
        $totalQuantity = 0;

        foreach ($items as $item) {
            $productsList[] = $item['product_name'] . ' (x' . $item['quantity'] . ')';
            $totalItems++;
            $totalQuantity += $item['quantity'];
        }

        $order['products'] = implode(', ', $productsList);
        $order['total_items'] = $totalItems;
        $order['total_quantity'] = $totalQuantity;
    }
    
        foreach ($recentOrders as &$order) {
                if ($order['payment_status'] === 'pending') {
            $order['display_status'] = 'En attente de paiement';
            $order['status_color'] = 'bg-yellow-500/20 text-yellow-400';
        } elseif ($order['payment_status'] === 'failed') {
            $order['display_status'] = 'Paiement échoué';
            $order['status_color'] = 'bg-red-500/20 text-red-400';
        } elseif ($order['status'] === 'completed') {
            $order['display_status'] = 'Terminée';
            $order['status_color'] = 'bg-green-500/20 text-green-400';
        } elseif ($order['status'] === 'processing') {
            $order['display_status'] = 'En traitement';
            $order['status_color'] = 'bg-blue-500/20 text-blue-400';
        } elseif ($order['status'] === 'cancelled') {
            $order['display_status'] = 'Annulée';
            $order['status_color'] = 'bg-gray-500/20 text-gray-400';
        } else {
            $order['display_status'] = 'En cours';
            $order['status_color'] = 'bg-purple-500/20 text-purple-400';
        }
    }
    
        $activityItems = [];
    
        $balance_stmt = $db->prepare("
        SELECT 
            'balance' as activity_type,
            transaction_id,
            type,
            amount,
            description,
            created_at
        FROM balance_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $balance_stmt->execute([$currentUser['id']]);
    $balanceTransactions = $balance_stmt->fetchAll();
    
    foreach ($balanceTransactions as $trans) {
        $activityItems[] = [
            'type' => 'balance',
            'icon' => in_array($trans['type'], ['credit', 'bonus']) ? 'plus' : 'minus',
            'icon_color' => in_array($trans['type'], ['credit', 'bonus']) ? 'green' : 'red',
            'title' => $trans['description'],
            'description' => 'Transaction #' . $trans['transaction_id'],
            'amount' => $trans['amount'],
            'amount_sign' => in_array($trans['type'], ['credit', 'bonus']) ? '+' : '-',
            'date' => $trans['created_at']
        ];
    }
    
        $activity_orders_stmt = $db->prepare("
        SELECT 
            'order' as activity_type,
            order_number,
            total_amount,
            status,
            created_at
        FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $activity_orders_stmt->execute([$currentUser['id']]);
    $activityOrders = $activity_orders_stmt->fetchAll();
    
    foreach ($activityOrders as $ord) {
        $activityItems[] = [
            'type' => 'order',
            'icon' => 'shopping-cart',
            'icon_color' => 'blue',
            'title' => 'Commande #' . $ord['order_number'],
            'description' => 'Statut: ' . ucfirst($ord['status']),
            'amount' => $ord['total_amount'],
            'amount_sign' => '',
            'date' => $ord['created_at']
        ];
    }
    
        try {
        $fav_activity_stmt = $db->prepare("
            SELECT 
                'favorite' as activity_type,
                p.name as product_name,
                uf.created_at
            FROM user_favorites uf
            JOIN products p ON uf.product_id = p.id
            WHERE uf.user_id = ?
            ORDER BY uf.created_at DESC
            LIMIT 5
        ");
        $fav_activity_stmt->execute([$currentUser['id']]);
        $favActivities = $fav_activity_stmt->fetchAll();
        
        foreach ($favActivities as $fav) {
            $activityItems[] = [
                'type' => 'favorite',
                'icon' => 'heart',
                'icon_color' => 'red',
                'title' => 'Ajouté aux favoris',
                'description' => $fav['product_name'],
                'amount' => null,
                'date' => $fav['created_at']
            ];
        }
    } catch (Exception $e) {
            }
    
        $sub_activity_stmt = $db->prepare("
        SELECT 
            'subscription' as activity_type,
            subscription_number,
            status,
            amount,
            created_at
        FROM subscriptions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $sub_activity_stmt->execute([$currentUser['id']]);
    $subActivities = $sub_activity_stmt->fetchAll();
    
    foreach ($subActivities as $sub) {
        $activityItems[] = [
            'type' => 'subscription',
            'icon' => 'sync-alt',
            'icon_color' => 'purple',
            'title' => 'Abonnement ' . ($sub['status'] === 'active' ? 'activé' : $sub['status']),
            'description' => '#' . $sub['subscription_number'],
            'amount' => $sub['amount'],
            'amount_sign' => '',
            'date' => $sub['created_at']
        ];
    }
    
        usort($activityItems, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
    $recentActivity = array_slice($activityItems, 0, 10);
    
        $twofa_stmt = $db->prepare("SELECT is_enabled FROM users_2fa WHERE user_id = ?");
    $twofa_stmt->execute([$currentUser['id']]);
    $twofa = $twofa_stmt->fetch();
    $has2FA = $twofa && $twofa['is_enabled'];

} catch (Exception $e) {
    error_log("Dashboard data error: " . $e->getMessage());
    $recentOrders = [];
    $recentActivity = [];
    $has2FA = false;
}

$dashboardNotifications = [];
$unreadNotificationsCount = 0;
try {
    $notificationManager = new NotificationManager();
    $dashboardNotifications = $notificationManager->getUserNotifications($currentUser['id'], 3, 0, true);     $unreadNotificationsCount = $notificationManager->getUnreadCount($currentUser['id']);
} catch (Exception $e) {
    error_log("Dashboard notifications error: " . $e->getMessage());
}

$seo = [
    'title' => 'Tableau de bord - Pilote Shopping',
    'description' => 'Gérez votre compte Pilote Shopping, consultez vos commandes et votre solde.',
    'keywords' => 'dashboard, compte, commandes, solde, pilote shopping',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen">

<section class="py-16">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-7xl mx-auto">

<?php if (!empty($dashboardNotifications)): ?>
            <div class="mb-8" data-aos="fade-down">
                <div class="bg-gradient-to-r from-blue-500/10 via-purple-500/10 to-pink-500/10 border border-blue-500/30 rounded-2xl p-6 relative overflow-hidden">

                    <div class="absolute inset-0 opacity-5">
                        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #60a5fa 1px, transparent 1px); background-size: 20px 20px;"></div>
                    </div>

                    <div class="relative z-10">
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-500/20 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-bell text-blue-400 text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-white font-bold text-lg">Notifications récentes</h3>
                                    <p class="text-gray-400 text-sm">
<?php if ($unreadNotificationsCount > 3): ?>
                                            Vous avez <?= $unreadNotificationsCount ?> notifications non lues
<?php else: ?>
<?= count($dashboardNotifications) ?> notification<?= count($dashboardNotifications) > 1 ? 's' : '' ?> non lue<?= count($dashboardNotifications) > 1 ? 's' : '' ?>
<?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            <a href="/dashboard/notifications"
                               class="text-blue-400 hover:text-blue-300 font-medium text-sm transition-colors flex items-center gap-2">
                                Voir tout
<?php if ($unreadNotificationsCount > 0): ?>
                                <span class="bg-blue-500 text-white text-xs font-bold px-2 py-1 rounded-full">
<?= $unreadNotificationsCount > 99 ? '99+' : $unreadNotificationsCount ?>
                                </span>
<?php endif; ?>
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="space-y-3">
<?php foreach ($dashboardNotifications as $notification): ?>
<?php
                                $colorClasses = [
                                    'blue' => 'bg-blue-500/10 border-blue-500/20 hover:border-blue-500/40',
                                    'green' => 'bg-green-500/10 border-green-500/20 hover:border-green-500/40',
                                    'red' => 'bg-red-500/10 border-red-500/20 hover:border-red-500/40',
                                    'orange' => 'bg-orange-500/10 border-orange-500/20 hover:border-orange-500/40',
                                    'purple' => 'bg-purple-500/10 border-purple-500/20 hover:border-purple-500/40',
                                    'yellow' => 'bg-yellow-500/10 border-yellow-500/20 hover:border-yellow-500/40'
                                ];
                                $colorClass = $colorClasses[$notification['color']] ?? $colorClasses['blue'];

                                $iconColorClasses = [
                                    'blue' => 'text-blue-400',
                                    'green' => 'text-green-400',
                                    'red' => 'text-red-400',
                                    'orange' => 'text-orange-400',
                                    'purple' => 'text-purple-400',
                                    'yellow' => 'text-yellow-400'
                                ];
                                $iconColor = $iconColorClasses[$notification['color']] ?? $iconColorClasses['blue'];
                            ?>
                            <div class="<?= $colorClass ?> border rounded-xl p-4 transition-all duration-300 cursor-pointer hover:scale-[1.02]"
                                 onclick="handleNotificationClick(<?= $notification['id'] ?>, '<?= htmlspecialchars($notification['link'] ?? '', ENT_QUOTES) ?>')">
                                <div class="flex items-start gap-4">
                                    <div class="w-10 h-10 bg-dark-700/50 rounded-lg flex items-center justify-center flex-shrink-0">
                                        <i class="fas fa-<?= htmlspecialchars($notification['icon']) ?><?= $iconColor ?>"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-4 mb-1">
                                            <h4 class="font-semibold text-white">
<?= htmlspecialchars($notification['title']) ?>
                                            </h4>
                                            <span class="text-gray-500 text-xs flex-shrink-0">
<?php
                                                    $created = strtotime($notification['created_at']);
                                                    $diff = time() - $created;
                                                    if ($diff < 3600) {
                                                        echo floor($diff / 60) . ' min';
                                                    } elseif ($diff < 86400) {
                                                        echo floor($diff / 3600) . ' h';
                                                    } else {
                                                        echo date('d/m', $created);
                                                    }
                                                ?>
                                            </span>
                                        </div>
                                        <p class="text-gray-300 text-sm line-clamp-2">
<?= htmlspecialchars($notification['message']) ?>
                                        </p>
<?php if ($notification['link']): ?>
                                        <div class="mt-2">
                                            <span class="text-blue-400 text-xs font-medium inline-flex items-center gap-1">
                                                <i class="fas fa-arrow-right text-xs"></i>
                                                Voir les détails
                                            </span>
                                        </div>
<?php endif; ?>
                                    </div>
                                </div>
                            </div>
<?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
<?php endif; ?>


            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-12" data-aos="fade-up">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-blue-primary/50 transition-all duration-300 group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm font-medium">Total commandes</p>
                            <p class="text-3xl font-bold text-white group-hover:text-blue-primary transition-colors">
<?= $stats['total_orders'] ?>
                            </p>
                        </div>
                        <div class="w-14 h-14 bg-blue-primary/20 rounded-xl flex items-center justify-center group-hover:bg-blue-primary/30 transition-colors">
                            <i class="fas fa-shopping-bag text-blue-primary text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-orange-500/50 transition-all duration-300 group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm font-medium">En cours</p>
                            <p class="text-3xl font-bold text-white group-hover:text-orange-400 transition-colors">
<?= $stats['pending_orders'] ?>
                            </p>
                        </div>
                        <div class="w-14 h-14 bg-orange-500/20 rounded-xl flex items-center justify-center group-hover:bg-orange-500/30 transition-colors">
                            <i class="fas fa-clock text-orange-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-green-500/50 transition-all duration-300 group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm font-medium">Total dépensé</p>
                            <p class="text-3xl font-bold text-white group-hover:text-green-400 transition-colors">
<?= number_format($stats['total_spent'], 2) ?>€
                            </p>
                        </div>
                        <div class="w-14 h-14 bg-green-500/20 rounded-xl flex items-center justify-center group-hover:bg-green-500/30 transition-colors">
                            <i class="fas fa-euro-sign text-green-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-purple-500/50 transition-all duration-300 group">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-gray-400 text-sm font-medium">Abonnements</p>
                            <p class="text-3xl font-bold text-white group-hover:text-purple-400 transition-colors">
<?= $stats['active_subscriptions'] ?>
                            </p>
                        </div>
                        <div class="w-14 h-14 bg-purple-500/20 rounded-xl flex items-center justify-center group-hover:bg-purple-500/30 transition-colors">
                            <i class="fas fa-sync-alt text-purple-400 text-xl"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-12" data-aos="fade-up">
                <h2 class="text-2xl font-bold text-white mb-6">
                    <i class="fas fa-bolt text-blue-primary mr-2"></i>
                    Actions rapides
                </h2>

                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                    <a href="/products" class="group bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-blue-primary/50 transition-all duration-300 text-center">
                        <div class="w-12 h-12 bg-blue-primary/20 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-blue-primary/30 transition-colors">
                            <i class="fas fa-shopping-cart text-blue-primary text-lg"></i>
                        </div>
                        <span class="text-white text-sm font-medium">Boutique</span>
                    </a>

                    <a href="/orders" class="group bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-green-500/50 transition-all duration-300 text-center">
                        <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-green-500/30 transition-colors">
                            <i class="fas fa-box text-green-400 text-lg"></i>
                        </div>
                        <span class="text-white text-sm font-medium">Commandes</span>
                    </a>

                    <a href="/dashboard/subscriptions" class="group bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-purple-500/50 transition-all duration-300 text-center">
                        <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-purple-500/30 transition-colors">
                            <i class="fas fa-sync-alt text-purple-400 text-lg"></i>
                        </div>
                        <span class="text-white text-sm font-medium">Abonnements</span>
                    </a>

                    <a href="/wallet" class="group bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-yellow-500/50 transition-all duration-300 text-center">
                        <div class="w-12 h-12 bg-yellow-500/20 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-yellow-500/30 transition-colors">
                            <i class="fas fa-wallet text-yellow-400 text-lg"></i>
                        </div>
                        <span class="text-white text-sm font-medium">Portefeuille</span>
                    </a>

                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                <div class="lg:col-span-2 space-y-8">

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6" data-aos="fade-up">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold text-white">
                                <i class="fas fa-history text-blue-primary mr-2"></i>
                                Commandes récentes
                            </h2>
                            <a href="/orders" class="text-blue-primary hover:text-blue-secondary text-sm font-medium transition-colors">
                                Voir tout <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                        </div>

<?php if (empty($recentOrders)): ?>
                            <div class="text-center py-12">
                                <div class="w-16 h-16 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                    <i class="fas fa-shopping-bag text-blue-primary text-2xl"></i>
                                </div>
                                <h3 class="text-white font-medium mb-2">Aucune commande pour le moment</h3>
                                <p class="text-gray-400 text-sm mb-6">Découvrez notre boutique et passez votre première commande !</p>
                                <a href="/products" class="bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-105">
                                    <i class="fas fa-shopping-cart mr-2"></i>Découvrir la boutique
                                </a>
                            </div>
<?php else: ?>
                            <div class="space-y-4">
<?php foreach ($recentOrders as $order): ?>
                                <div class="bg-dark-700/50 rounded-xl p-4 hover:bg-dark-700 transition-colors">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <div class="flex items-center flex-wrap gap-3 mb-2">
                                                <span class="font-semibold text-white">
                                                    #<?= htmlspecialchars($order['order_number']) ?>
                                                </span>
                                                <span class="px-3 py-1 rounded-full text-xs font-medium <?= $order['status_color'] ?>">
<?= htmlspecialchars($order['display_status']) ?>
                                                </span>
<?php if ($order['total_items'] > 0): ?>
                                                <span class="text-gray-400 text-xs">
<?= $order['total_items'] ?> article<?= $order['total_items'] > 1 ? 's' : '' ?>
                                                </span>
<?php endif; ?>
                                            </div>
                                            <p class="text-gray-400 text-sm mb-1">
<?= htmlspecialchars(substr($order['products'] ?? 'Commande vide', 0, 100)) ?>
<?= strlen($order['products'] ?? '') > 100 ? '...' : '' ?>
                                            </p>
                                            <p class="text-gray-500 text-xs mt-2">
                                                <i class="far fa-clock mr-1"></i>
<?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?>
                                            </p>
                                        </div>
                                        <div class="text-right ml-4">
                                            <p class="text-white font-bold text-lg">
<?= number_format($order['total_amount'], 2) ?>€
                                            </p>
                                            <a href="/orders/detail?order=<?= $order['id'] ?>"
                                               class="inline-flex items-center text-blue-primary hover:text-blue-secondary text-sm mt-2 transition-colors">
                                                Détails <i class="fas fa-arrow-right ml-1"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
<?php endforeach; ?>
                            </div>
<?php endif; ?>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6" data-aos="fade-up">
                        <div class="flex items-center justify-between mb-6">
                            <h2 class="text-xl font-bold text-white">
                                <i class="fas fa-chart-line text-blue-primary mr-2"></i>
                                Activité récente
                            </h2>
                        </div>

<?php if (empty($recentActivity)): ?>
                            <div class="text-center py-8">
                                <div class="w-12 h-12 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-3">
                                    <i class="fas fa-chart-line text-gray-400 text-lg"></i>
                                </div>
                                <p class="text-gray-400 text-sm">Aucune activité récente</p>
                            </div>
<?php else: ?>
                            <div class="space-y-3">
<?php foreach ($recentActivity as $activity): ?>
                                <div class="flex items-center space-x-4 p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center bg-<?= $activity['icon_color'] ?>-500/20">
                                        <i class="fas fa-<?= $activity['icon'] ?> text-<?= $activity['icon_color'] ?>-400 text-xs"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-white text-sm font-medium truncate">
<?= htmlspecialchars($activity['title']) ?>
                                        </p>
                                        <p class="text-gray-400 text-xs truncate">
<?= htmlspecialchars($activity['description']) ?>
                                        </p>
                                    </div>
                                    <div class="text-right">
<?php if ($activity['amount'] !== null): ?>
                                        <span class="font-bold text-sm <?= 
                                            $activity['amount_sign'] === '+' ? 'text-green-400' : 
                                            ($activity['amount_sign'] === '-' ? 'text-red-400' : 'text-white')
                                        ?>">
<?= $activity['amount_sign'] ?><?= number_format($activity['amount'], 2) ?>€
                                        </span>
<?php endif; ?>
                                        <p class="text-gray-500 text-xs">
<?= date('d/m H:i', strtotime($activity['date'])) ?>
                                        </p>
                                    </div>
                                </div>
<?php endforeach; ?>
                            </div>
<?php endif; ?>
                    </div>
                </div>

                <div class="space-y-8">

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6" data-aos="fade-up">
                        <h3 class="text-lg font-bold text-white mb-4">
                            <i class="fas fa-user text-blue-primary mr-2"></i>
                            Mon compte
                        </h3>

                        <div class="space-y-4">
                            <div class="flex items-center space-x-3">
<?php if ($currentUser['avatar_url']): ?>
                                    <img src="<?= htmlspecialchars($currentUser['avatar_url']) ?>" 
                                         alt="Avatar" 
                                         class="w-12 h-12 rounded-full object-cover border-2 border-blue-primary/30">
<?php else: ?>
                                    <div class="w-12 h-12 bg-gradient-to-br from-blue-primary to-purple-500 rounded-full flex items-center justify-center">
                                        <span class="text-white text-lg font-bold">
<?= strtoupper(substr($currentUser['first_name'], 0, 1)) ?>
                                        </span>
                                    </div>
<?php endif; ?>
                                <div>
                                    <p class="text-white font-medium">
<?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?>
                                    </p>
                                    <p class="text-gray-400 text-sm">@<?= htmlspecialchars($currentUser['username']) ?></p>
                                </div>
                            </div>

                            <div class="border-t border-dark-600 pt-4">
                                <div class="space-y-3">
                                    <div class="flex justify-between">
                                        <span class="text-gray-400 text-sm">Email</span>
                                        <span class="text-white text-sm"><?= htmlspecialchars($currentUser['email']) ?></span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400 text-sm">Statut</span>
                                        <span class="text-green-400 text-sm font-medium">
                                            <i class="fas fa-check-circle mr-1"></i>Vérifié
                                        </span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400 text-sm">Membre depuis</span>
                                        <span class="text-white text-sm">
<?= date('M Y', strtotime($currentUser['created_at'] ?? 'now')) ?>
                                        </span>
                                    </div>
<?php if ($stats['last_order_date']): ?>
                                    <div class="flex justify-between">
                                        <span class="text-gray-400 text-sm">Dernière commande</span>
                                        <span class="text-white text-sm">
<?= date('d/m/Y', strtotime($stats['last_order_date'])) ?>
                                        </span>
                                    </div>
<?php endif; ?>
                                </div>
                            </div>

                            <a href="/users/<?= htmlspecialchars($currentUser['username']) ?>" 
                               class="block w-full text-center px-4 py-2 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white rounded-xl transition-all duration-300 transform hover:scale-105">
                                Voir mon profil
                            </a>
                        </div>
                    </div>

<?php if ($stats['active_subscriptions'] > 0): ?>
                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6" data-aos="fade-up">
                        <h3 class="text-lg font-bold text-white mb-4">
                            <i class="fas fa-sync-alt text-purple-400 mr-2"></i>
                            Abonnements actifs
                        </h3>

                        <div class="text-center">
                            <div class="text-3xl font-bold text-purple-400 mb-2">
<?= $stats['active_subscriptions'] ?>
                            </div>
                            <p class="text-gray-400 text-sm mb-4">
                                Abonnement<?= $stats['active_subscriptions'] > 1 ? 's' : '' ?> en cours
                            </p>
                            <a href="/dashboard/subscriptions" 
                               class="bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-bold py-2 px-4 rounded-xl transition-all duration-300 transform hover:scale-105 text-sm">
                                Gérer mes abonnements
                            </a>
                        </div>
                    </div>
<?php endif; ?>


<?php if ($has2FA): ?>
                    <div class="bg-green-500/10 border border-green-500/30 rounded-2xl p-4" data-aos="fade-up">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-shield-alt text-green-400"></i>
                            </div>
                            <div>
                                <p class="text-green-400 font-medium">2FA Activée</p>
                                <p class="text-gray-400 text-xs">Votre compte est sécurisé</p>
                            </div>
                        </div>
                    </div>
<?php else: ?>
                    <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-2xl p-4" data-aos="fade-up">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-yellow-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-exclamation-triangle text-yellow-400"></i>
                            </div>
                            <div class="flex-1">
                                <p class="text-yellow-400 font-medium">Sécurité</p>
                                <p class="text-gray-400 text-xs mb-2">Activez la 2FA pour plus de sécurité</p>
                                <a href="/settings?tab=security" class="text-yellow-400 hover:text-yellow-300 text-xs font-medium">
                                    Activer maintenant →
                                </a>
                            </div>
                        </div>
                    </div>
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<?php require_once '../includes/footer.php'; ?>


<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>

async function handleNotificationClick(notificationId, link) {
    try {

        await fetch('/api/v1/notifications/actions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'mark_as_read',
                notification_id: notificationId
            })
        });

        if (typeof updateNotificationBadge === 'function') {
            updateNotificationBadge();
        }

        if (link) {
            window.location.href = link;
        }
    } catch (error) {
        console.error('Erreur lors du marquage de la notification:', error);

        if (link) {
            window.location.href = link;
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 600,
            once: true,
            offset: 50
        });
    }

    adjustContentMargin();
});

function adjustContentMargin() {
    const header = document.querySelector('header');
    const mainContent = document.getElementById('main-content');

    if (header && mainContent) {
        mainContent.style.paddingTop = `${header.offsetHeight}px`;
    }
}

window.addEventListener('resize', adjustContentMargin);
</script>

<style>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeInUp {
    animation: fadeInUp 0.6s ease-out;
}

.group:hover {
    transform: translateY(-2px);
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.notification-banner-item {
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .grid-cols-6 {
        grid-template-columns: repeat(2, 1fr);
    }

    .text-4xl.md\\:text-5xl {
        font-size: 2.5rem;
    }
}
</style>

<?php
endHTMLPage();
?>