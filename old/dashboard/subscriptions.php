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
    
        $subscriptionsStmt = $db->prepare("
        SELECT s.*, oi.product_name, oi.product_type, oi.unit_price,
               p.slug as product_slug, p.images,
               pc.name as category_name, pc.color as category_color,
               pt.name as type_name, pt.icon as type_icon,
               po.name as offer_name, po.billing_cycle, po.trial_days,
               o.order_number, o.created_at as order_date
        FROM subscriptions s
        JOIN order_items oi ON s.order_item_id = oi.id
        JOIN orders o ON oi.order_id = o.id
        JOIN products p ON oi.product_id = p.id
        JOIN product_categories pc ON p.category_id = pc.id
        JOIN product_types pt ON p.product_type_id = pt.id
        LEFT JOIN product_offers po ON s.product_offer_id = po.id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $subscriptionsStmt->execute([$currentUser['id']]);
    $subscriptions = $subscriptionsStmt->fetchAll();
    
        foreach ($subscriptions as &$sub) {
        $sub['images'] = json_decode($sub['images'] ?? '[]', true);
        $sub['main_image'] = !empty($sub['images']) ? $sub['images'][0] : '/assets/images/product-placeholder.jpg';
        $sub['product_url'] = '/products/' . $sub['product_slug'];
        
                $sub['billing_info'] = [
            'cycle' => $sub['billing_cycle'],
            'cycle_label' => getBillingCycleLabel($sub['billing_cycle']),             'next_date' => $sub['next_billing_date'],
            'last_date' => $sub['last_billing_date'],
            'amount' => $sub['amount'],
            'currency' => $sub['currency']
        ];
        
                $sub['can_cancel'] = in_array($sub['status'], ['active', 'paused']);
        $sub['can_resume'] = $sub['status'] === 'paused';
        $sub['is_trial'] = $sub['trial_days'] > 0 && empty($sub['last_billing_date']);
    }
    
        $stats = [
        'total' => count($subscriptions),
        'active' => count(array_filter($subscriptions, fn($s) => $s['status'] === 'active')),
        'paused' => count(array_filter($subscriptions, fn($s) => $s['status'] === 'paused')),
        'cancelled' => count(array_filter($subscriptions, fn($s) => in_array($s['status'], ['cancelled', 'expired']))),
        'monthly_total' => array_sum(array_map(fn($s) => $s['status'] === 'active' ? $s['amount'] : 0, $subscriptions))
    ];
    
} catch (Exception $e) {
    error_log("Dashboard subscriptions error: " . $e->getMessage());
    $subscriptions = [];
    $stats = ['total' => 0, 'active' => 0, 'paused' => 0, 'cancelled' => 0, 'monthly_total' => 0];
}

