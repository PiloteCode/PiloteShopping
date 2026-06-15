<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
if ($auth->isAuthenticated()) {
    $currentUser = $auth->getCurrentUser();
    
        if (!$currentUser['profile_completed']) {
        header('Location: /auth/complete-profile');
        exit();
    }
    
    header('Location: /dashboard');
    exit();
}

$seo = [
    'title' => 'Connexion - Pilote Shopping',
    'description' => 'Connectez-vous à votre compte Pilote Shopping pour accéder à vos commandes et profiter d\'une expérience d\'achat personnalisée.',
    'keywords' => 'connexion, login, compte, pilote shopping, authentification',
    'og_type' => 'website'
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

                <div class="inline-flex items-center space-x-2 bg-gradient-to-r from-blue-primary/20 to-purple-500/20 rounded-full px-6 py-2 mb-6 border border-blue-primary/30">
                    <i class="fas fa-sign-in-alt text-blue-primary"></i>
                    <span class="text-white font-medium">Connexion sécurisée</span>
                </div>

                <h1 class="text-4xl md:text-5xl font-bold text-white mb-6 leading-tight">
                    <span class="block">Bon retour !</span>
                    <span class="block bg-gradient-to-r from-blue-primary to-purple-500 bg-clip-text text-transparent">
                        Connectez-vous
                    </span>
                </h1>

                <p class="text-xl text-gray-300 mb-8 max-w-3xl mx-auto leading-relaxed">
                    Accédez à votre compte Pilote Shopping et profitez d'une expérience d'achat premium.
                </p>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-lg rounded-3xl border border-dark-600 p-8 lg:p-12 space-y-8 shadow-2xl" data-aos="fade-up" data-aos-delay="200">

                <div id="alert-container" class="hidden">
                    <div id="alert" class="p-4 rounded-xl text-sm font-medium border backdrop-blur-sm"></div>
                </div>

                <form id="loginForm" class="space-y-8">

                    <div class="space-y-2">
                        <label for="email" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-envelope text-blue-primary mr-2"></i>Adresse email
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            required
                            autocomplete="email"
                            class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300"
                            placeholder="votre@email.com"
                        >
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-lock text-blue-primary mr-2"></i>Mot de passe
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                autocomplete="current-password"
                                class="w-full px-4 py-3 pr-12 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300"
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
                    </div>

                    <div id="twofa-container" class="hidden space-y-2">
                        <label for="totp_code" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-shield-alt text-blue-primary mr-2"></i>Code d'authentification
                        </label>
                        <input 
                            type="text" 
                            id="totp_code" 
                            name="totp_code" 
                            maxlength="6"
                            pattern="[0-9]{6}"
                            class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-2 focus:ring-blue-primary/20 transition-all duration-300 text-center tracking-widest font-mono text-xl"
                            placeholder="123456"
                        >
                        <p class="text-xs text-gray-400 flex items-center space-x-1">
                            <i class="fas fa-info-circle text-blue-primary"></i>
                            <span>Entrez le code de votre application d'authentification</span>
                        </p>
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center group cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="remember_me" 
                                id="remember_me"
                                class="w-4 h-4 text-blue-primary bg-dark-700 border border-dark-600 rounded focus:ring-blue-primary focus:ring-2 transition-all duration-200"
                            >
                            <span class="ml-2 text-sm text-gray-300 group-hover:text-white transition-colors">
                                <i class="fas fa-clock text-blue-primary mr-1"></i>
                                Se souvenir de moi
                            </span>
                        </label>
                        <a href="/auth/forgot-password" class="text-sm text-blue-primary hover:text-blue-secondary transition-colors font-medium">
                            Mot de passe oublié ?
                        </a>
                    </div>

                    <div id="remember-me-info" class="hidden bg-blue-primary/10 border border-blue-primary/20 rounded-xl p-4 backdrop-blur-sm">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-info-circle text-blue-primary text-lg mt-0.5"></i>
                            <div>
                                <p class="text-sm font-medium text-blue-primary">Connexion automatique activée</p>
                                <p class="text-sm text-blue-300/80 mt-1">Vous resterez connecté sur cet appareil pendant 30 jours</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center">
                        <div class="cf-turnstile" data-sitekey="<?= CLOUDFLARE_TURNSTILE_SITE_KEY ?>" data-theme="dark" data-callback="onCaptchaSuccess"></div>
                    </div>

                    <button 
                        type="submit" 
                        id="loginBtn"
                        class="group w-full py-4 px-8 bg-gradient-to-r from-blue-primary to-purple-500 hover:from-blue-secondary hover:to-purple-600 text-white font-bold rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-2xl focus:outline-none focus:ring-2 focus:ring-blue-primary focus:ring-offset-2 focus:ring-offset-dark-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        <span id="loginBtnText" class="flex items-center justify-center space-x-3">
                            <i class="fas fa-sign-in-alt group-hover:animate-pulse"></i>
                            <span>Se connecter</span>
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </span>
                        <div id="loginBtnSpinner" class="hidden flex items-center justify-center space-x-3">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Connexion en cours...</span>
                        </div>
                    </button>
                </form>

                <div class="relative">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-gradient-to-r from-transparent via-dark-600 to-transparent"></div>
                    </div>
                    <div class="relative flex justify-center">
                        <div class="bg-gradient-to-r from-blue-primary to-purple-500 w-12 h-1 rounded-full"></div>
                    </div>
                </div>

                <div class="relative">
                    <button 
                        type="button" 
                        id="googleLoginBtn"
                        onclick="handleGoogleLogin()"
                        class="group w-full bg-white text-gray-900 font-medium px-6 py-4 rounded-xl transition-all duration-300 hover:shadow-xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-gray-300 flex items-center justify-center space-x-3 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none disabled:hover:scale-100"
                        disabled
                    >
                        <svg class="w-6 h-6" viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        <span>Continuer avec Google</span>
                        <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                    </button>

                    <div id="captcha-popover" class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-80 bg-dark-700 border border-dark-600 rounded-xl p-4 shadow-2xl backdrop-blur-sm hidden">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-shield-alt text-blue-primary text-lg mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-medium text-white">Validation captcha requise</h4>
                                <p class="text-sm text-gray-300 mt-1">
                                    Veuillez valider le captcha Cloudflare ci-dessus pour activer la connexion avec Google.
                                </p>
                            </div>
                        </div>

                        <div class="absolute top-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-dark-600"></div>
                    </div>
                </div>

                <div class="text-center pt-6 border-t border-dark-600">
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

let requires2FA = false;
let currentUserId = null;
let captchaValidated = false;

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Initialisation de la page de connexion ===');

    setTimeout(initAOS, 100);

    adjustContentMargin();

    checkUrlParameters();

    initPopoverListeners();
});

