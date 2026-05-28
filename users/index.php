<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';

$requestedUsername = $_GET['username'] ?? null;

if (!$requestedUsername) {
    header('Location: /');
    exit();
}

try {
    $db = Database::getInstance();

        $stmt = $db->prepare("
        SELECT
            u.*,
            COALESCE(uo.provider, 'local') as auth_provider
        FROM users u
        LEFT JOIN users_oauth uo ON u.id = uo.user_id
        WHERE u.username = ? AND u.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([$requestedUsername]);
    $profileUser = $stmt->fetch();

    if (!$profileUser) {
        header('Location: /404');
        exit();
    }

        $gravatarHash = md5(strtolower(trim($profileUser['email'])));
    $gravatarUrl = "https://www.gravatar.com/avatar/{$gravatarHash}?s=400&d=mp";

        if (!empty($profileUser['avatar_url'])) {
        $avatarUrl = htmlspecialchars($profileUser['avatar_url']);
        $isGravatar = false;
    } else {
        $avatarUrl = $gravatarUrl;
        $isGravatar = true;
    }

        $isAdmin = false;
    try {
        $adminStmt = $db->prepare("
            SELECT role FROM admin_users WHERE user_id = ? AND is_active = 1 LIMIT 1
        ");
        $adminStmt->execute([$profileUser['id']]);
        $adminData = $adminStmt->fetch();
        if ($adminData) {
            $isAdmin = true;
            $adminRole = $adminData['role'];
        }
    } catch (Exception $e) {
        error_log("Admin check error: " . $e->getMessage());
    }

        $privacySettings = [
        'hide_full_name' => 0,
        'hide_email' => 1,
        'hide_phone' => 1,
        'hide_stats' => 0,
        'hide_member_since' => 0,
        'hide_last_activity' => 0
    ];

    try {
        $privacyStmt = $db->prepare("
            SELECT * FROM user_privacy_settings WHERE user_id = ? LIMIT 1
        ");
        $privacyStmt->execute([$profileUser['id']]);
        $privacyData = $privacyStmt->fetch();
        if ($privacyData) {
            $privacySettings = [
                'hide_full_name' => (bool)$privacyData['hide_full_name'],
                'hide_email' => (bool)$privacyData['hide_email'],
                'hide_phone' => (bool)$privacyData['hide_phone'],
                'hide_stats' => (bool)$privacyData['hide_stats'],
                'hide_member_since' => (bool)$privacyData['hide_member_since'],
                'hide_last_activity' => (bool)$privacyData['hide_last_activity']
            ];
        }
    } catch (Exception $e) {
        error_log("Privacy settings error: " . $e->getMessage());
    }

        if ($privacySettings['hide_full_name'] && !$isOwnProfile) {
        $displayName = '@' . htmlspecialchars($profileUser['username']);
        $showFullName = false;
    } else {
        $displayName = htmlspecialchars($profileUser['first_name'] . ' ' . $profileUser['last_name']);
        $showFullName = true;
    }

        $stats = [
        'total_orders' => 0,
        'total_spent' => 0.00,
        'member_since' => $profileUser['created_at'],
        'active_subscriptions' => 0,
        'completed_orders' => 0,
        'last_order_date' => null
    ];

        $statsStmt = $db->prepare("
        SELECT
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.id END) as completed_orders,
            COALESCE(SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END), 0) as total_spent,
            MAX(o.created_at) as last_order_date
        FROM orders o
        WHERE o.user_id = ?
    ");
    $statsStmt->execute([$profileUser['id']]);
    $orderStats = $statsStmt->fetch();

    if ($orderStats) {
        $stats['total_orders'] = $orderStats['total_orders'];
        $stats['completed_orders'] = $orderStats['completed_orders'];
        $stats['total_spent'] = $orderStats['total_spent'];
        $stats['last_order_date'] = $orderStats['last_order_date'];
    }

        $subsStmt = $db->prepare("
        SELECT COUNT(*) as active_subscriptions
        FROM subscriptions
        WHERE user_id = ? AND status = 'active'
    ");
    $subsStmt->execute([$profileUser['id']]);
    $subsResult = $subsStmt->fetch();
    $stats['active_subscriptions'] = $subsResult['active_subscriptions'];

        $isOwnProfile = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profileUser['id'];

        $memberSince = new DateTime($stats['member_since']);
    $now = new DateTime();
    $interval = $memberSince->diff($now);

    if ($interval->y > 0) {
        $memberDuration = $interval->y . ' an' . ($interval->y > 1 ? 's' : '');
    } elseif ($interval->m > 0) {
        $memberDuration = $interval->m . ' mois';
    } else {
        $memberDuration = $interval->d . ' jour' . ($interval->d > 1 ? 's' : '');
    }

        $gravatarProfileUrl = "https://www.gravatar.com/{$gravatarHash}.json";

} catch (Exception $e) {
    error_log("Profile page error: " . $e->getMessage());
    header('Location: /404');
    exit();
}

$seo = [
    'title' => $displayName . ' - Pilote Shopping',
    'description' => 'Profil de ' . $displayName . ' sur Pilote Shopping',
    'keywords' => 'profil, utilisateur, ' . htmlspecialchars($profileUser['username']) . ', pilote shopping',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen">

    <section class="py-8">
        <div class="container mx-auto px-4 lg:px-6">
            <div class="max-w-6xl mx-auto">

                <div class="relative overflow-hidden rounded-2xl border border-dark-600 bg-gradient-to-br from-dark-800/80 to-dark-900/80 backdrop-blur-xl p-6 mb-6" data-aos="fade-down">

                    <div class="absolute inset-0 opacity-5">
                        <div class="absolute inset-0" style="background-image: radial-gradient(circle, #60a5fa 1px, transparent 1px); background-size: 20px 20px;"></div>
                    </div>

                    <div class="relative z-10">
                        <div class="flex flex-col md:flex-row items-center md:items-start gap-6">

                            <div class="relative group">
                                <div class="absolute -inset-1 bg-gradient-to-r from-blue-primary to-purple-500 rounded-full blur opacity-75 group-hover:opacity-100 transition duration-300"></div>
                                <img src="<?= $avatarUrl ?>"
                                     alt="Avatar de <?= $showFullName ? htmlspecialchars($profileUser['first_name']) : htmlspecialchars($profileUser['username']) ?>"
                                     class="relative w-28 h-28 rounded-full object-cover border-4 border-dark-800 shadow-2xl"
                                     onerror="this.src='<?= $gravatarUrl ?>'">

<?php if ($profileUser['is_verified'] || $profileUser['email_verified_at']): ?>
                                <div class="absolute bottom-1 right-1 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center border-3 border-dark-800 shadow-lg">
                                    <i class="fas fa-check text-white text-xs"></i>
                                </div>
<?php endif; ?>


<?php if ($isAdmin): ?>
                                <div class="absolute top-1 right-1 w-8 h-8 bg-red-500 rounded-full flex items-center justify-center border-3 border-dark-800 shadow-lg" title="Administrateur">
                                    <i class="fas fa-shield-alt text-white text-xs"></i>
                                </div>
<?php endif; ?>
                            </div>

                            <div class="flex-1 text-center md:text-left">
                                <h1 class="text-2xl md:text-3xl font-bold text-white mb-1">
<?= $displayName ?>
                                </h1>

<?php if (!$privacySettings['hide_full_name'] || $isOwnProfile): ?>
                                <p class="text-lg text-gray-400 mb-3">
                                    <i class="fas fa-at mr-1"></i><?= htmlspecialchars($profileUser['username']) ?>
                                </p>
<?php endif; ?>


                                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2 mb-4">

<?php if ($isAdmin): ?>
                                    <span class="inline-flex items-center gap-1.5 bg-red-500/20 border border-red-500/30 text-red-400 px-3 py-1.5 rounded-full text-xs font-medium">
                                        <i class="fas fa-shield-alt"></i>
                                        Administrateur
                                    </span>
<?php endif; ?>


<?php if (!$privacySettings['hide_member_since'] || $isOwnProfile): ?>
                                    <span class="inline-flex items-center gap-1.5 bg-blue-500/20 border border-blue-500/30 text-blue-400 px-3 py-1.5 rounded-full text-xs font-medium">
                                        <i class="fas fa-user-check"></i>
                                        Membre depuis <?= $memberDuration ?>
                                    </span>
<?php endif; ?>


<?php if ($profileUser['is_verified'] || $profileUser['email_verified_at']): ?>
                                    <span class="inline-flex items-center gap-1.5 bg-green-500/20 border border-green-500/30 text-green-400 px-3 py-1.5 rounded-full text-xs font-medium">
                                        <i class="fas fa-shield-check"></i>
                                        Vérifié
                                    </span>
<?php endif; ?>


<?php if ($stats['active_subscriptions'] > 0 && (!$privacySettings['hide_stats'] || $isOwnProfile)): ?>
                                    <span class="inline-flex items-center gap-1.5 bg-purple-500/20 border border-purple-500/30 text-purple-400 px-3 py-1.5 rounded-full text-xs font-medium">
                                        <i class="fas fa-crown"></i>
                                        Abonné actif
                                    </span>
<?php endif; ?>
                                </div>

<?php if ($isOwnProfile): ?>
                                <div class="flex flex-wrap items-center justify-center md:justify-start gap-2">
                                    <a href="/dashboard" class="inline-flex items-center gap-2 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white font-medium py-2 px-4 rounded-lg transition-all duration-300 transform hover:scale-105 text-sm">
                                        <i class="fas fa-columns"></i>
                                        Dashboard
                                    </a>
                                    <a href="/settings" class="inline-flex items-center gap-2 bg-dark-700 hover:bg-dark-600 text-white font-medium py-2 px-4 rounded-lg transition-all duration-300 border border-dark-600 text-sm">
                                        <i class="fas fa-cog"></i>
                                        Paramètres
                                    </a>
                                </div>
<?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

<?php if (!$privacySettings['hide_stats'] || $isOwnProfile): ?>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" data-aos="fade-up">

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 p-4 hover:border-blue-primary/50 transition-all duration-300 group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-xs font-medium mb-1">Commandes</p>
                                <p class="text-2xl font-bold text-white group-hover:text-blue-primary transition-colors">
<?= $stats['total_orders'] ?>
                                </p>
                                <p class="text-gray-500 text-xs mt-0.5">
<?= $stats['completed_orders'] ?> terminées
                                </p>
                            </div>
                            <div class="w-10 h-10 bg-blue-primary/20 rounded-lg flex items-center justify-center group-hover:bg-blue-primary/30 transition-colors">
                                <i class="fas fa-shopping-bag text-blue-primary"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 p-4 hover:border-green-500/50 transition-all duration-300 group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-xs font-medium mb-1">Dépenses</p>
                                <p class="text-2xl font-bold text-white group-hover:text-green-400 transition-colors">
<?= number_format($stats['total_spent'], 0) ?>€
                                </p>
                                <p class="text-gray-500 text-xs mt-0.5">Total</p>
                            </div>
                            <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center group-hover:bg-green-500/30 transition-colors">
                                <i class="fas fa-euro-sign text-green-400"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 p-4 hover:border-purple-500/50 transition-all duration-300 group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-xs font-medium mb-1">Abonnements</p>
                                <p class="text-2xl font-bold text-white group-hover:text-purple-400 transition-colors">
<?= $stats['active_subscriptions'] ?>
                                </p>
                                <p class="text-gray-500 text-xs mt-0.5">Actifs</p>
                            </div>
                            <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center group-hover:bg-purple-500/30 transition-colors">
                                <i class="fas fa-sync-alt text-purple-400"></i>
                            </div>
                        </div>
                    </div>

<?php if ($isOwnProfile): ?>
                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 p-4 hover:border-yellow-500/50 transition-all duration-300 group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-xs font-medium mb-1">Solde</p>
                                <p class="text-2xl font-bold text-white group-hover:text-yellow-400 transition-colors">
<?= number_format($profileUser['balance'], 2) ?>€
                                </p>
                                <p class="text-gray-500 text-xs mt-0.5">Disponible</p>
                            </div>
                            <div class="w-10 h-10 bg-yellow-500/20 rounded-lg flex items-center justify-center group-hover:bg-yellow-500/30 transition-colors">
                                <i class="fas fa-wallet text-yellow-400"></i>
                            </div>
                        </div>
                    </div>
<?php else: ?>

<?php if (!$privacySettings['hide_member_since']): ?>
                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 p-4 hover:border-gray-500/50 transition-all duration-300 group">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-xs font-medium mb-1">Membre</p>
                                <p class="text-lg font-bold text-white group-hover:text-gray-300 transition-colors">
<?= date('M Y', strtotime($stats['member_since'])) ?>
                                </p>
                                <p class="text-gray-500 text-xs mt-0.5">
<?= $memberDuration ?>
                                </p>
                            </div>
                            <div class="w-10 h-10 bg-gray-500/20 rounded-lg flex items-center justify-center group-hover:bg-gray-500/30 transition-colors">
                                <i class="fas fa-calendar text-gray-400"></i>
                            </div>
                        </div>
                    </div>
<?php endif; ?>
<?php endif; ?>

                </div>
<?php endif; ?>


                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 p-5" data-aos="fade-up">
                        <h2 class="text-lg font-bold text-white mb-4">
                            <i class="fas fa-id-card text-blue-primary mr-2"></i>
                            Informations du compte
                        </h2>

                        <div class="space-y-3">

<?php if ($showFullName || $isOwnProfile): ?>
                            <div class="flex items-center justify-between py-2 border-b border-dark-600">
                                <span class="text-gray-400 flex items-center gap-2 text-sm">
                                    <i class="fas fa-user w-4"></i>
                                    Nom complet
                                </span>
                                <span class="text-white font-medium text-sm">
<?= htmlspecialchars($profileUser['first_name'] . ' ' . $profileUser['last_name']) ?>
                                </span>
                            </div>
<?php endif; ?>


                            <div class="flex items-center justify-between py-2 border-b border-dark-600">
                                <span class="text-gray-400 flex items-center gap-2 text-sm">
                                    <i class="fas fa-at w-4"></i>
                                    Pseudo
                                </span>
                                <span class="text-white font-medium text-sm">
<?= htmlspecialchars($profileUser['username']) ?>
                                </span>
                            </div>

<?php if ($isOwnProfile || (!$privacySettings['hide_email'] && !$isOwnProfile)): ?>
                            <div class="flex items-center justify-between py-2 border-b border-dark-600">
                                <span class="text-gray-400 flex items-center gap-2 text-sm">
                                    <i class="fas fa-envelope w-4"></i>
                                    Email
                                </span>
                                <span class="text-white font-medium text-sm">
<?= htmlspecialchars($profileUser['email']) ?>
                                </span>
                            </div>
<?php endif; ?>


<?php if ($profileUser['phone'] && ($isOwnProfile || !$privacySettings['hide_phone'])): ?>
                            <div class="flex items-center justify-between py-2 border-b border-dark-600">
                                <span class="text-gray-400 flex items-center gap-2 text-sm">
                                    <i class="fas fa-phone w-4"></i>
                                    Téléphone
                                </span>
                                <span class="text-white font-medium text-sm">
<?= htmlspecialchars($profileUser['phone']) ?>
                                </span>
                            </div>
<?php endif; ?>


<?php if (!$privacySettings['hide_member_since'] || $isOwnProfile): ?>
                            <div class="flex items-center justify-between py-2 border-b border-dark-600">
                                <span class="text-gray-400 flex items-center gap-2 text-sm">
                                    <i class="fas fa-calendar-plus w-4"></i>
                                    Inscription
                                </span>
                                <span class="text-white font-medium text-sm">
<?= date('d/m/Y', strtotime($profileUser['created_at'])) ?>
                                </span>
                            </div>
<?php endif; ?>


<?php if ($isOwnProfile && $profileUser['last_login_at'] && !$privacySettings['hide_last_activity']): ?>
                            <div class="flex items-center justify-between py-2">
                                <span class="text-gray-400 flex items-center gap-2 text-sm">
                                    <i class="fas fa-sign-in-alt w-4"></i>
                                    Dernière connexion
                                </span>
                                <span class="text-white font-medium text-sm">
<?= date('d/m/Y à H:i', strtotime($profileUser['last_login_at'])) ?>
                                </span>
                            </div>
<?php endif; ?>
                        </div>
                    </div>

<?php if (!$privacySettings['hide_last_activity'] || $isOwnProfile): ?>
                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 p-5" data-aos="fade-up" data-aos-delay="100">
                        <h2 class="text-lg font-bold text-white mb-4">
                            <i class="fas fa-chart-line text-blue-primary mr-2"></i>
                            Activité récente
                        </h2>

                        <div class="space-y-3">

<?php if (!$privacySettings['hide_stats'] || $isOwnProfile): ?>
                            <div class="flex items-center gap-3 p-3 bg-dark-700/50 rounded-lg hover:bg-dark-700 transition-colors">
                                <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-shopping-cart text-blue-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-white font-medium text-sm"><?= $stats['total_orders'] ?> commande<?= $stats['total_orders'] > 1 ? 's' : '' ?></p>
                                    <p class="text-gray-400 text-xs">Total des achats</p>
                                </div>
                            </div>
<?php endif; ?>


<?php if ($stats['last_order_date'] && (!$privacySettings['hide_stats'] || $isOwnProfile)): ?>
                            <div class="flex items-center gap-3 p-3 bg-dark-700/50 rounded-lg hover:bg-dark-700 transition-colors">
                                <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-clock text-green-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-white font-medium text-sm">Dernière commande</p>
                                    <p class="text-gray-400 text-xs">
<?= date('d/m/Y', strtotime($stats['last_order_date'])) ?>
                                    </p>
                                </div>
                            </div>
<?php endif; ?>


<?php if ($stats['active_subscriptions'] > 0 && (!$privacySettings['hide_stats'] || $isOwnProfile)): ?>
                            <div class="flex items-center gap-3 p-3 bg-dark-700/50 rounded-lg hover:bg-dark-700 transition-colors">
                                <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
                                    <i class="fas fa-sync-alt text-purple-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-white font-medium text-sm"><?= $stats['active_subscriptions'] ?> abonnement<?= $stats['active_subscriptions'] > 1 ? 's' : '' ?></p>
                                    <p class="text-gray-400 text-xs">Actuellement actif<?= $stats['active_subscriptions'] > 1 ? 's' : '' ?></p>
                                </div>
                            </div>
<?php endif; ?>


<?php if ($stats['total_orders'] == 0 && (!$privacySettings['hide_stats'] || $isOwnProfile)): ?>
                            <div class="text-center py-6">
                                <div class="w-12 h-12 bg-gray-700 rounded-full flex items-center justify-center mx-auto mb-2">
                                    <i class="fas fa-inbox text-gray-400 text-lg"></i>
                                </div>
                                <p class="text-gray-400 text-xs">Aucune activité pour le moment</p>
                            </div>
<?php endif; ?>
                        </div>
                    </div>
<?php endif; ?>

                </div>

<?php if ($isGravatar): ?>
                <div class="mt-6 bg-gradient-to-r from-blue-500/10 via-purple-500/10 to-pink-500/10 border border-blue-500/30 rounded-xl p-4" data-aos="fade-up">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-blue-primary rounded-full flex items-center justify-center">
                            <i class="fas fa-user-circle text-white text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-bold text-base">Powered by Gravatar</h3>
                            <p class="text-gray-400 text-xs">Avatar universel reconnu mondialement</p>
                        </div>
                    </div>
                    <p class="text-gray-300 text-xs mb-3">
                        L'avatar de ce profil est fourni par <strong>Gravatar</strong>, le service d'avatar global utilisé par des millions de sites web.
<?php if ($isOwnProfile): ?>
                        Votre avatar Gravatar est lié à votre adresse email et vous suit partout sur le web.
<?php endif; ?>
                    </p>
<?php if ($isOwnProfile): ?>
                    <a href="https://gravatar.com" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-2 bg-blue-primary hover:bg-blue-secondary text-white font-medium py-2 px-3 rounded-lg transition-colors text-xs">
                        <i class="fas fa-external-link-alt"></i>
                        Gérer mon avatar Gravatar
                    </a>
<?php endif; ?>
                </div>
<?php endif; ?>

            </div>
        </div>
    </section>
</main>

<?php require_once '../includes/footer.php'; ?>


<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>

<script>

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

@media (max-width: 768px) {
    .text-4xl.md\\:text-5xl {
        font-size: 2rem;
    }
}
</style>

<?php
endHTMLPage();
?>
