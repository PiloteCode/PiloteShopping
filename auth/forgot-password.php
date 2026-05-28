<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
if ($auth->isAuthenticated()) {
    header('Location: /dashboard');
    exit();
}

$seo = [
    'title' => 'Mot de passe oublié - Pilote Shopping',
    'description' => 'Réinitialisez votre mot de passe Pilote Shopping en toute sécurité.',
    'keywords' => 'mot de passe oublié, réinitialisation, pilote shopping, récupération compte',
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
        <div class="w-full max-w-lg space-y-8">

            <div class="text-center space-y-4" data-aos="fade-up">

                <div class="inline-flex items-center space-x-2 bg-gradient-to-r from-orange-500/20 to-red-500/20 rounded-full px-6 py-2 mb-6 border border-orange-500/30">
                    <i class="fas fa-key text-orange-500"></i>
                    <span class="text-white font-medium">Récupération sécurisée</span>
                </div>

                <h1 class="text-4xl md:text-5xl font-bold text-white mb-6 leading-tight">
                    <span class="block">Mot de passe</span>
                    <span class="block bg-gradient-to-r from-orange-500 to-red-500 bg-clip-text text-transparent">
                        oublié ?
                    </span>
                </h1>

                <p class="text-xl text-gray-300 mb-8 max-w-3xl mx-auto leading-relaxed">
                    Pas de souci ! Saisissez votre adresse email et nous vous enverrons un lien pour réinitialiser votre mot de passe.
                </p>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-lg rounded-3xl border border-dark-600 p-8 lg:p-12 space-y-8 shadow-2xl" data-aos="fade-up" data-aos-delay="200">

                <div id="alert-container" class="hidden">
                    <div id="alert" class="p-4 rounded-xl text-sm font-medium border backdrop-blur-sm"></div>
                </div>

                <form id="forgotPasswordForm" class="space-y-8">

                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-envelope text-orange-500 mr-2"></i>Adresse email
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            autocomplete="email"
                            class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-500/20 transition-all duration-300"
                            placeholder="votre@email.com"
                        >
                        <p class="text-xs text-gray-400 flex items-center space-x-1">
                            <i class="fas fa-info-circle text-orange-500"></i>
                            <span>Nous vous enverrons un lien de réinitialisation sur cette adresse</span>
                        </p>
                    </div>

                    <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-4 backdrop-blur-sm">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-shield-alt text-orange-500 text-lg mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-medium text-orange-500">Processus sécurisé</h4>
                                <p class="text-sm text-orange-300/80 mt-1">
                                    Le lien de réinitialisation sera valide pendant 1 heure seulement et ne peut être utilisé qu'une seule fois.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center">
                        <div class="cf-turnstile" data-sitekey="<?= CLOUDFLARE_TURNSTILE_SITE_KEY ?>" data-theme="dark" data-callback="onCaptchaSuccess"></div>
                    </div>

                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="group w-full py-4 px-8 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-bold rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-2xl focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2 focus:ring-offset-dark-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        <span id="submitBtnText" class="flex items-center justify-center space-x-3">
                            <i class="fas fa-paper-plane group-hover:animate-pulse"></i>
                            <span>Envoyer le lien de réinitialisation</span>
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </span>
                        <div id="submitBtnSpinner" class="hidden flex items-center justify-center space-x-3">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Envoi en cours...</span>
                        </div>
                    </button>
                </form>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gradient-to-r from-transparent via-dark-600 to-transparent"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <div class="bg-gradient-to-r from-orange-500 to-red-500 w-12 h-1 rounded-full"></div>
                    </div>
                </div>

                <div class="text-center space-y-4">
                    <p class="text-gray-400">
                        Vous vous souvenez de votre mot de passe ? 
                        <a href="/auth/login" class="text-orange-500 hover:text-orange-400 transition-colors font-medium">
                            <i class="fas fa-sign-in-alt mr-1"></i>Se connecter
                        </a>
                    </p>

                    <p class="text-gray-400">
                        Pas encore de compte ? 
                        <a href="/auth/register" class="text-blue-primary hover:text-blue-secondary transition-colors font-medium">
                            <i class="fas fa-user-plus mr-1"></i>Créer un compte
                        </a>
                    </p>
                </div>
            </div>

            <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                <div class="inline-flex items-center space-x-3 bg-dark-800/30 backdrop-blur-sm border border-dark-600 rounded-2xl px-6 py-4">
                    <div class="w-8 h-8 bg-green-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-shield-alt text-green-400"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-medium text-white">Protection anti-spam</p>
                        <p class="text-xs text-gray-400">Limité à 5 demandes par heure</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

</main>

<?php require_once '../includes/footer.php'; ?>


<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

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

let captchaValidated = false;

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Initialisation de la page mot de passe oublié ===');

    setTimeout(initAOS, 100);

    adjustContentMargin();

    checkUrlParameters();
});

