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

$token = $_GET['token'] ?? '';
if (empty($token)) {
    header('Location: /auth/forgot-password?error=' . urlencode('Token de réinitialisation manquant'));
    exit();
}

$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT vt.*, u.email, u.first_name 
    FROM verification_tokens vt
    JOIN users u ON vt.user_id = u.id
    WHERE vt.token = ? 
    AND vt.type = 'password_reset' 
    AND vt.used_at IS NULL 
    AND vt.expires_at > NOW()
");
$stmt->execute([$token]);
$tokenData = $stmt->fetch();

if (!$tokenData) {
        $stmt = $db->prepare("
        SELECT used_at, expires_at 
        FROM verification_tokens 
        WHERE token = ? AND type = 'password_reset'
    ");
    $stmt->execute([$token]);
    $invalidToken = $stmt->fetch();
    
    if ($invalidToken) {
        if ($invalidToken['used_at']) {
            $redirectUrl = '/auth/forgot-password?used=true';
        } elseif (strtotime($invalidToken['expires_at']) < time()) {
            $redirectUrl = '/auth/forgot-password?expired=true';
        } else {
            $redirectUrl = '/auth/forgot-password?invalid=true';
        }
    } else {
        $redirectUrl = '/auth/forgot-password?invalid=true';
    }
    
    header('Location: ' . $redirectUrl);
    exit();
}

$seo = [
    'title' => 'Nouveau mot de passe - Pilote Shopping',
    'description' => 'Créez un nouveau mot de passe sécurisé pour votre compte Pilote Shopping.',
    'keywords' => 'nouveau mot de passe, réinitialisation, sécurité, pilote shopping',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen">

<section class="min-h-screen relative overflow-hidden">

    <div class="absolute inset-0 -z-10">
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-green-500/20 rounded-full blur-3xl"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-blue-primary/20 rounded-full blur-3xl"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-gradient-radial opacity-30"></div>
    </div>

    <div class="relative z-10 flex items-center justify-center min-h-screen px-4 py-12">
        <div class="w-full max-w-lg space-y-8">

            <div class="text-center space-y-4" data-aos="fade-up">

                <div class="inline-flex items-center space-x-2 bg-gradient-to-r from-green-500/20 to-blue-primary/20 rounded-full px-6 py-2 mb-6 border border-green-500/30">
                    <i class="fas fa-lock text-green-500"></i>
                    <span class="text-white font-medium">Réinitialisation sécurisée</span>
                </div>

                <h1 class="text-4xl md:text-5xl font-bold text-white mb-6 leading-tight">
                    <span class="block">Nouveau</span>
                    <span class="block bg-gradient-to-r from-green-500 to-blue-primary bg-clip-text text-transparent">
                        mot de passe
                    </span>
                </h1>

                <p class="text-xl text-gray-300 mb-8 max-w-3xl mx-auto leading-relaxed">
                    Salut <?= htmlspecialchars($tokenData['first_name']) ?> ! Créez un nouveau mot de passe sécurisé pour votre compte.
                </p>

                <div class="bg-green-500/10 border border-green-500/20 rounded-xl p-4 backdrop-blur-sm">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center">
                            <i class="fas fa-user text-green-500"></i>
                        </div>
                        <div class="text-left">
                            <p class="text-sm font-medium text-green-500">Compte à réinitialiser</p>
                            <p class="text-sm text-green-300/80"><?= htmlspecialchars($tokenData['email']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-lg rounded-3xl border border-dark-600 p-8 lg:p-12 space-y-8 shadow-2xl" data-aos="fade-up" data-aos-delay="200">

                <div id="alert-container" class="hidden">
                    <div id="alert" class="p-4 rounded-xl text-sm font-medium border backdrop-blur-sm"></div>
                </div>

                <div id="password-strength" class="hidden bg-dark-700/50 backdrop-blur-sm rounded-xl p-4 border border-dark-600">
                    <div class="flex items-center justify-between text-sm text-gray-300 mb-2">
                        <span>Force du mot de passe</span>
                        <span id="strength-text" class="font-bold">Faible</span>
                    </div>
                    <div class="w-full bg-dark-800 rounded-full h-2 overflow-hidden">
                        <div id="strength-bar" class="h-2 rounded-full transition-all duration-500" style="width: 0%"></div>
                    </div>
                    <div id="strength-requirements" class="mt-3 space-y-1 text-xs">
                        <div class="flex items-center space-x-2">
                            <i id="req-length" class="fas fa-times text-red-400"></i>
                            <span>Au moins 8 caractères</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i id="req-lower" class="fas fa-times text-red-400"></i>
                            <span>Au moins une minuscule</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i id="req-upper" class="fas fa-times text-red-400"></i>
                            <span>Au moins une majuscule</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i id="req-number" class="fas fa-times text-red-400"></i>
                            <span>Au moins un chiffre</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <i id="req-special" class="fas fa-times text-red-400"></i>
                            <span>Au moins un caractère spécial (@$!%*?&)</span>
                        </div>
                    </div>
                </div>

                <form id="resetPasswordForm" class="space-y-8">
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div class="space-y-2">
                        <label for="password" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-lock text-green-500 mr-2"></i>Nouveau mot de passe
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                autocomplete="new-password"
                                class="w-full px-4 py-3 pr-12 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all duration-300"
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

                    <div class="space-y-2">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-300">
                            <i class="fas fa-lock text-green-500 mr-2"></i>Confirmer le mot de passe
                        </label>
                        <div class="relative">
                            <input 
                                type="password" 
                                id="confirm_password" 
                                name="confirm_password" 
                                required
                                autocomplete="new-password"
                                class="w-full px-4 py-3 pr-12 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20 transition-all duration-300"
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
                        <div id="password-match" class="hidden text-xs mt-1"></div>
                    </div>

                    <div class="bg-blue-primary/10 border border-blue-primary/20 rounded-xl p-4 backdrop-blur-sm">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-lightbulb text-blue-primary text-lg mt-0.5"></i>
                            <div>
                                <h4 class="text-sm font-medium text-blue-primary">Conseils pour un mot de passe sécurisé</h4>
                                <ul class="text-sm text-blue-300/80 mt-1 space-y-1">
                                    <li>• Utilisez une phrase secrète facile à retenir</li>
                                    <li>• Mélangez lettres, chiffres et symboles</li>
                                    <li>• Évitez les informations personnelles</li>
                                    <li>• Ne réutilisez pas d'anciens mots de passe</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-center">
                        <div class="cf-turnstile" data-sitekey="<?= CLOUDFLARE_TURNSTILE_SITE_KEY ?>" data-theme="dark" data-callback="onCaptchaSuccess"></div>
                    </div>

                    <button 
                        type="submit" 
                        id="submitBtn"
                        class="group w-full py-4 px-8 bg-gradient-to-r from-green-500 to-blue-primary hover:from-green-600 hover:to-blue-secondary text-white font-bold rounded-xl transition-all duration-300 transform hover:scale-105 hover:shadow-2xl focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 focus:ring-offset-dark-800 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                    >
                        <span id="submitBtnText" class="flex items-center justify-center space-x-3">
                            <i class="fas fa-shield-alt group-hover:animate-pulse"></i>
                            <span>Changer le mot de passe</span>
                            <i class="fas fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
                        </span>
                        <div id="submitBtnSpinner" class="hidden flex items-center justify-center space-x-3">
                            <i class="fas fa-spinner fa-spin"></i>
                            <span>Changement en cours...</span>
                        </div>
                    </button>
                </form>

                <div class="text-center pt-6 border-t border-dark-600">
                    <a href="/auth/login" class="text-green-500 hover:text-green-400 transition-colors font-medium">
                        <i class="fas fa-arrow-left mr-1"></i>Retour à la connexion
                    </a>
                </div>
            </div>

            <div class="text-center" data-aos="fade-up" data-aos-delay="400">
                <div class="inline-flex items-center space-x-3 bg-dark-800/30 backdrop-blur-sm border border-dark-600 rounded-2xl px-6 py-4">
                    <div class="w-8 h-8 bg-red-500/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="text-left">
                        <p class="text-sm font-medium text-white">Ce lien expire dans 1 heure</p>
                        <p class="text-xs text-gray-400">Usage unique pour votre sécurité</p>
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

let captchaValidated = false;

document.addEventListener('DOMContentLoaded', function() {
    console.log('=== Initialisation de la page réinitialisation mot de passe ===');

    setTimeout(initAOS, 100);

    adjustContentMargin();

    initializePasswordValidation();
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

function initializePasswordValidation() {
    const passwordField = document.getElementById('password');
    const confirmField = document.getElementById('confirm_password');

    if (passwordField) {
        passwordField.addEventListener('input', function() {
            validatePasswordStrength(this.value);
            checkPasswordMatch();
        });

        passwordField.addEventListener('focus', function() {
            document.getElementById('password-strength').classList.remove('hidden');
        });
    }

    if (confirmField) {
        confirmField.addEventListener('input', checkPasswordMatch);
        confirmField.addEventListener('blur', checkPasswordMatch);
    }
}

function validatePasswordStrength(password) {
    const requirements = {
        length: password.length >= 8,
        lower: /[a-z]/.test(password),
        upper: /[A-Z]/.test(password),
        number: /\d/.test(password),
        special: /[@$!%*?&]/.test(password)
    };

    Object.keys(requirements).forEach(req => {
        const icon = document.getElementById(`req-${req}`);
        if (icon) {
            if (requirements[req]) {
                icon.className = 'fas fa-check text-green-400';
            } else {
                icon.className = 'fas fa-times text-red-400';
            }
        }
    });

    const score = Object.values(requirements).filter(Boolean).length;
    const strengthBar = document.getElementById('strength-bar');
    const strengthText = document.getElementById('strength-text');

    if (strengthBar && strengthText) {
        let strength, color, width;

        switch (score) {
            case 0:
            case 1:
                strength = 'Très faible';
                color = '#EF4444';
                width = '20%';
                break;
            case 2:
                strength = 'Faible';
                color = '#F97316';
                width = '40%';
                break;
            case 3:
                strength = 'Moyen';
                color = '#EAB308';
                width = '60%';
                break;
            case 4:
                strength = 'Fort';
                color = '#22C55E';
                width = '80%';
                break;
            case 5:
                strength = 'Très fort';
                color = '#10B981';
                width = '100%';
                break;
        }

        strengthText.textContent = strength;
        strengthText.style.color = color;
        strengthBar.style.backgroundColor = color;
        strengthBar.style.width = width;
    }

    return score >= 4; 
}

function checkPasswordMatch() {
    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    const matchDiv = document.getElementById('password-match');

    if (confirmPassword.length > 0) {
        matchDiv.classList.remove('hidden');

        if (password === confirmPassword) {
            matchDiv.className = 'text-green-400 text-xs mt-1 flex items-center space-x-1';
            matchDiv.innerHTML = '<i class="fas fa-check"></i><span>Les mots de passe correspondent</span>';
            return true;
        } else {
            matchDiv.className = 'text-red-400 text-xs mt-1 flex items-center space-x-1';
            matchDiv.innerHTML = '<i class="fas fa-times"></i><span>Les mots de passe ne correspondent pas</span>';
            return false;
        }
    } else {
        matchDiv.classList.add('hidden');
        return false;
    }
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

document.getElementById('resetPasswordForm').addEventListener('submit', async function(e) {
    e.preventDefault();

    const btn = document.getElementById('submitBtn');
    const btnText = document.getElementById('submitBtnText');
    const btnSpinner = document.getElementById('submitBtnSpinner');

    if (!captchaValidated) {
        showAlert('Veuillez valider le captcha de sécurité.', 'error');
        return;
    }

    const password = document.getElementById('password').value;
    const confirmPassword = document.getElementById('confirm_password').value;

    if (!validatePasswordStrength(password)) {
        showAlert('Le mot de passe ne respecte pas les critères de sécurité.', 'error');
        return;
    }

    if (!checkPasswordMatch()) {
        showAlert('Les mots de passe ne correspondent pas.', 'error');
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
            token: formData.get('token'),
            password: password,
            confirm_password: confirmPassword,
            turnstile_token: turnstileResponse
        };

        console.log('Sending reset password request...');

        const response = await fetch('/api/v1/auth/reset-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(data)
        });

        const result = await response.json();
        console.log('Reset password response:', result);

        if (result.success) {
            showAlert('🎉 Mot de passe changé avec succès !', 'success');

            if (typeof confetti !== 'undefined') {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 },
                    colors: ['#10B981', '#3B82F6', '#06B6D4']
                });
            }

            setTimeout(() => {
                window.location.href = '/auth/login?reset=true';
            }, 2000);

        } else {
            showAlert(result.error || 'Erreur lors de la réinitialisation', 'error');

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