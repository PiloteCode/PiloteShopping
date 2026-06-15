<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once __DIR__ . '/../../../includes/AuthMiddleware.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$auth = initAuth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentification requise', 'code' => 'AUTH_REQUIRED']);
    exit();
}
$currentUser = $auth->getCurrentUser();

class OrderStatusManager {
    private $db;
    private $userId;

    public function __construct($userId) {
        $this->db = Database::getInstance();
        $this->userId = $userId;
    }

    public function checkOrderStatus($orderNumber) {
        try {
            $order = $this->getOrderDetails($orderNumber);
            if (!$order) {
                return ['success' => false, 'error' => 'Commande non trouvée', 'code' => 404];
            }
            if ((int)$order['user_id'] !== (int)$this->userId) {
                return ['success' => false, 'error' => 'Accès non autorisé', 'code' => 403];
            }
            $deliveryStats = $this->getDeliveryStats($order['id']);
            return [
                'success' => true,
                'order_number' => $order['order_number'],
                'status' => $order['status'],
                'payment_status' => $order['payment_status'],
                'transaction_status' => $order['transaction_status'],
                'payment_method' => $order['payment_method_name'],
                'total_amount' => floatval($order['total_amount']),
                'delivery_stats' => $deliveryStats,
                'created_at' => $order['created_at'],
                'updated' => false
            ];
        } catch (Exception $e) {
            logSecurityEvent($this->userId, 'order_status_check_error', [
                'order_number' => $orderNumber,
                'error' => $e->getMessage()
            ], 'failed');
            return ['success' => false, 'error' => 'Erreur serveur', 'code' => 500];
        }
    }

    private function getOrderDetails($orderNumber) {
        $stmt = $this->db->prepare("
            SELECT o.id, o.order_number, o.user_id, o.status, o.payment_status,
                   o.total_amount, o.created_at, o.payment_method,
                   pt.status as transaction_status, pt.gateway_transaction_id,
                   pt.payment_adapter_id, pt.metadata as transaction_metadata,
                   pa.name as payment_method_name, pa.slug as payment_method_slug
            FROM orders o
            LEFT JOIN payment_transactions pt ON o.id = pt.order_id
            LEFT JOIN payment_adapters pa ON pt.payment_adapter_id = pa.id
            WHERE o.order_number = ?
            ORDER BY pt.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$orderNumber]);
        return $stmt->fetch();
    }

    private function getDeliveryStats($orderId) {
        $stmt = $this->db->prepare("
            SELECT delivery_status, COUNT(*) as count
            FROM order_items
            WHERE order_id = ?
            GROUP BY delivery_status
        ");
        $stmt->execute([$orderId]);
        $stats = [];
        while ($row = $stmt->fetch()) {
            $stats[$row['delivery_status']] = intval($row['count']);
        }
        return $stats;
    }
}

try {
    $orderNumber = $_GET['order'] ?? '';
    if (empty($orderNumber)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Numéro de commande requis']);
        exit();
    }
    if (!preg_match('/^PS\d{8}-[A-Z0-9]{6,12}$/', $orderNumber)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Format de numéro de commande invalide']);
        exit();
    }
    $statusManager = new OrderStatusManager($currentUser['id']);
    $result = $statusManager->checkOrderStatus($orderNumber);
    $httpCode = 200;
    if (!$result['success']) {
        $httpCode = $result['code'] ?? 500;
    }
    http_response_code($httpCode);
    unset($result['code']);
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Order Status API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur']);
}
