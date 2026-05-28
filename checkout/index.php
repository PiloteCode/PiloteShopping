<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        $needsAuthentication = false;
    if (!$currentUser) {
                session_start();
        $cartCheckStmt = $db->prepare("
            SELECT COUNT(*) as subscription_count
            FROM cart_items ci
            LEFT JOIN product_offers po ON ci.product_offer_id = po.id
            WHERE ci.session_id = ? 
            AND po.billing_cycle IS NOT NULL AND po.billing_cycle != 'one_time'
        ");
        $cartCheckStmt->execute([session_id()]);
        $cartCheck = $cartCheckStmt->fetch();
        
        if ($cartCheck['subscription_count'] > 0) {
            $needsAuthentication = true;
        }
    }
    
        $adaptersStmt = $db->prepare("
        SELECT *
        FROM payment_adapters
        WHERE is_active = 1
        ORDER BY sort_order ASC
    ");
    $adaptersStmt->execute();
    $paymentAdapters = $adaptersStmt->fetchAll();
    
        $stripeConfig = null;
    foreach ($paymentAdapters as $adapter) {
        if ($adapter['slug'] === 'stripe') {
            $config = json_decode($adapter['config'], true);
            $stripeConfig = [
                'publishable_key' => $config['publishable_key'] ?? '',
                'supports_subscriptions' => $adapter['supports_subscriptions']
            ];
            break;
        }
    }
    
        $countries = [
        'FR' => 'France',
        'BE' => 'Belgique',
        'CH' => 'Suisse',
        'CA' => 'Canada',
        'LU' => 'Luxembourg',
        'MC' => 'Monaco',
        'ES' => 'Espagne',
        'IT' => 'Italie',
        'DE' => 'Allemagne',
        'GB' => 'Royaume-Uni',
        'US' => 'États-Unis'
    ];
    
        $billingAddresses = [];
    if ($currentUser) {
        $billingStmt = $db->prepare("
            SELECT *
            FROM users_billing
            WHERE user_id = ?
            ORDER BY is_default DESC, created_at DESC
        ");
        $billingStmt->execute([$currentUser['id']]);
        $billingAddresses = $billingStmt->fetchAll();
    }
    
} catch (Exception $e) {
    error_log("Checkout page error: " . $e->getMessage());
    $paymentAdapters = [];
    $billingAddresses = [];
    $countries = ['FR' => 'France'];
    $stripeConfig = null;
}

$seo = [
    'title' => 'Finaliser votre commande - Pilote Shopping',
    'description' => 'Finalisez votre achat en toute sécurité avec nos solutions de paiement sécurisées',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen bg-dark-900 py-8">
    <div class="container mx-auto px-4 lg:px-6">

<?php if ($needsAuthentication): ?>
        <div id="auth-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center">
            <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 max-w-md mx-4">
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-user-lock text-blue-primary text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-4">Connexion requise</h3>
                    <p class="text-gray-400 mb-6">
                        Vous devez être connecté pour commander des abonnements.
                    </p>
                    <div class="flex flex-col space-y-3">
                        <a href="/auth/login?redirect=/checkout" 
                           class="bg-blue-primary hover:bg-blue-secondary text-white font-bold py-3 px-6 rounded-xl transition-colors">
                            <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                        </a>
                        <a href="/auth/register?redirect=/checkout" 
                           class="bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-6 rounded-xl transition-colors">
                            <i class="fas fa-user-plus mr-2"></i>Créer un compte
                        </a>
                        <a href="/cart" 
                           class="text-gray-400 hover:text-white transition-colors text-sm">
                            Retour au panier
                        </a>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>


        <nav class="mb-8">
            <div class="flex items-center space-x-2 text-sm">
                <a href="/" class="text-gray-400 hover:text-white transition-colors">Accueil</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <a href="/cart" class="text-gray-400 hover:text-white transition-colors">Panier</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <span class="text-white font-medium">Checkout</span>
            </div>
        </nav>

        <div class="text-center mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                <i class="fas fa-credit-card text-blue-primary mr-3"></i>
                Finaliser votre commande
            </h1>
            <p class="text-gray-400">Renseignez vos informations pour finaliser votre achat</p>
        </div>

        <div class="lg:grid lg:grid-cols-12 lg:gap-8">

            <div class="lg:col-span-8 mb-8 lg:mb-0">
                <form id="checkout-form" class="space-y-8">

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                        <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                            <i class="fas fa-receipt text-blue-primary mr-3"></i>
                            Informations de facturation
                        </h3>

<?php if (!empty($billingAddresses)): ?>
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-300 mb-3">
                                Adresse de facturation
                            </label>
                            <div class="space-y-3">
<?php foreach ($billingAddresses as $index => $address): ?>
                                <label class="flex items-start space-x-3 p-4 bg-dark-700/50 rounded-xl border border-dark-600 hover:border-blue-primary/50 cursor-pointer transition-all">
                                    <input type="radio" 
                                           name="billing_address_type" 
                                           value="existing_<?= $address['id'] ?>"
                                           data-address-id="<?= $address['id'] ?>"
<?= $address['is_default'] ? 'checked' : '' ?>
                                           class="mt-1">
                                    <div class="flex-1">
                                        <div class="font-medium text-white">
<?= htmlspecialchars($address['first_name'] . ' ' . $address['last_name']) ?>
<?php if ($address['is_default']): ?>
                                            <span class="bg-blue-primary text-white text-xs px-2 py-1 rounded-full ml-2">Par défaut</span>
<?php endif; ?>
                                        </div>
                                        <div class="text-sm text-gray-400 mt-1">
<?php if ($address['company_name']): ?>
<?= htmlspecialchars($address['company_name']) ?><br>
<?php endif; ?>
<?= htmlspecialchars($address['address_line1']) ?>
<?php if ($address['address_line2']): ?>
                                            <br><?= htmlspecialchars($address['address_line2']) ?>
<?php endif; ?>
                                            <br><?= htmlspecialchars($address['postal_code'] . ' ' . $address['city']) ?>
                                            <br><?= htmlspecialchars($countries[$address['country']] ?? $address['country']) ?>
                                        </div>
                                    </div>
                                </label>
<?php endforeach; ?>

                                <label class="flex items-start space-x-3 p-4 bg-dark-700/50 rounded-xl border border-dark-600 hover:border-blue-primary/50 cursor-pointer transition-all">
                                    <input type="radio" name="billing_address_type" value="new" class="mt-1">
                                    <div>
                                        <div class="font-medium text-white">Nouvelle adresse</div>
                                        <div class="text-sm text-gray-400">Saisir une nouvelle adresse de facturation</div>
                                    </div>
                                </label>
                            </div>
                        </div>
<?php endif; ?>


                        <div id="new-billing-address" class="grid grid-cols-1 md:grid-cols-2 gap-4 <?= !empty($billingAddresses) ? 'hidden' : '' ?>">
<?php 
                            $defaultAddress = null;
                            if (!empty($billingAddresses)) {
                                foreach ($billingAddresses as $addr) {
                                    if ($addr['is_default']) {
                                        $defaultAddress = $addr;
                                        break;
                                    }
                                }
                                if (!$defaultAddress) {
                                    $defaultAddress = $billingAddresses[0];
                                }
                            }
                            ?>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Nom de l'entreprise (optionnel)
                                </label>
                                <input type="text" 
                                       name="company_name" 
                                       id="company_name"
                                       value="<?= htmlspecialchars($defaultAddress['company_name'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                                       placeholder="Votre entreprise">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Prénom <span class="text-red-400">*</span>
                                </label>
                                <input type="text" 
                                       name="first_name" 
                                       id="first_name"
                                       required
                                       value="<?= htmlspecialchars($defaultAddress['first_name'] ?? $currentUser['first_name'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                                       placeholder="Votre prénom">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Nom <span class="text-red-400">*</span>
                                </label>
                                <input type="text" 
                                       name="last_name" 
                                       id="last_name"
                                       required
                                       value="<?= htmlspecialchars($defaultAddress['last_name'] ?? $currentUser['last_name'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                                       placeholder="Votre nom">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Adresse <span class="text-red-400">*</span>
                                </label>
                                <input type="text" 
                                       name="address_line1" 
                                       id="address_line1"
                                       required
                                       value="<?= htmlspecialchars($defaultAddress['address_line1'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                                       placeholder="Numéro et nom de rue">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Complément d'adresse (optionnel)
                                </label>
                                <input type="text" 
                                       name="address_line2" 
                                       id="address_line2"
                                       value="<?= htmlspecialchars($defaultAddress['address_line2'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                                       placeholder="Appartement, bâtiment, etc.">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Code postal <span class="text-red-400">*</span>
                                </label>
                                <input type="text" 
                                       name="postal_code" 
                                       id="postal_code"
                                       required
                                       value="<?= htmlspecialchars($defaultAddress['postal_code'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                                       placeholder="75001">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Ville <span class="text-red-400">*</span>
                                </label>
                                <input type="text" 
                                       name="city" 
                                       id="city"
                                       required
                                       value="<?= htmlspecialchars($defaultAddress['city'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                                       placeholder="Paris">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Pays <span class="text-red-400">*</span>
                                </label>
                                <select name="country" 
                                        id="country"
                                        required
                                        class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white focus:outline-none focus:border-blue-primary transition-colors">
<?php 
                                    $defaultCountry = $defaultAddress['country'] ?? 'FR';
                                    foreach ($countries as $code => $name): 
                                    ?>
                                    <option value="<?= $code ?>" <?= $code === $defaultCountry ? 'selected' : '' ?>>
<?= htmlspecialchars($name) ?>
                                    </option>
<?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">
                                    Téléphone (optionnel)
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       id="phone"
                                       value="<?= htmlspecialchars($defaultAddress['phone'] ?? $currentUser['phone'] ?? '') ?>"
                                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                                       placeholder="+33 1 23 45 67 89">
                            </div>

<?php if ($currentUser): ?>
                            <div class="md:col-span-2">
                                <label class="flex items-center space-x-3">
                                    <input type="checkbox" 
                                           name="save_address" 
                                           id="save_address"
                                           value="1"
                                           class="w-5 h-5 bg-dark-700 border border-dark-600 rounded text-blue-primary focus:ring-blue-primary focus:ring-2">
                                    <span class="text-sm text-gray-300">
                                        Enregistrer cette adresse pour mes prochaines commandes
                                    </span>
                                </label>
                            </div>
<?php endif; ?>
                        </div>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                        <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                            <i class="fas fa-credit-card text-blue-primary mr-3"></i>
                            Méthode de paiement
                        </h3>

                        <div class="space-y-4">
<?php foreach ($paymentAdapters as $index => $adapter): ?>
                            <label class="payment-method flex items-center justify-between p-4 bg-dark-700/50 rounded-xl border border-dark-600 hover:border-blue-primary/50 cursor-pointer transition-all">
                                <div class="flex items-center space-x-4">
                                    <input type="radio" 
                                           name="payment_method" 
                                           value="<?= htmlspecialchars($adapter['slug']) ?>"
                                           data-adapter-id="<?= $adapter['id'] ?>"
                                           data-supports-subscriptions="<?= $adapter['supports_subscriptions'] ?>"
<?= $index === 0 ? 'checked' : '' ?>
                                           class="w-5 h-5 text-blue-primary">

                                    <div>
                                        <div class="font-medium text-white">
<?= htmlspecialchars($adapter['name']) ?>
                                        </div>
                                        <div class="text-sm text-gray-400">
<?= htmlspecialchars($adapter['description']) ?>
                                        </div>
                                    </div>
                                </div>
                            </label>
<?php endforeach; ?>
                        </div>

                        <div id="stripe-payment-details" class="mt-6 <?= $paymentAdapters[0]['slug'] !== 'stripe' ? 'hidden' : '' ?>">
                            <div class="bg-dark-700/50 rounded-xl p-4">
                                <h4 class="text-white font-medium mb-3 flex items-center">
                                    <i class="fas fa-credit-card mr-2"></i>
                                    Informations de carte bancaire
                                </h4>

                                <div id="saved-cards-container" class="mb-4 hidden"></div>

                                <div id="new-card-container">
                                    <div id="card-element" class="p-3 bg-dark-800 border border-dark-600 rounded-lg">

                                    </div>
                                    <div id="card-errors" class="text-red-400 text-sm mt-2 hidden"></div>
                                </div>

<?php if ($currentUser): ?>
                                <div class="mt-3 flex items-start space-x-2 text-xs text-gray-400">
                                    <i class="fas fa-lock mt-0.5"></i>
                                    <p>
                                        Votre carte sera automatiquement enregistrée de manière sécurisée pour vos prochains achats. 
                                        Vous pourrez la gérer depuis votre espace client.
                                    </p>
                                </div>
<?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                        <h3 class="text-xl font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-sticky-note text-blue-primary mr-3"></i>
                            Notes de commande (optionnel)
                        </h3>

                        <textarea name="order_notes" 
                                  id="order_notes"
                                  rows="4"
                                  placeholder="Ajoutez des notes ou instructions particulières pour votre commande..."
                                  class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors resize-none"></textarea>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                        <h3 class="text-xl font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-file-contract text-blue-primary mr-3"></i>
                            Conditions générales
                        </h3>

                        <div class="space-y-4">
                            <label class="flex items-start space-x-3">
                                <input type="checkbox" 
                                       name="accept_terms" 
                                       id="accept_terms"
                                       required
                                       class="w-5 h-5 bg-dark-700 border border-dark-600 rounded text-blue-primary focus:ring-blue-primary focus:ring-2 mt-1">
                                <span class="text-sm text-gray-300">
                                    J'accepte les <a href="/legal/terms" target="_blank" class="text-blue-primary hover:underline">conditions générales de vente</a> 
                                    et la <a href="/legal/privacy" target="_blank" class="text-blue-primary hover:underline">politique de confidentialité</a>
                                    <span class="text-red-400">*</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <div class="lg:col-span-4">
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 sticky top-24">
                    <h3 class="text-xl font-semibold text-white mb-6">
                        <i class="fas fa-shopping-cart text-blue-primary mr-2"></i>
                        Votre commande
                    </h3>

                    <div id="order-summary">
                        <div class="animate-pulse space-y-3">
                            <div class="h-4 bg-dark-600 rounded"></div>
                            <div class="h-4 bg-dark-600 rounded w-3/4"></div>
                            <div class="h-4 bg-dark-600 rounded w-1/2"></div>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-dark-600">
                        <button id="place-order-btn" 
                                type="submit"
                                form="checkout-form"
                                class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 disabled:opacity-50 disabled:cursor-not-allowed"
                                disabled>
                            <i class="fas fa-lock mr-2"></i>
                            <span id="place-order-text">Chargement...</span>
                        </button>

                        <div class="mt-4 text-center">
                            <p class="text-xs text-gray-400">
                                <i class="fas fa-shield-check text-green-400 mr-1"></i>
                                Paiement 100% sécurisé SSL
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>


<div id="loading-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-primary mx-auto mb-4"></div>
        <h3 class="text-lg font-semibold text-white mb-2">Traitement en cours...</h3>
        <p class="text-gray-400" id="loading-text">Veuillez patienter pendant que nous traitons votre commande</p>
    </div>
</div>

<div id="secure-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 text-center max-w-md">
        <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-shield-alt text-green-400 text-2xl"></i>
        </div>
        <h3 class="text-xl font-semibold text-white mb-2">Authentification 3D Secure</h3>
        <p class="text-gray-400 mb-4">Une vérification supplémentaire est requise par votre banque pour sécuriser ce paiement.</p>
        <p class="text-sm text-gray-500">Une nouvelle fenêtre va s'ouvrir pour l'authentification. Ne fermez pas cette page.</p>
    </div>
</div>

<?php if ($stripeConfig): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script>
const CONFIG = {
    currentUser: <?= $currentUser ? json_encode($currentUser) : 'null' ?>,
    stripePublishableKey: <?= $stripeConfig ? json_encode($stripeConfig['publishable_key']) : 'null' ?>,
    paymentAdapters: <?= json_encode($paymentAdapters) ?>,
    billingAddresses: <?= json_encode($billingAddresses) ?>
};

const STATE = {
    cartData: null,
    hasSubscriptions: false,
    isLoading: false,
    selectedPaymentMethod: 'stripe',
    stripe: null,
    cardElement: null,
    savedCards: [],
    selectedCard: 'new'
};

function formatPrice(price) {
    return parseFloat(price).toFixed(2);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showLoadingModal(show, text = null) {
    const modal = document.getElementById('loading-modal');
    const loadingText = document.getElementById('loading-text');

    if (text) {
        loadingText.textContent = text;
    } else {
        loadingText.textContent = 'Veuillez patienter pendant que nous traitons votre commande';
    }

    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function show3DSecureModal(show) {
    const modal = document.getElementById('secure-modal');
    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
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
        case 'warning':
            notification.className += ' bg-orange-500';
            break;
        default:
            notification.className += ' bg-blue-primary';
    }

    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : type === 'warning' ? 'exclamation-triangle' : 'info'}-circle"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => notification.classList.remove('translate-x-full'), 100);

    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            if (document.body.contains(notification)) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 5000);
}

async function updatePaymentStatusImmediately(orderId, paymentIntent) {
    try {
        const requestData = {
            order_id: parseInt(orderId),
            payment_intent_id: paymentIntent.id,
            payment_status: paymentIntent.status,
            payment_data: {
                id: paymentIntent.id,
                status: paymentIntent.status,
                amount: paymentIntent.amount,
                currency: paymentIntent.currency,
                created: paymentIntent.created
            }
        };

        const response = await fetch('/api/v1/orders/update-payment-status', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(requestData)
        });

        if (!response.ok) {
            const errorText = await response.text();
            throw new Error(`Erreur HTTP ${response.status}: ${errorText}`);
        }

        const result = await response.json();

        if (result.success) {
            showNotification('Paiement confirmé et statut mis à jour !', 'success');
            return true;
        } else {
            showNotification('Paiement réussi mais erreur de mise à jour du statut', 'warning');
            return false;
        }

    } catch (error) {
        showNotification('Paiement réussi mais erreur de mise à jour', 'warning');
        return false;
    }
}

async function forceStatusCheckViaExistingAPI(orderNumber) {
    try {
        const response = await fetch(`/api/v1/orders/status.php?order=${orderNumber}&force_check=1`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        const result = await response.json();

        if (result.success && result.updated) {
            showNotification('Statut de paiement mis à jour !', 'success');
            return true;
        }

        return false;

    } catch (error) {
        return false;
    }
}

async function handlePaymentResult(paymentIntent, orderNumber, orderId) {
    if (paymentIntent.status === 'succeeded') {
        showLoadingModal(true, 'Paiement confirmé, finalisation...');

        const updateSuccess = await updatePaymentStatusImmediately(orderId, paymentIntent);

        if (!updateSuccess) {
            await forceStatusCheckViaExistingAPI(orderNumber);
        }

        await new Promise(resolve => setTimeout(resolve, 1000));

        window.location.href = `/orders/detail?order=${orderId}&payment=success`;

    } else if (paymentIntent.status === 'processing') {
        showLoadingModal(true, 'Paiement en cours de validation...');

        await updatePaymentStatusImmediately(orderId, paymentIntent);

        window.location.href = `/orders/detail?order=${orderId}&payment=processing`;

    } else if (paymentIntent.status === 'requires_action') {
        show3DSecureModal(true);
        await new Promise(resolve => setTimeout(resolve, 3000));
        show3DSecureModal(false);
        showLoadingModal(true, 'Finalisation du paiement...');

        window.location.href = `/orders/detail?order=${orderId}&payment=action_required`;

    } else {
        throw new Error('Le paiement n\'a pas pu être finalisé');
    }
}

document.addEventListener('DOMContentLoaded', async function() {
    await loadCartData();
    setupBillingAddressHandlers();
    setupPaymentMethodHandlers();
    setupFormValidation();

    if (CONFIG.stripePublishableKey) {
        await initializeStripe();
    }
});

async function loadCartData() {
    try {
        const response = await fetch('/api/v1/cart/get');
        const result = await response.json();

        if (result.success) {
            STATE.cartData = result;
            STATE.hasSubscriptions = result.summary?.has_subscriptions || false;
            renderOrderSummary();
            validateForm();
        } else {
            throw new Error(result.error || 'Erreur lors du chargement du panier');
        }
    } catch (error) {
        showNotification('Erreur lors du chargement du panier', 'error');
        setTimeout(() => window.location.href = '/cart', 2000);
    }
}

async function removeCouponFromCheckout() {
    if (STATE.isLoading) return;

    if (!confirm('Êtes-vous sûr de vouloir retirer ce code promo ?')) {
        return;
    }

    try {
        STATE.isLoading = true;
        showLoadingModal(true, 'Retrait du code promo...');

        const response = await fetch('/api/v1/cart/coupon', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Code promo retiré', 'info');
            await loadCartData();
        } else {
            throw new Error(result.error || 'Erreur lors du retrait du coupon');
        }

    } catch (error) {
        showNotification(error.message, 'error');
    } finally {
        STATE.isLoading = false;
        showLoadingModal(false);
    }
}

function renderOrderSummary() {
    const summaryContainer = document.getElementById('order-summary');

    if (!STATE.cartData || !STATE.cartData.items || STATE.cartData.items.length === 0) {
        summaryContainer.innerHTML = `
            <div class="text-center py-8">
                <p class="text-gray-400">Votre panier est vide</p>
                <a href="/products" class="text-blue-primary hover:underline">Continuer vos achats</a>
            </div>
        `;
        return;
    }

    const items = STATE.cartData.items;
    const summary = STATE.cartData.summary;
    const appliedCoupon = STATE.cartData.applied_coupon;

    const itemsHTML = items.map(item => {
        let priceDisplay = `${formatPrice(item.unit_price)}€`;
        let priceDetails = '';

        if (item.billing_info && item.billing_info.is_subscription) {
            const billingInfo = item.billing_info;
            priceDisplay += billingInfo.billing_suffix || '/mois';

            if (billingInfo.monthly_equivalent_price && billingInfo.billing_cycle !== 'monthly') {
                priceDetails = `<div class="text-xs text-gray-500">≈ ${formatPrice(billingInfo.monthly_equivalent_price)}€/mois</div>`;
            }

            if (billingInfo.is_commitment) {
                priceDetails += `<div class="text-xs text-yellow-400"><i class="fas fa-lock mr-1"></i>Engagement</div>`;
            }
        }

        return `
            <div class="flex items-center justify-between py-3 border-b border-dark-600 last:border-b-0">
                <div class="flex items-center space-x-3">
                    <img src="${item.main_image}" 
                         alt="${escapeHtml(item.product_name)}"
                         class="w-12 h-12 object-cover rounded-lg">
                    <div>
                        <div class="font-medium text-white text-sm">${escapeHtml(item.product_name)}</div>
                        <div class="text-xs text-gray-400">
                            ${item.billing_info?.is_subscription ? 
                                `${item.billing_info.billing_label}` :
                                `Quantité: ${item.quantity}`
                            }
                        </div>
                    </div>
                </div>
                <div class="text-right">
                    <div class="font-medium text-white text-sm">
                        ${priceDisplay}
                    </div>
                    ${priceDetails}
                </div>
            </div>
        `;
    }).join('');

    let summaryHTML = `
        <div class="space-y-3 mb-6">
            ${itemsHTML}
        </div>

        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-400">Sous-total</span>
                <span class="text-white">${formatPrice(summary.subtotal)}€</span>
            </div>
    `;

    if (appliedCoupon && appliedCoupon.code) {
        const discountAmount = summary.discount_amount || 0;
        const discountType = appliedCoupon.discount_type;
        const discountValue = appliedCoupon.discount_value;

        let discountLabel = '';
        if (discountType === 'percentage') {
            discountLabel = `Code promo (${discountValue}%)`;
        } else {
            discountLabel = `Code promo`;
        }

        summaryHTML += `
            <div class="bg-green-500/10 border border-green-500/30 rounded-lg p-3 -mx-1">
                <div class="flex items-center justify-between mb-2">
                    <div class="flex items-center space-x-2">
                        <i class="fas fa-tag text-green-400"></i>
                        <span class="text-green-400 font-medium text-xs">CODE PROMO ACTIF</span>
                    </div>
                    <button onclick="removeCouponFromCheckout()" 
                            class="text-red-400 hover:text-red-300 text-xs transition-colors"
                            title="Retirer le code promo">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-white font-bold">${escapeHtml(appliedCoupon.code)}</div>
                        <div class="text-green-300 text-xs">${discountLabel}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-green-400 font-bold">-${formatPrice(discountAmount)}€</div>
                        ${discountType === 'percentage' ? 
                            `<div class="text-green-300 text-xs">${formatPrice((discountAmount / summary.subtotal) * 100)}% économisés</div>` : 
                            ''
                        }
                    </div>
                </div>
            </div>
        `;
    }

    summaryHTML += `
            ${summary.setup_fees > 0 ? `
            <div class="flex justify-between">
                <span class="text-gray-400">Frais d'installation</span>
                <span class="text-yellow-400">${formatPrice(summary.setup_fees)}€</span>
            </div>
            ` : ''}

            <div class="flex justify-between">
                <span class="text-gray-400">TVA (20%)</span>
                <span class="text-white">${formatPrice(summary.tax_amount)}€</span>
            </div>

            <div class="border-t border-dark-600 pt-2 mt-2">
                <div class="flex justify-between text-lg font-bold">
                    <span class="text-white">Total initial</span>
                    <span class="text-blue-primary">${formatPrice(summary.initial_total)}€</span>
                </div>
            </div>

            ${summary.has_subscriptions ? generateRecurringDisplayHTML(summary) : ''}
        </div>
    `;

    summaryHTML += `
        <div class="mt-4 pt-4 border-t border-dark-600">
            <a href="/cart" 
               class="text-blue-primary hover:text-blue-secondary text-sm transition-colors flex items-center justify-center">
                <i class="fas fa-arrow-left mr-2"></i>
                Modifier mon panier
            </a>
        </div>
    `;

    summaryContainer.innerHTML = summaryHTML;
}

function generateRecurringDisplayHTML(summary) {
    if (!summary.recurring_total || summary.recurring_total <= 0) return '';

    return `
        <div class="bg-purple-500/10 border border-purple-500/30 rounded-lg p-3 mt-4">
            <div class="text-purple-400 font-medium text-sm mb-1">
                <i class="fas fa-sync-alt mr-1"></i>
                Montant récurrent
            </div>
            <div class="text-purple-300 text-sm">
                ${formatPrice(summary.recurring_total)}€/mois
            </div>
        </div>
    `;
}

function setupBillingAddressHandlers() {
    const addressRadios = document.querySelectorAll('input[name="billing_address_type"]');
    const newAddressForm = document.getElementById('new-billing-address');

    addressRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'new') {
                newAddressForm.classList.remove('hidden');
                newAddressForm.querySelectorAll('input, select').forEach(field => {
                    if (field.type !== 'checkbox') {
                        field.value = field.type === 'select-one' ? 'FR' : '';
                    }
                });
            } else {
                newAddressForm.classList.add('hidden');
                const addressId = this.dataset.addressId;
                const selectedAddress = CONFIG.billingAddresses.find(a => a.id == addressId);

                if (selectedAddress) {
                    document.getElementById('company_name').value = selectedAddress.company_name || '';
                    document.getElementById('first_name').value = selectedAddress.first_name || '';
                    document.getElementById('last_name').value = selectedAddress.last_name || '';
                    document.getElementById('address_line1').value = selectedAddress.address_line1 || '';
                    document.getElementById('address_line2').value = selectedAddress.address_line2 || '';
                    document.getElementById('postal_code').value = selectedAddress.postal_code || '';
                    document.getElementById('city').value = selectedAddress.city || '';
                    document.getElementById('country').value = selectedAddress.country || 'FR';
                    document.getElementById('phone').value = selectedAddress.phone || '';
                }
            }
            validateForm();
        });
    });
}

