<?php

$footer_current_year = date('Y');
$footer_site_name = defined('SITE_NAME') ? SITE_NAME : 'Pilote Shopping';
$footer_site_email = defined('SITE_EMAIL') ? SITE_EMAIL : 'contact@piloteshopping.com';

$footer_user_connected = isset($_SESSION['user_id']) && $_SESSION['user_id'];

$footer_stats = [
    'users' => '5000+',
    'orders' => '15000+',
    'satisfaction' => '96%'
];

try {
    if (class_exists('Database')) {
        $db = Database::getInstance();
        
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
        $stmt->execute();
        $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $footer_stats['users'] = ceil($userCount / 100) * 3000 . '+';
        
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM orders WHERE status = 'completed'");
        $stmt->execute();
        $orderCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        $footer_stats['orders'] = ceil($orderCount / 100) * 100 . '+';
    }
} catch (Exception $e) {
    }

$footer_links = [
    'Produits' => [
        ['title' => 'Gaming', 'url' => '/product?category=1', 'icon' => 'fas fa-gamepad'],
        ['title' => 'Streaming', 'url' => '/product?category=2', 'icon' => 'fas fa-play'],
        ['title' => 'Productivité', 'url' => '/product?category=3', 'icon' => 'fas fa-briefcase'],
        ['title' => 'Créativité', 'url' => '/product?category=4', 'icon' => 'fas fa-palette']
    ],
    'Compte' => [
        ['title' => $footer_user_connected ? 'Dashboard' : 'Se connecter', 'url' => $footer_user_connected ? '/dashboard' : '/auth/login', 'icon' => 'fas fa-user'],
        ['title' => $footer_user_connected ? 'Mes commandes' : 'Créer un compte', 'url' => $footer_user_connected ? '/orders' : '/auth/register', 'icon' => 'fas fa-shopping-bag'],
        ['title' => 'Mon portefeuille', 'url' => '/wallet', 'icon' => 'fas fa-wallet'],
        ['title' => 'Mon profil', 'url' => '/profile', 'icon' => 'fas fa-cog']
    ],
    'Support' => [
        ['title' => 'Centre d\'aide', 'url' => '/help', 'icon' => 'fas fa-question-circle'],
        ['title' => 'Contact', 'url' => '/contact', 'icon' => 'fas fa-envelope'],
        ['title' => 'FAQ', 'url' => '/faq', 'icon' => 'fas fa-info-circle'],
        ['title' => 'Status', 'url' => '/status', 'icon' => 'fas fa-heartbeat']
    ],
    'Légal' => [
        ['title' => 'Conditions d\'utilisation', 'url' => '/terms', 'icon' => 'fas fa-file-contract'],
        ['title' => 'Conditions de vente', 'url' => '/sales', 'icon' => 'fas fa-file-contract'],
        ['title' => 'Politique de confidentialité', 'url' => '/privacy', 'icon' => 'fas fa-user-shield'],
        ['title' => 'Mentions légales', 'url' => '/legal', 'icon' => 'fas fa-balance-scale'],
        ['title' => 'Remboursements', 'url' => '/refunds', 'icon' => 'fas fa-undo']
    ]
];

$footer_social_links = [
    ['platform' => 'Discord', 'url' => 'https://discord.gg/PILOTE', 'icon' => 'fab fa-discord', 'color' => 'hover:text-indigo-400'],
    ['platform' => 'Twitter', 'url' => 'https://twitter.com/PiloteShopping', 'icon' => 'fab fa-x-twitter', 'color' => 'hover:text-blue-400'],
    ['platform' => 'Instagram', 'url' => 'https://instagram.com/PiloteShopping', 'icon' => 'fab fa-instagram', 'color' => 'hover:text-pink-400'],
    ['platform' => 'YouTube', 'url' => 'https://www.youtube.com/@PiloteShopping', 'icon' => 'fab fa-youtube', 'color' => 'hover:text-red-400']
];
?>


<footer class="bg-dark-900 border-t border-dark-600 relative overflow-hidden">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-0 left-1/4 w-96 h-96 bg-blue-primary/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-0 right-1/4 w-80 h-80 bg-purple-500/5 rounded-full blur-3xl"></div>
    </div>

    <div class="container mx-auto px-4 lg:px-6 py-16">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">

            <div class="lg:col-span-4">
                <div class="mb-8">
                    <a href="/" class="flex items-center mb-6">
                        <img src="/assets/images/logo.png" alt="<?= htmlspecialchars($footer_site_name) ?>" class="w-12 h-12 rounded-xl glow transition-all duration-300 hover:scale-110">
                        <span class="text-2xl font-bold text-white ml-3">
<?= htmlspecialchars($footer_site_name) ?>
                        </span>
                    </a>

                    <p class="text-gray-400 mb-6 leading-relaxed">
                        Votre plateforme de confiance pour des services premium à prix réduits. 
                        Économisez jusqu'à 70% sur vos abonnements favoris avec une livraison instantanée 
                        et un support client de qualité.
                    </p>

                    <div class="grid grid-cols-3 gap-4 mb-8">
                        <div class="text-center bg-dark-800/50 rounded-xl p-4 border border-dark-600">
                            <div class="text-2xl font-bold text-blue-primary"><?= $footer_stats['users'] ?></div>
                            <div class="text-gray-400 text-sm">Clients</div>
                        </div>
                        <div class="text-center bg-dark-800/50 rounded-xl p-4 border border-dark-600">
                            <div class="text-2xl font-bold text-green-400"><?= $footer_stats['orders'] ?></div>
                            <div class="text-gray-400 text-sm">Commandes</div>
                        </div>
                        <div class="text-center bg-dark-800/50 rounded-xl p-4 border border-dark-600">
                            <div class="text-2xl font-bold text-yellow-400"><?= $footer_stats['satisfaction'] ?></div>
                            <div class="text-gray-400 text-sm">Satisfaction</div>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-blue-primary/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-envelope text-blue-primary text-sm"></i>
                            </div>
                            <a href="mailto:<?= htmlspecialchars($footer_site_email) ?>" 
                               class="text-gray-400 hover:text-white transition-colors">
