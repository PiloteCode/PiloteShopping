<?php

try {
    define('API_ACCESS', true);
    require_once '../api/v1/config.php';
    require_once '../includes/import.php';
    
        if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
        if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login');
        exit();
    }
    
    $currentUserId = $_SESSION['user_id'];
    
        $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT id, username, email, first_name, last_name, phone, profile_completed
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$currentUserId]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser) {
        session_destroy();
        header('Location: /auth/login?error=' . urlencode('Session invalide'));
        exit();
    }
    
        if ($currentUser['profile_completed']) {
        header('Location: /dashboard');
        exit();
    }
    
        $stmt = $db->prepare("
        SELECT provider, provider_email, needs_completion
        FROM users_oauth 
        WHERE user_id = ? AND provider = 'google'
    ");
    $stmt->execute([$currentUserId]);
    $googleAccount = $stmt->fetch();
    
    $isGoogleAccount = (bool)$googleAccount;
    
} catch (Exception $e) {
    error_log("Complete profile page error: " . $e->getMessage());
    header('Location: /auth/login?error=' . urlencode('Erreur de chargement'));
    exit();
}

$seo = [
    'title' => 'Compléter votre profil - Pilote Shopping',
    'description' => 'Complétez votre profil pour finaliser votre inscription sur Pilote Shopping.',
    'keywords' => 'profil, complétion, informations, pilote shopping',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen">

<section class="min-h-screen relative overflow-hidden">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-blue-primary/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-purple-500/20 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-radial opacity-30"></div>
    </div>

    <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-12">
        <div class="w-full max-w-2xl space-y-8">

            <div class="text-center space-y-4" data-aos="fade-up">

                <div class="inline-flex items-center space-x-2 bg-gradient-to-r from-blue-primary/20 to-purple-500/20 rounded-full px-6 py-2 mb-6 border border-blue-primary/30">
                    <i class="fas fa-user-edit text-blue-primary"></i>
                    <span class="text-white font-medium">
<?= $isGoogleAccount ? 'Finalisez votre inscription' : 'Complétez votre profil' ?>
                    </span>
                </div>

                <h1 class="text-4xl md:text-5xl font-bold text-white mb-6 leading-tight">
                    <span class="block">
<?= $isGoogleAccount ? 'Bienvenue !' : 'Dernière étape' ?>
                    </span>
                    <span class="block bg-gradient-to-r from-blue-primary to-purple-500 bg-clip-text text-transparent">
                        Complétez votre profil
                    </span>
                </h1>

                <p class="text-xl text-gray-300 mb-8 max-w-3xl mx-auto leading-relaxed">
<?php if ($isGoogleAccount): ?>
                        Pour finaliser votre inscription avec Google, nous avons besoin de quelques informations supplémentaires pour la facturation.
<?php else: ?>
                        Ajoutez vos informations de facturation pour accéder à toutes les fonctionnalités de Pilote Shopping.
<?php endif; ?>
                </p>

<?php if ($isGoogleAccount): ?>
                    <div class="bg-blue-primary/10 border border-blue-primary/20 rounded-2xl p-6 backdrop-blur-sm">
                        <div class="flex items-center space-x-3">
                            <div class="w-12 h-12 bg-blue-primary/20 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-blue-primary" viewBox="0 0 24 24">
                                    <path fill="currentColor" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="currentColor" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="currentColor" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                    <path fill="currentColor" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                                </svg>
                            </div>
                            <div class="text-left">
                                <p class="text-blue-primary font-medium">Connecté via Google</p>
                                <p class="text-blue-300 text-sm"><?= htmlspecialchars($currentUser['email']) ?></p>
                            </div>
                        </div>
                    </div>
<?php endif; ?>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-lg rounded-3xl border border-dark-600 p-8 lg:p-12 space-y-8 shadow-2xl" data-aos="fade-up" data-aos-delay="200">

                <div id="alert-container" class="hidden">
                    <div id="alert" class="p-4 rounded-xl text-sm font-medium border backdrop-blur-sm"></div>
                </div>

                <div class="bg-dark-700/50 backdrop-blur-sm rounded-2xl p-6 border border-dark-600">
                    <div class="flex items-center justify-between text-sm text-gray-300 mb-4">
                        <span class="flex items-center space-x-2">
                            <i class="fas fa-chart-line text-blue-primary"></i>
                            <span>Progression du profil</span>
                        </span>
                        <span id="progress-text" class="font-bold text-blue-primary">0%</span>
                    </div>
                    <div class="w-full bg-dark-800 rounded-full h-3 overflow-hidden">
                        <div id="progress-bar" class="bg-gradient-to-r from-blue-primary to-purple-500 h-3 rounded-full transition-all duration-500 shadow-lg" style="width: 0%"></div>
                    </div>
                </div>

                <form 
                    id="completeProfileForm" 
                    method="POST" 
                    action="/api/v1/auth/complete-profile" 
                    class="space-y-8"
                    onsubmit="return false;"
                >

                    <div class="space-y-6">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-r from-blue-primary to-purple-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-user text-white text-sm"></i>
                            </div>
                            <span>Informations personnelles</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-300">
                                    <i class="fas fa-user text-blue-primary mr-2"></i>Prénom
                                </label>
                                <input 
                                    type="text" 
                                    id="first_name"
                                    name="first_name" 
                                    value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>"
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300 <?= $isGoogleAccount ? 'opacity-60 cursor-not-allowed' : '' ?>"
<?= $isGoogleAccount ? 'readonly' : 'required' ?>
                                >
                            </div>

                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-gray-300">
                                    <i class="fas fa-user text-blue-primary mr-2"></i>Nom
                                </label>
                                <input 
                                    type="text" 
                                    id="last_name"
                                    name="last_name" 
                                    value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>"
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300 <?= $isGoogleAccount ? 'opacity-60 cursor-not-allowed' : '' ?>"
<?= $isGoogleAccount ? 'readonly' : 'required' ?>
                                >
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block text-sm font-medium text-gray-300">
                                <i class="fas fa-envelope text-blue-primary mr-2"></i>Email
                            </label>
                            <div class="relative">
                                <input 
                                    type="email" 
                                    value="<?= htmlspecialchars($currentUser['email']) ?>"
                                    class="w-full px-4 py-3 bg-dark-700/50 border border-dark-600 rounded-xl text-white opacity-60 cursor-not-allowed"
                                    readonly
                                >
                                <div class="absolute inset-y-0 right-0 flex items-center pr-4">
                                    <div class="flex items-center space-x-2 text-green-400">
                                        <i class="fas fa-check-circle text-sm"></i>
                                        <span class="text-xs font-medium">Vérifié</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label for="phone" class="block text-sm font-medium text-gray-300">
                                <i class="fas fa-phone text-blue-primary mr-2"></i>Téléphone *
                            </label>
                            <input 
                                type="tel" 
                                id="phone" 
                                name="phone" 
                                value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>"
                                required
                                class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300"
                                placeholder="0123456789"
                            >
                            <p class="text-xs text-gray-400 flex items-center space-x-1">
                                <i class="fas fa-info-circle text-blue-primary"></i>
                                <span>Format: 0123456789 ou +33123456789</span>
                            </p>
                            <div class="error-message hidden text-red-400 text-xs mt-1"></div>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="absolute inset-0 flex items-center">
                            <div class="w-full border-t border-gradient-to-r from-transparent via-dark-600 to-transparent"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <div class="bg-gradient-to-r from-blue-primary to-purple-500 w-12 h-1 rounded-full"></div>
                        </div>
                    </div>

                    <div class="space-y-6">
                        <h3 class="text-xl font-bold text-white flex items-center space-x-3">
                            <div class="w-8 h-8 bg-gradient-to-r from-purple-500 to-pink-500 rounded-lg flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-white text-sm"></i>
                            </div>
                            <span>Adresse de facturation</span>
                        </h3>

                        <div class="bg-blue-primary/10 border border-blue-primary/20 rounded-xl p-4">
                            <div class="flex items-start space-x-3">
                                <i class="fas fa-info-circle text-blue-primary text-lg mt-0.5"></i>
                                <div>
                                    <h4 class="text-sm font-medium text-blue-primary">Informations requises</h4>
                                    <p class="text-sm text-blue-300/80 mt-1">
                                        Ces informations sont nécessaires pour la facturation et conformes à la réglementation française.
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
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300"
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
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300"
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
                                        class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300"
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
                                        class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300"
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
                                    class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300"
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
                    </div>

                    <div class="pt-8">
                        <button 
                            type="submit" 
                            id="submitBtn"
                            class="group w-full py-4 px-8 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white font-bold rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-2xl focus:outline-none focus:ring-2 focus:ring-blue-primary focus:ring-offset-2 focus:ring-offset-dark-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                        >
                            <span id="submitBtnText" class="flex items-center justify-center space-x-3">
                                <i class="fas fa-rocket group-hover:animate-pulse"></i>
                                <span>Finaliser mon profil</span>
                                <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                            </span>
                            <div id="submitBtnSpinner" class="hidden flex items-center justify-center space-x-3">
                                <i class="fas fa-spinner fa-spin"></i>
                                <span>Finalisation en cours...</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>

            <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                <div class="inline-flex items-center space-x-3 bg-dark-800/30 backdrop-blur-sm border border-dark-600 rounded-2xl px-6 py-4">
                    <div class="w-8 h-8 bg-green-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-shield-alt text-green-400"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-medium text-white">Données sécurisées</p>
                        <p class="text-xs text-gray-400">Protégées selon notre politique de confidentialité</p>
                    </div>
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

let progressFields = ['phone', 'address_line1', 'city', 'postal_code', 'country'];
let totalFields = progressFields.length;

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Initialisation du formulaire ===');

    setTimeout(initAOS, 100);

    initializeFormHandling();
    initializeValidation();
    setupAddressAutocomplete();

    setTimeout(() => {
        updateProgress();
        console.log('Progression initiale calculée');
    }, 200);

    adjustContentMargin();
});

