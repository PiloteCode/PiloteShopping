<?php

if (!defined('API_ACCESS') && !defined('ADMIN_ACCESS')) {
    http_response_code(403);
    exit('Access Denied');
}

require_once __DIR__ . '/../../includes/EnvLoader.php';
EnvLoader::load(__DIR__ . '/../../.env');

define('APP_ENV', EnvLoader::get('APP_ENV', 'production'));
define('ENVIRONMENT', APP_ENV);
define('DEBUG_MODE', APP_ENV !== 'production');

if (APP_ENV === 'production') {
    @ini_set('display_errors', '0');
    @ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    @ini_set('display_errors', '1');
    error_reporting(E_ALL);
}
@ini_set('log_errors', '1');

define('DB_HOST', EnvLoader::required('DB_HOST'));
define('DB_PORT', EnvLoader::get('DB_PORT', '3306'));
define('DB_NAME', EnvLoader::required('DB_NAME'));
define('DB_USER', EnvLoader::required('DB_USER'));
define('DB_PASS', EnvLoader::required('DB_PASS'));
define('DB_CHARSET', EnvLoader::get('DB_CHARSET', 'utf8mb4'));

define('SITE_NAME', EnvLoader::get('SITE_NAME', 'Pilote Shopping'));
define('SITE_URL', rtrim(EnvLoader::required('SITE_URL'), '/'));
define('SITE_EMAIL', EnvLoader::get('SITE_EMAIL', 'support@piloteshopping.com'));
define('ADMIN_EMAIL', EnvLoader::get('ADMIN_EMAIL', 'admin@piloteshopping.com'));
define('WEBSITE_URL', SITE_URL . '/');
define('DOMAIN', parse_url(SITE_URL, PHP_URL_HOST));

define('JWT_SECRET', EnvLoader::required('JWT_SECRET'));
define('JWT_SECRET_KEY', JWT_SECRET);
define('JWT_ISSUER', SITE_URL);
define('JWT_AUDIENCE', SITE_URL);
define('JWT_EXPIRATION_TIME', 3600);
define('ENCRYPTION_KEY', EnvLoader::required('ENCRYPTION_KEY'));
define('HASH_SALT', EnvLoader::required('HASH_SALT'));

define('GOOGLE_CLIENT_ID', EnvLoader::get('GOOGLE_CLIENT_ID', ''));
define('GOOGLE_CLIENT_SECRET', EnvLoader::get('GOOGLE_CLIENT_SECRET', ''));
define('GOOGLE_REDIRECT_URI', SITE_URL . '/auth/google/callback.php');

define('CLOUDFLARE_TURNSTILE_SITE_KEY', EnvLoader::get('CLOUDFLARE_TURNSTILE_SITE_KEY', ''));
define('CLOUDFLARE_TURNSTILE_SECRET_KEY', EnvLoader::get('CLOUDFLARE_TURNSTILE_SECRET_KEY', ''));
define('CLOUDFLARE_TURNSTILE_ENABLED', EnvLoader::get('CLOUDFLARE_TURNSTILE_ENABLED', true, 'bool'));

define('SMTP_HOST', EnvLoader::get('SMTP_HOST', ''));
define('SMTP_PORT', EnvLoader::get('SMTP_PORT', 465, 'int'));
define('SMTP_USERNAME', EnvLoader::get('SMTP_USERNAME', ''));
define('SMTP_USER', SMTP_USERNAME);
define('SMTP_PASSWORD', EnvLoader::get('SMTP_PASSWORD', ''));
define('SMTP_PASS', SMTP_PASSWORD);
define('SMTP_ENCRYPTION', EnvLoader::get('SMTP_ENCRYPTION', 'tls'));
define('SMTP_FROM', EnvLoader::get('SMTP_FROM', SMTP_USERNAME));
define('SMTP_FROM_NAME', EnvLoader::get('SMTP_FROM_NAME', SITE_NAME));

define('DKIM_SELECTOR', EnvLoader::get('DKIM_SELECTOR', 'default'));
define('DKIM_PRIVATE_KEY_PATH', EnvLoader::get('DKIM_PRIVATE_KEY_PATH', ''));

define('CRON_SECRET', EnvLoader::get('CRON_SECRET', ''));

define('DISCORD_BLOG_WEBHOOK', EnvLoader::get('DISCORD_BLOG_WEBHOOK', ''));
define('discord_blog_webhook', DISCORD_BLOG_WEBHOOK);

