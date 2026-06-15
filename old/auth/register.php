<?php
define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
if ($auth->isAuthenticated()) {
    $currentUser = $auth->getCurrentUser();
    
        if ($auth->needsProfileCompletion()) {
        header('Location: /auth/complete-profile');
        exit();
    }
    
        header('Location: /dashboard');
    exit();
}

$seo = [
    'title' => 'Créer un compte - Pilote Shopping',
    'description' => 'Rejoignez Pilote Shopping et profitez d\'une expérience d\'achat premium avec des produits de qualité supérieure et un service client exceptionnel.',
    'keywords' => 'inscription, créer compte, register, pilote shopping, nouveau membre',
    'og_type' => 'website'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen">

<section class="min-h-screen relative overflow-hidden">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-1/4 right-1/4 w-96 h-96 bg-purple-500/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 left-1/3 w-80 h-80 bg-blue-primary/20 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-radial opacity-30"></div>
    </div>

    <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-12">
        <div class="w-full max-w-2xl space-y-8">

            <div class="text-center space-y-4" data-aos="fade-up">

                <div class="inline-flex items-center space-x-2 bg-gradient-to-r from-purple-500/20 to-blue-primary/20 rounded-full px-6 py-2 mb-6 border border-purple-500/30">
                    <i class="fas fa-user-plus text-purple-500"></i>
                    <span class="text-white font-medium">Nouvelle inscription</span>
                </div>

                <h1 class="text-4xl md:text-5xl font-bold text-white mb-6 leading-tight">
                    <span class="block">Rejoignez-nous !</span>
                    <span class="block bg-gradient-to-r from-purple-500 to-blue-primary bg-clip-text text-transparent">
                        Créez votre compte
                    </span>
                </h1>

                <p class="text-xl text-gray-300 mb-8 max-w-3xl mx-auto leading-relaxed">
                    Rejoignez Pilote Shopping et profitez d'une expérience d'achat premium avec des produits de qualité supérieure.
                </p>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-lg rounded-3xl border border-dark-600 p-8 lg:p-12 space-y-8 shadow-2xl" data-aos="fade-up" data-aos-delay="200">

                <div id="alert-container" class="hidden">
                    <div id="alert" class="p-4 rounded-xl text-sm font-medium border backdrop-blur-sm"></div>
                </div>

                <div class="bg-dark-700/50 backdrop-blur-sm rounded-2xl p-6 border border-dark-600">
                    <div class="flex items-center justify-between text-sm text-gray-300 mb-4">
                        <span class="flex items-center space-x-2">
                            <i class="fas fa-chart-line text-purple-500"></i>
                            <span>Étape <span id="step-current">1</span> sur 3</span>
                        </span>
                        <span id="step-title" class="font-bold text-purple-500">Informations personnelles</span>
                    </div>
                    <div class="w-full bg-dark-800 rounded-full h-3 overflow-hidden">
                        <div id="progress-bar" class="bg-gradient-to-r from-purple-500 to-blue-primary h-3 rounded-full transition-all duration-500 shadow-lg" style="width: 33.33%"></div>
                    </div>
                </div>

                <form id="registerForm" class="space-y-8" novalidate>

                    <div id="step-1" class="step-content space-y-6">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-blue-primary rounded-lg flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span>Informations personnelles</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label for="first_name" class="block text-sm font-medium text-gray-300">
                                    <i class="fas fa-user text-blue-primary mr-2"></i>Prénom *
                                </label>
                                <input 
                                    type="text" 
                                    id="first_name" 
                                    name="first_name" 
                                    required
                                    maxlength="50"
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                    placeholder="Jean"
                                >
                                <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                            </div>

                            <div class="space-y-2">
                                <label for="last_name" class="block text-sm font-medium text-gray-300">
                                    <i class="fas fa-user text-blue-primary mr-2"></i>Nom *
                                </label>
                                <input 
                                    type="text" 
                                    id="last_name" 
                                    name="last_name" 
                                    required
                                    maxlength="50"
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                    placeholder="Dupont"
                                >
                                <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="email" class="block text-sm font-medium text-gray-300">
                                <i class="fas fa-envelope text-blue-primary mr-2"></i>Adresse email *
                            </label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                required
                                autocomplete="email"
                                class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                placeholder="jean.dupont@email.com"
                            >
                            <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                        </div>

                        <div class="space-y-2">
                            <label for="username" class="block text-sm font-medium text-gray-300">
                                <i class="fas fa-at text-blue-primary mr-2"></i>Nom d'utilisateur *
                            </label>
                            <input 
                                type="text" 
                                id="username" 
                                name="username" 
                                required
                                maxlength="30"
                                pattern="[a-zA-Z0-9\-_]+"
                                class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                placeholder="jeandupont"
                            >
                            <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                            <p class="text-xs text-gray-400 flex items-center space-x-1">
                                <i class="fas fa-info-circle text-blue-primary"></i>
                                <span>Uniquement lettres, chiffres, tirets et underscore</span>
                            </p>
                        </div>

                        <div class="space-y-2">
                            <label for="phone" class="block text-sm font-medium text-gray-300">
                                <i class="fas fa-phone text-blue-primary mr-2"></i>Téléphone
                            </label>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                pattern="^(\+33|0)[1-9](\d{8})$"
                                class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                placeholder="0123456789"
                            >
                            <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                        </div>
                    </div>

                    <div id="step-2" class="step-content hidden space-y-6">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-r from-red-500 to-orange-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shield-alt text-white text-sm"></i>
                            </div>
                            <span>Sécurité</span>
                        </h3>

                        <div class="space-y-2">
                            <label for="password" class="block text-sm font-medium text-gray-300">
                                <i class="fas fa-lock text-blue-primary mr-2"></i>Mot de passe *
                            </label>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    id="password" 
                                    name="password" 
                                    required
                                    minlength="8"
                                    class="w-full px-4 py-3 pr-12 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                    placeholder="••••••••"
                                >
                                <button 
                                    type="button" 
                                    onclick="togglePassword('password')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-gray-300 transition-colors"
                                >
                                    <i id="password-eye" class="fas fa-eye text-lg"></i>
                                </button>
                            </div>
                            <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                        </div>

                        <div class="space-y-2">
                            <label for="confirm_password" class="block text-sm font-medium text-gray-300">
                                <i class="fas fa-check text-blue-primary mr-2"></i>Confirmer le mot de passe *
                            </label>
                            <div class="relative">
                                <input 
                                    type="password" 
                                    id="confirm_password" 
                                    name="confirm_password" 
                                    required
                                    minlength="8"
                                    class="w-full px-4 py-3 pr-12 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                    placeholder="••••••••"
                                >
                                <button 
                                    type="button" 
                                    onclick="togglePassword('confirm_password')"
                                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-gray-300 transition-colors"
                                >
                                    <i id="confirm_password-eye" class="fas fa-eye text-lg"></i>
                                </button>
                            </div>
                            <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                        </div>

                        <div id="password-strength" class="hidden space-y-2">
                            <div class="flex items-center space-x-3">
                                <span class="text-sm text-gray-400">Force :</span>
                                <div class="flex space-x-1">
                                    <div class="w-8 h-1.5 bg-gray-700 rounded" id="strength-1"></div>
                                    <div class="w-8 h-1.5 bg-gray-700 rounded" id="strength-2"></div>
                                    <div class="w-8 h-1.5 bg-gray-700 rounded" id="strength-3"></div>
                                    <div class="w-8 h-1.5 bg-gray-700 rounded" id="strength-4"></div>
                                </div>
                                <span id="strength-text" class="text-sm font-medium text-red-400">Faible</span>
                            </div>
                        </div>

                        <div class="bg-blue-primary/10 border border-blue-primary/20 rounded-xl p-4">
                            <h4 class="text-sm font-medium text-blue-primary mb-3">
                                <i class="fas fa-info-circle mr-2"></i>Exigences du mot de passe :
                            </h4>
                            <div class="grid grid-cols-1 gap-2 text-xs">
                                <div class="flex items-center space-x-2" id="req-length">
                                    <i class="fas fa-times text-red-400"></i>
                                    <span class="text-gray-400">Au moins 8 caractères</span>
                                </div>
                                <div class="flex items-center space-x-2" id="req-lowercase">
                                    <i class="fas fa-times text-red-400"></i>
                                    <span class="text-gray-400">Une lettre minuscule (a-z)</span>
                                </div>
                                <div class="flex items-center space-x-2" id="req-uppercase">
                                    <i class="fas fa-times text-red-400"></i>
                                    <span class="text-gray-400">Une lettre majuscule (A-Z)</span>
                                </div>
                                <div class="flex items-center space-x-2" id="req-number">
                                    <i class="fas fa-times text-red-400"></i>
                                    <span class="text-gray-400">Un chiffre (0-9)</span>
                                </div>
                                <div class="flex items-center space-x-2" id="req-special">
                                    <i class="fas fa-times text-red-400"></i>
                                    <span class="text-gray-400">Un caractère spécial (@$!%*?&)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="step-3" class="step-content hidden space-y-6">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-r from-green-500 to-teal-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-white text-sm"></i>
                            </div>
                            <span>Informations légales</span>
                        </h3>

                        <div class="bg-blue-primary/10 border border-blue-primary/20 rounded-xl p-4">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-info-circle text-blue-primary text-lg mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-blue-primary">Informations légales requises</h4>
                                    <p class="text-sm text-blue-300/80 mt-1">
                                        Conformément à la réglementation française, nous devons collecter votre adresse de facturation.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="space-y-2">
                                <label for="address_line1" class="block text-sm font-medium text-gray-300">
                                    Adresse *
                                </label>
                                <input 
                                    type="text" 
                                    id="address_line1" 
                                    name="address_line1" 
                                    required
                                    maxlength="255"
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                    placeholder="123 Rue de la Paix"
                                >
                                <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                            </div>

                            <div class="space-y-2">
                                <label for="address_line2" class="block text-sm font-medium text-gray-300">
                                    Complément d'adresse
                                </label>
                                <input 
                                    type="text" 
                                    id="address_line2" 
                                    name="address_line2" 
                                    maxlength="255"
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                    placeholder="Appartement, étage, etc."
                                >
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label for="city" class="block text-sm font-medium text-gray-300">
                                        Ville *
                                    </label>
                                    <input 
                                        type="text" 
                                        id="city" 
                                        name="city" 
                                        required
                                        maxlength="100"
                                        class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                        placeholder="Paris"
                                    >
                                    <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                                </div>

                                <div class="space-y-2">
                                    <label for="postal_code" class="block text-sm font-medium text-gray-300">
                                        Code postal *
                                    </label>
                                    <input 
                                        type="text" 
                                        id="postal_code" 
                                        name="postal_code" 
                                        required
                                        maxlength="20"
                                        pattern="[0-9]{5}"
                                        class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                        placeholder="75001"
                                    >
                                    <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <label for="country" class="block text-sm font-medium text-gray-300">
                                    Pays *
                                </label>
                                <select 
                                    id="country" 
                                    name="country"
                                    required
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white focus:outline-none focus:border-purple-500 focus:ring-2 focus:ring-purple-500/20 transition-all duration-300"
                                >
                                    <option value="">Sélectionner un pays</option>
                                    <option value="FR" selected>France</option>
                                    <option value="BE">Belgique</option>
                                    <option value="CH">Suisse</option>
                                    <option value="LU">Luxembourg</option>
                                    <option value="CA">Canada</option>
                                </select>
                                <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="bg-blue-primary/10 border border-blue-primary/20 rounded-xl p-4">
                                <label class="flex items-start space-x-3 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="certify_info" 
                                        id="certify_info"
                                        required
                                        class="w-4 h-4 text-blue-primary bg-dark-700 border border-dark-600 rounded focus:ring-blue-primary focus:ring-2 mt-1 flex-shrink-0"
                                    >
                                    <div class="text-sm">
                                        <span class="font-medium text-blue-primary">
                                            <i class="fas fa-certificate mr-2"></i>
                                            Je certifie que les informations fournies sont exactes et véridiques.
                                        </span>
                                        <p class="text-blue-300/80 mt-1">Toute déclaration inexacte pourra entraîner la fermeture de mon compte.</p>
                                    </div>
                                </label>
                            </div>

                            <div class="bg-dark-700/50 border border-dark-600 rounded-xl p-4">
                                <label class="flex items-start space-x-3 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="terms_accepted" 
                                        id="terms_accepted"
                                        required
                                        class="w-4 h-4 text-purple-500 bg-dark-700 border border-dark-600 rounded focus:ring-purple-500 focus:ring-2 mt-1 flex-shrink-0"
                                    >
                                    <span class="text-sm text-gray-300">
                                        <i class="fas fa-file-contract text-purple-500 mr-2"></i>
                                        J'accepte les 
                                        <a href="/terms" target="_blank" class="text-purple-400 hover:text-purple-300 transition-colors underline">conditions d'utilisation</a> 
                                        et la 
                                        <a href="/privacy" target="_blank" class="text-purple-400 hover:text-purple-300 transition-colors underline">politique de confidentialité</a>
                                    </span>
                                </label>
                            </div>

                            <div class="bg-dark-700/30 border border-dark-600 rounded-xl p-4">
                                <label class="flex items-start space-x-3 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="newsletter" 
                                        id="newsletter"
                                        checked
                                        class="w-4 h-4 text-green-500 bg-dark-700 border border-dark-600 rounded focus:ring-green-500 focus:ring-2 mt-1 flex-shrink-0"
                                    >
                                    <span class="text-sm text-gray-300">
                                        <i class="fas fa-envelope-open text-green-500 mr-2"></i>
                                        Je souhaite recevoir les actualités et offres par email
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="flex justify-between items-center pt-8 border-t border-dark-600">
                    <button 
                        type="button" 
                        id="prevBtn"
                        class="hidden px-6 py-3 bg-dark-700 text-white font-medium rounded-xl transition-all duration-300 hover:bg-dark-600 focus:outline-none focus:ring-2 focus:ring-gray-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        onclick="changeStep(-1)"
                    >
                        <i class="fas fa-arrow-left mr-2"></i>
                        Précédent
                    </button>

                    <button 
                        type="button" 
                        id="nextBtn"
                        class="ml-auto group px-8 py-3 bg-gradient-to-r from-purple-500 to-blue-primary text-white font-semibold rounded-xl transition-all duration-300 hover:from-purple-600 hover:to-blue-secondary hover:shadow-xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-purple-500 disabled:opacity-50 disabled:cursor-not-allowed"
                        onclick="changeStep(1)"
                    >
                        Suivant
                        <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </button>

                    <button
                        type="submit"
                        id="submitBtn"
                        form="registerForm"
                        class="hidden group ml-auto px-8 py-4 bg-gradient-to-r from-green-500 to-blue-primary text-white font-bold rounded-xl transition-all duration-300 hover:from-green-600 hover:to-blue-secondary hover:shadow-2xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-green-500 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        <span id="submitBtnText" class="flex items-center space-x-3">
                            <i class="fas fa-rocket group-hover:animate-pulse"></i>
                            <span>Créer mon compte</span>
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </span>
                        <div id="submitBtnSpinner" class="hidden flex items-center space-x-3">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Création en cours...</span>
                        </div>
                    </button>
                </div>

<?php if (CLOUDFLARE_TURNSTILE_ENABLED && CLOUDFLARE_TURNSTILE_SITE_KEY): ?>
                <div id="step-3-turnstile" class="hidden mt-4 flex justify-center">
                    <div class="cf-turnstile" data-sitekey="<?= htmlspecialchars(CLOUDFLARE_TURNSTILE_SITE_KEY, ENT_QUOTES, 'UTF-8') ?>" data-theme="dark"></div>
                </div>
                <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
<?php endif; ?>


                <div class="relative pt-6">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gradient-to-r from-transparent via-dark-600 to-transparent"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <div class="bg-gradient-to-r from-purple-500 to-blue-primary w-12 h-1 rounded-full"></div>
                    </div>
                </div>

                <div class="relative">
                    <button 
                        type="button" 
                        id="googleRegisterBtn"
                        onclick="registerWithGoogle()"
                        class="group w-full bg-white text-gray-900 font-medium px-6 py-4 rounded-xl transition-all duration-300 hover:shadow-xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-gray-300 flex items-center justify-center space-x-3"
                    >
                        <svg class="w-6 h-6" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        <span>S'inscrire avec Google</span>
                        <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </button>
                </div>

                <div class="text-center pt-6 border-t border-dark-600">
                    <p class="text-gray-400">
                        Déjà un compte ? 
                        <a href="/auth/login" class="text-purple-500 hover:text-purple-400 transition-colors font-medium">
                            <i class="fas fa-sign-in-alt mr-1"></i>Se connecter
                        </a>
                    </p>
                </div>
            </div>

            <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                <div class="inline-flex items-center space-x-3 bg-dark-800/30 backdrop-blur-sm border border-dark-600 rounded-2xl px-6 py-4">
                    <a href="/" class="inline-flex items-center space-x-2 text-gray-400 hover:text-white transition-colors">
                        <i class="fas fa-home"></i>
                        <span>Retour à l'accueil</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<?php require_once '../includes/footer.php'; ?>


<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>

<script>

function initAOS() {
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 800,
            once: true,
            offset: 50
        });
        console.log('AOS initialisé');
    } else {
        console.warn('AOS non disponible');
    }
}

