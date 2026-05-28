<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$auth->requireAuth();
$currentUser = $auth->getCurrentUser();

$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$payment_status = $_GET['payment_status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

try {
    $db = Database::getInstance();
    
        $where_conditions = ['o.user_id = ?'];
    $params = [$currentUser['id']];
    
    if ($search) {
        $where_conditions[] = '(o.order_number LIKE ? OR oi.product_name LIKE ?)';
        $params[] = "%" . escapeLike($search) . "%";
        $params[] = "%" . escapeLike($search) . "%";
    }
    
    if ($status) {
        $where_conditions[] = 'o.status = ?';
        $params[] = $status;
    }
    
    if ($payment_status) {
        $where_conditions[] = 'o.payment_status = ?';
        $params[] = $payment_status;
    }
    
    if ($date_from) {
        $where_conditions[] = 'DATE(o.created_at) >= ?';
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $where_conditions[] = 'DATE(o.created_at) <= ?';
        $params[] = $date_to;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
        $count_stmt = $db->prepare("
        SELECT COUNT(DISTINCT o.id) as total
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        $where_clause
    ");
    $count_stmt->execute($params);
    $total_orders = $count_stmt->fetchColumn();
    $total_pages = ceil($total_orders / $limit);
    
        $orders_stmt = $db->prepare("
        SELECT o.*, 
               GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ', ') as products,
               COUNT(DISTINCT oi.id) as items_count,
               COUNT(DISTINCT s.id) as subscriptions_count,
               pa.name as payment_method_name,
               CASE 
                   WHEN o.payment_status = 'pending' THEN 1
                   ELSE 0
               END as needs_payment
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN subscriptions s ON oi.id = s.order_item_id
        LEFT JOIN payment_transactions pt ON o.id = pt.order_id
        LEFT JOIN payment_adapters pa ON pt.payment_adapter_id = pa.id
        $where_clause
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT $limit OFFSET $offset
    ");
    $orders_stmt->execute($params);
    $orders = $orders_stmt->fetchAll();
    
        $stats_stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status IN ('pending', 'processing') THEN 1 ELSE 0 END) as pending_orders,
            SUM(CASE WHEN payment_status = 'pending' THEN 1 ELSE 0 END) as payment_pending,
            SUM(total_amount) as total_spent
        FROM orders 
        WHERE user_id = ?
    ");
    $stats_stmt->execute([$currentUser['id']]);
    $stats = $stats_stmt->fetch();
    
} catch (Exception $e) {
    error_log("Orders list error: " . $e->getMessage());
    $orders = [];
    $total_orders = 0;
    $total_pages = 1;
    $stats = [
        'total_orders' => 0,
        'pending_orders' => 0,
        'payment_pending' => 0,
        'total_spent' => 0
    ];
}

$seo = [
    'title' => 'Mes commandes - Pilote Shopping',
    'description' => 'Consultez l\'historique de vos commandes et suivez leur statut.',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen bg-dark-900 py-8">
    <div class="container mx-auto px-4 lg:px-6">

        <nav class="mb-8">
            <div class="flex items-center space-x-2 text-sm">
                <a href="/dashboard" class="text-gray-400 hover:text-white transition-colors">Dashboard</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <span class="text-white font-medium">Mes commandes</span>
            </div>
        </nav>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    <i class="fas fa-shopping-bag text-blue-primary mr-3"></i>
                    Mes commandes
                </h1>
                <p class="text-gray-400">Gérez et consultez l'historique de vos commandes</p>
            </div>

            <div class="mt-6 lg:mt-0 flex flex-wrap gap-4">
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 px-4 py-3">
                    <div class="text-2xl font-bold text-blue-primary"><?= $stats['total_orders'] ?></div>
                    <div class="text-sm text-gray-400">Total commandes</div>
                </div>

<?php if ($stats['pending_orders'] > 0): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 px-4 py-3">
                    <div class="text-2xl font-bold text-orange-400"><?= $stats['pending_orders'] ?></div>
                    <div class="text-sm text-gray-400">En cours</div>
                </div>
<?php endif; ?>

<?php if ($stats['payment_pending'] > 0): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 px-4 py-3">
                    <div class="text-2xl font-bold text-red-400"><?= $stats['payment_pending'] ?></div>
                    <div class="text-sm text-gray-400">Paiement requis</div>
                </div>
<?php endif; ?>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 px-4 py-3">
                    <div class="text-2xl font-bold text-green-400"><?= number_format($stats['total_spent'], 0) ?>€</div>
                    <div class="text-sm text-gray-400">Total dépensé</div>
                </div>
            </div>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 mb-8">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">

                <div class="lg:col-span-2">
                    <label class="block text-sm font-medium text-gray-300 mb-2">Recherche</label>
                    <input type="text" 
                           name="search" 
                           value="<?= htmlspecialchars($search) ?>"
                           placeholder="Numéro de commande ou produit..."
                           class="w-full px-4 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Statut</label>
                    <select name="status" class="w-full px-4 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-blue-primary transition-colors">
                        <option value="">Tous les statuts</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>En traitement</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Terminée</option>
                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Annulée</option>
                        <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Remboursée</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Paiement</label>
                    <select name="payment_status" class="w-full px-4 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-blue-primary transition-colors">
                        <option value="">Tous</option>
                        <option value="pending" <?= $payment_status === 'pending' ? 'selected' : '' ?>>En attente</option>
                        <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Payé</option>
                        <option value="failed" <?= $payment_status === 'failed' ? 'selected' : '' ?>>Échoué</option>
                        <option value="refunded" <?= $payment_status === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Du</label>
                    <input type="date" 
                           name="date_from" 
                           value="<?= htmlspecialchars($date_from) ?>"
                           class="w-full px-4 py-2 bg-dark-700 border border-dark-600 rounded-lg text-white focus:outline-none focus:border-blue-primary transition-colors">
                </div>

                <div class="flex items-end space-x-2">
                    <button type="submit" 
                            class="flex-1 bg-blue-primary hover:bg-blue-secondary text-white font-medium py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-search mr-2"></i>Filtrer
                    </button>
                    <a href="/orders" 
                       class="bg-dark-700 hover:bg-dark-600 text-white font-medium py-2 px-4 rounded-lg transition-colors">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>

<?php if (empty($orders)): ?>
            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-8 text-center">
                <div class="w-20 h-20 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-shopping-bag text-blue-primary text-3xl"></i>
                </div>
                <h3 class="text-2xl font-bold text-white mb-4">Aucune commande trouvée</h3>
<?php if ($search || $status || $payment_status || $date_from): ?>
                    <p class="text-gray-400 mb-6">Aucune commande ne correspond à vos critères de recherche.</p>
                    <a href="/orders" class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-6 rounded-xl transition-colors">
                        Voir toutes mes commandes
                    </a>
<?php else: ?>
                    <p class="text-gray-400 mb-6">Vous n'avez pas encore passé de commande.</p>
                    <a href="/products" class="bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white font-bold py-3 px-6 rounded-xl transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-shopping-cart mr-2"></i>Découvrir nos produits
                    </a>
<?php endif; ?>
            </div>
<?php else: ?>
            <div class="space-y-4">
<?php foreach ($orders as $order): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-blue-primary/50 transition-all duration-300">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex-1">

                            <div class="flex flex-wrap items-center gap-3 mb-3">
                                <h3 class="text-lg font-bold text-white">
                                    Commande #<?= htmlspecialchars($order['order_number']) ?>
                                </h3>

                                <span class="px-3 py-1 rounded-full text-xs font-medium <?= 
                                    $order['status'] === 'completed' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
                                    ($order['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400 border border-yellow-500/30' :
                                    ($order['status'] === 'processing' ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' :
                                    ($order['status'] === 'cancelled' ? 'bg-red-500/20 text-red-400 border border-red-500/30' :
                                    'bg-gray-500/20 text-gray-400 border border-gray-500/30')))
                                ?>">
<?= ucfirst($order['status']) ?>
                                </span>

                                <span class="px-3 py-1 rounded-full text-xs font-medium <?= 
                                    $order['payment_status'] === 'paid' ? 'bg-green-500/20 text-green-400 border border-green-500/30' :
                                    ($order['payment_status'] === 'pending' ? 'bg-orange-500/20 text-orange-400 border border-orange-500/30' :
                                    ($order['payment_status'] === 'failed' ? 'bg-red-500/20 text-red-400 border border-red-500/30' :
                                    'bg-gray-500/20 text-gray-400 border border-gray-500/30'))
                                ?>">
                                    Paiement <?= $order['payment_status'] === 'paid' ? 'confirmé' : $order['payment_status'] ?>
                                </span>

<?php if ($order['subscriptions_count'] > 0): ?>
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-purple-500/20 text-purple-400 border border-purple-500/30">
                                    <i class="fas fa-sync-alt mr-1"></i>
<?= $order['subscriptions_count'] ?> abonnement<?= $order['subscriptions_count'] > 1 ? 's' : '' ?>
                                </span>
<?php endif; ?>

<?php if ($order['needs_payment']): ?>
                                <span class="px-3 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/30 animate-pulse">
                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                    Paiement requis
                                </span>
<?php endif; ?>
                            </div>

                            <div class="space-y-2 text-sm">
                                <div class="text-gray-400">
                                    <i class="fas fa-box text-blue-primary mr-2"></i>
<?= $order['items_count'] ?> article<?= $order['items_count'] > 1 ? 's' : '' ?> : 
<?= htmlspecialchars(substr($order['products'], 0, 80)) ?><?= strlen($order['products']) > 80 ? '...' : '' ?>
                                </div>

                                <div class="flex flex-wrap items-center gap-4 text-gray-400">
                                    <span>
                                        <i class="fas fa-calendar text-blue-primary mr-2"></i>
<?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?>
                                    </span>

<?php if ($order['payment_method_name']): ?>
                                    <span>
                                        <i class="fas fa-credit-card text-blue-primary mr-2"></i>
<?= htmlspecialchars($order['payment_method_name']) ?>
                                    </span>
<?php endif; ?>

                                    <span class="font-bold text-white">
                                        <i class="fas fa-euro-sign text-green-400 mr-2"></i>
<?= number_format($order['total_amount'], 2) ?>€
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 lg:mt-0 lg:ml-6 flex flex-col sm:flex-row gap-3">
<?php if ($order['needs_payment']): ?>
                                <a href="/orders/pay?order=<?= $order['id'] ?>" 
                                   class="bg-red-500 hover:bg-red-600 text-white font-medium py-2 px-4 rounded-lg transition-colors text-center">
                                    <i class="fas fa-credit-card mr-2"></i>Payer maintenant
                                </a>
<?php endif; ?>

                            <a href="/orders/detail?order=<?= $order['id'] ?>" 
                               class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-2 px-4 rounded-lg transition-colors text-center">
                                <i class="fas fa-eye mr-2"></i>Voir détails
                            </a>

<?php if ($order['subscriptions_count'] > 0): ?>
                                <a href="/dashboard/subscriptions" 
                                   class="bg-purple-500 hover:bg-purple-600 text-white font-medium py-2 px-4 rounded-lg transition-colors text-center">
                                    <i class="fas fa-cog mr-2"></i>Gérer abonnements
                                </a>
<?php endif; ?>
                        </div>
                    </div>
                </div>
<?php endforeach; ?>
            </div>

<?php if ($total_pages > 1): ?>
            <div class="mt-8 flex justify-center">
                <nav class="flex items-center space-x-2">
<?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="px-3 py-2 bg-dark-700 hover:bg-dark-600 text-white rounded-lg transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
<?php endif; ?>

<?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="px-4 py-2 <?= $i === $page ? 'bg-blue-primary text-white' : 'bg-dark-700 hover:bg-dark-600 text-white' ?> rounded-lg transition-colors">
<?= $i ?>
                        </a>
<?php endfor; ?>

<?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="px-3 py-2 bg-dark-700 hover:bg-dark-600 text-white rounded-lg transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
<?php endif; ?>
                </nav>
            </div>
<?php endif; ?>
<?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function() {
    const needsPaymentElements = document.querySelectorAll('[data-needs-payment="true"]');

    if (needsPaymentElements.length > 0) {

        setInterval(() => {
            window.location.reload();
        }, 30000);
    }
});
</script>

<?php endHTMLPage(); ?>