define('TAX_RATE', EnvLoader::get('TAX_RATE', '0.064'));

define('EMAIL_BATCH_SIZE', EnvLoader::get('EMAIL_BATCH_SIZE', 50, 'int'));
define('EMAIL_MAX_EXECUTION_TIME', EnvLoader::get('EMAIL_MAX_EXECUTION_TIME', 290, 'int'));
define('EMAIL_SLEEP_INTERVAL', EnvLoader::get('EMAIL_SLEEP_INTERVAL', 1, 'int'));
define('EMAIL_HOURLY_LIMIT', EnvLoader::get('EMAIL_HOURLY_LIMIT', 1000, 'int'));
define('EMAIL_DAILY_LIMIT', EnvLoader::get('EMAIL_DAILY_LIMIT', 20000, 'int'));
define('EMAIL_MAX_RETRIES', EnvLoader::get('EMAIL_MAX_RETRIES', 3, 'int'));
define('EMAIL_RETRY_INTERVALS', serialize([5, 15, 60, 180]));
define('EMAIL_LOGGING_ENABLED', EnvLoader::get('EMAIL_LOGGING_ENABLED', true, 'bool'));
define('EMAIL_LOG_LEVEL', EnvLoader::get('EMAIL_LOG_LEVEL', 'warning'));

define('SESSION_LIFETIME', 86400);
define('SESSION_NAME', 'PILOTE_SESSION');
define('COOKIE_DOMAIN', '');
define('COOKIE_SECURE', strpos(SITE_URL, 'https://') === 0);
define('COOKIE_HTTPONLY', true);
define('COOKIE_SAMESITE', 'Lax');

define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);
define('PASSWORD_MIN_LENGTH', 8);
define('REQUIRE_2FA', false);
define('BCRYPT_COST', 12);

define('UPLOAD_MAX_SIZE', 50 * 1024 * 1024);
define('UPLOAD_PATH', '/uploads/');
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

define('API_VERSION', 'v1');
define('API_RATE_LIMIT', 1000);
define('API_TIMEOUT', 30);

class Config {
    private static $instance = null;
    private $config = [];

    private function __construct() {
        $this->loadConfig();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function loadConfig() {
        $this->config = [
            'db' => [
                'host' => DB_HOST,
                'port' => DB_PORT,
                'name' => DB_NAME,
                'user' => DB_USER,
                'pass' => DB_PASS,
                'charset' => DB_CHARSET,
                'options' => [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]
            ],
            'site' => [
                'name' => SITE_NAME,
                'url' => SITE_URL,
                'email' => SITE_EMAIL,
                'admin_email' => ADMIN_EMAIL
            ],
            'security' => [
                'jwt_secret' => JWT_SECRET,
                'encryption_key' => ENCRYPTION_KEY,
                'hash_salt' => HASH_SALT,
                'max_login_attempts' => MAX_LOGIN_ATTEMPTS,
                'lockout_time' => LOGIN_LOCKOUT_TIME,
                'password_min_length' => PASSWORD_MIN_LENGTH,
                'require_2fa' => REQUIRE_2FA,
                'bcrypt_cost' => BCRYPT_COST
            ],
            'google' => [
                'client_id' => GOOGLE_CLIENT_ID,
                'client_secret' => GOOGLE_CLIENT_SECRET,
                'redirect_uri' => GOOGLE_REDIRECT_URI
            ],
            'cloudflare' => [
                'turnstile_site_key' => CLOUDFLARE_TURNSTILE_SITE_KEY,
                'turnstile_secret_key' => CLOUDFLARE_TURNSTILE_SECRET_KEY
            ],
            'smtp' => [
                'host' => SMTP_HOST,
                'port' => SMTP_PORT,
                'username' => SMTP_USERNAME,
                'password' => SMTP_PASSWORD,
                'encryption' => SMTP_ENCRYPTION
            ],
            'session' => [
                'lifetime' => SESSION_LIFETIME,
                'name' => SESSION_NAME,
                'cookie_domain' => COOKIE_DOMAIN,
                'cookie_secure' => COOKIE_SECURE,
                'cookie_httponly' => COOKIE_HTTPONLY,
                'cookie_samesite' => COOKIE_SAMESITE
            ],
            'upload' => [
                'max_size' => UPLOAD_MAX_SIZE,
                'path' => UPLOAD_PATH,
                'allowed_extensions' => ALLOWED_EXTENSIONS
            ],
            'api' => [
                'version' => API_VERSION,
                'rate_limit' => API_RATE_LIMIT,
                'timeout' => API_TIMEOUT
            ]
        ];
    }

    public function get($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public function getAll() {
        return $this->config;
    }
}

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        $config = Config::getInstance();
        $dbConfig = $config->get('db');
        $dsn = "mysql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
        try {
            $this->connection = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $dbConfig['options']);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    public function commit() {
        return $this->connection->commit();
    }

    public function rollback() {
        return $this->connection->rollback();
    }

    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }

    public function inTransaction() {
        return $this->connection->inTransaction();
    }
}

function generateUUID() {
    $b = random_bytes(16);
    $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
    $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
}

function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function generateOrderNumber() {
    return 'PS' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(6)));
}

function hashPassword($password, $salt = null) {
    if ($salt === null) {
        $salt = bin2hex(random_bytes(16));
    }
    $hash = password_hash($password . $salt . HASH_SALT, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
    return ['hash' => $hash, 'salt' => $salt];
}

function verifyPassword($password, $hash, $salt) {
    return password_verify($password . $salt . HASH_SALT, $hash);
}

function sanitizeUsername($username) {
    $username = preg_replace('/[^a-zA-Z0-9\-_]/', '', $username);
    $username = strtolower($username);
    $username = trim($username, '-_');
    return substr($username, 0, 30);
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function escapeLike($value) {
    return addcslashes($value, '%_\\');
}

function getTrustedProxies() {
    static $proxies = null;
    if ($proxies === null) {
        $raw = EnvLoader::get('TRUSTED_PROXIES', '');
        $proxies = array_filter(array_map('trim', explode(',', $raw)));
    }
    return $proxies;
}

function ipInCidr($ip, $cidr) {
    if (strpos($cidr, '/') === false) {
        return $ip === $cidr;
    }
    [$subnet, $bits] = explode('/', $cidr);
    $bits = (int)$bits;
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        if ($bits === 0) return true;
        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
    return false;
}

function getRealIP() {
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    $trusted = getTrustedProxies();
    if ($trusted && $remote) {
        $isTrusted = false;
        foreach ($trusted as $cidr) {
            if (ipInCidr($remote, $cidr)) {
                $isTrusted = true;
                break;
            }
        }
        if ($isTrusted && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $candidate = trim(end($forwarded));
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }
    return $remote ?: 'unknown';
}

function logSecurityEvent($userId, $action, $details, $status = 'success') {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO security_logs (user_id, action, details, ip_address, user_agent, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $action,
            json_encode($details),
            getRealIP(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $status
        ]);
    } catch (Exception $e) {
        error_log("Security log error: " . $e->getMessage());
    }
}

function setSecurityHeaders() {
    if (headers_sent()) {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header_remove('X-Powered-By');
    if (COOKIE_SECURE) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    }
}

setSecurityHeaders();

ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_secure', COOKIE_SECURE ? '1' : '0');
ini_set('session.cookie_samesite', COOKIE_SAMESITE);
ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);

function getBillingCycleLabel($cycle) {
    $labels = [
        'one_time' => 'Achat unique',
        'monthly' => 'Mensuel',
        'quarterly' => 'Trimestriel',
        'semi_yearly' => 'Semestriel',
        'yearly' => 'Annuel',
        'commitment_3' => 'Engagement 3 mois',
        'commitment_6' => 'Engagement 6 mois',
        'commitment_12' => 'Engagement 12 mois',
        'commitment_24' => 'Engagement 24 mois'
    ];
    return $labels[$cycle] ?? 'Inconnu';
}

function getBillingCycleSuffix($cycle) {
    $suffixes = [
        'one_time' => '',
        'monthly' => '/mois',
        'quarterly' => '/trimestre',
        'semi_yearly' => '/semestre',
        'yearly' => '/an',
        'commitment_3' => '/mois',
        'commitment_6' => '/mois',
        'commitment_12' => '/mois',
        'commitment_24' => '/mois'
    ];
    return $suffixes[$cycle] ?? '';
}

function getBillingCycleDurationMonths($cycle) {
    $durations = [
        'one_time' => 0,
        'monthly' => 1,
        'quarterly' => 3,
        'semi_yearly' => 6,
        'yearly' => 12,
        'commitment_3' => 3,
        'commitment_6' => 6,
        'commitment_12' => 12,
        'commitment_24' => 24
    ];
    return $durations[$cycle] ?? 1;
}

function calculatePeriodTotalPrice($monthlyPrice, $billingCycle) {
    $duration = getBillingCycleDurationMonths($billingCycle);
    if ($billingCycle === 'one_time' || $duration === 0) {
        return floatval($monthlyPrice);
    }
    if (!str_starts_with($billingCycle, 'commitment_')) {
        return floatval($monthlyPrice);
    }
    return floatval($monthlyPrice) * $duration;
}

function calculateMonthlyEquivalentPrice($price, $billingCycle) {
    $duration = getBillingCycleDurationMonths($billingCycle);
    if ($billingCycle === 'one_time' || $duration === 0) {
        return null;
    }
    if (!str_starts_with($billingCycle, 'commitment_')) {
        return floatval($price) / $duration;
    }
    return floatval($price);
}

function isSubscriptionBillingCycle($cycle) {
    return $cycle !== 'one_time';
}

function isCommitmentBillingCycle($cycle) {
    return str_starts_with($cycle, 'commitment_');
}

function getBillingCycleInfo($cycle, $price) {
    return [
        'cycle' => $cycle,
        'label' => getBillingCycleLabel($cycle),
        'suffix' => getBillingCycleSuffix($cycle),
        'duration_months' => getBillingCycleDurationMonths($cycle),
        'is_subscription' => isSubscriptionBillingCycle($cycle),
        'is_commitment' => isCommitmentBillingCycle($cycle),
        'period_total_price' => calculatePeriodTotalPrice($price, $cycle),
        'monthly_equivalent_price' => calculateMonthlyEquivalentPrice($price, $cycle)
    ];
}

function getSystemSetting($key, $default = null) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function updateProductRating($db, $product_id) {
    $stats_stmt = $db->prepare("
        SELECT
            AVG(rating) as avg_rating,
            COUNT(*) as review_count
        FROM product_reviews
        WHERE product_id = ? AND status = 'approved'
    ");
    $stats_stmt->execute([$product_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    $update_stmt = $db->prepare("
        UPDATE products
        SET rating_average = ?, rating_count = ?
        WHERE id = ?
    ");
    $update_stmt->execute([
        round($stats['avg_rating'], 2),
        $stats['review_count'],
        $product_id
    ]);
}

function updateOfferRating($db, $offer_id) {
    $stats_stmt = $db->prepare("
        SELECT
            AVG(rating) as avg_rating,
            COUNT(*) as review_count
        FROM product_reviews
        WHERE product_offer_id = ? AND status = 'approved'
    ");
    $stats_stmt->execute([$offer_id]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    $update_stmt = $db->prepare("
        UPDATE product_offers
        SET rating_average = ?, rating_count = ?
        WHERE id = ?
    ");
    $update_stmt->execute([
        round($stats['avg_rating'], 2),
        $stats['review_count'],
        $offer_id
    ]);
}

function getUserLoyaltyBalance($user_id) {
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN type IN ('earned', 'bonus') THEN points
                    WHEN type IN ('spent', 'expired', 'penalty') THEN -points
                    ELSE 0
                END
            ), 0) as balance
            FROM loyalty_transactions
            WHERE user_id = ?
            AND (expires_at IS NULL OR expires_at > NOW())
        ");
        $stmt->execute([$user_id]);
        return intval($stmt->fetchColumn());
    } catch (Exception $e) {
        return 0;
    }
}

function getDeliveryMethodLabel($method) {
    $labels = [
        'automatic' => 'Instantané',
        'semi_automatic' => 'Quelques minutes',
        'manual_24h' => 'Sous 24h',
        'manual_48h' => 'Sous 48h',
        'manual_72h' => 'Sous 72h',
        'manual_1week' => 'Sous 1 semaine'
    ];
    return $labels[$method] ?? 'Standard';
}

function getAccessMethodLabel($method) {
    $labels = [
        'no_connection' => 'Sans connexion',
        'account_required' => 'Compte requis',
        'license_key' => 'Clé de licence',
        'api_access' => 'Accès API',
        'download_link' => 'Téléchargement'
    ];
    return $labels[$method] ?? 'Standard';
}