let currentStep = 1;
const totalSteps = 3;
let isSubmitting = false;

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Initialisation de la page d\'inscription ===');

    setTimeout(initAOS, 100);

    updateUI();
    initializeEventListeners();

    adjustContentMargin();
});

function adjustContentMargin() {
    const header = document.querySelector('header');
    const mainContent = document.getElementById('main-content');

    if (header && mainContent) {
        mainContent.style.paddingTop = `${header.offsetHeight}px`;
    }
}

function initializeEventListeners() {

    const form = document.getElementById('registerForm');
    if (form) {
        form.addEventListener('submit', handleSubmit);
        console.log('Écouteur de formulaire ajouté');
    }

    const passwordField = document.getElementById('password');
    if (passwordField) {
        passwordField.addEventListener('input', function(e) {
            const password = e.target.value;
            if (password.length > 0) {
                document.getElementById('password-strength').classList.remove('hidden');
                updatePasswordStrength(password);
                updatePasswordRequirements(password);
            } else {
                document.getElementById('password-strength').classList.add('hidden');
            }
        });
    }

    ['first_name', 'last_name', 'email', 'username', 'password', 'confirm_password', 'address_line1', 'city', 'postal_code', 'country'].forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.addEventListener('blur', function() {
                validateField(this);
            });
        }
    });
}

