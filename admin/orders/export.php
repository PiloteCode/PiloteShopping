<?php
define('API_ACCESS', true);
require_once '../../api/v1/config.php';
require_once '../../includes/import.php';
require_once '../../includes/AuthMiddleware.php';
require_once '../includes/AdminAuthMiddleware.php';

$adminAuth = initAdminAuth();
$adminAuth->requireAdminAuth(['admin', 'super_admin', 'moderator']);

$currentAdmin = $adminAuth->getCurrentAdmin();
$currentUser = $adminAuth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        
    $format = $_GET['format'] ?? 'csv';
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $payment_status = $_GET['payment_status'] ?? '';
    $date_from = $_GET['date_from'] ?? '';
    $date_to = $_GET['date_to'] ?? '';
    $user_id = intval($_GET['user_id'] ?? 0);
    
        $allowedFormats = ['csv', 'excel'];
    if (!in_array($format, $allowedFormats)) {
        $format = 'csv';
    }
    
        
    $whereConditions = [];
    $params = [];
    
        if (!empty($search)) {
        $whereConditions[] = "(o.order_number LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
        $searchParam = "%" . escapeLike($search) . "%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
        if ($status) {
        $whereConditions[] = "o.status = ?";
        $params[] = $status;
    }
    
    if ($payment_status) {
        $whereConditions[] = "o.payment_status = ?";
        $params[] = $payment_status;
    }
    
    if ($user_id > 0) {
        $whereConditions[] = "o.user_id = ?";
        $params[] = $user_id;
    }
    
    if ($date_from) {
        $whereConditions[] = "DATE(o.created_at) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $whereConditions[] = "DATE(o.created_at) <= ?";
        $params[] = $date_to;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
        
    if (isset($_GET['download']) && $_GET['download'] === '1') {
        
                $stmt = $db->prepare("
            SELECT o.order_number, o.status, o.payment_status, o.currency,
                   o.subtotal, o.tax_amount, o.discount_amount, o.total_amount,
                   o.coupon_code, o.payment_method, o.payment_reference,
                   o.created_at, o.updated_at, o.delivered_at, o.cancelled_at, o.refunded_at,
                   u.username, u.email, u.first_name, u.last_name, u.phone,
                   o.billing_address, o.shipping_address, o.notes,
                   COUNT(DISTINCT oi.id) as items_count,
                   COUNT(DISTINCT s.id) as subscriptions_count,
                   GROUP_CONCAT(DISTINCT oi.product_name SEPARATOR '; ') as products_list,
                   GROUP_CONCAT(DISTINCT oi.quantity SEPARATOR '; ') as quantities_list,
                   GROUP_CONCAT(DISTINCT oi.unit_price SEPARATOR '; ') as unit_prices_list,
                   GROUP_CONCAT(DISTINCT oi.delivery_status SEPARATOR '; ') as delivery_statuses_list
            FROM orders o
            LEFT JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN subscriptions s ON oi.id = s.order_item_id
            $whereClause
            GROUP BY o.id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
                $adminAuth->logAction('orders_exported', 'order', null, null, [
            'format' => $format,
            'count' => count($orders),
            'filters' => compact('search', 'status', 'payment_status', 'date_from', 'date_to', 'user_id')
        ]);
        
                $filename = 'commandes_' . date('Y-m-d_H-i-s');
        
        if ($format === 'csv') {
                        
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
                        $output = fopen('php://output', 'w');
            
                        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
                        $headers = [
                'Numéro commande',
                'Statut commande',
                'Statut paiement',
                'Devise',
                'Sous-total',
                'Taxes',
                'Réduction',
                'Total',
                'Code coupon',
                'Méthode paiement',
                'Référence paiement',
                'Username client',
                'Email client',
                'Prénom client',
                'Nom client',
                'Téléphone client',
                'Nombre articles',
                'Nombre abonnements',
                'Liste produits',
                'Quantités',
                'Prix unitaires',
                'Statuts livraison',
                'Adresse facturation',
                'Adresse livraison',
                'Notes',
                'Date création',
                'Date mise à jour',
                'Date livraison',
                'Date annulation',
                'Date remboursement'
            ];
            
            fputcsv($output, $headers, ';');
            
                        foreach ($orders as $order) {
                $billingAddress = json_decode($order['billing_address'] ?? '{}', true);
                $shippingAddress = json_decode($order['shipping_address'] ?? '{}', true);
                
                $billingStr = '';
                if (!empty($billingAddress)) {
                    $billingStr = implode(', ', array_filter([
                        $billingAddress['first_name'] ?? '',
                        $billingAddress['last_name'] ?? '',
                        $billingAddress['address_line1'] ?? '',
                        $billingAddress['address_line2'] ?? '',
                        $billingAddress['postal_code'] ?? '',
                        $billingAddress['city'] ?? '',
                        $billingAddress['country'] ?? ''
                    ]));
                }
                
                $shippingStr = '';
                if (!empty($shippingAddress)) {
                    $shippingStr = implode(', ', array_filter([
                        $shippingAddress['first_name'] ?? '',
                        $shippingAddress['last_name'] ?? '',
                        $shippingAddress['address_line1'] ?? '',
                        $shippingAddress['address_line2'] ?? '',
                        $shippingAddress['postal_code'] ?? '',
                        $shippingAddress['city'] ?? '',
                        $shippingAddress['country'] ?? ''
                    ]));
                }
                
                $row = [
                    $order['order_number'],
                    $order['status'],
                    $order['payment_status'],
                    $order['currency'],
                    number_format($order['subtotal'], 2, ',', ''),
                    number_format($order['tax_amount'], 2, ',', ''),
                    number_format($order['discount_amount'], 2, ',', ''),
                    number_format($order['total_amount'], 2, ',', ''),
                    $order['coupon_code'] ?? '',
                    $order['payment_method'] ?? '',
                    $order['payment_reference'] ?? '',
                    $order['username'] ?? '',
                    $order['email'] ?? '',
                    $order['first_name'] ?? '',
                    $order['last_name'] ?? '',
                    $order['phone'] ?? '',
                    $order['items_count'],
                    $order['subscriptions_count'],
                    $order['products_list'] ?? '',
                    $order['quantities_list'] ?? '',
                    $order['unit_prices_list'] ?? '',
                    $order['delivery_statuses_list'] ?? '',
                    $billingStr,
                    $shippingStr,
                    str_replace(["\r", "\n"], ' ', $order['notes'] ?? ''),
                    $order['created_at'] ? date('d/m/Y H:i:s', strtotime($order['created_at'])) : '',
                    $order['updated_at'] ? date('d/m/Y H:i:s', strtotime($order['updated_at'])) : '',
                    $order['delivered_at'] ? date('d/m/Y H:i:s', strtotime($order['delivered_at'])) : '',
                    $order['cancelled_at'] ? date('d/m/Y H:i:s', strtotime($order['cancelled_at'])) : '',
                    $order['refunded_at'] ? date('d/m/Y H:i:s', strtotime($order['refunded_at'])) : ''
                ];
                
                fputcsv($output, $row, ';');
            }
            
            fclose($output);
            exit();
            
        } else {
                        
                        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
            
            echo "\xEF\xBB\xBF";             
                        echo "<table border='1'>\n";
            echo "<tr style='background-color: #f0f0f0; font-weight: bold;'>\n";
            echo "<th>Numéro commande</th>";
            echo "<th>Statut commande</th>";
            echo "<th>Statut paiement</th>";
            echo "<th>Total (€)</th>";
            echo "<th>Client</th>";
            echo "<th>Email</th>";
            echo "<th>Articles</th>";
            echo "<th>Date création</th>";
            echo "<th>Notes</th>";
            echo "</tr>\n";
            
            foreach ($orders as $order) {
                echo "<tr>\n";
                echo "<td>" . htmlspecialchars($order['order_number']) . "</td>";
                echo "<td>" . htmlspecialchars($order['status']) . "</td>";
                echo "<td>" . htmlspecialchars($order['payment_status']) . "</td>";
                echo "<td>" . number_format($order['total_amount'], 2) . "</td>";
                echo "<td>" . htmlspecialchars(($order['first_name'] ?? '') . ' ' . ($order['last_name'] ?? '')) . "</td>";
                echo "<td>" . htmlspecialchars($order['email'] ?? '') . "</td>";
                echo "<td>" . $order['items_count'] . "</td>";
                echo "<td>" . ($order['created_at'] ? date('d/m/Y H:i', strtotime($order['created_at'])) : '') . "</td>";
                echo "<td>" . htmlspecialchars(substr($order['notes'] ?? '', 0, 100)) . "</td>";
                echo "</tr>\n";
            }
            
            echo "</table>\n";
            exit();
        }
    }
    
        
        $countStmt = $db->prepare("SELECT COUNT(DISTINCT o.id) FROM orders o LEFT JOIN users u ON o.user_id = u.id $whereClause");
    $countStmt->execute($params);
    $totalExportOrders = $countStmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Admin orders export error: " . $e->getMessage());
    $error = "Erreur lors de la préparation de l'export";
    $totalExportOrders = 0;
}

$seo = [
    'title' => 'Export des commandes - Admin Pilote Shopping',
    'description' => 'Export des commandes en CSV/Excel',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 lg:px-6 py-8">

        <div class="mb-6">
            <nav class="flex items-center space-x-2 text-sm text-gray-400">
                <a href="/admin/" class="hover:text-white transition-colors">Admin</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <a href="/admin/orders/" class="hover:text-white transition-colors">Commandes</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <span class="text-white">Export</span>
            </nav>
        </div>

        <div class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                <i class="fas fa-download text-blue-400 mr-3"></i>
                Export des commandes
            </h1>
            <p class="text-gray-400">
                Exportez les commandes selon vos critères de filtrage
            </p>
        </div>

<?php if (isset($error)): ?>
        <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-xl">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-8">

            <div class="mb-8 p-4 bg-blue-500/10 border border-blue-500/30 rounded-xl">
                <div class="flex items-start space-x-3">
                    <i class="fas fa-info-circle text-blue-400 text-xl mt-1"></i>
                    <div>
                        <h3 class="text-blue-300 font-semibold mb-2">Informations sur l'export</h3>
                        <div class="text-blue-200 text-sm space-y-1">
                            <p><strong><?= number_format($totalExportOrders) ?></strong> commande(s) seront exportées avec les filtres actuels</p>
                            <p>Les formats disponibles sont CSV (recommandé pour Excel) et Excel simple</p>
                            <p>L'export inclut toutes les informations des commandes, articles et adresses</p>
                        </div>
                    </div>
                </div>
            </div>

            <form method="GET" action="/admin/orders/export.php">
                <input type="hidden" name="download" value="1">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">

                    <div>
                        <h3 class="text-xl font-bold text-white mb-6">
                            <i class="fas fa-filter text-green-400 mr-2"></i>
                            Filtres de données
                        </h3>

                        <div class="space-y-4">

                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Recherche</label>
                                <input type="text" 
                                       name="search" 
                                       value="<?= htmlspecialchars($search) ?>"
                                       placeholder="Numéro commande, client, email..." 
                                       class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                            </div>

                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Statut commande</label>
                                <select name="status" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500">
                                    <option value="">Tous les statuts</option>
                                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>En attente</option>
                                    <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>En cours</option>
                                    <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Terminées</option>
                                    <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Annulées</option>
                                    <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Remboursées</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Statut paiement</label>
                                <select name="payment_status" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500">
                                    <option value="">Tous paiements</option>
                                    <option value="pending" <?= $payment_status === 'pending' ? 'selected' : '' ?>>En attente</option>
                                    <option value="paid" <?= $payment_status === 'paid' ? 'selected' : '' ?>>Payé</option>
                                    <option value="failed" <?= $payment_status === 'failed' ? 'selected' : '' ?>>Échoué</option>
                                    <option value="refunded" <?= $payment_status === 'refunded' ? 'selected' : '' ?>>Remboursé</option>
                                </select>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-300 font-medium mb-2">Date début</label>
                                    <input type="date" 
                                           name="date_from" 
                                           value="<?= htmlspecialchars($date_from) ?>"
                                           class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-gray-300 font-medium mb-2">Date fin</label>
                                    <input type="date" 
                                           name="date_to" 
                                           value="<?= htmlspecialchars($date_to) ?>"
                                           class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500">
                                </div>
                            </div>

                            <div>
                                <label class="block text-gray-300 font-medium mb-2">Raccourcis</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button type="button" onclick="setDateRange('today')" 
                                            class="bg-dark-600 hover:bg-dark-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                                        Aujourd'hui
                                    </button>
                                    <button type="button" onclick="setDateRange('week')" 
                                            class="bg-dark-600 hover:bg-dark-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                                        Cette semaine
                                    </button>
                                    <button type="button" onclick="setDateRange('month')" 
                                            class="bg-dark-600 hover:bg-dark-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                                        Ce mois
                                    </button>
                                    <button type="button" onclick="setDateRange('year')" 
                                            class="bg-dark-600 hover:bg-dark-500 text-white px-3 py-2 rounded-lg text-sm transition-colors">
                                        Cette année
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-xl font-bold text-white mb-6">
                            <i class="fas fa-cog text-orange-400 mr-2"></i>
                            Options d'export
                        </h3>

                        <div class="space-y-6">

                            <div>
                                <label class="block text-gray-300 font-medium mb-3">Format d'export</label>
                                <div class="space-y-3">
                                    <label class="flex items-center space-x-3 cursor-pointer p-3 bg-dark-700/50 rounded-lg hover:bg-dark-700 transition-colors">
                                        <input type="radio" name="format" value="csv" <?= $format === 'csv' ? 'checked' : '' ?>
                                               class="text-blue-600 focus:ring-blue-500 focus:ring-2">
                                        <div class="flex-1">
                                            <div class="text-white font-medium">CSV (Recommandé)</div>
                                            <div class="text-gray-400 text-sm">Compatible Excel, LibreOffice, format standard</div>
                                        </div>
                                        <i class="fas fa-file-csv text-green-400 text-xl"></i>
                                    </label>

                                    <label class="flex items-center space-x-3 cursor-pointer p-3 bg-dark-700/50 rounded-lg hover:bg-dark-700 transition-colors">
                                        <input type="radio" name="format" value="excel" <?= $format === 'excel' ? 'checked' : '' ?>
                                               class="text-blue-600 focus:ring-blue-500 focus:ring-2">
                                        <div class="flex-1">
                                            <div class="text-white font-medium">Excel Simple</div>
                                            <div class="text-gray-400 text-sm">Format XLS basique pour Microsoft Excel</div>
                                        </div>
                                        <i class="fas fa-file-excel text-green-400 text-xl"></i>
                                    </label>
                                </div>
                            </div>

                            <div class="p-4 bg-dark-700/30 rounded-xl">
                                <h4 class="text-white font-medium mb-3">
                                    <i class="fas fa-eye text-blue-400 mr-2"></i>
                                    Aperçu des données incluses
                                </h4>
                                <div class="text-sm text-gray-300 space-y-1">
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-check text-green-400 text-xs"></i>
                                        <span>Informations commande (numéro, statuts, montants)</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-check text-green-400 text-xs"></i>
                                        <span>Données client (nom, email, téléphone)</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-check text-green-400 text-xs"></i>
                                        <span>Articles commandés (produits, quantités, prix)</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-check text-green-400 text-xs"></i>
                                        <span>Adresses de facturation et livraison</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-check text-green-400 text-xs"></i>
                                        <span>Dates importantes (création, livraison, etc.)</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <i class="fas fa-check text-green-400 text-xs"></i>
                                        <span>Notes administrateur</span>
                                    </div>
                                </div>
                            </div>

                            <div class="p-4 bg-orange-500/10 border border-orange-500/30 rounded-xl">
                                <div class="flex items-start space-x-2">
                                    <i class="fas fa-exclamation-triangle text-orange-400 text-sm mt-1"></i>
                                    <div class="text-orange-200 text-sm">
                                        <p class="font-medium mb-1">Important :</p>
                                        <ul class="space-y-1 text-xs">
                                            <li>• Les données exportées contiennent des informations sensibles</li>
                                            <li>• Respectez la confidentialité des données clients</li>
                                            <li>• L'export est limité à 10 000 commandes maximum</li>
                                            <li>• Cette action sera enregistrée dans les logs admin</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-dark-600">
                    <a href="/admin/orders/" 
                       class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Retour aux commandes
                    </a>

                    <div class="flex items-center space-x-4">
                        <div class="text-gray-400 text-sm">
                            <strong><?= number_format($totalExportOrders) ?></strong> commande(s) à exporter
                        </div>

                        <button type="submit" 
                                class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-3 rounded-xl font-medium transition-colors"
<?= $totalExportOrders === 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-download mr-2"></i>Télécharger l'export
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<script>
function setDateRange(period) {
    const today = new Date();
    const dateFromInput = document.querySelector('input[name="date_from"]');
    const dateToInput = document.querySelector('input[name="date_to"]');

    let dateFrom, dateTo;

    switch (period) {
        case 'today':
            dateFrom = dateTo = today;
            break;

        case 'week':
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay() + 1); // Lundi
            dateFrom = startOfWeek;
            dateTo = today;
            break;

        case 'month':
            dateFrom = new Date(today.getFullYear(), today.getMonth(), 1);
            dateTo = today;
            break;

        case 'year':
            dateFrom = new Date(today.getFullYear(), 0, 1);
            dateTo = today;
            break;

        default:
            return;
    }

    dateFromInput.value = dateFrom.toISOString().split('T')[0];
    dateToInput.value = dateTo.toISOString().split('T')[0];

    dateFromInput.dispatchEvent(new Event('change'));
}

document.addEventListener('DOMContentLoaded', function() {
    const filterInputs = document.querySelectorAll('input, select');

    filterInputs.forEach(input => {
        input.addEventListener('change', function() {
        });
    });
});

document.querySelector('form').addEventListener('submit', function(e) {
    const totalOrders = <?= $totalExportOrders ?>;

    if (totalOrders === 0) {
        e.preventDefault();
        alert('Aucune commande ne correspond à vos critères de filtrage.');
        return false;
    }

    if (totalOrders > 10000) {
        if (!confirm(`Vous êtes sur le point d'exporter ${totalOrders.toLocaleString()} commandes. Cela peut prendre plusieurs minutes. Continuer ?`)) {
            e.preventDefault();
            return false;
        }
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Génération en cours...';

    setTimeout(() => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-download mr-2"></i>Télécharger l\'export';
    }, 10000);
});
</script>

<style>

button[onclick^="setDateRange"] {
    transition: all 0.2s ease;
}

button[onclick^="setDateRange"]:hover {
    transform: translateY(-1px);
}

button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

input[type="radio"] {
    width: 1.25rem;
    height: 1.25rem;
}

@media (max-width: 768px) {
    .grid.grid-cols-1.lg\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }

    .flex.items-center.justify-between {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
    }
}
</style>

<?php
endHTMLPage();
?>