function onCaptchaSuccess(token) {
    console.log('Captcha validé avec succès');
    captchaValidated = true;
}

function adjustContentMargin() {
    const header = document.querySelector('header');
    const mainContent = document.getElementById('main-content');

    if (header && mainContent) {
        mainContent.style.paddingTop = `${header.offsetHeight}px`;
    }
}

document.getElementById('forgotPasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('submitBtnText');
    const btnSpinner = document.getElementById('submitBtnSpinner');

    if (!captchaValidated) {
        showAlert('Veuillez valider le captcha de sécurité.', 'error');
        return;
    }

    btn.disabled = true;
    btnText.classList.add('hidden');
    btnSpinner.classList.remove('hidden');

    try {

        const formData = new FormData(this);
        let turnstileResponse = '';

        try {
            if (typeof turnstile !== 'undefined') {
                turnstileResponse = turnstile.getResponse() || '';
            }
        } catch (e) {
            console.log('Turnstile not available:', e);
        }

        const data = {
            email: formData.get('email'),
            turnstile_token: turnstileResponse
        };

        console.log('Sending forgot password request...');

        const response = await fetch('/api/v1/auth/forgot-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        console.log('Forgot password response:', result);

        if (result.success) {
            showAlert('📧 Email de réinitialisation envoyé ! Vérifiez votre boîte mail (et vos spams).', 'success');

            setTimeout(() => {
                showSuccessState(data.email);
            }, 2000);

        } else {
            showAlert(result.error || 'Erreur lors de l\'envoi', 'error');

            try {
                if (typeof turnstile !== 'undefined') {
                    turnstile.reset();
                    captchaValidated = false;
                }
            } catch (e) {
                console.log('Turnstile reset failed:', e);
            }
        }

    } catch (error) {
        console.error('Erreur:', error);
        showAlert('Erreur de connexion. Veuillez réessayer.', 'error');

        try {
            if (typeof turnstile !== 'undefined') {
                turnstile.reset();
                captchaValidated = false;
            }
        } catch (e) {
            console.log('Turnstile reset failed:', e);
        }
    } finally {

        btn.disabled = false;
        btnText.classList.remove('hidden');
        btnSpinner.classList.add('hidden');
    }
});

function showSuccessState(email) {
    const form = document.getElementById('forgotPasswordForm');
    const container = form.parentNode;

    container.innerHTML = `
        <div class="text-center space-y-6">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-green-500 to-blue-500 rounded-full mb-6">
                <i class="fas fa-check text-white text-2xl"></i>
            </div>

            <h2 class="text-2xl font-bold text-white">Email envoyé !</h2>

            <div class="bg-green-500/10 border border-green-500/20 rounded-xl p-6 backdrop-blur-sm text-left">
                <div class="flex items-start space-x-3">
                    <i class="fas fa-envelope text-green-500 text-lg mt-0.5"></i>
                    <div>
                        <h4 class="text-sm font-medium text-green-500 mb-2">Instructions envoyées</h4>
                        <p class="text-sm text-green-300/80 mb-3">
                            Un email contenant les instructions de réinitialisation a été envoyé à :
                        </p>
                        <p class="text-sm font-mono text-white bg-dark-700 px-3 py-2 rounded">
                            ${email}
                        </p>
                        <p class="text-xs text-green-300/60 mt-3">
                            N'oubliez pas de vérifier votre dossier spam/courrier indésirable.
                        </p>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <a href="/auth/login" 
                   class="block w-full py-3 px-6 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-semibold rounded-xl transition-all duration-300 hover:scale-105">
                    <i class="fas fa-sign-in-alt mr-2"></i>Retour à la connexion
                </a>

                <button onclick="location.reload()" 
                        class="block w-full py-3 px-6 bg-dark-700 text-white font-semibold rounded-xl transition-all duration-300 hover:bg-dark-600">
                    <i class="fas fa-redo mr-2"></i>Renvoyer un email
                </button>
            </div>
        </div>
    `;
}

function checkUrlParameters() {
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.get('email')) {
        const email = decodeURIComponent(urlParams.get('email'));
        document.getElementById('email').value = email;
    }

    if (urlParams.get('expired') === 'true') {
        showAlert('Le lien de réinitialisation a expiré. Veuillez en demander un nouveau.', 'error');
    }

    if (urlParams.get('invalid') === 'true') {
        showAlert('Le lien de réinitialisation est invalide. Veuillez en demander un nouveau.', 'error');
    }

    if (urlParams.get('used') === 'true') {
        showAlert('Ce lien de réinitialisation a déjà été utilisé. Veuillez en demander un nouveau.', 'error');
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
        info: 'bg-orange-500/20 border-orange-500/30 text-orange-500 backdrop-blur-sm'
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