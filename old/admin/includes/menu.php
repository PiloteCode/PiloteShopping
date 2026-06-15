<?php

if (!isset($currentAdmin) || !isset($currentUser)) {
    return;
}
if (!class_exists('CsrfProtection')) {
    require_once __DIR__ . '/../../includes/CsrfProtection.php';
}
$__adminCsrfToken = \CsrfProtection::token();
?>
<meta name="csrf-token" content="<?= htmlspecialchars($__adminCsrfToken, ENT_QUOTES, 'UTF-8') ?>">
<script>
(function(){
    var meta = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.getAttribute('content') : '';
    window.CSRF_TOKEN = token;
    function injectIntoForms() {
        document.querySelectorAll('form').forEach(function(form){
            var method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method === 'GET') return;
            if (form.querySelector('input[name="_csrf_token"]')) return;
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = '_csrf_token';
            input.value = token;
            form.appendChild(input);
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectIntoForms);
    } else {
        injectIntoForms();
    }
    new MutationObserver(injectIntoForms).observe(document.documentElement, { childList: true, subtree: true });
    var origFetch = window.fetch;
    window.fetch = function(input, init) {
        init = init || {};
        try {
            var m = (init.method || (input && input.method) || 'GET').toUpperCase();
            var url = typeof input === 'string' ? input : (input && input.url) || '';
            var same = url.indexOf('/') === 0 || url.indexOf(location.origin) === 0;
            if (same && ['POST','PUT','PATCH','DELETE'].indexOf(m) !== -1) {
                init.headers = init.headers || {};
                if (init.headers instanceof Headers) { if (!init.headers.has('X-CSRF-Token')) init.headers.set('X-CSRF-Token', token); }
                else if (Array.isArray(init.headers)) { if (!init.headers.some(h => h[0] && h[0].toLowerCase()==='x-csrf-token')) init.headers.push(['X-CSRF-Token', token]); }
                else if (!Object.keys(init.headers).some(k => k.toLowerCase()==='x-csrf-token')) init.headers['X-CSRF-Token'] = token;
            }
        } catch(e){}
        return origFetch.call(this, input, init);
    };
    if (window.XMLHttpRequest) {
        var oOpen = XMLHttpRequest.prototype.open;
        var oSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function(method, url){ this._csrfM = method; this._csrfU = url; return oOpen.apply(this, arguments); };
        XMLHttpRequest.prototype.send = function(body){
            try {
                var m = (this._csrfM || 'GET').toUpperCase();
                var u = this._csrfU || '';
                var s = u.indexOf('/') === 0 || u.indexOf(location.origin) === 0;
                if (s && ['POST','PUT','PATCH','DELETE'].indexOf(m) !== -1) this.setRequestHeader('X-CSRF-Token', token);
            } catch(e){}
            return oSend.apply(this, arguments);
        };
    }
})();
</script>
<?php

