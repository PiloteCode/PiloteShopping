<?php

if (PHP_SAPI !== 'cli') {
    define('API_ACCESS', true);
    require_once __DIR__ . '/../api/v1/config.php';
    $providedSecret = $_SERVER['HTTP_X_CRON_SECRET'] ?? ($_GET['cron_secret'] ?? '');
    if (empty(CRON_SECRET) || !is_string($providedSecret) || !hash_equals(CRON_SECRET, $providedSecret)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        echo "Forbidden\n";
        error_log("Cron HTTP access denied from " . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        exit;
    }
}

ini_set('memory_limit', '256M');
set_time_limit(3600);
if (PHP_SAPI === 'cli') {
} else {
}
if (PHP_SAPI === 'cli') {
    echo "=== DÉMARRAGE DU SCRIPT ===" . PHP_EOL;
    echo "PHP Version: " . PHP_VERSION . PHP_EOL;
    echo "Script: " . __FILE__ . PHP_EOL;
    echo "Working directory: " . getcwd() . PHP_EOL . PHP_EOL;
}

if (!file_exists(__DIR__ . '/../api/v1/config.php')) {
    die("ERREUR: Fichier config.php non trouvé dans " . __DIR__ . "/../api/v1/config.php" . PHP_EOL);
}

if (!defined('API_ACCESS')) {
    define('API_ACCESS', true);
}

try {
    echo "Chargement de la configuration..." . PHP_EOL;
    require_once __DIR__ . '/../api/v1/config.php';
    echo "✓ Configuration chargée" . PHP_EOL;
} catch (Exception $e) {
    die("ERREUR lors du chargement config: " . $e->getMessage() . PHP_EOL);
}

if (!file_exists(__DIR__ . '/../api/v1/mail/MailerManager.php')) {
    echo "ATTENTION: MailerManager non trouvé, emails désactivés" . PHP_EOL;
    $mailEnabled = false;
} else {
    try {
        require_once __DIR__ . '/../api/v1/mail/MailerManager.php';
        $mailEnabled = true;
        echo "✓ MailerManager chargé" . PHP_EOL;
    } catch (Exception $e) {
        echo "ATTENTION: Erreur MailerManager, emails désactivés: " . $e->getMessage() . PHP_EOL;
        $mailEnabled = false;
    }
}

class SubscriptionPaymentProcessor {
    private $db;
    private $mailer = null;
    private $stripeConfig = null;
    private $mailEnabled = false;
    private $processedCount = 0;
    private $errorCount = 0;
    private $startTime;
    private $logFile;
    
    public function __construct($mailEnabled = false) {
        $this->startTime = time();
        $this->mailEnabled = $mailEnabled;
        $this->logFile = __DIR__ . '/logs/subscriptions-pay-' . date('Y-m-d') . '.log';
        
        $this->ensureLogDirectory();
        $this->initializeDatabase();
        
        if ($this->mailEnabled) {
            $this->initializeMailer();
        }
        
        $this->loadStripeConfig();
    }
    
        private function initializeDatabase() {
        try {
            echo "Connexion à la base de données..." . PHP_EOL;
            $this->db = Database::getInstance();
            
                        $stmt = $this->db->prepare("SELECT 1");
            $stmt->execute();
            
            echo "✓ Base de données connectée" . PHP_EOL;
            $this->log("Base de données initialisée avec succès");
            
        } catch (Exception $e) {
            echo "ERREUR BDD: " . $e->getMessage() . PHP_EOL;
            throw new Exception("Impossible de se connecter à la base de données: " . $e->getMessage());
        }
    }
    
        private function initializeMailer() {
        try {
            if (class_exists('MailerManager')) {
                $this->mailer = new MailerManager();
                echo "✓ MailerManager initialisé" . PHP_EOL;
            } else {
                echo "ATTENTION: Classe MailerManager non disponible" . PHP_EOL;
                $this->mailEnabled = false;
            }
        } catch (Exception $e) {
            echo "ATTENTION: Erreur initialisation mailer: " . $e->getMessage() . PHP_EOL;
            $this->mailEnabled = false;
        }
    }
    
        private function loadStripeConfig() {
        try {
            echo "Chargement configuration Stripe..." . PHP_EOL;
            
            $stmt = $this->db->prepare("SELECT config FROM payment_adapters WHERE slug = 'stripe' AND is_active = 1");
            $stmt->execute();
            $config = $stmt->fetchColumn();
            
            if (!$config) {
                throw new Exception('Adaptateur Stripe non trouvé ou désactivé');
            }
            
            $this->stripeConfig = json_decode($config, true);
            
            if (empty($this->stripeConfig['secret_key'])) {
                throw new Exception('Clé secrète Stripe manquante dans la configuration');
            }
            
                        $possiblePaths = [
                __DIR__ . '/../plugins/stripe-php/init.php',
                __DIR__ . '/../../plugins/stripe-php/init.php',
                __DIR__ . '/../../../plugins/stripe-php/init.php',
                __DIR__ . '/../../../../plugins/stripe-php/init.php'
            ];
            
            $stripePath = null;
            foreach ($possiblePaths as $path) {
                if (file_exists($path)) {
                    $stripePath = $path;
                    break;
                }
            }
            
            if (!$stripePath) {
                throw new Exception("SDK Stripe non trouvé. Chemins testés: " . implode(', ', $possiblePaths));
            }
            
            require_once $stripePath;
            \Stripe\Stripe::setApiKey($this->stripeConfig['secret_key']);
            
            echo "✓ Configuration Stripe chargée" . PHP_EOL;
            $this->log("Configuration Stripe chargée avec succès");
            
        } catch (Exception $e) {
            echo "ERREUR STRIPE: " . $e->getMessage() . PHP_EOL;
            $this->log("ERREUR - Configuration Stripe: " . $e->getMessage(), 'ERROR');
            throw new Exception("Configuration Stripe échouée: " . $e->getMessage());
        }
    }
    
        public function processSubscriptions() {
        $this->log("=== DÉBUT DU TRAITEMENT DES ABONNEMENTS ===");
        echo "Recherche des abonnements à traiter..." . PHP_EOL;
        
        try {
                        $subscriptions = $this->getSubscriptionsToProcess();
            
            if (empty($subscriptions)) {
                $this->log("Aucun abonnement à traiter");
                echo "✓ Aucun abonnement à traiter" . PHP_EOL;
                return;
            }
            
            $count = count($subscriptions);
            $this->log("Nombre d'abonnements à traiter: $count");
            echo "Abonnements trouvés: $count" . PHP_EOL . PHP_EOL;
            
            foreach ($subscriptions as $index => $subscription) {
                echo "[$index+1/$count] Traitement de: " . $subscription['subscription_number'] . PHP_EOL;
                $this->processSubscription($subscription);
                
                                usleep(500000);                 echo PHP_EOL;
            }
            
            echo "=== RÉSUMÉ ===" . PHP_EOL;
            echo "Traités avec succès: " . $this->processedCount . PHP_EOL;
            echo "Erreurs: " . $this->errorCount . PHP_EOL;
            echo "Durée totale: " . (time() - $this->startTime) . " secondes" . PHP_EOL;
            
            $this->log("=== FIN DU TRAITEMENT ===");
            $this->log("Traités avec succès: " . $this->processedCount);
            $this->log("Erreurs: " . $this->errorCount);
            $this->log("Durée totale: " . (time() - $this->startTime) . " secondes");
            
        } catch (Exception $e) {
            $this->log("ERREUR FATALE: " . $e->getMessage(), 'ERROR');
            echo "ERREUR FATALE: " . $e->getMessage() . PHP_EOL;
            throw $e;
        }
    }
    
        private function getSubscriptionsToProcess() {
        try {
                        $debugSql = "
                SELECT COUNT(*) as total,
                       COUNT(CASE WHEN u.stripe_customer_id IS NOT NULL THEN 1 END) as with_stripe,
                       COUNT(CASE WHEN u.is_active = 1 THEN 1 END) as active_users,
                       COUNT(CASE WHEN po.id IS NOT NULL THEN 1 END) as with_offers
                FROM subscriptions s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN product_offers po ON s.product_offer_id = po.id
                LEFT JOIN order_items oi ON s.order_item_id = oi.id
                WHERE s.status = 'active'
                AND s.next_billing_date <= NOW()
            ";
            
            $debugStmt = $this->db->prepare($debugSql);
            $debugStmt->execute();
            $debug = $debugStmt->fetch();
            
            echo "Debug abonnements en retard:" . PHP_EOL;
            echo "  Total: " . $debug['total'] . PHP_EOL;
            echo "  Avec Stripe customer: " . $debug['with_stripe'] . PHP_EOL;
            echo "  Utilisateurs actifs: " . $debug['active_users'] . PHP_EOL;
            echo "  Avec product_offers: " . $debug['with_offers'] . PHP_EOL;
            
            $this->log("Debug - Total abonnements en retard: " . $debug['total'] . 
                      ", avec Stripe: " . $debug['with_stripe'] . 
                      ", utilisateurs actifs: " . $debug['active_users'] .
                      ", avec offers: " . $debug['with_offers']);
            
                        $sql = "
                SELECT s.*, u.email, u.first_name, u.last_name, u.stripe_customer_id,
                       COALESCE(po.name, 'Offre supprimée') as offer_name, 
                       COALESCE(p.name, oi.product_name, 'Produit inconnu') as product_name,
                       oi.product_data
                FROM subscriptions s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN product_offers po ON s.product_offer_id = po.id
                LEFT JOIN order_items oi ON s.order_item_id = oi.id
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE s.status = 'active'
                AND s.next_billing_date <= NOW()
                AND u.is_active = 1
                AND u.stripe_customer_id IS NOT NULL
                ORDER BY s.next_billing_date ASC
                LIMIT 50
            ";
            
            echo "Exécution de la requête principale..." . PHP_EOL;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $subscriptions = $stmt->fetchAll();
            
            $this->log("Requête abonnements exécutée, résultats: " . count($subscriptions));
            
            if (empty($subscriptions) && $debug['total'] > 0) {
                                echo "ATTENTION: " . $debug['total'] . " abonnements en retard trouvés mais aucun ne correspond aux critères" . PHP_EOL;
                
                                $problemSql = "
                    SELECT s.id, s.subscription_number, s.next_billing_date,
                           u.id as user_id, u.email, u.is_active, u.stripe_customer_id,
                           s.product_offer_id, po.id as offer_exists
                    FROM subscriptions s
                    JOIN users u ON s.user_id = u.id
                    LEFT JOIN product_offers po ON s.product_offer_id = po.id
                    WHERE s.status = 'active'
                    AND s.next_billing_date <= NOW()
                    LIMIT 5
                ";
                
                $problemStmt = $this->db->prepare($problemSql);
                $problemStmt->execute();
                $problems = $problemStmt->fetchAll();
                
                echo "Abonnements problématiques:" . PHP_EOL;
                foreach ($problems as $problem) {
                    echo "  - " . $problem['subscription_number'] . PHP_EOL;
                    echo "    User active: " . ($problem['is_active'] ? 'OUI' : 'NON') . PHP_EOL;
                    echo "    Stripe customer: " . ($problem['stripe_customer_id'] ? 'OUI' : 'NON') . PHP_EOL;
                    echo "    Product offer: " . ($problem['product_offer_id'] ?: 'NULL') . 
                         " (existe: " . ($problem['offer_exists'] ? 'OUI' : 'NON') . ")" . PHP_EOL;
                    echo PHP_EOL;
                }
            }
            
            return $subscriptions;
            
        } catch (Exception $e) {
            $this->log("ERREUR récupération abonnements: " . $e->getMessage(), 'ERROR');
            echo "ERREUR SQL: " . $e->getMessage() . PHP_EOL;
            throw $e;
        }
    }
    
        private function processSubscription($subscription) {
        $subId = $subscription['subscription_number'];
        $this->log("--- Traitement de l'abonnement: $subId ---");
        echo "  Utilisateur: " . $subscription['first_name'] . " " . $subscription['last_name'] . PHP_EOL;
        echo "  Email: " . $subscription['email'] . PHP_EOL;
        echo "  Montant: " . $subscription['amount'] . " " . $subscription['currency'] . PHP_EOL;
        
        try {
            $this->db->beginTransaction();
            
                        if (empty($subscription['stripe_customer_id'])) {
                throw new Exception("Utilisateur sans customer ID Stripe");
            }
            
                        $missedBillings = $this->calculateMissedBillings($subscription);
            $this->log("Facturations en retard: $missedBillings");
            echo "  Facturations en retard: $missedBillings" . PHP_EOL;
            
            if ($missedBillings == 0) {
                $this->log("Aucune facturation en retard, passage au suivant");
                echo "  ✓ Aucune facturation en retard" . PHP_EOL;
                $this->db->rollback();
                return;
            }
            
                        $paymentsToProcess = min($missedBillings, 1);
            $this->log("Nombre de paiements à traiter: $paymentsToProcess");
            
            for ($i = 0; $i < $paymentsToProcess; $i++) {
                echo "  Tentative de paiement " . ($i + 1) . "/$paymentsToProcess..." . PHP_EOL;
                
                $paymentResult = $this->attemptPayment($subscription);
                
                if ($paymentResult['success']) {
                                        $this->updateSubscriptionDates($subscription['id']);
                    
                                        $this->createPaymentTransaction($subscription, $paymentResult);
                    
                                        if ($this->mailEnabled) {
                        $this->sendPaymentConfirmation($subscription, $paymentResult);
                    }
                    
                    $this->log("Paiement réussi pour période " . ($i + 1));
                    echo "  ✓ Paiement réussi: " . $paymentResult['payment_intent_id'] . PHP_EOL;
                    
                } else {
                                        $this->handlePaymentFailure($subscription, $paymentResult['error']);
                    $this->log("Échec du paiement: " . $paymentResult['error'], 'ERROR');
                    echo "  ✗ Échec: " . $paymentResult['error'] . PHP_EOL;
                    break;                 }
            }
            
            $this->db->commit();
            $this->processedCount++;
            echo "  ✓ Traitement terminé avec succès" . PHP_EOL;
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->errorCount++;
            $this->log("ERREUR abonnement $subId: " . $e->getMessage(), 'ERROR');
            echo "  ✗ ERREUR: " . $e->getMessage() . PHP_EOL;
            
                        if (function_exists('logSecurityEvent')) {
                logSecurityEvent($subscription['user_id'], 'subscription_payment_error', [
                    'subscription_id' => $subscription['id'],
                    'subscription_number' => $subId,
                    'error' => $e->getMessage()
                ], 'failed');
            }
        }
    }
    
        private function calculateMissedBillings($subscription) {
        $nextBilling = new DateTime($subscription['next_billing_date']);
        $now = new DateTime();
        
        if ($nextBilling >= $now) {
            return 0;         }
        
        $cycleDays = $this->getBillingCycleDays($subscription['billing_cycle']);
        $diffDays = $now->diff($nextBilling)->days;
        
        return min(ceil($diffDays / $cycleDays), 6);     }
    
        private function getBillingCycleDays($cycle) {
        return match($cycle) {
            'monthly' => 30,
            'quarterly' => 90,
            'semi_yearly' => 180,
            'yearly' => 365,
            'commitment_3' => 30,
            'commitment_6' => 30,
            'commitment_12' => 30,
            'commitment_24' => 30,
            default => 30
        };
    }
    
        private function attemptPayment($subscription) {
        try {
            echo "    Récupération du client Stripe..." . PHP_EOL;
            
                        $customer = \Stripe\Customer::retrieve($subscription['stripe_customer_id']);
            
            if (!$customer || $customer->deleted) {
                throw new Exception("Client Stripe non trouvé ou supprimé");
            }
            
            echo "    ✓ Client trouvé: " . $customer->id . PHP_EOL;
            echo "    Récupération des méthodes de paiement..." . PHP_EOL;
            
                        $paymentMethods = \Stripe\PaymentMethod::all([
                'customer' => $customer->id,
                'type' => 'card',
            ]);
            
            if (empty($paymentMethods->data)) {
                throw new Exception("Aucune méthode de paiement disponible pour ce client");
            }
            
                        $paymentMethod = $paymentMethods->data[0];
            echo "    ✓ Méthode de paiement: **** " . $paymentMethod->card->last4 . PHP_EOL;
            
                        $amount = round($subscription['amount'] * 100);             echo "    Création du PaymentIntent pour " . ($amount/100) . " " . $subscription['currency'] . "..." . PHP_EOL;
            
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => strtolower($subscription['currency']),
                'customer' => $customer->id,
                'payment_method' => $paymentMethod->id,
                'confirmation_method' => 'automatic',
                'confirm' => true,
                'off_session' => true,                 'metadata' => [
                    'subscription_id' => $subscription['id'],
                    'subscription_number' => $subscription['subscription_number'],
                    'user_id' => $subscription['user_id'],
                    'billing_type' => 'subscription_renewal'
                ]
            ]);
            
            if ($paymentIntent->status === 'succeeded') {
                echo "    ✓ Paiement réussi!" . PHP_EOL;
                return [
                    'success' => true,
                    'payment_intent_id' => $paymentIntent->id,
                    'amount' => $subscription['amount'],
                    'currency' => $subscription['currency']
                ];
            } else {
                throw new Exception("Statut de paiement inattendu: " . $paymentIntent->status);
            }
            
        } catch (\Stripe\Exception\CardException $e) {
                        return [
                'success' => false,
                'error' => 'Carte refusée: ' . $e->getMessage(),
                'stripe_code' => $e->getStripeCode()
            ];
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
                        return [
                'success' => false,
                'error' => 'Erreur Stripe: ' . $e->getMessage(),
                'stripe_code' => $e->getStripeCode() ?? 'unknown'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
        private function updateSubscriptionDates($subscriptionId) {
                $stmt = $this->db->prepare("
            SELECT next_billing_date, billing_cycle 
            FROM subscriptions 
            WHERE id = ?
        ");
        $stmt->execute([$subscriptionId]);
        $sub = $stmt->fetch();
        
        if (!$sub) {
            throw new Exception("Abonnement non trouvé pour mise à jour");
        }
        
                $currentBilling = new DateTime($sub['next_billing_date']);
        $newBillingDate = clone $currentBilling;
        
                switch ($sub['billing_cycle']) {
            case 'monthly':
            case 'commitment_3':
            case 'commitment_6':
            case 'commitment_12':
            case 'commitment_24':
                $newBillingDate->add(new DateInterval('P1M'));
                break;
            case 'quarterly':
                $newBillingDate->add(new DateInterval('P3M'));
                break;
            case 'semi_yearly':
                $newBillingDate->add(new DateInterval('P6M'));
                break;
            case 'yearly':
                $newBillingDate->add(new DateInterval('P1Y'));
                break;
            default:
                $newBillingDate->add(new DateInterval('P1M'));
        }
        
                $updateStmt = $this->db->prepare("
            UPDATE subscriptions 
            SET last_billing_date = ?,
                next_billing_date = ?,
                billing_attempts = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $currentBilling->format('Y-m-d H:i:s'),
            $newBillingDate->format('Y-m-d H:i:s'),
            $subscriptionId
        ]);
        
        $this->log("Dates mises à jour - Prochaine facturation: " . $newBillingDate->format('Y-m-d H:i:s'));
        echo "    ✓ Prochaine facturation: " . $newBillingDate->format('d/m/Y H:i') . PHP_EOL;
    }
    
        private function createPaymentTransaction($subscription, $paymentResult) {
    $transactionId = 'SUB_PAY_' . substr(uniqid(), -8) . strtoupper(substr(bin2hex(random_bytes(4)), 0, 4));
    
        $orderStmt = $this->db->prepare("SELECT order_id FROM order_items WHERE id = ?");
    $orderStmt->execute([$subscription['order_item_id']]);
    $orderId = $orderStmt->fetchColumn();
    
    if (!$orderId) {
        throw new Exception("Impossible de trouver l'order_id pour l'order_item: " . $subscription['order_item_id']);
    }
    
    $stmt = $this->db->prepare("
        INSERT INTO payment_transactions (
            transaction_id, order_id, payment_adapter_id, user_id,
            type, status, amount, currency, net_amount,
            gateway_transaction_id, gateway_response, metadata,
            processed_at, created_at
        ) VALUES (?, ?, 
            (SELECT id FROM payment_adapters WHERE slug = 'stripe'),
            ?, 'payment', 'completed', ?, ?, ?, ?, ?, ?, NOW(), NOW()
        )
    ");
    
    $metadata = [
        'subscription_id' => $subscription['id'],
        'subscription_number' => $subscription['subscription_number'],
        'billing_cycle' => $subscription['billing_cycle'],
        'payment_type' => 'subscription_renewal',
        'order_item_id' => $subscription['order_item_id']     ];
    
    $stmt->execute([
        $transactionId,
        $orderId,         $subscription['user_id'],
        $paymentResult['amount'],
        $paymentResult['currency'],
        $paymentResult['amount'],         $paymentResult['payment_intent_id'],
        json_encode($paymentResult),
        json_encode($metadata)
    ]);
    
    $this->log("Transaction créée: $transactionId pour order_id: $orderId");
    echo "    ✓ Transaction créée: $transactionId" . PHP_EOL;
}
    
        private function sendPaymentConfirmation($subscription, $paymentResult) {
        try {
            echo "    Envoi de l'email de confirmation..." . PHP_EOL;
            
                        $this->ensurePaymentConfirmationTemplate();
            
                        $variables = [
                'USER_FIRST_NAME' => $subscription['first_name'],
                'USER_LAST_NAME' => $subscription['last_name'],
                'SUBSCRIPTION_NUMBER' => $subscription['subscription_number'],
                'PRODUCT_NAME' => $subscription['product_name'],
                'OFFER_NAME' => $subscription['offer_name'],
                'AMOUNT' => number_format($paymentResult['amount'], 2, ',', ' '),
                'CURRENCY' => '€',
                'BILLING_CYCLE' => $this->getBillingCycleLabel($subscription['billing_cycle']),
                'PAYMENT_DATE' => date('d/m/Y à H:i'),
                'NEXT_BILLING_DATE' => '',
                'PAYMENT_METHOD' => 'Carte bancaire',
                'TRANSACTION_ID' => $paymentResult['payment_intent_id']
            ];
            
                        $nextBilling = new DateTime($subscription['next_billing_date']);
            switch ($subscription['billing_cycle']) {
                case 'monthly':
                case 'commitment_3':
                case 'commitment_6':
                case 'commitment_12':
                case 'commitment_24':
                    $nextBilling->add(new DateInterval('P1M'));
                    break;
                case 'quarterly':
                    $nextBilling->add(new DateInterval('P3M'));
                    break;
                case 'semi_yearly':
                    $nextBilling->add(new DateInterval('P6M'));
                    break;
                case 'yearly':
                    $nextBilling->add(new DateInterval('P1Y'));
                    break;
            }
            $variables['NEXT_BILLING_DATE'] = $nextBilling->format('d/m/Y');
            
                        $result = $this->mailer->sendTemplate(
                'subscription_payment_success',
                $subscription['email'],
                $subscription['first_name'] . ' ' . $subscription['last_name'],
                $variables,
                [
                    'category' => 'transactional',
                    'priority' => 2
                ]
            );
            
            if ($result['success']) {
                $this->log("Email de confirmation envoyé à: " . $subscription['email']);
                echo "    ✓ Email de confirmation envoyé" . PHP_EOL;
            } else {
                $this->log("Erreur envoi email: " . ($result['error'] ?? 'Erreur inconnue'), 'WARNING');
                echo "    ⚠ Erreur envoi email: " . ($result['error'] ?? 'Erreur inconnue') . PHP_EOL;
            }
            
        } catch (Exception $e) {
            $this->log("Erreur envoi email de confirmation: " . $e->getMessage(), 'WARNING');
            echo "    ⚠ Erreur envoi email: " . $e->getMessage() . PHP_EOL;
        }
    }

        private function sendPaymentFailureEmail($subscription, $error, $attempts) {
        try {
            echo "    Envoi de l'email d'échec de paiement..." . PHP_EOL;

                        $this->ensurePaymentFailureTemplate();

                        $variables = [
                'USER_FIRST_NAME' => $subscription['first_name'],
                'USER_LAST_NAME' => $subscription['last_name'],
                'SUBSCRIPTION_NUMBER' => $subscription['subscription_number'],
                'PRODUCT_NAME' => $subscription['product_name'],
                'OFFER_NAME' => $subscription['offer_name'],
                'AMOUNT' => number_format($subscription['amount'], 2, ',', ' '),
                'CURRENCY' => '€',
                'BILLING_CYCLE' => $this->getBillingCycleLabel($subscription['billing_cycle']),
                'ERROR_MESSAGE' => $error,
                'ATTEMPTS' => $attempts,
                'NEXT_BILLING_DATE' => date('d/m/Y', strtotime($subscription['next_billing_date'])),
                'ACCOUNT_URL' => SITE_URL . '/dashboard/subscriptions',
                'PAYMENT_METHOD_URL' => SITE_URL . '/dashboard/payment-methods',
                'SUPPORT_EMAIL' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@piloteshopping.fr'
            ];

                        $result = $this->mailer->sendTemplate(
                'subscription_payment_failed',
                $subscription['email'],
                $subscription['first_name'] . ' ' . $subscription['last_name'],
                $variables,
                [
                    'category' => 'transactional',
                    'priority' => 1                 ]
            );

            if ($result['success']) {
                $this->log("Email d'échec envoyé à: " . $subscription['email']);
                echo "    ✓ Email d'échec envoyé" . PHP_EOL;
            } else {
                $this->log("Erreur envoi email d'échec: " . ($result['error'] ?? 'Erreur inconnue'), 'WARNING');
                echo "    ⚠ Erreur envoi email: " . ($result['error'] ?? 'Erreur inconnue') . PHP_EOL;
            }

        } catch (Exception $e) {
            $this->log("Erreur envoi email d'échec: " . $e->getMessage(), 'WARNING');
            echo "    ⚠ Erreur envoi email: " . $e->getMessage() . PHP_EOL;
        }
    }

        private function sendCommitmentPaymentFailureEmail($subscription, $error, $attempts) {
        try {
            echo "    Envoi de l'email d'échec ENGAGEMENT (1ère tentative)..." . PHP_EOL;

                        $this->ensureCommitmentPaymentFailureTemplate();

                        $commitmentDuration = $this->getCommitmentDuration($subscription['billing_cycle']);
            $remainingMonths = $commitmentDuration;             $totalDebt = $subscription['amount'] * $remainingMonths;

                        $variables = [
                'USER_FIRST_NAME' => $subscription['first_name'],
                'USER_LAST_NAME' => $subscription['last_name'],
                'SUBSCRIPTION_NUMBER' => $subscription['subscription_number'],
                'PRODUCT_NAME' => $subscription['product_name'],
                'OFFER_NAME' => $subscription['offer_name'],
                'AMOUNT' => number_format($subscription['amount'], 2, ',', ' '),
                'CURRENCY' => '€',
                'BILLING_CYCLE' => $this->getBillingCycleLabel($subscription['billing_cycle']),
                'ERROR_MESSAGE' => $error,
                'ATTEMPTS' => $attempts,
                'COMMITMENT_DURATION' => $commitmentDuration,
                'REMAINING_MONTHS' => $remainingMonths,
                'TOTAL_DEBT' => number_format($totalDebt, 2, ',', ' '),
                'NEXT_BILLING_DATE' => date('d/m/Y', strtotime($subscription['next_billing_date'])),
                'ACCOUNT_URL' => SITE_URL . '/dashboard/subscriptions',
                'PAYMENT_METHOD_URL' => SITE_URL . '/dashboard/payment-methods',
                'SUPPORT_EMAIL' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@piloteshopping.fr',
                'TO_EMAIL' => $subscription['email']
            ];

                        $result = $this->mailer->sendTemplate(
                'subscription_commitment_payment_failed',
                $subscription['email'],
                $subscription['first_name'] . ' ' . $subscription['last_name'],
                $variables,
                [
                    'category' => 'transactional',
                    'priority' => 1                 ]
            );

            if ($result['success']) {
                $this->log("Email d'échec ENGAGEMENT (1ère tentative) envoyé à: " . $subscription['email']);
                echo "    ✓ Email d'échec ENGAGEMENT envoyé" . PHP_EOL;
            } else {
                $this->log("Erreur envoi email ENGAGEMENT: " . ($result['error'] ?? 'Erreur inconnue'), 'WARNING');
                echo "    ⚠ Erreur envoi email: " . ($result['error'] ?? 'Erreur inconnue') . PHP_EOL;
            }

        } catch (Exception $e) {
            $this->log("Erreur envoi email ENGAGEMENT: " . $e->getMessage(), 'WARNING');
            echo "    ⚠ Erreur envoi email: " . $e->getMessage() . PHP_EOL;
        }
    }

        private function sendCommitmentReminderEmail($subscription, $error, $attempts) {
        try {
            echo "    Envoi de l'email de RAPPEL ENGAGEMENT (tentative $attempts / 12)..." . PHP_EOL;

                        $this->ensureCommitmentPaymentFailureTemplate();

                        $commitmentDuration = $this->getCommitmentDuration($subscription['billing_cycle']);
            $remainingMonths = $commitmentDuration;             $totalDebt = $subscription['amount'] * $remainingMonths;

                        $variables = [
                'USER_FIRST_NAME' => $subscription['first_name'],
                'USER_LAST_NAME' => $subscription['last_name'],
                'SUBSCRIPTION_NUMBER' => $subscription['subscription_number'],
                'PRODUCT_NAME' => $subscription['product_name'],
                'OFFER_NAME' => $subscription['offer_name'],
                'AMOUNT' => number_format($subscription['amount'], 2, ',', ' '),
                'CURRENCY' => '€',
                'BILLING_CYCLE' => $this->getBillingCycleLabel($subscription['billing_cycle']),
                'ERROR_MESSAGE' => $error,
                'ATTEMPTS' => $attempts,
                'COMMITMENT_DURATION' => $commitmentDuration,
                'REMAINING_MONTHS' => $remainingMonths,
                'TOTAL_DEBT' => number_format($totalDebt, 2, ',', ' '),
                'NEXT_BILLING_DATE' => date('d/m/Y', strtotime($subscription['next_billing_date'])),
                'ACCOUNT_URL' => SITE_URL . '/dashboard/subscriptions',
                'PAYMENT_METHOD_URL' => SITE_URL . '/dashboard/payment-methods',
                'SUPPORT_EMAIL' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@piloteshopping.fr',
                'TO_EMAIL' => $subscription['email']
            ];

                        $result = $this->mailer->sendTemplate(
                'subscription_commitment_payment_failed',
                $subscription['email'],
                $subscription['first_name'] . ' ' . $subscription['last_name'],
                $variables,
                [
                    'category' => 'transactional',
                    'priority' => 1,
                    'subject_override' => "RAPPEL $attempts/12 - Échec paiement abonnement ENGAGEMENT - {$subscription['subscription_number']}"
                ]
            );

            if ($result['success']) {
                $this->log("Email de RAPPEL ENGAGEMENT (tentative $attempts) envoyé à: " . $subscription['email']);
                echo "    ✓ Email de RAPPEL ENGAGEMENT envoyé" . PHP_EOL;
            } else {
                $this->log("Erreur envoi email RAPPEL ENGAGEMENT: " . ($result['error'] ?? 'Erreur inconnue'), 'WARNING');
                echo "    ⚠ Erreur envoi email: " . ($result['error'] ?? 'Erreur inconnue') . PHP_EOL;
            }

        } catch (Exception $e) {
            $this->log("Erreur envoi email RAPPEL ENGAGEMENT: " . $e->getMessage(), 'WARNING');
            echo "    ⚠ Erreur envoi email: " . $e->getMessage() . PHP_EOL;
        }
    }

        private function sendCommitmentFinalWarningEmail($subscription, $error, $attempts) {
        try {
            echo "    Envoi de l'email MISE EN DEMEURE ENGAGEMENT..." . PHP_EOL;

                        $this->ensureCommitmentFinalWarningTemplate();

                        $commitmentDuration = $this->getCommitmentDuration($subscription['billing_cycle']);
            $remainingMonths = $commitmentDuration;             $totalDebt = $subscription['amount'] * $remainingMonths;

                        $variables = [
                'USER_FIRST_NAME' => $subscription['first_name'],
                'USER_LAST_NAME' => $subscription['last_name'],
                'SUBSCRIPTION_NUMBER' => $subscription['subscription_number'],
                'PRODUCT_NAME' => $subscription['product_name'],
                'OFFER_NAME' => $subscription['offer_name'],
                'AMOUNT' => number_format($subscription['amount'], 2, ',', ' '),
                'CURRENCY' => '€',
                'BILLING_CYCLE' => $this->getBillingCycleLabel($subscription['billing_cycle']),
                'ERROR_MESSAGE' => $error,
                'ATTEMPTS' => $attempts,
                'COMMITMENT_DURATION' => $commitmentDuration,
                'REMAINING_MONTHS' => $remainingMonths,
                'TOTAL_DEBT' => number_format($totalDebt, 2, ',', ' '),
                'DEADLINE_DATE' => date('d/m/Y', strtotime('+7 days')),                 'ACCOUNT_URL' => SITE_URL . '/dashboard/subscriptions',
                'PAYMENT_METHOD_URL' => SITE_URL . '/dashboard/payment-methods',
                'SUPPORT_EMAIL' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@piloteshopping.fr',
                'COMPANY_NAME' => SITE_NAME,
                'CURRENT_DATE' => date('d/m/Y'),
                'TO_EMAIL' => $subscription['email']
            ];

                        $result = $this->mailer->sendTemplate(
                'subscription_commitment_final_warning',
                $subscription['email'],
                $subscription['first_name'] . ' ' . $subscription['last_name'],
                $variables,
                [
                    'category' => 'transactional',
                    'priority' => 1                 ]
            );

            if ($result['success']) {
                $this->log("Email MISE EN DEMEURE ENGAGEMENT envoyé à: " . $subscription['email']);
                echo "    ✓ Email MISE EN DEMEURE envoyé" . PHP_EOL;
            } else {
                $this->log("Erreur envoi email MISE EN DEMEURE: " . ($result['error'] ?? 'Erreur inconnue'), 'WARNING');
                echo "    ⚠ Erreur envoi email: " . ($result['error'] ?? 'Erreur inconnue') . PHP_EOL;
            }

        } catch (Exception $e) {
            $this->log("Erreur envoi email MISE EN DEMEURE: " . $e->getMessage(), 'WARNING');
            echo "    ⚠ Erreur envoi email: " . $e->getMessage() . PHP_EOL;
        }
    }

        private function handlePaymentFailure($subscription, $error) {
                $stmt = $this->db->prepare("
            UPDATE subscriptions
            SET billing_attempts = billing_attempts + 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$subscription['id']]);

                $attempts = $subscription['billing_attempts'] + 1;
        $isCommitment = $this->isCommitmentSubscription($subscription['billing_cycle']);

                $maxAttempts = $isCommitment ? 12 : 3;

                if ($isCommitment && $attempts === 1 && $this->mailEnabled) {
            $this->log("Engagement détecté, envoi email dès la 1ère tentative");
            echo "    ⚠ ENGAGEMENT: Envoi email dès la 1ère tentative" . PHP_EOL;
            $this->sendCommitmentPaymentFailureEmail($subscription, $error, $attempts);
        }

                if ($isCommitment && in_array($attempts, [3, 6, 9]) && $this->mailEnabled) {
            $this->log("Engagement: Email de rappel tentative $attempts");
            echo "    ⚠ ENGAGEMENT: Email de rappel tentative $attempts / 12" . PHP_EOL;
            $this->sendCommitmentReminderEmail($subscription, $error, $attempts);
        }

                if ($attempts >= $maxAttempts) {
                        if (!$isCommitment) {
                $suspendStmt = $this->db->prepare("
                    UPDATE subscriptions
                    SET status = 'failed',
                        cancellation_reason = ?
                    WHERE id = ?
                ");
                $suspendStmt->execute([
                    "Paiement échoué après $attempts tentatives: $error",
                    $subscription['id']
                ]);

                $this->log("Abonnement classique suspendu après $attempts tentatives");
                echo "    ⚠ Abonnement classique suspendu après $attempts tentatives" . PHP_EOL;

                                $this->createFailureNotification($subscription, $error, $attempts);

                                if ($this->mailEnabled) {
                    $this->sendPaymentFailureEmail($subscription, $error, $attempts);
                }
            }
                        else {
                $this->log("ENGAGEMENT: Mise en demeure envoyée à la tentative $attempts");
                echo "    🚨 ENGAGEMENT: Envoi MISE EN DEMEURE tentative $attempts" . PHP_EOL;

                                $this->createFailureNotification($subscription, $error, $attempts);

                                if ($this->mailEnabled) {
                    $this->sendCommitmentFinalWarningEmail($subscription, $error, $attempts);
                }
            }
        }

                        if ($isCommitment && $attempts > 12) {
                        $relanceSchedule = [16, 28, 35, 40, 45, 50, 60, 70, 90, 120, 180, 270, 365];
            $daysFromFirstFailure = $attempts; 
            if (in_array($daysFromFirstFailure, $relanceSchedule)) {
                $this->log("ENGAGEMENT: Email de relance post-mise en demeure - jour $daysFromFirstFailure");
                echo "    📧 ENGAGEMENT: Relance jour $daysFromFirstFailure (tentative $attempts)" . PHP_EOL;

                if ($this->mailEnabled) {
                    $this->sendCommitmentFollowUpEmail($subscription, $error, $attempts, $daysFromFirstFailure);
                }
            }

                        if ($attempts >= 365) {
                $suspendStmt = $this->db->prepare("
                    UPDATE subscriptions
                    SET status = 'failed',
                        cancellation_reason = ?
                    WHERE id = ?
                ");
                $suspendStmt->execute([
                    "ENGAGEMENT - Paiement échoué après 1 an (365 jours / $attempts tentatives): $error",
                    $subscription['id']
                ]);

                $this->log("ENGAGEMENT suspendu définitivement après 1 an (tentative $attempts)");
                echo "    ❌ ENGAGEMENT suspendu définitivement après 365 jours" . PHP_EOL;
            }
        }

                if (function_exists('logSecurityEvent')) {
            logSecurityEvent($subscription['user_id'], 'subscription_payment_failed', [
                'subscription_id' => $subscription['id'],
                'subscription_number' => $subscription['subscription_number'],
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'error' => $error,
                'is_commitment' => $isCommitment
            ], 'failed');
        }
    }
    
        private function sendCommitmentFollowUpEmail($subscription, $error, $attempts, $daysSinceFirst) {
        try {
            echo "    Envoi de l'email de RELANCE ENGAGEMENT (jour $daysSinceFirst)..." . PHP_EOL;

                        $this->ensureCommitmentFinalWarningTemplate();

                        $commitmentDuration = $this->getCommitmentDuration($subscription['billing_cycle']);
            $remainingMonths = $commitmentDuration;             $totalDebt = $subscription['amount'] * $remainingMonths;

                        $variables = [
                'USER_FIRST_NAME' => $subscription['first_name'],
                'USER_LAST_NAME' => $subscription['last_name'],
                'SUBSCRIPTION_NUMBER' => $subscription['subscription_number'],
                'PRODUCT_NAME' => $subscription['product_name'],
                'OFFER_NAME' => $subscription['offer_name'],
                'AMOUNT' => number_format($subscription['amount'], 2, ',', ' '),
                'CURRENCY' => '€',
                'BILLING_CYCLE' => $this->getBillingCycleLabel($subscription['billing_cycle']),
                'ERROR_MESSAGE' => $error,
                'ATTEMPTS' => $attempts,
                'COMMITMENT_DURATION' => $commitmentDuration,
                'REMAINING_MONTHS' => $remainingMonths,
                'TOTAL_DEBT' => number_format($totalDebt, 2, ',', ' '),
                'DEADLINE_DATE' => date('d/m/Y', strtotime('+3 days')),                 'ACCOUNT_URL' => SITE_URL . '/dashboard/subscriptions',
                'PAYMENT_METHOD_URL' => SITE_URL . '/dashboard/payment-methods',
                'SUPPORT_EMAIL' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : 'support@piloteshopping.fr',
                'COMPANY_NAME' => SITE_NAME,
                'CURRENT_DATE' => date('d/m/Y'),
                'TO_EMAIL' => $subscription['email']
            ];

                        $result = $this->mailer->sendTemplate(
                'subscription_commitment_final_warning',
                $subscription['email'],
                $subscription['first_name'] . ' ' . $subscription['last_name'],
                $variables,
                [
                    'category' => 'transactional',
                    'priority' => 1,
                    'subject_override' => "RELANCE Jour $daysSinceFirst - Dette impayée - Abonnement {$subscription['subscription_number']}"
                ]
            );

            if ($result['success']) {
                $this->log("Email de RELANCE ENGAGEMENT (jour $daysSinceFirst) envoyé à: " . $subscription['email']);
                echo "    ✓ Email de RELANCE ENGAGEMENT envoyé" . PHP_EOL;
            } else {
                $this->log("Erreur envoi email RELANCE ENGAGEMENT: " . ($result['error'] ?? 'Erreur inconnue'), 'WARNING');
                echo "    ⚠ Erreur envoi email: " . ($result['error'] ?? 'Erreur inconnue') . PHP_EOL;
            }

        } catch (Exception $e) {
            $this->log("Erreur envoi email RELANCE ENGAGEMENT: " . $e->getMessage(), 'WARNING');
            echo "    ⚠ Erreur envoi email: " . $e->getMessage() . PHP_EOL;
        }
    }

        private function createFailureNotification($subscription, $error, $attempts) {
        try {
                        $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, title, message, data, created_at)
                VALUES (?, 'subscription_failed', ?, ?, ?, NOW())
            ");
            
            $title = "Problème de paiement - Abonnement " . $subscription['subscription_number'];
            $message = "Votre paiement d'abonnement a échoué ($attempts tentatives). Veuillez mettre à jour votre méthode de paiement.";
            
            $stmt->execute([
                $subscription['user_id'],
                $title,
                $message,
                json_encode([
                    'subscription_id' => $subscription['id'],
                    'error' => $error,
                    'attempts' => $attempts
                ])
            ]);
            
            $this->log("Notification d'échec créée pour utilisateur: " . $subscription['user_id']);
            
        } catch (Exception $e) {
            $this->log("Erreur création notification: " . $e->getMessage(), 'WARNING');
        }
    }
    
        private function isCommitmentSubscription($billingCycle) {
        return in_array($billingCycle, ['commitment_3', 'commitment_6', 'commitment_12', 'commitment_24']);
    }

        private function getCommitmentDuration($billingCycle) {
        $durations = [
            'commitment_3' => 3,
            'commitment_6' => 6,
            'commitment_12' => 12,
            'commitment_24' => 24
        ];

        return $durations[$billingCycle] ?? 0;
    }

        private function getBillingCycleLabel($cycle) {
        $labels = [
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
    
        private function ensurePaymentConfirmationTemplate() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM email_templates 
                WHERE slug = 'subscription_payment_success'
            ");
            $stmt->execute();
            
            if ($stmt->fetchColumn() == 0) {
                $this->createPaymentConfirmationTemplate();
            }
        } catch (Exception $e) {
            $this->log("Erreur vérification template: " . $e->getMessage(), 'WARNING');
        }
    }
    
        private function createPaymentConfirmationTemplate() {
        $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Paiement confirmé - {{SITE_NAME}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .header { text-align: center; border-bottom: 2px solid #0EA5E9; padding-bottom: 20px; margin-bottom: 30px; }
        .success { color: #10B981; font-size: 48px; margin: 20px 0; }
        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .info-row { margin: 10px 0; }
        .label { font-weight: bold; color: #374151; }
        .button { display: inline-block; padding: 12px 24px; background: #0EA5E9; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{SITE_NAME}}</h1>
            <div class="success">✓</div>
            <h2 style="color: #10B981;">Paiement confirmé</h2>
        </div>
        
        <p>Bonjour {{USER_FIRST_NAME}},</p>
        
        <p>Nous vous confirmons que votre paiement d\'abonnement a été traité avec succès.</p>
        
        <div class="info-box">
            <h3>Détails du paiement</h3>
            <div class="info-row"><span class="label">Abonnement :</span> {{SUBSCRIPTION_NUMBER}}</div>
            <div class="info-row"><span class="label">Produit :</span> {{PRODUCT_NAME}} - {{OFFER_NAME}}</div>
            <div class="info-row"><span class="label">Montant :</span> {{AMOUNT}} {{CURRENCY}}</div>
            <div class="info-row"><span class="label">Cycle :</span> {{BILLING_CYCLE}}</div>
            <div class="info-row"><span class="label">Date :</span> {{PAYMENT_DATE}}</div>
            <div class="info-row"><span class="label">Prochaine facturation :</span> {{NEXT_BILLING_DATE}}</div>
            <div class="info-row"><span class="label">Transaction :</span> {{TRANSACTION_ID}}</div>
        </div>
        
        <p>Votre abonnement reste actif et vous continuez à bénéficier de nos services.</p>
        
        <p style="text-align: center;">
            <a href="{{SITE_URL}}/account/subscriptions" class="button">Gérer mes abonnements</a>
        </p>
        
        <p>Merci de votre confiance,<br>L\'équipe {{SITE_NAME}}</p>
    </div>
</body>
</html>';
        
        $textContent = 'Bonjour {{USER_FIRST_NAME}},

Nous vous confirmons que votre paiement d\'abonnement a été traité avec succès.

Détails :
- Abonnement : {{SUBSCRIPTION_NUMBER}}
- Produit : {{PRODUCT_NAME}} - {{OFFER_NAME}}
- Montant : {{AMOUNT}} {{CURRENCY}}
- Cycle : {{BILLING_CYCLE}}
- Date : {{PAYMENT_DATE}}
- Prochaine facturation : {{NEXT_BILLING_DATE}}
- Transaction : {{TRANSACTION_ID}}

Votre abonnement reste actif.

Gérer vos abonnements : {{SITE_URL}}/account/subscriptions

Merci de votre confiance,
L\'équipe {{SITE_NAME}}';
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_templates (
                    slug, name, subject, html_content, text_content, 
                    category, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");
            
            $stmt->execute([
                'subscription_payment_success',
                'Confirmation de paiement d\'abonnement',
                'Paiement confirmé - Abonnement {{SUBSCRIPTION_NUMBER}}',
                $htmlContent,
                $textContent,
                'transactional'
            ]);
            
            $this->log("Template de confirmation de paiement créé");
            
        } catch (Exception $e) {
            $this->log("Erreur création template: " . $e->getMessage(), 'WARNING');
        }
    }

        private function ensurePaymentFailureTemplate() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM email_templates
                WHERE slug = 'subscription_payment_failed'
            ");
            $stmt->execute();

            if ($stmt->fetchColumn() == 0) {
                $this->createPaymentFailureTemplate();
            }
        } catch (Exception $e) {
            $this->log("Erreur vérification template: " . $e->getMessage(), 'WARNING');
        }
    }

        private function createPaymentFailureTemplate() {
        $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Échec de paiement - {{SITE_NAME}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        .header { text-align: center; border-bottom: 2px solid #EF4444; padding-bottom: 20px; margin-bottom: 30px; }
        .error { color: #EF4444; font-size: 48px; margin: 20px 0; }
        .alert-box { background: #FEF2F2; border: 1px solid #FCA5A5; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .info-row { margin: 10px 0; }
        .label { font-weight: bold; color: #374151; }
        .button { display: inline-block; padding: 12px 24px; background: #EF4444; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .button-secondary { display: inline-block; padding: 12px 24px; background: #0EA5E9; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
        .warning { color: #F59E0B; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{SITE_NAME}}</h1>
            <div class="error">⚠</div>
            <h2 style="color: #EF4444;">Problème de paiement</h2>
        </div>

        <p>Bonjour {{USER_FIRST_NAME}},</p>

        <div class="alert-box">
            <h3 style="color: #EF4444; margin-top: 0;">Le paiement de votre abonnement a échoué</h3>
            <p>Nous avons rencontré un problème lors du traitement du paiement de votre abonnement.</p>
        </div>

        <div class="info-box">
            <h3>Détails de l\'abonnement</h3>
            <div class="info-row"><span class="label">Abonnement :</span> {{SUBSCRIPTION_NUMBER}}</div>
            <div class="info-row"><span class="label">Produit :</span> {{PRODUCT_NAME}} - {{OFFER_NAME}}</div>
            <div class="info-row"><span class="label">Montant :</span> {{AMOUNT}} {{CURRENCY}}</div>
            <div class="info-row"><span class="label">Cycle :</span> {{BILLING_CYCLE}}</div>
            <div class="info-row"><span class="label">Tentative(s) :</span> <span class="warning">{{ATTEMPTS}} / 3</span></div>
        </div>

        <div class="info-box">
            <h3>Raison de l\'échec</h3>
            <p style="color: #DC2626; font-style: italic;">{{ERROR_MESSAGE}}</p>
        </div>

        <div class="alert-box">
            <h3 style="color: #F59E0B; margin-top: 0;">Action requise</h3>
            <p><strong>Votre abonnement a été suspendu après {{ATTEMPTS}} tentatives de paiement échouées.</strong></p>
            <p>Pour réactiver votre abonnement, veuillez :</p>
            <ol>
                <li>Vérifier que votre carte bancaire est valide et possède des fonds suffisants</li>
                <li>Mettre à jour votre méthode de paiement si nécessaire</li>
                <li>Contacter notre support si le problème persiste</li>
            </ol>
        </div>

        <p style="text-align: center;">
            <a href="{{PAYMENT_METHOD_URL}}" class="button">Mettre à jour ma méthode de paiement</a>
        </p>

        <p style="text-align: center;">
            <a href="{{ACCOUNT_URL}}" class="button-secondary">Gérer mes abonnements</a>
        </p>

        <p>Si vous pensez qu\'il s\'agit d\'une erreur ou si vous avez besoin d\'aide, n\'hésitez pas à contacter notre support à <a href="mailto:{{SUPPORT_EMAIL}}">{{SUPPORT_EMAIL}}</a>.</p>

        <p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 12px;">
            Cet email concerne votre abonnement {{SUBSCRIPTION_NUMBER}}. Si vous souhaitez annuler votre abonnement, rendez-vous dans votre <a href="{{ACCOUNT_URL}}">espace client</a>.
        </p>

        <p>L\'équipe {{SITE_NAME}}</p>
    </div>
</body>
</html>';

        $textContent = 'Bonjour {{USER_FIRST_NAME}},

PROBLÈME DE PAIEMENT

Le paiement de votre abonnement a échoué après {{ATTEMPTS}} tentative(s).

Détails :
- Abonnement : {{SUBSCRIPTION_NUMBER}}
- Produit : {{PRODUCT_NAME}} - {{OFFER_NAME}}
- Montant : {{AMOUNT}} {{CURRENCY}}
- Cycle : {{BILLING_CYCLE}}
- Tentatives : {{ATTEMPTS}} / 3

Raison de l\'échec :
{{ERROR_MESSAGE}}

ACTION REQUISE

Votre abonnement a été suspendu. Pour le réactiver, veuillez :
1. Vérifier que votre carte bancaire est valide
2. Mettre à jour votre méthode de paiement : {{PAYMENT_METHOD_URL}}
3. Contacter notre support si besoin : {{SUPPORT_EMAIL}}

Gérer vos abonnements : {{ACCOUNT_URL}}

L\'équipe {{SITE_NAME}}';

        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_templates (
                    slug, name, subject, html_content, text_content,
                    category, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");

            $stmt->execute([
                'subscription_payment_failed',
                'Échec de paiement d\'abonnement',
                'Problème de paiement - Abonnement {{SUBSCRIPTION_NUMBER}}',
                $htmlContent,
                $textContent,
                'transactional'
            ]);

            $this->log("Template d'échec de paiement créé");

        } catch (Exception $e) {
            $this->log("Erreur création template d'échec: " . $e->getMessage(), 'WARNING');
        }
    }

        private function ensureCommitmentPaymentFailureTemplate() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM email_templates
                WHERE slug = 'subscription_commitment_payment_failed'
            ");
            $stmt->execute();

            if ($stmt->fetchColumn() == 0) {
                $this->createCommitmentPaymentFailureTemplate();
            }
        } catch (Exception $e) {
            $this->log("Erreur vérification template ENGAGEMENT: " . $e->getMessage(), 'WARNING');
        }
    }

        private function createCommitmentPaymentFailureTemplate() {
        $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>URGENT - Échec paiement abonnement avec ENGAGEMENT - {{SITE_NAME}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; border: 3px solid #DC2626; }
        .header { text-align: center; border-bottom: 3px solid #DC2626; padding-bottom: 20px; margin-bottom: 30px; }
        .urgent { color: #DC2626; font-size: 56px; margin: 20px 0; font-weight: bold; }
        .alert-box { background: #FEE2E2; border: 2px solid #DC2626; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .legal-box { background: #FEF3C7; border: 2px solid #F59E0B; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .info-row { margin: 10px 0; }
        .label { font-weight: bold; color: #374151; }
        .button { display: inline-block; padding: 16px 32px; background: #DC2626; color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; font-weight: bold; font-size: 16px; }
        .warning { color: #DC2626; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>{{SITE_NAME}}</h1>
            <div class="urgent">⚠️ URGENT</div>
            <h2 style="color: #DC2626;">Échec de paiement - ABONNEMENT AVEC ENGAGEMENT</h2>
        </div>

        <p>Madame, Monsieur {{USER_LAST_NAME}},</p>

        <div class="alert-box">
            <h3 style="color: #DC2626; margin-top: 0;">⚠️ PREMIÈRE TENTATIVE DE PRÉLÈVEMENT ÉCHOUÉE</h3>
            <p><strong>Nous vous informons que le paiement de votre abonnement avec ENGAGEMENT a échoué.</strong></p>
            <p>Conformément aux Conditions Générales de Vente que vous avez acceptées lors de votre souscription, vous êtes contractuellement engagé(e) pour une durée de <strong>{{COMMITMENT_DURATION}} mois</strong>.</p>
        </div>

        <div class="info-box">
            <h3>Détails de votre abonnement</h3>
            <div class="info-row"><span class="label">Numéro d\'abonnement :</span> {{SUBSCRIPTION_NUMBER}}</div>
            <div class="info-row"><span class="label">Produit/Service :</span> {{PRODUCT_NAME}} - {{OFFER_NAME}}</div>
            <div class="info-row"><span class="label">Montant mensuel :</span> <span class="warning">{{AMOUNT}} {{CURRENCY}}</span></div>
            <div class="info-row"><span class="label">Type d\'engagement :</span> {{BILLING_CYCLE}}</div>
            <div class="info-row"><span class="label">Durée totale engagement :</span> <strong>{{COMMITMENT_DURATION}} mois</strong></div>
            <div class="info-row"><span class="label">Dette potentielle totale :</span> <span class="warning">{{TOTAL_DEBT}} {{CURRENCY}}</span></div>
        </div>

        <div class="legal-box">
            <h3 style="color: #F59E0B; margin-top: 0;">⚖️ RAPPEL DE VOS OBLIGATIONS CONTRACTUELLES</h3>
            <p><strong>En souscrivant à un abonnement avec engagement, vous avez accepté :</strong></p>
            <ul>
                <li>De régler l\'intégralité des mensualités sur toute la durée de l\'engagement</li>
                <li>Que toute interruption de paiement constitue un manquement contractuel</li>
                <li>Que les sommes restant dues demeurent exigibles même en cas de non-paiement</li>
            </ul>
            <p style="color: #DC2626; font-weight: bold;">⚠️ Le non-règlement de cet abonnement peut entraîner :</p>
            <ul>
                <li>L\'exigibilité immédiate de la totalité des sommes dues ({{TOTAL_DEBT}} {{CURRENCY}})</li>
                <li>Des pénalités de retard selon les conditions générales de vente</li>
                <li>La transmission du dossier à un cabinet de recouvrement</li>
                <li>Un impayé pouvant, à terme, avoir un impact sur votre solvabilité</li>
                <li>Des procédures judiciaires et des frais associés le cas échéant</li>
            </ul>
        </div>

        <div class="info-box">
            <h3 style="color: #DC2626;">Raison de l\'échec</h3>
            <p style="color: #DC2626; font-style: italic;"><strong>{{ERROR_MESSAGE}}</strong></p>
        </div>

        <div class="alert-box">
            <h3 style="color: #DC2626; margin-top: 0;">✅ ACTION IMMÉDIATE REQUISE</h3>
            <p><strong>Pour régulariser votre situation dans les plus brefs délais :</strong></p>
            <ol>
                <li><strong>Vérifiez immédiatement votre méthode de paiement</strong></li>
                <li>Assurez-vous que votre carte bancaire est valide et possède les fonds nécessaires</li>
                <li>Mettez à jour vos informations de paiement si nécessaire</li>
            </ol>
            <p style="color: #DC2626;"><strong>⏰ Vous disposez de 48 heures pour régulariser avant une nouvelle tentative automatique.</strong></p>
        </div>

        <p style="text-align: center;">
            <a href="{{PAYMENT_METHOD_URL}}" class="button">► RÉGULARISER MAINTENANT</a>
        </p>

        <p style="margin-top: 30px; padding: 20px; background: #F3F4F6; border-left: 4px solid #0EA5E9; border-radius: 4px;">
            <strong>💬 Besoin d\'aide ?</strong><br>
            Si vous rencontrez des difficultés ou souhaitez discuter d\'un échéancier de paiement, contactez notre service client au plus vite à <a href="mailto:{{SUPPORT_EMAIL}}">{{SUPPORT_EMAIL}}</a>.
        </p>

        <p style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 11px;">
            <strong>Mentions légales :</strong> Cet email constitue un rappel formel de vos obligations contractuelles concernant l\'abonnement {{SUBSCRIPTION_NUMBER}} souscrit auprès de {{SITE_NAME}}. Conformément aux articles L. 215-1 et suivants du Code de la consommation, le non-paiement d\'un abonnement avec engagement peut donner lieu à l\'exigibilité immédiate de la totalité des sommes dues. Pour toute question, consultez nos CGV disponibles sur notre site.
        </p>

        <p style="text-align: center; color: #6b7280; font-size: 12px; margin-top: 20px;">
            {{SITE_NAME}} - Email automatique - Ne pas répondre
        </p>
    </div>
</body>
</html>';

        $textContent = 'URGENT - ÉCHEC DE PAIEMENT - ABONNEMENT AVEC ENGAGEMENT

Madame, Monsieur {{USER_LAST_NAME}},

⚠️ PREMIÈRE TENTATIVE DE PRÉLÈVEMENT ÉCHOUÉE

Le paiement de votre abonnement avec ENGAGEMENT a échoué.

RAPPEL : Vous êtes contractuellement engagé(e) pour {{COMMITMENT_DURATION}} mois.

DÉTAILS DE VOTRE ABONNEMENT :
- Abonnement : {{SUBSCRIPTION_NUMBER}}
- Produit : {{PRODUCT_NAME}} - {{OFFER_NAME}}
- Montant mensuel : {{AMOUNT}} {{CURRENCY}}
- Engagement : {{BILLING_CYCLE}}
- Durée totale : {{COMMITMENT_DURATION}} mois
- Dette potentielle totale : {{TOTAL_DEBT}} {{CURRENCY}}

RAISON DE L\'ÉCHEC :
{{ERROR_MESSAGE}}

⚖️ VOS OBLIGATIONS CONTRACTUELLES :

En souscrivant à cet abonnement avec engagement, vous avez accepté :
- De régler l\'intégralité des mensualités sur toute la durée
- Que toute interruption de paiement constitue un manquement contractuel
- Que les sommes restant dues demeurent exigibles

⚠️ LE NON-RÈGLEMENT PEUT ENTRAÎNER :
- L\'exigibilité immédiate de la totalité ({{TOTAL_DEBT}} {{CURRENCY}})
- Des pénalités de retard selon les CGV
- La transmission à un cabinet de recouvrement
- Un impayé pouvant impacter votre solvabilité
- Des procédures judiciaires et des frais associés

✅ ACTION IMMÉDIATE REQUISE :

1. Vérifiez votre méthode de paiement
2. Assurez-vous que votre carte est valide et provisionnée
3. Mettez à jour vos informations si nécessaire

⏰ Vous avez 48 heures avant une nouvelle tentative.

RÉGULARISER : {{PAYMENT_METHOD_URL}}
SUPPORT : {{SUPPORT_EMAIL}}

Cet email constitue un rappel formel de vos obligations contractuelles.

{{SITE_NAME}}';

        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_templates (
                    slug, name, subject, html_content, text_content,
                    category, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");

            $stmt->execute([
                'subscription_commitment_payment_failed',
                'Échec paiement abonnement ENGAGEMENT (1ère tentative)',
                'URGENT - Échec paiement abonnement avec ENGAGEMENT - {{SUBSCRIPTION_NUMBER}}',
                $htmlContent,
                $textContent,
                'transactional'
            ]);

            $this->log("Template d'échec ENGAGEMENT (1ère tentative) créé");

        } catch (Exception $e) {
            $this->log("Erreur création template ENGAGEMENT: " . $e->getMessage(), 'WARNING');
        }
    }

        private function ensureCommitmentFinalWarningTemplate() {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM email_templates
                WHERE slug = 'subscription_commitment_final_warning'
            ");
            $stmt->execute();

            if ($stmt->fetchColumn() == 0) {
                $this->createCommitmentFinalWarningTemplate();
            }
        } catch (Exception $e) {
            $this->log("Erreur vérification template MISE EN DEMEURE: " . $e->getMessage(), 'WARNING');
        }
    }

        private function createCommitmentFinalWarningTemplate() {
        $htmlContent = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>MISE EN DEMEURE - Abonnement {{SUBSCRIPTION_NUMBER}} - {{SITE_NAME}}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 20px; background: #f4f4f4; }
        .container { max-width: 700px; margin: 0 auto; background: white; padding: 40px; border-radius: 10px; border: 4px solid #7F1D1D; }
        .header { text-align: center; border-bottom: 4px solid #7F1D1D; padding-bottom: 20px; margin-bottom: 30px; background: #FEE2E2; padding: 20px; border-radius: 8px; }
        .urgent { color: #7F1D1D; font-size: 60px; margin: 20px 0; font-weight: bold; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
        .alert-box { background: #7F1D1D; color: white; padding: 25px; border-radius: 8px; margin: 20px 0; }
        .legal-box { background: #FEF3C7; border: 3px solid #F59E0B; padding: 25px; border-radius: 8px; margin: 20px 0; }
        .debt-box { background: #DBEAFE; border: 3px solid #1E40AF; padding: 25px; border-radius: 8px; margin: 20px 0; text-align: center; }
        .info-row { margin: 15px 0; padding: 10px; background: #F3F4F6; border-radius: 4px; }
        .label { font-weight: bold; color: #374151; }
        .button { display: inline-block; padding: 20px 40px; background: #7F1D1D; color: white; text-decoration: none; border-radius: 8px; margin: 20px 0; font-weight: bold; font-size: 18px; text-transform: uppercase; }
        .warning { color: #DC2626; font-weight: bold; font-size: 18px; }
        .deadline { background: #FEE2E2; border: 2px solid #DC2626; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #7F1D1D; margin: 0;">{{COMPANY_NAME}}</h1>
            <div class="urgent">⚠️</div>
            <h2 style="color: #7F1D1D; margin: 10px 0;">MISE EN DEMEURE</h2>
            <p style="margin: 5px 0; color: #7F1D1D; font-weight: bold;">Abonnement avec ENGAGEMENT - Dernier avertissement</p>
        </div>

        <p style="font-weight: bold; font-size: 16px;">Madame, Monsieur {{USER_LAST_NAME}},</p>

        <div class="alert-box">
            <h3 style="margin-top: 0; font-size: 20px;">🚨 MISE EN DEMEURE DE PAYER</h3>
            <p style="font-size: 15px;"><strong>Après {{ATTEMPTS}} tentatives de prélèvement infructueuses, nous vous mettons formellement en demeure de régulariser votre situation dans les meilleurs délais.</strong></p>
            <p style="font-size: 15px;">Cet email constitue une mise en demeure au sens des articles 1344 et suivants du Code civil.</p>
        </div>

        <div class="debt-box">
            <h3 style="color: #1E40AF; margin-top: 0;">💰 MONTANT CONTRACTUEL DÛ</h3>
            <p style="font-size: 40px; font-weight: bold; margin: 20px 0; color: #1E40AF;">{{TOTAL_DEBT}} {{CURRENCY}}</p>
            <p style="margin: 5px 0;"><small>Montant des mensualités restantes</small></p>
            <p style="margin: 5px 0;"><small>Hors pénalités éventuelles selon CGV</small></p>
        </div>

        <div class="info-row">
            <span class="label">Abonnement concerné :</span> {{SUBSCRIPTION_NUMBER}}
        </div>
        <div class="info-row">
            <span class="label">Produit/Service :</span> {{PRODUCT_NAME}} - {{OFFER_NAME}}
        </div>
        <div class="info-row">
            <span class="label">Engagement contractuel :</span> {{COMMITMENT_DURATION}} mois
        </div>
        <div class="info-row">
            <span class="label">Mensualités restantes :</span> {{REMAINING_MONTHS}} x {{AMOUNT}} {{CURRENCY}}
        </div>
        <div class="info-row">
            <span class="label">Tentatives de prélèvement :</span> <span class="warning">{{ATTEMPTS}} / 3 (ÉCHEC)</span>
        </div>

        <div class="deadline">
            <h3 style="color: #DC2626; margin-top: 0;">⏰ DÉLAI IMPÉRATIF</h3>
            <p style="font-size: 18px; font-weight: bold; margin: 10px 0;">Date limite : <span style="color: #DC2626;">{{DEADLINE_DATE}}</span></p>
            <p>Passé ce délai, votre dossier sera transmis à notre service de recouvrement contentieux.</p>
        </div>

        <div class="legal-box">
            <h3 style="color: #F59E0B; margin-top: 0;">⚖️ CONSÉQUENCES EN L\'ABSENCE DE RÈGLEMENT</h3>
            <p><strong>Conformément à la législation en vigueur et aux CGV acceptées lors de votre souscription, en cas de non-règlement dans les délais impartis :</strong></p>

            <h4 style="color: #DC2626; margin-top: 20px;">1. Exigibilité immédiate</h4>
            <ul>
                <li>La totalité des sommes dues devient immédiatement exigible ({{TOTAL_DEBT}} {{CURRENCY}})</li>
                <li>Application de pénalités de retard conformément aux Conditions Générales de Vente</li>
                <li>Frais de recouvrement conformément aux stipulations contractuelles</li>
            </ul>

            <h4 style="color: #DC2626; margin-top: 20px;">2. Procédure de recouvrement amiable</h4>
            <ul>
                <li>Transmission du dossier à un cabinet de recouvrement</li>
                <li>Un impayé pouvant, à terme, avoir un impact sur votre solvabilité</li>
                <li>Conséquences possibles sur vos futures démarches financières</li>
            </ul>

            <h4 style="color: #DC2626; margin-top: 20px;">3. Procédures judiciaires</h4>
            <ul>
                <li>Engagement possible d\'une procédure d\'injonction de payer</li>
                <li>En cas de décision de justice : saisie sur salaire ou sur compte bancaire possible</li>
                <li>Frais de justice et d\'huissier à votre charge en cas de condamnation</li>
            </ul>

            <p style="background: white; padding: 15px; border-left: 4px solid #DC2626; margin-top: 20px;">
                <strong style="color: #DC2626;">⚠️ IMPORTANT :</strong> Le non-paiement d\'un abonnement avec engagement constitue un manquement contractuel. Vous restez redevable de l\'intégralité des sommes dues sur toute la période d\'engagement, même en cas de résiliation anticipée, conformément aux CGV.
            </p>
        </div>

        <p style="text-align: center; margin: 40px 0;">
            <a href="{{PAYMENT_METHOD_URL}}" class="button">► RÉGULARISER IMMÉDIATEMENT</a>
        </p>

        <div style="background: #F3F4F6; padding: 25px; border-radius: 8px; margin: 30px 0;">
            <h4 style="margin-top: 0;">💬 Difficultés de paiement ?</h4>
            <p>Si vous rencontrez des difficultés financières temporaires, <strong>contactez-nous AVANT le {{DEADLINE_DATE}}</strong> pour étudier ensemble les solutions possibles (échéancier, report, etc.).</p>
            <p>Email : <a href="mailto:{{SUPPORT_EMAIL}}" style="color: #0EA5E9; font-weight: bold;">{{SUPPORT_EMAIL}}</a></p>
            <p style="color: #DC2626; font-weight: bold;">⚠️ Aucune solution ne pourra être proposée après engagement de la procédure de recouvrement.</p>
        </div>

        <div style="margin-top: 40px; padding: 25px; border-top: 3px solid #7F1D1D;">
            <p style="font-weight: bold; color: #7F1D1D;">Fait le {{CURRENT_DATE}}</p>
            <p style="font-size: 12px; color: #6b7280; line-height: 1.8; margin-top: 20px;">
                <strong>Mentions légales :</strong> Le présent courriel constitue une mise en demeure au sens de l\'article 1344 du Code civil, adressé à l\'adresse email fournie par le client lors de son inscription. Il est conservé et horodaté dans nos systèmes et peut être produit en justice. Conformément aux Conditions Générales de Vente acceptées lors de la souscription de l\'abonnement {{SUBSCRIPTION_NUMBER}}, le souscripteur s\'est engagé contractuellement pour une durée de {{COMMITMENT_DURATION}} mois. Le défaut de paiement entraîne l\'application des pénalités de retard et frais de recouvrement tels que prévus dans les CGV acceptées lors de la souscription.
            </p>
            <p style="font-size: 11px; color: #9CA3AF; margin-top: 10px;">
                Expéditeur identifié : {{COMPANY_NAME}} - Message conservable et daté - Envoyé à l\'adresse : {{TO_EMAIL}}
            </p>
        </div>

        <p style="text-align: center; color: #7F1D1D; font-weight: bold; font-size: 14px; margin-top: 30px;">
            {{COMPANY_NAME}} - Service Contentieux - Document officiel
        </p>
    </div>
</body>
</html>';

        $textContent = '═══════════════════════════════════════════
MISE EN DEMEURE DE PAYER
═══════════════════════════════════════════

{{COMPANY_NAME}}
Fait le {{CURRENT_DATE}}

Madame, Monsieur {{USER_LAST_NAME}},

🚨 MISE EN DEMEURE FORMELLE

Après {{ATTEMPTS}} tentatives de prélèvement infructueuses, nous vous mettons en demeure de régulariser votre situation.

Cet email constitue une mise en demeure au sens des articles 1344 et suivants du Code civil.

═══════════════════════════════════════════
MONTANT CONTRACTUEL DÛ : {{TOTAL_DEBT}} {{CURRENCY}}
═══════════════════════════════════════════

Montant des mensualités restantes
Hors pénalités éventuelles selon CGV

DÉTAILS :
- Abonnement : {{SUBSCRIPTION_NUMBER}}
- Produit : {{PRODUCT_NAME}} - {{OFFER_NAME}}
- Engagement : {{COMMITMENT_DURATION}} mois
- Mensualités restantes : {{REMAINING_MONTHS}} x {{AMOUNT}} {{CURRENCY}}
- Tentatives : {{ATTEMPTS}} / 3 (ÉCHEC)

⏰ DATE LIMITE : {{DEADLINE_DATE}}

Passé ce délai, transmission au service contentieux.

⚖️ CONSÉQUENCES EN L\'ABSENCE DE RÈGLEMENT :

1. EXIGIBILITÉ IMMÉDIATE
- Totalité due : {{TOTAL_DEBT}} {{CURRENCY}}
- Pénalités de retard conformément aux CGV
- Frais de recouvrement selon stipulations contractuelles

2. PROCÉDURE DE RECOUVREMENT AMIABLE
- Transmission à cabinet de recouvrement
- Impayé pouvant impacter votre solvabilité
- Conséquences sur futures démarches financières

3. PROCÉDURES JUDICIAIRES
- Engagement possible d\'une injonction de payer
- En cas de décision de justice : saisie possible
- Frais de justice et huissier en cas de condamnation

⚠️ Le non-paiement d\'un abonnement avec engagement constitue un manquement contractuel grave. Vous restez redevable de l\'intégralité sur toute la période d\'engagement.

RÉGULARISER : {{PAYMENT_METHOD_URL}}

💬 DIFFICULTÉS FINANCIÈRES ?
Contactez-nous AVANT le {{DEADLINE_DATE}} :
{{SUPPORT_EMAIL}}

⚠️ Aucune solution après engagement de la procédure.

───────────────────────────────────────────

Le présent courriel constitue une mise en demeure au sens de l\'article 1344 du Code civil, adressé à l\'adresse email fournie par le client. Il est conservé et horodaté et peut être produit en justice. Conformément aux CGV acceptées, le souscripteur s\'est engagé pour {{COMMITMENT_DURATION}} mois. Pénalités et frais selon CGV.

Expéditeur identifié : {{COMPANY_NAME}} - Message conservable et daté

{{COMPANY_NAME}} - Service Contentieux';

        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_templates (
                    slug, name, subject, html_content, text_content,
                    category, is_active, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ");

            $stmt->execute([
                'subscription_commitment_final_warning',
                'MISE EN DEMEURE - Abonnement ENGAGEMENT (3ème tentative)',
                'MISE EN DEMEURE - Abonnement {{SUBSCRIPTION_NUMBER}} - Dernier avertissement',
                $htmlContent,
                $textContent,
                'transactional'
            ]);

            $this->log("Template MISE EN DEMEURE ENGAGEMENT créé");

        } catch (Exception $e) {
            $this->log("Erreur création template MISE EN DEMEURE: " . $e->getMessage(), 'WARNING');
        }
    }

        private function ensureLogDirectory() {
        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            echo "Création du répertoire de logs: $logDir" . PHP_EOL;
            if (!mkdir($logDir, 0755, true)) {
                echo "ATTENTION: Impossible de créer le répertoire de logs" . PHP_EOL;
            }
        }
    }
    
        private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
        
                @file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
                if ($level === 'ERROR') {
            error_log("SUBSCRIPTION PAYMENT ERROR: $message");
        }
    }
}

try {
    echo "=== TRAITEMENT DES PAIEMENTS D'ABONNEMENTS ===" . PHP_EOL;
    echo "Début: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;
    
    $processor = new SubscriptionPaymentProcessor($mailEnabled);
    $processor->processSubscriptions();
    
    echo PHP_EOL . "=== TRAITEMENT TERMINÉ ===" . PHP_EOL;
    echo "Fin: " . date('Y-m-d H:i:s') . PHP_EOL;
    
    exit(0);
    
} catch (Exception $e) {
    echo PHP_EOL . "ERREUR FATALE: " . $e->getMessage() . PHP_EOL;
    echo "Trace: " . $e->getTraceAsString() . PHP_EOL;
    error_log("SUBSCRIPTION PAYMENT FATAL ERROR: " . $e->getMessage());
    exit(1);
}
?>