<?php
define('API_ACCESS', true);

require_once 'api/v1/config.php';

session_start();

require_once 'includes/import.php';
require_once 'includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$seo = [
    'title' => 'À propos d\'Ewan BERAUD - Expert en Tarification Localisée | Pilote Shopping',
    'description' => 'Découvrez Ewan BERAUD, 19 ans, expert reconnu #1 en France en tarification localisée. Étudiant alternant spécialisé dans l\'optimisation des coûts numériques avec 3 ans d\'expertise.',
    'keywords' => 'ewan beraud, pilote shopping, tarification localisée, expert france, services premium, économies numériques, étudiant alternant, optimisation coûts',
    'og_image' => '/assets/images/ewan-profile-social.jpg'
];

startHTMLPage($seo);
?>


<?php require_once 'includes/menu.php'; ?>


<main id="main-content" class="min-h-screen">

<section id="hero-about" class="relative min-h-screen flex items-center overflow-hidden">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-primary/20 rounded-full blur-3xl animate-pulse"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-radial opacity-30"></div>
    </div>

    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-6xl mx-auto">

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

                <div class="text-center lg:text-left" data-aos="fade-right">
                    <div class="relative inline-block mb-8">

                        <div class="relative">
                            <div class="w-80 h-80 mx-auto lg:mx-0 rounded-full overflow-hidden border-4 border-blue-primary/30 shadow-2xl group relative">
                                <img src="/assets/images/ewan-profile.jpg" 
                                     alt="Ewan BERAUD - Expert en Tarification Localisée" 
                                     class="w-full h-full object-cover transition-all duration-500 group-hover:brightness-110 group-hover:contrast-110 group-hover:scale-105">

                                <div class="absolute inset-0 bg-gradient-to-tr from-transparent via-white/10 to-transparent opacity-0 group-hover:opacity-100 transition-all duration-500 transform -skew-x-12 group-hover:translate-x-full"></div>

