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
    
        
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? 50);
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $payment_status = $_GET['payment_status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $user_id = intval($_GET['user_id'] ?? 0);
    $sort = $_GET['sort'] ?? 'created_at';
    $order = $_GET['order'] ?? 'DESC';
    
        $page = max(1, $page);
    $limit = min(100, max(10, $limit));
    $offset = ($page - 1) * $limit;
    
    $allowedSorts = ['created_at', 'updated_at', 'order_number', 'total_amount', 'user_id'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
    $order = in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';
    
        
    $whereConditions = [];
    $params = [];
    
        if (!empty($search)) {
        $whereConditions[] = "(o.order_number LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $searchParam = "%" . escapeLike($search) . "%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
        if ($status) {
        $whereConditions[] = "o.status = ?";
        $params[] = $status;
    }
    
        if ($payment_status) {
        $whereConditions[] = "o.payment_status = ?";
        $params[] = $payment_status;
    }
    
        if ($user_id > 0) {
        $whereConditions[] = "o.user_id = ?";
        $params[] = $user_id;
    }
    
        if ($date_from) {
        $whereConditions[] = "DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $whereConditions[] = "DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
        
    $stmt = $db->prepare("
        SELECT o.*, 
               u.username, u.email, u.first_name, u.last_name,
               COUNT(DISTINCT oi.id) as items_count,
               COUNT(DISTINCT s.id) as subscriptions_count,
               pa.name as payment_method_name,
               GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR ', ') as products_list
        FROM orders o
        LEFT JOIN users u ON o.user_id = u.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN subscriptions s ON oi.id = s.order_item_id
        LEFT JOIN payment_transactions pt ON o.id = pt.order_id
        LEFT JOIN payment_adapters pa ON pt.payment_adapter_id = pa.id
        $whereClause
        GROUP BY o.id
        ORDER BY o.$sort $order
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
        
    $countStmt = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereClause");
    $countStmt->execute($params);
    $totalOrders = $countStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $limit);
    
        
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'processing' THEN 1 END) as processing_orders,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
            COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as payment_pending,
            COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as payment_paid,
            COUNT(CASE WHEN payment_status = 'failed' THEN 1 END) as payment_failed,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_24h,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_7d,
            COALESCE(SUM(total_amount), 0) as total_revenue,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount END), 0) as completed_revenue,
            COALESCE(AVG(total_amount), 0) as avg_order_value
        FROM orders
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $orderId = intval($_POST['order_id'] ?? 0);
        
        if ($orderId > 0) {
            try {
                switch ($action) {
                    case 'update_status':
                        $newStatus = $_POST['new_status'] ?? '';
                        $validStatuses = ['pending', 'processing', 'completed', 'cancelled', 'refunded'];
                        
                        if (in_array($newStatus, $validStatuses)) {
                            $updateStmt = $db->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
                            $updateStmt->execute([$newStatus, $orderId]);
                            
                            $adminAuth->logAction('order_status_updated', 'order', $orderId, null, ['new_status' => $newStatus]);
                            $success = "Statut de la commande mis à jour";
                        } else {
                            $error = "Statut invalide";
                        }
                        break;
                        
                    case 'update_payment_status':
                        $newPaymentStatus = $_POST['new_payment_status'] ?? '';
                        $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];
                        
                        if (in_array($newPaymentStatus, $validPaymentStatuses)) {
                            $updateStmt = $db->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
                            $updateStmt->execute([$newPaymentStatus, $orderId]);
                            
                            $adminAuth->logAction('order_payment_status_updated', 'order', $orderId, null, ['new_payment_status' => $newPaymentStatus]);
                            $success = "Statut de paiement mis à jour";
                        } else {
                            $error = "Statut de paiement invalide";
                        }
                        break;
                        
                    case 'add_note':
                        $note = trim($_POST['note'] ?? '');
                        if (!empty($note)) {
                            $updateStmt = $db->prepare("UPDATE orders SET notes = CONCAT(COALESCE(notes, ''), '\n[', NOW(), '] Admin: ', ?) WHERE id = ?");
                            $updateStmt->execute([$note, $orderId]);
                            
                            $adminAuth->logAction('order_note_added', 'order', $orderId, null, ['note' => $note]);
                            $success = "Note ajoutée à la commande";
                        } else {
                            $error = "La note ne peut pas être vide";
                        }
                        break;
                        
                    default:
                        $error = "Action non reconnue";
                }
                
                                if (isset($success)) {
                    header("Location: /admin/orders/?success=" . urlencode($success));
                    exit();
                }
                
            } catch (Exception $e) {
                $error = "Erreur lors de l'action: " . $e->getMessage();
                $adminAuth->logAction('order_action_failed', 'order', $orderId, null, ['error' => $e->getMessage()], 'critical');
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Admin orders error: " . $e->getMessage());
    $error = "Erreur lors du chargement des commandes";
    $orders = [];
    $stats = [
        'total_orders' => 0, 'pending_orders' => 0, 'processing_orders' => 0, 'completed_orders' => 0,
        'cancelled_orders' => 0, 'payment_pending' => 0, 'payment_paid' => 0, 'payment_failed' => 0,
        'new_24h' => 0, 'new_7d' => 0, 'total_revenue' => 0, 'completed_revenue' => 0, 'avg_order_value' => 0
    ];
    $totalOrders = 0;
    $totalPages = 1;
}

