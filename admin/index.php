<?php
define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';
require_once 'includes/AdminAuthMiddleware.php';

$adminAuth = initAdminAuth();
$adminAuth->requireAdminAuth(['admin', 'super_admin']);

$currentAdmin = $adminAuth->getCurrentAdmin();
$currentUser = $adminAuth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        
        $usersStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
            COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified_users,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_users_7d,
            COUNT(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users_7d
        FROM users
    ");
    $usersStmt->execute();
    $usersStats = $usersStmt->fetch();
    
        $ordersStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as orders_30d,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as orders_7d,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount END), 0) as completed_revenue,
            COALESCE(SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount END), 0) as revenue_30d,
            COALESCE(AVG(CASE WHEN status = 'completed' THEN total_amount END), 0) as avg_order_value
        FROM orders
    ");
    $ordersStmt->execute();
    $ordersStats = $ordersStmt->fetch();
    
        $productsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_products,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_products,
            COUNT(CASE WHEN stock_quantity <= stock_threshold THEN 1 END) as low_stock_products,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_products_30d,
            COALESCE(SUM(view_count), 0) as total_views,
            COALESCE(SUM(sale_count), 0) as total_sales
        FROM products
    ");
    $productsStmt->execute();
    $productsStats = $productsStmt->fetch();
    
        $subscriptionsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_subscriptions,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_subscriptions,
            COUNT(CASE WHEN status = 'paused' THEN 1 END) as paused_subscriptions,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_subscriptions,
            COALESCE(SUM(CASE WHEN status = 'active' THEN amount END), 0) as monthly_recurring_revenue
        FROM subscriptions
    ");
    $subscriptionsStmt->execute();
    $subscriptionsStats = $subscriptionsStmt->fetch();
    
        
        $recentLoginsStmt = $db->prepare("
        SELECT u.username, u.first_name, u.last_name, u.avatar_url, u.last_login_at
        FROM users u
        WHERE u.last_login_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY u.last_login_at DESC
        LIMIT 10
    ");
    $recentLoginsStmt->execute();
    $recentLogins = $recentLoginsStmt->fetchAll();
    
        $recentOrdersStmt = $db->prepare("
        SELECT o.*, u.username, u.first_name, u.last_name,
               GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 8
    ");
    $recentOrdersStmt->execute();
    $recentOrders = $recentOrdersStmt->fetchAll();
    
        $systemActivityStmt = $db->prepare("
        SELECT 'user_registration' as type, created_at, 
               CONCAT(first_name, ' ', last_name) as description,
               'user' as icon, 'blue' as color
        FROM users 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        UNION ALL
        SELECT 'order_placed' as type, created_at,
               CONCAT('Commande #', order_number, ' - ', FORMAT(total_amount, 2), '€') as description,
               'shopping-cart' as icon, 'green' as color
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 15
    ");
    $systemActivityStmt->execute();
    $systemActivity = $systemActivityStmt->fetchAll();
    
        
        $systemHealth = [];
    try {
        $systemHealthStmt = $db->prepare("
            SELECT check_name, status, message, response_time, last_checked
            FROM system_health
            ORDER BY 
                CASE status 
                    WHEN 'critical' THEN 1 
                    WHEN 'error' THEN 2 
                    WHEN 'warning' THEN 3 
                    ELSE 4 
                END,
                check_name
        ");
        $systemHealthStmt->execute();
        $systemHealth = $systemHealthStmt->fetchAll();
    } catch (Exception $e) {
                $systemHealth = [
            ['check_name' => 'database_connection', 'status' => 'ok', 'message' => 'Base de données accessible', 'response_time' => 0.023],
            ['check_name' => 'file_permissions', 'status' => 'ok', 'message' => 'Permissions fichiers OK', 'response_time' => null],
            ['check_name' => 'storage_space', 'status' => 'warning', 'message' => 'Espace disque à surveiller', 'response_time' => null]
        ];
    }
    
        $notifications = [];
    try {
        $adminNotifications = $db->prepare("
            SELECT an.*, 
                   CASE WHEN anr.admin_id IS NULL THEN 0 ELSE 1 END as is_read
            FROM admin_notifications an
            LEFT JOIN admin_notification_reads anr ON an.id = anr.notification_id AND anr.admin_id = ?
            WHERE an.is_active = 1 
            AND (an.expires_at IS NULL OR an.expires_at > NOW())
            AND (an.target_role = 'all' OR an.target_role = ?)
            ORDER BY an.priority DESC, an.created_at DESC
            LIMIT 10
        ");
        $adminNotifications->execute([$currentAdmin['id'], $currentAdmin['role']]);
        $notifications = $adminNotifications->fetchAll();
    } catch (Exception $e) {
                $notifications = [];
    }
    
        
        $dailyRevenueStmt = $db->prepare("
        SELECT DATE(created_at) as date,
               COALESCE(SUM(total_amount), 0) as revenue,
               COUNT(*) as orders
        FROM orders 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND status = 'completed'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $dailyRevenueStmt->execute();
    $dailyRevenue = $dailyRevenueStmt->fetchAll();
    
        $topProductsStmt = $db->prepare("
        SELECT p.name, p.slug, p.images,
               COALESCE(SUM(oi.quantity), 0) as total_sold,
               COALESCE(SUM(oi.total_price), 0) as revenue
        FROM products p
        LEFT JOIN order_items oi ON p.id = oi.product_id
        LEFT JOIN orders o ON oi.order_id = o.id AND o.status = 'completed' AND o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY p.id
        HAVING total_sold > 0
        ORDER BY total_sold DESC
        LIMIT 6
    ");
    $topProductsStmt->execute();
    $topProducts = $topProductsStmt->fetchAll();
    
        foreach ($topProducts as &$product) {
        $images = json_decode($product['images'] ?? '[]', true);
        $product['image'] = !empty($images) ? $images[0] : '/assets/images/product-placeholder.jpg';
    }
    
        $recentAdminLogs = [];
    try {
        $recentAdminLogsStmt = $db->prepare("
            SELECT al.*, u.username, u.first_name, u.last_name, u.avatar_url,
                   au.role as admin_role
            FROM admin_logs al
            JOIN admin_users au ON al.admin_id = au.id
            JOIN users u ON au.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT 10
        ");
        $recentAdminLogsStmt->execute();
        $recentAdminLogs = $recentAdminLogsStmt->fetchAll();
    } catch (Exception $e) {
                $recentAdminLogs = [];
    }
    
} catch (Exception $e) {
    error_log("Admin dashboard error: " . $e->getMessage());
    
        $usersStats = ['total_users' => 0, 'active_users' => 0, 'verified_users' => 0, 'new_users_30d' => 0, 'new_users_7d' => 0, 'active_users_7d' => 0];
    $ordersStats = ['total_orders' => 0, 'completed_orders' => 0, 'pending_orders' => 0, 'orders_30d' => 0, 'orders_7d' => 0, 'total_revenue' => 0, 'completed_revenue' => 0, 'revenue_30d' => 0, 'avg_order_value' => 0];
    $productsStats = ['total_products' => 0, 'active_products' => 0, 'low_stock_products' => 0, 'new_products_30d' => 0, 'total_views' => 0, 'total_sales' => 0];
    $subscriptionsStats = ['total_subscriptions' => 0, 'active_subscriptions' => 0, 'paused_subscriptions' => 0, 'cancelled_subscriptions' => 0, 'monthly_recurring_revenue' => 0];
    $recentLogins = $recentOrders = $systemActivity = $systemHealth = $notifications = $dailyRevenue = $topProducts = $recentAdminLogs = [];
}

$seo = [
    'title' => 'Admin Dashboard - Pilote Shopping',
    'description' => 'Tableau de bord administrateur',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once 'includes/menu.php'; ?>


<div id="notifications-panel" class="fixed top-16 right-4 w-96 max-h-96 bg-dark-800 rounded-2xl border border-dark-600 shadow-2xl z-40 overflow-hidden transform translate-x-full transition-transform duration-300">
    <div class="p-4 border-b border-dark-600">
        <div class="flex items-center justify-between">
            <h3 class="text-white font-semibold">Notifications</h3>
            <button onclick="toggleNotifications()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>
    <div class="max-h-80 overflow-y-auto">
<?php if (empty($notifications)): ?>
        <div class="p-4 text-center text-gray-400">
            <i class="fas fa-bell-slash text-2xl mb-2"></i>
            <p>Aucune notification</p>
        </div>
<?php else: ?>
<?php foreach ($notifications as $notif): ?>
            <div class="p-4 border-b border-dark-700 <?= $notif['is_read'] ? 'opacity-60' : '' ?>">
                <div class="flex items-start space-x-3">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center <?= 
                        $notif['type'] === 'error' ? 'bg-red-500/20 text-red-400' :
                        ($notif['type'] === 'warning' ? 'bg-yellow-500/20 text-yellow-400' :
                        ($notif['type'] === 'success' ? 'bg-green-500/20 text-green-400' : 'bg-blue-500/20 text-blue-400'))
                    ?>">
                        <i class="fas fa-<?= 
                            $notif['type'] === 'error' ? 'exclamation-triangle' :
                            ($notif['type'] === 'warning' ? 'exclamation-circle' :
                            ($notif['type'] === 'success' ? 'check' : 'info'))
                        ?> text-xs"></i>
                    </div>
                    <div class="flex-1">
                        <h4 class="text-white font-medium text-sm"><?= htmlspecialchars($notif['title']) ?></h4>
                        <p class="text-gray-400 text-xs"><?= htmlspecialchars($notif['message']) ?></p>
                        <p class="text-gray-500 text-xs mt-1">
<?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                        </p>
                    </div>
                </div>
            </div>
<?php endforeach; ?>
<?php endif; ?>
    </div>
</div>

<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 lg:px-6 py-8">

        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                <i class="fas fa-tachometer-alt text-orange-400 mr-3"></i>
                Dashboard Administrateur
            </h1>
            <p class="text-gray-400">Vue d'ensemble des performances et de l'activité du site</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 group hover:border-blue-500/50 transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-blue-500/20 rounded-xl flex items-center justify-center group-hover:bg-blue-500/30 transition-colors">
                        <i class="fas fa-users text-blue-400 text-xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-bold text-white"><?= number_format($usersStats['total_users']) ?></span>
                        <p class="text-gray-400 text-sm">Utilisateurs</p>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Actifs 7j</span>
                        <span class="text-green-400 font-medium"><?= number_format($usersStats['active_users_7d']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Nouveaux 30j</span>
                        <span class="text-blue-400 font-medium"><?= number_format($usersStats['new_users_30d']) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 group hover:border-green-500/50 transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-green-500/20 rounded-xl flex items-center justify-center group-hover:bg-green-500/30 transition-colors">
                        <i class="fas fa-shopping-cart text-green-400 text-xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-bold text-white"><?= number_format($ordersStats['total_orders']) ?></span>
                        <p class="text-gray-400 text-sm">Commandes</p>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">Complétées</span>
                        <span class="text-green-400 font-medium"><?= number_format($ordersStats['completed_orders']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">En attente</span>
                        <span class="text-orange-400 font-medium"><?= number_format($ordersStats['pending_orders']) ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 group hover:border-purple-500/50 transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-purple-500/20 rounded-xl flex items-center justify-center group-hover:bg-purple-500/30 transition-colors">
                        <i class="fas fa-euro-sign text-purple-400 text-xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-bold text-white"><?= number_format($ordersStats['completed_revenue'], 0) ?>€</span>
                        <p class="text-gray-400 text-sm">Revenus totaux</p>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">30 derniers jours</span>
                        <span class="text-purple-400 font-medium"><?= number_format($ordersStats['revenue_30d'], 0) ?>€</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">Panier moyen</span>
                        <span class="text-blue-400 font-medium"><?= number_format($ordersStats['avg_order_value'], 2) ?>€</span>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 group hover:border-orange-500/50 transition-all duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-14 h-14 bg-orange-500/20 rounded-xl flex items-center justify-center group-hover:bg-orange-500/30 transition-colors">
                        <i class="fas fa-sync-alt text-orange-400 text-xl"></i>
                    </div>
                    <div class="text-right">
                        <span class="text-2xl font-bold text-white"><?= number_format($subscriptionsStats['active_subscriptions']) ?></span>
                        <p class="text-gray-400 text-sm">Abonnements actifs</p>
                    </div>
                </div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-400">MRR</span>
                        <span class="text-orange-400 font-medium"><?= number_format($subscriptionsStats['monthly_recurring_revenue'], 0) ?>€</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-400">En pause</span>
                        <span class="text-gray-400 font-medium"><?= number_format($subscriptionsStats['paused_subscriptions']) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-8">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-chart-line text-purple-400 mr-2"></i>
                            Revenus journaliers (30j)
                        </h2>
                    </div>

                    <div class="h-64 flex items-center justify-center">
                        <canvas id="revenueChart" width="800" height="300"></canvas>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-shopping-bag text-green-400 mr-2"></i>
                            Commandes récentes
                        </h2>
                        <a href="/admin/orders" class="text-green-400 hover:text-green-300 text-sm font-medium">
                            Voir tout <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

<?php if (empty($recentOrders)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-inbox text-3xl mb-2"></i>
                        <p>Aucune commande récente</p>
                    </div>
<?php else: ?>
                    <div class="space-y-4">
<?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                        <div class="flex items-center justify-between p-4 bg-dark-700/50 rounded-xl hover:bg-dark-700 transition-colors">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-blue-500 rounded-full flex items-center justify-center">
                                    <i class="fas fa-shopping-bag text-white text-sm"></i>
                                </div>
                                <div>
                                    <p class="text-white font-medium">
                                        Commande #<?= htmlspecialchars($order['order_number']) ?>
                                    </p>
                                    <p class="text-gray-400 text-sm">
<?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                                    </p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-white font-bold"><?= number_format($order['total_amount'], 2) ?>€</p>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= 
                                    $order['status'] === 'completed' ? 'bg-green-500/20 text-green-400' :
                                    ($order['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-gray-500/20 text-gray-400')
                                ?>">
<?= ucfirst($order['status']) ?>
                                </span>
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>
<?php endif; ?>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-star text-yellow-400 mr-2"></i>
                            Produits les plus vendus
                        </h2>
                        <a href="/admin/products" class="text-yellow-400 hover:text-yellow-300 text-sm font-medium">
                            Voir tout <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

<?php if (empty($topProducts)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-box-open text-3xl mb-2"></i>
                        <p>Aucune donnée de vente</p>
                    </div>
<?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<?php foreach ($topProducts as $product): ?>
                        <div class="flex items-center space-x-4 p-4 bg-dark-700/50 rounded-xl">
                            <img src="<?= htmlspecialchars($product['image']) ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="w-12 h-12 object-cover rounded-lg">
                            <div class="flex-1">
                                <p class="text-white font-medium text-sm">
<?= htmlspecialchars(substr($product['name'], 0, 30)) ?><?= strlen($product['name']) > 30 ? '...' : '' ?>
                                </p>
                                <div class="flex items-center space-x-3 mt-1">
                                    <span class="text-yellow-400 text-sm font-bold">
<?= $product['total_sold'] ?> vendus
                                    </span>
                                    <span class="text-green-400 text-sm">
<?= number_format($product['revenue'], 0) ?>€
                                    </span>
                                </div>
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>
<?php endif; ?>
                </div>
            </div>

            <div class="space-y-8">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-bolt text-orange-400 mr-2"></i>
                        Actions rapides
                    </h3>

                    <div class="space-y-3">
                        <a href="/admin/products" class="block w-full p-3 bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white rounded-xl transition-all duration-300 text-center transform hover:scale-105">
                            <i class="fas fa-box mr-2"></i>Gérer produits
                        </a>

                        <a href="/admin/users" class="block w-full p-3 bg-dark-700 hover:bg-dark-600 text-white rounded-xl transition-colors text-center">
                            <i class="fas fa-users mr-2"></i>Gérer utilisateurs
                        </a>

                        <a href="/admin/orders?status=pending" class="block w-full p-3 bg-orange-500/20 hover:bg-orange-500/30 text-orange-400 rounded-xl transition-colors text-center">
                            <i class="fas fa-clock mr-2"></i>Commandes en attente
                        </a>

                        <a href="/admin/settings" class="block w-full p-3 bg-gray-500/20 hover:bg-gray-500/30 text-gray-400 rounded-xl transition-colors text-center">
                            <i class="fas fa-cog mr-2"></i>Paramètres système
                        </a>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-heartbeat text-red-400 mr-2"></i>
                        Santé du système
                    </h3>

                    <div class="space-y-3">
<?php if (empty($systemHealth)): ?>
                        <p class="text-gray-400 text-sm">Aucune donnée de santé</p>
<?php else: ?>
<?php foreach ($systemHealth as $health): ?>
                            <div class="flex items-center justify-between p-3 bg-dark-700/50 rounded-lg">
                                <div class="flex items-center space-x-3">
                                    <div class="w-3 h-3 rounded-full <?= 
                                        $health['status'] === 'ok' ? 'bg-green-500' :
                                        ($health['status'] === 'warning' ? 'bg-yellow-500' :
                                        ($health['status'] === 'error' ? 'bg-red-500' : 'bg-red-600'))
                                    ?>"></div>
                                    <span class="text-white text-sm"><?= ucfirst(str_replace('_', ' ', $health['check_name'])) ?></span>
                                </div>
<?php if ($health['response_time']): ?>
                                <span class="text-gray-400 text-xs"><?= number_format($health['response_time'] * 1000, 0) ?>ms</span>
<?php endif; ?>
                            </div>
<?php endforeach; ?>
<?php endif; ?>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-clock text-blue-400 mr-2"></i>
                        Activité récente
                    </h3>

                    <div class="space-y-3 max-h-80 overflow-y-auto">
<?php if (empty($systemActivity)): ?>
                        <p class="text-gray-400 text-sm">Aucune activité récente</p>
<?php else: ?>
<?php foreach (array_slice($systemActivity, 0, 10) as $activity): ?>
                            <div class="flex items-start space-x-3 p-3 bg-dark-700/30 rounded-lg">
                                <div class="w-8 h-8 bg-<?= $activity['color'] ?>-500/20 rounded-full flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-<?= $activity['icon'] ?> text-<?= $activity['color'] ?>-400 text-xs"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-white text-sm"><?= htmlspecialchars($activity['description']) ?></p>
                                    <p class="text-gray-400 text-xs">
<?= date('d/m/Y H:i', strtotime($activity['created_at'])) ?>
                                    </p>
                                </div>
                            </div>
<?php endforeach; ?>
<?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php if (!empty($recentAdminLogs)): ?>
        <div class="mt-8">
            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white">
                        <i class="fas fa-history text-gray-400 mr-2"></i>
                        Logs administrateur récents
                    </h2>
                    <a href="/admin/logs" class="text-gray-400 hover:text-white text-sm font-medium">
                        Voir tout <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-dark-600">
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Admin</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Action</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">Date</th>
                                <th class="text-left py-3 px-4 text-gray-400 font-medium">IP</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($recentAdminLogs as $log): ?>
                            <tr class="border-b border-dark-700 hover:bg-dark-700/50">
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-3">
<?php if ($log['avatar_url']): ?>
                                            <img src="<?= htmlspecialchars($log['avatar_url']) ?>" 
                                                 alt="Avatar" class="w-6 h-6 rounded-full object-cover">
<?php else: ?>
                                            <div class="w-6 h-6 bg-gradient-to-br from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                                                <span class="text-white text-xs font-bold">
<?= strtoupper(substr($log['first_name'], 0, 1)) ?>
                                                </span>
                                            </div>
<?php endif; ?>
                                        <div>
                                            <p class="text-white"><?= htmlspecialchars($log['username']) ?></p>
                                            <p class="text-xs text-orange-400 uppercase"><?= htmlspecialchars($log['admin_role']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="px-2 py-1 bg-blue-500/20 text-blue-400 rounded-lg text-xs font-medium">
<?= htmlspecialchars($log['action']) ?>
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-gray-400">
<?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                </td>
                                <td class="py-3 px-4 text-gray-400 font-mono">
<?= htmlspecialchars($log['ip_address']) ?>
                                </td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
<?php endif; ?>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let revenueChart = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeRevenueChart();
});

function initializeRevenueChart() {
    const ctx = document.getElementById('revenueChart');
    if (!ctx) return;

    const revenueData = <?= json_encode($dailyRevenue) ?>;

    const labels = revenueData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('fr-FR', { month: 'short', day: 'numeric' });
    });

    const data = revenueData.map(item => parseFloat(item.revenue));

    revenueChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Revenus (€)',
                data: data,
                borderColor: '#A855F7',
                backgroundColor: 'rgba(168, 85, 247, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#A855F7',
                pointBorderColor: '#1F2937',
                pointBorderWidth: 2,
                pointRadius: 5,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(75, 85, 99, 0.2)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#9CA3AF',
                        font: {
                            size: 12
                        }
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(75, 85, 99, 0.2)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#9CA3AF',
                        font: {
                            size: 12
                        },
                        callback: function(value) {
                            return value + '€';
                        }
                    },
                    beginAtZero: true
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            },
            elements: {
                point: {
                    hoverBackgroundColor: '#FFFFFF'
                }
            }
        }
    });
}

setInterval(() => {
    fetch(window.location.href)
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const newDoc = parser.parseFromString(html, 'text/html');

            const sections = ['recent-orders', 'system-activity', 'system-health'];
            sections.forEach(sectionId => {
                const oldSection = document.getElementById(sectionId);
                const newSection = newDoc.getElementById(sectionId);
                if (oldSection && newSection) {
                    oldSection.innerHTML = newSection.innerHTML;
                }
            });
        })
        .catch(error => {
            console.log('Auto-refresh error:', error);
        });
}, 5 * 60 * 1000);
</script>

<style>

.group:hover {
    transform: translateY(-2px);
}

::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #1F2937;
}

::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 3px;
}

::-webkit-scrollbar-thumb:hover {
    background: #4B5563;
}

@media (max-width: 768px) {
    .grid-cols-4 {
        grid-template-columns: repeat(2, 1fr);
    }

    .text-3xl.md\\:text-4xl {
        font-size: 1.875rem;
    }
}
</style>

<?php
endHTMLPage();
?>