</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 max-w-md mx-auto lg:mx-0">
                        <div class="text-center bg-dark-800/50 backdrop-blur-sm rounded-xl p-4 border border-dark-600 hover:border-blue-primary/50 transition-all duration-300 group">
                            <div class="text-2xl font-bold text-blue-primary animated-counter group-hover:scale-110 transition-transform duration-300" data-target="3">0</div>
                            <div class="text-gray-400 text-sm">Années d'expertise</div>
                        </div>
                        <div class="text-center bg-dark-800/50 backdrop-blur-sm rounded-xl p-4 border border-dark-600 hover:border-green-400/50 transition-all duration-300 group">
                            <div class="text-2xl font-bold text-green-400 group-hover:scale-110 transition-transform duration-300">70%</div>
                            <div class="text-gray-400 text-sm">Économies max</div>
                        </div>
                        <div class="text-center bg-dark-800/50 backdrop-blur-sm rounded-xl p-4 border border-dark-600 hover:border-purple-400/50 transition-all duration-300 group">
                            <div class="text-2xl font-bold text-purple-400 animated-counter group-hover:scale-110 transition-transform duration-300" data-target="150">0</div>
                            <div class="text-gray-400 text-sm">Services maîtrisés</div>
                        </div>
                    </div>
                </div>

                <div data-aos="fade-left">
                    <div class="inline-flex items-center space-x-2 bg-gradient-to-r from-blue-primary/20 to-purple-500/20 rounded-full px-6 py-2 mb-6 border border-blue-primary/30">
                        <i class="fas fa-star text-yellow-400"></i>
                        <span class="text-white font-medium">Expert #1 France</span>
                    </div>

                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6 leading-tight">
                        Salut, je suis
                        <span class="block bg-gradient-to-r from-blue-primary to-purple-500 bg-clip-text text-transparent">
                            Ewan BERAUD
                        </span>
                    </h1>

                    <div class="text-xl md:text-2xl text-gray-300 mb-8 leading-relaxed">
                        <p class="mb-4">
                            Reconnu comme <span class="text-blue-400 font-semibold">l'expert référence en France</span> 
                            dans le domaine de la tarification localisée.
                        </p>
                        <p>
                            J'aide des milliers de personnes à <span class="text-green-400 font-semibold">économiser jusqu'à 70%</span> 
                            sur leurs services numériques favoris.
                        </p>
                    </div>

                    <div class="space-y-4 mb-8">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-blue-primary/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-blue-primary"></i>
                            </div>
                            <div>
                                <p class="text-white font-medium">Strasbourg, France</p>
                                <p class="text-gray-400 text-sm">Étudiant alternant - Maison de santé Bethel</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-green-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-certificate text-green-500"></i>
                            </div>
                            <div>
                                <p class="text-white font-medium">Expert en optimisation des coûts</p>
                                <p class="text-gray-400 text-sm">Spécialisé dans la tarification géo-localisée</p>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 bg-purple-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-users text-purple-500"></i>
                            </div>
                            <div>
                                <p class="text-white font-medium">Milliers de clients satisfaits</p>
                                <p class="text-gray-400 text-sm">Support personnalisé 7j/7</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-4 mb-6">
                        <a href="#mon-histoire" class="group px-8 py-4 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white rounded-xl font-semibold text-lg shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-book-open mr-2"></i>
                            Mon parcours
                            <i class="fas fa-arrow-down ml-2 group-hover:translate-y-1 transition-transform"></i>
                        </a>

                        <a href="#competences" class="group px-8 py-4 bg-dark-800 hover:bg-dark-700 text-white rounded-xl font-semibold text-lg border border-blue-primary/30 hover:border-blue-primary transition-all duration-300">
                            <i class="fas fa-cogs mr-2"></i>
                            Mes compétences
                        </a>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-4 border border-dark-600 inline-block">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-blue-primary/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-envelope text-blue-primary"></i>
                            </div>
                            <div>
                                <p class="text-gray-400 text-sm">Contact direct :</p>
                                <a href="mailto:ewan.beraud@piloteshopping.com" class="text-white hover:text-blue-400 font-medium transition-colors">
                                    ewan.beraud@piloteshopping.com
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="competences" class="py-24 bg-gradient-to-b from-dark-900 to-dark-800">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-6xl mx-auto">

            <div class="text-center mb-16">
                <div class="inline-block px-4 py-2 bg-blue-primary/10 border border-blue-primary/20 rounded-full text-blue-400 text-sm font-medium mb-6" data-aos="fade-up">
                    EXPERTISE TECHNIQUE
                </div>
                <h2 class="text-3xl lg:text-5xl font-bold text-white mb-6" data-aos="fade-up" data-aos-delay="100">
                    Mes domaines d'expertise
                </h2>
                <p class="text-gray-400 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="200">
                    3 années d'expérience dans l'optimisation des coûts numériques avec une maîtrise 
                    complète des techniques de tarification localisée.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-red-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="100">
                    <div class="w-16 h-16 bg-red-500/20 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-play text-red-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Streaming & Divertissement</h3>
                    <p class="text-gray-300 mb-6">Netflix, Disney+, Spotify, YouTube Premium, Amazon Prime...</p>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-green-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="200">
                    <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-gamepad text-green-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Gaming</h3>
                    <p class="text-gray-300 mb-6">PlayStation Plus, Xbox Game Pass, Steam, Epic Games...</p>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-blue-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="300">
                    <div class="w-16 h-16 bg-blue-500/20 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-briefcase text-blue-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Productivité</h3>
                    <p class="text-gray-300 mb-6">Office 365, Adobe Creative, Notion, Canva Pro...</p>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-purple-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="400">
                    <div class="w-16 h-16 bg-purple-500/20 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-shield-alt text-purple-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">VPN & Sécurité</h3>
                    <p class="text-gray-300 mb-6">NordVPN, ExpressVPN, Surfshark, antivirus premium...</p>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-yellow-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="500">
                    <div class="w-16 h-16 bg-yellow-500/20 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-cloud text-yellow-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Cloud & Stockage</h3>
                    <p class="text-gray-300 mb-6">iCloud, Google Drive, Dropbox, OneDrive...</p>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-pink-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="600">
                    <div class="w-16 h-16 bg-pink-500/20 rounded-full flex items-center justify-center mb-6">
                        <i class="fas fa-magic text-pink-500 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Services Spécialisés</h3>
                    <p class="text-gray-300 mb-6">ChatGPT Plus, GitHub Pro, et bien d'autres...</p>
                </div>
            </div>

            <div class="mt-16 grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="text-4xl font-bold text-blue-primary animated-counter" data-target="152">0</div>
                    <div class="text-gray-400">Services maîtrisés</div>
                </div>
                <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="text-4xl font-bold text-green-400">95%</div>
                    <div class="text-gray-400">Économies maximales</div>
                </div>
                <div class="text-center" data-aos="fade-up" data-aos-delay="300">
                    <div class="text-4xl font-bold text-purple-400 animated-counter" data-target="30000">0</div>
                    <div class="text-gray-400">Clients satisfaits</div>
                </div>
                <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                    <div class="text-4xl font-bold text-yellow-400">90%</div>
                    <div class="text-gray-400">Taux de réussite</div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="mon-histoire" class="py-24 bg-gradient-to-b from-dark-800 to-dark-900">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-6xl mx-auto">

            <div class="text-center mb-16">
                <div class="inline-block px-4 py-2 bg-blue-primary/10 border border-blue-primary/20 rounded-full text-blue-400 text-sm font-medium mb-6" data-aos="fade-up">
                    MON PARCOURS
                </div>
                <h2 class="text-3xl lg:text-5xl font-bold text-white mb-6" data-aos="fade-up" data-aos-delay="100">
                    Comment tout a commencé
                </h2>
                <p class="text-gray-400 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="200">
                    De simple étudiant curieux à expert : l'histoire de ma transformation 
                    en spécialiste de l'optimisation des coûts numériques.
                </p>
            </div>

            <div class="relative">

                <div class="absolute left-1/2 transform -translate-x-1/2 w-1 h-full bg-gradient-to-b from-blue-primary via-green-500 to-purple-500 hidden lg:block"></div>

                <div class="relative mb-16" data-aos="fade-right">
                    <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                        <div class="lg:text-right mb-8 lg:mb-0">
                            <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-blue-primary/50 transition-all duration-300">
                                <div class="flex items-center space-x-3 mb-6 lg:justify-end">
                                    <div class="w-16 h-16 bg-blue-primary/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-seedling text-blue-primary text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold text-white">2022 - Les Débuts</h3>
                                        <p class="text-blue-400 font-medium">Première curiosité</p>
                                    </div>
                                </div>

                                <p class="text-gray-300 text-lg leading-relaxed mb-6">
                                    Étudiant en informatique, je cherchais des moyens d'économiser sur mes 
                                    <strong class="text-white">abonnements numériques</strong>. Budget étudiant oblige !
                                </p>

                                <p class="text-gray-300 text-lg leading-relaxed">
                                    C'est en cherchant une solution pour réduire mes frais que j'ai découvert 
                                    <span class="text-blue-400 font-semibold">l'existence de la tarification géo-localisée</span>.
                                </p>

                                <div class="mt-6 grid grid-cols-2 gap-4">
                                    <div class="bg-blue-primary/10 rounded-xl p-3 border border-blue-primary/20">
                                        <div class="text-lg font-bold text-blue-400">Premier test</div>
                                        <div class="text-gray-400 text-sm">Discord Nitro à -93%</div>
                                    </div>
                                    <div class="bg-blue-primary/10 rounded-xl p-3 border border-blue-primary/20">
                                        <div class="text-lg font-bold text-blue-400">Révélation</div>
                                        <div class="text-gray-400 text-sm">Potentiel énorme</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="lg:pl-8">
                            <div class="relative">
                                <img src="https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=400&h=300&fit=crop&crop=center" 
                                     alt="Débuts 2022" 
                                     class="w-full h-80 object-cover rounded-2xl shadow-xl border border-dark-600">
                                <div class="absolute inset-0 bg-gradient-to-t from-blue-primary/20 to-transparent rounded-2xl"></div>

                                <div class="absolute top-4 left-4">
                                    <div class="bg-blue-primary/90 backdrop-blur-sm rounded-full px-4 py-2 text-white font-bold text-xl">
                                        2022
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 w-6 h-6 bg-blue-primary rounded-full border-4 border-dark-900 hidden lg:block"></div>
                </div>

                <div class="relative mb-16" data-aos="fade-left">
                    <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                        <div class="lg:pr-8 mb-8 lg:mb-0">
                            <div class="relative">
                                <img src="https://images.unsplash.com/photo-1513475382585-d06e58bcb0e0?w=400&h=300&fit=crop&crop=center" 
                                     alt="Recherche 2023" 
                                     class="w-full h-80 object-cover rounded-2xl shadow-xl border border-dark-600">
                                <div class="absolute inset-0 bg-gradient-to-t from-green-500/20 to-transparent rounded-2xl"></div>

                                <div class="absolute top-4 right-4">
                                    <div class="bg-green-500/90 backdrop-blur-sm rounded-full px-4 py-2 text-white font-bold text-xl">
                                        2023
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-green-500/50 transition-all duration-300">
                                <div class="flex items-center space-x-3 mb-6">
                                    <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-search text-green-500 text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold text-white">2023 - L'Approfondissement</h3>
                                        <p class="text-green-400 font-medium">Recherche intensive</p>
                                    </div>
                                </div>

                                <p class="text-gray-300 text-lg leading-relaxed mb-6">
                                    Fasciné par les possibilités, je me suis lancé dans une 
                                    <strong class="text-white">recherche approfondie</strong> de toutes les méthodes existantes.
                                </p>

                                <p class="text-gray-300 text-lg leading-relaxed mb-6">
                                    Tests, analyses, documentation... J'ai passé des centaines d'heures à 
                                    <span class="text-green-400 font-semibold">perfectionner mes techniques</span> 
                                    et à comprendre chaque subtilité.
                                </p>

                                <div class="space-y-3">
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-check text-green-400"></i>
                                        <span class="text-gray-300">50+ services analysés</span>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-check text-green-400"></i>
                                        <span class="text-gray-300">150 méthodes différentes testées</span>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <i class="fas fa-check text-green-400"></i>
                                        <span class="text-gray-300">Documentation complète créée</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 w-6 h-6 bg-green-500 rounded-full border-4 border-dark-900 hidden lg:block"></div>
                </div>

                <div class="relative mb-16" data-aos="fade-right">
                    <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                        <div class="lg:text-right mb-8 lg:mb-0">
                            <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-yellow-500/50 transition-all duration-300">
                                <div class="flex items-center space-x-3 mb-6 lg:justify-end">
                                    <div class="w-16 h-16 bg-yellow-500/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-share-alt text-yellow-500 text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold text-white">2024 - Le Partage</h3>
                                        <p class="text-yellow-400 font-medium">Premiers clients</p>
                                    </div>
                                </div>

                                <p class="text-gray-300 text-lg leading-relaxed mb-6">
                                    Mes proches ont commencé à me demander de l'aide pour 
                                    <strong class="text-white">réduire leurs factures</strong>. 
                                    Le bouche-à-oreille a fait le reste.
                                </p>

                                <p class="text-gray-300 text-lg leading-relaxed mb-6">
                                    J'ai réalisé que mon expertise pouvait 
                                    <span class="text-yellow-400 font-semibold">vraiment aider les gens</span> 
                                    à économiser des centaines d'euros par an.
                                </p>

                                <div class="mt-6 p-4 bg-yellow-500/10 rounded-xl border border-yellow-500/20">
                                    <p class="text-yellow-300 font-medium">
                                        <i class="fas fa-quote-left mr-2"></i>
                                        "Première économie réalisée pour un ami : 430€/an sur ses abonnements !"
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="lg:pl-8">
                            <div class="relative">
                                <img src="https://images.unsplash.com/photo-1552664730-d307ca884978?w=400&h=300&fit=crop&crop=center" 
                                     alt="Partage 2024" 
                                     class="w-full h-80 object-cover rounded-2xl shadow-xl border border-dark-600">
                                <div class="absolute inset-0 bg-gradient-to-t from-yellow-500/20 to-transparent rounded-2xl"></div>

                                <div class="absolute top-4 left-4">
                                    <div class="bg-yellow-500/90 backdrop-blur-sm rounded-full px-4 py-2 text-white font-bold text-xl">
                                        2024
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 w-6 h-6 bg-yellow-500 rounded-full border-4 border-dark-900 hidden lg:block"></div>
                </div>

                <div class="relative" data-aos="fade-left">
                    <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                        <div class="lg:pr-8 mb-8 lg:mb-0">
                            <div class="relative">
                                <img src="/assets/images/apropos/expertise.png" 
                                     alt="Expertise 2025" 
                                     class="w-full h-80 object-cover rounded-2xl shadow-xl border border-dark-600">
                                <div class="absolute inset-0 bg-gradient-to-t from-purple-500/20 to-transparent rounded-2xl"></div>

                                <div class="absolute top-4 right-4">
                                    <div class="bg-purple-500/90 backdrop-blur-sm rounded-full px-4 py-2 text-white font-bold text-xl">
                                        2025
                                    </div>
                                </div>

                                <div class="absolute bottom-4 right-4">
                                    <div class="bg-yellow-500/90 backdrop-blur-sm rounded-full px-4 py-2 text-white font-bold text-sm">
                                        <i class="fas fa-crown mr-1"></i>Expert #1
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-purple-500/50 transition-all duration-300">
                                <div class="flex items-center space-x-3 mb-6">
                                    <div class="w-16 h-16 bg-purple-500/20 rounded-full flex items-center justify-center">
                                        <i class="fas fa-crown text-purple-500 text-2xl"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-2xl font-bold text-white">2025 - L'Expertise</h3>
                                        <p class="text-purple-400 font-medium">Référence nationale</p>
                                    </div>
                                </div>

                                <p class="text-gray-300 text-lg leading-relaxed mb-6">
                                    Aujourd'hui, <strong class="text-white">Pilote Shopping</strong> est reconnu 
                                    comme la référence en France pour l'optimisation des coûts numériques.
                                </p>

                                <p class="text-gray-300 text-lg leading-relaxed mb-6">
                                    Avec plus de <span class="text-purple-400 font-semibold">150 services maîtrisés</span> 
                                    et des milliers de clients satisfaits, je continue d'innover et d'améliorer mes services.
                                </p>

                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-purple-500/10 rounded-xl p-4 border border-purple-500/20">
                                        <div class="text-2xl font-bold text-purple-400 animated-counter" data-target="84000">0</div>
                                        <div class="text-gray-400 text-sm">Clients aidés</div>
                                    </div>
                                    <div class="bg-purple-500/10 rounded-xl p-4 border border-purple-500/20">
                                        <div class="text-2xl font-bold text-purple-400">1,850M€</div>
                                        <div class="text-gray-400 text-sm">Économies totales</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="absolute left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 w-8 h-8 bg-purple-500 rounded-full border-4 border-dark-900 hidden lg:block animate-pulse"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="methode" class="py-24 bg-gradient-to-b from-dark-900 to-dark-800">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-6xl mx-auto">

            <div class="text-center mb-16">
                <div class="inline-block px-4 py-2 bg-green-500/10 border border-green-500/20 rounded-full text-green-400 text-sm font-medium mb-6" data-aos="fade-up">
                    MÉTHODE DE TRAVAIL
                </div>
                <h2 class="text-3xl lg:text-5xl font-bold text-white mb-6" data-aos="fade-up" data-aos-delay="100">
                    Comment je procède
                </h2>
                <p class="text-gray-400 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="200">
                    Un processus éprouvé et transparent pour garantir les meilleures économies 
                    tout en respectant vos besoins et contraintes.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">

                <div class="text-center" data-aos="fade-up" data-aos-delay="100">
                    <div class="relative mb-6">
                        <div class="w-20 h-20 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto border-4 border-blue-primary/30">
                            <i class="fas fa-comments text-blue-primary text-2xl"></i>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-blue-primary rounded-full flex items-center justify-center text-white font-bold text-sm">
                            1
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Analyse des besoins</h3>
                    <p class="text-gray-300">
                        Échange détaillé pour comprendre vos services actuels, 
                        vos usages et vos objectifs d'économies.
                    </p>
                </div>

                <div class="text-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="relative mb-6">
                        <div class="w-20 h-20 bg-green-500/20 rounded-full flex items-center justify-center mx-auto border-4 border-green-500/30">
                            <i class="fas fa-search text-green-500 text-2xl"></i>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            2
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Recherche optimale</h3>
                    <p class="text-gray-300">
                        Identification des meilleures méthodes et régions 
                        pour maximiser vos économies sur chaque service.
                    </p>
                </div>

                <div class="text-center" data-aos="fade-up" data-aos-delay="300">
                    <div class="relative mb-6">
                        <div class="w-20 h-20 bg-purple-500/20 rounded-full flex items-center justify-center mx-auto border-4 border-purple-500/30">
                            <i class="fas fa-cogs text-purple-500 text-2xl"></i>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            3
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Mise en place</h3>
                    <p class="text-gray-300">
                        Configuration sécurisée de vos comptes avec 
                        les meilleures méthodes légales disponibles.
                    </p>
                </div>

                <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                    <div class="relative mb-6">
                        <div class="w-20 h-20 bg-yellow-500/20 rounded-full flex items-center justify-center mx-auto border-4 border-yellow-500/30">
                            <i class="fas fa-headset text-yellow-500 text-2xl"></i>
                        </div>
                        <div class="absolute -top-2 -right-2 w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white font-bold text-sm">
                            4
                        </div>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Support continu</h3>
                    <p class="text-gray-300">
                        Accompagnement personnalisé et support technique 
                        pour garantir le bon fonctionnement sur le long terme.
                    </p>
                </div>
            </div>

        </div>
    </div>
