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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        $db->beginTransaction();
        
                $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $firstName = trim($_POST['first_name']);
        $lastName = trim($_POST['last_name']);
        $phone = trim($_POST['phone']);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $isVerified = isset($_POST['is_verified']) ? 1 : 0;
        $balance = floatval($_POST['balance'] ?? 0);
        $sendWelcomeEmail = isset($_POST['send_welcome_email']);
        
                if (empty($username) || empty($email) || empty($password) || empty($firstName) || empty($lastName)) {
            throw new Exception('Les champs obligatoires doivent être remplis');
        }
        
        if (!validateEmail($email)) {
            throw new Exception('Format d\'email invalide');
        }
        
        if (strlen($username) < 3) {
            throw new Exception('Le nom d\'utilisateur doit contenir au moins 3 caractères');
        }
        
        if (strlen($password) < 8) {
            throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
        }
        
        if ($password !== $confirmPassword) {
            throw new Exception('Les mots de passe ne correspondent pas');
        }
        
                if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new Exception('Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores');
        }
        
                $checkStmt = $db->prepare("SELECT id FROM users WHERE email = ?");
        $checkStmt->execute([$email]);
        if ($checkStmt->fetch()) {
            throw new Exception('Un compte existe déjà avec cette adresse email');
        }
        
        $checkStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $checkStmt->execute([$username]);
        if ($checkStmt->fetch()) {
            throw new Exception('Ce nom d\'utilisateur est déjà pris');
        }
        
        if (!empty($phone)) {
            $checkStmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
            $checkStmt->execute([$phone]);
            if ($checkStmt->fetch()) {
                throw new Exception('Un compte existe déjà avec ce numéro de téléphone');
            }
        }
        
                $uuid = generateUUID();
        $passwordData = hashPassword($password);
        
                $insertStmt = $db->prepare("
            INSERT INTO users (
                uuid, username, email, password_hash, password_salt,
                first_name, last_name, phone, balance, 
                is_active, is_verified, profile_completed,
                needs_profile_completion, last_ip, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $profileCompleted = !empty($_POST['address_line1']) && !empty($_POST['city']) ? 1 : 0;
        $needsCompletion = $profileCompleted ? 0 : 1;
        
        $insertStmt->execute([
            $uuid, $username, $email, $passwordData['hash'], $passwordData['salt'],
            $firstName, $lastName, $phone, $balance,
            $isActive, $isVerified, $profileCompleted,
            $needsCompletion, getRealIP()
        ]);
        
        $userId = $db->lastInsertId();
        
                if ($currentAdmin['role'] === 'super_admin') {
            $adminRole = $_POST['admin_role'] ?? '';
            $adminActive = isset($_POST['admin_active']) ? 1 : 0;
            
            if (!empty($adminRole) && in_array($adminRole, ['admin', 'moderator', 'support'])) {
                $adminInsertStmt = $db->prepare("
                    INSERT INTO admin_users (user_id, role, is_active, created_by)
                    VALUES (?, ?, ?, ?)
                ");
                $adminInsertStmt->execute([$userId, $adminRole, $adminActive, $currentAdmin['id']]);
            }
        }
        
                $address1 = trim($_POST['address_line1'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $postalCode = trim($_POST['postal_code'] ?? '');
        $country = trim($_POST['country'] ?? 'FR');
        
        if (!empty($address1) && !empty($city) && !empty($postalCode)) {
            $billingInsertStmt = $db->prepare("
                INSERT INTO users_billing 
                (user_id, first_name, last_name, address_line1, city, postal_code, country, phone, is_default)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $billingInsertStmt->execute([
                $userId, $firstName, $lastName, $address1, $city, $postalCode, $country, $phone
            ]);
        }
        
                try {
            $stmt = $db->prepare("INSERT INTO users_2fa (user_id, secret, is_enabled) VALUES (?, '', 0)");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
                    }
        
                if ($balance > 0) {
            $transactionStmt = $db->prepare("
                INSERT INTO balance_transactions 
                (user_id, type, amount, balance_before, balance_after, source, description, metadata)
                VALUES (?, 'credit', ?, 0.00, ?, ?, ?, ?)
            ");
            
            $source = 'ADMIN_CREATE_' . $currentAdmin['id'] . '_' . time();
            $description = 'Solde initial créé par admin';
            $metadata = json_encode([
                'admin_id' => $currentAdmin['id'],
                'admin_username' => $currentUser['username'],
                'ip' => getRealIP()
            ]);
            
            $transactionStmt->execute([
                $userId, $balance, $balance, $source, $description, $metadata
            ]);
        }
        
                if (!$isVerified) {
            $token = generateSecureToken(32);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400);             
            $tokenStmt = $db->prepare("
                INSERT INTO verification_tokens (user_id, token, type, expires_at) 
                VALUES (?, ?, 'email_verification', ?)
            ");
            $tokenStmt->execute([$userId, $token, $expiresAt]);
        }
        
        $db->commit();
        
                $adminAuth->logAction('user_created', 'user', $userId, null, [
            'username' => $username,
            'email' => $email,
            'created_by_admin' => $currentUser['username'],
            'initial_balance' => $balance,
            'admin_role' => $adminRole ?? null
        ], 'medium');
        
        $success = "Utilisateur créé avec succès";
        
                header("Location: /admin/users/user.php?uuid=" . urlencode($uuid) . "&success=" . urlencode($success));
        exit();
        
    } catch (Exception $e) {
        if (isset($db) && $db->inTransaction()) {
            $db->rollback();
        }
        $error = $e->getMessage();
        if (isset($adminAuth) && isset($userId)) {
            $adminAuth->logAction('user_create_failed', 'user', $userId, null, ['error' => $e->getMessage()], 'high');
        }
    }
}

$seo = [
    'title' => 'Créer un utilisateur - Admin Pilote Shopping',
    'description' => 'Création d\'un nouvel utilisateur',
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
                <span class="text-white">Créer</span>
            </nav>
        </div>

        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                <i class="fas fa-user-plus text-green-400 mr-3"></i>
                Créer un utilisateur
            </h1>
            <p class="text-gray-400">Création d'un nouveau compte utilisateur</p>
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
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               placeholder="john_doe"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                        <p class="text-gray-400 text-sm mt-1">3 caractères minimum, lettres, chiffres, - et _ uniquement</p>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Email <span class="text-red-400">*</span>
                        </label>
                        <input type="email" name="email" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                               placeholder="john@exemple.com"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Mot de passe <span class="text-red-400">*</span>
                        </label>
                        <input type="password" name="password" required
                               placeholder="••••••••"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                        <p class="text-gray-400 text-sm mt-1">8 caractères minimum</p>
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Confirmer le mot de passe <span class="text-red-400">*</span>
                        </label>
                        <input type="password" name="confirm_password" required
                               placeholder="••••••••"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Prénom <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="first_name" required
                               value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                               placeholder="John"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">
                            Nom <span class="text-red-400">*</span>
                        </label>
                        <input type="text" name="last_name" required
                               value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                               placeholder="Doe"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Téléphone</label>
                        <input type="tel" name="phone"
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                               placeholder="+33123456789"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Solde initial (€)</label>
                        <input type="number" name="balance" step="0.01" min="0"
                               value="<?= htmlspecialchars($_POST['balance'] ?? '0.00') ?>"
                               placeholder="0.00"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>
                </div>

                <div class="mt-6 space-y-4">
                    <div class="flex items-center space-x-3">
                        <input type="checkbox" name="is_active" id="is_active" checked
                               class="w-4 h-4 text-blue-600 bg-dark-700 border-dark-600 rounded focus:ring-blue-500 focus:ring-2">
                        <label for="is_active" class="text-gray-300">Compte actif</label>
                    </div>

                    <div class="flex items-center space-x-3">
                        <input type="checkbox" name="is_verified" id="is_verified"
                               class="w-4 h-4 text-blue-600 bg-dark-700 border-dark-600 rounded focus:ring-blue-500 focus:ring-2">
                        <label for="is_verified" class="text-gray-300">Email vérifié (sinon un email de vérification sera envoyé)</label>
                    </div>

                    <div class="flex items-center space-x-3">
                        <input type="checkbox" name="send_welcome_email" id="send_welcome_email"
                               class="w-4 h-4 text-blue-600 bg-dark-700 border-dark-600 rounded focus:ring-blue-500 focus:ring-2">
                        <label for="send_welcome_email" class="text-gray-300">Envoyer un email de bienvenue</label>
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
                            <option value="admin">Administrateur</option>
                            <option value="moderator">Modérateur</option>
                            <option value="support">Support</option>
                        </select>
                    </div>

                    <div class="flex items-center space-x-3 mt-8">
                        <input type="checkbox" name="admin_active" id="admin_active" checked
                               class="w-4 h-4 text-orange-600 bg-dark-700 border-dark-600 rounded focus:ring-orange-500 focus:ring-2">
                        <label for="admin_active" class="text-gray-300">Rôle admin actif</label>
                    </div>
                </div>
            </div>
<?php endif; ?>


            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h2 class="text-xl font-bold text-white mb-6">
                    <i class="fas fa-map-marker-alt text-green-400 mr-2"></i>
                    Adresse de facturation (optionnelle)
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div class="md:col-span-2">
                        <label class="block text-gray-300 font-medium mb-2">Adresse</label>
                        <input type="text" name="address_line1"
                               value="<?= htmlspecialchars($_POST['address_line1'] ?? '') ?>"
                               placeholder="123 Rue de la Paix"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Ville</label>
                        <input type="text" name="city"
                               value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                               placeholder="Paris"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Code postal</label>
                        <input type="text" name="postal_code"
                               value="<?= htmlspecialchars($_POST['postal_code'] ?? '') ?>"
                               placeholder="75001"
                               pattern="[0-9]{5}"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500 transition-colors">
                    </div>

                    <div>
                        <label class="block text-gray-300 font-medium mb-2">Pays</label>
                        <select name="country" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500 transition-colors">
                            <option value="FR">France</option>
                            <option value="BE">Belgique</option>
                            <option value="CH">Suisse</option>
                            <option value="LU">Luxembourg</option>
                            <option value="CA">Canada</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 p-4 bg-blue-500/10 border border-blue-500/20 rounded-xl">
                    <p class="text-blue-400 text-sm">
                        <i class="fas fa-info-circle mr-2"></i>
                        Si vous renseignez une adresse complète, le profil sera marqué comme complet automatiquement.
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-between bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center space-x-4">
                    <a href="/admin/users/" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Retour à la liste
                    </a>
                </div>

                <button type="submit" 
                        class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-xl font-medium transition-colors">
                    <i class="fas fa-plus mr-2"></i>
                    Créer l'utilisateur
                </button>
            </div>
        </form>
    </div>
</main>

<script>

document.querySelector('form').addEventListener('submit', function(e) {
    const username = document.querySelector('input[name="username"]').value.trim();
    const email = document.querySelector('input[name="email"]').value.trim();
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    const firstName = document.querySelector('input[name="first_name"]').value.trim();
    const lastName = document.querySelector('input[name="last_name"]').value.trim();

    if (!username || !email || !password || !firstName || !lastName) {
        e.preventDefault();
        alert('Veuillez remplir tous les champs obligatoires');
        return false;
    }

    if (username.length < 3) {
        e.preventDefault();
        alert('Le nom d\'utilisateur doit contenir au moins 3 caractères');
        return false;
    }

    if (password.length < 8) {
        e.preventDefault();
        alert('Le mot de passe doit contenir au moins 8 caractères');
        return false;
    }

    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Les mots de passe ne correspondent pas');
        return false;
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Format d\'email invalide');
        return false;
    }

    const usernameRegex = /^[a-zA-Z0-9_-]+$/;
    if (!usernameRegex.test(username)) {
        e.preventDefault();
        alert('Le nom d\'utilisateur ne peut contenir que des lettres, chiffres, tirets et underscores');
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

function generateUsername() {
    const firstName = document.querySelector('input[name="first_name"]').value.trim().toLowerCase();
    const lastName = document.querySelector('input[name="last_name"]').value.trim().toLowerCase();
    const usernameField = document.querySelector('input[name="username"]');

    if (firstName && lastName && !usernameField.value) {
        let suggestion = firstName + '_' + lastName;
        suggestion = suggestion.replace(/[^a-z0-9_-]/g, '');
        usernameField.value = suggestion;
    }
}

document.querySelector('input[name="first_name"]').addEventListener('blur', generateUsername);
document.querySelector('input[name="last_name"]').addEventListener('blur', generateUsername);

document.querySelector('input[name="confirm_password"]').addEventListener('input', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = e.target.value;

    if (password && confirmPassword) {
        if (password === confirmPassword) {
            e.target.style.borderColor = '#10B981';
        } else {
            e.target.style.borderColor = '#EF4444';
        }
    }
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

input:valid {
    border-color: #10B981;
}

input:invalid:not(:placeholder-shown) {
    border-color: #EF4444;
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