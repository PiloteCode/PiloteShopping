<?php

if (!class_exists('CsrfProtection')) {
    require_once __DIR__ . '/../../includes/CsrfProtection.php';
}

class AdminAuthMiddleware {
    private $db;
    private $currentUser;
    private $currentAdmin;
    private $userAuth;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->userAuth = initAuth();
    }

    public function requireAdminAuth($allowedRoles = ['admin', 'super_admin', 'moderator', 'support']) {
        try {
            $this->userAuth->requireAuth();
            $this->currentUser = $this->userAuth->getCurrentUser();

            if (!$this->currentUser) {
                $this->redirectToLogin('Connexion requise');
                return false;
            }

            $this->currentAdmin = $this->getAdminPrivileges($this->currentUser['id']);

            if (!$this->currentAdmin) {
                $this->accessDenied('Privilèges administrateur requis');
                return false;
            }

            if (!$this->currentAdmin['is_active']) {
                $this->accessDenied('Compte administrateur désactivé');
                return false;
            }

            if (!in_array($this->currentAdmin['role'], $allowedRoles)) {
                $this->accessDenied('Privilèges insuffisants pour cette action');
                return false;
            }

            $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
            if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
                if (!\CsrfProtection::validate()) {
                    logSecurityEvent($this->currentUser['id'] ?? null, 'admin_csrf_blocked', [
                        'uri' => $_SERVER['REQUEST_URI'] ?? '',
                        'method' => $method
                    ], 'blocked');
                    $this->accessDenied('CSRF token invalide ou manquant');
                    return false;
                }
            }

            $this->updateLastAdminLogin();
            $this->logAdminAccess($_SERVER['REQUEST_URI']);

            return true;

        } catch (Exception $e) {
            error_log("Admin auth error: " . $e->getMessage());
            $this->accessDenied('Erreur d\'authentification');
            return false;
        }
    }
    
        private function getAdminPrivileges($userId) {
        $stmt = $this->db->prepare("
            SELECT au.*, u.username, u.email, u.first_name, u.last_name, u.avatar_url
            FROM admin_users au
            JOIN users u ON au.user_id = u.id
            WHERE au.user_id = ? AND au.is_active = 1
        ");
        $stmt->execute([$userId]);
        
        return $stmt->fetch();
    }
    
        public function hasPermission($permission) {
        if (!$this->currentAdmin) {
            return false;
        }
        
                if ($this->currentAdmin['role'] === 'super_admin') {
            return true;
        }
        
                $permissions = json_decode($this->currentAdmin['permissions'] ?? '[]', true);
        
        return in_array($permission, $permissions);
    }
    
        public function canAccess($resource, $action = 'read') {
        if (!$this->currentAdmin) {
            return false;
        }
        
                $rolePermissions = [
            'super_admin' => ['*'],             'admin' => [
                'users.read', 'users.create', 'users.update', 'users.delete',
                'products.read', 'products.create', 'products.update', 'products.delete',
                'orders.read', 'orders.update',
                'subscriptions.read', 'subscriptions.update',
                'settings.read', 'settings.update',
                'logs.read'
            ],
            'moderator' => [
                'users.read', 'users.update',
                'products.read', 'products.update',
                'orders.read',
                'subscriptions.read'
            ],
            'support' => [
                'users.read',
                'orders.read', 'orders.update',
                'subscriptions.read'
            ]
        ];
        
        $permission = "$resource.$action";
        $userPermissions = $rolePermissions[$this->currentAdmin['role']] ?? [];
        
        return in_array('*', $userPermissions) || in_array($permission, $userPermissions);
    }
    
        private function updateLastAdminLogin() {
        try {
            $stmt = $this->db->prepare("
                UPDATE admin_users 
                SET last_login_at = NOW(), login_count = login_count + 1
                WHERE id = ?
            ");
            $stmt->execute([$this->currentAdmin['id']]);
        } catch (Exception $e) {
            error_log("Error updating admin login: " . $e->getMessage());
        }
    }
    
        private function logAdminAccess($resource) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO admin_logs (admin_id, action, resource_type, ip_address, user_agent, severity)
                VALUES (?, 'access', ?, ?, ?, 'low')
            ");
            
            $stmt->execute([
                $this->currentAdmin['id'],
                $resource,
                getRealIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            error_log("Error logging admin access: " . $e->getMessage());
        }
    }
    
        public function logAction($action, $resourceType = null, $resourceId = null, $oldData = null, $newData = null, $severity = 'medium') {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO admin_logs (admin_id, action, resource_type, resource_id, old_data, new_data, ip_address, user_agent, severity)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->currentAdmin['id'],
                $action,
                $resourceType,
                $resourceId,
                $oldData ? json_encode($oldData) : null,
                $newData ? json_encode($newData) : null,
                getRealIP(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $severity
            ]);
        } catch (Exception $e) {
            error_log("Error logging admin action: " . $e->getMessage());
        }
    }
    
        public function getCurrentUser() {
        return $this->currentUser;
    }
    
        public function getCurrentAdmin() {
        return $this->currentAdmin;
    }
    
        private function redirectToLogin($message = null) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        $url = "/auth/login?redirect=$redirect";
        
        if ($message) {
            $url .= "&error=" . urlencode($message);
        }
        
        header("Location: $url");
        exit();
    }
    
        private function accessDenied($message = 'Accès refusé') {
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => $message,
                'error_code' => 'ACCESS_DENIED'
            ]);
            exit();
        } else {
                        http_response_code(403);
            $this->showAccessDeniedPage($message);
            exit();
        }
    }
    
        private function isApiRequest() {
        return strpos($_SERVER['REQUEST_URI'], '/api/') !== false ||
               strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false ||
               !empty($_SERVER['HTTP_X_REQUESTED_WITH']);
    }
    
        private function showAccessDeniedPage($message) {
        ?>
        <!DOCTYPE html>
        <html lang="fr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Accès refusé - Administration</title>
            <script src="https://cdn.tailwindcss.com"></script>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
            <style>
                body { background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%); }
            </style>
        </head>
        <body class="min-h-screen flex items-center justify-center">
            <div class="max-w-md w-full mx-4">
                <div class="bg-gray-800 rounded-2xl border border-gray-700 p-8 text-center">
                    <div class="w-20 h-20 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                        <i class="fas fa-shield-alt text-red-400 text-3xl"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-white mb-4">Accès refusé</h1>
                    <p class="text-gray-400 mb-8"><?= htmlspecialchars($message) ?></p>
                    <div class="space-y-3">
                        <a href="/dashboard" class="block w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl transition-colors">
                            <i class="fas fa-arrow-left mr-2"></i>Retour au tableau de bord
                        </a>
                        <a href="/auth/logout" class="block w-full bg-gray-700 hover:bg-gray-600 text-white font-medium py-3 px-6 rounded-xl transition-colors">
                            Se déconnecter
                        </a>
                    </div>
                </div>
            </div>
        </body>
        </html>