$seo = [
    'title' => 'Mes Abonnements - Pilote Shopping',
    'description' => 'Gérez vos abonnements et consultez vos prochaines facturations',
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
                <span class="text-white font-medium">Abonnements</span>
            </div>
        </nav>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    <i class="fas fa-sync-alt text-purple-primary mr-3"></i>
                    Mes Abonnements
                </h1>
                <p class="text-gray-400">Gérez vos abonnements et consultez vos facturations</p>
            </div>

            <div class="mt-6 lg:mt-0">
                <a href="/products?filter=subscriptions" 
                   class="bg-purple-primary hover:bg-purple-secondary text-white font-bold py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-plus mr-2"></i>Découvrir les abonnements
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total</p>
                        <p class="text-2xl font-bold text-white"><?= $stats['total'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-primary/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-list text-blue-primary text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Actifs</p>
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
                        <p class="text-gray-400 text-sm">En pause</p>
                        <p class="text-2xl font-bold text-orange-400"><?= $stats['paused'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-pause-circle text-orange-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total mensuel</p>
                        <p class="text-2xl font-bold text-purple-400"><?= number_format($stats['monthly_total'], 2) ?>€</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-500/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-euro-sign text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

<?php if (empty($subscriptions)): ?>
        <div class="text-center py-16">
            <div class="w-24 h-24 bg-purple-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-sync-alt text-purple-400 text-4xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Aucun abonnement</h3>
            <p class="text-gray-400 mb-8">Vous n'avez pas encore d'abonnements actifs</p>
            <a href="/products?filter=subscriptions" 
               class="bg-purple-primary hover:bg-purple-secondary text-white font-bold py-3 px-6 rounded-xl transition-colors">
                <i class="fas fa-search mr-2"></i>Découvrir nos abonnements
            </a>
        </div>
<?php else: ?>
        <div class="space-y-6">
<?php foreach ($subscriptions as $sub): ?>
            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 subscription-card" 
                 data-subscription-id="<?= $sub['id'] ?>" 
                 data-status="<?= $sub['status'] ?>">

                <div class="flex flex-col lg:flex-row lg:items-center lg:space-x-6">

                    <div class="flex items-start space-x-4 lg:flex-1 mb-4 lg:mb-0">
                        <div class="relative">
                            <img src="<?= htmlspecialchars($sub['main_image']) ?>" 
                                 alt="<?= htmlspecialchars($sub['product_name']) ?>"
                                 class="w-20 h-20 object-cover rounded-xl">

                            <div class="absolute -top-2 -right-2">
                                <span class="text-white text-xs font-medium px-2 py-1 rounded-full backdrop-blur-sm border" 
                                      style="background-color: <?= $sub['category_color'] ?>33; border-color: <?= $sub['category_color'] ?>; color: <?= $sub['category_color'] ?>">
                                    <i class="<?= $sub['type_icon'] ?>"></i>
                                </span>
                            </div>

                            <div class="absolute -bottom-1 -right-1">
                                <span class="bg-purple-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">
                                    <i class="fas fa-sync-alt"></i>
                                </span>
                            </div>
                        </div>

                        <div class="flex-1">
                            <h3 class="font-semibold text-white mb-1">
                                <a href="<?= htmlspecialchars($sub['product_url']) ?>" class="hover:text-blue-primary transition-colors">
<?= htmlspecialchars($sub['product_name']) ?>
                                </a>
                            </h3>

<?php if ($sub['offer_name']): ?>
                            <div class="text-sm text-gray-400 mb-2">
                                <i class="fas fa-tag mr-1"></i>
<?= htmlspecialchars($sub['offer_name']) ?>
                            </div>
<?php endif; ?>

                            <div class="flex flex-wrap gap-3 text-sm">

                                <span class="subscription-status status-<?= $sub['status'] ?> px-2 py-1 rounded-full text-xs font-medium">
<?= ucfirst($sub['status']) ?>
                                </span>

                                <span class="text-gray-400">
                                    <i class="fas fa-hashtag mr-1"></i>
<?= htmlspecialchars($sub['subscription_number']) ?>
                                </span>

                                <span class="text-purple-400">
                                    <i class="fas fa-calendar-alt mr-1"></i>
<?= getBillingCycleLabel($sub['billing_cycle']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="lg:w-80 mb-4 lg:mb-0">
                        <div class="bg-dark-700/50 rounded-xl p-4 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400 text-sm">Montant</span>
                                <span class="text-white font-bold"><?= number_format($sub['amount'], 2) ?>€</span>
                            </div>

<?php if ($sub['is_trial']): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400 text-sm">Période d'essai</span>
                                <span class="text-green-400 text-sm font-medium">
<?= $sub['trial_days'] ?> jour<?= $sub['trial_days'] > 1 ? 's' : '' ?>
                                </span>
                            </div>
<?php endif; ?>

<?php if ($sub['next_billing_date']): ?>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-400 text-sm">Prochaine facturation</span>
                                <span class="text-white text-sm">
<?= date('d/m/Y', strtotime($sub['next_billing_date'])) ?>
                                </span>
                            </div>
<?php endif; ?>

                            <div class="flex justify-between items-center">
                                <span class="text-gray-400 text-sm">Depuis le</span>
                                <span class="text-white text-sm">
<?= date('d/m/Y', strtotime($sub['start_date'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="lg:w-48 flex flex-col space-y-2">
<?php if ($sub['can_cancel'] && $sub['status'] === 'active'): ?>
                        <button onclick="pauseSubscription('<?= $sub['id'] ?>')" 
                                class="w-full bg-orange-500/20 hover:bg-orange-500/30 text-orange-400 font-medium py-2 px-4 rounded-lg transition-colors text-sm">
                            <i class="fas fa-pause mr-2"></i>Mettre en pause
                        </button>
<?php endif; ?>

<?php if ($sub['can_resume']): ?>
                        <button onclick="resumeSubscription('<?= $sub['id'] ?>')" 
                                class="w-full bg-green-500/20 hover:bg-green-500/30 text-green-400 font-medium py-2 px-4 rounded-lg transition-colors text-sm">
                            <i class="fas fa-play mr-2"></i>Reprendre
                        </button>
<?php endif; ?>

<?php if ($sub['can_cancel']): ?>
                        <button onclick="cancelSubscription('<?= $sub['id'] ?>')" 
                                class="w-full bg-red-500/20 hover:bg-red-500/30 text-red-400 font-medium py-2 px-4 rounded-lg transition-colors text-sm">
                            <i class="fas fa-times mr-2"></i>Annuler
                        </button>
<?php endif; ?>

                        <a href="/dashboard/orders?subscription=<?= $sub['subscription_number'] ?>" 
                           class="w-full bg-dark-600 hover:bg-dark-500 text-white font-medium py-2 px-4 rounded-lg transition-colors text-sm text-center">
                            <i class="fas fa-receipt mr-2"></i>Factures
                        </a>
                    </div>
                </div>
            </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>


<div id="confirmation-modal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 max-w-md mx-4">
        <div class="text-center">
            <div id="modal-icon" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">

            </div>
            <h3 id="modal-title" class="text-xl font-bold text-white mb-4"></h3>
            <p id="modal-message" class="text-gray-400 mb-6"></p>
            <div class="flex space-x-3">
                <button onclick="closeConfirmationModal()" 
                        class="flex-1 bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    Annuler
                </button>
                <button id="modal-confirm-btn" 
                        class="flex-1 font-medium py-3 px-4 rounded-xl transition-colors">
                    Confirmer
                </button>
            </div>
        </div>
    </div>
</div>

<script>

let currentSubscriptionId = null;
let currentAction = null;

function pauseSubscription(subscriptionId) {
    showConfirmationModal(
        'pause',
        'Mettre en pause',
        'Êtes-vous sûr de vouloir mettre cet abonnement en pause ? Vous ne serez plus facturé jusqu\'à ce que vous le repreniez.',
        () => performSubscriptionAction(subscriptionId, 'pause')
    );
}

function resumeSubscription(subscriptionId) {
    showConfirmationModal(
        'resume',
        'Reprendre l\'abonnement',
        'Êtes-vous sûr de vouloir reprendre cet abonnement ? La facturation reprendra selon le cycle normal.',
        () => performSubscriptionAction(subscriptionId, 'resume')
    );
}

function cancelSubscription(subscriptionId) {
    showConfirmationModal(
        'cancel',
        'Annuler l\'abonnement',
        'Êtes-vous sûr de vouloir annuler définitivement cet abonnement ? Cette action est irréversible.',
        () => performSubscriptionAction(subscriptionId, 'cancel')
    );
}

function showConfirmationModal(action, title, message, confirmCallback) {
    const modal = document.getElementById('confirmation-modal');
    const icon = document.getElementById('modal-icon');
    const titleEl = document.getElementById('modal-title');
    const messageEl = document.getElementById('modal-message');
    const confirmBtn = document.getElementById('modal-confirm-btn');

    const configs = {
        pause: {
            icon: '<i class="fas fa-pause text-orange-400 text-2xl"></i>',
            iconBg: 'bg-orange-500/20',
            btnClass: 'bg-orange-500 hover:bg-orange-600 text-white'
        },
        resume: {
            icon: '<i class="fas fa-play text-green-400 text-2xl"></i>',
            iconBg: 'bg-green-500/20',
            btnClass: 'bg-green-500 hover:bg-green-600 text-white'
        },
        cancel: {
            icon: '<i class="fas fa-times text-red-400 text-2xl"></i>',
            iconBg: 'bg-red-500/20',
            btnClass: 'bg-red-500 hover:bg-red-600 text-white'
        }
    };

    const config = configs[action];

    icon.innerHTML = config.icon;
    icon.className = `w-16 h-16 ${config.iconBg} rounded-full flex items-center justify-center mx-auto mb-4`;
    titleEl.textContent = title;
    messageEl.textContent = message;
    confirmBtn.className = `flex-1 ${config.btnClass} font-medium py-3 px-4 rounded-xl transition-colors`;

    confirmBtn.onclick = () => {
        closeConfirmationModal();
        confirmCallback();
    };

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeConfirmationModal() {
    const modal = document.getElementById('confirmation-modal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function performSubscriptionAction(subscriptionId, action) {
    try {
        showLoading(true);

        const response = await fetch('/api/v1/subscriptions/manage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                subscription_id: subscriptionId,
                action: action
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message || 'Action effectuée avec succès', 'success');

            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            throw new Error(result.error);
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification(error.message || 'Erreur lors de l\'action', 'error');
    } finally {
        showLoading(false);
    }
}

function showLoading(show) {

    let loader = document.getElementById('page-loader');

    if (show && !loader) {
        loader = document.createElement('div');
        loader.id = 'page-loader';
        loader.className = 'fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center';
        loader.innerHTML = `
            <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-primary mx-auto mb-4"></div>
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
            notification.className += ' bg-purple-primary';
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

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfirmationModal();
    }
});
</script>

<style>

.subscription-status.status-active {
    background-color: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    border: 1px solid rgba(34, 197, 94, 0.3);
}

.subscription-status.status-paused {
    background-color: rgba(249, 115, 22, 0.1);
    color: #f97316;
    border: 1px solid rgba(249, 115, 22, 0.3);
}

.subscription-status.status-cancelled,
.subscription-status.status-expired {
    background-color: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.subscription-status.status-failed {
    background-color: rgba(156, 163, 175, 0.1);
    color: #9ca3af;
    border: 1px solid rgba(156, 163, 175, 0.3);
}

.subscription-card {
    transition: all 0.3s ease;
}

.subscription-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(168, 85, 247, 0.1);
}

@media (max-width: 1024px) {
    .subscription-card .lg\\:w-80,
    .subscription-card .lg\\:w-48 {
        width: 100%;
    }
}
</style>

<?php
endHTMLPage();
?>