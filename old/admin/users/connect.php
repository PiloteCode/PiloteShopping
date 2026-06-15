<?php
define('API_ACCESS', true);
require_once '../../api/v1/config.php';
require_once '../../includes/import.php';
require_once '../../includes/AuthMiddleware.php';
require_once '../includes/AdminAuthMiddleware.php';

$adminAuth = initAdminAuth();
$adminAuth->requireAdminAuth(['admin', 'super_admin']); 
$currentAdmin = $adminAuth->getCurrentAdmin();
$currentUser = $adminAuth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'connect_to_user') {
        $targetUserId = intval($_POST['target_user_id'] ?? 0);
        
        if ($targetUserId <= 0) {
            throw new Exception('ID utilisateur invalide');
        }
        
                $userStmt = $db->prepare("
            SELECT id, uuid, username, email, first_name, last_name, 
                   avatar_url, balance, is_active, is_verified, profile_completed
            FROM users 
            WHERE id = ? AND is_active = 1
        ");
        $userStmt->execute([$targetUserId]);
        $targetUser = $userStmt->fetch();
        
        if (!$targetUser) {
            throw new Exception('Utilisateur non trouvé ou inactif');
        }
        
                if ($currentAdmin['role'] !== 'super_admin') {
            $adminCheckStmt = $db->prepare("
                SELECT role FROM admin_users 
                WHERE user_id = ? AND is_active = 1
            ");
            $adminCheckStmt->execute([$targetUserId]);
            $targetAdminRole = $adminCheckStmt->fetchColumn();
            
            if ($targetAdminRole) {
                throw new Exception('Impossible de se connecter au compte d\'un autre administrateur');
            }
        }
        
        try {
            $db->beginTransaction();
            
                        $adminAuth->logAction('admin_connect_to_user', 'user', $targetUserId, null, [
                'target_username' => $targetUser['username'],
                'target_email' => $targetUser['email'],
                'admin_id' => $currentAdmin['id'],
                'admin_username' => $currentUser['username'],
                'ip' => getRealIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ], 'critical');
            
                        logSecurityEvent($targetUserId, 'admin_account_access', [
                'admin_id' => $currentAdmin['id'],
                'admin_username' => $currentUser['username'],
                'admin_role' => $currentAdmin['role']
            ], 'warning');
            
                        if (isset($_SESSION['admin_session_id'])) {
                $stmt = $db->prepare("DELETE FROM admin_sessions WHERE id = ?");
                $stmt->execute([$_SESSION['admin_session_id']]);
            }
            
                        session_destroy();
            session_start();
            
                        $auth = AuthMiddleware::getInstance();
            $sessionId = generateSecureToken(32);
            $expiresAt = date('Y-m-d H:i:s', time() + SESSION_LIFETIME);
            
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $isMobile = preg_match('/(android|iphone|ipad|mobile)/i', $userAgent);
            
            $sessionStmt = $db->prepare("
                INSERT INTO user_sessions 
                (id, user_id, ip_address, user_agent, is_mobile, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $sessionStmt->execute([
                $sessionId,
                $targetUserId,
                getRealIP(),
                $userAgent,
                $isMobile ? 1 : 0,
                $expiresAt
            ]);
            
                        $_SESSION['user_id'] = $targetUser['id'];
            $_SESSION['session_id'] = $sessionId;
            $_SESSION['username'] = $targetUser['username'];
            $_SESSION['admin_connected'] = true;             $_SESSION['original_admin_id'] = $currentAdmin['id'];
            $_SESSION['original_admin_username'] = $currentUser['username'];
            
                        $loginUpdateStmt = $db->prepare("
                UPDATE users 
                SET last_login_at = NOW(), last_ip = ? 
                WHERE id = ?
            ");
            $loginUpdateStmt->execute([getRealIP(), $targetUserId]);
            
            session_regenerate_id(true);
            
            $db->commit();
            
                        header('Location: /dashboard?admin_connected=1');
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            throw $e;
        }
    }
    
        
    $searchResults = [];
    $searchPerformed = false;
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['search'])) {
        $searchPerformed = true;
        $searchTerm = trim($_GET['search']);
        $searchType = $_GET['search_type'] ?? 'all';
        
        if (strlen($searchTerm) < 2) {
            throw new Exception('Le terme de recherche doit contenir au moins 2 caractères');
        }
        
                $whereConditions = [];
        $params = [];
        
        switch ($searchType) {
            case 'email':
                $whereConditions[] = "email LIKE ?";
                $params[] = "%" . escapeLike($searchTerm) . "%";
                break;
                
            case 'uuid':
                $whereConditions[] = "uuid LIKE ?";
                $params[] = "%" . escapeLike($searchTerm) . "%";
                break;
                
            case 'username':
                $whereConditions[] = "username LIKE ?";
                $params[] = "%" . escapeLike($searchTerm) . "%";
                break;
                
            case 'phone':
                $whereConditions[] = "phone LIKE ?";
                $params[] = "%" . escapeLike($searchTerm) . "%";
                break;
                
            case 'ip':
                $whereConditions[] = "last_ip LIKE ?";
                $params[] = "%" . escapeLike($searchTerm) . "%";
                break;
                
            case 'name':
                $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ?)";
                $params = array_merge($params, ["%$searchTerm%", "%$searchTerm%"]);
                break;
                
            default:                 $whereConditions[] = "(username LIKE ? OR email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR uuid LIKE ? OR phone LIKE ? OR last_ip LIKE ?)";
                $searchParam = "%" . escapeLike($searchTerm) . "%";
                $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
                break;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
                $searchStmt = $db->prepare("
            SELECT u.id, u.uuid, u.username, u.email, u.first_name, u.last_name, 
                   u.phone, u.avatar_url, u.balance, u.is_active, u.is_verified,
                   u.profile_completed, u.created_at, u.last_login_at, u.last_ip,
                   au.role as admin_role, au.is_active as admin_active
            FROM users u
            LEFT JOIN admin_users au ON u.id = au.user_id
            WHERE $whereClause
            ORDER BY u.last_login_at DESC
            LIMIT 50
        ");
        
        $searchStmt->execute($params);
        $searchResults = $searchStmt->fetchAll();
        
                $adminAuth->logAction('admin_user_search', 'search', null, null, [
            'search_term' => $searchTerm,
            'search_type' => $searchType,
            'results_count' => count($searchResults)
        ], 'low');
    }
    
} catch (Exception $e) {
    error_log("Admin user connect error: " . $e->getMessage());
    $error = $e->getMessage();
}

$seo = [
    'title' => 'Se connecter au compte - Admin Pilote Shopping',
    'description' => 'Connexion au compte utilisateur',
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
                <span class="text-white">Se connecter au compte</span>
            </nav>
        </div>

        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                <i class="fas fa-sign-in-alt text-orange-400 mr-3"></i>
                Se connecter au compte utilisateur
            </h1>
            <p class="text-gray-400">Rechercher et se connecter directement au compte d'un utilisateur</p>

            <div class="mt-4 p-4 bg-red-500/20 border border-red-500/50 rounded-xl">
                <div class="flex items-start space-x-3">
                    <i class="fas fa-exclamation-triangle text-red-400 text-xl mt-0.5"></i>
                    <div>
                        <h3 class="text-red-300 font-bold mb-1">Fonctionnalité sensible</h3>
                        <p class="text-red-200 text-sm">
                            Cette action vous connectera directement au compte de l'utilisateur sélectionné. 
                            Votre session administrateur sera fermée et toutes les actions seront loggées.
                            Utilisez cette fonctionnalité uniquement pour le support technique légitime.
                        </p>
                    </div>
                </div>
            </div>
        </div>

<?php if (isset($error)): ?>
        <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-xl">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 mb-8">
            <h2 class="text-xl font-bold text-white mb-6">
                <i class="fas fa-search text-blue-400 mr-2"></i>
                Rechercher un utilisateur
            </h2>

            <form method="GET" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">

                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">Terme de recherche</label>
                        <input type="text" 
                               name="search" 
                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>"
                               placeholder="Email, username, UUID, nom, téléphone, IP..." 
                               required
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Type de recherche</label>
                        <select name="search_type" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="all" <?= ($_GET['search_type'] ?? '') === 'all' ? 'selected' : '' ?>>Tous les champs</option>
                            <option value="email" <?= ($_GET['search_type'] ?? '') === 'email' ? 'selected' : '' ?>>Email</option>
                            <option value="username" <?= ($_GET['search_type'] ?? '') === 'username' ? 'selected' : '' ?>>Username</option>
                            <option value="uuid" <?= ($_GET['search_type'] ?? '') === 'uuid' ? 'selected' : '' ?>>UUID</option>
                            <option value="name" <?= ($_GET['search_type'] ?? '') === 'name' ? 'selected' : '' ?>>Prénom/Nom</option>
                            <option value="phone" <?= ($_GET['search_type'] ?? '') === 'phone' ? 'selected' : '' ?>>Téléphone</option>
                            <option value="ip" <?= ($_GET['search_type'] ?? '') === 'ip' ? 'selected' : '' ?>>Adresse IP</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                            <i class="fas fa-search mr-2"></i>Rechercher
                        </button>
                    </div>
                </div>
            </form>
        </div>

<?php if ($searchPerformed): ?>
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white">
                    <i class="fas fa-users text-green-400 mr-2"></i>
                    Résultats de recherche
                </h2>
                <span class="text-gray-400 text-sm"><?= count($searchResults) ?> résultat(s) trouvé(s)</span>
            </div>

<?php if (empty($searchResults)): ?>
            <div class="text-center py-12 text-gray-400">
                <i class="fas fa-search text-6xl mb-4 opacity-50"></i>
                <p class="text-xl">Aucun utilisateur trouvé</p>
                <p class="text-sm mt-2">Essayez avec d'autres critères de recherche</p>
            </div>
<?php else: ?>

            <div class="space-y-4">
<?php foreach ($searchResults as $user): ?>
                <div class="bg-dark-700/50 rounded-xl p-6 border border-dark-600 hover:border-dark-500 transition-colors">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-4">

<?php if ($user['avatar_url']): ?>
                                <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Avatar" class="w-16 h-16 rounded-xl object-cover">
<?php else: ?>
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-purple-500 rounded-xl flex items-center justify-center">
                                    <span class="text-white text-xl font-bold">
<?= strtoupper(substr($user['first_name'] ?: $user['username'], 0, 1)) ?>
                                    </span>
                                </div>
<?php endif; ?>


                            <div class="flex-1">
                                <div class="flex items-center space-x-3 mb-2">
                                    <h3 class="text-xl font-bold text-white">
<?= htmlspecialchars($user['username']) ?>
                                    </h3>

                                    <div class="flex items-center space-x-2">
                                        <div class="w-3 h-3 rounded-full <?= $user['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                        <span class="text-sm <?= $user['is_active'] ? 'text-green-400' : 'text-red-400' ?>">
<?= $user['is_active'] ? 'Actif' : 'Inactif' ?>
                                        </span>
                                    </div>

<?php if ($user['admin_role']): ?>
                                    <span class="px-2 py-1 bg-orange-500/20 text-orange-400 text-xs font-bold rounded-full uppercase">
<?= htmlspecialchars($user['admin_role']) ?>
                                    </span>
<?php endif; ?>

<?php if ($user['is_verified']): ?>
                                    <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-full">
                                        <i class="fas fa-check-circle mr-1"></i>Vérifié
                                    </span>
<?php endif; ?>
                                </div>

                                <p class="text-lg text-gray-300 mb-2">
<?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                </p>

                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">
                                    <div>
                                        <span class="text-gray-400">Email:</span>
                                        <p class="text-white font-mono"><?= htmlspecialchars($user['email']) ?></p>
                                    </div>

                                    <div>
                                        <span class="text-gray-400">UUID:</span>
                                        <p class="text-gray-300 font-mono text-xs"><?= htmlspecialchars($user['uuid']) ?></p>
                                    </div>

                                    <div>
                                        <span class="text-gray-400">Solde:</span>
                                        <p class="text-green-400 font-bold"><?= number_format($user['balance'], 2) ?>€</p>
                                    </div>

                                    <div>
                                        <span class="text-gray-400">Dernière IP:</span>
                                        <p class="text-gray-300 font-mono"><?= htmlspecialchars($user['last_ip']) ?></p>
                                    </div>

<?php if ($user['phone']): ?>
                                    <div>
                                        <span class="text-gray-400">Téléphone:</span>
                                        <p class="text-white"><?= htmlspecialchars($user['phone']) ?></p>
                                    </div>
<?php endif; ?>

                                    <div>
                                        <span class="text-gray-400">Inscription:</span>
                                        <p class="text-gray-300"><?= date('d/m/Y', strtotime($user['created_at'])) ?></p>
                                    </div>

                                    <div>
                                        <span class="text-gray-400">Dernière connexion:</span>
                                        <p class="text-gray-300">
<?= $user['last_login_at'] ? date('d/m/Y H:i', strtotime($user['last_login_at'])) : 'Jamais' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <a href="/admin/users/user.php?uuid=<?= htmlspecialchars($user['uuid']) ?>" 
                               class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                                <i class="fas fa-eye mr-2"></i>Voir détails
                            </a>

<?php if ($user['is_active']): ?>
                            <button onclick="confirmConnect(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>')" 
                                    class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                                <i class="fas fa-sign-in-alt mr-2"></i>Se connecter au compte
                            </button>
<?php else: ?>
                            <span class="bg-gray-600 text-gray-300 px-4 py-2 rounded-xl font-medium cursor-not-allowed">
                                <i class="fas fa-ban mr-2"></i>Compte inactif
                            </span>
<?php endif; ?>
                        </div>
                    </div>
                </div>
<?php endforeach; ?>
            </div>

<?php endif; ?>
        </div>
<?php endif; ?>
    </div>
</main>

<div id="confirm-modal" class="hidden fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 w-full max-w-md">
        <div class="text-center">
            <div class="w-16 h-16 bg-orange-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-exclamation-triangle text-orange-400 text-2xl"></i>
            </div>

            <h3 class="text-2xl font-bold text-white mb-4">Confirmation de connexion</h3>

            <div class="mb-6 text-left bg-dark-700/50 rounded-xl p-4">
                <p class="text-gray-300 mb-2"><strong>Utilisateur:</strong> <span id="target-username" class="text-white"></span></p>
                <p class="text-gray-300 mb-2"><strong>Email:</strong> <span id="target-email" class="text-white"></span></p>
                <p class="text-gray-300"><strong>ID:</strong> <span id="target-id" class="text-white"></span></p>
            </div>

            <div class="bg-red-500/20 border border-red-500/50 rounded-xl p-4 mb-6">
                <p class="text-red-300 text-sm">
                    ⚠️ Votre session administrateur sera fermée et vous serez connecté au compte de cet utilisateur.
                    Cette action est loggée et tracée pour des raisons de sécurité.
                </p>
            </div>

            <form id="connect-form" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="connect_to_user">
                <input type="hidden" name="target_user_id" id="target-user-id">

                <div class="flex space-x-3">
                    <button type="button" onclick="hideConfirmModal()" 
                            class="flex-1 bg-gray-600 hover:bg-gray-700 text-white py-3 rounded-xl font-medium transition-colors">
                        Annuler
                    </button>
                    <button type="submit" 
                            class="flex-1 bg-orange-600 hover:bg-orange-700 text-white py-3 rounded-xl font-medium transition-colors">
                        <i class="fas fa-sign-in-alt mr-2"></i>Confirmer la connexion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

function confirmConnect(userId, username, email) {
    document.getElementById('target-user-id').value = userId;
    document.getElementById('target-username').textContent = username;
    document.getElementById('target-email').textContent = email;
    document.getElementById('target-id').textContent = userId;

    document.getElementById('confirm-modal').classList.remove('hidden');
}

function hideConfirmModal() {
    document.getElementById('confirm-modal').classList.add('hidden');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideConfirmModal();
    }
});

document.getElementById('confirm-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        hideConfirmModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.querySelector('input[name="search"]');
    if (searchInput && !searchInput.value) {
        searchInput.focus();
    }
});

document.getElementById('connect-form').addEventListener('submit', function(e) {
    const targetUserId = document.getElementById('target-user-id').value;

    if (!targetUserId || parseInt(targetUserId) <= 0) {
        e.preventDefault();
        alert('ID utilisateur invalide');
        return false;
    }

    if (!confirm('Êtes-vous absolument sûr de vouloir vous connecter au compte de cet utilisateur ?')) {
        e.preventDefault();
        return false;
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Connexion...';
    submitBtn.disabled = true;

    setTimeout(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }, 10000);
});

document.addEventListener('keydown', function(e) {

    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        document.querySelector('input[name="search"]')?.focus();
    }
});

