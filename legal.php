<?php

define('API_ACCESS', true);

require_once 'api/v1/config.php';

session_start();

require_once 'includes/import.php';
require_once 'includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$seo = [
    'title' => 'Mentions Légales - Pilote Shopping',
    'description' => 'Consultez les mentions légales de Pilote Shopping. Informations sur l\'éditeur, l\'hébergeur et vos droits.',
    'keywords' => 'mentions légales, pilote shopping, RGPD, éditeur, hébergeur',
    'og_image' => '/assets/images/og-legal.jpg'
];

startHTMLPage($seo);
?>


<?php require_once 'includes/menu.php'; ?>

<main id="main-content" class="min-h-screen">

    <section class="py-16 border-b border-dark-600/50">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center space-x-2 bg-dark-800/50 rounded-full px-6 py-2 mb-6 border border-dark-600/50">
                    <i class="fas fa-balance-scale text-blue-primary"></i>
                    <span class="text-white font-medium">Informations légales</span>
                </div>

                <h1 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-6">
                    Mentions Légales
                </h1>

                <p class="text-xl text-gray-300 mb-8">
                    Informations obligatoires sur l'éditeur, l'hébergeur et vos droits
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center space-y-4 sm:space-y-0 sm:space-x-8 text-sm text-gray-400">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-calendar-alt text-blue-primary"></i>
                        <span>Dernière mise à jour : Septembre 2025</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-clock text-green-400"></i>
                        <span>Lecture : ~5 minutes</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto">

                <div class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-6 mb-12 backdrop-blur-sm">
                    <h3 class="text-xl font-bold text-white mb-4 flex items-center">
                        <i class="fas fa-list-ul text-blue-primary mr-3"></i>
                        Navigation rapide
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        <a href="#editeur" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 1</div>
                            <div class="text-gray-400 text-xs">Éditeur du Site</div>
                        </a>
                        <a href="#directeur" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 2</div>
                            <div class="text-gray-400 text-xs">Directeur de publication</div>
                        </a>
                        <a href="#hebergeur" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 3</div>
                            <div class="text-gray-400 text-xs">Hébergeur</div>
                        </a>
                        <a href="#propriete" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 4</div>
                            <div class="text-gray-400 text-xs">Propriété intellectuelle</div>
                        </a>
                        <a href="#donnees" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 5</div>
                            <div class="text-gray-400 text-xs">Données personnelles & RGPD</div>
                        </a>
                        <a href="#signalement" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 6</div>
                            <div class="text-gray-400 text-xs">Signalement de contenu</div>
                        </a>
                        <a href="#responsabilite" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 7</div>
                            <div class="text-gray-400 text-xs">Responsabilité</div>
                        </a>
                        <a href="#contact" class="block p-3 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors border border-dark-600/30 hover:border-blue-primary/50">
                            <div class="text-white font-semibold text-sm mb-1">Section 8</div>
                            <div class="text-gray-400 text-xs">Contact</div>
                        </a>
                    </div>
                </div>

                <div class="prose prose-invert prose-lg max-w-none">

                    <div id="editeur" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                            <i class="fas fa-building text-blue-primary mr-3"></i>
                            1) Éditeur du Site
                        </h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                <p><strong>Pilote Shopping</strong>, Entreprise Individuelle (EI)</p>
                                <p><strong>Représentant légal :</strong> M. Ewan Jefferson BERAUD</p>
                                <p><strong>Adresse :</strong> 67270 Schwindratzheim (France)</p>
                                <p><strong>SIRET :</strong> 934 019 613 00017</p>
                                <p><strong>RCS :</strong> RCS Strasbourg</p>
                                <p><strong>TVA :</strong> Non applicable – art. 293 B du CGI</p>
                                <p><strong>Contact (support) :</strong> <a href="mailto:support@piloteshopping.com">support@piloteshopping.com</a></p>
                            </div>
                        </div>
                    </div>

                    <div id="directeur" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                            <i class="fas fa-user-tie text-blue-primary mr-3"></i>
                            2) Directeur de la publication
                        </h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                <p><strong>M. Ewan Jefferson BERAUD</strong>, en qualité de représentant légal de Pilote Shopping.</p>
                            </div>
                        </div>
                    </div>

                    <div id="hebergeur" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                            <i class="fas fa-server text-blue-primary mr-3"></i>
                            3) Hébergeur
                        </h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                <p><strong>OneProvider – BrainStorm Network Inc. / Le Réseau BrainStorm Inc.</strong></p>
                                <p><strong>Adresse :</strong> 3275, avenue Francis-Hughes, Laval (Québec) H7L 5A5, Canada.</p>
                            </div>

                            <div class="space-y-2">
                                <p><strong>Page de contact (OneProvider) :</strong> formulaire de contact officiel (catégorie « Abuse », « General », etc.).</p>
                                <p><strong>Site web :</strong> <a href="https://oneprovider.com" target="_blank" rel="noopener">OneProvider.com</a></p>
                                <p><strong>Informations société/présentation :</strong> OneProvider (société canadienne).</p>
                            </div>
                        </div>
                    </div>

                    <div id="propriete" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                            <i class="fas fa-copyright text-blue-primary mr-3"></i>
                            4) Propriété intellectuelle
                        </h2>

                        <div class="space-y-6 text-gray-300 leading-relaxed">
                            <div>
                                <h3 class="text-lg font-bold text-white mb-3">Contenus du Site</h3>
                                <p>Le Site et ses contenus (textes, éléments graphiques, codes, bases de données, hors éléments de tiers) sont protégés par le Code de la propriété intellectuelle.</p>
                                <p>Toute reproduction, représentation, adaptation ou diffusion non autorisée, totale ou partielle, est interdite et susceptible de constituer une contrefaçon.</p>
                            </div>

                            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                <h3 class="text-lg font-bold text-white mb-3">Signes distinctifs de tiers</h3>
                                <p>Les marques, logos, noms commerciaux et habillages de tiers éventuellement cités sur le Site demeurent la propriété de leurs titulaires. Leur présence a un but descriptif et n'implique aucune affiliation, parrainage ou approbation par les titulaires.</p>
                            </div>
                        </div>
                    </div>

                    <div id="donnees" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                            <i class="fas fa-shield-alt text-blue-primary mr-3"></i>
                            5) Données personnelles (RGPD) & Cookies
                        </h2>

                        <div class="space-y-6 text-gray-300 leading-relaxed">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h3 class="text-white font-bold mb-2 text-sm">Responsable de traitement</h3>
                                    <p class="text-sm">Pilote Shopping, EI.</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h3 class="text-white font-bold mb-2 text-sm">Finalités</h3>
                                    <p class="text-sm">Gestion des comptes, commandes, prévention de la fraude, support, statistiques, communications liées au Site.</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h3 class="text-white font-bold mb-2 text-sm">Base(s) légale(s)</h3>
                                    <p class="text-sm">Exécution contractuelle, intérêt légitime, consentement (le cas échéant), obligations légales.</p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h3 class="text-white font-bold mb-2 text-sm">Durées de conservation</h3>
                                    <p class="text-sm">Limitées au nécessaire selon finalités et obligations.</p>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-lg font-bold text-white mb-3">Vos droits</h3>
                                <div class="bg-green-500/10 border border-green-500/30 rounded-xl p-4">
                                    <p><strong>Droits des personnes :</strong> accès, rectification, effacement, limitation, opposition, portabilité.</p>
                                    <p><strong>Exercice des droits :</strong> <a href="mailto:piloteshopping-legal@piloteproduction.yt">piloteshopping-legal@piloteproduction.yt</a> (joindre un justificatif d'identité si nécessaire).</p>
                                    <p><strong>Réclamation :</strong> CNIL (<a href="https://www.cnil.fr" target="_blank" rel="noopener">www.cnil.fr</a>).</p>
                                </div>
                            </div>

                            <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                <h3 class="text-lg font-bold text-white mb-3">Cookies</h3>
                                <p>Le Site utilise des cookies et technologies similaires. Vous pouvez les gérer via les réglages du navigateur et/ou les interfaces prévues sur le Site (bannière/console de préférences).</p>
                            </div>
                        </div>
                    </div>

                    <div id="signalement" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                            <i class="fas fa-flag text-blue-primary mr-3"></i>
                            6) Signalement de contenu illicite / abus
                        </h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <p>Pour signaler un contenu manifestement illicite ou un abus relatif à l'hébergement, vous pouvez :</p>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h3 class="text-white font-bold mb-2 flex items-center">
                                        <i class="fas fa-envelope text-green-400 mr-2"></i>
                                        Contacter l'Éditeur
                                    </h3>
                                    <p class="text-sm"><a href="mailto:support@piloteshopping.com">support@piloteshopping.com</a></p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h3 class="text-white font-bold mb-2 flex items-center">
                                        <i class="fas fa-server text-orange-400 mr-2"></i>
                                        Signalement technique
                                    </h3>
                                    <p class="text-sm">Formulaire de contact OneProvider (catégorie « Abuse ») avec les éléments nécessaires (URL, description, journaux/horodatage).</p>
                                    <p class="text-sm mt-2"><a href="https://oneprovider.com" target="_blank" rel="noopener">OneProvider.com</a></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="responsabilite" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                            <i class="fas fa-gavel text-blue-primary mr-3"></i>
                            7) Responsabilité
                        </h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4">
                                <p>L'Éditeur met tout en œuvre pour assurer l'exactitude et la mise à jour des informations mais ne peut garantir l'absence d'erreurs ou d'omissions.</p>
                            </div>

                            <p>L'accès au Site peut être interrompu pour maintenance, sécurité ou cas de force majeure.</p>

                            <p>L'Éditeur n'est pas responsable des contenus, produits ou services de tiers accessibles depuis le Site.</p>
                        </div>
                    </div>

                    <div id="contact" class="bg-dark-800/30 rounded-2xl border border-dark-600/50 p-8 mb-8 backdrop-blur-sm">
                        <h2 class="text-2xl font-bold text-white mb-6 flex items-center">
                            <i class="fas fa-envelope text-blue-primary mr-3"></i>
                            8) Contact
                        </h2>

                        <div class="space-y-4 text-gray-300 leading-relaxed">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h3 class="text-white font-bold mb-2 flex items-center">
                                        <i class="fas fa-headset text-blue-400 mr-2"></i>
                                        Support client
                                    </h3>
                                    <p><a href="mailto:support@piloteshopping.com">support@piloteshopping.com</a></p>
                                </div>

                                <div class="bg-dark-700/30 rounded-xl p-4 border border-dark-600/30">
                                    <h3 class="text-white font-bold mb-2 flex items-center">
                                        <i class="fas fa-balance-scale text-purple-400 mr-2"></i>
                                        Demandes légales / RGPD
                                    </h3>
                                    <p><a href="mailto:piloteshopping-legal@piloteproduction.yt">piloteshopping-legal@piloteproduction.yt</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-blue-primary/10 to-purple-500/10 border border-blue-primary/30 rounded-2xl p-8 mt-12 text-center backdrop-blur-sm">
                    <h3 class="text-2xl font-bold text-white mb-4">
                        <i class="fas fa-info-circle text-blue-primary mr-3"></i>
                        Besoin d'informations supplémentaires ?
                    </h3>
                    <p class="text-gray-300 mb-6">
                        Notre équipe est à votre disposition pour toute question relative aux mentions légales ou à vos droits.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="mailto:support@piloteshopping.com" class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white rounded-xl font-semibold transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-envelope mr-2"></i>
                            Support général
                        </a>
                        <a href="mailto:piloteshopping-legal@piloteproduction.yt" class="inline-flex items-center px-6 py-3 bg-dark-700/50 hover:bg-dark-600/50 text-white rounded-xl font-semibold border border-dark-600/50 hover:border-blue-primary/50 transition-all duration-300">
                            <i class="fas fa-balance-scale mr-2"></i>
                            Questions juridiques
                        </a>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 justify-center mt-4">
                        <a href="/terms" class="inline-flex items-center px-4 py-2 bg-dark-600/50 hover:bg-dark-500/50 text-white rounded-lg font-medium border border-dark-500/50 hover:border-gray-400/50 transition-all duration-300 text-sm">
                            <i class="fas fa-file-contract mr-2"></i>
                            CGU
                        </a>
                        <a href="/sales" class="inline-flex items-center px-4 py-2 bg-dark-600/50 hover:bg-dark-500/50 text-white rounded-lg font-medium border border-dark-500/50 hover:border-gray-400/50 transition-all duration-300 text-sm">
                            <i class="fas fa-shopping-cart mr-2"></i>
                            CGV
                        </a>
                        <a href="/privacy" class="inline-flex items-center px-4 py-2 bg-dark-600/50 hover:bg-dark-500/50 text-white rounded-lg font-medium border border-dark-500/50 hover:border-gray-400/50 transition-all duration-300 text-sm">
                            <i class="fas fa-shield-alt mr-2"></i>
                            Politique de confidentialité
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once 'includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function() {

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);

            if (targetElement) {
                const headerHeight = 100;
                const targetPosition = targetElement.getBoundingClientRect().top + window.scrollY - headerHeight;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    function adjustContentMargin() {
        const header = document.querySelector('#menuContainer');
        const mainContent = document.getElementById('main-content');

        if (header && mainContent) {
            const headerHeight = header.offsetHeight;
            const headerTop = parseInt(window.getComputedStyle(header).top) || 8;
            mainContent.style.paddingTop = `${headerHeight + headerTop + 20}px`;
        }
    }

    adjustContentMargin();
    window.addEventListener('resize', adjustContentMargin);
});
</script>

