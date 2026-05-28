<?php
define('API_ACCESS', true);
require_once '../../api/v1/config.php';
require_once '../../includes/import.php';
require_once '../../includes/AuthMiddleware.php';
require_once '../includes/AdminAuthMiddleware.php';

$adminAuth = initAdminAuth();
$adminAuth->requireAdminAuth(['admin', 'super_admin', 'moderator']);

$currentAdmin = $adminAuth->getCurrentAdmin();
$currentUser = $adminAuth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        
    $period = $_GET['period'] ?? '30d';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    
        if (empty($dateFrom) || empty($dateTo)) {
        $endDate = new DateTime();
        $startDate = new DateTime();
        
        switch ($period) {
            case '7d':
                $startDate->modify('-7 days');
                break;
            case '30d':
                $startDate->modify('-30 days');
                break;
            case '90d':
                $startDate->modify('-90 days');
                break;
            case '365d':
                $startDate->modify('-365 days');
                break;
            case 'current_month':
                $startDate = new DateTime('first day of this month');
                break;
            case 'last_month':
                $startDate = new DateTime('first day of last month');
                $endDate = new DateTime('last day of last month');
                break;
            case 'current_year':
                $startDate = new DateTime('first day of January this year');
                break;
            default:
                $startDate->modify('-30 days');
        }
        
        $dateFrom = $startDate->format('Y-m-d');
        $dateTo = $endDate->format('Y-m-d');
    }
    
        
    $generalStatsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
            COUNT(CASE WHEN status = 'refunded' THEN 1 END) as refunded_orders,
            
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_orders,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as payment_pending_orders,
            COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_payments,
            COUNT(CASE WHEN payment_status = 'refunded' THEN 1 END) as refunded_payments,
            
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount END), 0) as completed_revenue,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount END), 0) as paid_revenue,
            COALESCE(SUM(discount_amount), 0) as total_discounts,
            COALESCE(SUM(tax_amount), 0) as total_taxes,
            
            COALESCE(AVG(total_amount), 0) as avg_order_value,
            COALESCE(AVG(CASE WHEN status = 'completed' THEN total_amount END), 0) as avg_completed_order_value,
            
            COUNT(DISTINCT user_id) as unique_customers,
            COUNT(DISTINCT CASE WHEN status = 'completed' THEN user_id END) as paying_customers
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $generalStatsStmt->execute([$dateFrom, $dateTo]);
    $generalStats = $generalStatsStmt->fetch();
    
        
    $dailyStatsStmt = $db->prepare("
        SELECT 
            DATE(created_at) as order_date,
            COUNT(*) as orders_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COALESCE(SUM(total_amount), 0) as daily_revenue,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount END), 0) as completed_revenue,
            COUNT(DISTINCT user_id) as unique_customers,
            COALESCE(AVG(total_amount), 0) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY order_date ASC
    ");
    $dailyStatsStmt->execute([$dateFrom, $dateTo]);
    $dailyStats = $dailyStatsStmt->fetchAll();
    
        
    $topProductsStmt = $db->prepare("
        SELECT 
            p.name as product_name,
            p.slug as product_slug,
            SUM(oi.quantity) as total_quantity,
            COUNT(DISTINCT oi.order_id) as orders_count,
            COALESCE(SUM(oi.total_price), 0) as total_revenue,
            COALESCE(AVG(oi.unit_price), 0) as avg_price
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY p.id, p.name, p.slug
        ORDER BY total_revenue DESC
        LIMIT 10
    ");
    $topProductsStmt->execute([$dateFrom, $dateTo]);
    $topProducts = $topProductsStmt->fetchAll();
    
        
    $topCustomersStmt = $db->prepare("
        SELECT 
            u.username,
            u.first_name,
            u.last_name,
            u.email,
            COUNT(o.id) as orders_count,
            COALESCE(SUM(o.total_amount), 0) as total_spent,
            COALESCE(AVG(o.total_amount), 0) as avg_order_value,
            MAX(o.created_at) as last_order_date
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE DATE(o.created_at) BETWEEN ? AND ?
        GROUP BY u.id, u.username, u.first_name, u.last_name, u.email
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    $topCustomersStmt->execute([$dateFrom, $dateTo]);
    $topCustomers = $topCustomersStmt->fetchAll();
    
        
    $paymentMethodsStmt = $db->prepare("
        SELECT 
            COALESCE(payment_method, 'Non spécifié') as payment_method,
            COUNT(*) as orders_count,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(AVG(total_amount), 0) as avg_order_value,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
            COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as failed_count
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY payment_method
        ORDER BY total_revenue DESC
    ");
    $paymentMethodsStmt->execute([$dateFrom, $dateTo]);
    $paymentMethods = $paymentMethodsStmt->fetchAll();
    
        
    $couponsStatsStmt = $db->prepare("
        SELECT 
            COALESCE(coupon_code, 'Sans coupon') as coupon_code,
            COUNT(*) as orders_count,
            COALESCE(SUM(discount_amount), 0) as total_discount,
            COALESCE(AVG(discount_amount), 0) as avg_discount,
            COALESCE(SUM(total_amount), 0) as total_revenue
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY coupon_code
        ORDER BY total_discount DESC
        LIMIT 10
    ");
    $couponsStatsStmt->execute([$dateFrom, $dateTo]);
    $couponsStats = $couponsStatsStmt->fetchAll();
    
        
    $processingTimeStmt = $db->prepare("
        SELECT 
            COALESCE(AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)), 0) as avg_processing_hours,
            COALESCE(AVG(CASE WHEN status = 'completed' THEN TIMESTAMPDIFF(HOUR, created_at, delivered_at) END), 0) as avg_delivery_hours,
            COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) <= 24 THEN 1 END) as processed_within_24h,
            COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) > 24 AND TIMESTAMPDIFF(HOUR, created_at, updated_at) <= 72 THEN 1 END) as processed_within_72h,
            COUNT(CASE WHEN TIMESTAMPDIFF(HOUR, created_at, updated_at) > 72 THEN 1 END) as processed_over_72h
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        AND status IN ('completed', 'cancelled', 'refunded')
    ");
    $processingTimeStmt->execute([$dateFrom, $dateTo]);
    $processingTime = $processingTimeStmt->fetch();
    
        
    $hourlyStatsStmt = $db->prepare("
        SELECT 
            HOUR(created_at) as hour_of_day,
            COUNT(*) as orders_count,
            COALESCE(SUM(total_amount), 0) as hourly_revenue,
            COALESCE(AVG(total_amount), 0) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY HOUR(created_at)
        ORDER BY hour_of_day ASC
    ");
    $hourlyStatsStmt->execute([$dateFrom, $dateTo]);
    $hourlyStats = $hourlyStatsStmt->fetchAll();
    
    $weeklyStatsStmt = $db->prepare("
        SELECT 
            DAYOFWEEK(created_at) as day_of_week,
            DAYNAME(created_at) as day_name,
            COUNT(*) as orders_count,
            COALESCE(SUM(total_amount), 0) as daily_revenue,
            COALESCE(AVG(total_amount), 0) as avg_order_value
        FROM orders 
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DAYOFWEEK(created_at), DAYNAME(created_at)
        ORDER BY day_of_week ASC
    ");
    $weeklyStatsStmt->execute([$dateFrom, $dateTo]);
    $weeklyStats = $weeklyStatsStmt->fetchAll();
    
        $conversionRates = [
        'order_completion' => $generalStats['total_orders'] > 0 ? ($generalStats['completed_orders'] / $generalStats['total_orders']) * 100 : 0,
        'payment_success' => $generalStats['total_orders'] > 0 ? ($generalStats['paid_orders'] / $generalStats['total_orders']) * 100 : 0,
        'customer_retention' => $generalStats['unique_customers'] > 0 ? ($generalStats['paying_customers'] / $generalStats['unique_customers']) * 100 : 0
    ];
    
} catch (Exception $e) {
    error_log("Admin orders stats error: " . $e->getMessage());
    $error = "Erreur lors du chargement des statistiques";
        $generalStats = ['total_orders' => 0, 'total_revenue' => 0, 'avg_order_value' => 0];
    $dailyStats = [];
    $topProducts = [];
    $topCustomers = [];
    $paymentMethods = [];
    $couponsStats = [];
    $processingTime = ['avg_processing_hours' => 0, 'avg_delivery_hours' => 0];
    $hourlyStats = [];
    $weeklyStats = [];
    $conversionRates = ['order_completion' => 0, 'payment_success' => 0, 'customer_retention' => 0];
}

$seo = [
    'title' => 'Statistiques des commandes - Admin Pilote Shopping',
    'description' => 'Analyses et statistiques détaillées des commandes',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 lg:px-6 py-8">

        <div class="mb-6">
            <nav class="flex items-center space-x-2 text-sm text-gray-400">
                <a href="/admin/" class="hover:text-white transition-colors">Admin</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <a href="/admin/orders/" class="hover:text-white transition-colors">Commandes</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <span class="text-white">Statistiques</span>
            </nav>
        </div>

        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                        <i class="fas fa-chart-line text-purple-400 mr-3"></i>
                        Statistiques des commandes
                    </h1>
                    <p class="text-gray-400">
                        Analyses détaillées pour la période du <?= date('d/m/Y', strtotime($dateFrom)) ?> au <?= date('d/m/Y', strtotime($dateTo)) ?>
                    </p>
                </div>

                <div class="flex items-center space-x-4">
                    <form method="GET" class="flex items-center space-x-3">
                        <select name="period" onchange="this.form.submit()"
                                class="bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="7d" <?= $period === '7d' ? 'selected' : '' ?>>7 derniers jours</option>
                            <option value="30d" <?= $period === '30d' ? 'selected' : '' ?>>30 derniers jours</option>
                            <option value="90d" <?= $period === '90d' ? 'selected' : '' ?>>90 derniers jours</option>
                            <option value="365d" <?= $period === '365d' ? 'selected' : '' ?>>365 derniers jours</option>
                            <option value="current_month" <?= $period === 'current_month' ? 'selected' : '' ?>>Mois actuel</option>
                            <option value="last_month" <?= $period === 'last_month' ? 'selected' : '' ?>>Mois dernier</option>
                            <option value="current_year" <?= $period === 'current_year' ? 'selected' : '' ?>>Année actuelle</option>
                            <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>Personnalisé</option>
                        </select>

                        <div id="custom-dates" class="flex items-center space-x-2 <?= $period !== 'custom' ? 'hidden' : '' ?>">
                            <input type="date" name="date_from" value="<?= $dateFrom ?>"
                                   class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500">
                            <span class="text-gray-400">à</span>
                            <input type="date" name="date_to" value="<?= $dateTo ?>"
                                   class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:border-blue-500">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

<?php if (isset($error)): ?>
        <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-xl">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-8">
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-blue-400 text-2xl font-bold"><?= number_format($generalStats['total_orders']) ?></div>
                <div class="text-gray-400 text-sm">Total commandes</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-green-400 text-2xl font-bold"><?= number_format($generalStats['completed_orders']) ?></div>
                <div class="text-gray-400 text-sm">Terminées</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-purple-400 text-2xl font-bold"><?= number_format($generalStats['total_revenue'], 0) ?>€</div>
                <div class="text-gray-400 text-sm">CA total</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-cyan-400 text-2xl font-bold"><?= number_format($generalStats['avg_order_value'], 0) ?>€</div>
                <div class="text-gray-400 text-sm">Panier moyen</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-yellow-400 text-2xl font-bold"><?= number_format($generalStats['unique_customers']) ?></div>
                <div class="text-gray-400 text-sm">Clients uniques</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-orange-400 text-2xl font-bold"><?= number_format($conversionRates['order_completion'], 1) ?>%</div>
                <div class="text-gray-400 text-sm">Taux finalisation</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-emerald-400 text-2xl font-bold"><?= number_format($conversionRates['payment_success'], 1) ?>%</div>
                <div class="text-gray-400 text-sm">Paiements réussis</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-red-400 text-2xl font-bold"><?= number_format($generalStats['total_discounts'], 0) ?>€</div>
                <div class="text-gray-400 text-sm">Réductions totales</div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h3 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-chart-area text-blue-400 mr-2"></i>
                    Évolution quotidienne
                </h3>

                <canvas id="dailyChart" width="400" height="200"></canvas>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h3 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-chart-pie text-green-400 mr-2"></i>
                    Répartition par statut
                </h3>

                <canvas id="statusChart" width="400" height="200"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h3 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-clock text-purple-400 mr-2"></i>
                    Commandes par heure
                </h3>

                <canvas id="hourlyChart" width="400" height="200"></canvas>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h3 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-calendar text-orange-400 mr-2"></i>
                    Commandes par jour
                </h3>

                <canvas id="weeklyChart" width="400" height="200"></canvas>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 overflow-hidden">
                <div class="p-6 border-b border-dark-600">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-trophy text-yellow-400 mr-2"></i>
                        Top produits
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-dark-700/50">
                            <tr>
                                <th class="text-left py-3 px-4 text-gray-300 text-sm">Produit</th>
                                <th class="text-left py-3 px-4 text-gray-300 text-sm">Quantité</th>
                                <th class="text-left py-3 px-4 text-gray-300 text-sm">CA</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($topProducts as $product): ?>
                            <tr class="border-t border-dark-600">
                                <td class="py-3 px-4">
                                    <div class="text-white font-medium"><?= htmlspecialchars($product['product_name']) ?></div>
                                    <div class="text-gray-400 text-sm"><?= $product['orders_count'] ?> commande(s)</div>
                                </td>
                                <td class="py-3 px-4 text-white"><?= number_format($product['total_quantity']) ?></td>
                                <td class="py-3 px-4 text-green-400 font-bold"><?= number_format($product['total_revenue'], 2) ?>€</td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 overflow-hidden">
                <div class="p-6 border-b border-dark-600">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-users text-blue-400 mr-2"></i>
                        Top clients
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-dark-700/50">
                            <tr>
                                <th class="text-left py-3 px-4 text-gray-300 text-sm">Client</th>
                                <th class="text-left py-3 px-4 text-gray-300 text-sm">Commandes</th>
                                <th class="text-left py-3 px-4 text-gray-300 text-sm">Total</th>
                            </tr>
                        </thead>
                        <tbody>
<?php foreach ($topCustomers as $customer): ?>
                            <tr class="border-t border-dark-600">
                                <td class="py-3 px-4">
                                    <div class="text-white font-medium"><?= htmlspecialchars($customer['username']) ?></div>
                                    <div class="text-gray-400 text-sm"><?= htmlspecialchars($customer['first_name'] . ' ' . $customer['last_name']) ?></div>
                                </td>
                                <td class="py-3 px-4 text-white"><?= $customer['orders_count'] ?></td>
                                <td class="py-3 px-4 text-green-400 font-bold"><?= number_format($customer['total_spent'], 2) ?>€</td>
                            </tr>
<?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 overflow-hidden">
                <div class="p-6 border-b border-dark-600">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-credit-card text-green-400 mr-2"></i>
                        Méthodes de paiement
                    </h3>
                </div>

                <div class="p-6">
<?php foreach ($paymentMethods as $method): ?>
                    <div class="flex items-center justify-between py-3 border-b border-dark-600 last:border-b-0">
                        <div>
                            <div class="text-white font-medium"><?= htmlspecialchars($method['payment_method']) ?></div>
                            <div class="text-gray-400 text-sm">
<?= $method['orders_count'] ?> commandes • 
<?= $method['paid_count'] ?> payées • 
<?= $method['failed_count'] ?> échouées
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-green-400 font-bold"><?= number_format($method['total_revenue'], 2) ?>€</div>
                            <div class="text-gray-400 text-sm"><?= number_format($method['avg_order_value'], 2) ?>€ moy.</div>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 overflow-hidden">
                <div class="p-6 border-b border-dark-600">
                    <h3 class="text-xl font-bold text-white">
                        <i class="fas fa-ticket-alt text-yellow-400 mr-2"></i>
                        Utilisation des coupons
                    </h3>
                </div>

                <div class="p-6">
<?php foreach (array_slice($couponsStats, 0, 8) as $coupon): ?>
                    <div class="flex items-center justify-between py-3 border-b border-dark-600 last:border-b-0">
                        <div>
                            <div class="text-white font-medium">
<?= $coupon['coupon_code'] === 'Sans coupon' ? 'Sans coupon' : htmlspecialchars($coupon['coupon_code']) ?>
                            </div>
                            <div class="text-gray-400 text-sm"><?= $coupon['orders_count'] ?> utilisation(s)</div>
                        </div>
                        <div class="text-right">
                            <div class="text-red-400 font-bold">-<?= number_format($coupon['total_discount'], 2) ?>€</div>
                            <div class="text-gray-400 text-sm">-<?= number_format($coupon['avg_discount'], 2) ?>€ moy.</div>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 mb-8">
            <h3 class="text-xl font-bold text-white mb-6">
                <i class="fas fa-stopwatch text-red-400 mr-2"></i>
                Analyse des temps de traitement
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-6">
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-400 mb-2">
<?= number_format($processingTime['avg_processing_hours'], 1) ?>h
                    </div>
                    <div class="text-gray-400 text-sm">Temps de traitement moyen</div>
                </div>

                <div class="text-center">
                    <div class="text-3xl font-bold text-green-400 mb-2">
<?= number_format($processingTime['avg_delivery_hours'], 1) ?>h
                    </div>
                    <div class="text-gray-400 text-sm">Temps de livraison moyen</div>
                </div>

                <div class="text-center">
                    <div class="text-3xl font-bold text-emerald-400 mb-2">
<?= number_format($processingTime['processed_within_24h']) ?>
                    </div>
                    <div class="text-gray-400 text-sm">Traitées en -24h</div>
                </div>

                <div class="text-center">
                    <div class="text-3xl font-bold text-yellow-400 mb-2">
<?= number_format($processingTime['processed_within_72h']) ?>
                    </div>
                    <div class="text-gray-400 text-sm">Traitées en 24-72h</div>
                </div>

                <div class="text-center">
                    <div class="text-3xl font-bold text-red-400 mb-2">
<?= number_format($processingTime['processed_over_72h']) ?>
                    </div>
                    <div class="text-gray-400 text-sm">Traitées en +72h</div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
Chart.defaults.color = '#9CA3AF';
Chart.defaults.borderColor = '#374151';

const dailyData = <?= json_encode($dailyStats) ?>;
const hourlyData = <?= json_encode($hourlyStats) ?>;
const weeklyData = <?= json_encode($weeklyStats) ?>;
const generalStats = <?= json_encode($generalStats) ?>;

const dailyCtx = document.getElementById('dailyChart').getContext('2d');
new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: dailyData.map(d => new Date(d.order_date).toLocaleDateString('fr-FR')),
        datasets: [{
            label: 'Commandes',
            data: dailyData.map(d => d.orders_count),
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            yAxisID: 'y'
        }, {
            label: 'Chiffre d\'affaires (€)',
            data: dailyData.map(d => d.daily_revenue),
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        interaction: {
            mode: 'index',
            intersect: false,
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {
                    display: true,
                    text: 'Nombre de commandes'
                }
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {
                    display: true,
                    text: 'Chiffre d\'affaires (€)'
                },
                grid: {
                    drawOnChartArea: false,
                }
            }
        },
        plugins: {
            legend: {
                position: 'top'
            }
        }
    }
});