function handleSubmit(e) {
    e.preventDefault();
    console.log('Soumission du formulaire interceptée');

    if (isSubmitting) {
        console.log('Soumission déjà en cours...');
        return;
    }

    if (currentStep !== totalSteps) {
        console.log('Pas à la dernière étape');
        return;
    }

    if (!validateCurrentStep()) {
        console.log('Validation échouée');
        return;
    }

    submitForm();
}

async function submitForm() {
    console.log('Début de la soumission du formulaire');
    isSubmitting = true;

    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('submitBtnText');
    const btnSpinner = document.getElementById('submitBtnSpinner');

    submitBtn.disabled = true;
    btnText.classList.add('hidden');
    btnSpinner.classList.remove('hidden');

    try {

        const formData = new FormData(document.getElementById('registerForm'));

        let turnstileToken = '';
        try {
            if (typeof turnstile !== 'undefined') {
                turnstileToken = turnstile.getResponse() || formData.get('cf-turnstile-response') || '';
            } else {
                turnstileToken = formData.get('cf-turnstile-response') || '';
            }
        } catch (e) { turnstileToken = ''; }

        const data = {
            first_name: formData.get('first_name'),
            last_name: formData.get('last_name'),
            email: formData.get('email'),
            username: formData.get('username'),
            phone: formData.get('phone') || '',
            password: formData.get('password'),
            confirm_password: formData.get('confirm_password'),
            address_line1: formData.get('address_line1'),
            address_line2: formData.get('address_line2') || '',
            city: formData.get('city'),
            postal_code: formData.get('postal_code'),
            country: formData.get('country'),
            certify_info: formData.get('certify_info') ? true : false,
            terms_accepted: formData.get('terms_accepted') ? true : false,
            newsletter: formData.get('newsletter') ? true : false,
            turnstile_token: turnstileToken
        };

        console.log('Données à envoyer:', data);

        const response = await fetch('/api/v1/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        console.log('Réponse reçue:', response.status);
        const result = await response.json();
        console.log('Résultat:', result);

        if (result.success) {
            showAlert('🎉 Compte créé avec succès ! Vérifiez votre email pour l\'activer.', 'success');

            if (typeof confetti !== 'undefined') {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 },
                    colors: ['#10B981', '#7B61FF', '#00D4FF']
                });
            }

            setTimeout(() => {
                window.location.href = '/auth/login?registered=true';
            }, 3000);

        } else {
            showAlert(result.error || 'Erreur lors de la création du compte', 'error');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion. Veuillez réessayer.', 'error');
    } finally {

        isSubmitting = false;
        submitBtn.disabled = false;
        btnText.classList.remove('hidden');
        btnSpinner.classList.add('hidden');
    }
}

