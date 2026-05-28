<?php
define('API_ACCESS', true);
require_once '../../api/v1/config.php';
require_once '../../includes/import.php';
require_once '../../includes/AuthMiddleware.php';
require_once '../includes/AdminAuthMiddleware.php';

$adminAuth = initAdminAuth();
$adminAuth->requireAdminAuth(['admin', 'super_admin']);

$currentAdmin = $adminAuth->getCurrentAdmin();
$currentUser = $adminAuth->getCurrentUser();

try {
    $db = Database::getInstance();
    
        $offerId = !empty($_GET['offer_id']) ? intval($_GET['offer_id']) : null;
    $productId = !empty($_GET['product_id']) ? intval($_GET['product_id']) : null;
    
        
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            switch ($action) {
                case 'add_field':
                    $offer_id = intval($_POST['offer_id']);
                    $field_name = trim($_POST['field_name']);
                    $field_label = trim($_POST['field_label']);
                    $field_type = $_POST['field_type'];
                    $field_options = $_POST['field_options'] ?? '';
                    $is_required = isset($_POST['is_required']) ? 1 : 0;
                    $is_visible_admin = isset($_POST['is_visible_admin']) ? 1 : 0;
                    $is_visible_customer = isset($_POST['is_visible_customer']) ? 1 : 0;
                    $validation_rules = trim($_POST['validation_rules'] ?? '');
                    $placeholder = trim($_POST['placeholder'] ?? '');
                    $help_text = trim($_POST['help_text'] ?? '');
                    $default_value = trim($_POST['default_value'] ?? '');
                    
                                        if (empty($field_name)) throw new Exception('Le nom technique du champ est requis');
                    if (empty($field_label)) throw new Exception('Le libellé du champ est requis');
                    if (empty($field_type)) throw new Exception('Le type de champ est requis');
                    
                                        $checkStmt = $db->prepare("SELECT id FROM product_offer_custom_fields WHERE offer_id = ? AND field_name = ?");
                    $checkStmt->execute([$offer_id, $field_name]);
                    if ($checkStmt->fetch()) {
                        throw new Exception('Ce nom de champ existe déjà pour cette offre');
                    }
                    
                                        $processedOptions = null;
                    if (in_array($field_type, ['select', 'radio', 'checkbox']) && !empty($field_options)) {
                        $options = [];
                        $lines = explode("\n", $field_options);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                if (strpos($line, '|') !== false) {
                                    list($value, $label) = explode('|', $line, 2);
                                    $options[] = ['value' => trim($value), 'label' => trim($label)];
                                } else {
                                    $options[] = ['value' => $line, 'label' => $line];
                                }
                            }
                        }
                        $processedOptions = json_encode($options);
                    }
                    
                                        $orderStmt = $db->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM product_offer_custom_fields WHERE offer_id = ?");
                    $orderStmt->execute([$offer_id]);
                    $sortOrder = $orderStmt->fetchColumn();
                    
                                        $insertStmt = $db->prepare("
                        INSERT INTO product_offer_custom_fields (
                            offer_id, field_name, field_label, field_type, field_options,
                            is_required, is_visible_admin, is_visible_customer,
                            validation_rules, placeholder, help_text, default_value,
                            sort_order, is_active
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    
                    $insertStmt->execute([
                        $offer_id, $field_name, $field_label, $field_type, $processedOptions,
                        $is_required, $is_visible_admin, $is_visible_customer,
                        $validation_rules ?: null, $placeholder ?: null, $help_text ?: null, 
                        $default_value ?: null, $sortOrder
                    ]);
                    
                    $adminAuth->logAction('custom_field_created', 'custom_field', $db->lastInsertId(), null, [
                        'offer_id' => $offer_id,
                        'field_name' => $field_name,
                        'field_type' => $field_type
                    ]);
                    
                    $success = "Champ personnalisé ajouté avec succès";
                    break;
                    
                case 'edit_field':
                    $field_id = intval($_POST['field_id']);
                    $field_label = trim($_POST['field_label']);
                    $field_type = $_POST['field_type'];
                    $field_options = $_POST['field_options'] ?? '';
                    $is_required = isset($_POST['is_required']) ? 1 : 0;
                    $is_visible_admin = isset($_POST['is_visible_admin']) ? 1 : 0;
                    $is_visible_customer = isset($_POST['is_visible_customer']) ? 1 : 0;
                    $validation_rules = trim($_POST['validation_rules'] ?? '');
                    $placeholder = trim($_POST['placeholder'] ?? '');
                    $help_text = trim($_POST['help_text'] ?? '');
                    $default_value = trim($_POST['default_value'] ?? '');
                    
                                        if (empty($field_label)) throw new Exception('Le libellé du champ est requis');
                    
                                        $processedOptions = null;
                    if (in_array($field_type, ['select', 'radio', 'checkbox']) && !empty($field_options)) {
                        $options = [];
                        $lines = explode("\n", $field_options);
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if (!empty($line)) {
                                if (strpos($line, '|') !== false) {
                                    list($value, $label) = explode('|', $line, 2);
                                    $options[] = ['value' => trim($value), 'label' => trim($label)];
                                } else {
                                    $options[] = ['value' => $line, 'label' => $line];
                                }
                            }
                        }
                        $processedOptions = json_encode($options);
                    }
                    
                                        $updateStmt = $db->prepare("
                        UPDATE product_offer_custom_fields SET
                            field_label = ?, field_type = ?, field_options = ?,
                            is_required = ?, is_visible_admin = ?, is_visible_customer = ?,
                            validation_rules = ?, placeholder = ?, help_text = ?, default_value = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    
                    $updateStmt->execute([
                        $field_label, $field_type, $processedOptions,
                        $is_required, $is_visible_admin, $is_visible_customer,
                        $validation_rules ?: null, $placeholder ?: null, $help_text ?: null, 
                        $default_value ?: null, $field_id
                    ]);
                    
                    $adminAuth->logAction('custom_field_updated', 'custom_field', $field_id);
                    $success = "Champ personnalisé mis à jour avec succès";
                    break;
                    
                case 'delete_field':
                    $field_id = intval($_POST['field_id']);
                    
                                        $fieldStmt = $db->prepare("SELECT * FROM product_offer_custom_fields WHERE id = ?");
                    $fieldStmt->execute([$field_id]);
                    $field = $fieldStmt->fetch();
                    
                    if (!$field) {
                        throw new Exception('Champ non trouvé');
                    }
                    
                                        $deleteStmt = $db->prepare("DELETE FROM product_offer_custom_fields WHERE id = ?");
                    $deleteStmt->execute([$field_id]);
                    
                    $adminAuth->logAction('custom_field_deleted', 'custom_field', $field_id, $field, null, 'medium');
                    $success = "Champ personnalisé supprimé avec succès";
                    break;
                    
                case 'toggle_status':
                    $field_id = intval($_POST['field_id']);
                    
                    $toggleStmt = $db->prepare("UPDATE product_offer_custom_fields SET is_active = NOT is_active WHERE id = ?");
                    $toggleStmt->execute([$field_id]);
                    
                    $adminAuth->logAction('custom_field_status_toggled', 'custom_field', $field_id);
                    $success = "Statut du champ modifié avec succès";
                    break;
                    
                case 'reorder_fields':
                    $order = $_POST['order'] ?? [];
                    
                    if (!empty($order)) {
                        $updateStmt = $db->prepare("UPDATE product_offer_custom_fields SET sort_order = ? WHERE id = ?");
                        
                        foreach ($order as $position => $fieldId) {
                            $updateStmt->execute([$position + 1, intval($fieldId)]);
                        }
                        
                        $adminAuth->logAction('custom_fields_reordered', 'custom_field', null, null, ['order' => $order]);
                        $success = "Ordre des champs mis à jour avec succès";
                    }
                    break;
                    
                default:
                    $error = "Action non reconnue";
            }
            
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
    
        
        $productsStmt = $db->prepare("
        SELECT p.id, p.name, p.slug,
               COUNT(DISTINCT pat.id) as access_types_count,
               COUNT(DISTINCT po.id) as offers_count
        FROM products p
        LEFT JOIN product_access_types pat ON p.id = pat.product_id AND pat.is_active = 1
        LEFT JOIN product_offers po ON pat.id = po.access_type_id AND po.is_active = 1
        WHERE p.is_active = 1
        GROUP BY p.id
        ORDER BY p.name ASC
    ");
    $productsStmt->execute();
    $products = $productsStmt->fetchAll();
    
        $offers = [];
    if ($productId) {
        $offersStmt = $db->prepare("
            SELECT po.*, pat.name as access_type_name
            FROM product_offers po
            JOIN product_access_types pat ON po.access_type_id = pat.id
            WHERE pat.product_id = ? AND po.is_active = 1 AND pat.is_active = 1
            ORDER BY pat.sort_order ASC, po.sort_order ASC
        ");
        $offersStmt->execute([$productId]);
        $offers = $offersStmt->fetchAll();
    }
    
        $customFields = [];
    $selectedOffer = null;
    if ($offerId) {
                $offerStmt = $db->prepare("
            SELECT po.*, pat.name as access_type_name, p.name as product_name
            FROM product_offers po
            JOIN product_access_types pat ON po.access_type_id = pat.id
            JOIN products p ON pat.product_id = p.id
            WHERE po.id = ?
        ");
        $offerStmt->execute([$offerId]);
        $selectedOffer = $offerStmt->fetch();
        
        if ($selectedOffer) {
                        $fieldsStmt = $db->prepare("
                SELECT * FROM product_offer_custom_fields
                WHERE offer_id = ?
                ORDER BY sort_order ASC
            ");
            $fieldsStmt->execute([$offerId]);
            $customFields = $fieldsStmt->fetchAll();
        }
    }
    
        $fieldTypes = [
        'text' => 'Texte simple',
        'textarea' => 'Zone de texte',
        'email' => 'Email',
        'password' => 'Mot de passe',
        'url' => 'URL',
        'number' => 'Nombre',
        'date' => 'Date',
        'select' => 'Liste déroulante',
        'checkbox' => 'Cases à cocher',
        'radio' => 'Boutons radio',
        'file' => 'Fichier'
    ];
    
} catch (Exception $e) {
    error_log("Admin custom fields error: " . $e->getMessage());
    $error = "Erreur lors du chargement des champs personnalisés";
    $products = [];
    $offers = [];
    $customFields = [];
}

$seo = [
    'title' => 'Gestion des champs personnalisés - Admin Pilote Shopping',
    'description' => 'Administration des champs personnalisés des offres',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 sm:py-6 lg:py-8 max-w-7xl">

        <div class="mb-4 sm:mb-6">
            <nav class="flex items-center space-x-2 text-xs sm:text-sm text-gray-400">
                <a href="/admin/" class="hover:text-white transition-colors">Admin</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <a href="/admin/products/" class="hover:text-white transition-colors">Produits</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <span class="text-white">Champs personnalisés</span>
            </nav>
        </div>

        <div class="mb-6 sm:mb-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                <div>
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-bold text-white mb-2">
                        <i class="fas fa-cogs text-purple-400 mr-2 sm:mr-3"></i>
                        Champs personnalisés
                    </h1>
                    <p class="text-sm sm:text-base text-gray-400">Gestion des champs personnalisés pour les offres de produits</p>
                </div>

                <a href="/admin/products/" class="w-full sm:w-auto bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg sm:rounded-xl font-medium transition-colors text-center text-sm sm:text-base">
                    <i class="fas fa-arrow-left mr-2"></i>Retour aux produits
                </a>
            </div>
        </div>

<?php if (isset($success)): ?>
        <div class="mb-4 sm:mb-6 bg-green-500/20 border border-green-500/50 text-green-300 px-3 sm:px-4 py-2 sm:py-3 rounded-lg sm:rounded-xl text-sm sm:text-base">
            <i class="fas fa-check-circle mr-2"></i><?= htmlspecialchars($success) ?>
        </div>
<?php endif; ?>

<?php if (isset($error)): ?>
        <div class="mb-4 sm:mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-3 sm:px-4 py-2 sm:py-3 rounded-lg sm:rounded-xl text-sm sm:text-base">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6 mb-6 sm:mb-8">
            <h2 class="text-lg sm:text-xl font-bold text-white mb-4">Sélectionner une offre</h2>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Produit</label>
                    <select onchange="selectProduct(this.value)" class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-purple-500">
                        <option value="">Choisir un produit</option>
<?php foreach ($products as $product): ?>
                        <option value="<?= $product['id'] ?>" <?= $productId == $product['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($product['name']) ?> (<?= $product['offers_count'] ?> offres)
                        </option>
<?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Offre</label>
                    <select onchange="selectOffer(this.value)" class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-purple-500" <?= empty($offers) ? 'disabled' : '' ?>>
                        <option value="">Choisir une offre</option>
<?php foreach ($offers as $offer): ?>
                        <option value="<?= $offer['id'] ?>" <?= $offerId == $offer['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($offer['access_type_name']) ?> - <?= htmlspecialchars($offer['name']) ?> (<?= number_format($offer['price'], 2) ?>€)
                        </option>
<?php endforeach; ?>
                    </select>
                </div>
            </div>

<?php if ($selectedOffer): ?>
            <div class="mt-4 p-3 sm:p-4 bg-purple-500/10 border border-purple-500/30 rounded-lg sm:rounded-xl">
                <h3 class="font-bold text-purple-300 mb-2 text-sm sm:text-base">Offre sélectionnée</h3>
                <p class="text-white text-sm sm:text-base">
                    <strong><?= htmlspecialchars($selectedOffer['product_name']) ?></strong> - 
<?= htmlspecialchars($selectedOffer['access_type_name']) ?> - 
<?= htmlspecialchars($selectedOffer['name']) ?>
                </p>
                <p class="text-gray-400 text-xs sm:text-sm"><?= number_format(count($customFields)) ?> champ(s) personnalisé(s)</p>
            </div>
<?php endif; ?>
        </div>

<?php if ($selectedOffer): ?>


        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 p-4 sm:p-6 mb-6 sm:mb-8">
            <h2 class="text-lg sm:text-xl font-bold text-white mb-4 sm:mb-6 flex items-center">
                <div class="w-6 h-6 sm:w-8 sm:h-8 bg-green-500/20 rounded-lg flex items-center justify-center mr-2 sm:mr-3">
                    <i class="fas fa-plus text-green-400 text-sm sm:text-base"></i>
                </div>
                Ajouter un champ personnalisé
            </h2>

            <form method="POST" class="space-y-4 sm:space-y-6">
                <input type="hidden" name="action" value="add_field">
                <input type="hidden" name="offer_id" value="<?= $offerId ?>">

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">

                    <div>
                        <label class="block text-white font-semibold mb-2 text-sm sm:text-base">
                            Nom technique <span class="text-red-400">*</span>
                        </label>
                        <input type="text" 
                               name="field_name" 
                               required
                               placeholder="ex: email_contact, duree_validite"
                               pattern="[a-z_]+"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-green-500">
                        <p class="text-gray-400 text-xs sm:text-sm mt-1">Uniquement lettres minuscules et underscores</p>
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2 text-sm sm:text-base">
                            Libellé affiché <span class="text-red-400">*</span>
                        </label>
                        <input type="text" 
                               name="field_label" 
                               required
                               placeholder="ex: Adresse email de contact"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2 text-sm sm:text-base">
                            Type de champ <span class="text-red-400">*</span>
                        </label>
                        <select name="field_type" 
                                required
                                onchange="toggleFieldOptions(this, 'add')"
                                class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base focus:outline-none focus:border-green-500">
                            <option value="">Choisir un type</option>
<?php foreach ($fieldTypes as $value => $label): ?>
                            <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
<?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Placeholder</label>
                        <input type="text" 
                               name="placeholder" 
                               placeholder="Texte d'aide dans le champ"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Valeur par défaut</label>
                        <input type="text" 
                               name="default_value" 
                               placeholder="Valeur pré-remplie"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-green-500">
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Règles de validation</label>
                        <input type="text" 
                               name="validation_rules" 
                               placeholder="ex: min:3,max:50"
                               class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-green-500">
                    </div>
                </div>

                <div id="field-options-add" class="hidden">
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Options</label>
                    <textarea name="field_options" 
                              rows="6" 
                              placeholder="Une option par ligne.&#10;Format: valeur|libellé&#10;&#10;Exemple:&#10;visa|Visa Virtuelle&#10;mastercard|Mastercard Virtuelle&#10;auto|Automatique"
                              class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-green-500"></textarea>
                    <p class="text-gray-400 text-xs sm:text-sm mt-1">Format: valeur|libellé (ou juste la valeur si identique)</p>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Texte d'aide</label>
                    <textarea name="help_text" 
                              rows="2" 
                              placeholder="Texte explicatif affiché sous le champ"
                              class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base placeholder-gray-400 focus:outline-none focus:border-green-500"></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
                    <label class="flex items-center p-3 sm:p-4 bg-dark-700/50 rounded-lg sm:rounded-xl border border-dark-600 hover:border-red-400/50 transition-all cursor-pointer">
                        <input type="checkbox" name="is_required" value="1" class="text-red-400 mr-2 sm:mr-3 w-4 h-4">
                        <span class="text-white text-sm sm:text-base">Champ obligatoire</span>
                    </label>

                    <label class="flex items-center p-3 sm:p-4 bg-dark-700/50 rounded-lg sm:rounded-xl border border-dark-600 hover:border-blue-400/50 transition-all cursor-pointer">
                        <input type="checkbox" name="is_visible_admin" value="1" checked class="text-blue-400 mr-2 sm:mr-3 w-4 h-4">
                        <span class="text-white text-sm sm:text-base">Visible admin</span>
                    </label>

                    <label class="flex items-center p-3 sm:p-4 bg-dark-700/50 rounded-lg sm:rounded-xl border border-dark-600 hover:border-green-400/50 transition-all cursor-pointer">
                        <input type="checkbox" name="is_visible_customer" value="1" checked class="text-green-400 mr-2 sm:mr-3 w-4 h-4">
                        <span class="text-white text-sm sm:text-base">Visible client</span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white px-4 sm:px-6 py-2 sm:py-3 rounded-lg sm:rounded-xl font-medium transition-colors text-sm sm:text-base">
                        <i class="fas fa-plus mr-2"></i>Ajouter le champ
                    </button>
                </div>
            </form>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl sm:rounded-2xl border border-dark-600 overflow-hidden">
            <div class="p-4 sm:p-6 border-b border-dark-600">
                <h2 class="text-lg sm:text-xl font-bold text-white flex items-center">
                    <div class="w-6 h-6 sm:w-8 sm:h-8 bg-purple-500/20 rounded-lg flex items-center justify-center mr-2 sm:mr-3">
                        <i class="fas fa-list text-purple-400 text-sm sm:text-base"></i>
                    </div>
                    Champs personnalisés (<?= count($customFields) ?>)
                </h2>
            </div>

<?php if (empty($customFields)): ?>
            <div class="p-8 sm:p-12 text-center text-gray-400">
                <i class="fas fa-cogs text-4xl sm:text-6xl mb-3 sm:mb-4 opacity-50"></i>
                <p class="text-lg sm:text-xl">Aucun champ personnalisé</p>
                <p class="text-xs sm:text-sm mt-2">Ajoutez le premier champ personnalisé ci-dessus</p>
            </div>
<?php else: ?>

            <div id="fields-list" class="divide-y divide-dark-600">
<?php foreach ($customFields as $field): ?>
                <div class="p-4 sm:p-6 hover:bg-dark-700/30 transition-colors" data-field-id="<?= $field['id'] ?>">
                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between space-y-4 lg:space-y-0">
                        <div class="flex-1">
                            <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4 mb-3">
                                <div class="flex items-center space-x-2">
                                    <i class="fas fa-grip-vertical text-gray-500 cursor-move"></i>
                                    <h3 class="text-base sm:text-lg font-bold text-white"><?= htmlspecialchars($field['field_label']) ?></h3>
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="px-2 py-1 bg-blue-500/20 text-blue-400 text-xs font-bold rounded-full">
<?= strtoupper($field['field_type']) ?>
                                    </span>

<?php if ($field['is_required']): ?>
                                    <span class="px-2 py-1 bg-red-500/20 text-red-400 text-xs font-bold rounded-full">
                                        OBLIGATOIRE
                                    </span>
<?php endif; ?>

                                    <div class="w-2 h-2 rounded-full <?= $field['is_active'] ? 'bg-green-500' : 'bg-red-500' ?>"></div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 text-xs sm:text-sm">
                                <div>
                                    <span class="text-gray-400">Nom technique:</span>
                                    <span class="text-white font-mono block"><?= htmlspecialchars($field['field_name']) ?></span>
                                </div>

<?php if ($field['placeholder']): ?>
                                <div>
                                    <span class="text-gray-400">Placeholder:</span>
                                    <span class="text-white block"><?= htmlspecialchars($field['placeholder']) ?></span>
                                </div>
<?php endif; ?>

<?php if ($field['default_value']): ?>
                                <div>
                                    <span class="text-gray-400">Valeur par défaut:</span>
                                    <span class="text-white block"><?= htmlspecialchars($field['default_value']) ?></span>
                                </div>
<?php endif; ?>

                                <div>
                                    <span class="text-gray-400">Visibilité:</span>
                                    <span class="text-white block">
<?php
                                        $visibility = [];
                                        if ($field['is_visible_admin']) $visibility[] = 'Admin';
                                        if ($field['is_visible_customer']) $visibility[] = 'Client';
                                        echo implode(', ', $visibility);
                                        ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="text-gray-400">Ordre:</span>
                                    <span class="text-white block"><?= $field['sort_order'] ?></span>
                                </div>
                            </div>

<?php if ($field['help_text']): ?>
                            <div class="mt-3 p-2 sm:p-3 bg-dark-700/50 rounded-lg">
                                <span class="text-gray-400 text-xs sm:text-sm">Aide: </span>
                                <span class="text-white text-xs sm:text-sm"><?= htmlspecialchars($field['help_text']) ?></span>
                            </div>
<?php endif; ?>

<?php if (in_array($field['field_type'], ['select', 'radio', 'checkbox']) && $field['field_options']): ?>
                            <div class="mt-3">
                                <span class="text-gray-400 text-xs sm:text-sm">Options: </span>
                                <div class="flex flex-wrap gap-1 sm:gap-2 mt-1">
<?php
                                    $options = json_decode($field['field_options'], true);
                                    if ($options):
                                        foreach ($options as $option):
                                    ?>
                                    <span class="px-2 py-1 bg-gray-600/50 text-gray-300 text-xs rounded">
<?= htmlspecialchars($option['label']) ?>
<?php if ($option['value'] !== $option['label']): ?>
                                        (<?= htmlspecialchars($option['value']) ?>)
<?php endif; ?>
                                    </span>
<?php endforeach; endif; ?>
                                </div>
                            </div>
<?php endif; ?>
                        </div>

                        <div class="flex flex-row lg:flex-col items-center space-x-2 lg:space-x-0 lg:space-y-2 lg:ml-4">
                            <button onclick="editField(<?= $field['id'] ?>)" 
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-2 sm:px-3 py-1 rounded-md sm:rounded-lg text-xs sm:text-sm transition-colors min-w-[36px]">
                                <i class="fas fa-edit"></i>
                            </button>

                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="field_id" value="<?= $field['id'] ?>">
                                <button type="submit" 
                                        class="bg-<?= $field['is_active'] ? 'orange' : 'green' ?>-600 hover:bg-<?= $field['is_active'] ? 'orange' : 'green' ?>-700 text-white px-2 sm:px-3 py-1 rounded-md sm:rounded-lg text-xs sm:text-sm transition-colors min-w-[36px]">
                                    <i class="fas fa-<?= $field['is_active'] ? 'pause' : 'play' ?>"></i>
                                </button>
                            </form>

                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="delete_field">
                                <input type="hidden" name="field_id" value="<?= $field['id'] ?>">
                                <button type="submit" 
                                        onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce champ ?')"
                                        class="bg-red-600 hover:bg-red-700 text-white px-2 sm:px-3 py-1 rounded-md sm:rounded-lg text-xs sm:text-sm transition-colors min-w-[36px]">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
<?php endforeach; ?>
            </div>

<?php if (count($customFields) > 1): ?>
            <div class="p-4 sm:p-6 border-t border-dark-600 bg-dark-700/30">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-2 sm:space-y-0">
                    <button onclick="saveFieldOrder()" 
                            class="w-full sm:w-auto bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm sm:text-base">
                        <i class="fas fa-save mr-2"></i>Sauvegarder l'ordre
                    </button>
                    <span class="text-gray-400 text-xs sm:text-sm text-center sm:text-left">Glissez-déposez les champs pour réorganiser</span>
                </div>
            </div>
<?php endif; ?>

<?php endif; ?>
        </div>

<?php endif; ?>
    </div>
</main>

<div id="edit-modal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-dark-800 rounded-xl sm:rounded-2xl border border-dark-600 w-full max-w-4xl max-h-[95vh] overflow-y-auto">
        <div class="p-4 sm:p-6 border-b border-dark-600">
            <div class="flex items-center justify-between">
                <h3 class="text-lg sm:text-xl font-bold text-white">Éditer le champ personnalisé</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-xl sm:text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        <form method="POST" class="p-4 sm:p-6 space-y-4 sm:space-y-6">
            <input type="hidden" name="action" value="edit_field">
            <input type="hidden" name="field_id" id="edit-field-id">

            <div id="edit-form-content"></div>

            <div class="flex flex-col sm:flex-row justify-end space-y-2 sm:space-y-0 sm:space-x-4">
                <button type="button" 
                        onclick="closeEditModal()" 
                        class="w-full sm:w-auto bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg transition-colors text-sm sm:text-base">
                    Annuler
                </button>
                <button type="submit" 
                        class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition-colors text-sm sm:text-base">
                    Mettre à jour
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function selectProduct(productId) {
    if (productId) {
        window.location.href = '?product_id=' + productId;
    } else {
        window.location.href = '?';
    }
}

function selectOffer(offerId) {
    const urlParams = new URLSearchParams(window.location.search);
    if (offerId) {
        urlParams.set('offer_id', offerId);
    } else {
        urlParams.delete('offer_id');
    }
    window.location.href = '?' + urlParams.toString();
}

function toggleFieldOptions(select, context) {
    const container = document.getElementById('field-options-' + context);
    const needsOptions = ['select', 'radio', 'checkbox'].includes(select.value);

    if (needsOptions) {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

const fieldsData = <?= json_encode($customFields) ?>;
const fieldTypes = <?= json_encode($fieldTypes) ?>;

function editField(fieldId) {
    const field = fieldsData.find(f => f.id == fieldId);
    if (!field) return;

    document.getElementById('edit-field-id').value = fieldId;

    let optionsHtml = '';
    if (field.field_options) {
        const options = JSON.parse(field.field_options);
        optionsHtml = options.map(opt => opt.value + '|' + opt.label).join('\n');
    }

    const formContent = `
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <div>
                <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Nom technique</label>
                <input type="text" value="${field.field_name}" readonly 
                       class="w-full bg-dark-600 border border-dark-500 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-gray-400 text-sm sm:text-base">
                <p class="text-gray-500 text-xs sm:text-sm mt-1">Le nom technique ne peut pas être modifié</p>
            </div>

            <div>
                <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Libellé affiché <span class="text-red-400">*</span></label>
                <input type="text" name="field_label" value="${field.field_label}" required
                       class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base">
            </div>

            <div>
                <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Type de champ</label>
                <select name="field_type" onchange="toggleFieldOptions(this, 'edit')"
                        class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base">
                    ${Object.entries(fieldTypes).map(([value, label]) => 
                        `<option value="${value}" ${field.field_type === value ? 'selected' : ''}>${label}</option>`
                    ).join('')}
                </select>
            </div>

            <div>
                <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Placeholder</label>
                <input type="text" name="placeholder" value="${field.placeholder || ''}"
                       class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base">
            </div>

            <div>
                <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Valeur par défaut</label>
                <input type="text" name="default_value" value="${field.default_value || ''}"
                       class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base">
            </div>

            <div>
                <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Règles de validation</label>
                <input type="text" name="validation_rules" value="${field.validation_rules || ''}"
                       class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base">
            </div>
        </div>

        <div id="field-options-edit" class="${['select', 'radio', 'checkbox'].includes(field.field_type) ? '' : 'hidden'}">
            <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Options</label>
            <textarea name="field_options" rows="6" 
                      class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base">${optionsHtml}</textarea>
        </div>

        <div>
            <label class="block text-white font-semibold mb-2 text-sm sm:text-base">Texte d'aide</label>
            <textarea name="help_text" rows="2" 
                      class="w-full bg-dark-700 border border-dark-600 rounded-lg sm:rounded-xl px-3 sm:px-4 py-2 sm:py-3 text-white text-sm sm:text-base">${field.help_text || ''}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
            <label class="flex items-center p-3 sm:p-4 bg-dark-700/50 rounded-lg sm:rounded-xl border border-dark-600 cursor-pointer">
                <input type="checkbox" name="is_required" value="1" ${field.is_required ? 'checked' : ''} class="text-red-400 mr-2 sm:mr-3 w-4 h-4">
                <span class="text-white text-sm sm:text-base">Champ obligatoire</span>
            </label>

            <label class="flex items-center p-3 sm:p-4 bg-dark-700/50 rounded-lg sm:rounded-xl border border-dark-600 cursor-pointer">
                <input type="checkbox" name="is_visible_admin" value="1" ${field.is_visible_admin ? 'checked' : ''} class="text-blue-400 mr-2 sm:mr-3 w-4 h-4">
                <span class="text-white text-sm sm:text-base">Visible admin</span>
            </label>

            <label class="flex items-center p-3 sm:p-4 bg-dark-700/50 rounded-lg sm:rounded-xl border border-dark-600 cursor-pointer">
                <input type="checkbox" name="is_visible_customer" value="1" ${field.is_visible_customer ? 'checked' : ''} class="text-green-400 mr-2 sm:mr-3 w-4 h-4">
                <span class="text-white text-sm sm:text-base">Visible client</span>
            </label>
        </div>
    `;

    document.getElementById('edit-form-content').innerHTML = formContent;
    document.getElementById('edit-modal').classList.remove('hidden');
}

function closeEditModal() {
    document.getElementById('edit-modal').classList.add('hidden');
}

let draggedElement = null;

document.addEventListener('DOMContentLoaded', function() {
    const fieldsList = document.getElementById('fields-list');
    if (fieldsList && typeof Sortable !== 'undefined') {
        new Sortable(fieldsList, {
            handle: '.fa-grip-vertical',
            animation: 150,
            ghostClass: 'opacity-50'
        });
    }
});

function saveFieldOrder() {
    const fieldsList = document.getElementById('fields-list');
    const fieldItems = fieldsList.querySelectorAll('[data-field-id]');
    const order = Array.from(fieldItems).map(item => item.dataset.fieldId);

    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';

    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'reorder_fields';
    form.appendChild(actionInput);

    order.forEach((fieldId, index) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `order[${index}]`;
        input.value = fieldId;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
}

document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});
</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

<style>

@media (max-width: 640px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .text-2xl.sm\\:text-3xl.lg\\:text-4xl {
        font-size: 1.5rem;
    }

    .grid.grid-cols-1.lg\\:grid-cols-2 {
        grid-template-columns: 1fr;
    }

    .grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-3 {
        grid-template-columns: 1fr;
    }
}

@media (min-width: 640px) and (max-width: 1024px) {
    .grid.grid-cols-1.sm\\:grid-cols-2.lg\\:grid-cols-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    button, select, input, textarea {
        min-height: 44px;
    }

    .w-4.h-4 {
        width: 1.2rem;
        height: 1.2rem;
    }
}

.order-item {
    animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (max-width: 768px) {
    #edit-modal .max-w-4xl {
        max-width: 100%;
        margin: 1rem;
    }

    #edit-modal .max-h-\[95vh\] {
        max-height: 90vh;
    }
}

@media (hover: hover) {
    .hover\\:bg-dark-700\\/30:hover {
        background-color: rgba(55, 65, 81, 0.3);
    }
}

@media (max-width: 640px) {
    .space-y-4 > * + * {
        margin-top: 0.75rem;
    }

    .space-y-6 > * + * {
        margin-top: 1rem;
    }
}

.sortable-ghost {
    opacity: 0.4;
}

.sortable-chosen {
    transform: scale(1.02);
}
</style>

<?php endHTMLPage(); ?>