function setupPaymentMethodHandlers() {
    const paymentRadios = document.querySelectorAll('input[name="payment_method"]');
    const stripeDetails = document.getElementById('stripe-payment-details');

    paymentRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            STATE.selectedPaymentMethod = this.value;

            if (this.value === 'stripe') {
                stripeDetails?.classList.remove('hidden');
            } else {
                stripeDetails?.classList.add('hidden');
            }

            validateForm();
        });
    });
}

async function initializeStripe() {
    if (!CONFIG.stripePublishableKey) return;

    try {
        STATE.stripe = Stripe(CONFIG.stripePublishableKey);

        const elements = STATE.stripe.elements({
            appearance: {
                theme: 'night',
                variables: {
                    colorPrimary: '#0ea5e9',
                    colorBackground: '#1f2937',
                    colorText: '#ffffff',
                    colorDanger: '#ef4444'
                }
            }
        });

        STATE.cardElement = elements.create('card', {
            style: {
                base: {
                    fontSize: '16px',
                    color: '#ffffff',
                    '::placeholder': {
                        color: '#9ca3af'
                    }
                }
            }
        });

        STATE.cardElement.mount('#card-element');

        STATE.cardElement.on('change', function(event) {
            const errorElement = document.getElementById('card-errors');
            if (event.error) {
                errorElement.textContent = event.error.message;
                errorElement.classList.remove('hidden');
            } else {
                errorElement.textContent = '';
                errorElement.classList.add('hidden');
            }
            validateForm();
        });

        if (CONFIG.currentUser) {
            await loadSavedCards();
        }

    } catch (error) {
        console.error('Erreur initialisation Stripe:', error);
    }
}

