<?php

if (!headers_sent()) {
    http_response_code(404);
}

try {
    define('API_ACCESS', true);
    require_once 'api/v1/config.php';
    session_start();
    require_once 'includes/import.php';
    require_once 'includes/AuthMiddleware.php';
} catch (Exception $e) {
    }

$auth = null;
$currentUser = null;
try {
    $auth = initAuth();
    $currentUser = $auth->getCurrentUser();
} catch (Exception $e) {
    }

$seo = [
    'title' => '404 - Page non trouvée',
    'description' => 'La page que vous recherchez n\'existe pas. Retournez à l\'accueil de Pilote Shopping.',
    'keywords' => '404, erreur, page non trouvée',
    'robots' => 'noindex, nofollow'
];

try {
    startHTMLPage($seo);
} catch (Exception $e) {
        ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>404 - Page non trouvée | Pilote Shopping</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            :root {
                --bg-primary: #0A0A0A;
                --bg-secondary: #121212;
                --bg-surface: #1A1A1A;
                --bg-elevated: #242424;
                --border-primary: #2E2E2E;
                --text-primary: #F8FAFC;
                --text-secondary: #CBD5E1;
                --blue-primary: #0EA5E9;
                --blue-secondary: #0284C7;
            }
            body { 
                background: var(--bg-primary); 
                color: var(--text-primary);
                font-family: Inter, sans-serif;
            }
        </style>
    </head>
    <body class="bg-dark text-white">
<?php
}

try {
    require_once 'includes/menu.php';
} catch (Exception $e) {
    }
?>


<main class="min-h-screen">

<section class="relative overflow-hidden">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-1/4 left-1/4 w-64 h-64 bg-blue-primary/5 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-48 h-48 bg-purple-500/5 rounded-full blur-3xl"></div>
    </div>

    <div class="container mx-auto px-4 lg:px-6 py-12">
        <div class="max-w-4xl mx-auto">

            <div class="text-center mb-12">
                <div class="mb-6">
                    <div class="text-7xl md:text-8xl font-black text-white leading-none">
                        404
                    </div>
                    <div class="w-24 h-1 bg-gradient-to-r from-red-400 to-blue-primary mx-auto rounded-full mt-3"></div>
                </div>

                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    Page non trouvée
                </h1>

                <p class="text-lg text-gray-secondary max-w-2xl mx-auto leading-relaxed">
                    La page que vous recherchez n'existe pas ou a été déplacée. 
                    Retrouvez votre chemin avec les options ci-dessous.
                </p>
            </div>

            <div class="grid md:grid-cols-2 gap-6 mb-12">

                <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
                    <h2 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-directions text-blue-primary mr-2"></i>
                        Actions rapides
                    </h2>

                    <div class="space-y-3">
                        <a href="/" 
                           class="w-full flex items-center justify-between p-3 bg-gradient-to-r from-blue-primary to-blue-secondary hover:from-blue-secondary hover:to-blue-dark text-white rounded-lg font-medium transition-all duration-200 group">
                            <div class="flex items-center">
                                <i class="fas fa-home mr-2"></i>
                                Retourner à l'accueil
                            </div>
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </a>

                        <a href="/products" 
                           class="w-full flex items-center justify-between p-3 bg-dark-700 border border-dark-600 hover:border-blue-primary/50 text-white rounded-lg font-medium transition-all duration-200 group hover:bg-dark-600">
                            <div class="flex items-center">
                                <i class="fas fa-shopping-bag mr-2"></i>
                                Explorer nos produits
                            </div>
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </a>

                        <button onclick="history.back()" 
                                class="w-full flex items-center justify-between p-3 bg-dark-700 border border-dark-600 hover:border-purple-500/50 text-white rounded-lg font-medium transition-all duration-200 group hover:bg-dark-600">
                            <div class="flex items-center">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Page précédente
                            </div>
                            <i class="fas fa-undo group-hover:translate-x-1 transition-transform"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-dark-800 border border-dark-600 rounded-xl p-6">
                    <h2 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-search text-blue-primary mr-2"></i>
                        Rechercher
                    </h2>

                    <form action="/search" method="GET" class="space-y-3">
                        <div>
                            <input type="text" 
                                   name="q" 
                                   placeholder="Rechercher un produit..." 
                                   class="w-full px-3 py-2.5 bg-dark-700 border border-dark-500 rounded-lg text-white placeholder-gray-muted focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all">
                        </div>
                        <button type="submit" 
                                class="w-full py-2.5 bg-gradient-to-r from-blue-primary to-blue-secondary text-white font-medium rounded-lg hover:from-blue-secondary hover:to-blue-dark transition-all duration-200">
                            <i class="fas fa-search mr-2"></i>
                            Rechercher
                        </button>
                    </form>

                    <div class="mt-4 pt-4 border-t border-dark-600">
                        <p class="text-gray-muted text-sm mb-2">Recherches populaires :</p>
                        <div class="flex flex-wrap gap-1.5">
                            <a href="/products/discord" class="px-2 py-1 bg-dark-700 text-gray-secondary text-xs rounded-full hover:bg-blue-primary/20 hover:text-blue-400 transition-colors">Discord</a>
                            <a href="/products/netflix" class="px-2 py-1 bg-dark-700 text-gray-secondary text-xs rounded-full hover:bg-blue-primary/20 hover:text-blue-400 transition-colors">Netflix</a>
                            <a href="/products/spotify" class="px-2 py-1 bg-dark-700 text-gray-secondary text-xs rounded-full hover:bg-blue-primary/20 hover:text-blue-400 transition-colors">Spotify</a>
                            <a href="/products/vpn" class="px-2 py-1 bg-dark-700 text-gray-secondary text-xs rounded-full hover:bg-blue-primary/20 hover:text-blue-400 transition-colors">VPN</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-blue-primary/10 to-purple-500/10 rounded-2xl border border-blue-primary/20 p-8 text-center">
                <div class="w-16 h-16 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-headset text-blue-primary text-2xl"></i>
                </div>

                <h2 class="text-2xl font-bold text-white mb-3">
                    Besoin d'aide ?
                </h2>

                <p class="text-lg text-gray-secondary mb-6 max-w-xl mx-auto">
                    Notre équipe de support est disponible pour vous aider 
                    à retrouver ce que vous cherchez.
                </p>

                <div class="flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="/support" 
                       class="inline-flex items-center px-6 py-3 bg-blue-primary hover:bg-blue-secondary text-white font-medium rounded-lg transition-colors">
                        <i class="fas fa-comments mr-2"></i>
                        Contacter le support
                    </a>
                    <a href="/apropos" 
                       class="inline-flex items-center px-6 py-3 bg-dark-700 border border-dark-600 hover:border-blue-primary/50 text-white font-medium rounded-lg transition-all hover:bg-dark-600">
                        <i class="fas fa-info-circle mr-2"></i>
                        À propos
                    </a>
                </div>

                <div class="flex items-center justify-center mt-6 gap-4 text-gray-muted text-sm">
                    <div class="flex items-center gap-1">
                        <i class="fas fa-clock text-green-400"></i>
                        <span>Réponse rapide</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <i class="fas fa-shield-check text-blue-400"></i>
                        <span>Support expert</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <i class="fas fa-heart text-red-400"></i>
                        <span>Satisfaction garantie</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<?php