function adjustContentMargin() {
    const header = document.querySelector('header');
    const mainContent = document.getElementById('main-content');

    if (header && mainContent) {
        mainContent.style.paddingTop = `${header.offsetHeight}px`;
    }
}

function initializeFormHandling() {
    const form = document.getElementById('completeProfileForm');

    if (!form) {
        console.error('❌ Formulaire non trouvé !');
        return;
    }

    console.log('✅ Formulaire trouvé, ajout des event listeners...');

    form.addEventListener('submit', function(e) {
        console.log('📨 Event submit du formulaire déclenché');
        e.preventDefault();
        e.stopPropagation();
        handleFormSubmission();
    });

    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        console.log('✅ Bouton submit trouvé');
        submitBtn.addEventListener('click', function(e) {
            console.log('🖱️ Click sur le bouton submit');
            e.preventDefault();
            e.stopPropagation();
            handleFormSubmission();
        });
    } else {
        console.error('❌ Bouton submit non trouvé !');
    }
}

async function handleFormSubmission() {
    console.log('🚀 === Début de la soumission du formulaire ===');

    let isValid = true;
    let errors = [];

    progressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            if (!validateField(field)) {
                isValid = false;
                errors.push(`${field.name || fieldId}: ${field.validationMessage || 'erreur'}`);
            }
        } else {
            console.warn(`⚠️ Champ ${fieldId} non trouvé`);
        }
    });

    if (!isValid) {
        console.log('❌ Erreurs de validation:', errors);
        showAlert('Veuillez corriger les erreurs dans le formulaire', 'error');
        return;
    }

    console.log('✅ Validation réussie, envoi des données...');

    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('submitBtnText');
    const btnSpinner = document.getElementById('submitBtnSpinner');

    if (!btn || !btnText || !btnSpinner) {
        console.error('❌ Éléments du bouton non trouvés');
        return;
    }

    btn.disabled = true;
    btnText.classList.add('hidden');
    btnSpinner.classList.remove('hidden');

    try {

        const phone = document.getElementById('phone').value.trim();
        const address_line1 = document.getElementById('address_line1').value.trim();
        const address_line2 = document.getElementById('address_line2').value.trim();
        const city = document.getElementById('city').value.trim();
        const postal_code = document.getElementById('postal_code').value.trim();
        const country = document.getElementById('country').value.trim();
        const first_name = document.getElementById('first_name').value.trim();
        const last_name = document.getElementById('last_name').value.trim();

        const data = {
            first_name: first_name,
            last_name: last_name,
            phone: phone,
            address_line1: address_line1,
            address_line2: address_line2,
            city: city,
            postal_code: postal_code,
            country: country
        };

        console.log('📦 Données à envoyer:', data);

        if (!phone || !address_line1 || !city || !postal_code || !country) {
            throw new Error('Tous les champs requis doivent être remplis');
        }

        console.log('📡 Envoi de la requête POST...');
        const response = await fetch('/api/v1/auth/complete-profile', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });

        console.log(`📥 Réponse reçue, status: ${response.status}`);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ Erreur serveur:', errorText);
            throw new Error(`HTTP ${response.status}: ${errorText}`);
        }

        const result = await response.json();
        console.log('✅ Réponse du serveur:', result);

        if (result.success) {
            console.log('🎉 Succès !');
            showAlert('🎉 Profil complété avec succès !', 'success');

            if (typeof confetti !== 'undefined') {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 },
                    colors: ['#10B981', '#3B82F6', '#06B6D4']
                });
            }

            setTimeout(() => {
                console.log('🔄 Redirection vers dashboard...');
                window.location.href = '/dashboard?profile_completed=true';
            }, 2000);

        } else {
            console.error('❌ Erreur métier:', result.error);
            showAlert(result.error || 'Erreur lors de la complétion du profil', 'error');
        }

    } catch (error) {
        console.error('💥 Erreur de requête:', error);
        showAlert('Erreur de connexion: ' + error.message, 'error');
    } finally {

        btn.disabled = false;
        btnText.classList.remove('hidden');
        btnSpinner.classList.add('hidden');
        console.log('🔄 Bouton réactivé');
    }
}