function changeStep(direction) {
    console.log(`Changement d'étape: ${currentStep} -> ${currentStep + direction}`);

    if (direction === 1 && currentStep < totalSteps) {

        if (validateCurrentStep()) {
            currentStep++;
            updateUI();
        }
    } else if (direction === -1 && currentStep > 1) {
        currentStep--;
        updateUI();
    }
}

function updateUI() {
    console.log(`Mise à jour UI pour l'étape ${currentStep}`);

    document.querySelectorAll('.step-content').forEach(step => {
        step.classList.add('hidden');
    });

    const currentStepElement = document.getElementById(`step-${currentStep}`);
    if (currentStepElement) {
        currentStepElement.classList.remove('hidden');
    }

    const progressBar = document.getElementById('progress-bar');
    if (progressBar) {
        const progress = (currentStep / totalSteps) * 100;
        progressBar.style.width = progress + '%';
    }

    const stepCurrentElement = document.getElementById('step-current');
    if (stepCurrentElement) {
        stepCurrentElement.textContent = currentStep;
    }

    const stepTitles = {
        1: 'Informations personnelles',
        2: 'Sécurité',
        3: 'Informations légales'
    };

    const stepTitleElement = document.getElementById('step-title');
    if (stepTitleElement) {
        stepTitleElement.textContent = stepTitles[currentStep];
    }

    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const submitBtn = document.getElementById('submitBtn');

    if (prevBtn) {
        if (currentStep === 1) {
            prevBtn.classList.add('hidden');
        } else {
            prevBtn.classList.remove('hidden');
        }
    }

    if (nextBtn && submitBtn) {
        if (currentStep === totalSteps) {
            nextBtn.classList.add('hidden');
            submitBtn.classList.remove('hidden');
            const ts = document.getElementById('step-3-turnstile');
            if (ts) ts.classList.remove('hidden');
        } else {
            nextBtn.classList.remove('hidden');
            submitBtn.classList.add('hidden');
            const ts = document.getElementById('step-3-turnstile');
            if (ts) ts.classList.add('hidden');
        }
    }
}