try {
    $notificationsStmt = $db->prepare("
        SELECT COUNT(*) as unread_count
        FROM admin_notifications an
        LEFT JOIN admin_notification_reads anr ON an.id = anr.notification_id AND anr.admin_id = ?
        WHERE an.is_active = 1 
        AND (an.expires_at IS NULL OR an.expires_at > NOW())
        AND (an.target_role = 'all' OR an.target_role = ?)
        AND anr.admin_id IS NULL
    ");
    $notificationsStmt->execute([$currentAdmin['id'], $currentAdmin['role']]);
    $unreadCount = $notificationsStmt->fetchColumn();
} catch (Exception $e) {
    $unreadCount = 0;
}
?>


<header class="fixed top-0 left-0 right-0 z-50 bg-dark-900/95 backdrop-blur-sm border-b border-dark-700">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="flex items-center justify-between h-16">

            <div class="flex items-center space-x-8">
                <div class="flex items-center space-x-3">
                    <img src="https://piloteshopping.com/assets/images/logo.png" 
                         alt="Pilote Shopping" 
                         class="w-10 h-10 rounded-xl">
                    <div>
                        <h1 class="text-xl font-bold text-white">Admin Panel</h1>
                        <p class="text-xs text-gray-400">Pilote Shopping</p>
                    </div>
                </div>

                <nav class="hidden md:flex items-center space-x-6">
                    <a href="/admin/" class="<?= basename($_SERVER['REQUEST_URI']) === '' || basename($_SERVER['REQUEST_URI']) === 'index.php' ? 'text-orange-400 font-medium' : 'text-gray-400 hover:text-white transition-colors' ?>">
                        Dashboard
                    </a>
                    <a href="/admin/users" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/users') === 0 ? 'text-orange-400 font-medium' : 'text-gray-400 hover:text-white transition-colors' ?>">
                        Utilisateurs
                    </a>
                    <a href="/admin/products" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/products') === 0 ? 'text-orange-400 font-medium' : 'text-gray-400 hover:text-white transition-colors' ?>">
                        Produits
                    </a>
                    <a href="/admin/orders" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/orders') === 0 ? 'text-orange-400 font-medium' : 'text-gray-400 hover:text-white transition-colors' ?>">
                        Commandes
                    </a>
                    <a href="/admin/analytics" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/analytics') === 0 ? 'text-orange-400 font-medium' : 'text-gray-400 hover:text-white transition-colors' ?>">
                        Analytics
                    </a>
                    <a href="/admin/settings" class="<?= strpos($_SERVER['REQUEST_URI'], '/admin/settings') === 0 ? 'text-orange-400 font-medium' : 'text-gray-400 hover:text-white transition-colors' ?>">
                        Paramètres
                    </a>
                </nav>
            </div>

            <div class="flex items-center space-x-4">

                <div class="relative">
                    <button class="p-2 text-gray-400 hover:text-white transition-colors" onclick="toggleNotifications()">
                        <i class="fas fa-bell text-lg"></i>
<?php if ($unreadCount > 0): ?>
                        <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full"></span>
<?php endif; ?>
                    </button>
                </div>

                <button class="md:hidden p-2 text-gray-400 hover:text-white transition-colors" onclick="toggleMobileMenu()">
                    <i class="fas fa-bars text-lg"></i>
                </button>

                <div class="flex items-center space-x-3">
<?php if ($currentUser['avatar_url']): ?>
                        <img src="<?= htmlspecialchars($currentUser['avatar_url']) ?>" 
                             alt="Avatar" class="w-8 h-8 rounded-full object-cover">
<?php else: ?>
                        <div class="w-8 h-8 bg-gradient-to-br from-orange-500 to-red-500 rounded-full flex items-center justify-center">
                            <span class="text-white text-sm font-bold">
<?= strtoupper(substr($currentUser['first_name'], 0, 1)) ?>
                            </span>
                        </div>
<?php endif; ?>
                    <div class="hidden md:block">
                        <p class="text-white text-sm font-medium">
<?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?>
                        </p>
                        <p class="text-xs text-orange-400 uppercase"><?= htmlspecialchars($currentAdmin['role']) ?></p>
                    </div>

                    <div class="relative">
                        <button class="p-1 text-gray-400 hover:text-white transition-colors" onclick="toggleProfileMenu()">
                            <i class="fas fa-chevron-down text-sm"></i>
                        </button>

                        <div id="profile-menu" class="hidden absolute right-0 mt-2 w-48 bg-dark-800 rounded-xl border border-dark-600 shadow-2xl z-50">
                            <div class="py-2">
                                <a href="/dashboard" class="block px-4 py-2 text-gray-300 hover:bg-dark-700 hover:text-white transition-colors">
                                    <i class="fas fa-user mr-2"></i>Mon profil
                                </a>
                                <a href="/settings" class="block px-4 py-2 text-gray-300 hover:bg-dark-700 hover:text-white transition-colors">
                                    <i class="fas fa-cog mr-2"></i>Paramètres
                                </a>
                                <div class="border-t border-dark-600 my-2"></div>
                                <a href="/" target="_blank" class="block px-4 py-2 text-gray-300 hover:bg-dark-700 hover:text-white transition-colors">
                                    <i class="fas fa-external-link-alt mr-2"></i>Voir le site
                                </a>
                                <div class="border-t border-dark-600 my-2"></div>
                                <a href="/api/v1/auth/logout.php" class="block px-4 py-2 text-red-400 hover:bg-red-500/10 hover:text-red-300 transition-colors">
                                    <i class="fas fa-sign-out-alt mr-2"></i>Déconnexion
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="mobile-menu" class="hidden md:hidden bg-dark-800 border-t border-dark-700">
        <div class="container mx-auto px-4">
            <nav class="py-4 space-y-2">
                <a href="/admin/" class="block py-2 text-gray-300 hover:text-white transition-colors">
                    <i class="fas fa-tachometer-alt mr-2"></i>Dashboard
                </a>
                <a href="/admin/users" class="block py-2 text-gray-300 hover:text-white transition-colors">
                    <i class="fas fa-users mr-2"></i>Utilisateurs
                </a>
                <a href="/admin/products" class="block py-2 text-gray-300 hover:text-white transition-colors">
                    <i class="fas fa-box mr-2"></i>Produits
                </a>
                <a href="/admin/orders" class="block py-2 text-gray-300 hover:text-white transition-colors">
                    <i class="fas fa-shopping-bag mr-2"></i>Commandes
                </a>
                <a href="/admin/analytics" class="block py-2 text-gray-300 hover:text-white transition-colors">
                    <i class="fas fa-chart-bar mr-2"></i>Analytics
                </a>
                <a href="/admin/settings" class="block py-2 text-gray-300 hover:text-white transition-colors">
                    <i class="fas fa-cog mr-2"></i>Paramètres
                </a>
            </nav>
        </div>
    </div>
</header>

<script>

function toggleNotifications() {
    const panel = document.getElementById('notifications-panel');
    if (panel) {
        panel.classList.toggle('translate-x-full');
    }
}

function toggleMobileMenu() {
    const menu = document.getElementById('mobile-menu');
    menu.classList.toggle('hidden');
}

function toggleProfileMenu() {
    const menu = document.getElementById('profile-menu');
    menu.classList.toggle('hidden');
}

document.addEventListener('click', function(e) {
    const profileMenu = document.getElementById('profile-menu');
    const notificationsPanel = document.getElementById('notifications-panel');
    const mobileMenu = document.getElementById('mobile-menu');

    if (!e.target.closest('#profile-menu') && !e.target.closest('[onclick="toggleProfileMenu()"]')) {
        profileMenu?.classList.add('hidden');
    }

    if (!e.target.closest('#notifications-panel') && !e.target.closest('[onclick="toggleNotifications()"]')) {
        notificationsPanel?.classList.add('translate-x-full');
    }

    if (!e.target.closest('#mobile-menu') && !e.target.closest('[onclick="toggleMobileMenu()"]')) {
        mobileMenu?.classList.add('hidden');
    }
});
</script>