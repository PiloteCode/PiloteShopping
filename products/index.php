<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';

$request_uri = $_SERVER['REQUEST_URI'];
$path_parts = explode('/', trim(parse_url($request_uri, PHP_URL_PATH), '/'));

$is_product_page = false;
$product_slug = null;

if (count($path_parts) >= 2 && $path_parts[0] === 'products' && !empty($path_parts[1])) {
    $potential_slug = $path_parts[1];
    
        $reserved_words = [
        'search', 'category', 'type', 'filter', 'page', 'sort', 'view',
        'new', 'featured', 'bestsellers', 'deals', 'admin', 'api', 'add',
        'edit', 'delete', 'manage', 'list', 'index', 'compare', 'reviews',
        'access-types', 'offers', 'boosters'
    ];
    
        if (!in_array($potential_slug, $reserved_words) && 
        !isset($_GET['search']) && 
        !isset($_GET['category']) && 
        !isset($_GET['type']) &&
        !isset($_GET['access_method']) &&
        !isset($_GET['billing_type']) &&
        !isset($_GET['page']) &&
        !isset($_GET['sort'])) {
        
                try {
            $db = Database::getInstance();
            $slug_check = $db->prepare("SELECT slug FROM products WHERE slug = ? AND is_active = 1");
            $slug_check->execute([$potential_slug]);
            
            if ($slug_check->fetch()) {
                $is_product_page = true;
                $product_slug = $potential_slug;
            }
        } catch (Exception $e) {
            error_log("Slug verification error: " . $e->getMessage());
                    }
    }
}

if ($is_product_page && $product_slug) {
        require_once 'product_detail.php';
} else {
        require_once 'product_list.php';
}
?>