function validateCurrentStep() {
    console.log(`Validation de l'étape ${currentStep}`);
    let isValid = true;

    if (currentStep === 1) {
        const requiredFields = ['first_name', 'last_name', 'email', 'username'];
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!validateField(field)) {
                isValid = false;
            }
        });

    } else if (currentStep === 2) {
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        if (!validateField(password)) {
            isValid = false;
        }

        if (!validateField(confirmPassword)) {
            isValid = false;
        }

        if (password.value !== confirmPassword.value) {
            showFieldError(confirmPassword, 'Les mots de passe ne correspondent pas');
            isValid = false;
        }

    } else if (currentStep === 3) {
        const requiredFields = ['address_line1', 'city', 'postal_code', 'country'];
        requiredFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (!validateField(field)) {
                isValid = false;
            }
        });

        const certifyInfo = document.getElementById('certify_info');
        const termsAccepted = document.getElementById('terms_accepted');

        if (!certifyInfo.checked) {
            showAlert('Vous devez certifier que les informations sont exactes', 'error');
            isValid = false;
        }

        if (!termsAccepted.checked) {
            showAlert('Vous devez accepter les conditions d\'utilisation', 'error');
            isValid = false;
        }
    }

    console.log(`Validation étape ${currentStep}: ${isValid}`);
    return isValid;
}

