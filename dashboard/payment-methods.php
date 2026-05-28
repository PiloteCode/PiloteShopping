<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    header('Location: /auth/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

try {
    $db = Database::getInstance();

        $cardsStmt = $db->prepare("
        SELECT id, stripe_payment_method_id, type, brand, last4,
               exp_month, exp_year, country, fingerprint, nickname,
               is_default, created_at, updated_at
        FROM user_payment_methods
        WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL
        ORDER BY is_default DESC, created_at DESC
    ");
    $cardsStmt->execute([$currentUser['id']]);
    $paymentMethods = $cardsStmt->fetchAll();

        foreach ($paymentMethods as &$card) {
        $card['exp_month_formatted'] = sprintf('%02d', $card['exp_month']);
        $card['exp_year_short'] = substr($card['exp_year'], -2);

                $currentYear = (int)date('Y');
        $currentMonth = (int)date('m');
        $isExpired = ($card['exp_year'] < $currentYear) ||
                     ($card['exp_year'] == $currentYear && $card['exp_month'] < $currentMonth);

        $card['is_expired'] = $isExpired;

                $brandIcons = [
            'visa' => 'fab fa-cc-visa',
            'mastercard' => 'fab fa-cc-mastercard',
            'amex' => 'fab fa-cc-amex',
            'discover' => 'fab fa-cc-discover',
            'diners' => 'fab fa-cc-diners-club',
            'jcb' => 'fab fa-cc-jcb',
        ];

        $card['brand_icon'] = $brandIcons[strtolower($card['brand'])] ?? 'fas fa-credit-card';

                $brandColors = [
            'visa' => 'from-blue-600 to-blue-800',
            'mastercard' => 'from-orange-500 to-red-600',
            'amex' => 'from-green-600 to-teal-700',
            'discover' => 'from-orange-400 to-orange-600',
        ];

        $card['brand_color'] = $brandColors[strtolower($card['brand'])] ?? 'from-gray-600 to-gray-800';
    }

        $stats = [
        'total' => count($paymentMethods),
        'active' => count(array_filter($paymentMethods, fn($c) => !$c['is_expired'])),
        'expired' => count(array_filter($paymentMethods, fn($c) => $c['is_expired'])),
        'default_card' => array_filter($paymentMethods, fn($c) => $c['is_default'])[0] ?? null
    ];

        $stripeStmt = $db->prepare("SELECT config FROM payment_adapters WHERE slug = 'stripe' AND is_active = 1");
    $stripeStmt->execute();
    $stripeConfigJson = $stripeStmt->fetchColumn();
    $stripeConfig = $stripeConfigJson ? json_decode($stripeConfigJson, true) : null;
    $stripePublicKey = $stripeConfig['public_key'] ?? null;

} catch (Exception $e) {
    error_log("Dashboard payment methods error: " . $e->getMessage());
    $paymentMethods = [];
    $stats = ['total' => 0, 'active' => 0, 'expired' => 0, 'default_card' => null];
    $stripePublicKey = null;
}

$seo = [
    'title' => 'Mes Moyens de Paiement - Pilote Shopping',
    'description' => 'Gérez vos cartes bancaires et moyens de paiement',
    'robots' => 'noindex, nofollow'
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
                <a href="/dashboard" class="text-gray-400 hover:text-white transition-colors">Dashboard</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <span class="text-white font-medium">Moyens de paiement</span>
            </div>
        </nav>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    <i class="fas fa-credit-card text-blue-primary mr-3"></i>
                    Mes Moyens de Paiement
                </h1>
                <p class="text-gray-400">Gérez vos cartes bancaires et moyens de paiement</p>
            </div>

<?php if ($stripePublicKey): ?>
            <div class="mt-6 lg:mt-0">
                <button onclick="openAddCardModal()"
                        class="bg-blue-primary hover:bg-blue-secondary text-white font-bold py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-plus mr-2"></i>Ajouter une carte
                </button>
            </div>
<?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total de cartes</p>
                        <p class="text-2xl font-bold text-white"><?= $stats['total'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-primary/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-credit-card text-blue-primary text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Cartes actives</p>
                        <p class="text-2xl font-bold text-green-400"><?= $stats['active'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-check-circle text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Cartes expirées</p>
                        <p class="text-2xl font-bold text-orange-400"><?= $stats['expired'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-exclamation-triangle text-orange-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

<?php if (empty($paymentMethods)): ?>
        <div class="text-center py-16">
            <div class="w-24 h-24 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-credit-card text-blue-400 text-4xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Aucun moyen de paiement</h3>
            <p class="text-gray-400 mb-8">Ajoutez une carte pour faciliter vos paiements</p>
<?php if ($stripePublicKey): ?>
            <button onclick="openAddCardModal()"
                    class="bg-blue-primary hover:bg-blue-secondary text-white font-bold py-3 px-6 rounded-xl transition-colors">
                <i class="fas fa-plus mr-2"></i>Ajouter ma première carte
            </button>
<?php endif; ?>
        </div>
<?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
<?php foreach ($paymentMethods as $card): ?>
            <div class="relative payment-card" data-card-id="<?= $card['stripe_payment_method_id'] ?>">

                <div class="relative h-48 rounded-2xl overflow-hidden shadow-2xl transform transition-all hover:scale-105">

                    <div class="absolute inset-0 bg-gradient-to-br <?= $card['brand_color'] ?> opacity-90"></div>

                    <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,%3Csvg width=&quot;100&quot; height=&quot;100&quot; viewBox=&quot;0 0 100 100&quot; xmlns=&quot;http://www.w3.org/2000/svg&quot;%3E%3Cpath d=&quot;M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3z&quot; fill=&quot;%23ffffff&quot; fill-opacity=&quot;0.05&quot; fill-rule=&quot;evenodd&quot;/%3E%3C/svg%3E'); opacity: 0.3;"></div>

                    <div class="relative h-full p-6 flex flex-col justify-between text-white">

                        <div class="flex justify-between items-start">
                            <div>
<?php if ($card['is_default']): ?>
                                <span class="inline-block px-2 py-1 bg-white/20 backdrop-blur-sm rounded-lg text-xs font-bold mb-2">
                                    <i class="fas fa-star mr-1"></i>Par défaut
                                </span>
<?php endif; ?>
<?php if ($card['is_expired']): ?>
                                <span class="inline-block px-2 py-1 bg-red-500/50 backdrop-blur-sm rounded-lg text-xs font-bold mb-2">
                                    <i class="fas fa-exclamation-circle mr-1"></i>Expirée
                                </span>
<?php endif; ?>
                            </div>
                            <i class="<?= $card['brand_icon'] ?> text-4xl opacity-80"></i>
                        </div>

                        <div>
                            <div class="w-12 h-9 bg-gradient-to-br from-yellow-200 to-yellow-400 rounded-md mb-4 flex items-center justify-center">
                                <div class="w-8 h-6 border-2 border-yellow-600/30 rounded-sm"></div>
                            </div>
                        </div>

                        <div>
                            <div class="text-xl font-mono tracking-wider mb-3">
                                •••• •••• •••• <?= htmlspecialchars($card['last4']) ?>
                            </div>

                            <div class="flex justify-between items-end">
                                <div>
<?php if ($card['nickname']): ?>
                                    <div class="text-xs opacity-70">NOM</div>
                                    <div class="text-sm font-medium"><?= htmlspecialchars($card['nickname']) ?></div>
<?php else: ?>
                                    <div class="text-xs opacity-70">TITULAIRE</div>
                                    <div class="text-sm font-medium"><?= htmlspecialchars($currentUser['first_name'] . ' ' . $currentUser['last_name']) ?></div>
<?php endif; ?>
                                </div>

                                <div class="text-right">
                                    <div class="text-xs opacity-70">EXPIRE</div>
                                    <div class="text-sm font-medium"><?= $card['exp_month_formatted'] ?>/<?= $card['exp_year_short'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
<?php if (!$card['is_default'] && !$card['is_expired']): ?>
                    <button onclick="setDefaultCard('<?= $card['stripe_payment_method_id'] ?>')"
                            class="flex-1 bg-blue-500/20 hover:bg-blue-500/30 text-blue-400 font-medium py-2 px-3 rounded-lg transition-colors text-sm">
                        <i class="fas fa-star mr-1"></i>Définir par défaut
                    </button>
<?php endif; ?>

                    <button onclick="editCardNickname('<?= $card['stripe_payment_method_id'] ?>', '<?= htmlspecialchars($card['nickname'] ?? '', ENT_QUOTES) ?>')"
                            class="bg-gray-600/20 hover:bg-gray-600/30 text-gray-300 font-medium py-2 px-3 rounded-lg transition-colors text-sm">
                        <i class="fas fa-edit"></i>
                    </button>

                    <button onclick="deleteCard('<?= $card['stripe_payment_method_id'] ?>', '<?= htmlspecialchars($card['brand']) ?> ****<?= $card['last4'] ?>')"
                            class="bg-red-500/20 hover:bg-red-500/30 text-red-400 font-medium py-2 px-3 rounded-lg transition-colors text-sm">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

<?php if ($card['is_expired']): ?>
                <div class="mt-2 text-center">
                    <p class="text-orange-400 text-xs">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        Cette carte a expiré. Veuillez la supprimer et en ajouter une nouvelle.
                    </p>
                </div>
<?php endif; ?>
            </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>


        <div class="mt-12 bg-blue-500/10 border border-blue-500/30 rounded-2xl p-6">
            <div class="flex items-start space-x-4">
                <div class="w-12 h-12 bg-blue-500/20 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-shield-alt text-blue-400 text-xl"></i>
                </div>
                <div>
                    <h3 class="text-white font-bold mb-2">
                        <i class="fas fa-lock mr-2"></i>Paiements sécurisés
                    </h3>
                    <p class="text-gray-400 text-sm mb-3">
                        Vos informations de paiement sont sécurisées par Stripe, leader mondial des paiements en ligne.
                        Nous ne stockons jamais vos numéros de carte complets.
                    </p>
                    <ul class="text-gray-400 text-sm space-y-1">
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Chiffrement SSL/TLS 256 bits</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Conformité PCI-DSS niveau 1</li>
                        <li><i class="fas fa-check text-green-400 mr-2"></i>Authentification 3D Secure</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>


<div id="add-card-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 max-w-md w-full">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-credit-card mr-2"></i>Ajouter une carte
            </h3>
            <button onclick="closeAddCardModal()" class="text-gray-400 hover:text-white transition-colors">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <form id="add-card-form">

            <div class="mb-6">
                <label class="block text-gray-400 text-sm font-medium mb-2">Informations de carte</label>
                <div id="card-element" class="bg-dark-700 border border-dark-600 rounded-xl p-4">

                </div>
                <div id="card-errors" class="text-red-400 text-sm mt-2"></div>
            </div>

            <div class="mb-6">
                <label class="block text-gray-400 text-sm font-medium mb-2">Surnom (optionnel)</label>
                <input type="text"
                       id="card-nickname"
                       maxlength="50"
                       placeholder="Ex: Carte personnelle, Carte professionnelle..."
                       class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:border-blue-primary focus:outline-none transition-colors">
            </div>

            <div class="mb-6">
                <label class="flex items-center cursor-pointer">
                    <input type="checkbox" id="set-default" class="mr-3 w-5 h-5 rounded border-dark-600 bg-dark-700 text-blue-primary focus:ring-blue-primary focus:ring-offset-0">
                    <span class="text-white text-sm">Définir comme carte par défaut</span>
                </label>
            </div>

            <div class="flex space-x-3">
                <button type="button"
                        onclick="closeAddCardModal()"
                        class="flex-1 bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    Annuler
                </button>
                <button type="submit"
                        id="submit-card-btn"
                        class="flex-1 bg-blue-primary hover:bg-blue-secondary text-white font-bold py-3 px-4 rounded-xl transition-colors">
                    Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<div id="edit-nickname-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center p-4">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 max-w-md w-full">
        <h3 class="text-xl font-bold text-white mb-6">
            <i class="fas fa-edit mr-2"></i>Modifier le surnom
        </h3>

        <form id="edit-nickname-form">
            <input type="hidden" id="edit-card-id">
            <div class="mb-6">
                <label class="block text-gray-400 text-sm font-medium mb-2">Surnom</label>
                <input type="text"
                       id="edit-nickname-input"
                       maxlength="50"
                       placeholder="Nouveau surnom"
                       class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-500 focus:border-blue-primary focus:outline-none transition-colors">
            </div>

            <div class="flex space-x-3">
                <button type="button"
                        onclick="closeEditNicknameModal()"
                        class="flex-1 bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    Annuler
                </button>
                <button type="submit"
                        class="flex-1 bg-blue-primary hover:bg-blue-secondary text-white font-bold py-3 px-4 rounded-xl transition-colors">
                    Enregistrer
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($stripePublicKey): ?>
<script src="https://js.stripe.com/v3/"></script>
<script>
const stripe = Stripe('<?= $stripePublicKey ?>');
const elements = stripe.elements();

const cardStyle = {
    base: {
        color: '#ffffff',
        fontFamily: '"Inter", -apple-system, BlinkMacSystemFont, sans-serif',
        fontSize: '16px',
        '::placeholder': {
            color: '#6b7280'
        }
    },
    invalid: {
        color: '#ef4444',
        iconColor: '#ef4444'
    }
};

const cardElement = elements.create('card', { style: cardStyle });
let cardMounted = false;

function openAddCardModal() {
    const modal = document.getElementById('add-card-modal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (!cardMounted) {
        cardElement.mount('#card-element');
        cardMounted = true;

        cardElement.on('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
    }
}

function closeAddCardModal() {
    const modal = document.getElementById('add-card-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');

    document.getElementById('add-card-form').reset();
    document.getElementById('card-errors').textContent = '';
}

document.getElementById('add-card-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('submit-card-btn');
    const originalText = submitBtn.innerHTML;

    try {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Traitement...';

        const setupResponse = await fetch('/api/v1/payments/stripe/saved-cards.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'setup_intent'
            })
        });

        const setupResult = await setupResponse.json();

        if (!setupResult.success) {
            throw new Error(setupResult.error || 'Erreur lors de la création du Setup Intent');
        }

        const { setupIntent, error } = await stripe.confirmCardSetup(
            setupResult.client_secret,
            {
                payment_method: {
                    card: cardElement
                }
            }
        );

        if (error) {
            throw new Error(error.message);
        }

        const nickname = document.getElementById('card-nickname').value.trim();
        const setDefault = document.getElementById('set-default').checked;

        const saveResponse = await fetch('/api/v1/payments/stripe/saved-cards.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'save_card',
                payment_method_id: setupIntent.payment_method,
                set_as_default: setDefault,
                nickname: nickname || null
            })
        });

        const saveResult = await saveResponse.json();

        if (saveResult.success) {
            showNotification('Carte ajoutée avec succès', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(saveResult.error || 'Erreur lors de la sauvegarde');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
    }
});

async function setDefaultCard(paymentMethodId) {
    try {
        showLoading(true);

        const response = await fetch('/api/v1/payments/stripe/saved-cards.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'set_default',
                payment_method_id: paymentMethodId
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Carte définie comme carte par défaut', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error);
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
    } finally {
        showLoading(false);
    }
}

async function deleteCard(paymentMethodId, cardLabel) {
    if (!confirm(`Êtes-vous sûr de vouloir supprimer la carte ${cardLabel} ?`)) {
        return;
    }

    try {
        showLoading(true);

        const response = await fetch('/api/v1/payments/stripe/saved-cards.php', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                payment_method_id: paymentMethodId
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Carte supprimée avec succès', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error);
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
    } finally {
        showLoading(false);
    }
}

function editCardNickname(paymentMethodId, currentNickname) {
    const modal = document.getElementById('edit-nickname-modal');
    document.getElementById('edit-card-id').value = paymentMethodId;
    document.getElementById('edit-nickname-input').value = currentNickname;

    modal.classList.remove('hidden');
    modal.classList.add('flex');

    document.getElementById('edit-nickname-input').focus();
}

function closeEditNicknameModal() {
    const modal = document.getElementById('edit-nickname-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

document.getElementById('edit-nickname-form').addEventListener('submit', async function(e) {
    e.preventDefault();

    const paymentMethodId = document.getElementById('edit-card-id').value;
    const nickname = document.getElementById('edit-nickname-input').value.trim();

    try {
        showLoading(true);

        const response = await fetch('/api/v1/payments/stripe/saved-cards.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'update_nickname',
                payment_method_id: paymentMethodId,
                nickname: nickname
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Surnom mis à jour', 'success');
            closeEditNicknameModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error);
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
    } finally {
        showLoading(false);
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddCardModal();
        closeEditNicknameModal();
    }
});
</script>
<?php endif; ?>

<script>

function showLoading(show) {
    let loader = document.getElementById('page-loader');

    if (show && !loader) {
        loader = document.createElement('div');
        loader.id = 'page-loader';
        loader.className = 'fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center';
        loader.innerHTML = `
            <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-primary mx-auto mb-4"></div>
                <p class="text-white">Traitement en cours...</p>
            </div>
        `;
        document.body.appendChild(loader);
    } else if (!show && loader) {
        document.body.removeChild(loader);
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl text-white font-medium transition-all duration-300 transform translate-x-full`;

    switch (type) {
        case 'success':
            notification.className += ' bg-green-500';
            break;
        case 'error':
            notification.className += ' bg-red-500';
            break;
        default:
            notification.className += ' bg-blue-primary';
    }

    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);

    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}
</script>

<style>

.payment-card {
    transition: all 0.3s ease;
}

.payment-card:hover {
    transform: translateY(-4px);
}

#card-element {
    min-height: 50px;
}

.StripeElement {
    padding: 12px;
}

.StripeElement--focus {
    border-color: #3b82f6;
}

.StripeElement--invalid {
    border-color: #ef4444;
}

@media (max-width: 768px) {
    .payment-card {
        margin-bottom: 1rem;
    }
}
</style>

<?php
endHTMLPage();
?>
