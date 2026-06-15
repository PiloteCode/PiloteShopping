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
    $verification = $_GET['verification'] ?? '';
    $sort = $_GET['sort'] ?? 'created_at';
    $order = $_GET['order'] ?? 'DESC';
    
        $page = max(1, $page);
    $limit = min(100, max(10, $limit));
    $offset = ($page - 1) * $limit;
    
    $allowedSorts = ['created_at', 'last_login_at', 'username', 'email', 'balance'];
    $sort = in_array($sort, $allowedSorts) ? $sort : 'created_at';
    $order = in_array(strtoupper($order), ['ASC', 'DESC']) ? strtoupper($order) : 'DESC';
    
        
    $whereConditions = [];
    $params = [];
    
        if (!empty($search)) {
        $whereConditions[] = "(u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.uuid LIKE ?)";
        $searchParam = "%" . escapeLike($search) . "%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
        if ($status === 'active') {
        $whereConditions[] = "u.is_active = 1";
    } elseif ($status === 'inactive') {
        $whereConditions[] = "u.is_active = 0";
    }
    
        if ($verification === 'verified') {
        $whereConditions[] = "u.is_verified = 1";
    } elseif ($verification === 'unverified') {
        $whereConditions[] = "u.is_verified = 0";
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
        
    $stmt = $db->prepare("
        SELECT u.*, 
               ub.city, ub.country,
               uo.provider as oauth_provider,
               u2fa.is_enabled as has_2fa,
               COALESCE((
                   SELECT SUM(total_amount) 
                   FROM orders o 
                   WHERE o.user_id = u.id AND o.status = 'completed'
               ), 0) as total_spent,
               COALESCE((
                   SELECT COUNT(*) 
                   FROM orders o 
                   WHERE o.user_id = u.id
               ), 0) as total_orders,
               CASE 
                   WHEN au.id IS NOT NULL THEN au.role
                   ELSE NULL 
               END as admin_role
        FROM users u
        LEFT JOIN users_billing ub ON u.id = ub.user_id AND ub.is_default = 1
        LEFT JOIN users_oauth uo ON u.id = uo.user_id
        LEFT JOIN users_2fa u2fa ON u.id = u2fa.user_id
        LEFT JOIN admin_users au ON u.id = au.user_id AND au.is_active = 1
        $whereClause
        ORDER BY u.$sort $order
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
        
    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u $whereClause");
    $countStmt->execute($params);
    $totalUsers = $countStmt->fetchColumn();
    $totalPages = ceil($totalUsers / $limit);
    
        
    $statsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN is_active = 1 THEN 1 END) as active_users,
            COUNT(CASE WHEN is_verified = 1 THEN 1 END) as verified_users,
            COUNT(CASE WHEN profile_completed = 1 THEN 1 END) as completed_profiles,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as new_24h,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_7d,
            COUNT(CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_7d,
            COALESCE(SUM(balance), 0) as total_balance
        FROM users
    ");
    $statsStmt->execute();
    $stats = $statsStmt->fetch();
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        $userId = intval($_POST['user_id'] ?? 0);
        
        if ($userId > 0) {
            try {
                switch ($action) {
                    case 'toggle_status':
                        $stmt = $db->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?");
                        $stmt->execute([$userId]);
                        
                        $adminAuth->logAction('user_status_toggled', 'user', $userId, null, ['user_id' => $userId]);
                        $success = "Statut utilisateur modifié avec succès";
                        break;
                        
                    case 'verify_email':
                        $stmt = $db->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
                        $stmt->execute([$userId]);
                        
                        $adminAuth->logAction('user_email_verified', 'user', $userId, null, ['user_id' => $userId]);
                        $success = "Email vérifié avec succès";
                        break;
                        
                    case 'reset_password_flag':
                                                $stmt = $db->prepare("UPDATE users SET needs_password_reset = 1 WHERE id = ?");
                        $stmt->execute([$userId]);
                        
                        $adminAuth->logAction('user_password_reset_forced', 'user', $userId, null, ['user_id' => $userId]);
                        $success = "Réinitialisation de mot de passe forcée";
                        break;
                        
                    default:
                        $error = "Action non reconnue";
                }
                
                                if (isset($success)) {
                    header("Location: /admin/users/?success=" . urlencode($success));
                    exit();
                }
                
            } catch (Exception $e) {
                $error = "Erreur lors de l'action: " . $e->getMessage();
                $adminAuth->logAction('user_action_failed', 'user', $userId, null, ['error' => $e->getMessage()], 'critical');
            }
        }
    }
    
} catch (Exception $e) {
    error_log("Admin users error: " . $e->getMessage());
    $error = "Erreur lors du chargement des utilisateurs";
    $users = [];
    $stats = ['total_users' => 0, 'active_users' => 0, 'verified_users' => 0, 'completed_profiles' => 0, 'new_24h' => 0, 'new_7d' => 0, 'active_7d' => 0, 'total_balance' => 0];
    $totalUsers = 0;
    $totalPages = 1;
}

$seo = [
    'title' => 'Gestion des utilisateurs - Admin Pilote Shopping',
    'description' => 'Administration des utilisateurs',
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
                        <i class="fas fa-users text-blue-400 mr-3"></i>
                        Gestion des utilisateurs
                    </h1>
                    <p class="text-gray-400">Administration et monitoring des comptes utilisateur</p>
                </div>

                <div class="flex items-center space-x-4">
                    <a href="/admin/users/export.php" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
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


        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 mb-8">
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-blue-400 text-2xl font-bold"><?= number_format($stats['total_users']) ?></div>
                <div class="text-gray-400 text-sm">Total</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-green-400 text-2xl font-bold"><?= number_format($stats['active_users']) ?></div>
                <div class="text-gray-400 text-sm">Actifs</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-purple-400 text-2xl font-bold"><?= number_format($stats['verified_users']) ?></div>
                <div class="text-gray-400 text-sm">Vérifiés</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-orange-400 text-2xl font-bold"><?= number_format($stats['completed_profiles']) ?></div>
                <div class="text-gray-400 text-sm">Profils complets</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-yellow-400 text-2xl font-bold"><?= number_format($stats['new_24h']) ?></div>
                <div class="text-gray-400 text-sm">Nouveaux 24h</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-cyan-400 text-2xl font-bold"><?= number_format($stats['new_7d']) ?></div>
                <div class="text-gray-400 text-sm">Nouveaux 7j</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-teal-400 text-2xl font-bold"><?= number_format($stats['active_7d']) ?></div>
                <div class="text-gray-400 text-sm">Actifs 7j</div>
            </div>
            <div class="bg-dark-800/50 rounded-xl border border-dark-600 p-4">
                <div class="text-emerald-400 text-2xl font-bold"><?= number_format($stats['total_balance'], 2) ?>€</div>
                <div class="text-gray-400 text-sm">Total soldes</div>
            </div>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 mb-8">
            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">

                    <div class="lg:col-span-2">
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($search) ?>"
                               placeholder="Rechercher (username, email, nom, UUID...)" 
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <select name="status" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Tous les statuts</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Actifs</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactifs</option>
                        </select>
                    </div>

                    <div>
                        <select name="verification" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Toutes vérifications</option>
                            <option value="verified" <?= $verification === 'verified' ? 'selected' : '' ?>>Vérifiés</option>
                            <option value="unverified" <?= $verification === 'unverified' ? 'selected' : '' ?>>Non vérifiés</option>
                        </select>
                    </div>

                    <div class="flex space-x-2">
                        <select name="sort" class="flex-1 bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Date création</option>
                            <option value="last_login_at" <?= $sort === 'last_login_at' ? 'selected' : '' ?>>Dernière connexion</option>
                            <option value="username" <?= $sort === 'username' ? 'selected' : '' ?>>Username</option>
                            <option value="email" <?= $sort === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="balance" <?= $sort === 'balance' ? 'selected' : '' ?>>Solde</option>
                        </select>
                        <select name="order" class="bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-blue-500">
                            <option value="DESC" <?= $order === 'DESC' ? 'selected' : '' ?>>↓</option>
                            <option value="ASC" <?= $order === 'ASC' ? 'selected' : '' ?>>↑</option>
                        </select>
                    </div>

                    <div class="flex space-x-2">
                        <button type="submit" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i>Rechercher
                        </button>
                        <a href="/admin/users/" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-gray-400 text-sm">Afficher</span>
                        <select name="limit" onchange="this.form.submit()" class="bg-dark-700 border border-dark-600 rounded-lg px-3 py-1 text-white text-sm focus:outline-none focus:border-blue-500">
                            <option value="10" <?= $limit === 10 ? 'selected' : '' ?>>10</option>
                            <option value="25" <?= $limit === 25 ? 'selected' : '' ?>>25</option>
                            <option value="50" <?= $limit === 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit === 100 ? 'selected' : '' ?>>100</option>
                        </select>
                        <span class="text-gray-400 text-sm">résultats</span>
                    </div>

                    <div class="text-gray-400 text-sm">
<?= number_format($totalUsers) ?> utilisateur(s) trouvé(s)
                    </div>
                </div>
            </form>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 overflow-hidden">

<?php if (empty($users)): ?>
            <div class="p-12 text-center text-gray-400">
                <i class="fas fa-users text-6xl mb-4 opacity-50"></i>
                <p class="text-xl">Aucun utilisateur trouvé</p>
                <p class="text-sm mt-2">Essayez de modifier vos critères de recherche</p>
            </div>
<?php else: ?>

            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-dark-700/50">
                        <tr>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Utilisateur</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Statut</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Informations</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Activité</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Solde</th>
                            <th class="text-left py-4 px-6 text-gray-300 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
<?php foreach ($users as $user): ?>
                        <tr class="border-t border-dark-600 hover:bg-dark-700/30 transition-colors">

                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-3">
<?php if ($user['avatar_url']): ?>
                                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Avatar" class="w-12 h-12 rounded-full object-cover">
<?php else: ?>
                                        <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-purple-500 rounded-full flex items-center justify-center">
                                            <span class="text-white text-lg font-bold">
<?= strtoupper(substr($user['first_name'] ?: $user['username'], 0, 1)) ?>
                                            </span>
                                        </div>
<?php endif; ?>

                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <a href="/admin/users/user.php?uuid=<?= htmlspecialchars($user['uuid']) ?>" class="text-white font-medium hover:text-blue-400 transition-colors">
<?= htmlspecialchars($user['username']) ?>
                                            </a>

<?php if ($user['admin_role']): ?>
                                            <span class="px-2 py-1 bg-orange-500/20 text-orange-400 text-xs font-bold rounded-full uppercase">
<?= htmlspecialchars($user['admin_role']) ?>
                                            </span>
<?php endif; ?>
                                        </div>

                                        <p class="text-gray-400 text-sm">
<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                        </p>
                                        <p class="text-gray-500 text-xs font-mono">
<?= htmlspecialchars($user['email']) ?>
                                        </p>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="space-y-2">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-2 h-2 rounded-full <?= $user['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                        <span class="text-sm <?= $user['is_active'] ? 'text-green-400' : 'text-red-400' ?>">
<?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </div>

                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-<?= $user['is_verified'] ? 'check-circle text-green-500' : 'times-circle text-red-500' ?> text-xs"></i>
                                        <span class="text-xs <?= $user['is_verified'] ? 'text-green-400' : 'text-red-400' ?>">
<?= $user['is_verified'] ? 'Vérifié' : 'Non vérifié' ?>
                                        </span>
                                    </div>

<?php if ($user['profile_completed']): ?>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-user-check text-blue-500 text-xs"></i>
                                        <span class="text-xs text-blue-400">Profil complet</span>
                                    </div>
<?php endif; ?>

<?php if ($user['has_2fa']): ?>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-shield-alt text-purple-500 text-xs"></i>
                                        <span class="text-xs text-purple-400">2FA</span>
                                    </div>
<?php endif; ?>

<?php if ($user['oauth_provider']): ?>
                                    <div class="flex items-center space-x-2">
                                        <i class="fab fa-<?= $user['oauth_provider'] === 'google' ? 'google' : $user['oauth_provider'] ?> text-gray-500 text-xs"></i>
                                        <span class="text-xs text-gray-400"><?= ucfirst($user['oauth_provider']) ?></span>
                                    </div>
<?php endif; ?>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="space-y-1 text-sm">
<?php if ($user['phone']): ?>
                                    <div class="text-gray-400">
                                        <i class="fas fa-phone text-xs mr-2"></i>
<?= htmlspecialchars($user['phone']) ?>
                                    </div>
<?php endif; ?>

<?php if ($user['city'] || $user['country']): ?>
                                    <div class="text-gray-400">
                                        <i class="fas fa-map-marker-alt text-xs mr-2"></i>
<?= htmlspecialchars(trim($user['city'] . ' ' . $user['country'])) ?>
                                    </div>
<?php endif; ?>

                                    <div class="text-gray-500 text-xs">
                                        <i class="fas fa-calendar text-xs mr-2"></i>
                                        Inscrit le <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="space-y-1 text-sm">
<?php if ($user['last_login_at']): ?>
                                    <div class="text-gray-300">
                                        <i class="fas fa-clock text-xs mr-2"></i>
<?= date('d/m/Y H:i', strtotime($user['last_login_at'])) ?>
                                    </div>
<?php else: ?>
                                    <div class="text-gray-500">
                                        <i class="fas fa-clock text-xs mr-2"></i>
                                        Jamais connecté
                                    </div>
<?php endif; ?>

                                    <div class="text-gray-400 text-xs">
<?= number_format($user['total_orders']) ?> commande(s)
                                    </div>

                                    <div class="text-gray-400 text-xs">
<?= number_format($user['total_spent'], 2) ?>€ dépensés
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="text-right">
                                    <div class="text-white font-bold">
<?= number_format($user['balance'], 2) ?>€
                                    </div>
                                </div>
                            </td>

                            <td class="py-4 px-6">
                                <div class="flex items-center space-x-2">
                                    <a href="/admin/users/user.php?uuid=<?= htmlspecialchars($user['uuid']) ?>" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded-lg text-sm transition-colors"
                                       title="Voir détails">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <div class="relative group">
                                        <button class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded-lg text-sm transition-colors"
                                                onclick="toggleActionMenu(<?= $user['id'] ?>)">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>

                                        <div id="action-menu-<?= $user['id'] ?>" class="hidden absolute right-0 mt-2 w-48 bg-dark-700 rounded-lg border border-dark-600 shadow-xl z-50">
                                            <div class="py-2">
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="action" value="toggle_status" 
                                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                                        <i class="fas fa-<?= $user['is_active'] ? 'ban' : 'check' ?> mr-2"></i>
<?= $user['is_active'] ? 'Désactiver' : 'Activer' ?>
                                                    </button>
                                                </form>

<?php if (!$user['is_verified']): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="action" value="verify_email" 
                                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors">
                                                        <i class="fas fa-check-circle mr-2"></i>
                                                        Vérifier email
                                                    </button>
                                                </form>
<?php endif; ?>

                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" name="action" value="reset_password_flag" 
                                                            class="block w-full text-left px-4 py-2 text-gray-300 hover:bg-dark-600 hover:text-white transition-colors"
                                                            onclick="return confirm('Forcer la réinitialisation du mot de passe ?')">
                                                        <i class="fas fa-key mr-2"></i>
                                                        Forcer reset MdP
                                                    </button>
                                                </form>
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
                        Page <?= $page ?> sur <?= $totalPages ?> (<?= number_format($totalUsers) ?> résultats)
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

<script>

function toggleActionMenu(userId) {
    const menu = document.getElementById('action-menu-' + userId);
    const allMenus = document.querySelectorAll('[id^="action-menu-"]');

    allMenus.forEach(m => {
        if (m !== menu) {
            m.classList.add('hidden');
        }
    });

    menu.classList.toggle('hidden');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('[onclick*="toggleActionMenu"]') && !e.target.closest('[id^="action-menu-"]')) {
        document.querySelectorAll('[id^="action-menu-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
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