function validateField(field) {
    if (!field) return true;

    const value = field.value.trim();
    const fieldName = field.name;
    let isValid = true;
    let errorMessage = '';

    if (field.required && !value) {
        errorMessage = 'Ce champ est requis';
        isValid = false;
    } else if (fieldName === 'email' && value && !isValidEmail(value)) {
        errorMessage = 'Format d\'email invalide';
        isValid = false;
    } else if (fieldName === 'username' && value && !isValidUsername(value)) {
        errorMessage = 'Le nom d\'utilisateur doit contenir uniquement des lettres, chiffres, tirets et underscores';
        isValid = false;
    } else if (fieldName === 'password' && value && !isValidPassword(value)) {
        errorMessage = 'Le mot de passe ne respecte pas les exigences';
        isValid = false;
    } else if (fieldName === 'postal_code' && value && !/^[0-9]{5}$/.test(value)) {
        errorMessage = 'Le code postal doit contenir 5 chiffres';
        isValid = false;
    }

    if (isValid) {
        clearFieldError(field);
    } else {
        showFieldError(field, errorMessage);
    }

    return isValid;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidUsername(username) {
    return /^[a-zA-Z0-9\-_]+$/.test(username);
}

function isValidPassword(password) {
    return password.length >= 8 &&
           /[a-z]/.test(password) &&
           /[A-Z]/.test(password) &&
           /\d/.test(password) &&
           /[@$!%*?&]/.test(password);
}

function showFieldError(field, message) {
    field.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
    field.classList.remove('border-dark-600');

    const errorElement = field.parentNode.querySelector('.error-message');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    }
}

