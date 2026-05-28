<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once __DIR__ . '/../../../includes/CsrfProtection.php';
require_once __DIR__ . '/../../../includes/AuthMiddleware.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

\CsrfProtection::requireValidToken();

$auth = initAuth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentification requise']);
    exit();
}
$currentUser = $auth->getCurrentUser();
$userId = (int)$currentUser['id'];
$currentSessionId = $_SESSION['session_id'] ?? '';

class SessionsManager {
    private $db;
    private $userId;
    private $currentSessionId;

    public function __construct($userId, $currentSessionId) {
        $this->db = Database::getInstance();
        $this->userId = $userId;
        $this->currentSessionId = $currentSessionId;
    }

    public function listSessions() {
        $stmt = $this->db->prepare("
            SELECT id, ip_address, user_agent, is_mobile, created_at, updated_at, expires_at
            FROM user_sessions
            WHERE user_id = ? AND expires_at > NOW()
            ORDER BY updated_at DESC
        ");
        $stmt->execute([$this->userId]);
        $rows = $stmt->fetchAll();
        $sessions = [];
        foreach ($rows as $row) {
            $sessions[] = [
                'id' => $row['id'],
                'ip_address' => $row['ip_address'],
                'user_agent' => substr($row['user_agent'] ?? '', 0, 200),
                'is_mobile' => (bool)$row['is_mobile'],
                'is_current' => hash_equals((string)$row['id'], (string)$this->currentSessionId),
                'created_at' => $row['created_at'],
                'last_active' => $row['updated_at'],
                'expires_at' => $row['expires_at']
            ];
        }
        return ['success' => true, 'sessions' => $sessions];
    }

    public function revokeSession($sessionId) {
        if (hash_equals((string)$sessionId, (string)$this->currentSessionId)) {
            return ['success' => false, 'error' => 'Impossible de révoquer la session courante depuis cet endpoint. Utilisez /auth/logout.'];
        }
        $stmt = $this->db->prepare("
            DELETE FROM user_sessions
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$sessionId, $this->userId]);
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'error' => 'Session non trouvée'];
        }
        logSecurityEvent($this->userId, 'session_revoked', ['session_id' => $sessionId], 'success');
        return ['success' => true, 'message' => 'Session révoquée'];
    }

    public function revokeAllOthers() {
        $stmt = $this->db->prepare("
            DELETE FROM user_sessions
            WHERE user_id = ? AND id != ?
        ");
        $stmt->execute([$this->userId, $this->currentSessionId]);
        $revoked = $stmt->rowCount();
        logSecurityEvent($this->userId, 'all_other_sessions_revoked', ['count' => $revoked], 'success');
        return ['success' => true, 'revoked_count' => $revoked];
    }
}

try {
    $manager = new SessionsManager($userId, $currentSessionId);
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $result = $manager->listSessions();
            break;
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $target = $input['session_id'] ?? ($_GET['session_id'] ?? '');
            if ($target === '__all_others__') {
                $result = $manager->revokeAllOthers();
            } elseif (!empty($target)) {
                $result = $manager->revokeSession($target);
            } else {
                $result = ['success' => false, 'error' => 'session_id requis'];
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit();
    }
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
} catch (Exception $e) {
    logSecurityEvent($userId, 'sessions_api_error', ['error' => $e->getMessage()], 'failed');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur']);
}
