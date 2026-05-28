<?php

define('API_ACCESS', true);
require_once '../config.php';
require_once 'MailerManager.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit();
}

class EmailSendAPI {
    private $mailer;
    private $db;
    private $rateLimiter;
    
    public function __construct() {
        $this->mailer = new MailerManager();
        $this->db = Database::getInstance();
        $this->rateLimiter = new EmailRateLimiter();
    }
    
    public function processRequest() {
        try {
                        $this->authenticate();
            
                        $requestData = $this->getRequestData();
            $this->validateRequest($requestData);
            
                        $this->checkRateLimit($requestData);
            
                        if (isset($requestData['template'])) {
                return $this->sendTemplateEmail($requestData);
            } else {
                return $this->sendCustomEmail($requestData);
            }
            
        } catch (Exception $e) {
            return $this->handleError($e);
        }
    }
    
    private function authenticate() {
                $authenticated = false;
        
                if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (isset($_SESSION['user_id'])) {
            $authenticated = true;
        }
        
                $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_POST['api_key'] ?? null;
        if (!$authenticated && $apiKey) {
            $authenticated = $this->validateAPIKey($apiKey);
        }
        
        if (!$authenticated) {
            throw new Exception('Authentication required', 401);
        }
    }
    
    private function validateAPIKey($apiKey) {
                $stmt = $this->db->prepare("
            SELECT user_id FROM api_keys 
            WHERE api_key = ? AND is_active = 1 AND expires_at > NOW()
        ");
        $stmt->execute([hash('sha256', $apiKey)]);
        $result = $stmt->fetch();
        
        if ($result) {
            $_SESSION['api_user_id'] = $result['user_id'];
            return true;
        }
        
        return false;
    }
    
    private function getRequestData() {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            throw new Exception('Request body is empty');
        }
        
        $data = json_decode($input, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON: ' . json_last_error_msg());
        }
        
        return $data;
    }
    
    private function validateRequest($data) {
                $required = ['to_email'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Field '$field' is required");
            }
        }
        
                if (!filter_var($data['to_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
                if (isset($data['template'])) {
            if (empty($data['template'])) {
                throw new Exception('Template slug is required');
            }
        } else {
            if (empty($data['subject']) || empty($data['content'])) {
                throw new Exception('Subject and content are required for custom emails');
            }
        }
        
                if (isset($data['subject']) && strlen($data['subject']) > 500) {
            throw new Exception('Subject is too long (max 500 characters)');
        }
        
        if (isset($data['content']) && strlen($data['content']) > 1000000) {
            throw new Exception('Content is too long (max 1MB)');
        }
    }
    
    private function checkRateLimit($data) {
        $userId = $_SESSION['user_id'] ?? $_SESSION['api_user_id'] ?? null;
        $email = $data['to_email'];
        
                if ($userId && !$this->rateLimiter->checkUserLimit($userId)) {
            throw new Exception('User rate limit exceeded', 429);
        }
        
                if (!$this->rateLimiter->checkEmailLimit($email)) {
            throw new Exception('Recipient rate limit exceeded', 429);
        }
        
                if (!$this->rateLimiter->checkGlobalLimit()) {
            throw new Exception('Global rate limit exceeded', 429);
        }
    }
    
    private function sendTemplateEmail($data) {
        $result = $this->mailer->sendTemplate(
            $data['template'],
            $data['to_email'],
            $data['to_name'] ?? '',
            $data['variables'] ?? [],
            [
                'from_email' => $data['from_email'] ?? null,
                'from_name' => $data['from_name'] ?? null,
                'reply_to' => $data['reply_to'] ?? null,
                'priority' => $data['priority'] ?? 3,
                'tracking' => $data['tracking'] ?? true,
                'category' => $data['category'] ?? 'transactional'
            ]
        );
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'email_id' => $result['email_id'],
                'message_id' => $result['message_id']
            ];
        } else {
            throw new Exception($result['error']);
        }
    }
    
    private function sendCustomEmail($data) {
        $result = $this->mailer->sendCustom(
            $data['to_email'],
            $data['to_name'] ?? '',
            $data['subject'],
            $data['content'],
            $data['text_content'] ?? null,
            [
                'from_email' => $data['from_email'] ?? null,
                'from_name' => $data['from_name'] ?? null,
                'reply_to' => $data['reply_to'] ?? null,
                'priority' => $data['priority'] ?? 3,
                'tracking' => $data['tracking'] ?? true,
                'category' => $data['category'] ?? 'transactional'
            ]
        );
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Email sent successfully',
                'email_id' => $result['email_id'],
                'message_id' => $result['message_id']
            ];
        } else {
            throw new Exception($result['error']);
        }
    }
    
    private function handleError($exception) {
        $statusCode = method_exists($exception, 'getCode') ? $exception->getCode() : 500;
        $statusCode = in_array($statusCode, [400, 401, 403, 404, 409, 422, 429]) ? $statusCode : 500;
        
        http_response_code($statusCode);
        
                logSecurityEvent(
            $_SESSION['user_id'] ?? $_SESSION['api_user_id'] ?? null,
            'email_api_error',
            [
                'error' => $exception->getMessage(),
                'code' => $statusCode,
                'endpoint' => '/api/v1/mail/send'
            ],
            'failed'
        );
        
        return [
            'success' => false,
            'error' => DEBUG_MODE ? $exception->getMessage() : 'An error occurred while sending email'
        ];
    }
}

class EmailRateLimiter {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function checkUserLimit($userId, $limit = 100, $period = 3600) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM email_sends 
            WHERE (user_id = ? OR from_email IN (
                SELECT email FROM users WHERE id = ?
            ))
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$userId, $userId, $period]);
        
        return $stmt->fetchColumn() < $limit;
    }
    
    public function checkEmailLimit($email, $limit = 5, $period = 3600) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM email_sends 
            WHERE to_email = ? 
            AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$email, $period]);
        
        return $stmt->fetchColumn() < $limit;
    }
    
    public function checkGlobalLimit($limit = 1000, $period = 3600) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM email_sends 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$period]);
        
        return $stmt->fetchColumn() < $limit;
    }
}

try {
    $api = new EmailSendAPI();
    $result = $api->processRequest();
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Internal server error'
    ]);
}
?>