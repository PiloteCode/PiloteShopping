<?php

$db = null;
try {
    if (defined('API_ACCESS') || class_exists('Database')) {
        $db = Database::getInstance();
    }
} catch (Exception $e) {
    error_log("Menu database connection error: " . $e->getMessage());
}

$menu_user_connected = false;
$menu_user_id = null;
$menu_user_login = '';
$menu_user_email = '';
$menu_user_avatar = '';
$menu_user_solde = '0.00';
$menu_user_loyalty_points = '0';
$menu_user_is_admin = false;
$menu_user_first_name = '';
$menu_user_last_name = '';
$menu_unread_notifications = 0;

if ($db && isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    try {
        $stmt = $db->prepare("
            SELECT u.*, 
                   COALESCE(uo.provider, 'local') as auth_provider
            FROM users u
            LEFT JOIN users_oauth uo ON u.id = uo.user_id
            WHERE u.id = ? AND u.is_active = 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        if ($user = $stmt->fetch()) {
            $menu_user_connected = true;
            $menu_user_id = $user['id'];
            $menu_user_login = $user['username'];
            $menu_user_email = $user['email'];
            $menu_user_first_name = $user['first_name'];
            $menu_user_last_name = $user['last_name'];
            $menu_user_solde = number_format($user['balance'], 2);
            
            if ($user['avatar_url']) {
                $menu_user_avatar = htmlspecialchars($user['avatar_url']);
            } else {
                $menu_user_avatar = "https://www.gravatar.com/avatar/" . md5(strtolower(trim($user['email']))) . "?s=200&d=mp";
            }
            
                        try {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as is_admin
                    FROM user_sessions
                    WHERE user_id = ?
                    AND JSON_EXTRACT(user_agent, '$.admin') = true
                ");
                $stmt->execute([$menu_user_id]);
                $admin_check = $stmt->fetch();
                $menu_user_is_admin = $admin_check && $admin_check['is_admin'] > 0;
            } catch (Exception $e) {
                $menu_user_is_admin = false;
            }

                        try {
                $stmt = $db->prepare("
                    SELECT COUNT(*) as unread_count
                    FROM notifications
                    WHERE user_id = ?
                    AND is_read = 0
                    AND (expires_at IS NULL OR expires_at > NOW())
                ");
                $stmt->execute([$menu_user_id]);
                $notification_count = $stmt->fetch();
                $menu_unread_notifications = $notification_count ? (int)$notification_count['unread_count'] : 0;
            } catch (Exception $e) {
                $menu_unread_notifications = 0;
            }
        }
    } catch (Exception $e) {
        error_log("Menu user data error: " . $e->getMessage());
        $menu_user_connected = false;
    }
} elseif (isset($_SESSION['user_id']) && $_SESSION['user_id']) {
    $menu_user_connected = true;
    $menu_user_login = $_SESSION['username'] ?? 'Utilisateur';
    $menu_user_avatar = "https://www.gravatar.com/avatar/default?s=200&d=mp";
}

$menu_account_temp_suspended = false;
$menu_account_perm_suspended = false;
$menu_site_maintenance = false;
$menu_promo_active = false;
$menu_promo_message = '';
$menu_sanction_reason = '';
$menu_sanction_expires = '';

$menu_custom_offer = null;

if ($db && $menu_user_connected && $menu_user_id) {
    try {
        $stmt = $db->prepare("
            SELECT p.id, p.name, p.price, p.image_url, p.discount_percentage,
                   ROUND(p.price * (1 - COALESCE(p.discount_percentage, 0) / 100), 2) as final_price
            FROM products p
            WHERE p.is_active = 1 
            AND p.stock > 0
            AND p.is_visible = 1
            ORDER BY RAND()
            LIMIT 1
        ");
        $stmt->execute();
        
        if ($product = $stmt->fetch()) {
            $menu_custom_offer = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => number_format($product['price'], 2),
                'final_price' => number_format($product['final_price'], 2),
                'discount' => $product['discount_percentage'],
                'image' => $product['image_url'] ?? '/assets/images/default-product.jpg'
            ];
        }
    } catch (Exception $e) {
        error_log("Custom offer error: " . $e->getMessage());
    }
}

if ($db && $menu_user_connected && $menu_user_id) {
    try {
        $stmt = $db->prepare("
            SELECT sl.*, sl.details->>'$.reason' as reason, sl.details->>'$.expires_at' as expires_at
            FROM security_logs sl
            WHERE sl.user_id = ? 
            AND sl.action IN ('account_suspended', 'account_banned')
            AND sl.status = 'success'
            AND (sl.details->>'$.expires_at' IS NULL OR sl.details->>'$.expires_at' > NOW())
            ORDER BY sl.created_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$menu_user_id]);
        
        if ($sanction = $stmt->fetch()) {
            $menu_sanction_reason = $sanction['reason'] ?? 'Violation des conditions d\'utilisation';
            if ($sanction['expires_at']) {
                $menu_account_temp_suspended = true;
                $menu_sanction_expires = $sanction['expires_at'];
            } else {
                $menu_account_perm_suspended = true;
            }
        }
    } catch (Exception $e) {
        error_log("Sanction check error: " . $e->getMessage());
    }
}

if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT setting_key, setting_value, setting_type 
            FROM system_settings 
            WHERE setting_key IN ('maintenance_mode', 'promo_active', 'promo_message', 'promo_code', 'promo_expires')
            AND is_public = 1
        ");
        $stmt->execute();
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $menu_site_maintenance = isset($settings['maintenance_mode']) && $settings['maintenance_mode'] == '1';
        $menu_promo_active = isset($settings['promo_active']) && $settings['promo_active'] == '1';
        
        if ($menu_promo_active && isset($settings['promo_message'])) {
            $menu_promo_message = $settings['promo_message'];
        }
    } catch (Exception $e) {
        error_log("Settings check error: " . $e->getMessage());
    }
}

