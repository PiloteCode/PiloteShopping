<?php

class TrackingManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
        public function trackOpen($trackingToken) {
        try {
                        $stmt = $this->db->prepare("
                SELECT id, to_email, user_id 
                FROM email_sends 
                WHERE tracking_token = ? AND tracking_enabled = 1
            ");
            $stmt->execute([$trackingToken]);
            $email = $stmt->fetch();
            
            if (!$email) {
                return false;
            }
            
                        $clientInfo = $this->getClientInfo();
            
                        $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM email_opens 
                WHERE email_id = ? AND ip_address = ?
            ");
            $stmt->execute([$email['id'], $clientInfo['ip']]);
            $isFirstOpen = $stmt->fetchColumn() == 0;
            
                        $stmt = $this->db->prepare("
                INSERT INTO email_opens (
                    email_id, ip_address, user_agent, country, city,
                    device_type, browser, os, first_open
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $email['id'],
                $clientInfo['ip'],
                $clientInfo['user_agent'],
                $clientInfo['country'],
                $clientInfo['city'],
                $clientInfo['device_type'],
                $clientInfo['browser'],
                $clientInfo['os'],
                $isFirstOpen ? 1 : 0
            ]);
            
                        logSecurityEvent(
                $email['user_id'],
                'email_opened',
                [
                    'email_id' => $email['id'],
                    'to_email' => $email['to_email'],
                    'first_open' => $isFirstOpen,
                    'device' => $clientInfo['device_type'],
                    'browser' => $clientInfo['browser'],
                    'country' => $clientInfo['country']
                ],
                'success'
            );
            
            return true;
            
        } catch (Exception $e) {
            error_log("Email tracking error: " . $e->getMessage());
            return false;
        }
    }
    
        public function trackClick($linkToken) {
        try {
                        $stmt = $this->db->prepare("
                SELECT ec.*, es.to_email, es.user_id 
                FROM email_clicks ec
                JOIN email_sends es ON ec.email_id = es.id
                WHERE ec.tracking_token = ?
            ");
            $stmt->execute([$linkToken]);
            $click = $stmt->fetch();
            
            if (!$click) {
                return ['success' => false, 'error' => 'Invalid tracking token'];
            }
            
                        $clientInfo = $this->getClientInfo();
            
                        $isFirstClick = empty($click['ip_address']);
            
                        $stmt = $this->db->prepare("
                UPDATE email_clicks 
                SET ip_address = ?, user_agent = ?, country = ?, city = ?,
                    device_type = ?, browser = ?, os = ?, first_click = ?,
                    clicked_at = NOW()
                WHERE tracking_token = ?
            ");
            
            $stmt->execute([
                $clientInfo['ip'],
                $clientInfo['user_agent'],
                $clientInfo['country'],
                $clientInfo['city'],
                $clientInfo['device_type'],
                $clientInfo['browser'],
                $clientInfo['os'],
                $isFirstClick ? 1 : 0,
                $linkToken
            ]);
            
                        if (!$isFirstClick) {
                $stmt = $this->db->prepare("
                    INSERT INTO email_clicks (
                        email_id, original_url, tracking_token, ip_address, user_agent,
                        country, city, device_type, browser, os, first_click
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                
                $newToken = bin2hex(random_bytes(16));                 
                $stmt->execute([
                    $click['email_id'],
                    $click['original_url'],
                    $newToken,
                    $clientInfo['ip'],
                    $clientInfo['user_agent'],
                    $clientInfo['country'],
                    $clientInfo['city'],
                    $clientInfo['device_type'],
                    $clientInfo['browser'],
                    $clientInfo['os']
                ]);
            }
            
                        logSecurityEvent(
                $click['user_id'],
                'email_clicked',
                [
                    'email_id' => $click['email_id'],
                    'to_email' => $click['to_email'],
                    'url' => $click['original_url'],
                    'first_click' => $isFirstClick,
                    'device' => $clientInfo['device_type'],
                    'browser' => $clientInfo['browser'],
                    'country' => $clientInfo['country']
                ],
                'success'
            );
            
            return [
                'success' => true,
                'redirect_url' => $click['original_url']
            ];
            
        } catch (Exception $e) {
            error_log("Email click tracking error: " . $e->getMessage());
            return ['success' => false, 'error' => 'Tracking error'];
        }
    }
    
        private function getClientInfo() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ip = getRealIP();
        
                $deviceInfo = $this->parseUserAgent($userAgent);
        
                $geoInfo = $this->getLocationFromIP($ip);
        
        return [
            'ip' => $ip,
            'user_agent' => $userAgent,
            'device_type' => $deviceInfo['device'],
            'browser' => $deviceInfo['browser'],
            'os' => $deviceInfo['os'],
            'country' => $geoInfo['country'] ?? null,
            'city' => $geoInfo['city'] ?? null
        ];
    }
    
        private function parseUserAgent($userAgent) {
        $device = 'desktop';
        $browser = 'Unknown';
        $os = 'Unknown';
        
                if (preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
            if (preg_match('/iPad/i', $userAgent)) {
                $device = 'tablet';
            } else {
                $device = 'mobile';
            }
        } elseif (preg_match('/Tablet/i', $userAgent)) {
            $device = 'tablet';
        }
        
                if (preg_match('/Chrome/i', $userAgent)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox/i', $userAgent)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari/i', $userAgent)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $userAgent)) {
            $browser = 'Edge';
        } elseif (preg_match('/Opera/i', $userAgent)) {
            $browser = 'Opera';
        }
        
                if (preg_match('/Windows NT/i', $userAgent)) {
            $os = 'Windows';
        } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
            $os = 'iOS';
        }
        
        return [
            'device' => $device,
            'browser' => $browser,
            'os' => $os
        ];
    }
    
        private function getLocationFromIP($ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['country' => null, 'city' => null];
        }
        
        try {
                        $context = stream_context_create([
                'http' => [
                    'timeout' => 3,
                    'user_agent' => 'PiloteShopping/1.0'
                ]
            ]);
            
            $response = @file_get_contents("http://ipapi.co/{$ip}/json/", false, $context);
            
            if ($response) {
                $data = json_decode($response, true);
                if (isset($data['country_code']) && $data['country_code'] !== 'XX') {
                    return [
                        'country' => $data['country_code'],
                        'city' => $data['city'] ?? null
                    ];
                }
            }
        } catch (Exception $e) {
                    }
        
        return ['country' => null, 'city' => null];
    }
    
        public function getDetailedStats($emailId) {
        $stats = [];
        
                $stmt = $this->db->prepare("
            SELECT 
                (SELECT COUNT(*) FROM email_opens WHERE email_id = ?) as total_opens,
                (SELECT COUNT(DISTINCT ip_address) FROM email_opens WHERE email_id = ?) as unique_opens,
                (SELECT COUNT(*) FROM email_clicks WHERE email_id = ?) as total_clicks,
                (SELECT COUNT(DISTINCT ip_address) FROM email_clicks WHERE email_id = ?) as unique_clicks,
                (SELECT MIN(opened_at) FROM email_opens WHERE email_id = ?) as first_opened_at,
                (SELECT MAX(opened_at) FROM email_opens WHERE email_id = ?) as last_opened_at
        ");
        $stmt->execute([$emailId, $emailId, $emailId, $emailId, $emailId, $emailId]);
        $stats['general'] = $stmt->fetch();
        
                $stmt = $this->db->prepare("
            SELECT country, COUNT(*) as opens
            FROM email_opens 
            WHERE email_id = ? AND country IS NOT NULL
            GROUP BY country 
            ORDER BY opens DESC
            LIMIT 10
        ");
        $stmt->execute([$emailId]);
        $stats['countries'] = $stmt->fetchAll();
        
                $stmt = $this->db->prepare("
            SELECT device_type, COUNT(*) as opens
            FROM email_opens 
            WHERE email_id = ?
            GROUP BY device_type 
            ORDER BY opens DESC
        ");
        $stmt->execute([$emailId]);
        $stats['devices'] = $stmt->fetchAll();
        
                $stmt = $this->db->prepare("
            SELECT original_url, COUNT(*) as clicks
            FROM email_clicks 
            WHERE email_id = ?
            GROUP BY original_url 
            ORDER BY clicks DESC
            LIMIT 10
        ");
        $stmt->execute([$emailId]);
        $stats['top_links'] = $stmt->fetchAll();
        
                $stmt = $this->db->prepare("
            SELECT 
                HOUR(opened_at) as hour,
                COUNT(*) as opens
            FROM email_opens 
            WHERE email_id = ? AND opened_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            GROUP BY HOUR(opened_at)
            ORDER BY hour
        ");
        $stmt->execute([$emailId]);
        $stats['hourly_opens'] = $stmt->fetchAll();
        
        return $stats;
    }
    
        public function getGlobalStats($dateFrom = null, $dateTo = null) {
        $whereOuter = "WHERE es.sent_at IS NOT NULL";
        $whereInner = "WHERE es2.sent_at IS NOT NULL";
        $params = [];
        if ($dateFrom) {
            $whereOuter .= " AND es.sent_at >= ?";
            $whereInner .= " AND es2.sent_at >= ?";
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $whereOuter .= " AND es.sent_at <= ?";
            $whereInner .= " AND es2.sent_at <= ?";
            $params[] = $dateTo;
        }

        $innerParams = [];
        if ($dateFrom) { $innerParams[] = $dateFrom; }
        if ($dateTo)   { $innerParams[] = $dateTo; }

        $allParams = array_merge($params, $innerParams, $innerParams, $innerParams, $innerParams);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(es.id) as total_emails,
                COUNT(CASE WHEN es.status = 'sent' THEN 1 END) as sent_emails,
                COUNT(CASE WHEN es.status = 'failed' THEN 1 END) as failed_emails,
                COUNT(CASE WHEN es.status = 'bounced' THEN 1 END) as bounced_emails,

                (SELECT COUNT(*) FROM email_opens eo
                 JOIN email_sends es2 ON eo.email_id = es2.id
                 {$whereInner}
                ) as total_opens,

                (SELECT COUNT(DISTINCT eo.email_id) FROM email_opens eo
                 JOIN email_sends es2 ON eo.email_id = es2.id
                 {$whereInner}
                ) as emails_opened,

                (SELECT COUNT(*) FROM email_clicks ec
                 JOIN email_sends es2 ON ec.email_id = es2.id
                 {$whereInner}
                ) as total_clicks,

                (SELECT COUNT(DISTINCT ec.email_id) FROM email_clicks ec
                 JOIN email_sends es2 ON ec.email_id = es2.id
                 {$whereInner}
                ) as emails_clicked

            FROM email_sends es
            {$whereOuter}
        ");

        $stmt->execute($allParams);
        $stats = $stmt->fetch();
        
                if ($stats['sent_emails'] > 0) {
            $stats['delivery_rate'] = ($stats['sent_emails'] / $stats['total_emails']) * 100;
            $stats['open_rate'] = ($stats['emails_opened'] / $stats['sent_emails']) * 100;
            $stats['click_rate'] = ($stats['emails_clicked'] / $stats['sent_emails']) * 100;
            $stats['bounce_rate'] = ($stats['bounced_emails'] / $stats['total_emails']) * 100;
        } else {
            $stats['delivery_rate'] = 0;
            $stats['open_rate'] = 0;
            $stats['click_rate'] = 0;
            $stats['bounce_rate'] = 0;
        }
        
        return $stats;
    }
    
        public function cleanupOldTrackingData($daysToKeep = 365) {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
            
                        $stmt = $this->db->prepare("
                DELETE FROM email_opens 
                WHERE opened_at < ?
            ");
            $stmt->execute([$cutoffDate]);
            $deletedOpens = $stmt->rowCount();
            
                        $stmt = $this->db->prepare("
                DELETE FROM email_clicks 
                WHERE clicked_at < ?
            ");
            $stmt->execute([$cutoffDate]);
            $deletedClicks = $stmt->rowCount();
            
            error_log("Cleaned up tracking data: {$deletedOpens} opens, {$deletedClicks} clicks older than {$daysToKeep} days");
            
            return [
                'success' => true,
                'deleted_opens' => $deletedOpens,
                'deleted_clicks' => $deletedClicks
            ];
            
        } catch (Exception $e) {
            error_log("Cleanup tracking data error: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
?>