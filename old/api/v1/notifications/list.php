<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once '../../../includes/AuthMiddleware.php';
require_once '../../../includes/NotificationManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentification requise']);
    exit;
}

try {
    $manager = new NotificationManager();
    $userId = $currentUser['id'];

        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $unreadOnly = isset($_GET['unread']) && $_GET['unread'] === 'true';

        $notifications = $manager->getUserNotifications($userId, $limit, $offset, $unreadOnly);
    $unreadCount = $manager->getUnreadCount($userId);

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
        'total' => count($notifications)
    ]);

} catch (Exception $e) {
    error_log("Notifications list error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur lors de la récupération des notifications',
        'debug' => DEBUG_MODE ? [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ]);
}