async function loadSavedCards() {
    try {
        const response = await fetch('/api/v1/payments/stripe/saved-cards');
        const result = await response.json();

        if (result.success && result.cards.length > 0) {
            STATE.savedCards = result.cards;
            renderSavedCards();
        }

    } catch (error) {
        console.error('Erreur chargement cartes:', error);
    }
}

function renderSavedCards() {
    const container = document.getElementById('saved-cards-container');
    if (!container || STATE.savedCards.length === 0) return;

    const cardsHTML = STATE.savedCards.map(card => `
        <label class="flex items-center justify-between p-3 bg-dark-600/50 rounded-lg border border-dark-500 hover:border-blue-primary/50 cursor-pointer transition-all mb-2">
            <div class="flex items-center">
                <input type="radio" 
                       name="card-choice" 
                       value="${card.id}" 
                       ${card.is_default ? 'checked' : ''} 
                       class="mr-3 text-blue-primary focus:ring-blue-primary">
                <div class="flex items-center space-x-3">
                    <div class="text-lg">${getCardIcon(card.brand)}</div>
                    <div>
                        <div class="text-white font-medium">
                            ${card.brand} •••• ${card.last4}
                            ${card.is_default ? '<span class="bg-blue-primary text-white text-xs px-2 py-1 rounded-full ml-2">Par défaut</span>' : ''}
                        </div>
                        <div class="text-gray-400 text-sm">${card.exp_month}/${card.exp_year}</div>
                    </div>
                </div>
            </div>
            <button type="button" 
                    onclick="deleteSavedCard('${card.id}', event)" 
                    class="text-red-400 hover:text-red-300 text-sm p-1">
                <i class="fas fa-trash"></i>
            </button>
        </label>
    `).join('');

    container.innerHTML = `
        <h5 class="text-white font-medium mb-3">Cartes enregistrées</h5>
        ${cardsHTML}
        <label class="flex items-center p-3 bg-dark-600/50 rounded-lg border border-dark-500 hover:border-blue-primary/50 cursor-pointer transition-all">
            <input type="radio" 
                   name="card-choice" 
                   value="new" 
                   ${STATE.savedCards.length === 0 ? 'checked' : ''} 
                   class="mr-3 text-blue-primary focus:ring-blue-primary">
            <div>
                <div class="text-white font-medium">Nouvelle carte</div>
                <div class="text-gray-400 text-sm">Utiliser une nouvelle carte bancaire</div>
            </div>
        </label>
    `;

    container.classList.remove('hidden');

    const cardRadios = container.querySelectorAll('input[name="card-choice"]');
    cardRadios.forEach(radio => {
        radio.addEventListener('change', handleCardSelection);
    });

    const defaultCard = STATE.savedCards.find(card => card.is_default);
    STATE.selectedCard = defaultCard ? defaultCard.id : 'new';
    handleCardSelection({ target: { value: STATE.selectedCard } });
}

