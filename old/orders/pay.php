<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$auth->requireAuth();
$currentUser = $auth->getCurrentUser();

$orderId = intval($_GET['order'] ?? 0);

if (!$orderId) {
    header('Location: /orders?error=' . urlencode('Commande non spécifiée'));
    exit();
}

try {
    $db = Database::getInstance();
    
        $order_stmt = $db->prepare("
        SELECT o.*, 
               pt.status as transaction_status, 
               pt.gateway_transaction_id,
               pt.metadata as transaction_metadata,
               pa.name as payment_method_name, 
               pa.slug as payment_method_slug,
               pa.id as payment_adapter_id
        FROM orders o
        LEFT JOIN payment_transactions pt ON o.id = pt.order_id
        LEFT JOIN payment_adapters pa ON pt.payment_adapter_id = pa.id
        WHERE o.id = ? AND o.user_id = ?
        ORDER BY pt.created_at DESC
        LIMIT 1
    ");
    $order_stmt->execute([$orderId, $currentUser['id']]);
    $order = $order_stmt->fetch();
    
    if (!$order) {
        header('Location: /orders?error=' . urlencode('Commande non trouvée'));
        exit();
    }
    
        if ($order['payment_status'] === 'paid') {
        header("Location: /orders/detail?order={$orderId}");
        exit();
    }
    
        if ($order['payment_status'] !== 'pending') {
        header("Location: /orders/detail?order={$orderId}&error=" . urlencode('Cette commande ne peut pas être payée'));
        exit();
    }
    
        $items_stmt = $db->prepare("
        SELECT oi.*, p.images, p.slug as product_slug
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.created_at ASC
    ");
    $items_stmt->execute([$orderId]);
    $orderItems = $items_stmt->fetchAll();
    
        foreach ($orderItems as &$item) {
        $item['images'] = json_decode($item['images'] ?? '[]', true);
        $item['main_image'] = !empty($item['images']) ? $item['images'][0] : '/assets/images/product-placeholder.jpg';
        $item['product_url'] = '/products/' . $item['product_slug'];
    }
    
        $adapters_stmt = $db->prepare("
        SELECT * FROM payment_adapters 
        WHERE is_active = 1 
        ORDER BY sort_order ASC
    ");
    $adapters_stmt->execute();
    $paymentAdapters = $adapters_stmt->fetchAll();
    
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
    
        $billingAddress = json_decode($order['billing_address'] ?? '{}', true);
    
        $expiresAt = strtotime($order['created_at']) + (30 * 24 * 60 * 60);
    $isExpired = time() > $expiresAt;
    
} catch (Exception $e) {
    error_log("Order payment error: " . $e->getMessage());
    header('Location: /orders?error=' . urlencode('Erreur lors du chargement de la commande'));
    exit();
}

$seo = [
    'title' => "Paiement commande #{$order['order_number']} - Pilote Shopping",
    'description' => 'Finalisez le paiement de votre commande en toute sécurité.',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen bg-dark-900 py-8">
    <div class="container mx-auto px-4 lg:px-6">

        <nav class="mb-8">
            <div class="flex items-center space-x-2 text-sm">
                <a href="/dashboard" class="text-gray-400 hover:text-white transition-colors">Dashboard</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <a href="/orders" class="text-gray-400 hover:text-white transition-colors">Mes commandes</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <a href="/orders/detail?order=<?= $orderId ?>" class="text-gray-400 hover:text-white transition-colors">
                    Commande #<?= htmlspecialchars($order['order_number']) ?>
                </a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <span class="text-white font-medium">Paiement</span>
            </div>
        </nav>

        <div class="text-center mb-8">
<?php if ($isExpired): ?>
                <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-clock text-red-400 text-3xl"></i>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    Commande expirée
                </h1>
                <p class="text-red-300 mb-6">
                    Le délai de paiement pour cette commande a expiré (30 jours après création).
                </p>
                <a href="/orders" class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-6 rounded-xl transition-colors">
                    Retour aux commandes
                </a>
<?php else: ?>
                <div class="w-20 h-20 bg-orange-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-credit-card text-orange-400 text-3xl"></i>
                </div>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    Finaliser le paiement
                </h1>
                <p class="text-gray-400 mb-2">
                    Commande #<?= htmlspecialchars($order['order_number']) ?>
                </p>
                <p class="text-gray-400">
                    Finalisez le paiement de votre commande pour accéder à vos produits
                </p>

                <div class="mt-6 bg-orange-500/10 border border-orange-500/30 rounded-xl p-4 max-w-md mx-auto">
                    <div class="text-orange-400 text-sm">
                        <i class="fas fa-clock mr-2"></i>
                        Temps restant pour payer : <span id="countdown" class="font-bold"></span>
                    </div>
                </div>
<?php endif; ?>
        </div>

<?php if (!$isExpired): ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2">
                <form id="payment-form" class="space-y-8">

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
<?= $adapter['slug'] === $order['payment_method_slug'] ? 'checked' : '' ?>
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

                        <div id="stripe-payment-details" class="mt-6 <?= $order['payment_method_slug'] !== 'stripe' ? 'hidden' : '' ?>">
                            <div class="bg-dark-700/50 rounded-xl p-4">
                                <h4 class="text-white font-medium mb-3 flex items-center">
                                    <i class="fas fa-credit-card mr-2"></i>
                                    Informations de carte bancaire
                                </h4>

                                <div id="card-element" class="p-3 bg-dark-800 border border-dark-600 rounded-lg">

                                </div>
                                <div id="card-errors" class="text-red-400 text-sm mt-2 hidden"></div>

                                <div class="mt-3 flex items-start space-x-2 text-xs text-gray-400">
                                    <i class="fas fa-lock mt-0.5"></i>
                                    <p>Paiement 100% sécurisé. Vos données bancaires sont protégées par le cryptage SSL.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                        <h3 class="text-xl font-semibold text-white mb-6 flex items-center">
                            <i class="fas fa-map-marker-alt text-blue-primary mr-3"></i>
                            Adresse de facturation
                        </h3>

                        <div class="bg-dark-700/50 rounded-xl p-4">
                            <div class="text-sm text-gray-300 space-y-1">
<?php if (!empty($billingAddress['company_name'])): ?>
                                    <div class="font-medium"><?= htmlspecialchars($billingAddress['company_name']) ?></div>
<?php endif; ?>

                                <div class="font-medium">
<?= htmlspecialchars($billingAddress['first_name'] . ' ' . $billingAddress['last_name']) ?>
                                </div>

                                <div><?= htmlspecialchars($billingAddress['address_line1']) ?></div>

<?php if (!empty($billingAddress['address_line2'])): ?>
                                    <div><?= htmlspecialchars($billingAddress['address_line2']) ?></div>
<?php endif; ?>

                                <div>
<?= htmlspecialchars($billingAddress['postal_code'] . ' ' . $billingAddress['city']) ?>
                                </div>

                                <div><?= htmlspecialchars($billingAddress['country']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                        <h3 class="text-xl font-semibold text-white mb-4 flex items-center">
                            <i class="fas fa-file-contract text-blue-primary mr-3"></i>
                            Confirmation
                        </h3>

                        <div class="space-y-4">
                            <label class="flex items-start space-x-3">
                                <input type="checkbox" 
                                       name="confirm_order" 
                                       id="confirm_order"
                                       required
                                       class="w-5 h-5 bg-dark-700 border border-dark-600 rounded text-blue-primary focus:ring-blue-primary focus:ring-2 mt-1">
                                <span class="text-sm text-gray-300">
                                    Je confirme que toutes les informations sont correctes et je souhaite finaliser cette commande.
                                    <span class="text-red-400">*</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>

            <div class="space-y-6">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 sticky top-24">
                    <h3 class="text-xl font-semibold text-white mb-6">
                        <i class="fas fa-shopping-cart text-blue-primary mr-2"></i>
                        Récapitulatif
                    </h3>

                    <div class="space-y-3 mb-6">
<?php foreach ($orderItems as $item): ?>
                        <div class="flex items-center space-x-3 p-3 bg-dark-700/30 rounded-lg">
                            <img src="<?= htmlspecialchars($item['main_image']) ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>"
                                 class="w-12 h-12 object-cover rounded-lg">
                            <div class="flex-1">
                                <div class="font-medium text-white text-sm">
<?= htmlspecialchars($item['product_name']) ?>
                                </div>
                                <div class="text-xs text-gray-400">
                                    Quantité: <?= $item['quantity'] ?>
                                </div>
                            </div>
                            <div class="text-white font-semibold text-sm">
<?= number_format($item['total_price'], 2) ?>€
                            </div>
                        </div>
<?php endforeach; ?>
                    </div>

                    <div class="space-y-2 text-sm border-t border-dark-600 pt-4">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Sous-total:</span>
                            <span class="text-white"><?= number_format($order['subtotal'], 2) ?>€</span>
                        </div>

<?php if ($order['discount_amount'] > 0): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Réduction:</span>
                            <span class="text-green-400">-<?= number_format($order['discount_amount'], 2) ?>€</span>
                        </div>
<?php endif; ?>

                        <div class="flex justify-between">
                            <span class="text-gray-400">Taxes:</span>
                            <span class="text-white"><?= number_format($order['tax_amount'], 2) ?>€</span>
                        </div>

                        <div class="border-t border-dark-600 pt-2 mt-2">
                            <div class="flex justify-between text-lg font-bold">
                                <span class="text-white">Total à payer:</span>
                                <span class="text-blue-primary"><?= number_format($order['total_amount'], 2) ?>€</span>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button id="pay-button" 
                                type="submit"
                                form="payment-form"
                                class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none"
                                disabled>
                            <i class="fas fa-lock mr-2"></i>
                            <span id="pay-button-text">Finaliser le paiement</span>
                        </button>

                        <div class="mt-4 text-center">
                            <p class="text-xs text-gray-400">
                                <i class="fas fa-shield-check text-green-400 mr-1"></i>
                                Paiement 100% sécurisé SSL
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        Informations commande
                    </h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Numéro:</span>
                            <span class="text-white font-mono"><?= htmlspecialchars($order['order_number']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Date:</span>
                            <span class="text-white"><?= date('d/m/Y à H:i', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Expire le:</span>
                            <span class="text-orange-400"><?= date('d/m/Y à H:i', $expiresAt) ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
<?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>


<div id="loading-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-primary mx-auto mb-4"></div>
        <h3 class="text-lg font-semibold text-white mb-2">Traitement en cours...</h3>
        <p class="text-gray-400" id="loading-text">Veuillez patienter pendant que nous traitons votre paiement</p>
    </div>
</div>

<?php if ($stripeConfig && !$isExpired): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script>
const ORDER_DATA = {
    id: <?= $orderId ?>,
    orderNumber: '<?= htmlspecialchars($order['order_number']) ?>',
    totalAmount: <?= $order['total_amount'] ?>,
    expiresAt: <?= $expiresAt * 1000 ?>, // En millisecondes
    isExpired: <?= $isExpired ? 'true' : 'false' ?>
};

const STRIPE_CONFIG = <?= $stripeConfig ? json_encode($stripeConfig) : 'null' ?>;

const STATE = {
    stripe: null,
    cardElement: null,
    isProcessing: false,
    selectedPaymentMethod: '<?= htmlspecialchars($order['payment_method_slug']) ?>'
};

document.addEventListener('DOMContentLoaded', function() {
    if (!ORDER_DATA.isExpired) {
        setupCountdown();
        setupPaymentMethodHandlers();
        setupFormValidation();

        if (STRIPE_CONFIG) {
            initializeStripe();
        }
    }
});

function setupCountdown() {
    const countdownElement = document.getElementById('countdown');
    if (!countdownElement) return;

    function updateCountdown() {
        const now = new Date().getTime();
        const timeLeft = ORDER_DATA.expiresAt - now;

        if (timeLeft <= 0) {
            countdownElement.textContent = 'Expiré';
            countdownElement.parentElement.classList.add('text-red-400');
            location.reload(); // Recharger pour afficher l'état expiré
            return;
        }

        const hours = Math.floor(timeLeft / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);

        countdownElement.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;

        if (timeLeft < 3600000) { // 1 heure en millisecondes
            countdownElement.parentElement.classList.add('text-red-400');
            countdownElement.parentElement.classList.remove('text-orange-400');
        }
    }

    updateCountdown();
    setInterval(updateCountdown, 1000);
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
    if (!STRIPE_CONFIG?.publishable_key) return;

    try {
        STATE.stripe = Stripe(STRIPE_CONFIG.publishable_key);

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

    } catch (error) {
        console.error('Erreur initialisation Stripe:', error);
    }
}

function setupFormValidation() {
    const form = document.getElementById('payment-form');

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        if (!validateForm()) {
            showNotification('Veuillez remplir tous les champs requis', 'error');
            return;
        }

        await processPayment();
    });

    form.querySelectorAll('input').forEach(field => {
        field.addEventListener('input', validateForm);
        field.addEventListener('change', validateForm);
    });
}

function validateForm() {
    const payButton = document.getElementById('pay-button');
    const confirmCheckbox = document.getElementById('confirm_order');

    let isValid = true;

    if (!confirmCheckbox.checked) {
        isValid = false;
    }

    if (STATE.selectedPaymentMethod === 'stripe' && STATE.cardElement) {
    }

    payButton.disabled = !isValid;

    if (isValid) {
        document.getElementById('pay-button-text').textContent = `Payer ${ORDER_DATA.totalAmount.toFixed(2)}€`;
    } else {
        document.getElementById('pay-button-text').textContent = 'Finaliser le paiement';
    }

    return isValid;
}

async function processPayment() {
    if (STATE.isProcessing) return;

    try {
        STATE.isProcessing = true;
        showLoadingModal(true, 'Traitement du paiement en cours...');

        if (STATE.selectedPaymentMethod === 'stripe') {
            await processStripePayment();
        } else {
            await processOtherPayment();
        }

    } catch (error) {
        console.error('Erreur paiement:', error);
        showNotification(error.message || 'Erreur lors du traitement du paiement', 'error');
    } finally {
        STATE.isProcessing = false;
        showLoadingModal(false);
    }
}

async function processStripePayment() {
    if (!STATE.stripe || !STATE.cardElement) {
        throw new Error('Stripe non initialisé');
    }

    const response = await fetch('/api/v1/orders/create-payment-intent', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            order_id: ORDER_DATA.id
        })
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Erreur lors de la création du paiement');
    }

    const {error, paymentIntent} = await STATE.stripe.confirmCardPayment(result.client_secret, {
        payment_method: {
            card: STATE.cardElement,
            billing_details: {
                name: `${ORDER_DATA.billingAddress?.first_name || ''} ${ORDER_DATA.billingAddress?.last_name || ''}`.trim()
            }
        }
    });

    if (error) {
        throw new Error(error.message);
    }

    if (paymentIntent.status === 'succeeded') {
        showLoadingModal(true, 'Paiement confirmé, redirection...');
        setTimeout(() => {
            window.location.href = `/orders/detail?order=${ORDER_DATA.id}&payment=success`;
        }, 2000);
    } else {
        throw new Error('Le paiement n\'a pas pu être finalisé');
    }
}

async function processOtherPayment() {
    const response = await fetch('/api/v1/orders/update-payment-method', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            order_id: ORDER_DATA.id,
            payment_method: STATE.selectedPaymentMethod
        })
    });

    const result = await response.json();

    if (!result.success) {
        throw new Error(result.error || 'Erreur lors de la mise à jour');
    }

    window.location.href = `/orders/detail?order=${ORDER_DATA.id}&payment=pending`;
}

function showLoadingModal(show, text = null) {
    const modal = document.getElementById('loading-modal');
    const loadingText = document.getElementById('loading-text');

    if (text) {
        loadingText.textContent = text;
    }

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
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl text-white font-medium transition-all duration-300 transform translate-x-full max-w-sm`;

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

    setTimeout(() => notification.classList.remove('translate-x-full'), 100);

    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => document.body.removeChild(notification), 300);
    }, 5000);
}

window.addEventListener('beforeunload', function() {
    if (STATE.isProcessing) {
        return 'Un paiement est en cours. Êtes-vous sûr de vouloir quitter cette page ?';
    }
});
</script>

<?php endHTMLPage(); ?>