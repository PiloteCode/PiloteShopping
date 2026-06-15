<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$discordInviteLink = 'https://discord.gg/piloteshopping'; $discordServerId = 'piloteshopping'; 
$seo = [
    'title' => 'Support & Aide - Pilote Shopping',
    'description' => 'Besoin d\'aide ? Rejoignez notre serveur Discord pour obtenir une assistance rapide de notre équipe',
    'keywords' => 'support, aide, assistance, discord, pilote shopping',
    'robots' => 'index, follow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen bg-dark-900 py-8">
    <div class="container mx-auto px-4 lg:px-6">

        <nav class="mb-8">
            <div class="flex items-center space-x-2 text-sm">
                <a href="/" class="text-gray-400 hover:text-white transition-colors">Accueil</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <span class="text-white font-medium">Support</span>
            </div>
        </nav>

        <div class="text-center mb-12" data-aos="fade-down">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-primary/20 rounded-full mb-6 animate-pulse-slow">
                <i class="fas fa-headset text-blue-primary text-3xl"></i>
            </div>
            <h1 class="text-4xl md:text-5xl font-bold text-white mb-4">
                Besoin d'aide ?
            </h1>
            <p class="text-gray-400 text-lg max-w-2xl mx-auto">
                Notre équipe est là pour vous aider ! Rejoignez notre serveur Discord pour obtenir une assistance rapide et personnalisée
            </p>
        </div>

        <div class="max-w-4xl mx-auto mb-12" data-aos="fade-up">
            <div class="bg-gradient-to-br from-indigo-500/10 via-purple-500/10 to-pink-500/10 backdrop-blur-sm rounded-3xl border border-indigo-500/30 p-8 md:p-12 relative overflow-hidden">

                <div class="absolute inset-0 bg-gradient-to-r from-indigo-500/5 via-purple-500/5 to-pink-500/5 animate-pulse"></div>

                <div class="relative z-10">
                    <div class="flex flex-col md:flex-row items-center gap-8">

                        <div class="flex-shrink-0">
                            <div class="w-24 h-24 md:w-32 md:h-32 bg-indigo-500/20 rounded-3xl flex items-center justify-center transform hover:scale-110 transition-transform duration-300">
                                <i class="fab fa-discord text-indigo-400 text-5xl md:text-6xl"></i>
                            </div>
                        </div>

                        <div class="flex-1 text-center md:text-left">
                            <h2 class="text-2xl md:text-3xl font-bold text-white mb-3">
                                Rejoignez notre Discord
                            </h2>
                            <p class="text-gray-300 mb-6">
                                Notre équipe et notre communauté sont actives 24/7 sur Discord. Obtenez des réponses rapides, des conseils et restez informé des dernières nouveautés !
                            </p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                                <div class="flex items-center gap-2 text-gray-300">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span class="text-sm">Réponse rapide</span>
                                </div>
                                <div class="flex items-center gap-2 text-gray-300">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span class="text-sm">Support 24/7</span>
                                </div>
                                <div class="flex items-center gap-2 text-gray-300">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span class="text-sm">Communauté active</span>
                                </div>
                                <div class="flex items-center gap-2 text-gray-300">
                                    <i class="fas fa-check-circle text-green-400"></i>
                                    <span class="text-sm">Actualités en direct</span>
                                </div>
                            </div>

                            <a href="<?= htmlspecialchars($discordInviteLink) ?>" 
                               target="_blank" 
                               rel="noopener noreferrer"
                               class="inline-flex items-center gap-3 bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-4 px-8 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-indigo-500/50">
                                <i class="fab fa-discord text-2xl"></i>
                                <span>Rejoindre le Discord</span>
                                <i class="fas fa-external-link-alt text-sm"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-6xl mx-auto mb-12">
            <h2 class="text-2xl md:text-3xl font-bold text-white text-center mb-8" data-aos="fade-up">
                En attendant, voici quelques ressources utiles
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-blue-primary/50 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-14 h-14 bg-blue-primary/20 rounded-xl flex items-center justify-center mb-4 group-hover:bg-blue-primary/30 transition-colors">
                        <i class="fas fa-question-circle text-blue-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-blue-primary transition-colors">
                        FAQ
                    </h3>
                    <p class="text-gray-400 mb-4">
                        Consultez les questions fréquemment posées pour trouver des réponses rapides
                    </p>
                    <a href="/faq" class="text-blue-primary hover:text-blue-secondary font-medium transition-colors inline-flex items-center gap-2">
                        Voir la FAQ
                        <i class="fas fa-arrow-right text-sm"></i>
                    </a>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-green-500/50 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-14 h-14 bg-green-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:bg-green-500/30 transition-colors">
                        <i class="fas fa-book text-green-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-green-400 transition-colors">
                        Documentation
                    </h3>
                    <p class="text-gray-400 mb-4">
                        Guides complets pour utiliser nos services et fonctionnalités
                    </p>
                    <a href="/docs" class="text-green-400 hover:text-green-300 font-medium transition-colors inline-flex items-center gap-2">
                        Consulter les docs
                        <i class="fas fa-arrow-right text-sm"></i>
                    </a>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-purple-500/50 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-14 h-14 bg-purple-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:bg-purple-500/30 transition-colors">
                        <i class="fas fa-play-circle text-purple-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-purple-400 transition-colors">
                        Tutoriels
                    </h3>
                    <p class="text-gray-400 mb-4">
                        Vidéos et guides pas à pas pour bien démarrer
                    </p>
                    <a href="/tutorials" class="text-purple-400 hover:text-purple-300 font-medium transition-colors inline-flex items-center gap-2">
                        Voir les tutoriels
                        <i class="fas fa-arrow-right text-sm"></i>
                    </a>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-orange-500/50 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-14 h-14 bg-orange-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:bg-orange-500/30 transition-colors">
                        <i class="fas fa-chart-line text-orange-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-orange-400 transition-colors">
                        Statut des services
                    </h3>
                    <p class="text-gray-400 mb-4">
                        Vérifiez l'état de nos services en temps réel
                    </p>
                    <a href="/status" class="text-orange-400 hover:text-orange-300 font-medium transition-colors inline-flex items-center gap-2">
                        Voir le statut
                        <i class="fas fa-arrow-right text-sm"></i>
                    </a>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-cyan-500/50 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-14 h-14 bg-cyan-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:bg-cyan-500/30 transition-colors">
                        <i class="fas fa-shopping-bag text-cyan-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-cyan-400 transition-colors">
                        Mes commandes
                    </h3>
                    <p class="text-gray-400 mb-4">
                        Suivez et gérez toutes vos commandes en un clin d'œil
                    </p>
                    <a href="/orders" class="text-cyan-400 hover:text-cyan-300 font-medium transition-colors inline-flex items-center gap-2">
                        Voir mes commandes
                        <i class="fas fa-arrow-right text-sm"></i>
                    </a>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-yellow-500/50 transition-all duration-300 group" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-14 h-14 bg-yellow-500/20 rounded-xl flex items-center justify-center mb-4 group-hover:bg-yellow-500/30 transition-colors">
                        <i class="fas fa-file-contract text-yellow-400 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3 group-hover:text-yellow-400 transition-colors">
                        Conditions générales
                    </h3>
                    <p class="text-gray-400 mb-4">
                        Consultez nos conditions d'utilisation et de vente
                    </p>
                    <a href="/legal/terms" class="text-yellow-400 hover:text-yellow-300 font-medium transition-colors inline-flex items-center gap-2">
                        Lire les CGV
                        <i class="fas fa-arrow-right text-sm"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto mb-12" data-aos="fade-up">
            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-8">
                <div class="text-center">
                    <h2 class="text-2xl font-bold text-white mb-4">
                        Autres moyens de nous contacter
                    </h2>
                    <p class="text-gray-400 mb-8">
                        Bien que Discord soit notre moyen de support principal, vous pouvez aussi nous contacter via :
                    </p>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

                        <div class="bg-dark-700/50 rounded-xl p-6 hover:bg-dark-700 transition-colors">
                            <div class="w-12 h-12 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fas fa-envelope text-blue-primary text-xl"></i>
                            </div>
                            <h3 class="text-white font-semibold mb-2">Email</h3>
                            <p class="text-gray-400 text-sm mb-3">Pour les demandes formelles</p>
                            <a href="mailto:support@piloteshopping.com" class="text-blue-primary hover:text-blue-secondary text-sm font-medium transition-colors">
                                support@piloteshopping.com
                            </a>
                            <p class="text-gray-500 text-xs mt-2">Réponse sous 48h</p>
                        </div>

                        <div class="bg-dark-700/50 rounded-xl p-6 hover:bg-dark-700 transition-colors">
                            <div class="w-12 h-12 bg-cyan-500/20 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fab fa-twitter text-cyan-400 text-xl"></i>
                            </div>
                            <h3 class="text-white font-semibold mb-2">Twitter</h3>
                            <p class="text-gray-400 text-sm mb-3">Actualités et support</p>
                            <a href="https://twitter.com/piloteshopping" target="_blank" rel="noopener noreferrer" class="text-cyan-400 hover:text-cyan-300 text-sm font-medium transition-colors">
                                @piloteshopping
                            </a>
                            <p class="text-gray-500 text-xs mt-2">Réponse rapide</p>
                        </div>

                        <div class="bg-gradient-to-br from-indigo-500/20 to-purple-500/20 rounded-xl p-6 border border-indigo-500/30">
                            <div class="w-12 h-12 bg-indigo-500/30 rounded-full flex items-center justify-center mx-auto mb-3">
                                <i class="fab fa-discord text-indigo-400 text-xl"></i>
                            </div>
                            <h3 class="text-white font-semibold mb-2">Discord</h3>
                            <p class="text-gray-300 text-sm mb-3">
                                <span class="bg-green-500/20 text-green-400 text-xs px-2 py-1 rounded-full">Recommandé</span>
                            </p>
                            <a href="<?= htmlspecialchars($discordInviteLink) ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-400 hover:text-indigo-300 text-sm font-medium transition-colors">
                                Rejoindre
                            </a>
                            <p class="text-indigo-300 text-xs mt-2">Réponse immédiate</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="max-w-4xl mx-auto" data-aos="fade-up">
            <h2 class="text-2xl font-bold text-white text-center mb-8">
                Questions fréquentes
            </h2>

            <div class="space-y-4">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 overflow-hidden">
                    <button onclick="toggleFAQ('faq1')" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-dark-700/50 transition-colors">
                        <span class="text-white font-medium">Comment suivre ma commande ?</span>
                        <i id="faq1-icon" class="fas fa-chevron-down text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="faq1" class="faq-content hidden px-6 pb-4">
                        <p class="text-gray-400 text-sm">
                            Vous pouvez suivre votre commande directement depuis votre <a href="/dashboard" class="text-blue-primary hover:underline">tableau de bord</a> ou depuis la page <a href="/orders" class="text-blue-primary hover:underline">Mes commandes</a>. Vous recevrez également des notifications par email à chaque étape.
                        </p>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 overflow-hidden">
                    <button onclick="toggleFAQ('faq2')" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-dark-700/50 transition-colors">
                        <span class="text-white font-medium">Quels sont les délais de livraison ?</span>
                        <i id="faq2-icon" class="fas fa-chevron-down text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="faq2" class="faq-content hidden px-6 pb-4">
                        <p class="text-gray-400 text-sm">
                            La plupart de nos produits sont livrés instantanément après validation du paiement. Pour les produits nécessitant une intervention manuelle, le délai peut aller jusqu'à 24-48h. Les délais spécifiques sont indiqués sur chaque fiche produit.
                        </p>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 overflow-hidden">
                    <button onclick="toggleFAQ('faq3')" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-dark-700/50 transition-colors">
                        <span class="text-white font-medium">Comment recharger mon solde ?</span>
                        <i id="faq3-icon" class="fas fa-chevron-down text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="faq3" class="faq-content hidden px-6 pb-4">
                        <p class="text-gray-400 text-sm">
                            Rendez-vous sur votre <a href="/wallet" class="text-blue-primary hover:underline">portefeuille</a> et cliquez sur "Recharger le solde". Vous pouvez utiliser une carte bancaire ou PayPal. Les fonds sont crédités instantanément.
                        </p>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 overflow-hidden">
                    <button onclick="toggleFAQ('faq4')" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-dark-700/50 transition-colors">
                        <span class="text-white font-medium">Que faire si j'ai un problème avec ma commande ?</span>
                        <i id="faq4-icon" class="fas fa-chevron-down text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="faq4" class="faq-content hidden px-6 pb-4">
                        <p class="text-gray-400 text-sm">
                            Contactez-nous immédiatement sur notre <a href="<?= htmlspecialchars($discordInviteLink) ?>" target="_blank" class="text-indigo-400 hover:underline">serveur Discord</a> avec votre numéro de commande. Notre équipe vous répondra dans les plus brefs délais pour résoudre le problème.
                        </p>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-dark-600 overflow-hidden">
                    <button onclick="toggleFAQ('faq5')" class="w-full px-6 py-4 text-left flex items-center justify-between hover:bg-dark-700/50 transition-colors">
                        <span class="text-white font-medium">Les paiements sont-ils sécurisés ?</span>
                        <i id="faq5-icon" class="fas fa-chevron-down text-gray-400 transition-transform duration-300"></i>
                    </button>
                    <div id="faq5" class="faq-content hidden px-6 pb-4">
                        <p class="text-gray-400 text-sm">
                            Oui, tous les paiements sont sécurisés via Stripe, certifié PCI-DSS niveau 1. Vos données bancaires sont cryptées et nous ne stockons jamais vos informations de carte complètes. La protection 3D Secure est également activée pour une sécurité maximale.
                        </p>
                    </div>
                </div>
            </div>

            <div class="text-center mt-8">
                <a href="/faq" class="inline-flex items-center gap-2 text-blue-primary hover:text-blue-secondary font-medium transition-colors">
                    Voir toutes les questions fréquentes
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

    </div>
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
});

