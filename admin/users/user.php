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
    
        
    $uuid = $_GET['uuid'] ?? '';
    if (empty($uuid)) {
        header('Location: /admin/users/?error=UUID utilisateur requis');
        exit();
    }
    
        $userStmt = $db->prepare("
        SELECT u.*, 
               au.role as admin_role, au.is_active as admin_active
        FROM users u
        LEFT JOIN admin_users au ON u.id = au.user_id
        WHERE u.uuid = ?
    ");
    $userStmt->execute([$uuid]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        header('Location: /admin/users/?error=Utilisateur non trouvé');
        exit();
    }
    
        try {
        $u2faStmt = $db->prepare("SELECT is_enabled as has_2fa FROM users_2fa WHERE user_id = ?");
        $u2faStmt->execute([$user['id']]);
        $u2faResult = $u2faStmt->fetch();
        $user['has_2fa'] = $u2faResult ? $u2faResult['has_2fa'] : 0;
    } catch (Exception $e) {
        $user['has_2fa'] = 0;
    }
    
        try {
        $sessionsCountStmt = $db->prepare("SELECT COUNT(*) as active_sessions FROM user_sessions WHERE user_id = ? AND expires_at > NOW()");
        $sessionsCountStmt->execute([$user['id']]);
        $sessionCount = $sessionsCountStmt->fetch();
        $user['active_sessions'] = $sessionCount ? $sessionCount['active_sessions'] : 0;
    } catch (Exception $e) {
        $user['active_sessions'] = 0;
    }
    
    if (!$user) {
        header('Location: /admin/users/?error=Utilisateur non trouvé');
        exit();
    }
    
        
        $billingStmt = $db->prepare("
        SELECT * FROM users_billing 
        WHERE user_id = ? 
        ORDER BY is_default DESC, created_at DESC
    ");
    $billingStmt->execute([$user['id']]);
    $billingAddresses = $billingStmt->fetchAll();
    
        $oauthStmt = $db->prepare("
        SELECT * FROM users_oauth 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $oauthStmt->execute([$user['id']]);
    $oauthAccounts = $oauthStmt->fetchAll();
    
        $ordersStatsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_orders,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_orders,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_orders,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_orders,
            COALESCE(SUM(total_amount), 0) as total_spent,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount END), 0) as completed_spent,
            COALESCE(AVG(CASE WHEN status = 'completed' THEN total_amount END), 0) as avg_order_value,
            MIN(created_at) as first_order_date,
            MAX(created_at) as last_order_date
        FROM orders 
        WHERE user_id = ?
    ");
    $ordersStatsStmt->execute([$user['id']]);
    $orderStats = $ordersStatsStmt->fetch();
    
        $recentOrdersStmt = $db->prepare("
        SELECT o.*, 
               COUNT(oi.id) as items_count,
               GROUP_CONCAT(oi.product_name SEPARATOR ', ') as products
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $recentOrdersStmt->execute([$user['id']]);
    $recentOrders = $recentOrdersStmt->fetchAll();
    
        $balanceTransactionsStmt = $db->prepare("
        SELECT * FROM balance_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 20
    ");
    $balanceTransactionsStmt->execute([$user['id']]);
    $balanceTransactions = $balanceTransactionsStmt->fetchAll();
    
        try {
        $sessionsStmt = $db->prepare("
            SELECT * FROM user_sessions 
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY updated_at DESC
        ");
        $sessionsStmt->execute([$user['id']]);
        $activeSessions = $sessionsStmt->fetchAll();
    } catch (Exception $e) {
        $activeSessions = [];
    }
    
        try {
        $securityLogsStmt = $db->prepare("
            SELECT * FROM security_logs 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
        $securityLogsStmt->execute([$user['id']]);
        $securityLogs = $securityLogsStmt->fetchAll();
    } catch (Exception $e) {
        $securityLogs = [];
    }
    
        try {
        $subscriptionsStmt = $db->prepare("
            SELECT s.*, po.name as offer_name, p.name as product_name
            FROM subscriptions s
            JOIN product_offers po ON s.product_offer_id = po.id
            JOIN products p ON po.product_id = p.id
            WHERE s.user_id = ?
            ORDER BY s.created_at DESC
        ");
        $subscriptionsStmt->execute([$user['id']]);
        $subscriptions = $subscriptionsStmt->fetchAll();
    } catch (Exception $e) {
        $subscriptions = [];
    }
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'update_balance':
                                        if (!function_exists('canAdminAccess') || !canAdminAccess('users', 'update')) {
                        if ($currentAdmin['role'] !== 'super_admin' && $currentAdmin['role'] !== 'admin') {
                            throw new Exception('Permission insuffisante');
                        }
                    }
                    
                    $amount = floatval($_POST['amount']);
                    $type = $_POST['type'];                     $description = trim($_POST['description']);
                    
                    if ($amount <= 0) {
                        throw new Exception('Montant invalide');
                    }
                    
                    if (empty($description)) {
                        throw new Exception('Description requise');
                    }
                    
                    $db->beginTransaction();
                    
                                        $newBalance = $type === 'credit' 
                        ? $user['balance'] + $amount 
                        : $user['balance'] - $amount;
                    
                    if ($newBalance < 0 && $type === 'debit') {
                        throw new Exception('Solde insuffisant');
                    }
                    
                                        $updateBalanceStmt = $db->prepare("UPDATE users SET balance = ? WHERE id = ?");
                    $updateBalanceStmt->execute([$newBalance, $user['id']]);
                    
                                        $transactionStmt = $db->prepare("
                        INSERT INTO balance_transactions 
                        (user_id, type, amount, balance_before, balance_after, source, description, metadata)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $source = 'ADMIN_ADJUSTMENT_' . $currentAdmin['id'] . '_' . time();
                    $metadata = json_encode([
                        'admin_id' => $currentAdmin['id'],
                        'admin_username' => $currentUser['username'],
                        'ip' => getRealIP()
                    ]);
                    
                    $transactionStmt->execute([
                        $user['id'], $type, $amount, $user['balance'], $newBalance,
                        $source, $description, $metadata
                    ]);
                    
                    $db->commit();
                    
                    $adminAuth->logAction('user_balance_updated', 'user', $user['id'], 
                        ['old_balance' => $user['balance']], 
                        ['new_balance' => $newBalance, 'amount' => $amount, 'type' => $type], 
                        'medium');
                    
                    $success = "Solde mis à jour avec succès";
                    $user['balance'] = $newBalance;                     break;
                    
                case 'toggle_status':
                                        if (!function_exists('canAdminAccess') || !canAdminAccess('users', 'update')) {
                        if ($currentAdmin['role'] !== 'super_admin' && $currentAdmin['role'] !== 'admin') {
                            throw new Exception('Permission insuffisante');
                        }
                    }
                    
                    $newStatus = $user['is_active'] ? 0 : 1;
                    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
                    $stmt->execute([$newStatus, $user['id']]);
                    
                    $adminAuth->logAction('user_status_toggled', 'user', $user['id'], 
                        ['old_status' => $user['is_active']], 
                        ['new_status' => $newStatus]);
                    
                    $success = "Statut utilisateur modifié";
                    $user['is_active'] = $newStatus;
                    break;
                    
                case 'verify_email':
                    if (!$user['is_verified']) {
                        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                        $stmt->execute([$user['id']]);
                        
                        $adminAuth->logAction('user_email_verified', 'user', $user['id']);
                        $success = "Email vérifié avec succès";
                        $user['is_verified'] = 1;
                    }
                    break;
                    
                case 'revoke_sessions':
                    $stmt = $db->prepare("DELETE FROM user_sessions WHERE user_id = ?");
                    $stmt->execute([$user['id']]);
                    
                    $adminAuth->logAction('user_sessions_revoked', 'user', $user['id']);
                    $success = "Toutes les sessions ont été révoquées";
                    break;
                    
                case 'disable_2fa':
                    if ($user['has_2fa']) {
                        $stmt = $db->prepare("UPDATE users_2fa SET is_enabled = 0 WHERE user_id = ?");
                        $stmt->execute([$user['id']]);
                        
                        $adminAuth->logAction('user_2fa_disabled', 'user', $user['id']);
                        $success = "2FA désactivé avec succès";
                        $user['has_2fa'] = 0;
                    }
                    break;
                    
                default:
                    throw new Exception('Action non reconnue');
            }
            
            if (isset($success)) {
                header("Location: /admin/users/user.php?uuid=" . urlencode($uuid) . "&success=" . urlencode($success));
                exit();
            }
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            $error = $e->getMessage();
            $adminAuth->logAction('user_action_failed', 'user', $user['id'], null, ['error' => $e->getMessage()], 'critical');
        }
    }
    
} catch (Exception $e) {
    error_log("Admin user details error: " . $e->getMessage());
    $error = "Erreur lors du chargement des informations utilisateur";
    header('Location: /admin/users/?error=' . urlencode($error));
    exit();
}

$seo = [
    'title' => 'Utilisateur ' . $user['username'] . ' - Admin Pilote Shopping',
    'description' => 'Détails administrateur de l\'utilisateur',
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
                <a href="/admin/users/" class="hover:text-white transition-colors">Utilisateurs</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <span class="text-white"><?= htmlspecialchars($user['username']) ?></span>
            </nav>
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


        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-8 mb-8">
            <div class="flex items-start justify-between">
                <div class="flex items-center space-x-6">
<?php if ($user['avatar_url']): ?>
                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Avatar" class="w-24 h-24 rounded-2xl object-cover">
<?php else: ?>
                        <div class="w-24 h-24 bg-gradient-to-br from-blue-500 to-purple-500 rounded-2xl flex items-center justify-center">
                            <span class="text-white text-3xl font-bold">
<?= strtoupper(substr($user['first_name'] ?: $user['username'], 0, 1)) ?>
                            </span>
                        </div>
<?php endif; ?>

                    <div>
                        <div class="flex items-center space-x-3 mb-2">
                            <h1 class="text-3xl font-bold text-white"><?= htmlspecialchars($user['username']) ?></h1>

<?php if ($user['admin_role']): ?>
                            <span class="px-3 py-1 bg-orange-500/20 text-orange-400 text-sm font-bold rounded-full uppercase">
<?= htmlspecialchars($user['admin_role']) ?>
                            </span>
<?php endif; ?>

                            <div class="flex items-center space-x-2">
                                <div class="w-3 h-3 rounded-full <?= $user['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                <span class="text-sm <?= $user['is_active'] ? 'text-green-400' : 'text-red-400' ?>">
<?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                </span>
                            </div>
                        </div>

                        <p class="text-xl text-gray-300 mb-2">
<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                        </p>

                        <div class="flex items-center space-x-4 text-sm text-gray-400">
                            <span>
                                <i class="fas fa-envelope mr-1"></i>
<?= htmlspecialchars($user['email']) ?>
                            </span>

<?php if ($user['phone']): ?>
                            <span>
                                <i class="fas fa-phone mr-1"></i>
<?= htmlspecialchars($user['phone']) ?>
                            </span>
<?php endif; ?>

                            <span>
                                <i class="fas fa-calendar mr-1"></i>
                                Inscrit le <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                            </span>
                        </div>

                        <div class="flex items-center space-x-2 mt-4">
<?php if ($user['is_verified']): ?>
                            <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full">
                                <i class="fas fa-check-circle mr-1"></i>Email vérifié
                            </span>
<?php else: ?>
                            <span class="px-2 py-1 bg-red-500/20 text-red-400 text-xs rounded-full">
                                <i class="fas fa-times-circle mr-1"></i>Email non vérifié
                            </span>
<?php endif; ?>

<?php if ($user['profile_completed']): ?>
                            <span class="px-2 py-1 bg-blue-500/20 text-blue-400 text-xs rounded-full">
                                <i class="fas fa-user-check mr-1"></i>Profil complet
                            </span>
<?php endif; ?>

<?php if ($user['has_2fa']): ?>
                            <span class="px-2 py-1 bg-purple-500/20 text-purple-400 text-xs rounded-full">
                                <i class="fas fa-shield-alt mr-1"></i>2FA activé
                            </span>
<?php endif; ?>

<?php if ($user['active_sessions'] > 0): ?>
                            <span class="px-2 py-1 bg-yellow-500/20 text-yellow-400 text-xs rounded-full">
                                <i class="fas fa-desktop mr-1"></i><?= $user['active_sessions'] ?> session(s) active(s)
                            </span>
<?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-3">
                    <a href="/admin/users/edit.php?uuid=<?= htmlspecialchars($user['uuid']) ?>" 
                       class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                        <i class="fas fa-edit mr-2"></i>Éditer
                    </a>

<?php if ($currentAdmin['role'] === 'super_admin' || $currentAdmin['role'] === 'admin'): ?>
                    <button onclick="showBalanceModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                        <i class="fas fa-euro-sign mr-2"></i>Ajuster solde
                    </button>

                    <div class="relative">
                        <button onclick="toggleActionsMenu()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                            <i class="fas fa-cog mr-2"></i>Actions
                            <i class="fas fa-chevron-down ml-2 text-sm"></i>
                        </button>

                        <div id="actions-menu" class="hidden absolute right-0 mt-2 w-56 bg-dark-700 rounded-xl border border-dark-600 shadow-xl z-50">
                            <div class="py-2">
                                <form method="POST" class="inline">
                                    <button type="submit" name="action" value="toggle_status" 
                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                        <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?> mr-3"></i>
<?= $user['is_active'] ? 'Désactiver compte' : 'Activer compte' ?>
                                    </button>
                                </form>

<?php if (!$user['is_verified']): ?>
                                <form method="POST" class="inline">
                                    <button type="submit" name="action" value="verify_email" 
                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                        <i class="fas fa-check-circle mr-3"></i>
                                        Vérifier email
                                    </button>
                                </form>
<?php endif; ?>

<?php if ($user['active_sessions'] > 0): ?>
                                <form method="POST" class="inline">
                                    <button type="submit" name="action" value="revoke_sessions" 
                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors"
                                            onclick="return confirm('Révoquer toutes les sessions actives ?')">
                                        <i class="fas fa-sign-out-alt mr-3"></i>
                                        Déconnecter partout
                                    </button>
                                </form>
<?php endif; ?>

<?php if ($user['has_2fa']): ?>
                                <form method="POST" class="inline">
                                    <button type="submit" name="action" value="disable_2fa" 
                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors"
                                            onclick="return confirm('Désactiver l\'authentification à deux facteurs ?')">
                                        <i class="fas fa-shield-alt mr-3"></i>
                                        Désactiver 2FA
                                    </button>
                                </form>
<?php endif; ?>
                            </div>
                        </div>
                    </div>
<?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-8">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h2 class="text-xl font-bold text-white mb-6">
                        <i class="fas fa-user text-blue-400 mr-2"></i>
                        Informations générales
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="text-gray-400 text-sm">UUID</label>
                                <p class="text-white font-mono bg-dark-700/50 px-3 py-2 rounded-lg text-sm">
<?= htmlspecialchars($user['uuid']) ?>
                                </p>
                            </div>

                            <div>
                                <label class="text-gray-400 text-sm">Nom d'utilisateur</label>
                                <p class="text-white"><?= htmlspecialchars($user['username']) ?></p>
                            </div>

                            <div>
                                <label class="text-gray-400 text-sm">Email</label>
                                <p class="text-white"><?= htmlspecialchars($user['email']) ?></p>
                            </div>

                            <div>
                                <label class="text-gray-400 text-sm">Téléphone</label>
                                <p class="text-white"><?= $user['phone'] ? htmlspecialchars($user['phone']) : 'Non renseigné' ?></p>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="text-gray-400 text-sm">Nom complet</label>
                                <p class="text-white"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></p>
                            </div>

                            <div>
                                <label class="text-gray-400 text-sm">Solde actuel</label>
                                <p class="text-2xl font-bold text-green-400"><?= number_format($user['balance'], 2) ?>€</p>
                            </div>

                            <div>
                                <label class="text-gray-400 text-sm">Date d'inscription</label>
                                <p class="text-white"><?= date('d/m/Y à H:i', strtotime($user['created_at'])) ?></p>
                            </div>

                            <div>
                                <label class="text-gray-400 text-sm">Dernière connexion</label>
                                <p class="text-white">
<?= $user['last_login_at'] ? date('d/m/Y à H:i', strtotime($user['last_login_at'])) : 'Jamais' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h2 class="text-xl font-bold text-white mb-6">
                        <i class="fas fa-shopping-cart text-green-400 mr-2"></i>
                        Statistiques des commandes
                    </h2>

                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-dark-700/50 rounded-xl p-4">
                            <div class="text-blue-400 text-2xl font-bold"><?= number_format($orderStats['total_orders']) ?></div>
                            <div class="text-gray-400 text-sm">Total commandes</div>
                        </div>
                        <div class="bg-dark-700/50 rounded-xl p-4">
                            <div class="text-green-400 text-2xl font-bold"><?= number_format($orderStats['completed_orders']) ?></div>
                            <div class="text-gray-400 text-sm">Complétées</div>
                        </div>
                        <div class="bg-dark-700/50 rounded-xl p-4">
                            <div class="text-purple-400 text-2xl font-bold"><?= number_format($orderStats['total_spent'], 2) ?>€</div>
                            <div class="text-gray-400 text-sm">Total dépensé</div>
                        </div>
                        <div class="bg-dark-700/50 rounded-xl p-4">
                            <div class="text-orange-400 text-2xl font-bold"><?= number_format($orderStats['avg_order_value'], 2) ?>€</div>
                            <div class="text-gray-400 text-sm">Panier moyen</div>
                        </div>
                    </div>

<?php if ($orderStats['first_order_date']): ?>
                    <div class="text-gray-400 text-sm">
                        <p>Première commande: <?= date('d/m/Y', strtotime($orderStats['first_order_date'])) ?></p>
                        <p>Dernière commande: <?= date('d/m/Y', strtotime($orderStats['last_order_date'])) ?></p>
                    </div>
<?php endif; ?>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-shopping-bag text-green-400 mr-2"></i>
                            Commandes récentes
                        </h2>
                        <a href="/admin/orders/?user_id=<?= $user['id'] ?>" class="text-green-400 hover:text-green-300 text-sm font-medium">
                            Voir toutes <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

<?php if (empty($recentOrders)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-shopping-bag text-3xl mb-2 opacity-50"></i>
                        <p>Aucune commande</p>
                    </div>
<?php else: ?>
                    <div class="space-y-4">
<?php foreach (array_slice($recentOrders, 0, 5) as $order): ?>
                        <div class="flex items-center justify-between p-4 bg-dark-700/50 rounded-xl hover:bg-dark-700 transition-colors">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 bg-gradient-to-br from-green-500 to-blue-500 rounded-xl flex items-center justify-center">
                                    <i class="fas fa-shopping-bag text-white"></i>
                                </div>
                                <div>
                                    <p class="text-white font-medium">
                                        Commande #<?= htmlspecialchars($order['order_number']) ?>
                                    </p>
                                    <p class="text-gray-400 text-sm">
<?= $order['items_count'] ?> article(s) • 
<?= date('d/m/Y H:i', strtotime($order['created_at'])) ?>
                                    </p>
<?php if ($order['products']): ?>
                                    <p class="text-gray-500 text-xs">
<?= htmlspecialchars(substr($order['products'], 0, 50)) ?><?= strlen($order['products']) > 50 ? '...' : '' ?>
                                    </p>
<?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-white font-bold"><?= number_format($order['total_amount'], 2) ?>€</p>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= 
                                    $order['status'] === 'completed' ? 'bg-green-500/20 text-green-400' :
                                    ($order['status'] === 'pending' ? 'bg-yellow-500/20 text-yellow-400' :
                                    ($order['status'] === 'cancelled' ? 'bg-red-500/20 text-red-400' : 'bg-gray-500/20 text-gray-400'))
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
                    <h2 class="text-xl font-bold text-white mb-6">
                        <i class="fas fa-exchange-alt text-yellow-400 mr-2"></i>
                        Transactions de solde
                    </h2>

<?php if (empty($balanceTransactions)): ?>
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-receipt text-3xl mb-2 opacity-50"></i>
                        <p>Aucune transaction</p>
                    </div>
<?php else: ?>
                    <div class="space-y-3">
<?php foreach ($balanceTransactions as $transaction): ?>
                        <div class="flex items-center justify-between p-3 bg-dark-700/30 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center <?= 
                                    $transaction['type'] === 'credit' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400'
                                ?>">
                                    <i class="fas fa-<?= $transaction['type'] === 'credit' ? 'plus' : 'minus' ?> text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-white text-sm font-medium"><?= htmlspecialchars($transaction['description']) ?></p>
                                    <p class="text-gray-400 text-xs"><?= date('d/m/Y H:i', strtotime($transaction['created_at'])) ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="font-bold <?= $transaction['type'] === 'credit' ? 'text-green-400' : 'text-red-400' ?>">
<?= $transaction['type'] === 'credit' ? '+' : '-' ?><?= number_format($transaction['amount'], 2) ?>€
                                </p>
                                <p class="text-gray-400 text-xs">Solde: <?= number_format($transaction['balance_after'], 2) ?>€</p>
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
                        <i class="fas fa-map-marker-alt text-blue-400 mr-2"></i>
                        Adresses de facturation
                    </h3>

<?php if (empty($billingAddresses)): ?>
                    <p class="text-gray-400 text-sm">Aucune adresse enregistrée</p>
<?php else: ?>
                    <div class="space-y-4">
<?php foreach ($billingAddresses as $address): ?>
                        <div class="p-3 bg-dark-700/50 rounded-lg">
<?php if ($address['is_default']): ?>
                            <span class="inline-block px-2 py-1 bg-blue-500/20 text-blue-400 text-xs rounded mb-2">
                                Adresse par défaut
                            </span>
<?php endif; ?>

                            <div class="text-sm space-y-1">
                                <p class="text-white font-medium">
<?= htmlspecialchars($address['first_name'] . ' ' . $address['last_name']) ?>
                                </p>
<?php if ($address['company_name']): ?>
                                <p class="text-gray-300"><?= htmlspecialchars($address['company_name']) ?></p>
<?php endif; ?>
                                <p class="text-gray-400"><?= htmlspecialchars($address['address_line1']) ?></p>
<?php if ($address['address_line2']): ?>
                                <p class="text-gray-400"><?= htmlspecialchars($address['address_line2']) ?></p>
<?php endif; ?>
                                <p class="text-gray-400">
<?= htmlspecialchars($address['postal_code'] . ' ' . $address['city']) ?>
                                </p>
                                <p class="text-gray-400"><?= htmlspecialchars($address['country']) ?></p>
<?php if ($address['phone']): ?>
                                <p class="text-gray-400">
                                    <i class="fas fa-phone mr-1"></i>
<?= htmlspecialchars($address['phone']) ?>
                                </p>
<?php endif; ?>
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>
<?php endif; ?>
                </div>

<?php if (!empty($oauthAccounts)): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-link text-purple-400 mr-2"></i>
                        Comptes liés
                    </h3>

                    <div class="space-y-3">
<?php foreach ($oauthAccounts as $oauth): ?>
                        <div class="flex items-center justify-between p-3 bg-dark-700/50 rounded-lg">
                            <div class="flex items-center space-x-3">
                                <i class="fab fa-<?= $oauth['provider'] === 'google' ? 'google' : $oauth['provider'] ?> text-xl text-gray-400"></i>
                                <div>
                                    <p class="text-white font-medium"><?= ucfirst($oauth['provider']) ?></p>
<?php if ($oauth['provider_email']): ?>
                                    <p class="text-gray-400 text-sm"><?= htmlspecialchars($oauth['provider_email']) ?></p>
<?php endif; ?>
                                </div>
                            </div>
                            <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full">
                                Connecté
                            </span>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>
<?php endif; ?>


<?php if (!empty($subscriptions)): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-sync-alt text-orange-400 mr-2"></i>
                        Abonnements
                    </h3>

                    <div class="space-y-3">
<?php foreach ($subscriptions as $sub): ?>
                        <div class="p-3 bg-dark-700/50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-white font-medium"><?= htmlspecialchars($sub['product_name']) ?></p>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= 
                                    $sub['status'] === 'active' ? 'bg-green-500/20 text-green-400' :
                                    ($sub['status'] === 'paused' ? 'bg-yellow-500/20 text-yellow-400' :
                                    'bg-red-500/20 text-red-400')
                                ?>">
<?= ucfirst($sub['status']) ?>
                                </span>
                            </div>
                            <p class="text-gray-400 text-sm"><?= htmlspecialchars($sub['offer_name']) ?></p>
                            <p class="text-orange-400 font-bold"><?= number_format($sub['amount'], 2) ?>€/<?= $sub['billing_cycle'] ?></p>
<?php if ($sub['next_billing_date']): ?>
                            <p class="text-gray-500 text-xs">
                                Prochaine facture: <?= date('d/m/Y', strtotime($sub['next_billing_date'])) ?>
                            </p>
<?php endif; ?>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>
<?php endif; ?>


<?php if (!empty($activeSessions)): ?>
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-desktop text-yellow-400 mr-2"></i>
                        Sessions actives (<?= count($activeSessions) ?>)
                    </h3>

                    <div class="space-y-3">
<?php foreach (array_slice($activeSessions, 0, 5) as $session): ?>
                        <div class="p-3 bg-dark-700/50 rounded-lg">
                            <div class="flex items-center justify-between mb-2">
                                <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full">
                                    <i class="fas fa-circle text-xs mr-1"></i>Actif
                                </span>
                                <span class="text-gray-400 text-xs">
<?= $session['is_mobile'] ? 'Mobile' : 'Desktop' ?>
                                </span>
                            </div>

                            <p class="text-gray-400 text-sm font-mono"><?= htmlspecialchars($session['ip_address']) ?></p>

<?php if ($session['location']): ?>
                            <p class="text-gray-500 text-xs">
                                <i class="fas fa-map-marker-alt mr-1"></i>
<?= htmlspecialchars($session['location']) ?>
                            </p>
<?php endif; ?>

                            <p class="text-gray-500 text-xs">
                                Dernière activité: <?= date('d/m/Y H:i', strtotime($session['updated_at'])) ?>
                            </p>
                        </div>
<?php endforeach; ?>
                    </div>
                </div>
<?php endif; ?>


                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-shield-alt text-red-400 mr-2"></i>
                        Logs de sécurité récents
                    </h3>

<?php if (empty($securityLogs)): ?>
                    <p class="text-gray-400 text-sm">Aucun log récent</p>
<?php else: ?>
                    <div class="space-y-2 max-h-64 overflow-y-auto">
<?php foreach (array_slice($securityLogs, 0, 10) as $log): ?>
                        <div class="p-2 bg-dark-700/30 rounded-lg">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= 
                                    $log['status'] === 'success' ? 'bg-green-500/20 text-green-400' :
                                    ($log['status'] === 'failed' ? 'bg-red-500/20 text-red-400' :
                                    'bg-yellow-500/20 text-yellow-400')
                                ?>">
<?= htmlspecialchars($log['action']) ?>
                                </span>
                                <span class="text-gray-500 text-xs">
<?= date('d/m H:i', strtotime($log['created_at'])) ?>
                                </span>
                            </div>
                            <p class="text-gray-400 text-xs mt-1 font-mono">
<?= htmlspecialchars($log['ip_address']) ?>
                            </p>
                        </div>
<?php endforeach; ?>
                    </div>
<?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="balance-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">Ajuster le solde</h3>
            <button onclick="hideBalanceModal()" class="text-gray-400 hover:text-white">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="update_balance">

            <div>
                <label class="block text-gray-400 text-sm mb-2">Type d'opération</label>
                <select name="type" required class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                    <option value="credit">Crédit (+)</option>
                    <option value="debit">Débit (-)</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-400 text-sm mb-2">Montant (€)</label>
                <input type="number" name="amount" step="0.01" min="0.01" required 
                       class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
                <label class="block text-gray-400 text-sm mb-2">Description</label>
                <textarea name="description" rows="3" required 
                          placeholder="Raison de l'ajustement..."
                          class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"></textarea>
            </div>

            <div class="flex space-x-3 pt-4">
                <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Appliquer
                </button>
                <button type="button" onclick="hideBalanceModal()" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-2 rounded-xl font-medium transition-colors">
                    Annuler
                </button>
            </div>
        </form>
    </div>
</div>

<script>

function toggleActionsMenu() {
    const menu = document.getElementById('actions-menu');
    menu.classList.toggle('hidden');
}

function showBalanceModal() {
    document.getElementById('balance-modal').classList.remove('hidden');
}

function hideBalanceModal() {
    document.getElementById('balance-modal').classList.add('hidden');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('#actions-menu') && !e.target.closest('[onclick="toggleActionsMenu()"]')) {
        document.getElementById('actions-menu')?.classList.add('hidden');
    }

    if (!e.target.closest('#balance-modal > div') && !e.target.closest('[onclick="showBalanceModal()"]')) {
        hideBalanceModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideBalanceModal();
        document.getElementById('actions-menu')?.classList.add('hidden');
    }
});
</script>

<style>

.overflow-y-auto::-webkit-scrollbar {
    width: 4px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #1F2937;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 2px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #4B5563;
}

#balance-modal {
    backdrop-filter: blur(8px);
}

#balance-modal > div {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\\:grid-cols-3 {
        grid-template-columns: 1fr;
    }

    .text-3xl {
        font-size: 1.5rem;
    }

    .flex.items-center.space-x-6 {
        flex-direction: column;
        align-items: flex-start;
        space-x: 0;
    }
}
</style>

<?php
endHTMLPage();
?>