$menu_items = [
    [
        'title' => 'Produits',
        'url' => '/products/'
    ],
    [
        'title' => 'Support',
        'url' => '/support/'
    ],
    [
        'title' => 'Autres',
        'dropdown' => [
            ['title' => 'A Propos', 'url' => '/apropos/', 'icon' => 'fab fa-youtube'],
        ]
    ]
];

$commercial_notifications = [];
if ($db) {
    try {
        $stmt = $db->prepare("
            SELECT bt.description, bt.created_at, u.username, u.first_name
            FROM balance_transactions bt
            JOIN users u ON bt.user_id = u.id
            WHERE bt.type = 'debit' 
            AND bt.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY bt.created_at DESC
            LIMIT 10
        ");
        $stmt->execute();
        $transactions = $stmt->fetchAll();
        
        foreach ($transactions as $transaction) {
            $commercial_notifications[] = [
                'type' => 'purchase',
                'user' => $transaction['first_name'] ?: $transaction['username'],
                'product' => $transaction['description'],
                'time' => $transaction['created_at']
            ];
        }
        
        $stmt = $db->prepare("
            SELECT username, first_name, created_at
            FROM users
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $new_users = $stmt->fetchAll();
        
        foreach ($new_users as $user) {
            $commercial_notifications[] = [
                'type' => 'registration',
                'user' => $user['first_name'] ?: $user['username'],
                'time' => $user['created_at']
            ];
        }
    } catch (Exception $e) {
        error_log("Commercial notifications error: " . $e->getMessage());
    }
}

$has_banners = $menu_account_temp_suspended || $menu_account_perm_suspended || $menu_site_maintenance || $menu_promo_active || $menu_custom_offer;
?>

<style>

.dotted-pattern {
    background-image: radial-gradient(rgba(0, 0, 0, 0) 1px, rgba(36, 36, 36, 0.5) 1px);
    background-size: 4px 4px;
}

.dotted-pattern-dark {
    background-image: radial-gradient(rgba(0, 0, 0, 0) 1px, rgba(18, 18, 18, 0.8) 1px);
    background-size: 3px 3px;
}

.submenu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
}
.submenu.active {
    max-height: 500px;
}

@keyframes pulse-glow {
    0%, 100% {
        box-shadow: 0 0 20px rgba(14, 165, 233, 0.3);
    }
    50% {
        box-shadow: 0 0 30px rgba(14, 165, 233, 0.6);
    }
}

.pulse-glow {
    animation: pulse-glow 2s ease-in-out infinite;
}

.navbar-with-banners {
    border-bottom-left-radius: 0 !important;
    border-bottom-right-radius: 0 !important;
}

.banner-closing {
    animation: slideUp 0.3s ease-out forwards;
}

@keyframes slideUp {
    to {
        opacity: 0;
        transform: translateY(-20px);
        max-height: 0;
        padding: 0;
        margin: 0;
    }
}
</style>

<div id="backdrop" class="fixed inset-0 bg-black/70 backdrop-blur-md opacity-0 pointer-events-none transition-opacity duration-300 z-40"></div>

<div id="menuContainer" class="fixed z-50 inset-x-0 top-2 md:top-4 transition-all duration-500">

    <nav id="navbar" class="flex max-w-full items-center justify-between px-4 mx-auto h-16 w-11/12 rounded-2xl border border-gray-border bg-dark-800/50 backdrop-blur-sm backdrop-brightness-150 md:max-w-3xl lg:max-w-5xl lg:px-6 xl:max-w-7xl dotted-pattern transition-all <?= $has_banners ? 'navbar-with-banners' : '' ?>">

        <a class="flex items-center gap-2" href="/">
            <img src="<?php echo defined('SITE_URL') ? SITE_URL : 'https://piloteshopping.com'; ?>/assets/images/logo.png" 
                 alt="Logo" class="w-7 h-7 object-contain">
            <p class="text-xl font-semibold tracking-tight text-gray-text">
<?php echo defined('SITE_NAME') ? SITE_NAME : 'Pilote Shopping'; ?>
            </p>
        </a>

        <div class="hidden md:flex items-center text-gray-muted md:gap-5 lg:gap-10">
<?php foreach ($menu_items as $item): ?>
<?php if (isset($item['dropdown'])): ?>
                <div class="menu-item relative group">
                    <button class="transition-all duration-300 hover:text-gray-text flex items-center gap-1 cursor-pointer">
<?= htmlspecialchars($item['title']) ?>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-200 group-hover:rotate-180"></i>
                    </button>

                    <div class="opacity-0 invisible transform translate-y-2 scale-95 group-hover:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:scale-100 absolute top-full left-1/2 transform -translate-x-1/2 mt-2 w-64 pt-2 z-[60] transition-all duration-200 ease-out">
                        <div class="bg-dark-800/95 backdrop-blur-md rounded-2xl border border-gray-border/30 shadow-xl overflow-hidden">
                            <div class="py-2">
<?php foreach ($item['dropdown'] as $subitem): ?>
                                    <a href="<?= htmlspecialchars($subitem['url']) ?>" 
                                       class="flex items-center gap-3 px-4 py-3 text-gray-text hover:bg-white/5 hover:translate-x-1 transition-all duration-200">
                                        <div class="w-8 h-8 flex items-center justify-center rounded-full bg-dark-600">
                                            <i class="<?= htmlspecialchars($subitem['icon']) ?> text-blue-primary text-sm"></i>
                                        </div>
                                        <span class="font-medium"><?= htmlspecialchars($subitem['title']) ?></span>
                                    </a>
<?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
<?php else: ?>
                <a class="transition-all duration-300 hover:text-gray-text" href="<?= htmlspecialchars($item['url']) ?>">
<?= htmlspecialchars($item['title']) ?>
                </a>
<?php endif; ?>
<?php endforeach; ?>
        </div>

        <div class="flex items-center gap-2 sm:gap-4 text-gray-muted">

            <a class="transition-all duration-300 hover:text-gray-text hover:scale-110" href="/cart" title="Panier">
                <i class="fas fa-shopping-cart"></i>
            </a>

<?php if ($menu_user_connected): ?>
            <a class="relative transition-all duration-300 hover:text-gray-text hover:scale-110"
               href="/dashboard/notifications"
               title="Notifications"
               id="notification-link">
                <i class="fas fa-bell"></i>
<?php if ($menu_unread_notifications > 0): ?>
                <span id="notification-badge"
                      class="absolute -top-2 -right-2 flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse">
<?= $menu_unread_notifications > 99 ? '99+' : $menu_unread_notifications ?>
                </span>
<?php else: ?>
                <span id="notification-badge"
                      class="absolute -top-2 -right-2 flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full animate-pulse"
                      style="display: none;"></span>
<?php endif; ?>
            </a>
<?php endif; ?>


<?php if ($menu_user_connected): ?>
            <div class="relative group">
                <button class="w-8 h-8 rounded-full overflow-hidden border-2 border-transparent hover:border-blue-primary transition-all duration-300">
                    <img src="<?= $menu_user_avatar ?>" alt="Profile" class="w-full h-full object-cover">
                </button>

                <div class="opacity-0 invisible transform translate-y-2 scale-95 group-hover:opacity-100 group-hover:visible group-hover:translate-y-0 group-hover:scale-100 absolute right-0 top-full mt-2 w-72 pt-2 z-[60] transition-all duration-200 ease-out">
                    <div class="bg-dark-800/95 backdrop-blur-md rounded-2xl border border-gray-border/30 shadow-xl overflow-hidden">

                        <div class="p-4 border-b border-gray-border/30">
                            <div class="flex items-center gap-3 mb-3">
                                <img src="<?= $menu_user_avatar ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover">
                                <div class="flex-1">
                                    <p class="font-semibold text-gray-text"><?= htmlspecialchars($menu_user_first_name . ' ' . $menu_user_last_name) ?></p>
                                    <p class="text-sm text-gray-muted">@<?= htmlspecialchars($menu_user_login) ?></p>
                                </div>
                            </div>
                            <div class="bg-dark-700/80 rounded-xl p-3 flex items-center justify-between">
                                <span class="text-gray-muted text-sm">Solde</span>
                                <span class="font-semibold text-gray-text"><?= $menu_user_solde ?>€</span>
                            </div>
                            <div class="bg-dark-700/80 rounded-xl p-3 flex items-center justify-between mt-2">
                                <div class="flex items-center gap-2">
                                    <img src="https://piloteshopping.com/assets/loyal_point.png" alt="Points de fidélité" class="w-5 h-5 object-contain">
                                    <span class="text-gray-muted text-sm">Points de fidélité</span>
                                </div>
                                <span class="font-semibold text-gray-text"><?= $menu_user_loyalty_points ?></span>
                            </div>
                        </div>

                        <div class="py-2">
                            <a href="/dashboard" class="flex items-center gap-3 px-4 py-3 text-gray-text hover:bg-white/5 hover:translate-x-1 transition-all duration-200">
                                <i class="fas fa-columns text-blue-primary w-4"></i>
                                <span>Dashboard</span>
                            </a>
                            <a href="/users/<?= htmlspecialchars($menu_user_login) ?>" class="flex items-center gap-3 px-4 py-3 text-gray-text hover:bg-white/5 hover:translate-x-1 transition-all duration-200">
                                <i class="fas fa-user text-blue-primary w-4"></i>
                                <span>Mon profil</span>
                            </a>
                            <a href="/orders" class="flex items-center gap-3 px-4 py-3 text-gray-text hover:bg-white/5 hover:translate-x-1 transition-all duration-200">
                                <i class="fas fa-shopping-bag text-blue-primary w-4"></i>
                                <span>Mes commandes</span>
                            </a>
                            <a href="/wallet" class="flex items-center gap-3 px-4 py-3 text-gray-text hover:bg-white/5 hover:translate-x-1 transition-all duration-200">
                                <i class="fas fa-wallet text-blue-primary w-4"></i>
                                <span>Mon portefeuille</span>
                            </a>
<?php if ($menu_user_is_admin): ?>
                            <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-3 text-gray-text hover:bg-white/5 hover:translate-x-1 transition-all duration-200">
                                <i class="fas fa-shield-alt text-blue-primary w-4"></i>
                                <span>Administration</span>
                            </a>
<?php endif; ?>
                        </div>

                        <div class="border-t border-gray-border/30">
                            <a href="/api/v1/auth/logout" class="flex items-center gap-3 px-4 py-3 text-red-400 hover:bg-red-400/10 hover:translate-x-1 transition-all duration-200">
                                <i class="fas fa-sign-out-alt w-4"></i>
                                <span>Se déconnecter</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
<?php else: ?>

            <div class="hidden sm:flex items-center gap-2">
                <a class="transition-all duration-300 hover:text-gray-text hover:bg-dark-600/50 px-3 py-1.5 rounded-full whitespace-nowrap" href="/auth/login">Connexion</a>
                <a href="/auth/register" class="transition-all duration-300 rounded-full border border-gray-border bg-dark-700 px-3 py-1.5 font-medium hover:border-gray-light hover:bg-dark-600 text-gray-text whitespace-nowrap text-sm">
                    Inscription
                </a>
            </div>

            <div class="sm:hidden">
                <button id="authButton" class="transition-all duration-300 hover:text-gray-text hover:scale-110">
                    <i class="fas fa-user"></i>
                </button>
            </div>
<?php endif; ?>


            <button id="menuToggle" class="md:hidden transition-all duration-300 hover:text-gray-text">
                <i id="menuIcon" class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

<?php if ($has_banners): ?>
    <div class="mx-auto w-11/12 md:max-w-3xl lg:max-w-5xl xl:max-w-7xl" id="bannersContainer">

<?php if ($menu_custom_offer && $menu_user_connected): ?>
        <div id="customOfferBanner" class="bg-dark-900/70 backdrop-blur-sm border-x border-b border-gray-border overflow-hidden dotted-pattern-dark" style="display: none;">
            <div class="bg-gradient-to-r from-blue-primary/90 via-blue-secondary/90 to-blue-dark/90 backdrop-blur-sm pulse-glow">
                <div class="px-4 md:px-6 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">

                            <div class="flex-shrink-0 w-12 h-12 bg-white/20 rounded-lg overflow-hidden backdrop-blur-sm">
                                <img src="<?= htmlspecialchars($menu_custom_offer['image']) ?>" 
                                     alt="<?= htmlspecialchars($menu_custom_offer['name']) ?>" 
                                     class="w-full h-full object-cover">
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <i class="fas fa-star text-yellow-400 text-sm"></i>
                                    <h3 class="text-white font-semibold text-sm">Offre spéciale !</h3>
                                </div>
                                <p class="text-white/90 text-xs hidden md:block">
                                    <span class="font-semibold"><?= htmlspecialchars($menu_custom_offer['name']) ?></span>
                                </p>
                                <div class="flex items-center gap-2">
<?php if ($menu_custom_offer['discount'] > 0): ?>
                                    <span class="text-white/70 line-through text-xs"><?= $menu_custom_offer['price'] ?>€</span>
                                    <span class="bg-white/20 text-white px-1.5 py-0.5 rounded-full text-[10px] font-bold">
                                        -<?= $menu_custom_offer['discount'] ?>%
                                    </span>
<?php endif; ?>
                                    <span class="text-white font-bold text-sm"><?= $menu_custom_offer['final_price'] ?>€</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="/products/<?= $menu_custom_offer['id'] ?>" 
                               class="inline-flex items-center gap-2 bg-white hover:bg-white/90 text-blue-primary px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-300 hover:scale-105 whitespace-nowrap">
                                <i class="fas fa-shopping-cart"></i>
                                <span class="hidden sm:inline">Voir</span>
                            </a>
                            <button onclick="closeBanner('customOffer')" class="text-white/70 hover:text-white transition-colors p-2">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>

<?php if ($menu_account_temp_suspended): ?>
        <div class="bg-dark-900/70 backdrop-blur-sm border-x border-b border-gray-border overflow-hidden dotted-pattern-dark">
            <div class="bg-gradient-to-r from-amber-600/90 to-orange-600/90 backdrop-blur-sm">
            <div class="bg-gradient-to-r from-amber-600/90 to-orange-600/90 backdrop-blur-sm">
                <div class="px-4 md:px-6 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-white text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-white font-semibold text-sm mb-0.5">Compte suspendu</h3>
                                <p class="text-white/90 text-xs">
                                    Jusqu'au <?= date('d/m/Y à H:i', strtotime($menu_sanction_expires)) ?>
                                </p>
                            </div>
                        </div>
                        <a href="/support" class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors whitespace-nowrap">
                            <i class="fas fa-headset"></i>
                            <span class="hidden sm:inline">Support</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>

<?php if ($menu_account_perm_suspended): ?>
        <div class="bg-dark-900/70 backdrop-blur-sm border-x border-b border-gray-border overflow-hidden dotted-pattern-dark">
            <div class="bg-gradient-to-r from-red-600/90 to-red-700/90 backdrop-blur-sm">
                <div class="px-4 md:px-6 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-ban text-white text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-white font-semibold text-sm mb-0.5">Compte suspendu définitivement</h3>
                                <p class="text-white/90 text-xs truncate">
<?= htmlspecialchars($menu_sanction_reason) ?>
                                </p>
                            </div>
                        </div>
                        <a href="/support" class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors whitespace-nowrap">
                            <i class="fas fa-headset"></i>
                            <span class="hidden sm:inline">Appel</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>

<?php if ($menu_site_maintenance): ?>
        <div id="maintenanceBanner" class="bg-dark-900/70 backdrop-blur-sm border-x border-b border-gray-border overflow-hidden dotted-pattern-dark" style="display: none;">
            <div class="bg-gradient-to-r from-orange-600/90 to-amber-600/90 backdrop-blur-sm">
                <div class="px-4 md:px-6 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-wrench text-white text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-white font-semibold text-sm mb-0.5">BETA en cours</h3>
                                <p class="text-white/90 text-xs hidden md:block">
                                    Tests en cours pour une expérience optimale
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="/status" class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors whitespace-nowrap">
                                <i class="fas fa-chart-bar"></i>
                                <span class="hidden sm:inline">État</span>
                            </a>
                            <button onclick="closeBanner('maintenance')" class="text-white/70 hover:text-white transition-colors p-2">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>

<?php if ($menu_promo_active): ?>
        <div id="promoBanner" class="bg-dark-900/70 backdrop-blur-sm border-x border-b border-t-2 border-blue-primary/50 overflow-hidden dotted-pattern-dark" style="display: none;">
            <div class="bg-gradient-to-r from-blue-primary/90 to-blue-secondary/90 backdrop-blur-sm">
                <div class="px-4 md:px-6 py-3">
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-gift text-white text-lg"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h3 class="text-white font-semibold text-sm mb-0.5">Offre spéciale</h3>
                                <p class="text-white/90 text-xs truncate">
<?= htmlspecialchars($menu_promo_message) ?>
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="/shop" class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white px-3 py-1.5 rounded-lg text-sm font-medium transition-colors whitespace-nowrap">
                                <i class="fas fa-shopping-bag"></i>
                                <span class="hidden sm:inline">Profiter</span>
                            </a>
                            <button onclick="closeBanner('promo')" class="text-white/70 hover:text-white transition-colors p-2">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>
    </div>
<?php endif; ?>
</div>

<div id="searchModal" class="fixed inset-0 z-[70] opacity-0 pointer-events-none transition-all duration-300">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-md" id="searchBackdrop"></div>
    <div class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-2xl mx-4">
        <div class="bg-dark-800/95 backdrop-blur-md rounded-2xl border border-gray-border/30 shadow-2xl p-6 transform scale-95 transition-all duration-300" id="searchContent">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-gray-text font-semibold text-lg">Rechercher</h3>
                <button id="closeSearch" class="text-gray-muted hover:text-gray-text transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <form action="/search" method="GET" class="space-y-4">
                <div class="flex items-center bg-dark-700 rounded-xl px-4 py-3 border border-gray-border/50 focus-within:border-blue-primary transition-colors">
                    <i class="fas fa-search text-gray-muted mr-3"></i>
                    <input type="text" name="q" placeholder="Rechercher un produit ou service..." 
                           class="bg-transparent text-gray-text w-full focus:outline-none" id="searchInput" autocomplete="off">
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" id="cancelSearch" class="px-4 py-2 text-gray-muted hover:text-gray-text transition-colors">
                        Annuler
                    </button>
                    <button type="submit" class="px-6 py-2 bg-blue-primary hover:bg-blue-secondary text-white rounded-lg font-medium transition-colors">
                        Rechercher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if (!$menu_user_connected): ?>
<div id="authModal" class="fixed inset-0 z-[70] opacity-0 pointer-events-none transition-all duration-300">
    <div class="fixed inset-0 bg-black/70 backdrop-blur-md" id="authBackdrop"></div>
    <div class="fixed top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-sm mx-4">
        <div class="bg-dark-800/95 backdrop-blur-md rounded-2xl border border-gray-border/30 shadow-2xl p-6 transform scale-95 transition-all duration-300" id="authContent">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-gray-text font-semibold text-lg">Espace membre</h3>
                <button id="closeAuth" class="text-gray-muted hover:text-gray-text transition-colors">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="text-center mb-6">
                <div class="w-16 h-16 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-user-circle text-blue-primary text-3xl"></i>
                </div>
                <p class="text-gray-muted text-sm">Connectez-vous pour accéder à toutes les fonctionnalités</p>
            </div>

            <div class="space-y-3">
                <a href="/auth/login" class="block w-full py-3 bg-blue-primary hover:bg-blue-secondary text-white rounded-xl font-medium text-center transition-colors">
                    <i class="fas fa-sign-in-alt mr-2"></i> Se connecter
                </a>
                <a href="/auth/register" class="block w-full py-3 bg-dark-700 hover:bg-dark-600 text-gray-text rounded-xl font-medium text-center transition-colors">
                    <i class="fas fa-user-plus mr-2"></i> Créer un compte
                </a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>


<div id="mobileMenu" class="md:hidden fixed inset-0 transform translate-x-full transition-transform duration-300 z-[60] min-h-screen overflow-y-auto bg-dark-800/98 backdrop-blur-md">
    <div class="p-6 pt-20">

        <button id="closeMenu" class="absolute top-6 right-6 w-10 h-10 bg-dark-700 rounded-full flex items-center justify-center text-gray-text hover:bg-dark-600 transition-colors">
            <i class="fas fa-times"></i>
        </button>

<?php if ($menu_user_connected): ?>
        <div class="mb-8">
            <div class="flex items-center gap-4 mb-4">
                <img src="<?= $menu_user_avatar ?>" alt="Profile" class="w-16 h-16 rounded-full object-cover border-2 border-blue-primary">
                <div>
                    <p class="font-semibold text-gray-text text-lg"><?= htmlspecialchars($menu_user_first_name . ' ' . $menu_user_last_name) ?></p>
                    <p class="text-gray-muted">@<?= htmlspecialchars($menu_user_login) ?></p>
                </div>
            </div>
            <div class="bg-dark-700 rounded-xl p-4 flex items-center justify-between">
                <span class="text-gray-muted">Solde disponible</span>
                <span class="font-semibold text-gray-text text-lg"><?= $menu_user_solde ?>€</span>
            </div>
            <div class="bg-dark-700 rounded-xl p-4 flex items-center justify-between mt-3">
                <div class="flex items-center gap-2">
                    <img src="https://piloteshopping.com/assets/loyal_point.png" alt="Points de fidélité" class="w-6 h-6 object-contain">
                    <span class="text-gray-muted">Points de fidélité</span>
                </div>
                <span class="font-semibold text-gray-text text-lg"><?= $menu_user_loyalty_points ?></span>
            </div>
        </div>
<?php else: ?>
        <div class="mb-8 space-y-3">
            <a href="/auth/login" class="block bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-6 rounded-xl text-center transition-colors">
                <i class="fas fa-sign-in-alt mr-2"></i> Se connecter
            </a>
            <a href="/auth/register" class="block bg-dark-700 hover:bg-dark-600 text-gray-text font-medium py-3 px-6 rounded-xl text-center transition-colors">
                <i class="fas fa-user-plus mr-2"></i> Créer un compte
            </a>
        </div>
<?php endif; ?>


<?php if ($menu_custom_offer && $menu_user_connected): ?>
        <div class="mb-6" id="mobileCustomOffer" style="display: none;">
            <a href="/products/<?= $menu_custom_offer['id'] ?>" class="block bg-gradient-to-r from-blue-primary to-blue-secondary rounded-xl p-4 transition-all hover:scale-[1.02]">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-star text-yellow-400 text-lg"></i>
                        <p class="text-white font-semibold">Offre spéciale</p>
                    </div>
                    <button onclick="event.preventDefault(); closeBanner('customOffer');" class="text-white/70 hover:text-white">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="flex items-center gap-3">
                    <img src="<?= htmlspecialchars($menu_custom_offer['image']) ?>" 
                         alt="<?= htmlspecialchars($menu_custom_offer['name']) ?>" 
                         class="w-12 h-12 rounded-lg object-cover">
                    <div class="flex-1">
                        <p class="text-white/90 text-sm"><?= htmlspecialchars($menu_custom_offer['name']) ?></p>
                        <div class="flex items-center gap-2 mt-1">
<?php if ($menu_custom_offer['discount'] > 0): ?>
                            <span class="text-white/70 line-through text-xs"><?= $menu_custom_offer['price'] ?>€</span>
<?php endif; ?>
                            <span class="text-white font-bold"><?= $menu_custom_offer['final_price'] ?>€</span>
                        </div>
                    </div>
                </div>
            </a>
        </div>
<?php endif; ?>


        <div class="mb-6">
            <div class="flex items-center bg-dark-700 rounded-xl px-4 py-3 border border-gray-border">
                <i class="fas fa-search text-gray-muted mr-3"></i>
                <input type="text" placeholder="Rechercher..." class="bg-transparent text-gray-text w-full focus:outline-none">
            </div>
        </div>

        <div class="space-y-4">
<?php foreach ($menu_items as $item): ?>
<?php if (isset($item['dropdown'])): 
                    $submenuId = strtolower(str_replace(' ', '', $item['title']));
                ?>
                <div class="bg-dark-700/50 rounded-xl overflow-hidden">
                    <button onclick="toggleMobileSubmenu('<?= $submenuId ?>')" 
                            class="flex items-center justify-between w-full px-4 py-4 text-gray-text hover:bg-white/5 transition-colors">
                        <span class="font-medium"><?= htmlspecialchars($item['title']) ?></span>
                        <i id="<?= $submenuId ?>-icon" class="fas fa-chevron-down text-blue-primary transition-transform duration-200"></i>
                    </button>
                    <div id="<?= $submenuId ?>" class="submenu bg-dark-700/30">
<?php foreach ($item['dropdown'] as $subitem): ?>
                        <a href="<?= htmlspecialchars($subitem['url']) ?>" 
                           class="flex items-center gap-3 px-4 py-3 pl-6 text-gray-text hover:bg-white/5 transition-colors">
                            <i class="<?= htmlspecialchars($subitem['icon']) ?> w-6 text-blue-primary"></i>
                            <span><?= htmlspecialchars($subitem['title']) ?></span>
                        </a>
<?php endforeach; ?>
                    </div>
                </div>
<?php else: ?>
                <a href="<?= htmlspecialchars($item['url']) ?>" 
                   class="flex items-center gap-3 px-4 py-4 text-gray-text bg-dark-700/50 rounded-xl hover:bg-white/5 transition-colors">
                    <span class="font-medium"><?= htmlspecialchars($item['title']) ?></span>
                </a>
<?php endif; ?>
<?php endforeach; ?>
        </div>

<?php if ($menu_user_connected): ?>
        <div class="mt-8 space-y-4">
            <div class="text-gray-muted uppercase text-xs font-semibold tracking-wider">Mon compte</div>

            <a href="/dashboard" class="flex items-center gap-3 px-4 py-4 text-gray-text bg-dark-700/50 rounded-xl hover:bg-white/5 transition-colors">
                <i class="fas fa-columns text-blue-primary"></i>
                <span class="font-medium">Dashboard</span>
            </a>

            <a href="/orders" class="flex items-center gap-3 px-4 py-4 text-gray-text bg-dark-700/50 rounded-xl hover:bg-white/5 transition-colors">
                <i class="fas fa-shopping-bag text-blue-primary"></i>
                <span class="font-medium">Mes commandes</span>
            </a>

            <a href="/wallet" class="flex items-center gap-3 px-4 py-4 text-gray-text bg-dark-700/50 rounded-xl hover:bg-white/5 transition-colors">
                <i class="fas fa-wallet text-blue-primary"></i>
                <span class="font-medium">Mon portefeuille</span>
            </a>

<?php if ($menu_user_is_admin): ?>
            <a href="/admin/dashboard" class="flex items-center gap-3 px-4 py-4 text-gray-text bg-dark-700/50 rounded-xl hover:bg-white/5 transition-colors">
                <i class="fas fa-shield-alt text-blue-primary"></i>
                <span class="font-medium">Administration</span>
            </a>
<?php endif; ?>

            <a href="/api/v1/auth/logout" class="flex items-center gap-3 px-4 py-4 text-red-400 bg-red-400/5 rounded-xl hover:bg-red-400/10 transition-colors mt-4">
                <i class="fas fa-sign-out-alt"></i>
                <span class="font-medium">Se déconnecter</span>
            </a>
        </div>
<?php endif; ?>
    </div>
</div>

<div id="commercial_notifications_container" class="fixed bottom-4 left-4 z-30"></div>

<script>
function closeBanner(bannerType) {
    const bannerId = bannerType + 'Banner';
    const banner = document.getElementById(bannerId);
    const mobileBanner = document.getElementById('mobile' + bannerType.charAt(0).toUpperCase() + bannerType.slice(1));

    if (banner) {
        banner.classList.add('banner-closing');
        setTimeout(() => {
            banner.style.display = 'none';
            checkBannersVisibility();
        }, 300);
    }

    if (mobileBanner) {
        mobileBanner.style.display = 'none';
    }

    localStorage.setItem('banner_' + bannerType + '_closed', 'true');
}

function checkBannersVisibility() {
    const bannersContainer = document.getElementById('bannersContainer');
    if (!bannersContainer) return;

    const visibleBanners = Array.from(bannersContainer.children).filter(child => {
        const computedStyle = window.getComputedStyle(child);
        return computedStyle.display !== 'none' && child.offsetHeight > 0;
    });

    const navbar = document.getElementById('navbar');
    if (visibleBanners.length === 0) {
        navbar.classList.remove('navbar-with-banners');
        navbar.style.borderBottomLeftRadius = '1rem';
        navbar.style.borderBottomRightRadius = '1rem';
        bannersContainer.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const banners = ['customOffer', 'maintenance', 'promo'];
    let hasBanners = false;

    banners.forEach(bannerType => {
        const banner = document.getElementById(bannerType + 'Banner');
        const mobileBanner = document.getElementById('mobile' + bannerType.charAt(0).toUpperCase() + bannerType.slice(1));
        const isClosed = localStorage.getItem('banner_' + bannerType + '_closed') === 'true';

        if (banner && !isClosed) {
            banner.style.display = 'block';
            hasBanners = true;
        }

        if (mobileBanner && !isClosed) {
            mobileBanner.style.display = 'block';
        }
    });

    const bannersContainer = document.getElementById('bannersContainer');
    if (bannersContainer) {
        const permanentBanners = bannersContainer.querySelectorAll('[id*="Banner"]:not([id*="customOffer"]):not([id*="maintenance"]):not([id*="promo"])');
        if (permanentBanners.length > 0) {
            hasBanners = true;
        }
    }

    if (!hasBanners && bannersContainer) {
        bannersContainer.style.display = 'none';
        const navbar = document.getElementById('navbar');
        if (navbar) {
            navbar.classList.remove('navbar-with-banners');
        }
    } else if (bannersContainer) {
        bannersContainer.style.display = 'block';
    }
});

const commercial_originalNotifications = [
<?php foreach ($commercial_notifications as $notification): ?>
    {
        type: '<?= $notification['type'] ?>',
        user: '<?= addslashes($notification['user']) ?>',
<?php if ($notification['type'] === 'purchase'): ?>
        product: '<?= addslashes($notification['product']) ?>',
<?php endif; ?>
        time: '<?= $notification['time'] ?>'
    },
<?php endforeach; ?>
];

const commercial_trustNotifications = [
    {
        message: 'Livraison rapide et fiable !',
        icon: 'fa-bolt',
        color: 'blue'
    },
    {
        message: 'Paiement 100% sécurisé',
        icon: 'fa-shield-alt',
        color: 'green'
    },
    {
        message: 'Support client réactif',
        icon: 'fa-heart',
        color: 'purple'
    }
];

let commercial_notificationQueue = [...commercial_originalNotifications, ...commercial_trustNotifications];

function commercial_shuffleArray(array) {
    for (let i = array.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [array[i], array[j]] = [array[j], array[i]];
    }
}

function commercial_formatTimeAgo(seconds) {
    if (seconds < 60) return `Il y a ${seconds} secondes`;
    if (seconds < 3600) return `Il y a ${Math.floor(seconds / 60)} minutes`;
    if (seconds < 86400) return `Il y a ${Math.floor(seconds / 3600)} heures`;
    return `Il y a ${Math.floor(seconds / 86400)} jours`;
}

function commercial_showNextNotification() {
    if (commercial_notificationQueue.length === 0) {
        commercial_notificationQueue = [...commercial_originalNotifications, ...commercial_trustNotifications];
        commercial_shuffleArray(commercial_notificationQueue);
    }

    const commercial_notification = commercial_notificationQueue.shift();
    const commercial_container = document.getElementById('commercial_notifications_container');
    const commercial_notifElement = document.createElement('div');

    const _esc = (s) => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const _safeColor = (c) => /^[a-zA-Z]+$/.test(String(c||'')) ? String(c) : 'blue';
    const _safeIcon = (i) => /^[a-zA-Z0-9\- ]+$/.test(String(i||'')) ? String(i) : 'fa-info';

    if (commercial_notification.type) {
        const commercial_now = new Date();
        const commercial_time = new Date(commercial_notification.time);
        const commercial_timeAgo = commercial_formatTimeAgo(Math.floor((commercial_now - commercial_time) / 1000));

        commercial_notifElement.innerHTML = `
            <div class="flex items-center space-x-4 bg-dark-700/95 backdrop-blur-lg hover:bg-dark-600
                    border border-gray-border/30 rounded-xl p-4 shadow-lg transition-all duration-300
                    hover:scale-105 hover:shadow-xl cursor-pointer max-w-sm">
                <div class="bg-blue-primary/20 rounded-xl p-3">
                    <i class="fas ${commercial_notification.type === 'purchase' ? 'fa-shopping-cart' : 'fa-user-plus'} text-blue-primary text-lg"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-gray-text font-medium">
                        ${commercial_notification.type === 'purchase'
                        ? `${_esc(commercial_notification.user)} vient d'acheter ${_esc(commercial_notification.product)}`
                        : `${_esc(commercial_notification.user)} vient de nous rejoindre`}
                    </p>
                    <p class="text-gray-muted text-sm">${_esc(commercial_timeAgo)}</p>
                </div>
            </div>`;
    } else {
        commercial_notifElement.innerHTML = `
            <div class="flex items-center space-x-4 bg-dark-700/95 backdrop-blur-lg
                        border border-gray-border/30 rounded-xl p-4 shadow-lg
                        transition-all duration-300 max-w-sm">
                <div class="bg-${_safeColor(commercial_notification.color)}-500/20 rounded-xl p-3">
                    <i class="fas ${_safeIcon(commercial_notification.icon)} text-${_safeColor(commercial_notification.color)}-500 text-lg"></i>
                </div>
                <p class="text-gray-text font-medium">${_esc(commercial_notification.message)}</p>
            </div>`;
    }

    while (commercial_container.firstChild) {
        commercial_container.removeChild(commercial_container.firstChild);
    }

    commercial_container.appendChild(commercial_notifElement);

    commercial_notifElement.style.opacity = '0';
    commercial_notifElement.style.transform = 'translateX(-100%)';

    setTimeout(() => {
        commercial_notifElement.style.transition = 'all 0.5s ease';
        commercial_notifElement.style.opacity = '1';
        commercial_notifElement.style.transform = 'translateX(0)';
    }, 100);

    setTimeout(() => {
        commercial_notifElement.style.opacity = '0';
        commercial_notifElement.style.transform = 'translateX(-100%)';

        setTimeout(() => {
            if (commercial_container.contains(commercial_notifElement)) {
                commercial_container.removeChild(commercial_notifElement);
            }
            setTimeout(commercial_showNextNotification, Math.floor(Math.random() * 15000) + 5000);
        }, 500);
    }, 5000);
}

const menuToggle = document.getElementById('menuToggle');
const mobileMenu = document.getElementById('mobileMenu');
const closeMenu = document.getElementById('closeMenu');
const backdrop = document.getElementById('backdrop');
const menuIcon = document.getElementById('menuIcon');

const searchButton = document.getElementById('searchButton');
const searchModal = document.getElementById('searchModal');
const searchBackdrop = document.getElementById('searchBackdrop');
const closeSearch = document.getElementById('closeSearch');
const cancelSearch = document.getElementById('cancelSearch');
const searchContent = document.getElementById('searchContent');
const searchInput = document.getElementById('searchInput');

const authButton = document.getElementById('authButton');
const authModal = document.getElementById('authModal');
const authBackdrop = document.getElementById('authBackdrop');
const closeAuth = document.getElementById('closeAuth');
const authContent = document.getElementById('authContent');

let isMenuOpen = false;

function toggleMobileMenu() {
    isMenuOpen = !isMenuOpen;
    mobileMenu.classList.toggle('translate-x-full');
    backdrop.classList.toggle('opacity-0');
    backdrop.classList.toggle('pointer-events-none');
    document.body.style.overflow = isMenuOpen ? 'hidden' : '';

    if (isMenuOpen) {
        menuIcon.classList.remove('fa-bars');
        menuIcon.classList.add('fa-times');
    } else {
        menuIcon.classList.add('fa-bars');
        menuIcon.classList.remove('fa-times');
    }
}

function toggleMobileSubmenu(submenuId) {
    const submenu = document.getElementById(submenuId);
    const icon = document.getElementById(`${submenuId}-icon`);

    submenu.classList.toggle('active');
    icon.classList.toggle('rotate-180');
}

function openSearchModal() {
    if (!searchModal) return;
    searchModal.classList.remove('opacity-0', 'pointer-events-none');
    searchContent.classList.remove('scale-95');
    searchContent.classList.add('scale-100');
    document.body.style.overflow = 'hidden';

    setTimeout(() => {
        if (searchInput) searchInput.focus();
    }, 100);
}

function closeSearchModal() {
    if (!searchModal) return;
    searchModal.classList.add('opacity-0', 'pointer-events-none');
    searchContent.classList.remove('scale-100');
    searchContent.classList.add('scale-95');
    document.body.style.overflow = '';
}

function openAuthModal() {
    if (!authModal) return;
    authModal.classList.remove('opacity-0', 'pointer-events-none');
    authContent.classList.remove('scale-95');
    authContent.classList.add('scale-100');
    document.body.style.overflow = 'hidden';
}

function closeAuthModal() {
    if (!authModal) return;
    authModal.classList.add('opacity-0', 'pointer-events-none');
    authContent.classList.remove('scale-100');
    authContent.classList.add('scale-95');
    document.body.style.overflow = '';
}

menuToggle?.addEventListener('click', toggleMobileMenu);
closeMenu?.addEventListener('click', toggleMobileMenu);
backdrop?.addEventListener('click', toggleMobileMenu);

searchButton?.addEventListener('click', openSearchModal);
searchBackdrop?.addEventListener('click', closeSearchModal);
closeSearch?.addEventListener('click', closeSearchModal);
cancelSearch?.addEventListener('click', closeSearchModal);

authButton?.addEventListener('click', openAuthModal);
authBackdrop?.addEventListener('click', closeAuthModal);
closeAuth?.addEventListener('click', closeAuthModal);

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        if (searchModal && !searchModal.classList.contains('pointer-events-none')) {
            closeSearchModal();
        }
        if (authModal && !authModal.classList.contains('pointer-events-none')) {
            closeAuthModal();
        }
        if (isMenuOpen) {
            toggleMobileMenu();
        }
    }
});

window.addEventListener('resize', () => {
    if (window.innerWidth >= 768 && isMenuOpen) {
        toggleMobileMenu();
    }
});

let lastScrollY = window.scrollY;
const navbar = document.getElementById('navbar');

window.addEventListener('scroll', () => {
    const currentScrollY = window.scrollY;

    if (currentScrollY > 100) {
        navbar.classList.add('bg-dark-800/80');
        navbar.classList.remove('bg-dark-800/50');
    } else {
        navbar.classList.remove('bg-dark-800/80');
        navbar.classList.add('bg-dark-800/50');
    }

    lastScrollY = currentScrollY;
});

commercial_shuffleArray(commercial_notificationQueue);
if (commercial_originalNotifications.length > 0) {
    setTimeout(commercial_showNextNotification, 3000);
}

document.addEventListener('DOMContentLoaded', function() {
    const adjustBodyPadding = () => {
        const menuContainer = document.getElementById('menuContainer');
        if (menuContainer) {
            const containerHeight = menuContainer.offsetHeight;
            const topPosition = parseInt(window.getComputedStyle(menuContainer).top);
            document.body.style.paddingTop = `${containerHeight + topPosition + 20}px`;
        }
    };

    adjustBodyPadding();
    window.addEventListener('resize', adjustBodyPadding);

    const observer = new MutationObserver(adjustBodyPadding);
    const menuContainer = document.getElementById('menuContainer');
    if (menuContainer) {
        observer.observe(menuContainer, { childList: true, subtree: true });
    }
});

<?php if ($menu_user_connected): ?>
async function updateNotificationBadge() {
    try {
        const response = await fetch('/api/v1/notifications/?unread=true');
        if (!response.ok) return;

        const data = await response.json();
        const badge = document.getElementById('notification-badge');

        if (badge && data.success) {
            const count = data.unread_count || 0;

            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    } catch (error) {
        console.error('Erreur lors de la mise à jour des notifications:', error);
    }
}

setInterval(updateNotificationBadge, 30000);

document.addEventListener('DOMContentLoaded', updateNotificationBadge);
<?php endif; ?>
</script>