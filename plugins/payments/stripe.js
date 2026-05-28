/**
 * Stripe Client Integration - Pilote Shopping
 * Gestion des paiements, cartes sauvegardées et abonnements
 */

// Configuration Stripe
let stripe = null;
let cardElement = null;
let currentUser = null;
let savedCards = [];
let selectedCard = null;

// Initialisation Stripe
async function initializeStripe() {
    try {
        // Récupérer la clé publique
        const configResponse = await fetch('/api/v1/payments/stripe/config');
        const config = await configResponse.json();
        
        if (!config.success) {
            throw new Error('Configuration Stripe non disponible');
        }
        
        // Initialiser Stripe
        stripe = Stripe(config.publishable_key);
        
        // Charger les cartes sauvegardées si utilisateur connecté
        if (currentUser) {
            await loadSavedCards();
        }
        
        // Créer les éléments de carte
        createCardElements();
        
        console.log('Stripe initialisé avec succès');
        
    } catch (error) {
        console.error('Erreur initialisation Stripe:', error);
        showStripeError('Erreur de configuration Stripe');
    }
}

// Créer les éléments de carte Stripe
function createCardElements() {
    if (!stripe) return;
    
    const elements = stripe.elements({
        appearance: {
            theme: 'night',
            variables: {
                colorPrimary: '#0ea5e9',
                colorBackground: '#1f2937',
                colorText: '#ffffff',
                colorDanger: '#ef4444',
                fontFamily: 'system-ui, sans-serif',
                spacingUnit: '4px',
                borderRadius: '8px'
            }
        }
    });
    
    // Créer l'élément de carte
    cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '16px',
                color: '#ffffff',
                '::placeholder': {
                    color: '#9ca3af'
                }
            },
            invalid: {
                color: '#ef4444',
                iconColor: '#ef4444'
            }
        },
        hidePostalCode: true
    });
    
    // Monter l'élément
    const cardContainer = document.getElementById('card-element');
    if (cardContainer) {
        cardElement.mount('#card-element');
        
        // Écouter les changements
        cardElement.on('change', handleCardChange);
    }
}

// Gérer les changements de carte
function handleCardChange(event) {
    const errorElement = document.getElementById('card-errors');
    
    if (event.error) {
        errorElement.textContent = event.error.message;
        errorElement.classList.remove('hidden');
    } else {
        errorElement.textContent = '';
        errorElement.classList.add('hidden');
    }
    
    // Mettre à jour l'état du formulaire
    if (typeof validateForm === 'function') {
        validateForm();
    }
}

// Charger les cartes sauvegardées
async function loadSavedCards() {
    try {
        const response = await fetch('/api/v1/payments/stripe/saved-cards');
        const result = await response.json();
        
        if (result.success) {
            savedCards = result.cards || [];
            renderSavedCards();
        }
        
    } catch (error) {
        console.error('Erreur chargement cartes:', error);
    }
}

// Afficher les cartes sauvegardées
function renderSavedCards() {
    const container = document.getElementById('saved-cards-container');
    if (!container || savedCards.length === 0) return;
    
    const cardsHTML = savedCards.map(card => createSavedCardHTML(card)).join('');
    
    container.innerHTML = `
        <div class="mb-4">
            <h4 class="text-white font-medium mb-3">Cartes enregistrées</h4>
            <div class="space-y-2">
                ${cardsHTML}
                <label class="flex items-center p-3 bg-dark-700/50 rounded-lg border border-dark-600 hover:border-blue-primary/50 cursor-pointer transition-all">
                    <input type="radio" name="card-choice" value="new" checked class="mr-3">
                    <div>
                        <div class="text-white font-medium">Nouvelle carte</div>
                        <div class="text-gray-400 text-sm">Utiliser une nouvelle carte bancaire</div>
                    </div>
                </label>
            </div>
        </div>
    `;
    
    // Ajouter les événements
    const cardRadios = container.querySelectorAll('input[name="card-choice"]');
    cardRadios.forEach(radio => {
        radio.addEventListener('change', handleCardSelection);
    });
    
    container.classList.remove('hidden');
}

// Créer le HTML d'une carte sauvegardée
function createSavedCardHTML(card) {
    const cardBrand = getCardBrandIcon(card.brand);
    
    return `
        <label class="flex items-center justify-between p-3 bg-dark-700/50 rounded-lg border border-dark-600 hover:border-blue-primary/50 cursor-pointer transition-all">
            <div class="flex items-center">
                <input type="radio" name="card-choice" value="${card.id}" class="mr-3">
                <div class="flex items-center space-x-3">
                    <div class="text-xl">${cardBrand}</div>
                    <div>
                        <div class="text-white font-medium">•••• •••• •••• ${card.last4}</div>
                        <div class="text-gray-400 text-sm">${card.exp_month}/${card.exp_year}</div>
                    </div>
                </div>
            </div>
            <button type="button" onclick="deleteSavedCard('${card.id}')" class="text-red-400 hover:text-red-300 text-sm">
                <i class="fas fa-trash"></i>
            </button>
        </label>
    `;
}