$seo = [
    'title' => 'Gestion des commandes - Admin Pilote Shopping',
    'description' => 'Administration des commandes',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 lg:px-6 py-8">

        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                        <i class="fas fa-shopping-bag text-green-400 mr-3"></i>
                        Gestion des commandes
                    </h1>
                    <p class="text-gray-400">Administration et suivi des commandes clients</p>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="/admin/orders/export.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                        <i class="fas fa-download mr-2"></i>Exporter
                    </a>
                </div>
            </div>
        </div>

<?php if (isset($_GET['success'])): ?>
        <div class="mb-6 bg-green-500/20 border border-green-500/50 text-green-300 px-4 py-3 rounded-xl">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($_GET['success']) ?>
        </div>
<?php endif; ?>

<?php if (isset($error)): ?>
        <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-xl">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-9 gap-4 mb-8">
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-blue-400 text-2xl font-bold"><?= number_format($stats['total_orders']) ?></div>
                <div class="text-gray-400 text-sm">Total</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-yellow-400 text-2xl font-bold"><?= number_format($stats['pending_orders']) ?></div>
                <div class="text-gray-400 text-sm">En attente</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-orange-400 text-2xl font-bold"><?= number_format($stats['processing_orders']) ?></div>
                <div class="text-gray-400 text-sm">En cours</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-green-400 text-2xl font-bold"><?= number_format($stats['completed_orders']) ?></div>
                <div class="text-gray-400 text-sm">Terminées</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-red-400 text-2xl font-bold"><?= number_format($stats['cancelled_orders']) ?></div>
                <div class="text-gray-400 text-sm">Annulées</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-purple-400 text-2xl font-bold"><?= number_format($stats['payment_pending']) ?></div>
                <div class="text-gray-400 text-sm">Paiement en attente</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-cyan-400 text-2xl font-bold"><?= number_format($stats['new_24h']) ?></div>
                <div class="text-gray-400 text-sm">Nouvelles 24h</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-emerald-400 text-2xl font-bold"><?= number_format($stats['total_revenue'], 0) ?>€</div>
                <div class="text-gray-400 text-sm">CA total</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-teal-400 text-2xl font-bold"><?= number_format($stats['avg_order_value'], 0) ?>€</div>
                <div class="text-gray-400 text-sm">Panier moyen</div>
            </div>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-7 gap-4">

                    <div class="lg:col-span-2">
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Rechercher (numéro, client, email...)" 
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <select name="status" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Tous les statuts</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>En cours</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Terminées</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Annulées</option>
                            <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Remboursées</option>
                        </select>
                    </div>

                    <div>
                        <select name="payment_status" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Tous paiements</option>
                            <option value="pending" <?= $payment_status === 'pending' ? 'selected' : '' ?>>En attente</option>
                            <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Payé</option>
                            <option value="failed" <?= $payment_status === 'failed' ? 'selected' : '' ?>>Échoué</option>
                            <option value="refunded" <?= $payment_status === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                        </select>
                    </div>

                    <div>
                        <input type="date" 
                               name="date_from" 
                               value="<?= htmlspecialchars($date_from) ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <input type="date" 
                               name="date_to" 
                               value="<?= htmlspecialchars($date_to) ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i>Rechercher
                        </button>
                        <a href="/admin/orders/" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-gray-400 text-sm">Trier par</span>
                            <select name="sort" class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-1 text-white text-sm focus:outline-none focus:border-blue-500">
                                <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date création</option>
                                <option value="updated_at" <?= $sort === 'updated_at' ? 'selected' : '' ?>>Dernière MàJ</option>
                                <option value="order_number" <?= $sort === 'order_number' ? 'selected' : '' ?>>Numéro</option>
                                <option value="total_amount" <?= $sort === 'total_amount' ? 'selected' : '' ?>>Montant</option>
                            </select>
                            <select name="order" class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-1 text-white text-sm focus:outline-none focus:border-blue-500">
                                <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>↓</option>
                                <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>↑</option>
                            </select>
                        </div>

                        <div class="flex items-center space-x-2">
                            <span class="text-gray-400 text-sm">Afficher</span>
                            <select name="limit" onchange="this.form.submit()" class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-1 text-white text-sm focus:outline-none focus:border-blue-500">
                                <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                            </select>
                            <span class="text-gray-400 text-sm">résultats</span>
                        </div>
                    </div>

                    <div class="text-gray-400 text-sm">
<?= number_format($totalOrders) ?> commande(s) trouvée(s)
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 overflow-hidden">

<?php if (empty($orders)): ?>
            <div class="p-12 text-center text-gray-400">
                <i class="fas fa-shopping-bag text-6xl mb-4 opacity-50"></i>
                <p class="text-xl">Aucune commande trouvée</p>
                <p class="text-sm mt-2">Essayez de modifier vos critères de recherche</p>
            </div>
<?php else: ?>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-dark-700/50">
                        <tr>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Commande</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Client</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Statuts</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Produits</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Montant</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Date</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($orders as $order): ?>
                        <tr class="border-t border-dark-600 hover:bg-dark-700/30 transition-colors">

                            <td class="py-4 px-6">
                                <div>
                                    <a href="/admin/orders/order.php?id=<?= $order['id'] ?>" class="text-white font-medium hover:text-blue-400 transition-colors">
                                        #<?= htmlspecialchars($order['order_number']) ?>
                                    </a>
                                    <p class="text-gray-400 text-sm">
<?= $order['items_count'] ?> article(s)
<?php if ($order['subscriptions_count'] > 0): ?>
                                        • <?= $order['subscriptions_count'] ?> abonnement(s)
<?php endif; ?>
                                    </p>
<?php if ($order['payment_method_name']): ?>
                                    <p class="text-gray-500 text-xs">
                                        <i class="fas fa-credit-card mr-1"></i>
<?= htmlspecialchars($order['payment_method_name']) ?>
                                    </p>
<?php endif; ?>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div>
                                    <a href="/admin/users/user.php?uuid=<?= htmlspecialchars($order['username']) ?>" class="text-white font-medium hover:text-blue-400 transition-colors">
<?= htmlspecialchars($order['username']) ?>
                                    </a>
                                    <p class="text-gray-400 text-sm">
<?= htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) ?>
                                    </p>
                                    <p class="text-gray-500 text-xs">
