<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once __DIR__ . '/../../../includes/CsrfProtection.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

\CsrfProtection::requireValidToken();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

class EnhancedProductAccessManager {
    private $db;
    private $userId;
    
        private $supportedTypes = [
        'account' => [
            'icon' => 'fas fa-user-circle',
            'name' => 'Compte utilisateur',
            'description' => 'Identifiants de connexion'
        ],
        'license_key' => [
            'icon' => 'fas fa-key',
            'name' => 'Clé de licence',
            'description' => 'Clé d\'activation logiciel'
        ],
        'download_link' => [
            'icon' => 'fas fa-download',
            'name' => 'Téléchargement',
            'description' => 'Lien de téléchargement'
        ],
        'access_code' => [
            'icon' => 'fas fa-qrcode',
            'name' => 'Code d\'accès',
            'description' => 'Code de validation'
        ],
        'gift_card' => [
            'icon' => 'fas fa-gift',
            'name' => 'Carte cadeau',
            'description' => 'Bon d\'achat'
        ],
        'instructions' => [
            'icon' => 'fas fa-list-ol',
            'name' => 'Instructions',
            'description' => 'Guide d\'utilisation'
        ],
        'text' => [
            'icon' => 'fas fa-file-text',
            'name' => 'Texte simple',
            'description' => 'Contenu textuel'
        ],
        'markdown' => [
            'icon' => 'fab fa-markdown',
            'name' => 'Contenu formaté',
            'description' => 'Texte avec mise en forme'
        ],
        'url' => [
            'icon' => 'fas fa-link',
            'name' => 'Lien web',
            'description' => 'URL directe'
        ],
        'credentials' => [
            'icon' => 'fas fa-id-card',
            'name' => 'Identifiants complets',
            'description' => 'Ensemble d\'informations d\'accès'
        ],
        'serial' => [
            'icon' => 'fas fa-barcode',
            'name' => 'Numéro de série',
            'description' => 'Code produit unique'
        ],
        'activation' => [
            'icon' => 'fas fa-power-off',
            'name' => 'Code d\'activation',
            'description' => 'Code d\'activation service'
        ],
        'subscription' => [
            'icon' => 'fas fa-sync-alt',
            'name' => 'Abonnement',
            'description' => 'Détails d\'abonnement'
        ],
        'file' => [
            'icon' => 'fas fa-file-archive',
            'name' => 'Fichier',
            'description' => 'Ressource téléchargeable'
        ],
        'tutorial' => [
            'icon' => 'fas fa-graduation-cap',
            'name' => 'Tutoriel',
            'description' => 'Guide d\'apprentissage'
        ],
        'custom' => [
            'icon' => 'fas fa-cog',
            'name' => 'Contenu personnalisé',
            'description' => 'Contenu spécialisé'
        ]
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $this->userId = $_SESSION['user_id'] ?? null;
    }
    