function clearFieldError(field) {
    field.classList.remove('border-red-500', 'ring-2', 'ring-red-500/20');
    field.classList.add('border-dark-600');

    const errorElement = field.parentNode.querySelector('.error-message');
    if (errorElement) {
        errorElement.classList.add('hidden');
    }
}

function updatePasswordStrength(password) {
    let strength = 0;
    const strengthBars = ['strength-1', 'strength-2', 'strength-3', 'strength-4'];
    const strengthText = document.getElementById('strength-text');

    if (password.length >= 8) strength++;
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/\d/.test(password)) strength++;
    if (/[@$!%*?&]/.test(password)) strength++;

    strengthBars.forEach(bar => {
        const element = document.getElementById(bar);
        if (element) {
            element.className = 'w-8 h-1.5 bg-gray-700 rounded';
        }
    });

    const colors = ['bg-red-500', 'bg-orange-500', 'bg-yellow-500', 'bg-green-500'];
    const texts = ['Très faible', 'Faible', 'Moyen', 'Fort', 'Très fort'];
    const textColors = ['text-red-400', 'text-orange-400', 'text-yellow-400', 'text-green-400', 'text-green-400'];

    for (let i = 0; i < Math.min(strength, 4); i++) {
        const element = document.getElementById(strengthBars[i]);
        if (element) {
            element.className = `w-8 h-1.5 ${colors[Math.min(i, 3)]} rounded`;
        }
    }

    if (strengthText) {
        strengthText.textContent = texts[Math.min(strength, 4)];
        strengthText.className = `text-sm font-medium ${textColors[Math.min(strength, 4)]}`;
    }
}

