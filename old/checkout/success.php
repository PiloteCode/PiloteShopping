<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

$orderNumber = null;
$orderId = null;
$error = null;

try {
    $db = Database::getInstance();
    
        $orderNumber = $_GET['order'] ?? null;
    
    error_log("Checkout Success Redirect - Order Number: " . ($orderNumber ?? 'NULL'));
    error_log("Checkout Success Redirect - Current User: " . ($currentUser ? $currentUser['id'] : 'NULL'));
    
    if (!$orderNumber) {
        throw new Exception('Numéro de commande manquant');
    }
    
        if (!preg_match('/^PS\d{8}-[A-Z0-9]{6}$/', $orderNumber)) {
        throw new Exception('Format de numéro de commande invalide');
    }
    
        $orderStmt = $db->prepare("
        SELECT o.id, o.user_id, o.payment_status, o.status
        FROM orders o
        WHERE o.order_number = ?
    ");
    $orderStmt->execute([$orderNumber]);
    $order = $orderStmt->fetch();
    
    if (!$order) {
                sleep(2);
        $orderStmt->execute([$orderNumber]);
        $order = $orderStmt->fetch();
    }
    
    if (!$order) {
        throw new Exception('Commande non trouvée');
    }
    
    $orderId = $order['id'];
    
        $hasAccess = false;
    
    if ($currentUser && $order['user_id'] == $currentUser['id']) {
                $hasAccess = true;
        error_log("Checkout Success Redirect - Access granted: Owner");
    } elseif (!$currentUser && !$order['user_id']) {
                $redirectUrl = urlencode("/orders/detail?order={$orderId}");
        header("Location: /auth/login?redirect=$redirectUrl&message=" . urlencode("Veuillez vous connecter pour accéder à votre commande"));
        exit();
    } elseif (!$currentUser) {
                $redirectUrl = urlencode("/orders/detail?order={$orderId}");
        header("Location: /auth/login?redirect=$redirectUrl&message=" . urlencode("Veuillez vous connecter pour accéder à cette commande"));
        exit();
    } else {
                header('Location: /orders?error=' . urlencode("Cette commande ne vous appartient pas"));
        exit();
    }
    
    if (!$hasAccess) {
        header('Location: /orders?error=' . urlencode("Accès non autorisé"));
        exit();
    }
    
        $redirectUrl = "/orders/detail?order={$orderId}";
    
        $params = [];
    
    if (isset($_GET['pending']) && $_GET['pending'] === '1') {
        $params[] = 'payment=pending';
    }
    
    if ($order['payment_status'] === 'paid') {
        $params[] = 'payment=success';
    }
    
    if (!empty($params)) {
        $redirectUrl .= '&' . implode('&', $params);
    }
    
    error_log("Checkout Success Redirect - Redirecting to: $redirectUrl");
    
        header("Location: $redirectUrl");
    exit();
    
} catch (Exception $e) {
    error_log("Checkout Success Redirect Error: " . $e->getMessage());
    $error = $e->getMessage();
    
        if ($orderId) {
        header("Location: /orders/detail?order={$orderId}&error=" . urlencode($error));
        exit();
    }
    
        header('Location: /orders?error=' . urlencode($error));
    exit();
}

$seo = [
    'title' => 'Redirection en cours... - Pilote Shopping',
    'description' => 'Redirection vers votre commande',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen bg-dark-900 py-8">
    <div class="container mx-auto px-4 lg:px-6">
        <div class="text-center">
            <div class="w-20 h-20 bg-blue-primary/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-spinner fa-spin text-blue-primary text-3xl"></i>
            </div>
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                Redirection en cours...
            </h1>
            <p class="text-gray-400 mb-6">
                Vous allez être redirigé vers votre commande dans un instant.
            </p>

<?php if ($error): ?>
            <div class="bg-red-500/10 border border-red-500/30 rounded-xl p-4 mb-6 max-w-md mx-auto">
                <div class="text-red-400 text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
<?= htmlspecialchars($error) ?>
                </div>
            </div>
<?php endif; ?>

            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="/orders" 
                   class="bg-blue-primary hover:bg-blue-secondary text-white font-medium py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-list mr-2"></i>Mes commandes
                </a>

                <a href="/dashboard" 
                   class="bg-dark-700 hover:bg-dark-600 text-white font-medium py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-home mr-2"></i>Tableau de bord
                </a>
            </div>
        </div>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>

<script>

document.addEventListener('DOMContentLoaded', function() {

    const urlParams = new URLSearchParams(window.location.search);
    const orderNumber = urlParams.get('order');

    if (orderNumber) {

        fetch(`/api/v1/orders/get-id-by-number?order=${encodeURIComponent(orderNumber)}`)
            .then(response => response.json())
            .then(result => {
                if (result.success && result.order_id) {
                    let redirectUrl = `/orders/detail?order=${result.order_id}`;

                    if (urlParams.get('pending') === '1') {
                        redirectUrl += '&payment=pending';
                    }

                    window.location.href = redirectUrl;
                } else {

                    window.location.href = '/orders?error=' + encodeURIComponent('Commande non trouvée');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);

                setTimeout(() => {
                    window.location.href = '/orders';
                }, 3000);
            });
    } else {

        setTimeout(() => {
            window.location.href = '/orders';
        }, 2000);
    }
});
</script>

<?php endHTMLPage(); ?>