function handleCardSelection(event) {
    STATE.selectedCard = event.target.value;

    const newCardContainer = document.getElementById('new-card-container');

    if (STATE.selectedCard === 'new') {
        newCardContainer.style.display = 'block';
        if (STATE.cardElement) {
            STATE.cardElement.focus();
        }
    } else {
        newCardContainer.style.display = 'none';
        const errorElement = document.getElementById('card-errors');
        errorElement.textContent = '';
        errorElement.classList.add('hidden');
    }

    validateForm();
}

function getCardIcon(brand) {
    const icons = {
        'visa': '💳',
        'mastercard': '💳', 
        'amex': '💳',
        'discover': '💳'
    };
    return icons[brand.toLowerCase()] || '💳';
}

async function deleteSavedCard(cardId, event) {
    event.stopPropagation();

    if (!confirm('Êtes-vous sûr de vouloir supprimer cette carte ?')) {
        return;
    }

    try {
        const response = await fetch('/api/v1/payments/stripe/saved-cards', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ payment_method_id: cardId })
        });

        const result = await response.json();

        if (result.success) {
            await loadSavedCards();
            showNotification('Carte supprimée', 'success');
        } else {
            throw new Error(result.error);
        }

    } catch (error) {
        showNotification('Erreur lors de la suppression', 'error');
    }
}