function initPopoverListeners() {
    const googleBtn = document.getElementById('googleLoginBtn');
    const popover = document.getElementById('captcha-popover');

    if (googleBtn && popover) {

        googleBtn.addEventListener('mouseenter', function() {
            if (!captchaValidated) {
                popover.classList.remove('hidden');
            }
        });

        googleBtn.addEventListener('mouseleave', function() {
            if (!captchaValidated) {
                setTimeout(() => {
                    popover.classList.add('hidden');
                }, 200);
            }
        });

        googleBtn.addEventListener('click', function() {
            if (!captchaValidated) {
                popover.classList.toggle('hidden');

                setTimeout(() => {
                    popover.classList.add('hidden');
                }, 3000);
            }
        });
    }
}

function onCaptchaSuccess(token) {
    console.log('Captcha validé avec succès');
    captchaValidated = true;

    const googleBtn = document.getElementById('googleLoginBtn');
    const popover = document.getElementById('captcha-popover');

    if (googleBtn) {
        googleBtn.disabled = false;
        googleBtn.classList.remove('opacity-50', 'cursor-not-allowed');
    }

    if (popover) {
        popover.classList.add('hidden');
    }

    showAlert('✅ Captcha validé ! Vous pouvez maintenant utiliser la connexion Google.', 'success');
}

function handleGoogleLogin() {
    if (!captchaValidated) {
        const popover = document.getElementById('captcha-popover');
        if (popover) {
            popover.classList.remove('hidden');
            setTimeout(() => {
                popover.classList.add('hidden');
            }, 3000);
        }
        return;
    }

    loginWithGoogle();
}

