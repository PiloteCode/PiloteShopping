<?php

define('API_ACCESS', true);
define('ADMIN_ACCESS', true);
require_once '../config.php';
require_once __DIR__ . '/../../../includes/CsrfProtection.php';
require_once __DIR__ . '/../../../admin/includes/AdminAuthMiddleware.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class DynamicFieldsManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
        public function getFieldsForProduct($productId) {
        try {
                        $stmt = $this->db->prepare("
                SELECT product_type_id, delivery_method
                FROM products p
                JOIN product_types pt ON p.product_type_id = pt.id
                WHERE p.id = ? AND p.is_active = 1
            ");
            $stmt->execute([$productId]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception('Produit non trouvé');
            }
            
                        $templateStmt = $this->db->prepare("
                SELECT *
                FROM dynamic_field_templates
                WHERE product_type_id = ? AND is_active = 1
                ORDER BY sort_order ASC, field_label ASC
            ");
            $templateStmt->execute([$product['product_type_id']]);
            $templates = $templateStmt->fetchAll();
            
                        $valuesStmt = $this->db->prepare("
                SELECT field_name, field_value, is_required, is_visible_customer
                FROM product_dynamic_fields
                WHERE product_id = ?
            ");
            $valuesStmt->execute([$productId]);
            $existingValues = [];
            foreach ($valuesStmt->fetchAll() as $value) {
                $existingValues[$value['field_name']] = $value;
            }
            
                        $fields = [];
            foreach ($templates as $template) {
                $field = [
                    'template_id' => $template['id'],
                    'field_name' => $template['field_name'],
                    'field_label' => $template['field_label'],
                    'field_type' => $template['field_type'],
                    'field_options' => $template['field_options'] ? json_decode($template['field_options'], true) : null,
                    'is_required' => $template['is_required'],
                    'is_visible_admin' => $template['is_visible_admin'],
                    'is_visible_customer' => $template['is_visible_customer'],
                    'validation_rules' => $template['validation_rules'],
                    'placeholder' => $template['placeholder'],
                    'help_text' => $template['help_text'],
                    'sort_order' => $template['sort_order'],
                    'field_value' => null
                ];
                
                                if (isset($existingValues[$template['field_name']])) {
                    $field['field_value'] = $existingValues[$template['field_name']]['field_value'];
                    $field['is_required'] = $existingValues[$template['field_name']]['is_required'];
                    $field['is_visible_customer'] = $existingValues[$template['field_name']]['is_visible_customer'];
                }
                
                $fields[] = $field;
            }
            
            return [
                'success' => true,
                'product_id' => $productId,
                'product_type_id' => $product['product_type_id'],
                'delivery_method' => $product['delivery_method'],
                'fields' => $fields,
                'requires_custom_fields' => !empty($fields) && in_array($product['delivery_method'], ['manual_24h', 'manual_48h', 'manual_72h', 'manual_1week'])
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        public function getTemplatesForProductType($productTypeId) {
        try {
            $stmt = $this->db->prepare("
                SELECT *
                FROM dynamic_field_templates
                WHERE product_type_id = ? AND is_active = 1
                ORDER BY sort_order ASC, field_label ASC
            ");
            $stmt->execute([$productTypeId]);
            $templates = $stmt->fetchAll();
            
                        foreach ($templates as &$template) {
                if ($template['field_options']) {
                    $template['field_options'] = json_decode($template['field_options'], true);
                }
            }
            
            return [
                'success' => true,
                'product_type_id' => $productTypeId,
                'templates' => $templates
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        public function saveFieldValues($productId, $fieldValues, $userId = null) {
        try {
            $this->db->beginTransaction();
            
                        $stmt = $this->db->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
            $stmt->execute([$productId]);
            if (!$stmt->fetch()) {
                throw new Exception('Produit non trouvé');
            }
            
            foreach ($fieldValues as $fieldName => $fieldValue) {
                                $templateStmt = $this->db->prepare("
                    SELECT dft.*
                    FROM dynamic_field_templates dft
                    JOIN products p ON dft.product_type_id = p.product_type_id
                    WHERE p.id = ? AND dft.field_name = ? AND dft.is_active = 1
                ");
                $templateStmt->execute([$productId, $fieldName]);
                $template = $templateStmt->fetch();
                
                if (!$template) {
                    continue;                 }
                
                                $this->validateFieldValue($template, $fieldValue);
                
                                $existingStmt = $this->db->prepare("
                    SELECT id FROM product_dynamic_fields
                    WHERE product_id = ? AND field_name = ?
                ");
                $existingStmt->execute([$productId, $fieldName]);
                $existing = $existingStmt->fetch();
                
                if ($existing) {
                                        $updateStmt = $this->db->prepare("
                        UPDATE product_dynamic_fields
                        SET field_value = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$fieldValue, $existing['id']]);
                } else {
                                        $insertStmt = $this->db->prepare("
                        INSERT INTO product_dynamic_fields (
                            product_id, field_template_id, field_name, field_value,
                            is_required, is_visible_admin, is_visible_customer, sort_order
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $insertStmt->execute([
                        $productId,
                        $template['id'],
                        $fieldName,
                        $fieldValue,
                        $template['is_required'],
                        $template['is_visible_admin'],
                        $template['is_visible_customer'],
                        $template['sort_order']
                    ]);
                }
            }
            
            $this->db->commit();
            
                        logSecurityEvent($userId, 'dynamic_fields_saved', [
                'product_id' => $productId,
                'fields_count' => count($fieldValues)
            ], 'success');
            
            return [
                'success' => true,
                'message' => 'Champs sauvegardés avec succès'
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        private function validateFieldValue($template, $value) {
                if ($template['is_required'] && empty($value)) {
            throw new Exception("Le champ '{$template['field_label']}' est requis");
        }
        
                switch ($template['field_type']) {
            case 'email':
                if ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Le champ '{$template['field_label']}' doit être un email valide");
                }
                break;
                
            case 'url':
                if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
                    throw new Exception("Le champ '{$template['field_label']}' doit être une URL valide");
                }
                break;
                
            case 'number':
                if ($value && !is_numeric($value)) {
                    throw new Exception("Le champ '{$template['field_label']}' doit être un nombre");
                }
                break;
                
            case 'date':
                if ($value && !DateTime::createFromFormat('Y-m-d', $value)) {
                    throw new Exception("Le champ '{$template['field_label']}' doit être une date valide");
                }
                break;
                
            case 'select':
            case 'radio':
                if ($value && $template['field_options']) {
                    $options = json_decode($template['field_options'], true);
                    $validValues = array_column($options, 'value');
                    if (!in_array($value, $validValues)) {
                        throw new Exception("Valeur non autorisée pour le champ '{$template['field_label']}'");
                    }
                }
                break;
        }
        
                if ($template['validation_rules'] && $value) {
            $rules = explode('|', $template['validation_rules']);
            foreach ($rules as $rule) {
                $this->applyValidationRule($rule, $value, $template['field_label']);
            }
        }
    }
    
        private function applyValidationRule($rule, $value, $fieldLabel) {
        if (strpos($rule, 'min_length:') === 0) {
            $minLength = intval(substr($rule, 11));
            if (strlen($value) < $minLength) {
                throw new Exception("Le champ '{$fieldLabel}' doit contenir au moins {$minLength} caractères");
            }
        }
        
        if (strpos($rule, 'max_length:') === 0) {
            $maxLength = intval(substr($rule, 11));
            if (strlen($value) > $maxLength) {
                throw new Exception("Le champ '{$fieldLabel}' ne peut pas dépasser {$maxLength} caractères");
            }
        }
        
        if ($rule === 'alphanumeric') {
            if (!ctype_alnum($value)) {
                throw new Exception("Le champ '{$fieldLabel}' ne peut contenir que des lettres et des chiffres");
            }
        }
        
        if ($rule === 'alpha') {
            if (!ctype_alpha($value)) {
                throw new Exception("Le champ '{$fieldLabel}' ne peut contenir que des lettres");
            }
        }
        
        if ($rule === 'numeric') {
            if (!is_numeric($value)) {
                throw new Exception("Le champ '{$fieldLabel}' doit être numérique");
            }
        }
        
        if (strpos($rule, 'regex:') === 0) {
            $pattern = substr($rule, 6);
            if (!preg_match($pattern, $value)) {
                throw new Exception("Le format du champ '{$fieldLabel}' n'est pas valide");
            }
        }
    }
    
        public function getRequiredFieldsForUser($productId, $userId = null) {
        try {
            $result = $this->getFieldsForProduct($productId);
            
            if (!$result['success']) {
                return $result;
            }
            
                        $requiredFields = array_filter($result['fields'], function($field) {
                return $field['is_visible_customer'] && $field['is_required'];
            });
            
            return [
                'success' => true,
                'product_id' => $productId,
                'fields' => array_values($requiredFields),
                'requires_fields' => !empty($requiredFields)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}

try {
    $fieldsManager = new DynamicFieldsManager();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            $productId = $_GET['product_id'] ?? null;
            $productTypeId = $_GET['product_type_id'] ?? null;
            $mode = $_GET['mode'] ?? 'product';
            
            if ($mode === 'product' && $productId) {
                $result = $fieldsManager->getFieldsForProduct($productId);
            } elseif ($mode === 'type' && $productTypeId) {
                $result = $fieldsManager->getTemplatesForProductType($productTypeId);
            } elseif ($mode === 'required' && $productId) {
                $result = $fieldsManager->getRequiredFieldsForUser($productId, $_SESSION['user_id'] ?? null);
            } else {
                throw new Exception('Paramètres invalides');
            }
            break;
            
        case 'POST':
            $adminAuth = initAdminAuth();
            $adminAuth->requireAdminAuth(['admin', 'super_admin']);
            CsrfProtection::requireValidToken();
            $currentAdmin = $adminAuth->getCurrentAdmin();

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Format JSON invalide');
            }
            $productId = filter_var($input['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $fieldValues = $input['field_values'] ?? [];
            if (!$productId || empty($fieldValues) || !is_array($fieldValues)) {
                throw new Exception('Données manquantes');
            }
            $result = $fieldsManager->saveFieldValues($productId, $fieldValues, $currentAdmin['user_id']);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            exit();
    }
    
    if ($result['success']) {
        http_response_code(200);
        echo json_encode($result);
    } else {
        http_response_code(400);
        echo json_encode($result);
    }
    
} catch (Exception $e) {
    logSecurityEvent(null, 'dynamic_fields_api_error', [
        'error' => $e->getMessage(),
        'method' => $_SERVER['REQUEST_METHOD'],
        'get_params' => $_GET,
        'post_size' => strlen(file_get_contents('php://input'))
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