const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Terminées', 'En attente', 'En cours', 'Annulées', 'Remboursées'],
        datasets: [{
            data: [
                generalStats.completed_orders,
                generalStats.pending_orders,
                generalStats.processing_orders,
                generalStats.cancelled_orders,
                generalStats.refunded_orders
            ],
            backgroundColor: [
                '#10B981',
                '#F59E0B',
                '#3B82F6',
                '#EF4444',
                '#8B5CF6'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
new Chart(hourlyCtx, {
    type: 'bar',
    data: {
        labels: Array.from({length: 24}, (_, i) => `${i}h`),
        datasets: [{
            label: 'Commandes',
            data: Array.from({length: 24}, (_, i) => {
                const hourData = hourlyData.find(h => h.hour_of_day == i);
                return hourData ? hourData.orders_count : 0;
            }),
            backgroundColor: 'rgba(168, 85, 247, 0.7)',
            borderColor: '#A855F7',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Nombre de commandes'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Heure de la journée'
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

const weeklyCtx = document.getElementById('weeklyChart').getContext('2d');
new Chart(weeklyCtx, {
    type: 'bar',
    data: {
        labels: ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'],
        datasets: [{
            label: 'Commandes',
            data: Array.from({length: 7}, (_, i) => {
                const dayData = weeklyData.find(d => d.day_of_week == (i + 1));
                return dayData ? dayData.orders_count : 0;
            }),
            backgroundColor: 'rgba(251, 146, 60, 0.7)',
            borderColor: '#FB923C',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                title: {
                    display: true,
                    text: 'Nombre de commandes'
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

document.querySelector('select[name="period"]').addEventListener('change', function() {
    const customDates = document.getElementById('custom-dates');
    if (this.value === 'custom') {
        customDates.classList.remove('hidden');
    } else {
        customDates.classList.add('hidden');
        this.form.submit();
    }
});
</script>

<style>

canvas {
    max-height: 400px;
}

.overflow-x-auto {
    scrollbar-width: thin;
    scrollbar-color: #374151 #1F2937;
}

.overflow-x-auto::-webkit-scrollbar {
    height: 6px;
}

.overflow-x-auto::-webkit-scrollbar-track {
    background: #1F2937;
}

.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 3px;
}

@media (max-width: 768px) {
    .grid.grid-cols-2.md\\:grid-cols-4.lg\\:grid-cols-8 {
        grid-template-columns: repeat(2, 1fr);
    }

    .grid.grid-cols-1.lg\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }
}

.bg-dark-800\\/50 {
    transition: all 0.3s ease;
}

.bg-dark-800\\/50:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}
</style>

<?php
endHTMLPage();
?>