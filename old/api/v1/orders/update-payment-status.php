<?php

define('API_ACCESS', true);
require_once '../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

http_response_code(410);
echo json_encode([
    'success' => false,
    'error' => 'Cet endpoint a été désactivé pour des raisons de sécurité. Les statuts de paiement sont mis à jour exclusivement via le webhook Stripe signé (api/v1/payments/stripe/webhook.php).',
    'error_code' => 'ENDPOINT_DISABLED'
]);

logSecurityEvent(
    $_SESSION['user_id'] ?? null,
    'disabled_endpoint_called',
    [
        'endpoint' => 'update-payment-status',
        'method' => $_SERVER['REQUEST_METHOD'],
        'remote_ip' => getRealIP()
    ],
    'blocked'
);
exit;