<?php
    }
    
        public function createNotification($type, $title, $message, $targetRole = 'admin', $data = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO admin_notifications (type, title, message, data, target_role, created_by)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $type,
                $title,
                $message,
                $data ? json_encode($data) : null,
                $targetRole,
                $this->currentAdmin['id']
            ]);
        } catch (Exception $e) {
            error_log("Error creating admin notification: " . $e->getMessage());
            return false;
        }
    }
    
        public function markNotificationAsRead($notificationId) {
        try {
            $stmt = $this->db->prepare("
                INSERT IGNORE INTO admin_notification_reads (notification_id, admin_id)
                VALUES (?, ?)
            ");
            
            return $stmt->execute([$notificationId, $this->currentAdmin['id']]);
        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
        public function isSuperAdmin() {
        return $this->currentAdmin && $this->currentAdmin['role'] === 'super_admin';
    }
    
        public function getAdminStats($adminId = null) {
        $adminId = $adminId ?? $this->currentAdmin['id'];
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_actions,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 END) as actions_24h,
                    COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as actions_7d,
                    MAX(created_at) as last_action
                FROM admin_logs 
                WHERE admin_id = ?
            ");
            $stmt->execute([$adminId]);
            
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error getting admin stats: " . $e->getMessage());
            return null;
        }
    }
}

function initAdminAuth() {
    static $adminAuth = null;
    
    if ($adminAuth === null) {
        $adminAuth = new AdminAuthMiddleware();
    }
    
    return $adminAuth;
}

function hasAdminPermission($permission) {
    $auth = initAdminAuth();
    return $auth->hasPermission($permission);
}

function canAdminAccess($resource, $action = 'read') {
    $auth = initAdminAuth();
    return $auth->canAccess($resource, $action);
}

function logAdminAction($action, $resourceType = null, $resourceId = null, $oldData = null, $newData = null, $severity = 'medium') {
    $auth = initAdminAuth();
    return $auth->logAction($action, $resourceType, $resourceId, $oldData, $newData, $severity);
}
?>