<?= htmlspecialchars($footer_site_email) ?>
                            </a>
                        </div>
                        <div class="flex items-center space-x-3">
                            <div class="w-8 h-8 bg-green-500/20 rounded-lg flex items-center justify-center">
                                <i class="fas fa-clock text-green-500 text-sm"></i>
                            </div>
                            <span class="text-gray-400">Support 12h/24, 5j/7</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
<?php foreach ($footer_links as $section_title => $links): ?>
                    <div>
                        <h4 class="text-white font-semibold text-lg mb-6 relative">
<?= htmlspecialchars($section_title) ?>
                            <div class="absolute bottom-0 left-0 w-8 h-0.5 bg-blue-primary rounded-full"></div>
                        </h4>
                        <ul class="space-y-3">
<?php foreach ($links as $link): ?>
                            <li>
                                <a href="<?= htmlspecialchars($link['url']) ?>" 
                                   class="flex items-center space-x-2 text-gray-400 hover:text-white transition-all duration-200 hover:translate-x-1 group">
                                    <i class="<?= htmlspecialchars($link['icon']) ?> text-sm group-hover:text-blue-primary transition-colors"></i>
                                    <span><?= htmlspecialchars($link['title']) ?></span>
                                </a>
                            </li>
<?php endforeach; ?>
                        </ul>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="border-t border-dark-600 bg-dark-800/50">
        <div class="container mx-auto px-4 lg:px-6 py-8">
            <div class="flex flex-col lg:flex-row justify-between items-center gap-6">

                <div class="text-center lg:text-left">
                    <p class="text-gray-400">
                        &copy; <?= $footer_current_year ?><?= htmlspecialchars($footer_site_name) ?>. 
                        Tous droits réservés.
                    </p>
                    <p class="text-gray-500 text-sm mt-1">
                        Fait avec <i class="fas fa-heart text-red-400 mx-1"></i> pour notre communauté.
                    </p>
                </div>

                <div class="flex items-center space-x-4">
                    <span class="text-gray-400 hidden sm:block">Suivez-nous :</span>
<?php foreach ($footer_social_links as $social): ?>
                    <a href="<?= htmlspecialchars($social['url']) ?>" 
                       class="w-10 h-10 bg-dark-700 border border-dark-600 rounded-lg flex items-center justify-center text-gray-400 <?= $social['color'] ?> transition-all duration-200 hover:border-gray-light hover:transform hover:scale-110"
                       title="<?= htmlspecialchars($social['platform']) ?>">
                        <i class="<?= htmlspecialchars($social['icon']) ?>"></i>
                    </a>
<?php endforeach; ?>
                </div>

                <div class="flex items-center space-x-4 text-center lg:text-right">
                    <div class="flex items-center space-x-2 bg-dark-700/50 rounded-lg px-3 py-1.5 border border-dark-600">
                        <i class="fas fa-shield-check text-green-400"></i>
                        <span class="text-gray-400 text-sm">Sécurisé</span>
                    </div>
                    <div class="flex items-center space-x-2 bg-dark-700/50 rounded-lg px-3 py-1.5 border border-dark-600">
                        <i class="fas fa-clock text-blue-400"></i>
                        <span class="text-gray-400 text-sm">Livraison Rapide</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button id="backToTop" 
            class="fixed bottom-6 right-20 z-[90] w-12 h-12 bg-blue-primary hover:bg-blue-secondary text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-110 opacity-0 pointer-events-none">
        <i class="fas fa-chevron-up"></i>
    </button>
</footer>

<script>

document.addEventListener('DOMContentLoaded', function() {
    const backToTopButton = document.getElementById('backToTop');

    window.addEventListener('scroll', function() {
        if (window.scrollY > 500) {
            backToTopButton.classList.remove('opacity-0', 'pointer-events-none');
            backToTopButton.classList.add('opacity-100');
        } else {
            backToTopButton.classList.add('opacity-0', 'pointer-events-none');
            backToTopButton.classList.remove('opacity-100');
        }
    });

    backToTopButton.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
});

function animateFooterStats() {
    const stats = document.querySelectorAll('#footer .text-2xl.font-bold');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animate-pulse');
                setTimeout(() => {
                    entry.target.classList.remove('animate-pulse');
                }, 1000);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.5 });

    stats.forEach(stat => {
        observer.observe(stat);
    });
}

document.addEventListener('DOMContentLoaded', animateFooterStats);

document.querySelectorAll('footer a[title]').forEach(link => {
    link.addEventListener('mouseenter', function() {
        const icon = this.querySelector('i');
        if (icon) {
            icon.classList.add('animate-bounce');
            setTimeout(() => {
                icon.classList.remove('animate-bounce');
            }, 600);
        }
    });
});
</script>

<style>

footer a.group:hover i {
    transform: translateX(2px);
}

.glow {
    box-shadow: 0 0 20px rgba(123, 97, 255, 0.3);
}

.glow:hover {
    box-shadow: 0 0 30px rgba(123, 97, 255, 0.5);
}

@keyframes statPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

.stat-animate {
    animation: statPulse 0.6s ease-in-out;
}

@media (max-width: 640px) {
    footer .grid.grid-cols-2 {
        grid-template-columns: 1fr;
        gap: 2rem;
    }
}
</style>