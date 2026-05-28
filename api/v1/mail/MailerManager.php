<?php

require_once __DIR__ . '/../../../plugins/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../../../plugins/PHPMailer/SMTP.php';
require_once __DIR__ . '/../../../plugins/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailerManager {
    private $db;
    private $config;
    private $dkimPrivateKey;
    private $trackingEnabled = true;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->config = Config::getInstance();
        $this->loadDKIMKey();
    }
    
        public function sendTemplate($templateSlug, $toEmail, $toName, $variables = [], $options = []) {
        try {
                        $template = $this->getTemplate($templateSlug);
            if (!$template) {
                throw new Exception("Template '$templateSlug' non trouvé");
            }
            
                        $subject = $this->replaceVariables($template['subject'], $variables);
            $htmlContent = $this->replaceVariables($template['html_content'], $variables);
            $textContent = $template['text_content'] ? $this->replaceVariables($template['text_content'], $variables) : null;
            
                        $emailData = [
                'template_id' => $template['id'],
                'to_email' => $toEmail,
                'to_name' => $toName,
                'subject' => $subject,
                'html_content' => $htmlContent,
                'text_content' => $textContent,
                'variables' => $variables,
                'category' => $template['category'],
                'from_email' => $options['from_email'] ?? $this->config->get('smtp.username'),
                'from_name' => $options['from_name'] ?? SITE_NAME,
                'reply_to' => $options['reply_to'] ?? null,
                'priority' => $options['priority'] ?? 3,
                'tracking_enabled' => $options['tracking'] ?? $this->trackingEnabled
            ];
            
            return $this->sendEmail($emailData);
            
        } catch (Exception $e) {
            error_log("Template send error: " . $e->getMessage());
            return false;
        }
    }
    
        public function sendCustom($toEmail, $toName, $subject, $htmlContent, $textContent = null, $options = []) {
        $emailData = [
            'to_email' => $toEmail,
            'to_name' => $toName,
            'subject' => $subject,
            'html_content' => $htmlContent,
            'text_content' => $textContent,
            'variables' => [],
            'category' => $options['category'] ?? 'transactional',
            'from_email' => $options['from_email'] ?? $this->config->get('smtp.username'),
            'from_name' => $options['from_name'] ?? SITE_NAME,
            'reply_to' => $options['reply_to'] ?? null,
            'priority' => $options['priority'] ?? 3,
            'tracking_enabled' => $options['tracking'] ?? $this->trackingEnabled
        ];
        
        return $this->sendEmail($emailData);
    }
    
        private function sendEmail($emailData) {
        try {
                        if ($this->isEmailSuppressed($emailData['to_email'])) {
                throw new Exception("Email address is suppressed (bounced/opted-out)");
            }
            
                        $messageId = $this->generateMessageId();
            $trackingToken = $this->generateTrackingToken();
            
                        $emailId = $this->saveEmailToDatabase($emailData, $messageId, $trackingToken);
            
                        if ($emailData['tracking_enabled']) {
                $emailData['html_content'] = $this->addTracking($emailData['html_content'], $trackingToken, $emailId);
            }
            
                        $mail = $this->configurePHPMailer();
            
                        $mail->addAddress($emailData['to_email'], $emailData['to_name']);
            
                        $mail->setFrom($emailData['from_email'], $emailData['from_name']);
            
                        if (!empty($emailData['reply_to'])) {
                $mail->addReplyTo($emailData['reply_to']);
            }
            
                        $mail->MessageID = "<{$messageId}@" . parse_url(SITE_URL, PHP_URL_HOST) . ">";
            
                        $mail->Subject = $emailData['subject'];
            $mail->isHTML(true);
            $mail->Body = $emailData['html_content'];
            
            if (!empty($emailData['text_content'])) {
                $mail->AltBody = $emailData['text_content'];
            }
            
                        $this->addCustomHeaders($mail, $emailData);
            
                        $mail->Priority = $emailData['priority'];
            
                        if ($this->dkimPrivateKey) {
                $mail->DKIM_domain = parse_url(SITE_URL, PHP_URL_HOST);
                $mail->DKIM_private = $this->dkimPrivateKey;
                $mail->DKIM_selector = 'default';
                $mail->DKIM_passphrase = '';
                $mail->DKIM_identity = $mail->From;
            }
            
                        $success = $mail->send();
            
            if ($success) {
                                $this->updateEmailStatus($emailId, 'sent', null, $mail->getLastMessageID());
                
                                logSecurityEvent(
                    $this->getUserIdFromEmail($emailData['to_email']),
                    'email_sent',
                    [
                        'template_id' => $emailData['template_id'] ?? null,
                        'to_email' => $emailData['to_email'],
                        'subject' => $emailData['subject'],
                        'category' => $emailData['category'],
                        'message_id' => $messageId
                    ],
                    'success'
                );
                
                return [
                    'success' => true,
                    'email_id' => $emailId,
                    'message_id' => $messageId
                ];
            } else {
                throw new Exception("PHPMailer error: " . $mail->ErrorInfo);
            }
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            
                        if (isset($emailId)) {
                $this->updateEmailStatus($emailId, 'failed', $errorMessage);
            }
            
                        $this->handleSendError($emailData['to_email'], $errorMessage, $emailId ?? null);
            
                        logSecurityEvent(
                $this->getUserIdFromEmail($emailData['to_email']),
                'email_failed',
                [
                    'to_email' => $emailData['to_email'],
                    'error' => $errorMessage,
                    'category' => $emailData['category'] ?? 'unknown'
                ],
                'failed'
            );
            
            return [
                'success' => false,
                'error' => $errorMessage
            ];
        }
    }
    
        private function configurePHPMailer() {
        $mail = new PHPMailer(true);
        
                $mail->isSMTP();
        $mail->Host = $this->config->get('smtp.host');
        $mail->SMTPAuth = true;
        $mail->Username = $this->config->get('smtp.username');
        $mail->Password = $this->config->get('smtp.password');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;         $mail->Port = $this->config->get('smtp.port');
        
                $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];
        
                $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        
                if (ENVIRONMENT !== 'development') {
            $mail->SMTPDebug = SMTP::DEBUG_OFF;
        } else {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        
        return $mail;
    }
    
        private function addCustomHeaders($mail, $emailData) {
                $unsubscribeUrl = SITE_URL . '/unsubscribe?email=' . urlencode($emailData['to_email']);
        $mail->addCustomHeader('List-Unsubscribe', "<{$unsubscribeUrl}>");
        $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
        
                $mail->addCustomHeader('X-Auto-Response-Suppress', 'All');
        $mail->addCustomHeader('X-Mailer', SITE_NAME . ' Mailer v1.0');
        $mail->addCustomHeader('X-Original-To', $emailData['to_email']);
        
                if (isset($emailData['category'])) {
            $mail->addCustomHeader('X-Email-Category', $emailData['category']);
        }
        
                if ($emailData['category'] === 'transactional') {
            $mail->addCustomHeader('Precedence', 'bulk');
        }
        
                $mail->Sender = 'bounce@' . parse_url(SITE_URL, PHP_URL_HOST);
    }
    
        private function addTracking($htmlContent, $trackingToken, $emailId) {
                $trackingPixel = '<img src="' . SITE_URL . '/api/v1/mail/track/open/' . $trackingToken . '" width="1" height="1" style="display:none !important; width:1px !important; height:1px !important; border:none !important; margin:0 !important; padding:0 !important;" alt="">';
        
                if (stripos($htmlContent, '</body>') !== false) {
            $htmlContent = str_ireplace('</body>', $trackingPixel . '</body>', $htmlContent);
        } else {
                        $htmlContent .= $trackingPixel;
        }
        
                $htmlContent = $this->trackLinks($htmlContent, $trackingToken, $emailId);
        
        return $htmlContent;
    }
    
        private function trackLinks($htmlContent, $trackingToken, $emailId) {
        return preg_replace_callback(
            '/<a[^>]+href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/i',
            function($matches) use ($trackingToken, $emailId) {
                $originalUrl = $matches[1];
                $linkText = $matches[2];
                $fullMatch = $matches[0];
                
                                if (strpos($originalUrl, 'unsubscribe') !== false || 
                    strpos($originalUrl, '/track/') !== false ||
                    strpos($originalUrl, 'mailto:') === 0) {
                    return $fullMatch;
                }
                
                                $linkToken = $this->generateLinkToken($emailId, $originalUrl);
                
                                $trackedUrl = SITE_URL . '/api/v1/mail/track/click/' . $linkToken;
                
                                return str_replace($originalUrl, $trackedUrl, $fullMatch);
            },
            $htmlContent
        );
    }
    
        private function generateLinkToken($emailId, $originalUrl) {
        $token = bin2hex(random_bytes(32));
        
                $stmt = $this->db->prepare("
            INSERT INTO email_clicks (email_id, original_url, tracking_token, ip_address, user_agent, first_click)
            VALUES (?, ?, ?, '', '', 0)
        ");
        $stmt->execute([$emailId, $originalUrl, $token]);
        
        return $token;
    }
    
        private function replaceVariables($content, $variables) {
                $systemVars = [
            'SITE_NAME' => SITE_NAME,
            'SITE_URL' => SITE_URL,
            'SITE_EMAIL' => SITE_EMAIL,
            'CURRENT_YEAR' => date('Y'),
            'CURRENT_DATE' => date('d/m/Y'),
            'UNSUBSCRIBE_URL' => SITE_URL . '/unsubscribe'
        ];
        
        $allVars = array_merge($systemVars, $variables);
        
        foreach ($allVars as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        
        return $content;
    }
    
        private function getTemplate($slug) {
        $stmt = $this->db->prepare("
            SELECT * FROM email_templates 
            WHERE slug = ? AND is_active = 1
        ");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }
    
        private function saveEmailToDatabase($emailData, $messageId, $trackingToken) {
        $stmt = $this->db->prepare("
            INSERT INTO email_sends (
                message_id, template_id, user_id, to_email, to_name,
                from_email, from_name, reply_to, subject, html_content, text_content,
                variables, priority, category, tracking_enabled, tracking_token,
                ip_address, user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $userId = $this->getUserIdFromEmail($emailData['to_email']);
        
        $stmt->execute([
            $messageId,
            $emailData['template_id'] ?? null,
            $userId,
            $emailData['to_email'],
            $emailData['to_name'],
            $emailData['from_email'],
            $emailData['from_name'],
            $emailData['reply_to'],
            $emailData['subject'],
            $emailData['html_content'],
            $emailData['text_content'],
            json_encode($emailData['variables']),
            $emailData['priority'],
            $emailData['category'],
            $emailData['tracking_enabled'] ? 1 : 0,
            $trackingToken,
            getRealIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        return $this->db->lastInsertId();
    }
    
        private function updateEmailStatus($emailId, $status, $error = null, $smtpResponse = null) {
        $stmt = $this->db->prepare("
            UPDATE email_sends 
            SET status = ?, error_message = ?, smtp_response = ?, sent_at = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $sentAt = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
        $stmt->execute([$status, $error, $smtpResponse, $sentAt, $emailId]);
    }
    
        private function handleSendError($email, $errorMessage, $emailId) {
        $bounceType = 'soft';         
                if (preg_match('/550|551|553|554/', $errorMessage)) {
            $bounceType = 'hard';
        } elseif (preg_match('/552|421|450|451|452/', $errorMessage)) {
            $bounceType = 'soft';
        } elseif (preg_match('/spam|blacklist|blocked/', strtolower($errorMessage))) {
            $bounceType = 'block';
        }
        
                if ($emailId) {
            $stmt = $this->db->prepare("
                INSERT INTO email_bounces (email_id, bounce_type, bounce_reason, raw_message)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$emailId, $bounceType, $errorMessage, $errorMessage]);
        }
        
                if ($bounceType === 'hard') {
            $this->addToSuppressionList($email, 'bounce', $errorMessage, $emailId);
        }
    }
    
        private function isEmailSuppressed($email) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM email_suppressions 
            WHERE email = ? AND type IN ('bounce', 'complaint')
        ");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }
    
        private function addToSuppressionList($email, $type, $reason, $sourceEmailId = null) {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO email_suppressions (email, type, reason, source_email_id, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $email,
            $type,
            $reason,
            $sourceEmailId,
            getRealIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    }
    
        private function loadDKIMKey() {
        $keyPath = dirname(__FILE__) . '/dkim/private.key';
        if (file_exists($keyPath)) {
            $this->dkimPrivateKey = file_get_contents($keyPath);
        } else {
            error_log("DKIM private key not found at: $keyPath");
        }
    }
    
        private function getUserIdFromEmail($email) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() ?: null;
    }
    
        private function generateMessageId() {
        return bin2hex(random_bytes(16)) . '.' . time();
    }
    
        private function generateTrackingToken() {
        return bin2hex(random_bytes(32));
    }
    
        public function getEmailStats($emailId) {
        $stats = ['opens' => 0, 'clicks' => 0, 'unique_opens' => 0, 'unique_clicks' => 0];
        
                $stmt = $this->db->prepare("
            SELECT COUNT(*) as total, COUNT(CASE WHEN first_open = 1 THEN 1 END) as unique_count
            FROM email_opens WHERE email_id = ?
        ");
        $stmt->execute([$emailId]);
        $opens = $stmt->fetch();
        $stats['opens'] = $opens['total'];
        $stats['unique_opens'] = $opens['unique_count'];
        
                $stmt = $this->db->prepare("
            SELECT COUNT(*) as total, COUNT(CASE WHEN first_click = 1 THEN 1 END) as unique_count
            FROM email_clicks WHERE email_id = ?
        ");
        $stmt->execute([$emailId]);
        $clicks = $stmt->fetch();
        $stats['clicks'] = $clicks['total'];
        $stats['unique_clicks'] = $clicks['unique_count'];
        
        return $stats;
    }
    
        public function getEmailList($filters = [], $limit = 50, $offset = 0) {
        $whereClause = "WHERE 1=1";
        $params = [];
        
        if (!empty($filters['status'])) {
            $whereClause .= " AND status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['template_id'])) {
            $whereClause .= " AND template_id = ?";
            $params[] = $filters['template_id'];
        }
        
        if (!empty($filters['category'])) {
            $whereClause .= " AND category = ?";
            $params[] = $filters['category'];
        }
        
        if (!empty($filters['date_from'])) {
            $whereClause .= " AND created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $whereClause .= " AND created_at <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql = "
            SELECT es.*, et.name as template_name, et.slug as template_slug,
                   (SELECT COUNT(*) FROM email_opens WHERE email_id = es.id) as opens,
                   (SELECT COUNT(*) FROM email_clicks WHERE email_id = es.id) as clicks,
                   (SELECT COUNT(*) FROM email_opens WHERE email_id = es.id AND first_open = 1) as unique_opens,
                   (SELECT COUNT(*) FROM email_clicks WHERE email_id = es.id AND first_click = 1) as unique_clicks
            FROM email_sends es
            LEFT JOIN email_templates et ON es.template_id = et.id
            {$whereClause}
            ORDER BY es.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
}
?>