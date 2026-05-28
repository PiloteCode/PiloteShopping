<?php

define('API_ACCESS', true);
require_once '../../config.php';

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
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    $db = Database::getInstance();
    
        $stmt = $db->prepare("
        SELECT config, is_active 
        FROM payment_adapters 
        WHERE slug = 'stripe'
    ");
    $stmt->execute();
    $adapter = $stmt->fetch();
    
    if (!$adapter) {
        throw new Exception('Stripe non configuré');
    }
    
    if (!$adapter['is_active']) {
        throw new Exception('Stripe désactivé');
    }
    
    $config = json_decode($adapter['config'], true);
    
    if (empty($config['publishable_key'])) {
        throw new Exception('Clé publique Stripe manquante');
    }
    
        echo json_encode([
        'success' => true,
        'publishable_key' => $config['publishable_key'],
        'supported_currencies' => ['eur', 'usd', 'gbp'],
        'country' => 'FR'
    ]);
    
} catch (Exception $e) {
    logSecurityEvent(null, 'stripe_config_error', [
        'error' => $e->getMessage()
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Configuration non disponible'
    ]);
}
?>