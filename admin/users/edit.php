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
    
        
    $uuid = $_GET['uuid'] ?? '';
    if (empty($uuid)) {
        header('Location: /admin/users/?error=UUID utilisateur requis');
        exit();
    }
    
        $userStmt = $db->prepare("
        SELECT u.*, 
               au.role as admin_role, au.is_active as admin_active,
               au.id as admin_id
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
    
        $billingStmt = $db->prepare("
        SELECT * FROM users_billing 
        WHERE user_id = ? AND is_default = 1
    ");
    $billingStmt->execute([$user['id']]);
    $billing = $billingStmt->fetch();
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db->beginTransaction();
            
                        $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $phone = trim($_POST['phone']);
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $isVerified = isset($_POST['is_verified']) ? 1 : 0;
            $balance = floatval($_POST['balance']);
            
                        if (empty($username) || empty($email) || empty($firstName) || empty($lastName)) {
                throw new Exception('Les champs obligatoires doivent être remplis');
            }
            
            if (!validateEmail($email)) {
                throw new Exception('Format d\'email invalide');
            }
            
            if (strlen($username) < 3) {
                throw new Exception('Le nom d\'utilisateur doit contenir au moins 3 caractères');
            }
            
                        $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $checkStmt->execute([$email, $user['id']]);
            if ($checkStmt->fetch()) {
                throw new Exception('Cet email est déjà utilisé par un autre compte');
            }
            
            $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $checkStmt->execute([$username, $user['id']]);
            if ($checkStmt->fetch()) {
                throw new Exception('Ce nom d\'utilisateur est déjà pris');
            }
            
                        $oldData = [
                'username' => $user['username'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'phone' => $user['phone'],
                'is_active' => $user['is_active'],
                'is_verified' => $user['is_verified'],
                'balance' => $user['balance']
            ];
            
                        $updateStmt = $db->prepare("
                UPDATE users 
                SET username = ?, email = ?, first_name = ?, last_name = ?, 
                    phone = ?, is_active = ?, is_verified = ?, balance = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([
                $username, $email, $firstName, $lastName,
                $phone, $isActive, $isVerified, $balance,
                $user['id']
            ]);
            
                        if ($currentAdmin['role'] === 'super_admin') {
                $adminRole = $_POST['admin_role'] ?? '';
                $adminActive = isset($_POST['admin_active']) ? 1 : 0;
                
                if (!empty($adminRole) && in_array($adminRole, ['admin', 'moderator', 'support'])) {
                                        if ($user['admin_id']) {
                        $adminUpdateStmt = $db->prepare("
                            UPDATE admin_users 
                            SET role = ?, is_active = ?, updated_at = NOW()
                            WHERE id = ?
                        ");
                        $adminUpdateStmt->execute([$adminRole, $adminActive, $user['admin_id']]);
                    } else {
                        $adminInsertStmt = $db->prepare("
                            INSERT INTO admin_users (user_id, role, is_active, created_by)
                            VALUES (?, ?, ?, ?)
                        ");
                        $adminInsertStmt->execute([$user['id'], $adminRole, $adminActive, $currentAdmin['id']]);
                    }
                } else if ($user['admin_id']) {
                                        $adminDeleteStmt = $db->prepare("DELETE FROM admin_users WHERE id = ?");
                    $adminDeleteStmt->execute([$user['admin_id']]);
                }
            }
            
                        $address1 = trim($_POST['address_line1'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $postalCode = trim($_POST['postal_code'] ?? '');
            $country = trim($_POST['country'] ?? 'FR');
            
            if (!empty($address1) && !empty($city) && !empty($postalCode)) {
                if ($billing) {
                                        $billingUpdateStmt = $db->prepare("
                        UPDATE users_billing 
                        SET first_name = ?, last_name = ?, address_line1 = ?, 
                            city = ?, postal_code = ?, country = ?, phone = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $billingUpdateStmt->execute([
                        $firstName, $lastName, $address1, $city, $postalCode, $country, $phone,
                        $billing['id']
                    ]);
                } else {
                                        $billingInsertStmt = $db->prepare("
                        INSERT INTO users_billing 
                        (user_id, first_name, last_name, address_line1, city, postal_code, country, phone, is_default)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    $billingInsertStmt->execute([
                        $user['id'], $firstName, $lastName, $address1, $city, $postalCode, $country, $phone
                    ]);
                }
            }
            
                        if ($balance != $user['balance']) {
                $balanceDiff = $balance - $user['balance'];
                $transactionType = $balanceDiff > 0 ? 'credit' : 'debit';
                $transactionAmount = abs($balanceDiff);
                
                $transactionStmt = $db->prepare("
                    INSERT INTO balance_transactions 
                    (user_id, type, amount, balance_before, balance_after, source, description, metadata)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $source = 'ADMIN_EDIT_' . $currentAdmin['id'] . '_' . time();
                $description = 'Modification du solde par admin';
                $metadata = json_encode([
                    'admin_id' => $currentAdmin['id'],
                    'admin_username' => $currentUser['username'],
                    'old_balance' => $user['balance'],
                    'new_balance' => $balance,
                    'ip' => getRealIP()
                ]);
                
                $transactionStmt->execute([
                    $user['id'], $transactionType, $transactionAmount, 
                    $user['balance'], $balance, $source, $description, $metadata
                ]);
            }
            
            $db->commit();
            
                        $newData = [
                'username' => $username,
                'email' => $email,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $phone,
                'is_active' => $isActive,
                'is_verified' => $isVerified,
                'balance' => $balance
            ];
            
                        $adminAuth->logAction('user_updated', 'user', $user['id'], $oldData, $newData, 'medium');
            
            $success = "Utilisateur modifié avec succès";
            
                        header("Location: /admin/users/user.php?uuid=" . urlencode($uuid) . "&success=" . urlencode($success));
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error = $e->getMessage();
            $adminAuth->logAction('user_update_failed', 'user', $user['id'], null, ['error' => $e->getMessage()], 'high');
        }
    }
    
} catch (Exception $e) {
    error_log("Admin user edit error: " . $e->getMessage());
    header('Location: /admin/users/?error=Erreur lors du chargement de l\'utilisateur');
    exit();
}

$seo = [
    'title' => 'Éditer ' . $user['username'] . ' - Admin Pilote Shopping',
    'description' => 'Édition utilisateur admin',
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
                <a href="/admin/users/user.php?uuid=<?= htmlspecialchars($uuid) ?>" class="hover:text-white transition-colors"><?= htmlspecialchars($user['username']) ?></a>
                <i class="fas fa-chevron-right text-xs"></i>
                <span class="text-white">Éditer</span>
            </nav>
        </div>

        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                <i class="fas fa-user-edit text-blue-400 mr-3"></i>
                Éditer l'utilisateur
            </h1>
            <p class="text-gray-400">Modification des informations de <?= htmlspecialchars($user['username']) ?></p>
        </div>

<?php if (isset($error)): ?>
        <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-xl">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <form method="POST" class="space-y-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h2 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-user text-blue-400 mr-2"></i>
                    Informations générales
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Nom d'utilisateur <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="username" required
                               value="<?= htmlspecialchars($user['username']) ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Email <span class="text-red-400">*</span>
                        </label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($user['email']) ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Prénom <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="first_name" required
                               value="<?= htmlspecialchars($user['first_name']) ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Nom <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="last_name" required
                               value="<?= htmlspecialchars($user['last_name']) ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Téléphone</label>
                        <input type="tel" name="phone"
                               value="<?= htmlspecialchars($user['phone']) ?>"
                               placeholder="+33123456789"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Solde (€)</label>
                        <input type="number" name="balance" step="0.01" min="0"
                               value="<?= number_format($user['balance'], 2, '.', '') ?>"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    <div class="flex items-center space-x-3">
                        <input type="checkbox" name="is_active" id="is_active" 
<?= $user['is_active'] ? 'checked' : '' ?>
                               class="w-4 h-4 text-blue-600 bg-dark-700 border-dark-600 rounded focus:ring-blue-500 focus:ring-2">
                        <label for="is_active" class="text-gray-300">Compte actif</label>
                    </div>

                    <div class="flex items-center space-x-3">
                        <input type="checkbox" name="is_verified" id="is_verified" 
<?= $user['is_verified'] ? 'checked' : '' ?>
                               class="w-4 h-4 text-blue-600 bg-dark-700 border-dark-600 rounded focus:ring-blue-500 focus:ring-2">
                        <label for="is_verified" class="text-gray-300">Email vérifié</label>
                    </div>
                </div>
            </div>

<?php if ($currentAdmin['role'] === 'super_admin'): ?>
            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h2 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-shield-alt text-orange-400 mr-2"></i>
                    Rôles administrateur
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Rôle admin</label>
                        <select name="admin_role" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="">Aucun rôle admin</option>
                            <option value="admin" <?= $user['admin_role'] === 'admin' ? 'selected' : '' ?>>Administrateur</option>
                            <option value="moderator" <?= $user['admin_role'] === 'moderator' ? 'selected' : '' ?>>Modérateur</option>
                            <option value="support" <?= $user['admin_role'] === 'support' ? 'selected' : '' ?>>Support</option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-3 mt-8">
                        <input type="checkbox" name="admin_active" id="admin_active" 
<?= $user['admin_active'] ? 'checked' : '' ?>
                               class="w-4 h-4 text-orange-600 bg-dark-700 border-dark-600 rounded focus:ring-orange-500 focus:ring-2">
                        <label for="admin_active" class="text-gray-300">Rôle admin actif</label>
                    </div>
                </div>
            </div>
<?php endif; ?>


            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h2 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-map-marker-alt text-green-400 mr-2"></i>
                    Adresse de facturation
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">Adresse</label>
                        <input type="text" name="address_line1"
                               value="<?= htmlspecialchars($billing['address_line1'] ?? '') ?>"
                               placeholder="123 Rue de la Paix"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Ville</label>
                        <input type="text" name="city"
                               value="<?= htmlspecialchars($billing['city'] ?? '') ?>"
                               placeholder="Paris"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Code postal</label>
                        <input type="text" name="postal_code"
                               value="<?= htmlspecialchars($billing['postal_code'] ?? '') ?>"
                               placeholder="75001"
                               pattern="[0-9]{5}"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Pays</label>
                        <select name="country" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="FR" <?= ($billing['country'] ?? 'FR') === 'FR' ? 'selected' : '' ?>>France</option>
                            <option value="BE" <?= ($billing['country'] ?? '') === 'BE' ? 'selected' : '' ?>>Belgique</option>
                            <option value="CH" <?= ($billing['country'] ?? '') === 'CH' ? 'selected' : '' ?>>Suisse</option>
                            <option value="LU" <?= ($billing['country'] ?? '') === 'LU' ? 'selected' : '' ?>>Luxembourg</option>
                            <option value="CA" <?= ($billing['country'] ?? '') === 'CA' ? 'selected' : '' ?>>Canada</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center space-x-4">
                    <a href="/admin/users/user.php?uuid=<?= htmlspecialchars($uuid) ?>" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Annuler
                    </a>
                </div>

                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-medium transition-colors">
                    <i class="fas fa-save mr-2"></i>
                    Sauvegarder les modifications
                </button>
            </div>
        </form>
    </div>
</main>

<script>

document.querySelector('form').addEventListener('submit', function(e) {
    const username = document.querySelector('input[name="username"]').value.trim();
    const email = document.querySelector('input[name="email"]').value.trim();
    const firstName = document.querySelector('input[name="first_name"]').value.trim();
    const lastName = document.querySelector('input[name="last_name"]').value.trim();

    if (!username || !email || !firstName || !lastName) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires');
        return false;
    }

    if (username.length < 3) {
        e.preventDefault();
        alert('Le nom d\'utilisateur doit contenir au moins 3 caractères');
        return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Format d\'email invalide');
        return false;
    }
});

document.querySelector('input[name="phone"]').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.startsWith('33')) {
        value = '+' + value;
    } else if (value.startsWith('0') && value.length === 10) {
        value = '+33' + value.substring(1);
    }
    e.target.value = value;
});

document.querySelector('input[name="postal_code"]').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/\D/g, '').substring(0, 5);
});
</script>

<style>

input[type="checkbox"]:checked {
    background-color: #3B82F6;
    border-color: #3B82F6;
}

input[type="checkbox"]:focus {
    ring-color: #3B82F6;
}

form > div {
    animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    .grid-cols-1.md\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }

    .flex.items-center.justify-between {
        flex-direction: column;
        gap: 1rem;
    }
}
</style>

<?php
endHTMLPage();
?>