try {
    require_once 'includes/footer.php';
} catch (Exception $e) {
    }

try {
    endHTMLPage();
} catch (Exception $e) {
        echo '</body></html>';
}
?>

<script>

document.addEventListener('keydown', function(e) {

    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.focus();
        }
    }

    if (e.key === 'Escape') {
        window.location.href = '/';
    }
});

document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        const searchInput = document.querySelector('input[name="q"]');
        if (searchInput) {
            searchInput.focus();
        }
    }, 500);
});

document.querySelectorAll('a, button').forEach(element => {
    element.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-1px)';
    });

    element.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
    });
});

try {
    fetch('/api/v1/analytics/404', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            url: window.location.pathname,
            referrer: document.referrer,
            timestamp: new Date().toISOString(),
            user_agent: navigator.userAgent
        })
    }).catch(() => {});
} catch (e) {}

function suggestAlternatives() {
    const path = window.location.pathname.toLowerCase();
    const suggestions = [];

    if (path.includes('discord')) {
        suggestions.push({ title: 'Discord Nitro', url: '/products/discord' });
    }
    if (path.includes('netflix')) {
        suggestions.push({ title: 'Netflix Premium', url: '/products/netflix' });
    }
    if (path.includes('spotify')) {
        suggestions.push({ title: 'Spotify Premium', url: '/products/spotify' });
    }
    if (path.includes('admin') || path.includes('dashboard')) {
        suggestions.push({ title: 'Connexion', url: '/auth/login' });
    }

    if (suggestions.length > 0) {
        const suggestionsContainer = document.createElement('div');
        suggestionsContainer.className = 'mt-4 p-4 bg-blue-primary/10 rounded-lg border border-blue-primary/20';
        suggestionsContainer.innerHTML = `
            <p class="text-blue-400 font-medium mb-2"><i class="fas fa-lightbulb mr-1"></i> Suggestion :</p>
            <div class="space-y-1">
                ${suggestions.map(s => `
                    <a href="${s.url}" class="block text-blue-300 hover:text-blue-100 transition-colors">
                        <i class="fas fa-arrow-right mr-1"></i> ${s.title}
                    </a>
                `).join('')}
            </div>
        `;

        const searchContainer = document.querySelector('.bg-dark-800:last-child');
        if (searchContainer) {
            searchContainer.appendChild(suggestionsContainer);
        }
    }
}

document.addEventListener('DOMContentLoaded', suggestAlternatives);
</script>

<style>

@keyframes bounce-in {
    0% {
        transform: scale(0.3);
        opacity: 0;
    }
    50% {
        transform: scale(1.05);
    }
    70% {
        transform: scale(0.9);
    }
    100% {
        transform: scale(1);
        opacity: 1;
    }
}

.text-7xl, .text-8xl {
    animation: bounce-in 0.8s ease-out;
}

.text-7xl, .text-8xl {
    background: linear-gradient(45deg, #0EA5E9, #8B5CF6, #0EA5E9);
    background-size: 200% 200%;
    background-clip: text;
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    animation: gradient-shift 3s ease-in-out infinite, bounce-in 0.8s ease-out;
}

@keyframes gradient-shift {
    0%, 100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}

.bg-dark-800:hover {
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.fade-in-up {
    opacity: 0;
    transform: translateY(20px);
    animation: fadeInUp 0.6s ease-out forwards;
}

@keyframes fadeInUp {
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.bg-dark-800:nth-child(1) { animation-delay: 0.1s; }
.bg-dark-800:nth-child(2) { animation-delay: 0.2s; }

input:focus {
    box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
}
</style>