<?= htmlspecialchars($order['email']) ?>
                                    </p>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="space-y-2">
                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= 
                                        $order['status'] === 'completed' ? 'bg-green-500/20 text-green-400' :
                                        ($order['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                                        ($order['status'] === 'processing' ? 'bg-blue-500/20 text-blue-400' :
                                        ($order['status'] === 'cancelled' ? 'bg-red-500/20 text-red-400' :
                                        'bg-gray-500/20 text-gray-400')))
                                    ?>">
<?= ucfirst($order['status']) ?>
                                    </span>

                                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= 
                                        $order['payment_status'] === 'paid' ? 'bg-green-500/20 text-green-400' :
                                        ($order['payment_status'] === 'pending' ? 'bg-orange-500/20 text-orange-400' :
                                        ($order['payment_status'] === 'failed' ? 'bg-red-500/20 text-red-400' :
                                        'bg-gray-500/20 text-gray-400'))
                                    ?>">
<?= $order['payment_status'] === 'paid' ? 'Payé' : ucfirst($order['payment_status']) ?>
                                    </span>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="text-sm text-gray-300">
<?= htmlspecialchars(substr($order['products_list'], 0, 50)) ?><?= strlen($order['products_list']) > 50 ? '...' : '' ?>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="text-white font-bold">
<?= number_format($order['total_amount'], 2) ?>€
                                </div>
<?php if ($order['discount_amount'] > 0): ?>
                                <div class="text-green-400 text-xs">
                                    -<?= number_format($order['discount_amount'], 2) ?>€
                                </div>
<?php endif; ?>
                            </td>

                            <td class="py-4 px-6">
                                <div class="text-sm">
                                    <div class="text-white">
<?= date('d/m/Y', strtotime($order['created_at'])) ?>
                                    </div>
                                    <div class="text-gray-400">
<?= date('H:i', strtotime($order['created_at'])) ?>
                                    </div>
<?php if ($order['updated_at'] !== $order['created_at']): ?>
                                    <div class="text-gray-500 text-xs">
                                        MàJ: <?= date('d/m H:i', strtotime($order['updated_at'])) ?>
                                    </div>
