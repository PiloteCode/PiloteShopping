<?php

class NotificationManager {
    private $db;

        public function __construct() {
        $this->db = Database::getInstance();
    }

        public function create($userId, $type, $title, $message, $options = []) {
        try {
                        if (!$this->canReceiveNotification($userId, $type)) {
                return false;
            }

                        $defaults = [
                'link' => null,
                'icon' => $this->getDefaultIcon($type),
                'color' => $this->getDefaultColor($type),
                'priority' => 'normal',
                'data' => null,
                'expires_at' => null
            ];

            $options = array_merge($defaults, $options);

                        $data = is_array($options['data']) ? json_encode($options['data']) : $options['data'];

                        $stmt = $this->db->prepare("
                INSERT INTO notifications (
                    user_id, type, title, message, link, icon, color,
                    priority, data, expires_at, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $userId,
                $type,
                $title,
                $message,
                $options['link'],
                $options['icon'],
                $options['color'],
                $options['priority'],
                $data,
                $options['expires_at']
            ]);

            $notificationId = $this->db->lastInsertId();

                        error_log("Notification created: ID=$notificationId, User=$userId, Type=$type");

            return $notificationId;

        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }

        public function notifyOrderCreated($userId, $orderNumber, $amount, $orderId) {
        return $this->create(
            $userId,
            'order',
            'Nouvelle commande',
            "Votre commande #{$orderNumber} a été créée avec succès pour un montant de " . number_format($amount, 2) . "€",
            [
                'link' => "/orders/detail?order={$orderId}",
                'icon' => 'shopping-bag',
                'color' => 'blue',
                'priority' => 'normal',
                'data' => [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'amount' => $amount
                ]
            ]
        );
    }

        public function notifyOrderConfirmed($userId, $orderNumber, $orderId) {
        return $this->create(
            $userId,
            'order',
            'Commande confirmée',
            "Votre commande #{$orderNumber} a été confirmée et est en cours de traitement",
            [
                'link' => "/orders/detail?order={$orderId}",
                'icon' => 'check-circle',
                'color' => 'green',
                'priority' => 'normal',
                'data' => ['order_id' => $orderId, 'order_number' => $orderNumber]
            ]
        );
    }

        public function notifyOrderDelivered($userId, $orderNumber, $orderId) {
        return $this->create(
            $userId,
            'delivery',
            'Commande livrée',
            "Votre commande #{$orderNumber} a été livrée avec succès !",
            [
                'link' => "/orders/detail?order={$orderId}",
                'icon' => 'truck',
                'color' => 'green',
                'priority' => 'high',
                'data' => ['order_id' => $orderId, 'order_number' => $orderNumber]
            ]
        );
    }

        public function notifyOrderCancelled($userId, $orderNumber, $orderId, $reason = null) {
        $message = "Votre commande #{$orderNumber} a été annulée";
        if ($reason) {
            $message .= ". Raison: {$reason}";
        }

        return $this->create(
            $userId,
            'order',
            'Commande annulée',
            $message,
            [
                'link' => "/orders/detail?order={$orderId}",
                'icon' => 'times-circle',
                'color' => 'red',
                'priority' => 'high',
                'data' => [
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'reason' => $reason
                ]
            ]
        );
    }

        public function notifyPaymentSuccess($userId, $amount, $orderNumber = null, $orderId = null) {
        $message = "Paiement de " . number_format($amount, 2) . "€ effectué avec succès";
        if ($orderNumber) {
            $message .= " pour la commande #{$orderNumber}";
        }

        return $this->create(
            $userId,
            'payment',
            'Paiement réussi',
            $message,
            [
                'link' => $orderId ? "/orders/detail?order={$orderId}" : null,
                'icon' => 'check-circle',
                'color' => 'green',
                'priority' => 'normal',
                'data' => [
                    'amount' => $amount,
                    'order_id' => $orderId,
                    'order_number' => $orderNumber
                ]
            ]
        );
    }

        public function notifyPaymentFailed($userId, $amount, $reason = null, $orderNumber = null, $orderId = null) {
        $message = "Le paiement de " . number_format($amount, 2) . "€ a échoué";
        if ($reason) {
            $message .= ". {$reason}";
        }

        return $this->create(
            $userId,
            'payment',
            'Échec du paiement',
            $message,
            [
                'link' => $orderId ? "/orders/detail?order={$orderId}" : '/dashboard/payment-methods',
                'icon' => 'exclamation-circle',
                'color' => 'red',
                'priority' => 'high',
                'data' => [
                    'amount' => $amount,
                    'order_id' => $orderId,
                    'order_number' => $orderNumber,
                    'reason' => $reason
                ]
            ]
        );
    }

        public function notifySubscriptionActivated($userId, $subscriptionNumber, $productName, $subscriptionId) {
        return $this->create(
            $userId,
            'subscription',
            'Abonnement activé',
            "Votre abonnement '{$productName}' a été activé avec succès",
            [
                'link' => '/dashboard/subscriptions',
                'icon' => 'sync-alt',
                'color' => 'purple',
                'priority' => 'normal',
                'data' => [
                    'subscription_id' => $subscriptionId,
                    'subscription_number' => $subscriptionNumber,
                    'product_name' => $productName
                ]
            ]
        );
    }

        public function notifySubscriptionSuspended($userId, $subscriptionNumber, $productName, $reason = null) {
        $message = "Votre abonnement '{$productName}' a été suspendu";
        if ($reason) {
            $message .= ". {$reason}";
        }

        return $this->create(
            $userId,
            'subscription',
            'Abonnement suspendu',
            $message,
            [
                'link' => '/dashboard/subscriptions',
                'icon' => 'pause-circle',
                'color' => 'orange',
                'priority' => 'high',
                'data' => [
                    'subscription_number' => $subscriptionNumber,
                    'product_name' => $productName,
                    'reason' => $reason
                ]
            ]
        );
    }

        public function notifyWalletCredit($userId, $amount, $description = null) {
        $message = "Votre solde a été crédité de " . number_format($amount, 2) . "€";
        if ($description) {
            $message .= ". {$description}";
        }

        return $this->create(
            $userId,
            'wallet',
            'Solde crédité',
            $message,
            [
                'link' => '/wallet',
                'icon' => 'plus-circle',
                'color' => 'green',
                'priority' => 'normal',
                'data' => ['amount' => $amount, 'description' => $description]
            ]
        );
    }

        public function notifyWalletDebit($userId, $amount, $description = null) {
        $message = "Votre solde a été débité de " . number_format($amount, 2) . "€";
        if ($description) {
            $message .= ". {$description}";
        }

        return $this->create(
            $userId,
            'wallet',
            'Solde débité',
            $message,
            [
                'link' => '/wallet',
                'icon' => 'minus-circle',
                'color' => 'red',
                'priority' => 'normal',
                'data' => ['amount' => $amount, 'description' => $description]
            ]
        );
    }

        public function notifySecurityAlert($userId, $title, $message, $priority = 'high') {
        return $this->create(
            $userId,
            'security',
            $title,
            $message,
            [
                'icon' => 'shield-alt',
                'color' => 'red',
                'priority' => $priority
            ]
        );
    }

        public function notifySystem($userId, $title, $message, $options = []) {
        return $this->create(
            $userId,
            'system',
            $title,
            $message,
            array_merge([
                'icon' => 'info-circle',
                'color' => 'blue',
                'priority' => 'normal'
            ], $options)
        );
    }

        public function markAsRead($notificationId, $userId = null) {
        try {
            $sql = "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?";
            $params = [$notificationId];

                        if ($userId !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;

        } catch (Exception $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }

        public function markAllAsRead($userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE notifications
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);

            return $stmt->rowCount();

        } catch (Exception $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }

        public function delete($notificationId, $userId = null) {
        try {
            $sql = "DELETE FROM notifications WHERE id = ?";
            $params = [$notificationId];

            if ($userId !== null) {
                $sql .= " AND user_id = ?";
                $params[] = $userId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount() > 0;

        } catch (Exception $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            return false;
        }
    }

        public function getUserNotifications($userId, $limit = 50, $offset = 0, $unreadOnly = false) {
        try {
            $sql = "
                SELECT id, type, title, message, link, icon, color, priority,
                       is_read, data, created_at, read_at
                FROM notifications
                WHERE user_id = ?
                AND (expires_at IS NULL OR expires_at > NOW())
            ";

            $params = [$userId];

            if ($unreadOnly) {
                $sql .= " AND is_read = 0";
            }

            $sql .= " ORDER BY is_read ASC, created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll();

                        foreach ($notifications as &$notif) {
                if ($notif['data']) {
                    $notif['data'] = json_decode($notif['data'], true);
                }
            }

            return $notifications;

        } catch (Exception $e) {
            error_log("Error fetching notifications: " . $e->getMessage());
            return [];
        }
    }

        public function getUnreadCount($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM notifications
                WHERE user_id = ?
                AND is_read = 0
                AND (expires_at IS NULL OR expires_at > NOW())
            ");
            $stmt->execute([$userId]);

            return (int)$stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("Error counting unread notifications: " . $e->getMessage());
            return 0;
        }
    }

        private function canReceiveNotification($userId, $type) {
        try {
                        $stmt = $this->db->prepare("
                SELECT enabled
                FROM notification_preferences
                WHERE user_id = ? AND type = ?
            ");
            $stmt->execute([$userId, $type]);
            $pref = $stmt->fetchColumn();

                        if ($pref === false) {
                return true;
            }

            return (bool)$pref;

        } catch (Exception $e) {
                        return true;
        }
    }

        private function getDefaultIcon($type) {
        $icons = [
            'order' => 'shopping-bag',
            'payment' => 'credit-card',
            'subscription' => 'sync-alt',
            'delivery' => 'truck',
            'security' => 'shield-alt',
            'system' => 'info-circle',
            'promo' => 'tag',
            'wallet' => 'wallet'
        ];

        return $icons[$type] ?? 'bell';
    }

        private function getDefaultColor($type) {
        $colors = [
            'order' => 'blue',
            'payment' => 'green',
            'subscription' => 'purple',
            'delivery' => 'orange',
            'security' => 'red',
            'system' => 'blue',
            'promo' => 'yellow',
            'wallet' => 'green'
        ];

        return $colors[$type] ?? 'blue';
    }
}

function createNotification($userId, $type, $title, $message, $options = []) {
    $manager = new NotificationManager();
    return $manager->create($userId, $type, $title, $message, $options);
}
