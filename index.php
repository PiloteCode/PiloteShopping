<?php
define('API_ACCESS', true);

require_once 'api/v1/config.php';

session_start();

require_once 'includes/import.php';
require_once 'includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$seo = [
    'title' => 'Pilote Shopping - Fermeture Définitive',
    'description' => 'Annonce de fermeture définitive de Pilote Shopping. Merci à toute notre communauté pour votre confiance.',
    'keywords' => 'pilote shopping, fermeture, annonce',
    'og_image' => '/assets/images/og-home.jpg'
];

startHTMLPage($seo);
?>


<?php require_once 'includes/menu.php'; ?>


<main id="main-content" class="min-h-screen flex items-center justify-center py-20">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-4xl mx-auto">

            <div class="bg-dark-800/80 backdrop-blur-lg rounded-3xl border border-red-500/50 p-12 text-center shadow-2xl">

                <div class="w-24 h-24 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-8">
                    <i class="fas fa-exclamation-triangle text-red-500 text-5xl"></i>
                </div>

                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6">
                    Fermeture Définitive
                </h1>

                <div class="inline-block px-6 py-3 bg-red-500/20 border border-red-500/30 rounded-full text-red-400 font-semibold mb-8">
                    <i class="fas fa-store-slash mr-2"></i>
                    La boutique Pilote Shopping est définitivement fermée
                </div>

                <div class="text-lg md:text-xl text-gray-300 mb-8 leading-relaxed space-y-4">
                    <p>
                        Nous tenons à vous informer que <strong class="text-white">Pilote Shopping</strong>
                        a cessé définitivement ses activités.
                    </p>
                    <p>
                        Nous remercions sincèrement tous nos clients pour leur confiance et leur fidélité
                        tout au long de cette aventure.
                    </p>
                    <p class="text-gray-400 text-base">
                        Aucune nouvelle commande ne sera acceptée et tous les services ont été interrompus.
                    </p>
                </div>

                <div class="border-t border-dark-600 my-8"></div>

                <div class="bg-dark-700/50 rounded-2xl p-6 mb-8">
                    <h2 class="text-2xl font-bold text-white mb-6">
                        <i class="fas fa-info-circle text-blue-400 mr-2"></i>
                        Informations Importantes
                    </h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-left">
                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 bg-red-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-shopping-cart text-red-400"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-2">Nouvelles Commandes</h3>
                                <p class="text-gray-400 text-sm">
                                    Aucune nouvelle commande ne peut être passée sur la plateforme.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 bg-orange-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-box text-orange-400"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-2">Services Actifs</h3>
                                <p class="text-gray-400 text-sm">
                                    Tous les services et abonnements ont été interrompus.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-headset text-purple-400"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-2">Support Client</h3>
                                <p class="text-gray-400 text-sm">
                                    Le support client n'est plus disponible.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-user text-blue-400"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-2">Comptes Utilisateurs</h3>
                                <p class="text-gray-400 text-sm">
                                    Les comptes restent accessibles en lecture seule.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gradient-to-r from-blue-primary/10 to-purple-500/10 border border-blue-primary/30 rounded-2xl p-6">
                    <h2 class="text-2xl font-bold text-white mb-4">
                        <i class="fas fa-heart text-red-400 mr-2"></i>
                        Merci pour votre confiance
                    </h2>
                    <p class="text-gray-300">
                        Toute l'équipe de Pilote Shopping vous remercie pour votre soutien et votre fidélité.
                        Cette aventure n'aurait pas été possible sans vous.
                    </p>
                </div>

<?php if ($currentUser): ?>

                <div class="mt-8">
                    <a href="/dashboard" class="inline-block px-8 py-4 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white rounded-xl font-semibold transition-all duration-300 transform hover:scale-105">
                        <i class="fas fa-user mr-2"></i>
                        Accéder à mon compte
                    </a>
                </div>
<?php endif; ?>
            </div>

        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function() {
    adjustContentMargin();
    window.addEventListener('resize', adjustContentMargin);
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
</script>

<?php
endHTMLPage();
?>