</section>

<section class="py-24 bg-gradient-to-b from-dark-800 to-dark-900">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-6xl mx-auto">

            <div class="text-center mb-16">
                <div class="inline-block px-4 py-2 bg-yellow-500/10 border border-yellow-500/20 rounded-full text-yellow-400 text-sm font-medium mb-6" data-aos="fade-up">
                    RÉSULTATS CONCRETS
                </div>
                <h2 class="text-3xl lg:text-5xl font-bold text-white mb-6" data-aos="fade-up" data-aos-delay="100">
                    Ce que mes clients économisent
                </h2>
                <p class="text-gray-400 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="200">
                    Des économies réelles et vérifiables sur tous types de services numériques.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-green-500/50 transition-all duration-300" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-red-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-play text-red-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Netflix Premium</h3>
                            <p class="text-gray-400 text-sm">Famille de 4 personnes</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Prix normal France</span>
                            <span class="text-red-400 font-bold">29,99€/mois</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Prix optimisé</span>
                            <span class="text-green-400 font-bold">8,99€/mois</span>
                        </div>
                        <div class="border-t border-dark-600 pt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-white font-semibold">Économie annuelle</span>
                                <span class="text-green-400 font-bold text-lg">252€</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-green-500/50 transition-all duration-300" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-blue-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-music text-blue-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Spotify Premium</h3>
                            <p class="text-gray-400 text-sm">Compte individuel</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Prix normal France</span>
                            <span class="text-red-400 font-bold">12,99€/mois</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Prix optimisé</span>
                            <span class="text-green-400 font-bold">1,74€/mois</span>
                        </div>
                        <div class="border-t border-dark-600 pt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-white font-semibold">Économie annuelle</span>
                                <span class="text-green-400 font-bold text-lg">135€</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-green-500/50 transition-all duration-300" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-12 h-12 bg-orange-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-palette text-orange-500"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Adobe Creative Cloud</h3>
                            <p class="text-gray-400 text-sm">Suite complète</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Prix normal France</span>
                            <span class="text-red-400 font-bold">79,99€/mois</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Prix optimisé</span>
                            <span class="text-green-400 font-bold">15,99€/mois</span>
                        </div>
                        <div class="border-t border-dark-600 pt-4">
                            <div class="flex justify-between items-center">
                                <span class="text-white font-semibold">Économie annuelle</span>
                                <span class="text-green-400 font-bold text-lg">768€</span>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-16 bg-gradient-to-r from-green-500/20 to-blue-primary/20 rounded-2xl p-8 border border-green-500/30 text-center">
                <h3 class="text-2xl font-bold text-white mb-4">Économies totales pour une famille type</h3>
                <div class="text-5xl font-bold text-green-400 animated-counter" data-target="1143">0</div>
                <p class="text-xl text-gray-300">€ économisés par an en moyenne</p>
            </div>
        </div>
    </div>