// Obtenir l'icône de la marque de carte
function getCardBrandIcon(brand) {
    const icons = {
        'visa': '💳',
        'mastercard': '💳',
        'amex': '💳',
        'discover': '💳',
        'diners': '💳',
        'jcb': '💳',
        'unionpay': '💳'
    };
    
    return icons[brand] || '💳';
}

// Gérer la sélection de carte
function handleCardSelection(event) {
    selectedCard = event.target.value;
    
    const cardElementContainer = document.getElementById('card-element').parentElement;
    
    if (selectedCard === 'new') {
        cardElementContainer.style.display = 'block';
        if (cardElement) {
            cardElement.focus();
        }
    } else {
        cardElementContainer.style.display = 'none';
    }
    
    if (typeof validateForm === 'function') {
        validateForm();
    }
}

// Supprimer une carte sauvegardée
async function deleteSavedCard(cardId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette carte ?')) {
        return;
    }
    
    try {
        const response = await fetch('/api/v1/payments/stripe/delete-card', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ payment_method_id: cardId })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Recharger les cartes
            await loadSavedCards();
            showNotification('Carte supprimée', 'success');
        } else {
            throw new Error(result.error);
        }
        
    } catch (error) {
        console.error('Erreur suppression carte:', error);
        showNotification('Erreur lors de la suppression', 'error');
    }
}

// Traiter le paiement Stripe
async function processStripePayment(checkoutData) {
    try {
        if (!stripe) {
            throw new Error('Stripe non initialisé');
        }
        
        let paymentMethodId = null;
        
        if (selectedCard && selectedCard !== 'new') {
            // Utiliser une carte sauvegardée
            paymentMethodId = selectedCard;
        } else {
            // Créer une nouvelle méthode de paiement
            const {error, paymentMethod} = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: `${checkoutData.billing_address.first_name} ${checkoutData.billing_address.last_name}`,
                    email: currentUser?.email || '',
                    address: {
                        line1: checkoutData.billing_address.address_line1,
                        line2: checkoutData.billing_address.address_line2,
                        city: checkoutData.billing_address.city,
                        postal_code: checkoutData.billing_address.postal_code,
                        country: checkoutData.billing_address.country
                    }
                }
            });
            
            if (error) {
                throw new Error(error.message);
            }
            
            paymentMethodId = paymentMethod.id;
        }
        
        // Créer l'ordre et obtenir le client secret
        const orderResponse = await fetch('/api/v1/checkout/create-order', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                ...checkoutData,
                payment_method: 'stripe',
                payment_method_id: paymentMethodId
            })
        });
        
        const orderResult = await orderResponse.json();
        
        if (!orderResult.success) {
            throw new Error(orderResult.error);
        }
        
        // Confirmer le paiement
        const {error: confirmError} = await stripe.confirmCardPayment(orderResult.client_secret, {
            payment_method: paymentMethodId
        });
        
        if (confirmError) {
            throw new Error(confirmError.message);
        }
        
        // Proposer de sauvegarder la carte si nouvelle
        if (selectedCard === 'new' && currentUser && checkoutData.save_card) {
            await saveCardForFuture(paymentMethodId);
        }
        
        return {
            success: true,
            order_number: orderResult.order_number
        };
        
    } catch (error) {
        console.error('Erreur paiement Stripe:', error);
        throw error;
    }
}

// Sauvegarder une carte pour usage futur
async function saveCardForFuture(paymentMethodId) {
    try {
        const response = await fetch('/api/v1/payments/stripe/save-card', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                payment_method_id: paymentMethodId
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('Carte sauvegardée avec succès');
        }
        
    } catch (error) {
        console.error('Erreur sauvegarde carte:', error);
    }
}

// Créer un Setup Intent pour sauvegarder une carte
async function setupCardForFuture() {
    try {
        if (!stripe || !cardElement) {
            throw new Error('Stripe non initialisé');
        }
        
        // Créer le Setup Intent
        const response = await fetch('/api/v1/payments/stripe/setup-intent', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error);
        }
        
        // Confirmer le Setup Intent
        const {error, setupIntent} = await stripe.confirmCardSetup(result.client_secret, {
            payment_method: {
                card: cardElement,
                billing_details: {
                    name: currentUser ? `${currentUser.first_name} ${currentUser.last_name}` : ''
                }
            }
        });
        
        if (error) {
            throw new Error(error.message);
        }
        
        // Recharger les cartes sauvegardées
        await loadSavedCards();
        
        return {
            success: true,
            setup_intent: setupIntent
        };
        
    } catch (error) {
        console.error('Erreur setup carte:', error);
        throw error;
    }
}