function updateProgress() {
    let filledFields = 0;

    progressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field && field.value.trim() !== '' && validateField(field)) {
            filledFields++;
        }
    });

    const percentage = Math.round((filledFields / totalFields) * 100);

    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');

    if (progressBar) {
        progressBar.style.width = percentage + '%';
        progressBar.style.transition = 'width 0.5s ease-in-out';
    }
    if (progressText) {
        progressText.textContent = percentage + '%';
        if (percentage === 100) {
            progressText.classList.add('text-green-400');
            progressText.classList.remove('text-blue-primary');
        } else {
            progressText.classList.add('text-blue-primary');
            progressText.classList.remove('text-green-400');
        }
    }

    console.log(`📊 Progression: ${filledFields}/${totalFields} = ${percentage}%`);
}

function initializeValidation() {
    console.log('🔍 Initialisation de la validation...');

    progressFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            console.log(`✅ Event listeners attachés pour: ${fieldId}`);

            field.addEventListener('input', function() {
                setTimeout(() => {
                    validateField(this);
                    updateProgress();
                }, 50);
            });

            field.addEventListener('blur', function() {
                validateField(this);
                updateProgress();
            });

            field.addEventListener('change', function() {
                setTimeout(() => {
                    validateField(this);
                    updateProgress();
                }, 50);
            });
        } else {
            console.warn(`⚠️ Champ non trouvé: ${fieldId}`);
        }
    });
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
    } else if (fieldName === 'phone' && value) {

        const phoneRegex = /^(\+33[1-9]\d{8}|0[1-9]\d{8})$/;
        if (!phoneRegex.test(value.replace(/[\s.-]/g, ''))) {
            errorMessage = 'Format invalide. Utilisez: 0123456789 ou +33123456789';
            isValid = false;
        }
    } else if (fieldName === 'postal_code' && value && !/^\d{5}$/.test(value)) {
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

function setupAddressAutocomplete() {
    const cityField = document.getElementById('city');
    const postalField = document.getElementById('postal_code');

    if (postalField) {
        postalField.addEventListener('input', function() {
            const postal = this.value.trim();
            if (postal.length === 5 && /^\d{5}$/.test(postal)) {
                const suggestions = {
                    '75001': 'Paris', '75002': 'Paris', '75003': 'Paris', '75004': 'Paris',
                    '69001': 'Lyon', '69002': 'Lyon', '69003': 'Lyon',
                    '13001': 'Marseille', '13002': 'Marseille', '13003': 'Marseille',
                    '31000': 'Toulouse', '31100': 'Toulouse', '31200': 'Toulouse',
                    '06000': 'Nice', '06100': 'Nice', '06200': 'Nice',
                    '44000': 'Nantes', '44100': 'Nantes', '44200': 'Nantes',
                    '67000': 'Strasbourg', '67100': 'Strasbourg',
                    '59000': 'Lille', '59100': 'Roubaix', '59200': 'Tourcoing'
                };

                if (suggestions[postal] && cityField && !cityField.value.trim()) {
                    cityField.value = suggestions[postal];
                    console.log(`🏙️ Auto-complétion: ${postal} -> ${suggestions[postal]}`);
                    setTimeout(() => {
                        updateProgress();
                    }, 100);
                }
            }
        });
    }
}

function showFieldError(field, message) {
    if (!field) return;

    field.classList.add('border-red-500', 'ring-2', 'ring-red-500/20');
    field.classList.remove('border-dark-600');

    const parent = field.parentNode;
    if (parent) {
        const errorElement = parent.querySelector('.error-message');
        if (errorElement) {
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
            errorElement.classList.add('animate-pulse');
        }
    }
}

function clearFieldError(field) {
    if (!field) return;

    field.classList.remove('border-red-500', 'ring-2', 'ring-red-500/20');
    field.classList.add('border-dark-600');

    const parent = field.parentNode;
    if (parent) {
        const errorElement = parent.querySelector('.error-message');
        if (errorElement) {
            errorElement.classList.add('hidden');
            errorElement.classList.remove('animate-pulse');
        }
    }
}

function showAlert(message, type) {
    console.log(`🔔 Alerte: ${type} - ${message}`);

    const container = document.getElementById('alert-container');
    const alert = document.getElementById('alert');

    if (!container || !alert) {
        console.error('❌ Éléments d\'alerte non trouvés');
        return;
    }

    const classes = {
        success: 'bg-green-500/20 border-green-500/30 text-green-400 backdrop-blur-sm',
        error: 'bg-red-500/20 border-red-500/30 text-red-400 backdrop-blur-sm',
        info: 'bg-blue-primary/20 border-blue-primary/30 text-blue-primary backdrop-blur-sm'
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

window.addEventListener('load', function() {
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        console.log('✅ Bouton submit disponible après chargement');
    } else {
        console.error('❌ Bouton submit toujours non trouvé après chargement');
    }
});
</script>

<?php
endHTMLPage();
?>