</section>

<section class="py-24 bg-gradient-to-b from-dark-900 to-dark-800">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-4xl mx-auto">

            <div class="text-center mb-16">
                <div class="inline-block px-4 py-2 bg-purple-500/10 border border-purple-500/20 rounded-full text-purple-400 text-sm font-medium mb-6" data-aos="fade-up">
                    QUESTIONS FRÉQUENTES
                </div>
                <h2 class="text-3xl lg:text-5xl font-bold text-white mb-6" data-aos="fade-up" data-aos-delay="100">
                    Pourquoi me faire confiance ?
                </h2>
                <p class="text-gray-400 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="200">
                    Les questions les plus fréquentes de mes clients avant de commencer à économiser.
                </p>
            </div>

            <div class="space-y-6">

                <div class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden" data-aos="fade-up" data-aos-delay="100">
                    <button class="w-full px-8 py-6 text-left flex items-center justify-between focus:outline-none group" onclick="toggleFAQ(this)">
                        <h3 class="text-xl font-semibold text-white group-hover:text-blue-400 transition-colors">
                            Vos méthodes sont-elles légales et sûres ?
                        </h3>
                        <i class="fas fa-chevron-down text-gray-400 transform transition-transform group-hover:text-blue-400"></i>
                    </button>
                    <div class="px-8 pb-6 hidden">
                        <p class="text-gray-300 leading-relaxed">
                            Absolument ! Je n'utilise que la tarification géo-localisée officielle proposée par les plateformes elles-mêmes. 
                            C'est parfaitement légal et sûr. Les entreprises pratiquent cette différenciation tarifaire 
                            en fonction des économies locales, et j'aide simplement mes clients à en bénéficier.
                        </p>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden" data-aos="fade-up" data-aos-delay="200">
                    <button class="w-full px-8 py-6 text-left flex items-center justify-between focus:outline-none group" onclick="toggleFAQ(this)">
                        <h3 class="text-xl font-semibold text-white group-hover:text-blue-400 transition-colors">
                            Y a-t-il des risques pour mes comptes existants ?
                        </h3>
                        <i class="fas fa-chevron-down text-gray-400 transform transition-transform group-hover:text-blue-400"></i>
                    </button>
                    <div class="px-8 pb-6 hidden">
                        <p class="text-gray-300 leading-relaxed">
                            Aucun risque ! Je travaille avec des méthodes éprouvées qui préservent vos comptes existants. 
                            Vos données, historiques et préférences restent intacts. En 3 ans d'activité, 
                            je n'ai jamais eu de problème avec les comptes de mes clients.
                        </p>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden" data-aos="fade-up" data-aos-delay="300">
                    <button class="w-full px-8 py-6 text-left flex items-center justify-between focus:outline-none group" onclick="toggleFAQ(this)">
                        <h3 class="text-xl font-semibold text-white group-hover:text-blue-400 transition-colors">
                            Combien de temps durent les économies ?
                        </h3>
                        <i class="fas fa-chevron-down text-gray-400 transform transition-transform group-hover:text-blue-400"></i>
                    </button>
                    <div class="px-8 pb-6 hidden">
                        <p class="text-gray-300 leading-relaxed">
                            Les économies sont durables ! Une fois le compte mise en place, 
                            vous continuez à bénéficier des tarifs optimisés mois après mois. 
                            Je privilégie les solutions pérennes plutôt que les méthodes temporaires. 
                            De plus, je propose un support continu pour s'assurer que tout fonctionne parfaitement 
                            et vous aider en cas de changement dans les politiques des plateformes.
                        </p>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl border border-dark-600 overflow-hidden" data-aos="fade-up" data-aos-delay="500">
                    <button class="w-full px-8 py-6 text-left flex items-center justify-between focus:outline-none group" onclick="toggleFAQ(this)">
                        <h3 class="text-xl font-semibold text-white group-hover:text-blue-400 transition-colors">
                            Comment puis-je être sûr de votre expertise ?
                        </h3>
                        <i class="fas fa-chevron-down text-gray-400 transform transition-transform group-hover:text-blue-400"></i>
                    </button>
                    <div class="px-8 pb-6 hidden">
                        <p class="text-gray-300 leading-relaxed">
                            Ma réputation parle pour moi : 3 ans d'expérience, plus de 1000 clients satisfaits, 
                            reconnu comme la référence #1 en France dans ce domaine. Je partage régulièrement 
                            mes connaissances et suis transparent sur mes méthodes. Vous pouvez consulter 
                            les retours de mes clients ou me contacter directement pour discuter de votre projet 
                            avant de vous engager.
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-16 text-center">
                <div class="inline-flex items-center space-x-6 bg-dark-800/50 backdrop-blur-sm rounded-2xl px-8 py-6 border border-dark-600">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-star text-yellow-400 text-xl"></i>
                        <span class="text-white font-semibold">97% de satisfaction</span>
                    </div>
                    <div class="w-px h-8 bg-dark-600"></div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-users text-blue-400 text-xl"></i>
                        <span class="text-white font-semibold">+10000 clients</span>
                    </div>
                    <div class="w-px h-8 bg-dark-600"></div>
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-shield-check text-green-400 text-xl"></i>
                        <span class="text-white font-semibold">3 ans d'expertise</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-24 bg-gradient-to-b from-dark-800 to-dark-900">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-6xl mx-auto">

            <div class="text-center mb-16">
                <div class="inline-block px-4 py-2 bg-purple-500/10 border border-purple-500/20 rounded-full text-purple-400 text-sm font-medium mb-6" data-aos="fade-up">
                    MES VALEURS
                </div>
                <h2 class="text-3xl lg:text-5xl font-bold text-white mb-6" data-aos="fade-up" data-aos-delay="100">
                    Ce qui me guide au quotidien
                </h2>
                <p class="text-gray-400 max-w-3xl mx-auto" data-aos="fade-up" data-aos-delay="200">
                    Mes principes fondamentaux qui garantissent un service éthique, 
                    transparent et bénéfique pour tous mes clients.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-blue-primary/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-16 h-16 bg-blue-primary/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-handshake text-blue-primary text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Transparence</h3>
                            <p class="text-blue-400">Honnêteté avant tout</p>
                        </div>
                    </div>
                    <p class="text-gray-300 leading-relaxed mb-4">
                        Je crois en la transparence totale. Chaque méthode que j'utilise est légale et éthique. 
                        Mes clients savent exactement ce qu'ils achètent et comment cela fonctionne.
                    </p>
                    <div class="flex items-center space-x-2 text-sm text-blue-300">
                        <i class="fas fa-check"></i>
                        <span>Explications détaillées des processus</span>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-green-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="200">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-leaf text-green-500 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Durabilité</h3>
                            <p class="text-green-400">Solutions pérennes</p>
                        </div>
                    </div>
                    <p class="text-gray-300 leading-relaxed mb-4">
                        Je privilégie les solutions durables qui fonctionnent sur le long terme. 
                        Pas de méthodes douteuses qui risquent de causer des problèmes.
                    </p>
                    <div class="flex items-center space-x-2 text-sm text-green-300">
                        <i class="fas fa-check"></i>
                        <span>Méthodes testées et approuvées</span>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-purple-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="300">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-16 h-16 bg-purple-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-heart text-purple-500 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Satisfaction</h3>
                            <p class="text-purple-400">Client au centre</p>
                        </div>
                    </div>
                    <p class="text-gray-300 leading-relaxed mb-4">
                        La satisfaction de mes clients est ma priorité absolue. 
                        Je m'assure que chaque personne économise réellement et obtient exactement ce qu'elle souhaite.
                    </p>
                    <div class="flex items-center space-x-2 text-sm text-purple-300">
                        <i class="fas fa-check"></i>
                        <span>Support personnalisé 7j/7</span>
                    </div>
                </div>

                <div class="bg-dark-800 rounded-2xl p-8 border border-dark-600 hover:border-yellow-500/50 transition-all duration-300 transform hover:scale-105" data-aos="fade-up" data-aos-delay="400">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-16 h-16 bg-yellow-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-graduation-cap text-yellow-500 text-2xl"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Éducation</h3>
                            <p class="text-yellow-400">Partage de connaissances</p>
                        </div>
                    </div>
                    <p class="text-gray-300 leading-relaxed mb-4">
                        J'aime partager mes connaissances et éduquer mes clients sur les bonnes pratiques. 
                        L'objectif est de créer une communauté informée et autonome.
                    </p>
                    <div class="flex items-center space-x-2 text-sm text-yellow-300">
                        <i class="fas fa-check"></i>
                        <span>Guides et formations en cours de réalisation</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-24">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="max-w-5xl mx-auto">
            <div class="bg-gradient-to-r from-blue-primary/20 to-purple-500/20 rounded-3xl border border-blue-primary/30 p-12 text-center relative overflow-hidden">

                <div class="absolute inset-0 -z-10">
                    <div class="absolute top-0 left-0 w-40 h-40 bg-blue-primary/20 rounded-full blur-3xl animate-pulse"></div>
                    <div class="absolute bottom-0 right-0 w-40 h-40 bg-purple-500/20 rounded-full blur-3xl animate-pulse" style="animation-delay: 1s;"></div>
                    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-80 h-80 bg-gradient-to-r from-blue-primary/10 to-purple-500/10 rounded-full blur-3xl"></div>
                </div>

                <div data-aos="fade-up">
                    <div class="w-20 h-20 bg-gradient-to-r from-blue-primary to-purple-500 rounded-full flex items-center justify-center mx-auto mb-8">
                        <i class="fas fa-rocket text-white text-3xl"></i>
                    </div>

                    <h2 class="text-3xl md:text-4xl lg:text-5xl font-bold text-white mb-6">
                        Prêt à économiser des centaines d'euros ?
                    </h2>
                    <p class="text-xl text-gray-300 mb-10 max-w-3xl mx-auto">
                        Rejoignez les milliers de clients qui font confiance à mon expertise 
                        pour optimiser leurs dépenses numériques. Commencez dès aujourd'hui !
                    </p>

                    <div class="flex flex-col sm:flex-row gap-6 justify-center mb-8">
                        <a href="/shop" class="group px-10 py-5 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition-all duration-300 transform hover:scale-105">
                            <i class="fas fa-shopping-cart mr-3"></i>
                            Voir nos offres
                            <i class="fas fa-arrow-right ml-3 group-hover:translate-x-1 transition-transform"></i>
                        </a>

                        <a href="mailto:ewan.beraud@piloteshopping.com" class="group px-10 py-5 bg-dark-800 hover:bg-dark-700 text-white rounded-xl font-bold text-lg border border-blue-primary/30 hover:border-blue-primary transition-all duration-300">
                            <i class="fas fa-envelope mr-3"></i>
                            Me contacter
                        </a>
                    </div>

                    <div class="flex flex-wrap justify-center items-center gap-8 text-sm text-gray-400">
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-crown text-yellow-400"></i>
                            <span>Expert #1 France</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-clock text-green-400"></i>
                            <span>Support 7j/7</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-shield-check text-purple-400"></i>
                            <span>Satisfait ou remboursé</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i class="fas fa-users text-blue-400"></i>
                            <span>+10000 clients satisfaits</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<?php require_once 'includes/footer.php'; ?>