document.querySelector('input[name="search"]').addEventListener('input', function(e) {
    const value = e.target.value;

    const searchTypeSelect = document.querySelector('select[name="search_type"]');

    if (value.includes('@')) {
        searchTypeSelect.value = 'email';
    } else if (value.match(/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i)) {
        searchTypeSelect.value = 'uuid';
    } else if (value.match(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/)) {
        searchTypeSelect.value = 'ip';
    } else if (value.match(/^[\+]?[0-9\s\-\(\)]{8,}$/)) {
        searchTypeSelect.value = 'phone';
    }
});
</script>

<style>

.bg-dark-700\/50 {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#confirm-modal {
    backdrop-filter: blur(8px);
}

#confirm-modal > div {
    animation: modalSlideIn 0.3s ease-out;
}

@keyframes modalSlideIn {
    from {
        opacity: 0;
        transform: scale(0.9) translateY(-20px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }

    .grid-cols-1.md\\:grid-cols-2.lg\\:grid-cols-4 {
        grid-template-columns: 1fr;
    }

    .flex.items-center.justify-between {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }

    .flex.items-center.space-x-3 {
        flex-direction: column;
        space-x: 0;
        width: 100%;
        gap: 0.5rem;
    }

    .flex.items-center.space-x-3 > * {
        width: 100%;
    }
}

.search-highlight {
    background-color: rgba(59, 130, 246, 0.3);
    padding: 0.125rem 0.25rem;
    border-radius: 0.25rem;
}

.loading {
    opacity: 0.6;
    pointer-events: none;
}

input:focus, select:focus, button:focus {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
</style>

<?php
endHTMLPage();
?>