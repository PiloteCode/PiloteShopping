<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$seo = [
    'title' => 'Mon Panier - Pilote Shopping',
    'description' => 'Finalisez votre commande et procédez au paiement sécurisé',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen bg-dark-900 py-8">
    <div class="container mx-auto px-4 lg:px-6">

        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full mb-4">
                <i class="fas fa-shopping-cart text-2xl text-white"></i>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                Mon Panier
            </h1>
            <p class="text-gray-400">Vérifiez vos articles avant de finaliser votre commande</p>
        </div>

        <div id="cart-container" class="lg:grid lg:grid-cols-12 lg:gap-8">

            <div class="lg:col-span-8 mb-8 lg:mb-0">
                <div id="cart-items" class="space-y-6">

                    <div id="cart-loading" class="space-y-6">
                        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 animate-pulse">
                            <div class="flex space-x-4">
                                <div class="w-20 h-20 bg-gradient-to-br from-dark-600 to-dark-700 rounded-xl"></div>
                                <div class="flex-1 space-y-3">
                                    <div class="h-5 bg-gradient-to-r from-dark-600 to-dark-700 rounded-lg"></div>
                                    <div class="h-4 bg-gradient-to-r from-dark-600 to-dark-700 rounded-lg w-3/4"></div>
                                    <div class="flex space-x-4">
                                        <div class="h-3 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-20"></div>
                                        <div class="h-3 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-16"></div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <div class="h-8 w-24 bg-gradient-to-r from-dark-600 to-dark-700 rounded-lg"></div>
                                    <div class="h-6 w-16 bg-gradient-to-r from-dark-600 to-dark-700 rounded-lg"></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 animate-pulse">
                            <div class="flex space-x-4">
                                <div class="w-20 h-20 bg-gradient-to-br from-dark-600 to-dark-700 rounded-xl"></div>
                                <div class="flex-1 space-y-3">
                                    <div class="h-5 bg-gradient-to-r from-dark-600 to-dark-700 rounded-lg w-4/5"></div>
                                    <div class="h-4 bg-gradient-to-r from-dark-600 to-dark-700 rounded-lg w-2/3"></div>
                                    <div class="flex space-x-4">
                                        <div class="h-3 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-24"></div>
                                        <div class="h-3 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-20"></div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <div class="h-8 w-24 bg-gradient-to-r from-dark-600 to-dark-700 rounded-lg"></div>
                                    <div class="h-6 w-16 bg-gradient-to-r from-dark-600 to-dark-700 rounded-lg"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="empty-cart" class="hidden">
                    <div class="bg-dark-800/30 backdrop-blur-sm rounded-3xl border border-dark-600 p-12 text-center">
                        <div class="w-32 h-32 mx-auto mb-8 relative">
                            <div class="w-full h-full bg-gradient-to-br from-dark-700 to-dark-600 rounded-full flex items-center justify-center">
                                <i class="fas fa-shopping-cart text-5xl text-gray-400"></i>
                            </div>
                            <div class="absolute -top-2 -right-2 w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center">
                                <i class="fas fa-times text-white text-sm"></i>
                            </div>
                        </div>
                        <h3 class="text-3xl font-bold text-white mb-4">Votre panier est vide</h3>
                        <p class="text-gray-400 mb-8 text-lg">Découvrez nos produits premium et commencez vos achats</p>
                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                            <a href="/products" class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-bold py-4 px-8 rounded-xl transition-all duration-300 transform hover:scale-105">
                                <i class="fas fa-shopping-bag mr-2"></i>Découvrir nos produits
                            </a>
                            <a href="/categories" class="bg-dark-700 hover:bg-dark-600 text-white font-medium py-4 px-8 rounded-xl transition-colors border border-dark-600">
                                <i class="fas fa-th-large mr-2"></i>Parcourir les catégories
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-4">
                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 sticky top-24">
                    <h3 class="text-xl font-semibold text-white mb-6">
                        <i class="fas fa-receipt text-blue-primary mr-2"></i>
                        Résumé de la commande
                    </h3>

                    <div id="cart-summary">
                        <div class="space-y-4 animate-pulse">
                            <div class="flex justify-between items-center">
                                <div class="h-4 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-20"></div>
                                <div class="h-4 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-16"></div>
                            </div>
                            <div class="flex justify-between items-center">
                                <div class="h-4 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-16"></div>
                                <div class="h-4 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-12"></div>
                            </div>
                            <div class="border-t border-dark-600 pt-4">
                                <div class="flex justify-between items-center">
                                    <div class="h-5 bg-gradient-to-r from-dark-600 to-dark-700 rounded w-24"></div>
                                    <div class="h-6 bg-gradient-to-r from-blue-600 to-purple-600 rounded w-20"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 pt-6 border-t border-dark-600">
                        <h4 class="text-lg font-medium text-white mb-3">
                            <i class="fas fa-tag text-green-400 mr-2"></i>
                            Code promo
                        </h4>
                        <form id="coupon-form" class="space-y-3">
                            <div class="flex space-x-2">
                                <input type="text" 
                                       id="coupon-code" 
                                       placeholder="Entrez votre code"
                                       class="flex-1 px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary focus:ring-1 focus:ring-blue-primary transition-all">
                                <button type="submit" 
                                        class="bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-medium px-6 py-3 rounded-xl transition-all duration-300 transform hover:scale-105">
                                    <i class="fas fa-tag"></i>
                                </button>
                            </div>
                            <div id="coupon-message" class="text-sm hidden rounded-lg p-2"></div>
                        </form>
                    </div>

                    <div id="checkout-warnings" class="mt-6 space-y-3 hidden"></div>

                    <div class="mt-6">
                        <button id="checkout-btn" 
                                class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white font-bold py-4 px-6 rounded-xl transition-all duration-300 transform hover:scale-105 disabled:opacity-50 disabled:cursor-not-allowed disabled:transform-none disabled:from-gray-600 disabled:to-gray-700"
                                disabled>
                            <i class="fas mr-2"></i>
                            <span id="checkout-text">Finaliser la commande</span>
                        </button>
                    </div>

                    <div class="mt-6 pt-6 border-t border-dark-600">
                        <h4 class="text-sm font-medium text-white mb-3">Pourquoi nous choisir ?</h4>
                        <div class="space-y-3">
                            <div class="flex items-center text-blue-400 text-sm">
                                <div class="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-headset text-xs"></i>
                                </div>
                                <span>Support client réactif 24h/7j</span>
                            </div>
                            <div class="flex items-center text-purple-400 text-sm">
                                <div class="w-8 h-8 bg-purple-500/20 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-undo text-xs"></i>
                                </div>
                                <span>Garantie remboursement 30j</span>
                            </div>
                            <div class="flex items-center text-yellow-400 text-sm">
                                <div class="w-8 h-8 bg-yellow-500/20 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-certificate text-xs"></i>
                                </div>
                                <span>Livraison rapide et sécurisée</span>
                            </div>
                            <div class="flex items-center text-green-400 text-sm">
                                <div class="w-8 h-8 bg-green-500/20 rounded-lg flex items-center justify-center mr-3">
                                    <i class="fas fa-star text-xs"></i>
                                </div>
                                <span>Plus de 10K clients satisfaits</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="wishlist-section" class="mt-16 hidden">
            <div class="bg-dark-800/30 backdrop-blur-sm rounded-2xl border border-dark-600 p-8">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-2xl font-bold text-white">
                        <i class="fas fa-heart text-red-400 mr-3"></i>
                        Vous avez peut-être oublié...
                    </h3>
                    <a href="/wishlist" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">
                        Voir tout
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </div>
                <p class="text-gray-400 mb-6">Ces produits de votre liste de souhaits sont toujours disponibles</p>
                <div id="wishlist-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
            </div>
        </div>

        <div id="recommended-products" class="mt-16 hidden">
            <div class="text-center mb-8">
                <h3 class="text-2xl font-bold text-white mb-4">
                    Produits recommandés pour vous
                </h3>
                <p class="text-gray-400">Découvrez d'autres produits qui pourraient vous intéresser</p>
            </div>
            <div id="recommended-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6"></div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>


<script>
let cartData = null;
let isLoading = false;
let currentUser = <?= $currentUser ? json_encode($currentUser) : 'null' ?>;

document.addEventListener('DOMContentLoaded', function() {
    loadCart();
    setupEventListeners();
    if (currentUser) {
        loadWishlistItems();
    }
});

function setupEventListeners() {
    document.getElementById('coupon-form').addEventListener('submit', function(e) {
        e.preventDefault();
        applyCoupon();
    });

    document.getElementById('checkout-btn').addEventListener('click', function() {
        if (!this.disabled) {
            proceedToCheckout();
        }
    });
}

async function loadCart() {
    try {
        isLoading = true;
        showLoading(true);

        const response = await fetch('/api/v1/cart/get', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const result = await response.json();

        if (result.success) {
            cartData = result;
            console.log('Cart data loaded:', cartData);

            if (result.applied_coupon && isExpired(result.applied_coupon)) {
                await removeCoupon();
                return;
            }

            renderCart();
            updateSummary();
            checkCheckoutEligibility();
            loadRecommendedProducts();
        } else {
            throw new Error(result.error || 'Erreur lors du chargement du panier');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors du chargement du panier', 'error');
    } finally {
        isLoading = false;
        showLoading(false);
    }
}

function isExpired(coupon) {
    if (!coupon || !coupon.expires_at) return false;
    const expiresAt = new Date(coupon.expires_at);
    const now = new Date();
    return now > expiresAt;
}

function showLoading(show) {
    const loading = document.getElementById('cart-loading');
    if (loading) {
        loading.style.display = show ? 'block' : 'none';
    }
}

function renderCart() {
    const itemsContainer = document.getElementById('cart-items');
    const emptyCart = document.getElementById('empty-cart');

    if (!cartData.items || cartData.items.length === 0) {
        itemsContainer.innerHTML = '';
        emptyCart.classList.remove('hidden');
        return;
    }

    emptyCart.classList.add('hidden');

    const itemsHTML = cartData.items.map(item => createCartItemHTML(item)).join('');
    itemsContainer.innerHTML = itemsHTML;
}

function createCartItemHTML(item) {
    const billingCycle = item.billing_cycle || 'one_time';
    const isSubscription = billingCycle !== 'one_time';
    const hasError = !item.available;
    const isUnavailable = hasError;

    let mainImage = '/assets/images/product-placeholder.jpg';
    if (item.product_images) {
        try {
            const images = JSON.parse(item.product_images);
            if (images && images.length > 0) {
                mainImage = images[0];
            }
        } catch (e) {
            console.warn('Erreur parsing images:', e);
        }
    }

    const billingInfo = getBillingDisplayInfo(billingCycle, item.unit_price);
    const priceDisplay = formatPriceWithCycle(item.unit_price, billingCycle);

    let selectedBoosters = [];
    if (item.selected_boosters) {
        try {
            selectedBoosters = Array.isArray(item.selected_boosters) ? 
                item.selected_boosters : 
                JSON.parse(item.selected_boosters);
        } catch (e) {
            console.warn('Erreur parsing boosters:', e);
        }
    }

    return `
        <div class="cart-item bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 transition-all duration-300 hover:border-blue-primary/50 hover:shadow-lg hover:shadow-blue-primary/10 ${hasError ? 'border-red-500/30 bg-red-500/5' : ''}" data-item-id="${item.id}">
            <div class="flex flex-col lg:flex-row lg:items-center lg:space-x-6">

                <div class="flex items-start space-x-4 lg:flex-1 mb-4 lg:mb-0">
                    <div class="relative group">
                        <img src="${mainImage}" 
                             alt="${escapeHtml(item.product_name)}"
                             class="w-20 h-20 object-cover rounded-xl transition-all duration-300 ${isUnavailable ? 'grayscale opacity-50' : 'group-hover:scale-105'}">

                        <div class="absolute -top-2 -right-2">
                            <span class="text-white text-xs font-medium px-2 py-1 rounded-full backdrop-blur-sm border transition-all duration-300" 
                                  style="background-color: ${item.category_color || '#0EA5E9'}33; border-color: ${item.category_color || '#0EA5E9'}; color: ${item.category_color || '#0EA5E9'}">
                                <i class="${item.category_icon || 'fas fa-cube'}"></i>
                            </span>
                        </div>

                        ${isSubscription ? `
                        <div class="absolute -bottom-1 -right-1">
                            <span class="bg-gradient-to-r ${getSubscriptionBadgeColor(billingCycle)} text-white text-xs font-bold px-2 py-1 rounded-full shadow-lg">
                                <i class="fas ${getSubscriptionIcon(billingCycle)}"></i>
                            </span>
                        </div>
                        ` : ''}
                    </div>

                    <div class="flex-1">
                        <h3 class="font-semibold text-white mb-1 transition-colors ${isUnavailable ? 'text-gray-400' : ''}">
                            <a href="/products/${item.product_slug}" class="hover:text-blue-primary transition-colors">
                                ${escapeHtml(item.product_name)}
                            </a>
                        </h3>

                        <div class="text-sm text-gray-400 mb-2 space-y-1">
                            <div class="flex items-center space-x-4">
                                <span class="flex items-center bg-dark-700/50 px-2 py-1 rounded-lg">
                                    <i class="${item.access_type_icon || 'fas fa-key'} mr-1"></i>
                                    ${escapeHtml(item.access_type_name || 'Type d\'accès')}
                                </span>
                                <span class="flex items-center bg-blue-500/20 text-blue-400 px-2 py-1 rounded-lg">
                                    <i class="fas fa-tag mr-1"></i>
                                    ${escapeHtml(item.offer_name || 'Offre standard')}
                                </span>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="flex items-center text-green-400">
                                    <i class="fas fa-shipping-fast mr-1"></i>
                                    ${getDeliveryMethodLabel(item.default_delivery_method)}
                                </span>
                                ${item.access_method ? `
                                <span class="flex items-center">
                                    <i class="fas fa-user-circle mr-1"></i>
                                    ${getAccessMethodLabel(item.access_method)}
                                </span>
                                ` : ''}
                            </div>
                        </div>

                        ${isSubscription ? createSubscriptionInfoHTML(item, billingInfo) : ''}
                        ${selectedBoosters.length > 0 ? createBoostersInfoHTML(selectedBoosters, item) : ''}

                        ${hasError ? `
                        <div class="bg-red-500/10 border border-red-500/30 rounded-lg p-3 mt-2 animation-shake">
                            <div class="flex items-center text-red-400 text-sm">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                <span>${escapeHtml(item.error || 'Produit non disponible')}</span>
                            </div>
                        </div>
                        ` : ''}

                        ${(!item.is_available || item.available_stock <= 5) && item.available_stock > 0 ? `
                        <div class="bg-orange-500/10 border border-orange-500/30 rounded-lg p-2 mt-2">
                            <div class="flex items-center text-orange-400 text-sm">
                                <i class="fas fa-exclamation-circle mr-2"></i>
                                <span>Stock limité : ${item.available_stock} restant${item.available_stock > 1 ? 's' : ''}</span>
                            </div>
                        </div>
                        ` : ''}
                    </div>
                </div>

                <div class="flex items-center justify-between lg:justify-end lg:space-x-6">

                    ${!isSubscription && !isUnavailable ? `
                    <div class="flex items-center space-x-3">
                        <button onclick="updateQuantity(${item.id}, ${item.quantity - 1})" 
                                class="w-10 h-10 bg-dark-700 hover:bg-dark-600 text-white rounded-lg transition-all duration-300 hover:scale-110 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                                ${item.quantity <= 1 ? 'disabled' : ''}>
                            <i class="fas fa-minus"></i>
                        </button>

                        <input type="number" 
                               value="${item.quantity}" 
                               min="1" 
                               max="10"
                               onchange="updateQuantity(${item.id}, this.value)"
                               class="w-16 text-center bg-dark-700 border border-dark-600 rounded-lg text-white py-2 focus:outline-none focus:border-blue-primary focus:ring-1 focus:ring-blue-primary transition-all">

                        <button onclick="updateQuantity(${item.id}, ${item.quantity + 1})" 
                                class="w-10 h-10 bg-dark-700 hover:bg-dark-600 text-white rounded-lg transition-all duration-300 hover:scale-110 flex items-center justify-center disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:scale-100"
                                ${item.quantity >= 10 || item.available_stock <= item.quantity ? 'disabled' : ''}>
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    ` : `
                    <div class="text-sm text-gray-400 bg-dark-700/50 px-3 py-2 rounded-lg">
                        ${isSubscription ? billingInfo.label : `Quantité: ${item.quantity}`}
                    </div>
                    `}

                    <div class="text-right">
                        <div class="text-xl font-bold text-white ${isUnavailable ? 'text-gray-400' : ''}">
                            ${formatPrice(item.unit_price)}€
                            ${isSubscription && billingInfo.suffix ? `<span class="text-sm font-normal ${priceDisplay.color}">${billingInfo.suffix}</span>` : ''}
                        </div>

                        ${priceDisplay.equivalent ? `
                        <div class="text-sm text-gray-400">
                            ${priceDisplay.equivalent}
                        </div>
                        ` : ''}

                        ${item.setup_fee > 0 ? `
                        <div class="text-sm text-yellow-400">
                            + ${formatPrice(item.setup_fee)}€ installation
                        </div>
                        ` : ''}

                        ${(item.booster_amount > 0) ? `
                        <div class="text-sm text-orange-400">
                            + ${formatPrice(item.booster_amount)}€ boosters
                        </div>
                        ` : ''}

                        ${item.quantity > 1 && !isSubscription ? `
                        <div class="text-sm text-gray-400">
                            Total: ${formatPrice(item.total_price)}€
                        </div>
                        ` : ''}
                    </div>

                    <div class="flex flex-col space-y-2">
                        <button onclick="removeItem(${item.id})" 
                                class="w-10 h-10 bg-red-500/20 hover:bg-red-500/30 text-red-400 rounded-lg transition-all duration-300 hover:scale-110 flex items-center justify-center group">
                            <i class="fas fa-trash group-hover:fa-trash-alt"></i>
                        </button>

                        ${currentUser ? `
                        <button onclick="moveToWishlist(${item.id}, ${item.product_id})" 
                                class="w-10 h-10 bg-pink-500/20 hover:bg-pink-500/30 text-pink-400 rounded-lg transition-all duration-300 hover:scale-110 flex items-center justify-center group"
                                title="Déplacer vers la wishlist">
                            <i class="fas fa-heart group-hover:fa-heart-broken"></i>
                        </button>
                        ` : ''}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function updateSummary() {
    const summaryContainer = document.getElementById('cart-summary');

    if (!cartData || !cartData.summary) {
        summaryContainer.innerHTML = '<p class="text-gray-400 text-center">Chargement...</p>';
        return;
    }

    const summary = cartData.summary;
    const appliedCoupon = cartData.applied_coupon;

    let html = '<div class="space-y-4">';

    html += `
        <div class="flex justify-between items-center">
            <span class="text-gray-400">Sous-total (${summary.available_items} article${summary.available_items > 1 ? 's' : ''})</span>
            <span class="text-white font-medium">${formatPrice(summary.subtotal)}€</span>
        </div>
    `;

    if (summary.setup_fees > 0) {
        html += `
            <div class="flex justify-between items-center">
                <span class="text-gray-400">Frais d'installation</span>
                <span class="text-yellow-400 font-medium">${formatPrice(summary.setup_fees)}€</span>
            </div>
        `;
    }

    if (summary.boosters_amount > 0) {
        html += `
            <div class="flex justify-between items-center">
                <span class="text-gray-400">Boosters</span>
                <span class="text-orange-400 font-medium">${formatPrice(summary.boosters_amount)}€</span>
            </div>
        `;
    }

    if (appliedCoupon && summary.discount_amount > 0) {
        html += `
            <div class="flex justify-between items-center bg-green-500/10 border border-green-500/30 rounded-lg p-3 -mx-3">
                <div class="flex items-center">
                    <i class="fas fa-tag text-green-400 mr-2"></i>
                    <span class="text-green-400 font-medium">${appliedCoupon.code}</span>
                </div>
                <span class="text-green-400 font-bold">-${formatPrice(summary.discount_amount)}€</span>
            </div>
        `;
    }

    html += `
        <div class="border-t border-dark-600 pt-4">
            <div class="flex justify-between items-center">
                <span class="text-white text-lg font-semibold">Total</span>
                <span class="text-2xl font-bold bg-gradient-to-r from-blue-400 to-purple-400 bg-clip-text text-transparent">
                    ${formatPrice(summary.total_amount)}€
                </span>
            </div>
        </div>
    `;

    if (summary.has_subscriptions) {
        html += generateSubscriptionSummaryHTML(summary);
    }

    html += '</div>';

    summaryContainer.innerHTML = html;
}

function generateSubscriptionSummaryHTML(summary) {
    let html = `
        <div class="bg-gradient-to-r from-purple-500/10 to-pink-500/10 border border-purple-500/30 rounded-xl p-4 mt-4">
            <div class="text-purple-400 font-medium mb-3 flex items-center">
                <i class="fas fa-sync-alt mr-2"></i>
                Abonnements inclus
            </div>
            <div class="space-y-2">
    `;

    if (summary.recurring_monthly_total && summary.recurring_monthly_total > 0) {
        html += `
            <div class="flex justify-between items-center">
                <span class="text-purple-300 text-sm">Mensuel</span>
                <span class="text-purple-400 font-bold">${formatPrice(summary.recurring_monthly_total)}€/mois</span>
            </div>
        `;
    }

    if (summary.recurring_quarterly_total && summary.recurring_quarterly_total > 0) {
        const monthlyEquiv = summary.recurring_quarterly_total / 3;
        html += `
            <div class="flex justify-between items-center">
                <span class="text-blue-300 text-sm">Trimestriel</span>
                <div class="text-right">
                    <span class="text-blue-400 font-bold">${formatPrice(summary.recurring_quarterly_total)}€/trimestre</span>
                    <div class="text-xs text-blue-300 opacity-80">équiv. ${formatPrice(monthlyEquiv)}€/mois</div>
                </div>
            </div>
        `;
    }

    if (summary.recurring_semi_yearly_total && summary.recurring_semi_yearly_total > 0) {
        const monthlyEquiv = summary.recurring_semi_yearly_total / 6;
        html += `
            <div class="flex justify-between items-center">
                <span class="text-cyan-300 text-sm">Semestriel</span>
                <div class="text-right">
                    <span class="text-cyan-400 font-bold">${formatPrice(summary.recurring_semi_yearly_total)}€/semestre</span>
                    <div class="text-xs text-cyan-300 opacity-80">équiv. ${formatPrice(monthlyEquiv)}€/mois</div>
                </div>
            </div>
        `;
    }

    if (summary.recurring_yearly_total && summary.recurring_yearly_total > 0) {
        const monthlyEquiv = summary.recurring_yearly_total / 12;
        html += `
            <div class="flex justify-between items-center">
                <span class="text-green-300 text-sm">Annuel</span>
                <div class="text-right">
                    <span class="text-green-400 font-bold">${formatPrice(summary.recurring_yearly_total)}€/an</span>
                    <div class="text-xs text-green-300 opacity-80">équiv. ${formatPrice(monthlyEquiv)}€/mois</div>
                </div>
            </div>
        `;
    }

    if (summary.commitment_total && summary.commitment_total > 0) {
        html += `
            <div class="flex justify-between items-center">
                <span class="text-orange-300 text-sm">Engagements</span>
                <span class="text-orange-400 font-bold">${formatPrice(summary.commitment_total)}€/mois</span>
            </div>
        `;
    }

    const totalRecurring = summary.recurring_total || summary.recurring_monthly_total || 0;
    if (totalRecurring > 0) {
        html += `
            <div class="border-t border-purple-500/30 pt-2 mt-2">
                <div class="flex justify-between items-center">
                    <span class="text-purple-300 font-medium">Total mensuel équivalent</span>
                    <span class="text-purple-400 font-bold text-lg">${formatPrice(totalRecurring)}€/mois</span>
                </div>
            </div>
        `;
    }

    html += `
            </div>
            <div class="text-xs text-purple-300 mt-3 opacity-80">
                <i class="fas fa-info-circle mr-1"></i>
                Les abonnements seront activés après le paiement initial
            </div>
        </div>
    `;

    return html;
}

function getBillingDisplayInfo(cycle, price) {
    const labels = {
        'one_time': 'Achat unique',
        'monthly': 'Mensuel',
        'quarterly': 'Trimestriel',
        'semi_yearly': 'Semestriel',
        'yearly': 'Annuel',
        'commitment_3': 'Engagement 3 mois',
        'commitment_6': 'Engagement 6 mois',
        'commitment_12': 'Engagement 12 mois',
        'commitment_24': 'Engagement 24 mois'
    };

    const suffixes = {
        'one_time': '',
        'monthly': '/mois',
        'quarterly': '/trimestre',
        'semi_yearly': '/semestre',
        'yearly': '/an', 
        'commitment_3': '/mois',
        'commitment_6': '/mois',
        'commitment_12': '/mois',
        'commitment_24': '/mois'
    };

    return {
        label: labels[cycle] || 'Inconnu',
        suffix: suffixes[cycle] || '',
        isCommitment: cycle.startsWith('commitment_'),
        isSubscription: cycle !== 'one_time'
    };
}

function formatPriceWithCycle(price, cycle) {
    const floatPrice = parseFloat(price || 0);
    const billingInfo = getBillingDisplayInfo(cycle, price);

    let main = formatPrice(floatPrice) + '€';
    let suffix = billingInfo.suffix;
    let equivalent = null;
    let color = 'text-purple-400';

    switch (cycle) {
        case 'quarterly':
            equivalent = `Équiv. ${formatPrice(floatPrice / 3)}€/mois`;
            color = 'text-blue-400';
            break;
        case 'semi_yearly':
            equivalent = `Équiv. ${formatPrice(floatPrice / 6)}€/mois`;
            color = 'text-cyan-400';
            break;
        case 'yearly':
            equivalent = `Équiv. ${formatPrice(floatPrice / 12)}€/mois`;
            color = 'text-green-400';
            break;
        case 'commitment_3':
        case 'commitment_6':
        case 'commitment_12':
        case 'commitment_24':
            color = 'text-orange-400';
            break;
    }

    return { main, suffix, equivalent, color };
}

function getSubscriptionBadgeColor(cycle) {
    const colors = {
        'monthly': 'from-purple-500 to-pink-500',
        'quarterly': 'from-blue-500 to-cyan-500', 
        'semi_yearly': 'from-cyan-500 to-teal-500',
        'yearly': 'from-green-500 to-emerald-500',
        'commitment_3': 'from-orange-500 to-red-500',
        'commitment_6': 'from-orange-500 to-red-500',
        'commitment_12': 'from-orange-500 to-red-500',
        'commitment_24': 'from-orange-500 to-red-500'
    };
    return colors[cycle] || 'from-purple-500 to-pink-500';
}

function getSubscriptionIcon(cycle) {
    const icons = {
        'monthly': 'fa-sync-alt',
        'quarterly': 'fa-calendar-alt',
        'semi_yearly': 'fa-calendar-check',
        'yearly': 'fa-calendar-week',
        'commitment_3': 'fa-lock',
        'commitment_6': 'fa-lock',
        'commitment_12': 'fa-lock',
        'commitment_24': 'fa-lock'
    };
    return icons[cycle] || 'fa-sync-alt';
}

function createSubscriptionInfoHTML(item, billingInfo) {
    const hasTrialDays = (item.offer_trial_days || 0) > 0;
    const trialDays = item.offer_trial_days || 0;

    return `
        <div class="bg-gradient-to-r from-purple-500/10 to-pink-500/10 border border-purple-500/30 rounded-lg p-3 mt-2">
            <div class="text-sm space-y-2">
                <div class="text-purple-400 font-medium">
                    <i class="fas ${getSubscriptionIcon(item.billing_cycle)} mr-2"></i>
                    ${billingInfo.label}
                </div>

                <div class="grid grid-cols-2 gap-2">
                    ${hasTrialDays ? `
                    <div class="text-green-400 flex items-center">
                        <i class="fas fa-gift mr-2 text-xs"></i>
                        <span>${trialDays} jour${trialDays > 1 ? 's' : ''} gratuit${trialDays > 1 ? 's' : ''}</span>
                    </div>
                    ` : ''}

                    <div class="${billingInfo.isCommitment ? 'text-orange-400' : 'text-green-400'} flex items-center">
                        <i class="fas fa-${billingInfo.isCommitment ? 'lock' : 'check'} mr-2 text-xs"></i>
                        <span>${billingInfo.isCommitment ? 'Avec engagement' : 'Sans engagement'}</span>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function createBoostersInfoHTML(boosters, item) {
    if (!boosters || boosters.length === 0) return '';

    return `
        <div class="bg-gradient-to-r from-orange-500/10 to-yellow-500/10 border border-orange-500/30 rounded-lg p-3 mt-2">
            <div class="text-sm">
                <div class="text-orange-400 font-medium mb-2">
                    <i class="fas fa-rocket mr-2"></i>
                    Boosters sélectionnés (${boosters.length})
                </div>
                <div class="space-y-1 text-orange-300">
                    ${boosters.map(booster => `
                        <div class="flex items-center justify-between bg-orange-500/10 rounded px-2 py-1">
                            <span class="text-xs">• ${escapeHtml(booster.name || 'Booster')}</span>
                            <span class="text-xs font-medium">+${formatPrice(booster.calculated_price || 0)}€</span>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
}

function checkCheckoutEligibility() {
    const checkoutBtn = document.getElementById('checkout-btn');
    const checkoutText = document.getElementById('checkout-text');
    const warningsContainer = document.getElementById('checkout-warnings');

    let canCheckout = true;
    let warnings = [];

    if (!cartData.items || cartData.items.length === 0) {
        canCheckout = false;
        checkoutText.textContent = 'Panier vide';
    } else if (cartData.summary?.has_unavailable_items) {
        canCheckout = false;
        checkoutText.textContent = 'Articles indisponibles';
        warnings.push({
            type: 'error',
            message: 'Veuillez supprimer les articles indisponibles avant de continuer'
        });
    } else if (cartData.summary?.available_items === 0) {
        canCheckout = false;
        checkoutText.textContent = 'Aucun article disponible';
    } else {
        checkoutText.innerHTML = '<i class="fas fa-lock mr-2"></i>Finaliser la commande';

        if (cartData.summary?.has_subscriptions && !currentUser) {
            canCheckout = false;
            checkoutText.innerHTML = '<i class="fas fa-user mr-2"></i>Connexion requise';
            warnings.push({
                type: 'warning',
                message: 'Vous devez être connecté pour commander des abonnements'
            });
        }

        if (cartData.summary?.setup_fees > 0) {
            warnings.push({
                type: 'info',
                message: 'Les frais d\'installation ne sont facturés qu\'une seule fois'
            });
        }

        if (cartData.summary?.boosters_amount > 0) {
            warnings.push({
                type: 'success',
                message: 'Vos boosters amélioreront significativement votre expérience'
            });
        }
    }

    checkoutBtn.disabled = !canCheckout;

    if (warnings.length > 0) {
        const warningsHTML = warnings.map(warning => `
            <div class="flex items-start space-x-3 p-3 rounded-lg ${getWarningClasses(warning.type)} transition-all duration-300 hover:scale-105">
                <i class="fas fa-${getWarningIcon(warning.type)} mt-0.5 flex-shrink-0"></i>
                <span class="text-sm">${escapeHtml(warning.message)}</span>
            </div>
        `).join('');

        warningsContainer.innerHTML = warningsHTML;
        warningsContainer.classList.remove('hidden');
    } else {
        warningsContainer.classList.add('hidden');
    }
}

function getWarningClasses(type) {
    switch (type) {
        case 'error': return 'bg-red-500/10 border border-red-500/30 text-red-400';
        case 'warning': return 'bg-orange-500/10 border border-orange-500/30 text-orange-400';
        case 'info': return 'bg-blue-500/10 border border-blue-500/30 text-blue-400';
        case 'success': return 'bg-green-500/10 border border-green-500/30 text-green-400';
        default: return 'bg-gray-500/10 border border-gray-500/30 text-gray-400';
    }
}

function getWarningIcon(type) {
    switch (type) {
        case 'error': return 'exclamation-triangle';
        case 'warning': return 'exclamation-circle';
        case 'info': return 'info-circle';
        case 'success': return 'check-circle';
        default: return 'info';
    }
}

async function updateQuantity(itemId, newQuantity) {
    if (isLoading) return;

    newQuantity = parseInt(newQuantity);
    if (newQuantity < 0 || newQuantity > 10) return;

    try {
        isLoading = true;

        const response = await fetch('/api/v1/cart/update', {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart_item_id: itemId,
                quantity: newQuantity
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(newQuantity === 0 ? 'Produit retiré du panier' : 'Quantité mise à jour', 'success');
            await loadCart();
        } else {
            throw new Error(result.error || 'Erreur lors de la mise à jour');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
    } finally {
        isLoading = false;
    }
}

async function removeItem(itemId) {
    if (isLoading) return;

    if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
        return;
    }

    try {
        isLoading = true;

        const response = await fetch('/api/v1/cart/remove', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                cart_item_id: itemId
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Produit supprimé du panier', 'success');
            await loadCart();
        } else {
            throw new Error(result.error || 'Erreur lors de la suppression');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
    } finally {
        isLoading = false;
    }
}

async function applyCoupon() {
    const codeInput = document.getElementById('coupon-code');
    const code = codeInput.value.trim().toUpperCase();

    if (!code) {
        showCouponMessage('Veuillez entrer un code promo', 'error');
        return;
    }

    const submitBtn = document.querySelector('#coupon-form button[type="submit"]');
    const originalHTML = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    submitBtn.disabled = true;

    try {
        const response = await fetch('/api/v1/cart/coupon', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                coupon_code: code
            })
        });

        const result = await response.json();

        if (result.success) {
            showCouponMessage(
                `✅ Code promo appliqué ! Vous économisez ${formatPrice(result.data?.discount_amount || 0)}€ 
                (${result.data?.savings_percentage || 0}% de réduction)`, 
                'success'
            );

            codeInput.value = code;
            codeInput.disabled = true;
            submitBtn.style.display = 'none';

            const removeBtnHTML = `
                <button type="button" 
                        onclick="removeCoupon()" 
                        id="remove-coupon-btn"
                        class="bg-red-500/20 hover:bg-red-500/30 text-red-400 font-medium px-4 py-3 rounded-xl transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-times mr-2"></i>Retirer
                </button>
            `;

            submitBtn.insertAdjacentHTML('afterend', removeBtnHTML);
            await loadCart();
        } else {
            showCouponMessage(`❌ ${result.error || 'Code promo invalide'}`, 'error');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showCouponMessage('❌ Erreur lors de l\'application du code', 'error');
    } finally {
        submitBtn.innerHTML = originalHTML;
        submitBtn.disabled = false;
    }
}

async function removeCoupon() {
    const removeBtnEl = document.getElementById('remove-coupon-btn');
    if (!removeBtnEl) return;

    const originalHTML = removeBtnEl.innerHTML;
    removeBtnEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    removeBtnEl.disabled = true;

    try {
        const response = await fetch('/api/v1/cart/coupon', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Code promo retiré', 'info');

            const codeInput = document.getElementById('coupon-code');
            const submitBtn = document.querySelector('#coupon-form button[type="submit"]');
            const messageDiv = document.getElementById('coupon-message');

            codeInput.value = '';
            codeInput.disabled = false;
            submitBtn.style.display = 'block';
            removeBtnEl.remove();
            messageDiv.classList.add('hidden');

            await loadCart();
        } else {
            throw new Error(result.error || 'Erreur lors du retrait du coupon');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors du retrait du coupon', 'error');
        removeBtnEl.innerHTML = originalHTML;
        removeBtnEl.disabled = false;
    }
}

function showCouponMessage(message, type) {
    const messageDiv = document.getElementById('coupon-message');
    messageDiv.innerHTML = message;

    messageDiv.className = `text-sm p-3 rounded-lg transition-all duration-300 ${
        type === 'success' 
            ? 'text-green-400 bg-green-500/10 border border-green-500/30' 
            : 'text-red-400 bg-red-500/10 border border-red-500/30'
    }`;

    messageDiv.classList.remove('hidden');

    setTimeout(() => {
        messageDiv.style.transform = 'scale(1.05)';
        setTimeout(() => {
            messageDiv.style.transform = 'scale(1)';
        }, 150);
    }, 50);

    setTimeout(() => {
        messageDiv.classList.add('hidden');
        messageDiv.style.transform = '';
    }, 8000);
}

function proceedToCheckout() {
    if (!cartData.summary?.can_checkout) {
        showNotification('Impossible de procéder au checkout', 'error');
        return;
    }

    const checkoutBtn = document.getElementById('checkout-btn');
    checkoutBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Redirection...';
    checkoutBtn.disabled = true;

    setTimeout(() => {
        window.location.href = '/checkout';
    }, 1000);
}

async function loadRecommendedProducts() {
    try {
        const response = await fetch('/api/v1/products/recommended?source=cart', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const result = await response.json();

        if (result.success && result.products && result.products.length > 0) {
            renderRecommendedProducts(result.products);
            document.getElementById('recommended-products').classList.remove('hidden');
        }

    } catch (error) {
        console.error('Erreur lors du chargement des produits recommandés:', error);
    }
}

function renderRecommendedProducts(products) {
    const grid = document.getElementById('recommended-grid');

    const productsHTML = products.slice(0, 4).map(product => `
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 overflow-hidden hover:border-blue-primary/50 transition-all duration-300 hover:transform hover:scale-105 group">
            <div class="relative h-48 bg-gradient-to-br from-dark-700 to-dark-800 overflow-hidden">
                <img src="${product.main_image || '/assets/images/product-placeholder.jpg'}" 
                     alt="${escapeHtml(product.name)}"
                     class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-110">
                <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                <div class="absolute top-3 right-3">
                    <span class="text-white text-xs font-medium px-2 py-1 rounded-full backdrop-blur-sm" 
                          style="background-color: ${product.category_color || '#0EA5E9'}33; border: 1px solid ${product.category_color || '#0EA5E9'}">
                        <i class="${product.type_icon || 'fas fa-cube'}"></i>
                    </span>
                </div>
                ${product.is_featured ? `
                <div class="absolute top-3 left-3">
                    <span class="bg-gradient-to-r from-yellow-400 to-orange-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                        <i class="fas fa-star mr-1"></i>Premium
                    </span>
                </div>
                ` : ''}
            </div>

            <div class="p-6">
                <h4 class="text-lg font-semibold text-white mb-2 line-clamp-1 group-hover:text-blue-400 transition-colors">
                    ${escapeHtml(product.name)}
                </h4>
                <p class="text-gray-400 text-sm mb-4 line-clamp-2">
                    ${escapeHtml(product.short_description || 'Découvrez ce produit exceptionnel')}
                </p>
                <div class="flex items-center justify-between">
                    <div class="text-xl font-bold text-white">
                        À partir de
                        <span class="text-blue-400">${formatPrice(product.min_price || product.base_price || 0)}€</span>
                    </div>
                    <a href="/products/${product.slug}" 
                       class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-medium px-4 py-2 rounded-lg transition-all duration-300 text-sm transform hover:scale-105">
                        Voir
                        <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
        </div>
    `).join('');

    grid.innerHTML = productsHTML;
}

async function loadWishlistItems() {
    if (!currentUser) return;

    try {
        const response = await fetch('/api/v1/wishlist/available', {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });

        const result = await response.json();

        if (result.success && result.items && result.items.length > 0) {
            renderWishlistItems(result.items);
            document.getElementById('wishlist-section').classList.remove('hidden');
        }

    } catch (error) {
        console.error('Erreur lors du chargement de la wishlist:', error);
    }
}

function renderWishlistItems(items) {
    const grid = document.getElementById('wishlist-grid');

    const itemsHTML = items.slice(0, 3).map(item => `
        <div class="bg-dark-700/50 backdrop-blur-sm rounded-xl border border-dark-600 overflow-hidden hover:border-pink-primary/50 transition-all duration-300 hover:transform hover:scale-105">
            <div class="relative h-32 bg-gradient-to-br from-dark-600 to-dark-700">
                <img src="${item.main_image || '/assets/images/product-placeholder.jpg'}" 
                     alt="${escapeHtml(item.product_name)}"
                     class="w-full h-full object-cover">
                <div class="absolute top-2 right-2">
                    <span class="text-white text-xs font-medium px-2 py-1 rounded-full backdrop-blur-sm" 
                          style="background-color: ${item.category_color || '#0EA5E9'}33; border: 1px solid ${item.category_color || '#0EA5E9'}">
                        <i class="${item.type_icon || 'fas fa-cube'}"></i>
                    </span>
                </div>
                <div class="absolute top-2 left-2">
                    <span class="bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full">
                        <i class="fas fa-check mr-1"></i>En stock
                    </span>
                </div>
            </div>

            <div class="p-4">
                <h4 class="font-semibold text-white mb-2 line-clamp-1">
                    ${escapeHtml(item.product_name)}
                </h4>
                <p class="text-gray-400 text-sm mb-3 line-clamp-2">
                    ${escapeHtml(item.short_description || 'Description non disponible')}
                </p>
                <div class="flex items-center justify-between">
                    <span class="text-lg font-bold text-white">
                        À partir de ${formatPrice(item.min_price || item.base_price || 0)}€
                    </span>
                    <div class="flex space-x-2">
                        <button onclick="addToCartFromWishlist(${item.product_id}, ${item.min_offer_id || item.default_offer_id})" 
                                class="bg-blue-primary hover:bg-blue-secondary text-white font-medium px-3 py-1 rounded-lg transition-colors text-sm">
                            <i class="fas fa-plus mr-1"></i>Ajouter
                        </button>
                        <button onclick="removeFromWishlist(${item.id})" 
                                class="bg-red-500/20 hover:bg-red-500/30 text-red-400 font-medium px-2 py-1 rounded-lg transition-colors text-sm">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `).join('');

    grid.innerHTML = itemsHTML;
}

async function addToCartFromWishlist(productId, offerId) {
    try {
        const response = await fetch('/api/v1/cart/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId,
                product_offer_id: offerId,
                quantity: 1
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Produit ajouté au panier depuis la wishlist !', 'success');
            await loadCart();
        } else {
            throw new Error(result.error || 'Erreur lors de l\'ajout au panier');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
    }
}

async function removeFromWishlist(itemId) {
    try {
        const response = await fetch('/api/v1/wishlist/remove', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                item_id: itemId
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Retiré de la wishlist', 'success');
            await loadWishlistItems();
        } else {
            throw new Error(result.error || 'Erreur lors de la suppression');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
    }
}

async function moveToWishlist(cartItemId, productId) {
    if (!currentUser) {
        showNotification('Vous devez être connecté pour utiliser la wishlist', 'warning');
        return;
    }

    try {
        const addResponse = await fetch('/api/v1/wishlist/add', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                product_id: productId
            })
        });

        const addResult = await addResponse.json();

        if (addResult.success) {
            await removeItem(cartItemId);
            showNotification('Produit déplacé vers votre wishlist', 'success');
            await loadWishlistItems();
        } else {
            throw new Error(addResult.error || 'Erreur lors de l\'ajout à la wishlist');
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message, 'error');
    }
}

function getDeliveryMethodLabel(method) {
    const labels = {
        'automatic': 'Instantané ⚡',
        'semi_automatic': 'Quelques minutes',
        'manual_24h': 'Sous 24h',
        'manual_48h': 'Sous 48h',
        'manual_72h': 'Sous 72h',
        'manual_1week': 'Sous 1 semaine'
    };
    return labels[method] || 'Standard';
}

function getAccessMethodLabel(method) {
    const labels = {
        'no_connection': 'Sans connexion',
        'account_required': 'Compte requis',
        'license_key': 'Clé de licence',
        'api_access': 'Accès API',
        'download_link': 'Téléchargement'
    };
    return labels[method] || 'Standard';
}

function formatPrice(price) {
    return parseFloat(price || 0).toFixed(2);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl text-white font-medium transition-all duration-300 transform translate-x-full shadow-lg backdrop-blur-sm`;

    const colors = {
        success: 'bg-gradient-to-r from-green-500 to-emerald-600',
        error: 'bg-gradient-to-r from-red-500 to-rose-600',
        warning: 'bg-gradient-to-r from-orange-500 to-yellow-600',
        info: 'bg-gradient-to-r from-blue-500 to-purple-600'
    };

    const icons = {
        success: 'check-circle',
        error: 'exclamation-circle',
        warning: 'exclamation-triangle',
        info: 'info-circle'
    };

    notification.className += ` ${colors[type] || colors.info}`;

    notification.innerHTML = `
        <div class="flex items-center space-x-3">
            <i class="fas fa-${icons[type] || icons.info} text-lg"></i>
            <span>${message}</span>
            <button onclick="this.parentElement.parentElement.remove()" class="ml-2 hover:bg-white/20 rounded p-1 transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);

    setTimeout(() => {
        if (notification.parentElement) {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (notification.parentElement) {
                    document.body.removeChild(notification);
                }
            }, 300);
        }
    }, duration);
}
</script>

<style>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

@keyframes shimmer {
    0% { background-position: -1000px 0; }
    100% { background-position: 1000px 0; }
}

.animate-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

.animate-pulse > div {
    background: linear-gradient(90deg, #1f2937 25%, #374151 50%, #1f2937 75%);
    background-size: 1000px 100%;
    animation: shimmer 2s infinite;
}

.cart-item {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.cart-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 20px 25px -5px rgba(14, 165, 233, 0.1), 0 10px 10px -5px rgba(14, 165, 233, 0.04);
}

@keyframes shake {
    0%, 20%, 40%, 60%, 80%, 100% { transform: translateX(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-2px); }
}

.animation-shake {
    animation: shake 0.5s ease-in-out;
}

button:disabled {
    opacity: 0.5 !important;
    cursor: not-allowed !important;
    transform: none !important;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

#checkout-warnings > div {
    animation: slideInDown 0.3s ease-out;
}

.gradient-text {
    background: linear-gradient(135deg, #3b82f6, #8b5cf6);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

input:focus, button:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.group:hover img {
    filter: brightness(1.1);
}

::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: #1f2937;
}

::-webkit-scrollbar-thumb {
    background: #374151;
    border-radius: 4px;
}

::-webkit-scrollbar-thumb:hover {
    background: #4b5563;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification-enter {
    animation: slideInRight 0.3s ease-out;
}

.premium-shine {
    position: relative;
    overflow: hidden;
}

.premium-shine::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.premium-shine:hover::before {
    left: 100%;
}

@media (max-width: 768px) {
    .cart-item {
        margin-bottom: 1rem;
    }

    .cart-item:hover {
        transform: translateY(-2px);
    }
}
</style>

<?php
endHTMLPage();
?>