function setupFormValidation() {
    const form = document.getElementById('checkout-form');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!validateForm()) {
            showNotification('Veuillez remplir tous les champs requis', 'error');
            return;
        }

        await processCheckout();
    });

    form.querySelectorAll('input, select, textarea').forEach(field => {
        field.addEventListener('input', validateForm);
        field.addEventListener('change', validateForm);
    });
}

function validateForm() {
    const submitBtn = document.getElementById('place-order-btn');
    const submitText = document.getElementById('place-order-text');

    let isValid = true;

    if (!STATE.cartData || STATE.cartData.items.length === 0) {
        isValid = false;
    }

    const addressType = document.querySelector('input[name="billing_address_type"]:checked')?.value;
    if (addressType === 'new' || !addressType) {
        const requiredFields = ['first_name', 'last_name', 'address_line1', 'postal_code', 'city', 'country'];
        requiredFields.forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field && !field.value.trim()) {
                isValid = false;
                field.classList.add('border-red-500');
            } else if (field) {
                field.classList.remove('border-red-500');
            }
        });
    }

    if (!document.getElementById('accept_terms').checked) {
        isValid = false;
    }

    if (STATE.selectedPaymentMethod === 'stripe') {
        if (STATE.selectedCard === 'new') {
            if (!STATE.cardElement) {
                isValid = false;
            }
        } else {
            const selectedCardRadio = document.querySelector('input[name="card-choice"]:checked');
            if (!selectedCardRadio || selectedCardRadio.value === 'new') {
                isValid = false;
            }
        }
    }

    submitBtn.disabled = !isValid;

    if (STATE.cartData?.summary?.initial_total) {
        const summary = STATE.cartData.summary;
        let buttonText = `Payer ${formatPrice(summary.initial_total)}€`;

        if (summary.has_subscriptions && summary.recurring_total > 0) {
            const items = STATE.cartData.items.filter(item => item.billing_info?.is_subscription);
            if (items.length === 1) {
                const item = items[0];
                const suffix = item.billing_info.billing_suffix || '/mois';
                buttonText += ` puis ${formatPrice(item.unit_price)}€${suffix}`;
            } else if (summary.recurring_total) {
                buttonText += ` puis ~${formatPrice(summary.recurring_total)}€/mois`;
            }
        }

        submitText.textContent = buttonText;
    } else {
        submitText.textContent = 'Finaliser la commande';
    }

    return isValid;
}