    public function accessProduct($orderItemId) {
        try {
            if (!$this->userId) {
                throw new Exception('Authentification requise');
            }
            
                        $stmt = $this->db->prepare("
                SELECT oi.*, o.user_id, o.payment_status, o.status as order_status,
                       p.name as product_name, p.slug as product_slug
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.id = ? AND o.user_id = ?
            ");
            $stmt->execute([$orderItemId, $this->userId]);
            $item = $stmt->fetch();
            
            if (!$item) {
                throw new Exception('Produit non trouvé ou non autorisé');
            }
            
                        if ($item['payment_status'] !== 'paid') {
                throw new Exception('La commande doit être payée pour accéder au produit');
            }
            
                        if ($item['delivery_status'] !== 'delivered') {
                throw new Exception('Le produit n\'est pas encore livré');
            }
            
                        if (empty($item['delivery_data'])) {
                throw new Exception('Aucune donnée d\'accès disponible pour ce produit');
            }
            
                        $deliveryData = json_decode($item['delivery_data'], true);
            
            if (!$deliveryData || !is_array($deliveryData)) {
                throw new Exception('Données d\'accès corrompues');
            }
            
                        $processedData = $this->processDeliveryData($deliveryData);
            
                        $this->logAccess($orderItemId);
            
            return [
                'success' => true,
                'product_data' => $processedData,
                'product_name' => $item['product_name'],
                'product_slug' => $item['product_slug'],
                'access_type' => $processedData['type'],
                'type_info' => $this->getTypeInfo($processedData['type']),
                'markdown_enabled' => $processedData['markdown_enabled'] ?? false
            ];
            
        } catch (Exception $e) {
            logSecurityEvent($this->userId, 'product_access_failed', [
                'order_item_id' => $orderItemId,
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function processDeliveryData($deliveryData) {
        $type = $deliveryData['type'] ?? 'custom';
        $content = $deliveryData['content'] ?? '';
        $notes = $deliveryData['notes'] ?? '';
        $markdownEnabled = $deliveryData['markdown_enabled'] ?? false;
        
                $processed = [
            'type' => $type,
            'content' => $content,
            'notes' => $notes,
            'markdown_enabled' => $markdownEnabled,
            'delivered_at' => $deliveryData['delivered_at'] ?? null,
            'delivered_by' => $deliveryData['delivered_by'] ?? null
        ];
        
                switch ($type) {
            case 'account':
                $processed = array_merge($processed, $this->processAccountData($content));
                break;
                
            case 'license_key':
                $processed = array_merge($processed, $this->processLicenseData($content));
                break;
                
            case 'download_link':
                $processed = array_merge($processed, $this->processDownloadData($content));
                break;
                
            case 'access_code':
                $processed = array_merge($processed, $this->processAccessCodeData($content));
                break;
                
            case 'gift_card':
                $processed = array_merge($processed, $this->processGiftCardData($content));
                break;
                
            case 'credentials':
                $processed = array_merge($processed, $this->processCredentialsData($content));
                break;
                
            case 'url':
                $processed = array_merge($processed, $this->processUrlData($content));
                break;
                
            case 'serial':
                $processed = array_merge($processed, $this->processSerialData($content));
                break;
                
            case 'subscription':
                $processed = array_merge($processed, $this->processSubscriptionData($content));
                break;
                
            case 'file':
                $processed = array_merge($processed, $this->processFileData($content));
                break;
                
            default:
                                $processed['formatted_content'] = $this->formatGenericContent($content, $markdownEnabled);
                break;
        }
        
        return $processed;
    }
    
    private function processAccountData($content) {
                $data = ['formatted_content' => $content];
        
                if (preg_match('/(?:username|login|utilisateur)[:\s]*([^\r\n]+)/i', $content, $matches)) {
            $data['username'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:password|mot de passe)[:\s]*([^\r\n]+)/i', $content, $matches)) {
            $data['password'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:url|lien|site)[:\s]*(https?:\/\/[^\s\r\n]+)/i', $content, $matches)) {
            $data['url'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:email|e-mail)[:\s]*([^\s\r\n]+@[^\s\r\n]+)/i', $content, $matches)) {
            $data['email'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function processLicenseData($content) {
        $data = ['formatted_content' => $content];
        
                if (preg_match('/(?:clé|key|license)[:\s]*([A-Z0-9\-]{8,})/i', $content, $matches)) {
            $data['license_key'] = trim($matches[1]);
        }
        
                if (preg_match('/(?:serial|série)[:\s]*([A-Z0-9\-]{6,})/i', $content, $matches)) {
            $data['serial_number'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function processDownloadData($content) {
        $data = ['formatted_content' => $content];
        
                if (preg_match('/(https?:\/\/[^\s\r\n]+)/i', $content, $matches)) {
            $data['download_url'] = trim($matches[1]);
        }
        
                if (preg_match('/(?:password|mot de passe)[:\s]*([^\r\n]+)/i', $content, $matches)) {
            $data['password'] = trim($matches[1]);
        }
        
                if (preg_match('/(?:taille|size)[:\s]*([^\r\n]+)/i', $content, $matches)) {
            $data['file_size'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function processAccessCodeData($content) {
        $data = ['formatted_content' => $content];
        
                if (preg_match('/(?:code)[:\s]*([A-Z0-9]{4,})/i', $content, $matches)) {
            $data['access_code'] = trim($matches[1]);
        }
        
                if (preg_match('/(https?:\/\/[^\s\r\n]+)/i', $content, $matches)) {
            $data['validation_url'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function processGiftCardData($content) {
        $data = ['formatted_content' => $content];
        
                if (preg_match('/(?:numéro|number)[:\s]*([0-9\-]{8,})/i', $content, $matches)) {
            $data['card_number'] = trim($matches[1]);
        }
        
                if (preg_match('/(?:pin|code)[:\s]*([0-9]{3,8})/i', $content, $matches)) {
            $data['pin_code'] = trim($matches[1]);
        }
        
                if (preg_match('/(?:montant|amount)[:\s]*([0-9,\.]+)\s*€?/i', $content, $matches)) {
            $data['amount'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function processCredentialsData($content) {
        $data = ['formatted_content' => $content];
        
                $patterns = [
            'username' => '/(?:username|login|utilisateur)[:\s]*([^\r\n]+)/i',
            'password' => '/(?:password|mot de passe)[:\s]*([^\r\n]+)/i',
            'email' => '/(?:email|e-mail)[:\s]*([^\s\r\n]+@[^\s\r\n]+)/i',
            'url' => '/(?:url|site)[:\s]*(https?:\/\/[^\s\r\n]+)/i',
            'pin' => '/(?:pin)[:\s]*([0-9]{3,8})/i'
        ];
        
        foreach ($patterns as $key => $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                $data[$key] = trim($matches[1]);
            }
        }
        
        return $data;
    }
    
    private function processUrlData($content) {
        $data = ['formatted_content' => $content];
        
                if (preg_match('/(https?:\/\/[^\s\r\n]+)/i', $content, $matches)) {
            $data['main_url'] = trim($matches[1]);
            $data['display_url'] = $this->formatUrlForDisplay($data['main_url']);
        }
        
        return $data;
    }
    
    private function processSerialData($content) {
        $data = ['formatted_content' => $content];
        
                if (preg_match('/([A-Z0-9\-]{6,})/i', $content, $matches)) {
            $data['serial_number'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function processSubscriptionData($content) {
        $data = ['formatted_content' => $content];
        
                if (preg_match('/(?:id|identifiant)[:\s]*([^\r\n]+)/i', $content, $matches)) {
            $data['subscription_id'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:expire|expiration)[:\s]*([^\r\n]+)/i', $content, $matches)) {
            $data['expiration_date'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function processFileData($content) {
        $data = ['formatted_content' => $content];
        
                if (preg_match('/(https?:\/\/[^\s\r\n]+)/i', $content, $matches)) {
            $data['file_url'] = trim($matches[1]);
        }
        
                if (preg_match('/(?:nom|name)[:\s]*([^\r\n]+)/i', $content, $matches)) {
            $data['file_name'] = trim($matches[1]);
        }
        
        if (preg_match('/(?:taille|size)[:\s]*([^\r\n]+)/i', $content, $matches)) {
            $data['file_size'] = trim($matches[1]);
        }
        
        return $data;
    }
    
    private function formatGenericContent($content, $markdownEnabled) {
        if (!$markdownEnabled) {
            return $content;
        }
        
                $formatted = $content;
        
                $formatted = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $formatted);
        $formatted = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $formatted);
        $formatted = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $formatted);
        
                $formatted = preg_replace('/\*\*\*(.+?)\*\*\*/s', '<strong><em>$1</em></strong>', $formatted);
        $formatted = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $formatted);
        $formatted = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $formatted);
        
                $formatted = preg_replace('/`(.+?)`/', '<code>$1</code>', $formatted);
        
                $formatted = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2" target="_blank">$1</a>', $formatted);
        
                $formatted = preg_replace('/^- (.+)$/m', '<li>$1</li>', $formatted);
        $formatted = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $formatted);
        
                $formatted = nl2br($formatted);
        
        return $formatted;
    }
    
    private function formatUrlForDisplay($url) {
        $parsed = parse_url($url);
        return ($parsed['host'] ?? 'URL') . ($parsed['path'] ?? '');
    }
    
    private function getTypeInfo($type) {
        return $this->supportedTypes[$type] ?? $this->supportedTypes['custom'];
    }
    
    private function logAccess($orderItemId) {
        try {
                        logSecurityEvent($this->userId, 'product_accessed', [
                'order_item_id' => $orderItemId,
                'ip' => getRealIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ], 'success');
            
                        $updateStmt = $this->db->prepare("
                UPDATE product_inventory 
                SET access_count = COALESCE(access_count, 0) + 1, 
                    last_accessed_at = NOW()
                WHERE sold_in_order_id = (
                    SELECT order_id FROM order_items WHERE id = ?
                ) AND sold_to_user_id = ?
            ");
            $updateStmt->execute([$orderItemId, $this->userId]);
            
        } catch (Exception $e) {
                        error_log("Access log error: " . $e->getMessage());
        }
    }
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON invalide');
    }
    
    $orderItemId = intval($input['order_item_id'] ?? 0);
    
    if (!$orderItemId) {
        throw new Exception('ID d\'item de commande requis');
    }
    
    $manager = new EnhancedProductAccessManager();
    $result = $manager->accessProduct($orderItemId);
    
    http_response_code($result['success'] ? 200 : 400);
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
?>