function updatePasswordRequirements(password) {
    const requirements = [
        { id: 'req-length', test: password.length >= 8 },
        { id: 'req-lowercase', test: /[a-z]/.test(password) },
        { id: 'req-uppercase', test: /[A-Z]/.test(password) },
        { id: 'req-number', test: /\d/.test(password) },
        { id: 'req-special', test: /[@$!%*?&]/.test(password) }
    ];

    requirements.forEach(req => {
        const element = document.getElementById(req.id);
        if (element) {
            const icon = element.querySelector('i');
            const text = element.querySelector('span');

            if (req.test) {
                icon.className = 'fas fa-check text-green-400';
                text.classList.add('text-green-400');
                text.classList.remove('text-gray-400');
            } else {
                icon.className = 'fas fa-times text-red-400';
                text.classList.add('text-gray-400');
                text.classList.remove('text-green-400');
            }
        }
    });
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const eye = document.getElementById(fieldId + '-eye');

    if (field && eye) {
        if (field.type === 'password') {
            field.type = 'text';
            eye.className = 'fas fa-eye-slash text-lg';
        } else {
            field.type = 'password';
            eye.className = 'fas fa-eye text-lg';  
        }
    }
}

function registerWithGoogle() {
    showAlert('Redirection vers Google...', 'info');
    window.location.href = '/api/v1/auth/google/login.php';
}

function showAlert(message, type) {
    console.log(`🔔 Alerte: ${type} - ${message}`);

    const container = document.getElementById('alert-container');
    const alert = document.getElementById('alert');

    if (!container || !alert) return;

    const classes = {
        success: 'bg-green-500/20 border-green-500/30 text-green-400 backdrop-blur-sm',
        error: 'bg-red-500/20 border-red-500/30 text-red-400 backdrop-blur-sm',
        info: 'bg-blue-primary/20 border-blue-primary/30 text-blue-primary backdrop-blur-sm',
        warning: 'bg-yellow-500/20 border-yellow-500/30 text-yellow-400 backdrop-blur-sm'
    };

    alert.className = 'p-4 rounded-xl text-sm font-medium border ' + (classes[type] || classes.info);
    alert.textContent = message;

    container.classList.remove('hidden');

    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    if (type !== 'success') {
        setTimeout(() => {
            container.classList.add('hidden');
        }, 8000);
    }
}

window.addEventListener('resize', adjustContentMargin);
setTimeout(adjustContentMargin, 100);
setTimeout(adjustContentMargin, 500);
</script>

<?php
endHTMLPage();
?>