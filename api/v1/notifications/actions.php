<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once __DIR__ . '/../../../includes/CsrfProtection.php';
require_once '../../../includes/AuthMiddleware.php';
require_once '../../../includes/NotificationManager.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

        $input = json_decode(file_get_contents('php://input'), true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Format JSON invalide']);
        exit;
    }

    $action = $input['action'] ?? null;

    if (!$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action non spécifiée']);
        exit;
    }

    switch ($action) {
        case 'mark_as_read':
            if (empty($input['notification_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de notification requis']);
                exit;
            }

            $success = $manager->markAsRead($input['notification_id'], $userId);

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification marquée comme lue' : 'Notification non trouvée'
            ]);
            break;

        case 'mark_all_read':
            $count = $manager->markAllAsRead($userId);

            echo json_encode([
                'success' => true,
                'message' => "{$count} notification(s) marquée(s) comme lue(s)",
                'count' => $count
            ]);
            break;

        case 'delete':
            if (empty($input['notification_id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID de notification requis']);
                exit;
            }

            $success = $manager->delete($input['notification_id'], $userId);

            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Notification supprimée' : 'Notification non trouvée'
            ]);
            break;

        case 'get_unread_count':
            $count = $manager->getUnreadCount($userId);

            echo json_encode([
                'success' => true,
                'unread_count' => $count
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action non reconnue: ' . $action]);
            break;
    }

} catch (Exception $e) {
    error_log("Notifications action error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur lors du traitement de la requête',
        'debug' => DEBUG_MODE ? [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ]);
}