async function processCheckout() {
    if (STATE.isLoading) return;

    try {
        STATE.isLoading = true;
        showLoadingModal(true);

        const formData = new FormData(document.getElementById('checkout-form'));

        const addressType = formData.get('billing_address_type');
        let billingAddress = {};

        if (addressType && addressType.startsWith('existing_')) {
            const addressId = addressType.replace('existing_', '');
            const existingAddress = CONFIG.billingAddresses.find(a => a.id == addressId);
            billingAddress = existingAddress || {};
            billingAddress.type = 'existing';
        } else {
            billingAddress = {
                type: 'new',
                company_name: formData.get('company_name') || '',
                first_name: formData.get('first_name'),
                last_name: formData.get('last_name'),
                address_line1: formData.get('address_line1'),
                address_line2: formData.get('address_line2') || '',
                postal_code: formData.get('postal_code'),
                city: formData.get('city'),
                country: formData.get('country'),
                phone: formData.get('phone') || ''
            };
        }

        const orderResponse = await fetch('/api/v1/checkout/create-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                billing_address: billingAddress,
                payment_method: STATE.selectedPaymentMethod,
                order_notes: formData.get('order_notes') || '',
                save_address: formData.get('save_address') === '1',
                save_card: true
            })
        });

        const orderResult = await orderResponse.json();

        if (!orderResult.success) {
            throw new Error(orderResult.error || 'Erreur lors de la création de la commande');
        }

        if (!orderResult.order_id) {
            throw new Error('Erreur: ID de commande manquant');
        }

        if (STATE.selectedPaymentMethod === 'stripe' && orderResult.client_secret) {
            const billingData = {
                name: `${billingAddress.first_name} ${billingAddress.last_name}`,
                email: CONFIG.currentUser?.email || '',
                address: {
                    line1: billingAddress.address_line1,
                    line2: billingAddress.address_line2 || null,
                    city: billingAddress.city,
                    postal_code: billingAddress.postal_code,
                    country: billingAddress.country,
                    state: billingAddress.country === 'US' ? billingAddress.state : null
                }
            };

            const savedCardSelected = document.querySelector('input[name="card-choice"]:checked');
            let paymentMethodId = null;

            if (savedCardSelected && savedCardSelected.value !== 'new') {
                paymentMethodId = savedCardSelected.value;

                showLoadingModal(true, 'Traitement du paiement...');

                const confirmResult = await STATE.stripe.confirmCardPayment(orderResult.client_secret, {
                    payment_method: paymentMethodId
                });

                if (confirmResult.error) {
                    throw new Error(confirmResult.error.message);
                }

                await handlePaymentResult(confirmResult.paymentIntent, orderResult.order_number, orderResult.order_id);

            } else {
                const {error: methodError, paymentMethod} = await STATE.stripe.createPaymentMethod({
                    type: 'card',
                    card: STATE.cardElement,
                    billing_details: billingData
                });

                if (methodError) {
                    throw new Error(methodError.message);
                }

                showLoadingModal(true, 'Vérification de votre carte bancaire...');

                const confirmResult = await STATE.stripe.confirmCardPayment(orderResult.client_secret, {
                    payment_method: paymentMethod.id,
                    setup_future_usage: CONFIG.currentUser ? 'off_session' : null
                });

                if (confirmResult.error) {
                    throw new Error(confirmResult.error.message);
                }

                if (CONFIG.currentUser && confirmResult.paymentIntent.status === 'succeeded') {
                    try {
                        await fetch('/api/v1/payments/stripe/saved-cards', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'save_card',
                                payment_method_id: paymentMethod.id,
                                set_as_default: false
                            })
                        });
                    } catch (error) {
                        console.error('Erreur sauvegarde carte:', error);
                    }
                }

                await handlePaymentResult(confirmResult.paymentIntent, orderResult.order_number, orderResult.order_id);
            }
        } else {
            window.location.href = `/orders/detail?order=${orderResult.order_id}&payment=pending`;
        }

    } catch (error) {
        showNotification(error.message || 'Erreur lors du traitement de la commande', 'error');
    } finally {
        STATE.isLoading = false;
        showLoadingModal(false);
    }
}
</script>

<style>
.border-red-500 {
    border-color: #ef4444 !important;
}

#loading-modal {
    backdrop-filter: blur(4px);
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .5;
    }
}
</style>

<?php
endHTMLPage();
?>