// Gérer les abonnements Stripe
async function createStripeSubscription(productData) {
    try {
        const response = await fetch('/api/v1/payments/stripe/create-subscription', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(productData)
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error);
        }
        
        return result;
        
    } catch (error) {
        console.error('Erreur création abonnement:', error);
        throw error;
    }
}

// Annuler un abonnement Stripe
async function cancelStripeSubscription(subscriptionId) {
    try {
        const response = await fetch('/api/v1/payments/stripe/cancel-subscription', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                subscription_id: subscriptionId
            })
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error);
        }
        
        return result;
        
    } catch (error) {
        console.error('Erreur annulation abonnement:', error);
        throw error;
    }
}

// Afficher les erreurs Stripe
function showStripeError(message) {
    const errorElement = document.getElementById('card-errors');
    if (errorElement) {
        errorElement.textContent = message;
        errorElement.classList.remove('hidden');
    }
    
    if (typeof showNotification === 'function') {
        showNotification(message, 'error');
    }
}

// Modal pour ajouter une carte
function showAddCardModal() {
    const modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center';
    modal.innerHTML = `
        <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 max-w-md mx-4 w-full">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-bold text-white">Ajouter une carte</h3>
                <button onclick="closeAddCardModal()" class="text-gray-400 hover:text-white">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="space-y-4">
                <div id="modal-card-element" class="p-3 bg-dark-700 border border-dark-600 rounded-lg">
                    <!-- Stripe Elements sera monté ici -->
                </div>
                <div id="modal-card-errors" class="text-red-400 text-sm hidden"></div>
                
                <div class="flex space-x-3">
                    <button onclick="closeAddCardModal()" 
                            class="flex-1 bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                        Annuler
                    </button>
                    <button onclick="saveNewCard()" 
                            class="flex-1 bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-4 rounded-xl transition-colors">
                        Sauvegarder
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Créer un nouvel élément de carte pour le modal
    if (stripe) {
        const elements = stripe.elements({
            appearance: {
                theme: 'night',
                variables: {
                    colorPrimary: '#0ea5e9',
                    colorBackground: '#374151',
                    colorText: '#ffffff'
                }
            }
        });
        
        const modalCardElement = elements.create('card');
        modalCardElement.mount('#modal-card-element');
        
        modalCardElement.on('change', (event) => {
            const errorElement = document.getElementById('modal-card-errors');
            if (event.error) {
                errorElement.textContent = event.error.message;
                errorElement.classList.remove('hidden');
            } else {
                errorElement.textContent = '';
                errorElement.classList.add('hidden');
            }
        });
        
        window.modalCardElement = modalCardElement;
    }
}

// Fermer le modal d'ajout de carte
function closeAddCardModal() {
    const modal = document.querySelector('.fixed.inset-0.z-50');
    if (modal) {
        document.body.removeChild(modal);
    }
    window.modalCardElement = null;
}

// Sauvegarder une nouvelle carte via le modal
async function saveNewCard() {
    try {
        if (!window.modalCardElement) {
            throw new Error('Élément de carte non trouvé');
        }
        
        const {error, setupIntent} = await stripe.confirmCardSetup(
            (await fetch('/api/v1/payments/stripe/setup-intent', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            }).then(r => r.json())).client_secret,
            {
                payment_method: {
                    card: window.modalCardElement,
                    billing_details: {
                        name: currentUser ? `${currentUser.first_name} ${currentUser.last_name}` : ''
                    }
                }
            }
        );
        
        if (error) {
            throw new Error(error.message);
        }
        
        closeAddCardModal();
        await loadSavedCards();
        showNotification('Carte ajoutée avec succès', 'success');
        
    } catch (error) {
        console.error('Erreur sauvegarde carte:', error);
        showNotification(error.message, 'error');
    }
}

// Initialisation automatique si Stripe est disponible
if (typeof Stripe !== 'undefined') {
    // Attendre que le DOM soit prêt
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeStripe);
    } else {
        initializeStripe();
    }
}

// Export des fonctions pour usage global
window.stripePayment = {
    initialize: initializeStripe,
    processPayment: processStripePayment,
    setupCard: setupCardForFuture,
    showAddCardModal: showAddCardModal,
    createSubscription: createStripeSubscription,
    cancelSubscription: cancelStripeSubscription
};