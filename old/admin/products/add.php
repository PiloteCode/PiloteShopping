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
    
        $categoriesStmt = $db->prepare("SELECT * FROM product_categories WHERE is_active = 1 ORDER BY name ASC");
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll();
    
    $typesStmt = $db->prepare("SELECT * FROM product_types WHERE is_active = 1 ORDER BY name ASC");
    $typesStmt->execute();
    $types = $typesStmt->fetchAll();
    
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $db->beginTransaction();
            
                        $name = trim($_POST['name'] ?? '');
            $category_id = intval($_POST['category_id'] ?? 0);
            $product_type_id = intval($_POST['product_type_id'] ?? 0);
            $short_description = trim($_POST['short_description'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $sku = trim($_POST['sku'] ?? '');
            $weight = !empty($_POST['weight']) ? floatval($_POST['weight']) : null;
            $barcode = trim($_POST['barcode'] ?? '');
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $is_featured = isset($_POST['is_featured']) ? 1 : 0;
            $is_digital = isset($_POST['is_digital']) ? 1 : 0;
            $requires_shipping = isset($_POST['requires_shipping']) ? 1 : 0;
            $meta_title = trim($_POST['meta_title'] ?? '');
            $meta_description = trim($_POST['meta_description'] ?? '');
            $meta_keywords = trim($_POST['meta_keywords'] ?? '');
            
                        if (empty($name)) throw new Exception('Le nom du produit est requis');
            if ($category_id <= 0) throw new Exception('La catégorie est requise');
            if ($product_type_id <= 0) throw new Exception('Le type de produit est requis');
            if (empty($short_description)) throw new Exception('La description courte est requise');
            
                        $slug = generateSlug($name);
            $originalSlug = $slug;
            $counter = 1;
            
                        do {
                $slugCheck = $db->prepare("SELECT id FROM products WHERE slug = ?");
                $slugCheck->execute([$slug]);
                if ($slugCheck->fetch()) {
                    $slug = $originalSlug . '-' . $counter;
                    $counter++;
                } else {
                    break;
                }
            } while (true);
            
                        if ($sku) {
                $skuCheck = $db->prepare("SELECT id FROM products WHERE sku = ?");
                $skuCheck->execute([$sku]);
                if ($skuCheck->fetch()) {
                    throw new Exception('Ce SKU existe déjà');
                }
            }
            
                        $images = [];
            if (!empty($_POST['images'])) {
                $imageUrls = array_filter(array_map('trim', explode("\n", $_POST['images'])));
                foreach ($imageUrls as $url) {
                    if (filter_var($url, FILTER_VALIDATE_URL)) {
                        $images[] = $url;
                    }
                }
            }
            
                        $features = [];
            if (!empty($_POST['features'])) {
                $featureLines = array_filter(array_map('trim', explode("\n", $_POST['features'])));
                foreach ($featureLines as $feature) {
                    if (!empty($feature)) {
                        $features[] = $feature;
                    }
                }
            }
            
                        $insertProductStmt = $db->prepare("
                INSERT INTO products (
                    category_id, product_type_id, name, slug, short_description, description, 
                    features, images, weight, sku, barcode, is_active, is_featured, 
                    is_digital, requires_shipping, meta_title, meta_description, meta_keywords, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insertProductStmt->execute([
                $category_id, $product_type_id, $name, $slug, $short_description, $description,
                json_encode($features), json_encode($images), $weight, $sku ?: null, $barcode ?: null, 
                $is_active, $is_featured, $is_digital, $requires_shipping, $meta_title ?: null, 
                $meta_description ?: null, $meta_keywords ?: null, $currentUser['id']
            ]);
            
            $productId = $db->lastInsertId();
            
                        if (!empty($_POST['access_types'])) {
                foreach ($_POST['access_types'] as $index => $accessTypeData) {
                    $accessName = trim($accessTypeData['name'] ?? '');
                    $accessDescription = trim($accessTypeData['description'] ?? '');
                    $accessIcon = trim($accessTypeData['icon'] ?? 'fas fa-key');
                    $accessMethod = $accessTypeData['access_method'] ?? 'account_required';
                    $deliveryMethod = $accessTypeData['delivery_method'] ?? 'automatic';
                    $hasGlobalStock = isset($accessTypeData['has_global_stock']) ? 1 : 0;
                    $globalStockQuantity = $hasGlobalStock ? intval($accessTypeData['global_stock_quantity'] ?? 0) : 0;
                    $globalStockThreshold = $hasGlobalStock ? intval($accessTypeData['global_stock_threshold'] ?? 5) : 5;
                    
                    if (empty($accessName)) continue;
                    
                                        $accessSlug = generateSlug($accessName);
                    
                                        $insertAccessTypeStmt = $db->prepare("
                        INSERT INTO product_access_types (
                            product_id, name, slug, description, icon, access_method, 
                            default_delivery_method, has_global_stock, global_stock_quantity, 
                            global_stock_threshold, sort_order, is_active
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ");
                    
                    $insertAccessTypeStmt->execute([
                        $productId, $accessName, $accessSlug, $accessDescription, $accessIcon, 
                        $accessMethod, $deliveryMethod, $hasGlobalStock, $globalStockQuantity, 
                        $globalStockThreshold, $index
                    ]);
                    
                    $accessTypeId = $db->lastInsertId();
                    
                                        if (!empty($accessTypeData['offers'])) {
                        foreach ($accessTypeData['offers'] as $offerIndex => $offerData) {
                            $offerName = trim($offerData['name'] ?? '');
                            $offerDescription = trim($offerData['description'] ?? '');
                            $offerPrice = floatval($offerData['price'] ?? 0);
                            $compareAtPrice = !empty($offerData['compare_at_price']) ? floatval($offerData['compare_at_price']) : null;
                            $billingCycle = $offerData['billing_cycle'] ?? 'one_time';
                            $trialDays = intval($offerData['trial_days'] ?? 0);
                            $setupFee = floatval($offerData['setup_fee'] ?? 0);
                            $isDefault = isset($offerData['is_default']) ? 1 : 0;
                            $hasOwnStock = isset($offerData['has_own_stock']) ? 1 : 0;
                            $stockQuantity = $hasOwnStock ? intval($offerData['stock_quantity'] ?? 0) : 0;
                            $manageStock = $hasOwnStock;
                            
                            if (empty($offerName) || $offerPrice <= 0) continue;
                            
                                                        $billingDurationMonths = match($billingCycle) {
                                'monthly' => 1,
                                'quarterly' => 3,
                                'semi_yearly' => 6,
                                'yearly' => 12,
                                'commitment_3' => 3,
                                'commitment_6' => 6,
                                'commitment_12' => 12,
                                'commitment_24' => 24,
                                default => 0
                            };
                            
                                                        $offerSlug = generateSlug($offerName);
                            
                                                        $insertOfferStmt = $db->prepare("
                                INSERT INTO product_offers (
                                    access_type_id, name, slug, description, price, compare_at_price,
                                    billing_cycle, billing_duration_months, trial_days, setup_fee,
                                    has_own_stock, stock_quantity, manage_stock, sort_order, 
                                    is_active, is_default
                                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)
                            ");
                            
                            $insertOfferStmt->execute([
                                $accessTypeId, $offerName, $offerSlug, $offerDescription, $offerPrice, 
                                $compareAtPrice, $billingCycle, $billingDurationMonths, $trialDays, 
                                $setupFee, $hasOwnStock, $stockQuantity, $manageStock, $offerIndex, $isDefault
                            ]);
                        }
                    }
                }
            }
            
            $db->commit();
            
            $adminAuth->logAction('product_created', 'product', $productId, null, [
                'name' => $name,
                'category_id' => $category_id,
                'product_type_id' => $product_type_id
            ]);
            
            header("Location: /admin/products/edit.php?id=$productId&success=" . urlencode('Produit créé avec succès'));
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollback();
            }
            $error = $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    error_log("Admin product add error: " . $e->getMessage());
    $error = "Erreur lors du chargement du formulaire";
    $categories = [];
    $types = [];
}

function generateSlug($text) {
    $text = transliterator_transliterate('Any-Latin; Latin-ASCII', $text);
    $text = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', strtolower($text)));
    return trim($text, '-');
}

$seo = [
    'title' => 'Ajouter un produit - Admin Pilote Shopping',
    'description' => 'Création d\'un nouveau produit',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main class="min-h-screen bg-dark-900 pt-16">
    <div class="container mx-auto px-4 lg:px-6 py-8">

        <div class="mb-6">
            <nav class="flex items-center space-x-2 text-sm text-gray-400">
                <a href="/admin/" class="hover:text-white transition-colors">Admin</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <a href="/admin/products/" class="hover:text-white transition-colors">Produits</a>
                <i class="fas fa-chevron-right text-xs"></i>
                <span class="text-white">Nouveau produit</span>
            </nav>
        </div>

        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl md:text-4xl font-bold text-white mb-2">
                        <i class="fas fa-plus text-green-400 mr-3"></i>
                        Nouveau produit
                    </h1>
                    <p class="text-gray-400">Créer un nouveau produit avec ses types d'accès et offres</p>
                </div>

                <a href="/admin/products/" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                    <i class="fas fa-arrow-left mr-2"></i>Retour
                </a>
            </div>
        </div>

<?php if (isset($error)): ?>
        <div class="mb-6 bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-xl">
            <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
        </div>
<?php endif; ?>


        <form method="POST" class="space-y-8">

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                    <div class="w-8 h-8 bg-blue-primary/20 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-info-circle text-blue-primary"></i>
                    </div>
                    Informations de base
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                    <div class="md:col-span-2">
                        <label class="block text-white font-semibold mb-2">
                            Nom du produit <span class="text-red-400">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                               required
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2">
                            Catégorie <span class="text-red-400">*</span>
                        </label>
                        <select name="category_id" required class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Sélectionner une catégorie</option>
<?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($_POST['category_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($cat['name']) ?>
                            </option>
<?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2">
                            Type de produit <span class="text-red-400">*</span>
                        </label>
                        <select name="product_type_id" required class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-blue-500">
                            <option value="">Sélectionner un type</option>
<?php foreach ($types as $type): ?>
                            <option value="<?= $type['id'] ?>" <?= ($_POST['product_type_id'] ?? '') == $type['id'] ? 'selected' : '' ?>>
<?= htmlspecialchars($type['name']) ?>
                            </option>
<?php endforeach; ?>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-white font-semibold mb-2">
                            Description courte <span class="text-red-400">*</span>
                        </label>
                        <textarea name="short_description" 
                                  rows="2" 
                                  required
                                  placeholder="Description de présentation du produit"
                                  class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"><?= htmlspecialchars($_POST['short_description'] ?? '') ?></textarea>
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-white font-semibold mb-2">Description complète</label>
                        <textarea name="description" 
                                  rows="6" 
                                  placeholder="Description détaillée du produit"
                                  class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2">SKU</label>
                        <input type="text" 
                               name="sku" 
                               value="<?= htmlspecialchars($_POST['sku'] ?? '') ?>"
                               placeholder="Code produit unique"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2">Poids (kg)</label>
                        <input type="number" 
                               name="weight" 
                               value="<?= htmlspecialchars($_POST['weight'] ?? '') ?>"
                               step="0.001"
                               placeholder="0.000"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2">Code-barres</label>
                        <input type="text" 
                               name="barcode" 
                               value="<?= htmlspecialchars($_POST['barcode'] ?? '') ?>"
                               placeholder="Code-barres du produit"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6">
                    <label class="flex items-center p-4 bg-dark-700/50 rounded-xl border border-dark-600 hover:border-green-400/50 transition-all cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" <?= ($_POST['is_active'] ?? '1') ? 'checked' : '' ?> class="text-green-400 mr-3">
                        <span class="text-white">Produit actif</span>
                    </label>

                    <label class="flex items-center p-4 bg-dark-700/50 rounded-xl border border-dark-600 hover:border-yellow-400/50 transition-all cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" <?= ($_POST['is_featured'] ?? '') ? 'checked' : '' ?> class="text-yellow-400 mr-3">
                        <span class="text-white">Produit vedette</span>
                    </label>

                    <label class="flex items-center p-4 bg-dark-700/50 rounded-xl border border-dark-600 hover:border-blue-400/50 transition-all cursor-pointer">
                        <input type="checkbox" name="is_digital" value="1" <?= ($_POST['is_digital'] ?? '1') ? 'checked' : '' ?> class="text-blue-400 mr-3">
                        <span class="text-white">Produit numérique</span>
                    </label>

                    <label class="flex items-center p-4 bg-dark-700/50 rounded-xl border border-dark-600 hover:border-orange-400/50 transition-all cursor-pointer">
                        <input type="checkbox" name="requires_shipping" value="1" <?= ($_POST['requires_shipping'] ?? '') ? 'checked' : '' ?> class="text-orange-400 mr-3">
                        <span class="text-white">Nécessite expédition</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                        <div class="w-6 h-6 bg-purple-500/20 rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-images text-purple-400 text-sm"></i>
                        </div>
                        Images du produit
                    </h3>

                    <textarea name="images" 
                              rows="8" 
                              placeholder="URL d'image par ligne&#10;https://example.com/image1.jpg&#10;https://example.com/image2.jpg"
                              class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-purple-500"><?= htmlspecialchars($_POST['images'] ?? '') ?></textarea>

                    <p class="text-gray-400 text-sm mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Une URL par ligne. La première image sera l'image principale.
                    </p>
                </div>

                <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                    <h3 class="text-lg font-bold text-white mb-4 flex items-center">
                        <div class="w-6 h-6 bg-green-500/20 rounded-lg flex items-center justify-center mr-2">
                            <i class="fas fa-list-check text-green-400 text-sm"></i>
                        </div>
                        Fonctionnalités
                    </h3>

                    <textarea name="features" 
                              rows="8" 
                              placeholder="Fonctionnalité par ligne&#10;Accès illimité&#10;Support 24/7&#10;Interface moderne"
                              class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-green-500"><?= htmlspecialchars($_POST['features'] ?? '') ?></textarea>

                    <p class="text-gray-400 text-sm mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Une fonctionnalité par ligne.
                    </p>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-white flex items-center">
                        <div class="w-8 h-8 bg-orange-500/20 rounded-lg flex items-center justify-center mr-3">
                            <i class="fas fa-key text-orange-400"></i>
                        </div>
                        Types d'accès et offres
                    </h2>

                    <button type="button" 
                            onclick="addAccessType()" 
                            class="bg-orange-600 hover:bg-orange-700 text-white px-4 py-2 rounded-xl font-medium transition-colors">
                        <i class="fas fa-plus mr-2"></i>Ajouter un type d'accès
                    </button>
                </div>

                <div id="access-types-container" class="space-y-6">

                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <h2 class="text-xl font-bold text-white mb-6 flex items-center">
                    <div class="w-8 h-8 bg-cyan-500/20 rounded-lg flex items-center justify-center mr-3">
                        <i class="fas fa-search text-cyan-400"></i>
                    </div>
                    Référencement (SEO)
                </h2>

                <div class="grid grid-cols-1 gap-6">

                    <div>
                        <label class="block text-white font-semibold mb-2">Meta titre</label>
                        <input type="text" 
                               name="meta_title" 
                               value="<?= htmlspecialchars($_POST['meta_title'] ?? '') ?>"
                               placeholder="Titre pour les moteurs de recherche"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-cyan-500">
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2">Meta description</label>
                        <textarea name="meta_description" 
                                  rows="3" 
                                  placeholder="Description pour les moteurs de recherche"
                                  class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-cyan-500"><?= htmlspecialchars($_POST['meta_description'] ?? '') ?></textarea>
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2">Meta mots-clés</label>
                        <input type="text" 
                               name="meta_keywords" 
                               value="<?= htmlspecialchars($_POST['meta_keywords'] ?? '') ?>"
                               placeholder="mot-clé1, mot-clé2, mot-clé3"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-3 text-white placeholder-gray-400 focus:outline-none focus:border-cyan-500">
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between">
                <a href="/admin/products/" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                    <i class="fas fa-times mr-2"></i>Annuler
                </a>

                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl font-medium transition-colors">
                    <i class="fas fa-save mr-2"></i>Créer le produit
                </button>
            </div>
        </form>
    </div>
</main>

<script>
let accessTypeCounter = 0;

function addAccessType() {
    const container = document.getElementById('access-types-container');
    const accessTypeId = accessTypeCounter++;

    const accessTypeHtml = `
        <div class="access-type-item border border-dark-600 rounded-xl p-6 bg-dark-700/30" data-id="${accessTypeId}">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-white">Type d'accès #${accessTypeId + 1}</h3>
                <button type="button" onclick="removeAccessType(${accessTypeId})" class="text-red-400 hover:text-red-300 transition-colors">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-white font-semibold mb-2">Nom du type d'accès</label>
                    <input type="text" 
                           name="access_types[${accessTypeId}][name]" 
                           placeholder="Ex: Carte virtuelle, Compte direct"
                           class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-orange-500">
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2">Icône</label>
                    <input type="text" 
                           name="access_types[${accessTypeId}][icon]" 
                           value="fas fa-key"
                           placeholder="fas fa-key"
                           class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-orange-500">
                </div>

                <div class="md:col-span-2">
                    <label class="block text-white font-semibold mb-2">Description</label>
                    <textarea name="access_types[${accessTypeId}][description]" 
                              rows="2" 
                              placeholder="Description du type d'accès"
                              class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-orange-500"></textarea>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2">Méthode d'accès</label>
                    <select name="access_types[${accessTypeId}][access_method]" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-orange-500">
                        <option value="no_connection">Aucune connexion requise</option>
                        <option value="account_required" selected>Compte requis</option>
                        <option value="license_key">Clé de licence</option>
                        <option value="api_access">Accès API</option>
                        <option value="download_link">Lien de téléchargement</option>
                    </select>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2">Méthode de livraison</label>
                    <select name="access_types[${accessTypeId}][delivery_method]" class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white focus:outline-none focus:border-orange-500">
                        <option value="automatic" selected>Automatique</option>
                        <option value="semi_automatic">Semi-automatique</option>
                        <option value="manual_24h">Manuel 24h</option>
                        <option value="manual_48h">Manuel 48h</option>
                        <option value="manual_72h">Manuel 72h</option>
                        <option value="manual_1week">Manuel 1 semaine</option>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="flex items-center p-4 bg-dark-700/50 rounded-xl border border-dark-600 hover:border-yellow-400/50 transition-all cursor-pointer">
                    <input type="checkbox" 
                           name="access_types[${accessTypeId}][has_global_stock]" 
                           value="1" 
                           onchange="toggleGlobalStock(${accessTypeId})"
                           class="text-yellow-400 mr-3">
                    <span class="text-white">Gérer un stock global pour ce type d'accès</span>
                </label>

                <div id="global-stock-${accessTypeId}" class="hidden mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-white font-semibold mb-2">Quantité en stock</label>
                        <input type="number" 
                               name="access_types[${accessTypeId}][global_stock_quantity]" 
                               min="0"
                               placeholder="0"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-yellow-500">
                    </div>

                    <div>
                        <label class="block text-white font-semibold mb-2">Seuil d'alerte</label>
                        <input type="number" 
                               name="access_types[${accessTypeId}][global_stock_threshold]" 
                               value="5"
                               min="1"
                               class="w-full bg-dark-700 border border-dark-600 rounded-xl px-4 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-yellow-500">
                    </div>
                </div>
            </div>

            <div class="border-t border-dark-600 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="text-lg font-bold text-white">Offres pour ce type d'accès</h4>
                    <button type="button" 
                            onclick="addOffer(${accessTypeId})" 
                            class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded-lg font-medium transition-colors text-sm">
                        <i class="fas fa-plus mr-2"></i>Ajouter une offre
                    </button>
                </div>

                <div id="offers-container-${accessTypeId}" class="space-y-4">

                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', accessTypeHtml);

    addOffer(accessTypeId);
}

function removeAccessType(accessTypeId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce type d\'accès et toutes ses offres ?')) {
        const element = document.querySelector(`[data-id="${accessTypeId}"]`);
        element.remove();
    }
}

function toggleGlobalStock(accessTypeId) {
    const checkbox = document.querySelector(`input[name="access_types[${accessTypeId}][has_global_stock]"]`);
    const container = document.getElementById(`global-stock-${accessTypeId}`);

    if (checkbox.checked) {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

let offerCounters = {};

function addOffer(accessTypeId) {
    if (!offerCounters[accessTypeId]) {
        offerCounters[accessTypeId] = 0;
    }

    const container = document.getElementById(`offers-container-${accessTypeId}`);
    const offerId = offerCounters[accessTypeId]++;

    const offerHtml = `
        <div class="offer-item bg-dark-700/20 border border-dark-600 rounded-lg p-4" data-access-id="${accessTypeId}" data-offer-id="${offerId}">
            <div class="flex items-center justify-between mb-4">
                <h5 class="font-bold text-white">Offre #${offerId + 1}</h5>
                <button type="button" onclick="removeOffer(${accessTypeId}, ${offerId})" class="text-red-400 hover:text-red-300 transition-colors text-sm">
                    <i class="fas fa-trash"></i>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-white font-semibold mb-2">Nom de l'offre</label>
                    <input type="text" 
                           name="access_types[${accessTypeId}][offers][${offerId}][name]" 
                           placeholder="Ex: 1 mois, 1 an, etc."
                           class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 text-sm">
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2">Prix (€)</label>
                    <input type="number" 
                           name="access_types[${accessTypeId}][offers][${offerId}][price]" 
                           step="0.01"
                           min="0"
                           placeholder="0.00"
                           class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 text-sm">
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2">Prix barré (€)</label>
                    <input type="number" 
                           name="access_types[${accessTypeId}][offers][${offerId}][compare_at_price]" 
                           step="0.01"
                           min="0"
                           placeholder="0.00"
                           class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 text-sm">
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2">Cycle de facturation</label>
                    <select name="access_types[${accessTypeId}][offers][${offerId}][billing_cycle]" class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white focus:outline-none focus:border-purple-500 text-sm">
                        <option value="one_time" selected>Achat unique</option>
                        <option value="monthly">Mensuel</option>
                        <option value="quarterly">Trimestriel</option>
                        <option value="semi_yearly">Semestriel</option>
                        <option value="yearly">Annuel</option>
                        <option value="commitment_3">Engagement 3 mois</option>
                        <option value="commitment_6">Engagement 6 mois</option>
                        <option value="commitment_12">Engagement 12 mois</option>
                        <option value="commitment_24">Engagement 24 mois</option>
                    </select>
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2">Essai gratuit (jours)</label>
                    <input type="number" 
                           name="access_types[${accessTypeId}][offers][${offerId}][trial_days]" 
                           min="0"
                           value="0"
                           class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 text-sm">
                </div>

                <div>
                    <label class="block text-white font-semibold mb-2">Frais d'installation (€)</label>
                    <input type="number" 
                           name="access_types[${accessTypeId}][offers][${offerId}][setup_fee]" 
                           step="0.01"
                           min="0"
                           value="0"
                           class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 text-sm">
                </div>

                <div class="lg:col-span-3">
                    <label class="block text-white font-semibold mb-2">Description de l'offre</label>
                    <textarea name="access_types[${accessTypeId}][offers][${offerId}][description]" 
                              rows="2" 
                              placeholder="Description de cette offre spécifique"
                              class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 text-sm"></textarea>
                </div>

                <div class="lg:col-span-3 flex items-center space-x-6">
                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="access_types[${accessTypeId}][offers][${offerId}][is_default]" 
                               value="1" 
                               class="text-purple-400 mr-2">
                        <span class="text-white text-sm">Offre par défaut</span>
                    </label>

                    <label class="flex items-center">
                        <input type="checkbox" 
                               name="access_types[${accessTypeId}][offers][${offerId}][has_own_stock]" 
                               value="1" 
                               onchange="toggleOfferStock(${accessTypeId}, ${offerId})"
                               class="text-purple-400 mr-2">
                        <span class="text-white text-sm">Stock propre à cette offre</span>
                    </label>
                </div>

                <div id="offer-stock-${accessTypeId}-${offerId}" class="hidden lg:col-span-3">
                    <div class="grid grid-cols-2 gap-4 mt-4 p-4 bg-dark-700/30 rounded-lg">
                        <div>
                            <label class="block text-white font-semibold mb-2">Quantité en stock</label>
                            <input type="number" 
                                   name="access_types[${accessTypeId}][offers][${offerId}][stock_quantity]" 
                                   min="0"
                                   placeholder="0"
                                   class="w-full bg-dark-700 border border-dark-600 rounded-lg px-3 py-2 text-white placeholder-gray-400 focus:outline-none focus:border-purple-500 text-sm">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', offerHtml);
}

function removeOffer(accessTypeId, offerId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette offre ?')) {
        const element = document.querySelector(`[data-access-id="${accessTypeId}"][data-offer-id="${offerId}"]`);
        element.remove();
    }
}

function toggleOfferStock(accessTypeId, offerId) {
    const checkbox = document.querySelector(`input[name="access_types[${accessTypeId}][offers][${offerId}][has_own_stock]"]`);
    const container = document.getElementById(`offer-stock-${accessTypeId}-${offerId}`);

    if (checkbox.checked) {
        container.classList.remove('hidden');
    } else {
        container.classList.add('hidden');
    }
}

document.addEventListener('DOMContentLoaded', function() {
    addAccessType();
});
</script>

<?php endHTMLPage(); ?>