<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>

document.addEventListener('DOMContentLoaded', function() {

    if (typeof AOS !== 'undefined') {

        AOS.init({
            duration: 800,
            once: true,
            offset: 50,
            easing: 'ease-in-out'
        });
    } else {
        console.warn('AOS library not loaded');
    }

    function animateCounters() {
        const counters = document.querySelectorAll('.animated-counter');

        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting && !entry.target.hasAttribute('data-animated')) {
                    const counter = entry.target;
                    const target = parseInt(counter.getAttribute('data-target'));

                    counter.setAttribute('data-animated', 'true');

                    let current = 0;
                    const increment = target / 100; 
                    const duration = 2000; 
                    const stepTime = duration / 100;

                    const timer = setInterval(() => {
                        current += increment;

                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }

                        if (target >= 1000) {
                            counter.textContent = Math.floor(current).toLocaleString('fr-FR');
                        } else {
                            counter.textContent = Math.floor(current);
                        }
                    }, stepTime);

                    counter.style.transform = 'scale(1.1)';
                    counter.style.transition = 'transform 0.3s ease';

                    setTimeout(() => {
                        counter.style.transform = 'scale(1)';
                    }, 300);

                    observer.unobserve(counter);
                }
            });
        }, observerOptions);

        counters.forEach(counter => {
            counter.textContent = '0'; 
            observer.observe(counter);
        });
    }

    window.toggleFAQ = function(button) {
        const content = button.nextElementSibling;
        const icon = button.querySelector('i');
        const allFAQs = document.querySelectorAll('.bg-dark-800 .hidden');
        const allIcons = document.querySelectorAll('.bg-dark-800 i.fa-chevron-down');

        allFAQs.forEach(el => {
            if (el !== content && !el.classList.contains('hidden')) {
                el.classList.add('hidden');
                el.style.maxHeight = '0px';
                el.style.opacity = '0';
            }
        });

        allIcons.forEach(el => {
            if (el !== icon) {
                el.classList.remove('rotate-180');
            }
        });

        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            content.style.maxHeight = content.scrollHeight + 'px';
            content.style.opacity = '1';
            content.style.transition = 'all 0.3s ease-in-out';
            icon.classList.add('rotate-180');
        } else {
            content.style.maxHeight = '0px';
            content.style.opacity = '0';
            icon.classList.remove('rotate-180');

            setTimeout(() => {
                content.classList.add('hidden');
            }, 300);
        }
    };

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);

            if (targetElement) {
                const headerHeight = document.querySelector('header')?.offsetHeight || 80;
                const targetPosition = targetElement.getBoundingClientRect().top + window.scrollY - headerHeight - 20;

                window.scrollTo({
                    top: targetPosition,
                    behavior: 'smooth'
                });
            }
        });
    });

    function adjustContentMargin() {
        const header = document.querySelector('header');
        const mainContent = document.getElementById('main-content');

        if (header && mainContent) {
            const headerHeight = header.offsetHeight;
            mainContent.style.paddingTop = `${headerHeight}px`;
        }
    }

    adjustContentMargin();
    window.addEventListener('resize', adjustContentMargin);

    let ticking = false;

    function updateParallax() {
        const scrolled = window.pageYOffset;
        const parallaxElements = document.querySelectorAll('.bg-gradient-radial');

        parallaxElements.forEach(element => {
            const speed = 0.05;
            const yPos = -(scrolled * speed);
            element.style.transform = `translateY(${yPos}px)`;
        });

        ticking = false;
    }

    window.addEventListener('scroll', () => {
        if (!ticking) {
            requestAnimationFrame(updateParallax);
            ticking = true;
        }
    });

    animateCounters();

    setTimeout(() => {
        adjustContentMargin();
        animateCounters();
    }, 500);
});

function formatNumber(num) {
    if (num >= 1000) {
        return num.toLocaleString('fr-FR');
    }
    return num.toString();
}

function fadeInOnScroll() {
    const elements = document.querySelectorAll('[data-aos]');

    elements.forEach(element => {
        if (!element.classList.contains('aos-animate')) {
            const elementTop = element.getBoundingClientRect().top;
            const elementVisible = 150;

            if (elementTop < window.innerHeight - elementVisible) {
                element.classList.add('aos-animate');
            }
        }
    });
}

if (typeof AOS === 'undefined') {
    window.addEventListener('scroll', fadeInOnScroll);
}

const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const fadeInObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
            fadeInObserver.unobserve(entry.target);
        }
    });
}, observerOptions);

document.addEventListener('DOMContentLoaded', () => {
    if (typeof AOS === 'undefined') {
        document.querySelectorAll('[data-aos]').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(30px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            fadeInObserver.observe(el);
        });
    }
});
</script>

<?php
endHTMLPage();
?>