function adjustContentMargin() {
    const header = document.querySelector('header');
    const mainContent = document.getElementById('main-content');

    if (header && mainContent) {
        mainContent.style.paddingTop = `${header.offsetHeight}px`;
    }
}

document.getElementById('remember_me').addEventListener('change', function() {
    const info = document.getElementById('remember-me-info');
    if (this.checked) {
        info.classList.remove('hidden');
    } else {
        info.classList.add('hidden');
    }
});

document.getElementById('loginForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('loginBtn');
    const btnText = document.getElementById('loginBtnText');
    const btnSpinner = document.getElementById('loginBtnSpinner');

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
            password: formData.get('password'),
            remember_me: formData.get('remember_me') ? true : false,
            turnstile_token: turnstileResponse
        };

        if (requires2FA && formData.get('totp_code')) {
            data.totp_code = formData.get('totp_code');
        }

        console.log('Sending login request with remember_me:', data.remember_me);

        const response = await fetch('/api/v1/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        console.log('Login response:', result);

        if (result.success) {
            showAlert('🎉 Connexion réussie ! Redirection...', 'success');

            setTimeout(() => {
                if (result.needs_profile_completion) {
                    window.location.href = '/auth/complete-profile';
                } else {
                    window.location.href = '/dashboard';
                }
            }, 1500);

        } else if (result.requires_2fa) {

            requires2FA = true;
            currentUserId = result.user_id;
            show2FAField();
            showAlert('Veuillez entrer votre code d\'authentification à deux facteurs.', 'info');

        } else {
            showAlert(result.error || 'Erreur de connexion', 'error');
            try {
                if (typeof turnstile !== 'undefined') {
                    turnstile.reset();
                    captchaValidated = false;
                    document.getElementById('googleLoginBtn').disabled = true;
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
                document.getElementById('googleLoginBtn').disabled = true;
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

function show2FAField() {
    const container = document.getElementById('twofa-container');
    const input = document.getElementById('totp_code');

    container.classList.remove('hidden');
    input.focus();
}

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const eye = document.getElementById(fieldId + '-eye');

    if (field.type === 'password') {
        field.type = 'text';
        eye.className = 'fas fa-eye-slash text-lg';
    } else {
        field.type = 'password';
        eye.className = 'fas fa-eye text-lg';
    }
}

function loginWithGoogle() {
    const rememberMe = document.getElementById('remember_me').checked;

    showAlert('Redirection vers Google...', 'info');

    const googleUrl = '/auth/google/login.php' + (rememberMe ? '?remember_me=true' : '');
    window.location.href = googleUrl;
}

document.getElementById('totp_code').addEventListener('input', function(e) {
    if (e.target.value.length === 6) {

        document.getElementById('loginForm').dispatchEvent(new Event('submit'));
    }
});

function checkUrlParameters() {
    const urlParams = new URLSearchParams(window.location.search);

    if (urlParams.get('verified') === 'true') {
        showAlert('✅ Email vérifié avec succès ! Vous pouvez maintenant vous connecter.', 'success');
    }

    if (urlParams.get('reset') === 'true') {
        showAlert('✅ Mot de passe réinitialisé avec succès !', 'success');
    }

    if (urlParams.get('logout') === 'true') {
        showAlert('👋 Vous avez été déconnecté avec succès.', 'info');
    }

    if (urlParams.get('registered') === 'true') {
        showAlert('🎉 Compte créé avec succès ! Vous pouvez dès maintenant proceder à la connexion', 'success');
    }

    if (urlParams.get('profile_completed') === 'true') {
        showAlert('🎉 Profil complété avec succès ! Bienvenue sur Pilote Shopping !', 'success');
    }

    if (urlParams.get('error')) {
        showAlert(decodeURIComponent(urlParams.get('error')), 'error');
    }

    if (urlParams.get('google_login') === 'success') {
        showAlert('🎉 Connexion Google réussie ! Bienvenue !', 'success');
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
</script>

<?php
endHTMLPage();
?>