<?php endif; ?>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-2">
                                    <a href="/admin/orders/order.php?id=<?= $order['id'] ?>" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-sm transition-colors"
                                       title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <div class="relative group">
                                        <button class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded-lg text-sm transition-colors"
                                                onclick="toggleActionMenu(<?= $order['id'] ?>)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>

                                        <div id="action-menu-<?= $order['id'] ?>" class="hidden absolute right-0 mt-2 w-48 bg-dark-700 rounded-lg border border-dark-600 shadow-xl z-50">
                                            <div class="py-2">
                                                <a href="/admin/orders/edit.php?id=<?= $order['id'] ?>" 
                                                   class="block px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                                    <i class="fas fa-edit mr-2"></i>Éditer
                                                </a>

                                                <button onclick="showStatusModal(<?= $order['id'] ?>, '<?= htmlspecialchars($order['status']) ?>')" 
                                                        class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                                    <i class="fas fa-edit mr-2"></i>Changer statut
                                                </button>

                                                <button onclick="showNoteModal(<?= $order['id'] ?>)" 
                                                        class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                                    <i class="fas fa-sticky-note mr-2"></i>Ajouter note
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
<?php endforeach; ?>
                    </tbody>
                </table>
            </div>

<?php if ($totalPages > 1): ?>
            <div class="bg-dark-700/50 px-6 py-4 border-t border-dark-600">
                <div class="flex items-center justify-between">
                    <div class="text-gray-400 text-sm">
                        Page <?= $page ?> sur <?= $totalPages ?> (<?= number_format($totalOrders) ?> résultats)
                    </div>

                    <div class="flex items-center space-x-2">
<?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" 
                           class="bg-dark-600 hover:bg-dark-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-chevron-left"></i>
                        </a>
<?php endif; ?>

<?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="px-3 py-2 rounded-lg text-sm transition-colors <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-dark-600 hover:bg-dark-500 text-gray-300' ?>">
<?= $i ?>
                        </a>
<?php endfor; ?>

<?php if ($page < $totalPages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" 
                           class="bg-dark-600 hover:bg-dark-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                            <i class="fas fa-chevron-right"></i>
                        </a>
<?php endif; ?>
                    </div>
                </div>
            </div>
<?php endif; ?>

<?php endif; ?>
        </div>
    </div>
</main>

<div id="status-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Changer le statut</h3>
            <button onclick="hideStatusModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="status-form">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="order_id" id="status-order-id">

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Nouveau statut</label>
                <select name="new_status" id="status-select" required class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    <option value="pending">En attente</option>
                    <option value="processing">En cours</option>
                    <option value="completed">Terminée</option>
                    <option value="cancelled">Annulée</option>
                    <option value="refunded">Remboursée</option>
                </select>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Confirmer
                </button>
                <button type="button" onclick="hideStatusModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<div id="note-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Ajouter une note</h3>
            <button onclick="hideNoteModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" id="note-form">
            <input type="hidden" name="action" value="add_note">
            <input type="hidden" name="order_id" id="note-order-id">

            <div class="mb-4">
                <label class="block text-gray-400 text-sm mb-2">Note admin</label>
                <textarea name="note" rows="4" required placeholder="Ajoutez une note à cette commande..."
                          class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="flex space-x-3">
                <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Ajouter
                </button>
                <button type="button" onclick="hideNoteModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>

function toggleActionMenu(orderId) {
    const menu = document.getElementById('action-menu-' + orderId);
    const allMenus = document.querySelectorAll('[id^="action-menu-"]');

    allMenus.forEach(m => {
        if (m !== menu) {
            m.classList.add('hidden');
        }
    });

    menu.classList.toggle('hidden');
}

function showStatusModal(orderId, currentStatus) {
    document.getElementById('status-order-id').value = orderId;
    document.getElementById('status-select').value = currentStatus;
    document.getElementById('status-modal').classList.remove('hidden');
}

function hideStatusModal() {
    document.getElementById('status-modal').classList.add('hidden');
}

function showNoteModal(orderId) {
    document.getElementById('note-order-id').value = orderId;
    document.getElementById('note-modal').classList.remove('hidden');
}

function hideNoteModal() {
    document.getElementById('note-modal').classList.add('hidden');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('[onclick*="toggleActionMenu"]') && !e.target.closest('[id^="action-menu-"]')) {
        document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }

    if (!e.target.closest('#status-modal > div') && !e.target.closest('[onclick*="showStatusModal"]')) {
        hideStatusModal();
    }

    if (!e.target.closest('#note-modal > div') && !e.target.closest('[onclick*="showNoteModal"]')) {
        hideNoteModal();
    }
});

setInterval(() => {

}, 30000);
</script>

<style>

@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.875rem;
    }

    .table-responsive th,
    .table-responsive td {
        padding: 0.5rem;
    }
}

.transition-all {
    transition: all 0.3s ease;
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

.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #4B5563;
}
</style>

<?php
endHTMLPage();
?>