function toggleFAQ(id) {
    const content = document.getElementById(id);
    const icon = document.getElementById(id + '-icon');

    document.querySelectorAll('.faq-content').forEach(faq => {
        if (faq.id !== id && !faq.classList.contains('hidden')) {
            faq.classList.add('hidden');
            const faqIcon = document.getElementById(faq.id + '-icon');
            if (faqIcon) {
                faqIcon.classList.remove('rotate-180');
            }
        }
    });

    content.classList.toggle('hidden');
    icon.classList.toggle('rotate-180');
}

const discordButtons = document.querySelectorAll('a[href*="discord"]');
discordButtons.forEach(button => {
    button.addEventListener('mouseenter', function() {
        this.style.transform = 'scale(1.05)';
    });

    button.addEventListener('mouseleave', function() {
        this.style.transform = 'scale(1)';
    });
});
</script>

<style>

@keyframes pulse-slow {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.05);
    }
}

.animate-pulse-slow {
    animation: pulse-slow 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.faq-content {
    transition: max-height 0.3s ease-out;
}

.group:hover {
    transform: translateY(-4px);
}

.rotate-180 {
    transform: rotate(180deg);
}

@keyframes gradient-animation {
    0% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
    100% {
        background-position: 0% 50%;
    }
}

@media (max-width: 768px) {
    .text-4xl.md\:text-5xl {
        font-size: 2rem;
    }

    .w-24.md\:w-32 {
        width: 6rem;
    }

    .h-24.md\:h-32 {
        height: 6rem;
    }
}

a[target="_blank"] {
    position: relative;
}

a[target="_blank"]:hover {
    text-decoration: none;
}

.group:hover i {
    animation: bounce 0.5s ease-in-out;
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-5px);
    }
}
</style>

<?php
endHTMLPage();
?>