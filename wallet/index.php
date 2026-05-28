<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    header('Location: /auth/login?redirect=/wallet');
    exit();
}

try {
    $db = Database::getInstance();
    
        $statsStmt = $db->prepare("
        SELECT 
            u.balance,
            COALESCE(SUM(CASE WHEN bt.type IN ('credit', 'bonus') THEN bt.amount ELSE 0 END), 0) as total_credited,
            COALESCE(SUM(CASE WHEN bt.type IN ('debit', 'penalty') THEN bt.amount ELSE 0 END), 0) as total_debited,
            COUNT(DISTINCT bt.id) as transaction_count
        FROM users u
        LEFT JOIN balance_transactions bt ON u.id = bt.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $statsStmt->execute([$currentUser['id']]);
    $stats = $statsStmt->fetch() ?: [
        'balance' => 0,
        'total_credited' => 0,
        'total_debited' => 0,
        'transaction_count' => 0
    ];
    
        $cardsStmt = $db->prepare("
        SELECT *
        FROM user_payment_methods
        WHERE user_id = ? AND is_active = 1 AND deleted_at IS NULL
        ORDER BY is_default DESC, created_at DESC
    ");
    $cardsStmt->execute([$currentUser['id']]);
    $savedCards = $cardsStmt->fetchAll();
    
        $transactionsStmt = $db->prepare("
        SELECT *
        FROM balance_transactions
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $transactionsStmt->execute([$currentUser['id']]);
    $transactions = $transactionsStmt->fetchAll();
    
        $stripeConfig = null;
    $stripeAdapterStmt = $db->prepare("
        SELECT config
        FROM payment_adapters
        WHERE slug = 'stripe' AND is_active = 1
    ");
    $stripeAdapterStmt->execute();
    $stripeAdapter = $stripeAdapterStmt->fetch();
    
    if ($stripeAdapter) {
        $config = json_decode($stripeAdapter['config'], true);
        $stripeConfig = [
            'publishable_key' => $config['publishable_key'] ?? ''
        ];
    }
    
} catch (Exception $e) {
    error_log("Wallet page error: " . $e->getMessage());
    $stats = ['balance' => 0, 'total_credited' => 0, 'total_debited' => 0, 'transaction_count' => 0];
    $savedCards = [];
    $transactions = [];
    $stripeConfig = null;
}

function getCardBrandIcon($brand) {
    $icons = [
        'visa' => 'fab fa-cc-visa',
        'mastercard' => 'fab fa-cc-mastercard',
        'amex' => 'fab fa-cc-amex',
        'discover' => 'fab fa-cc-discover'
    ];
    return $icons[strtolower($brand)] ?? 'fas fa-credit-card';
}

function getTransactionColor($type) {
    $colors = [
        'credit' => 'text-green-400',
        'bonus' => 'text-green-400',
        'debit' => 'text-red-400',
        'penalty' => 'text-red-400'
    ];
    return $colors[$type] ?? 'text-gray-400';
}

$seo = [
    'title' => 'Mon Portefeuille - Pilote Shopping',
    'description' => 'Gérez votre solde, vos cartes bancaires et consultez votre historique de transactions',
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
                <span class="text-white font-medium">Portefeuille</span>
            </div>
        </nav>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    <i class="fas fa-wallet text-blue-primary mr-3"></i>
                    Mon Portefeuille
                </h1>
                <p class="text-gray-400">Gérez votre solde et vos moyens de paiement</p>
            </div>

            <div class="mt-6 lg:mt-0 flex flex-wrap gap-3">
                <button id="addFundsBtn" class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-plus mr-2"></i>Recharger le solde
                </button>
                <button id="addCardBtn" class="bg-blue-primary hover:bg-blue-secondary text-white font-bold py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-credit-card mr-2"></i>Ajouter une carte
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-blue-primary/50 transition-all duration-300 group">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Solde disponible</p>
                        <p class="text-3xl font-bold text-white group-hover:text-blue-primary transition-colors">
<?= number_format($stats['balance'], 2) ?>€
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-blue-primary/20 rounded-xl flex items-center justify-center group-hover:bg-blue-primary/30 transition-colors">
                        <i class="fas fa-wallet text-blue-primary text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-green-500/50 transition-all duration-300 group">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Total crédité</p>
                        <p class="text-3xl font-bold text-white group-hover:text-green-400 transition-colors">
<?= number_format($stats['total_credited'], 2) ?>€
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-green-500/20 rounded-xl flex items-center justify-center group-hover:bg-green-500/30 transition-colors">
                        <i class="fas fa-arrow-down text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-red-500/50 transition-all duration-300 group">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Total dépensé</p>
                        <p class="text-3xl font-bold text-white group-hover:text-red-400 transition-colors">
<?= number_format($stats['total_debited'], 2) ?>€
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-red-500/20 rounded-xl flex items-center justify-center group-hover:bg-red-500/30 transition-colors">
                        <i class="fas fa-arrow-up text-red-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6 hover:border-purple-500/50 transition-all duration-300 group">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm mb-1">Transactions</p>
                        <p class="text-3xl font-bold text-white group-hover:text-purple-400 transition-colors">
<?= $stats['transaction_count'] ?>
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-purple-500/20 rounded-xl flex items-center justify-center group-hover:bg-purple-500/30 transition-colors">
                        <i class="fas fa-exchange-alt text-purple-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <div class="lg:col-span-2 space-y-8">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-credit-card text-blue-primary mr-2"></i>
                            Mes cartes bancaires
                        </h2>
                        <button onclick="openAddCardModal()" class="text-blue-primary hover:text-blue-secondary text-sm font-medium transition-colors">
                            <i class="fas fa-plus mr-1"></i>Ajouter
                        </button>
                    </div>

<?php if (empty($savedCards)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-credit-card text-blue-primary text-2xl"></i>
                            </div>
                            <h3 class="text-white font-medium mb-2">Aucune carte enregistrée</h3>
                            <p class="text-gray-400 text-sm mb-6">Ajoutez une carte pour faciliter vos paiements futurs</p>
                            <button onclick="openAddCardModal()" class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-2 px-6 rounded-xl transition-colors">
                                <i class="fas fa-plus mr-2"></i>Ajouter une carte
                            </button>
                        </div>
<?php else: ?>
                        <div class="space-y-4">
<?php foreach ($savedCards as $card): ?>
                            <div class="bg-dark-700/50 rounded-xl p-4 hover:bg-dark-700 transition-all duration-300 card-item" data-card-id="<?= $card['id'] ?>">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-4 flex-1">

                                        <div class="w-12 h-12 bg-dark-600 rounded-xl flex items-center justify-center">
                                            <i class="<?= getCardBrandIcon($card['brand']) ?> text-blue-primary text-2xl"></i>
                                        </div>

                                        <div class="flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="font-semibold text-white capitalize">
<?= htmlspecialchars($card['brand']) ?>
                                                </span>
                                                <span class="text-gray-400">••••</span>
                                                <span class="text-white font-medium"><?= htmlspecialchars($card['last4']) ?></span>
<?php if ($card['is_default']): ?>
                                                <span class="bg-blue-primary text-white text-xs px-2 py-1 rounded-full">Par défaut</span>
<?php endif; ?>
                                            </div>

                                            <div class="flex items-center gap-4 text-sm text-gray-400">
                                                <span>
                                                    <i class="far fa-calendar mr-1"></i>
                                                    Expire <?= str_pad($card['exp_month'], 2, '0', STR_PAD_LEFT) ?>/<?= $card['exp_year'] ?>
                                                </span>
<?php if ($card['country']): ?>
                                                <span>
                                                    <i class="fas fa-globe mr-1"></i>
<?= htmlspecialchars($card['country']) ?>
                                                </span>
<?php endif; ?>
                                            </div>

<?php if ($card['nickname']): ?>
                                            <div class="text-sm text-gray-400 mt-1">
                                                <i class="fas fa-tag mr-1"></i>
<?= htmlspecialchars($card['nickname']) ?>
                                            </div>
<?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
<?php if (!$card['is_default']): ?>
                                        <button onclick="setDefaultCard('<?= $card['id'] ?>')" 
                                                class="text-gray-400 hover:text-blue-primary transition-colors p-2" 
                                                title="Définir par défaut">
                                            <i class="fas fa-star"></i>
                                        </button>
<?php endif; ?>

                                        <button onclick="deleteCard('<?= $card['id'] ?>')" 
                                                class="text-gray-400 hover:text-red-400 transition-colors p-2" 
                                                title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
<?php endforeach; ?>
                        </div>
<?php endif; ?>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white">
                            <i class="fas fa-history text-blue-primary mr-2"></i>
                            Historique des transactions
                        </h2>
                        <div class="flex items-center gap-2">
                            <select id="transactionFilter" class="bg-dark-700 text-white text-sm rounded-lg px-3 py-2 border border-dark-600 focus:border-blue-primary focus:outline-none">
                                <option value="all">Toutes</option>
                                <option value="credit">Crédits</option>
                                <option value="debit">Débits</option>
                            </select>
                        </div>
                    </div>

<?php if (empty($transactions)): ?>
                        <div class="text-center py-12">
                            <div class="w-16 h-16 bg-gray-700/50 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-receipt text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-white font-medium mb-2">Aucune transaction</h3>
                            <p class="text-gray-400 text-sm">Votre historique de transactions apparaîtra ici</p>
                        </div>
<?php else: ?>
                        <div class="space-y-3" id="transactionsList">
<?php foreach ($transactions as $transaction): ?>
                            <div class="transaction-item flex items-center justify-between p-4 bg-dark-700/30 rounded-lg hover:bg-dark-700/50 transition-colors" 
                                 data-type="<?= $transaction['type'] ?>">
                                <div class="flex items-center space-x-4 flex-1">
                                    <div class="w-10 h-10 rounded-full flex items-center justify-center <?= 
                                        in_array($transaction['type'], ['credit', 'bonus']) ? 'bg-green-500/20' : 'bg-red-500/20' 
                                    ?>">
                                        <i class="fas fa-<?= 
                                            in_array($transaction['type'], ['credit', 'bonus']) ? 'arrow-down' : 'arrow-up'
                                        ?><?= getTransactionColor($transaction['type']) ?>"></i>
                                    </div>

                                    <div class="flex-1">
                                        <p class="text-white font-medium"><?= htmlspecialchars($transaction['description']) ?></p>
                                        <div class="flex items-center gap-3 text-sm text-gray-400 mt-1">
                                            <span>
                                                <i class="far fa-clock mr-1"></i>
<?= date('d/m/Y à H:i', strtotime($transaction['created_at'])) ?>
                                            </span>
<?php if ($transaction['source']): ?>
                                            <span class="text-xs bg-dark-600 px-2 py-1 rounded">
<?= htmlspecialchars($transaction['source']) ?>
                                            </span>
<?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-right">
                                    <p class="font-bold text-lg <?= getTransactionColor($transaction['type']) ?>">
<?= in_array($transaction['type'], ['credit', 'bonus']) ? '+' : '-' ?>
<?= number_format($transaction['amount'], 2) ?>€
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        Solde: <?= number_format($transaction['balance_after'], 2) ?>€
                                    </p>
                                </div>
                            </div>
<?php endforeach; ?>
                        </div>
<?php endif; ?>
                </div>
            </div>

            <div class="space-y-8">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-bolt text-blue-primary mr-2"></i>
                        Actions rapides
                    </h3>

                    <div class="space-y-3">
                        <button onclick="openAddFundsModal()" class="w-full bg-green-500/20 hover:bg-green-500/30 text-green-400 font-medium py-3 px-4 rounded-xl transition-all duration-300 text-left flex items-center gap-3">
                            <div class="w-10 h-10 bg-green-500/30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div>
                                <p class="font-semibold">Recharger</p>
                                <p class="text-sm text-gray-400">Ajouter des fonds</p>
                            </div>
                        </button>

                        <button onclick="openTransferModal()" class="w-full bg-blue-primary/20 hover:bg-blue-primary/30 text-blue-primary font-medium py-3 px-4 rounded-xl transition-all duration-300 text-left flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-primary/30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div>
                                <p class="font-semibold">Transférer</p>
                                <p class="text-sm text-gray-400">Vers un autre compte</p>
                            </div>
                        </button>

                        <a href="/orders" class="block w-full bg-purple-500/20 hover:bg-purple-500/30 text-purple-400 font-medium py-3 px-4 rounded-xl transition-all duration-300 text-left flex items-center gap-3">
                            <div class="w-10 h-10 bg-purple-500/30 rounded-lg flex items-center justify-center">
                                <i class="fas fa-shopping-bag"></i>
                            </div>
                            <div>
                                <p class="font-semibold">Mes commandes</p>
                                <p class="text-sm text-gray-400">Voir l'historique</p>
                            </div>
                        </a>
                    </div>
                </div>

                <div class="bg-gradient-to-br from-green-500/10 to-emerald-500/10 border border-green-500/30 rounded-2xl p-6">
                    <div class="flex items-start gap-3 mb-4">
                        <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-shield-alt text-green-400"></i>
                        </div>
                        <div>
                            <h3 class="text-white font-semibold mb-1">Paiements sécurisés</h3>
                            <p class="text-gray-400 text-sm">Vos informations sont protégées par cryptage SSL</p>
                        </div>
                    </div>
                    <div class="space-y-2 text-sm text-gray-400">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-check text-green-400"></i>
                            <span>Certification PCI-DSS</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-check text-green-400"></i>
                            <span>Protection 3D Secure</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="fas fa-check text-green-400"></i>
                            <span>Données cryptées</span>
                        </div>
                    </div>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4">
                        <i class="fas fa-question-circle text-blue-primary mr-2"></i>
                        Besoin d'aide ?
                    </h3>
                    <p class="text-gray-400 text-sm mb-4">
                        Notre équipe est là pour vous aider avec vos questions sur les paiements
                    </p>
                    <a href="/support" class="block w-full bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-4 rounded-xl transition-colors text-center">
                        <i class="fas fa-headset mr-2"></i>Contacter le support
                    </a>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>


<div id="addCardModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-credit-card text-blue-primary mr-2"></i>
                Ajouter une carte
            </h3>
            <button onclick="closeAddCardModal()" class="text-gray-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="addCardForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Nom du titulaire</label>
                <input type="text" name="cardholder_name" required 
                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                       placeholder="John Doe">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Informations de carte</label>
                <div id="card-element-add" class="p-3 bg-dark-700 border border-dark-600 rounded-xl"></div>
                <div id="card-errors-add" class="text-red-400 text-sm mt-2 hidden"></div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Surnom (optionnel)</label>
                <input type="text" name="nickname" 
                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                       placeholder="Ma carte principale">
            </div>

            <div class="flex items-center space-x-2">
                <input type="checkbox" name="set_as_default" id="setAsDefault" 
                       class="w-5 h-5 bg-dark-700 border border-dark-600 rounded text-blue-primary focus:ring-blue-primary focus:ring-2">
                <label for="setAsDefault" class="text-sm text-gray-300">
                    Définir comme carte par défaut
                </label>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeAddCardModal()" 
                        class="flex-1 bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    Annuler
                </button>
                <button type="submit" 
                        class="flex-1 bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    <i class="fas fa-plus mr-2"></i>Ajouter
                </button>
            </div>
        </form>
    </div>
</div>

<div id="addFundsModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-plus-circle text-green-400 mr-2"></i>
                Recharger mon solde
            </h3>
            <button onclick="closeAddFundsModal()" class="text-gray-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="addFundsForm" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-3">Montant à ajouter</label>
                <div class="grid grid-cols-3 gap-3 mb-3">
                    <button type="button" onclick="setAmount(10)" class="amount-btn py-3 bg-dark-700 hover:bg-dark-600 text-white rounded-lg font-medium transition-colors">
                        10€
                    </button>
                    <button type="button" onclick="setAmount(25)" class="amount-btn py-3 bg-dark-700 hover:bg-dark-600 text-white rounded-lg font-medium transition-colors">
                        25€
                    </button>
                    <button type="button" onclick="setAmount(50)" class="amount-btn py-3 bg-dark-700 hover:bg-dark-600 text-white rounded-lg font-medium transition-colors">
                        50€
                    </button>
                    <button type="button" onclick="setAmount(100)" class="amount-btn py-3 bg-dark-700 hover:bg-dark-600 text-white rounded-lg font-medium transition-colors">
                        100€
                    </button>
                    <button type="button" onclick="setAmount(200)" class="amount-btn py-3 bg-dark-700 hover:bg-dark-600 text-white rounded-lg font-medium transition-colors">
                        200€
                    </button>
                    <button type="button" onclick="setAmount(500)" class="amount-btn py-3 bg-dark-700 hover:bg-dark-600 text-white rounded-lg font-medium transition-colors">
                        500€
                    </button>
                </div>
                <div class="relative">
                    <input type="number" name="amount" id="amountInput" min="5" step="1" required 
                           class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                           placeholder="Ou entrez un montant personnalisé">
                    <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400">€</span>
                </div>
                <p class="text-sm text-gray-400 mt-2">Montant minimum: 5€</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Méthode de paiement</label>
                <select name="payment_method" 
                        class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white focus:outline-none focus:border-blue-primary transition-colors">
                    <option value="card">Carte bancaire</option>
                    <option value="paypal">PayPal</option>
                </select>
            </div>

            <div class="bg-blue-primary/10 border border-blue-primary/30 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-info-circle text-blue-primary mt-0.5"></i>
                    <p class="text-sm text-gray-300">
                        Les fonds seront immédiatement disponibles après validation du paiement
                    </p>
                </div>
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeAddFundsModal()" 
                        class="flex-1 bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    Annuler
                </button>
                <button type="submit" 
                        class="flex-1 bg-green-500 hover:bg-green-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    <i class="fas fa-lock mr-2"></i>Recharger
                </button>
            </div>
        </form>
    </div>
</div>

<div id="transferModal" class="fixed inset-0 z-50 bg-black/50 backdrop-blur-sm hidden items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold text-white">
                <i class="fas fa-exchange-alt text-blue-primary mr-2"></i>
                Transférer des fonds
            </h3>
            <button onclick="closeTransferModal()" class="text-gray-400 hover:text-white transition-colors">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form id="transferForm" class="space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Destinataire</label>
                <input type="text" name="recipient" required 
                       class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                       placeholder="Nom d'utilisateur ou email">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Montant</label>
                <div class="relative">
                    <input type="number" name="transfer_amount" min="1" step="0.01" required 
                           class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors"
                           placeholder="Montant à transférer">
                    <span class="absolute right-4 top-1/2 transform -translate-y-1/2 text-gray-400">€</span>
                </div>
                <p class="text-sm text-gray-400 mt-2">Solde disponible: <?= number_format($stats['balance'], 2) ?>€</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-300 mb-2">Message (optionnel)</label>
                <textarea name="message" rows="3" 
                          class="w-full px-4 py-3 bg-dark-700 border border-dark-600 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-blue-primary transition-colors resize-none"
                          placeholder="Ajouter un message..."></textarea>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeTransferModal()" 
                        class="flex-1 bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    Annuler
                </button>
                <button type="submit" 
                        class="flex-1 bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-4 rounded-xl transition-colors">
                    <i class="fas fa-paper-plane mr-2"></i>Envoyer
                </button>
            </div>
        </form>
    </div>
</div>

<div id="loadingModal" class="fixed inset-0 z-[60] bg-black/50 backdrop-blur-sm hidden items-center justify-center">
    <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 text-center">
        <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-primary mx-auto mb-4"></div>
        <h3 class="text-lg font-semibold text-white mb-2">Traitement en cours...</h3>
        <p class="text-gray-400" id="loadingText">Veuillez patienter</p>
    </div>
</div>

<?php if ($stripeConfig): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script>
const CONFIG = {
    stripePublishableKey: <?= $stripeConfig ? json_encode($stripeConfig['publishable_key']) : 'null' ?>,
    currentBalance: <?= $stats['balance'] ?>
};

const STATE = {
    stripe: null,
    cardElement: null
};

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-[70] px-6 py-4 rounded-xl text-white font-medium transition-all duration-300 transform translate-x-full`;

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
        setTimeout(() => document.body.removeChild(notification), 300);
    }, 5000);
}

function showLoadingModal(show, text = 'Veuillez patienter') {
    const modal = document.getElementById('loadingModal');
    const loadingText = document.getElementById('loadingText');

    loadingText.textContent = text;

    if (show) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    } else {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

function openAddCardModal() {
    const modal = document.getElementById('addCardModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    if (!STATE.cardElement && CONFIG.stripePublishableKey) {
        initializeStripeForAddCard();
    }
}

function closeAddCardModal() {
    const modal = document.getElementById('addCardModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function openAddFundsModal() {
    const modal = document.getElementById('addFundsModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAddFundsModal() {
    const modal = document.getElementById('addFundsModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function openTransferModal() {
    const modal = document.getElementById('transferModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeTransferModal() {
    const modal = document.getElementById('transferModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

async function initializeStripeForAddCard() {
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

        STATE.cardElement.mount('#card-element-add');

        STATE.cardElement.on('change', function(event) {
            const errorElement = document.getElementById('card-errors-add');
            if (event.error) {
                errorElement.textContent = event.error.message;
                errorElement.classList.remove('hidden');
            } else {
                errorElement.textContent = '';
                errorElement.classList.add('hidden');
            }
        });
    } catch (error) {
        console.error('Stripe initialization error:', error);
        showNotification('Erreur lors de l\'initialisation du paiement', 'error');
    }
}

async function setDefaultCard(cardId) {
    try {
        showLoadingModal(true, 'Mise à jour de la carte par défaut...');

        const response = await fetch('/api/v1/payments/stripe/saved-cards', {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                payment_method_id: cardId,
                action: 'set_default'
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Carte définie par défaut', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(result.error || 'Erreur lors de la mise à jour');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    } finally {
        showLoadingModal(false);
    }
}

async function deleteCard(cardId) {
    if (!confirm('Êtes-vous sûr de vouloir supprimer cette carte ?')) {
        return;
    }

    try {
        showLoadingModal(true, 'Suppression de la carte...');

        const response = await fetch('/api/v1/payments/stripe/saved-cards', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                payment_method_id: cardId
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Carte supprimée', 'success');

            const cardElement = document.querySelector(`[data-card-id="${cardId}"]`);
            if (cardElement) {
                cardElement.style.opacity = '0';
                cardElement.style.transform = 'translateX(-20px)';
                setTimeout(() => cardElement.remove(), 300);
            }
        } else {
            throw new Error(result.error || 'Erreur lors de la suppression');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    } finally {
        showLoadingModal(false);
    }
}

document.getElementById('addCardForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    try {
        showLoadingModal(true, 'Ajout de la carte en cours...');

        const formData = new FormData(this);

        const {error, paymentMethod} = await STATE.stripe.createPaymentMethod({
            type: 'card',
            card: STATE.cardElement,
            billing_details: {
                name: formData.get('cardholder_name')
            }
        });

        if (error) {
            throw new Error(error.message);
        }

        const response = await fetch('/api/v1/payments/stripe/saved-cards', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'save_card',
                payment_method_id: paymentMethod.id,
                nickname: formData.get('nickname') || null,
                set_as_default: formData.get('set_as_default') === 'on'
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Carte ajoutée avec succès', 'success');
            closeAddCardModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(result.error || 'Erreur lors de l\'ajout de la carte');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    } finally {
        showLoadingModal(false);
    }
});

document.getElementById('addFundsForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const amount = parseFloat(formData.get('amount'));

    if (amount < 5) {
        showNotification('Le montant minimum est de 5€', 'warning');
        return;
    }

    try {
        showLoadingModal(true, 'Traitement du paiement...');

        const response = await fetch('/api/v1/wallet/add-funds', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                amount: amount,
                payment_method: formData.get('payment_method')
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Rechargement effectué avec succès', 'success');
            closeAddFundsModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(result.error || 'Erreur lors du rechargement');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    } finally {
        showLoadingModal(false);
    }
});

document.getElementById('transferForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();

    const formData = new FormData(this);
    const amount = parseFloat(formData.get('transfer_amount'));

    if (amount > CONFIG.currentBalance) {
        showNotification('Solde insuffisant', 'warning');
        return;
    }

    try {
        showLoadingModal(true, 'Transfert en cours...');

        const response = await fetch('/api/v1/wallet/transfer', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                recipient: formData.get('recipient'),
                amount: amount,
                message: formData.get('message')
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification('Transfert effectué avec succès', 'success');
            closeTransferModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            throw new Error(result.error || 'Erreur lors du transfert');
        }
    } catch (error) {
        showNotification(error.message, 'error');
    } finally {
        showLoadingModal(false);
    }
});

function setAmount(amount) {
    document.getElementById('amountInput').value = amount;

    document.querySelectorAll('.amount-btn').forEach(btn => {
        btn.classList.remove('bg-blue-primary', 'text-white');
        btn.classList.add('bg-dark-700', 'text-white');
    });

    event.target.classList.remove('bg-dark-700');
    event.target.classList.add('bg-blue-primary');
}

document.getElementById('transactionFilter')?.addEventListener('change', function() {
    const filterValue = this.value;
    const transactions = document.querySelectorAll('.transaction-item');

    transactions.forEach(transaction => {
        const type = transaction.dataset.type;

        if (filterValue === 'all') {
            transaction.style.display = 'flex';
        } else {
            transaction.style.display = type === filterValue ? 'flex' : 'none';
        }
    });
});

document.getElementById('addFundsBtn')?.addEventListener('click', openAddFundsModal);
document.getElementById('addCardBtn')?.addEventListener('click', openAddCardModal);

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAddCardModal();
        closeAddFundsModal();
        closeTransferModal();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    console.log('Wallet page loaded');
});
</script>

<style>

.card-item {
    transition: all 0.3s ease;
}

.card-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(14, 165, 233, 0.1);
}

.amount-btn {
    transition: all 0.2s ease;
}

.amount-btn:hover {
    transform: translateY(-2px);
}

.transaction-item {
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .grid-cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>

<?php
endHTMLPage();
?>