<style>

html {
    scroll-behavior: smooth;
}

.prose a[href^="#"] {
    color: #3b82f6;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.prose a[href^="#"]:hover {
    color: #60a5fa;
}

@media (max-width: 768px) {
    .prose {
        font-size: 14px;
    }

    .prose h2 {
        font-size: 1.5rem;
    }

    .container {
        padding-left: 0.75rem;
        padding-right: 0.75rem;
    }
}

.prose strong {
    color: #ffffff;
    font-weight: 700;
}

.prose a[href^="mailto:"],
.prose a[href^="http"] {
    color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.prose a[href^="mailto:"]:hover,
.prose a[href^="http"]:hover {
    color: #60a5fa;
    background: rgba(59, 130, 246, 0.2);
    transform: translateY(-1px);
}

.prose h2 i {
    font-size: 1.2em;
}

.bg-dark-700\/30:hover {
    background-color: rgba(55, 65, 81, 0.5);
    transform: translateY(-2px);
    transition: all 0.3s ease;
}

.bg-green-500\/10 {
    position: relative;
}

.bg-green-500\/10::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #10b981;
    border-radius: 2px;
}

.bg-yellow-500\/10 {
    position: relative;
}

.bg-yellow-500\/10::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #f59e0b;
    border-radius: 2px;
}
</style>

<?php
endHTMLPage();
?>