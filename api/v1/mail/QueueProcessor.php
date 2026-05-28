<?php

if (!defined('API_ACCESS')) {
    define('API_ACCESS', true);
}
require_once __DIR__ . '/../config.php';
require_once 'MailerManager.php';

class EmailQueueProcessor {
    private $db;
    private $mailer;
    private $maxProcessingTime = 300;
    private $batchSize = 50;
    private $isRunning = false;
    private $processedCount = 0;
    private $errorCount = 0;
    private $startTime;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->mailer = new MailerManager();
        $this->startTime = time();
        
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
        }
        
        ini_set('memory_limit', '512M');
        set_time_limit(0);
    }
    
    public function addToQueue($emailData, $priority = 3, $scheduleFor = null) {
        try {
            $this->validateQueueData($emailData);
            
            $nextAttempt = $scheduleFor ? date('Y-m-d H:i:s', strtotime($scheduleFor)) : date('Y-m-d H:i:s');
            
            $stmt = $this->db->prepare("
                INSERT INTO email_queue (email_data, priority, next_attempt_at, status)
                VALUES (?, ?, ?, 'pending')
            ");
            
            $result = $stmt->execute([
                json_encode($emailData),
                $priority,
                $nextAttempt
            ]);
            
            if ($result) {
                $queueId = $this->db->lastInsertId();
                error_log("Email added to queue - ID: $queueId, To: " . ($emailData['to_email'] ?? 'unknown'));
                
                return [
                    'success' => true,
                    'queue_id' => $queueId,
                    'scheduled_for' => $nextAttempt
                ];
            } else {
                throw new Exception('Failed to add email to queue');
            }
            
        } catch (Exception $e) {
            error_log("Queue add error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function processQueue($maxEmails = null) {
        try {
            $this->isRunning = true;
            $maxEmails = $maxEmails ?? $this->batchSize;
            
            $this->cleanupStaleProcesses();
            
            while ($this->isRunning && $this->processedCount < $maxEmails) {
                if (!$this->checkLimits()) {
                    break;
                }
                
                $emails = $this->getNextBatch();
                
                if (empty($emails)) {
                    break;
                }
                
                foreach ($emails as $email) {
                    if (!$this->isRunning) break;
                    
                    $this->processQueueItem($email);
                }
                
                if (function_exists('gc_collect_cycles')) {
                    gc_collect_cycles();
                }
            }
            
            $duration = time() - $this->startTime;
            error_log("Queue processing completed - Processed: {$this->processedCount}, Errors: {$this->errorCount}, Duration: {$duration}s");
            
            return [
                'success' => true,
                'processed' => $this->processedCount,
                'errors' => $this->errorCount,
                'duration' => $duration
            ];
            
        } catch (Exception $e) {
            error_log("Queue processing error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processed' => $this->processedCount,
                'errors' => $this->errorCount
            ];
        }
    }
    
    private function getNextBatch() {
        $stmt = $this->db->prepare("
            UPDATE email_queue 
            SET status = 'processing', updated_at = NOW()
            WHERE status = 'pending' 
            AND next_attempt_at <= NOW()
            AND attempts < max_attempts
            ORDER BY priority ASC, next_attempt_at ASC
            LIMIT ?
        ");
        $stmt->execute([$this->batchSize]);
        
        if ($stmt->rowCount() == 0) {
            return [];
        }
        
        $stmt = $this->db->prepare("
            SELECT * FROM email_queue 
            WHERE status = 'processing'
            AND updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ORDER BY priority ASC, id ASC
            LIMIT ?
        ");
        $stmt->execute([$this->batchSize]);
        
        return $stmt->fetchAll();
    }
    
    private function processQueueItem($queueItem) {
        try {
            $emailData = json_decode($queueItem['email_data'], true);
            
            if (!$emailData) {
                throw new Exception('Invalid email data in queue item ' . $queueItem['id']);
            }
            
            $this->incrementAttempts($queueItem['id']);
            
                        if (isset($emailData['template'])) {
                if (!$this->templateExists($emailData['template'])) {
                    throw new Exception("Template '{$emailData['template']}' not found");
                }
                
                $result = $this->mailer->sendTemplate(
                    $emailData['template'],
                    $emailData['to_email'],
                    $emailData['to_name'] ?? '',
                    $emailData['variables'] ?? [],
                    $emailData['options'] ?? []
                );
            } else {
                $result = $this->mailer->sendCustom(
                    $emailData['to_email'],
                    $emailData['to_name'] ?? '',
                    $emailData['subject'],
                    $emailData['content'],
                    $emailData['text_content'] ?? null,
                    $emailData['options'] ?? []
                );
            }
            
                        if (!is_array($result)) {
                throw new Exception('Mailer returned invalid response: ' . var_export($result, true));
            }
            
            if ($result['success']) {
                $this->markAsComplete($queueItem['id'], 'sent');
                $this->processedCount++;
                error_log("Queue email sent successfully - Queue ID: {$queueItem['id']}");
            } else {
                throw new Exception($result['error'] ?? 'Unknown sending error');
            }
            
        } catch (Exception $e) {
            $this->handleQueueError($queueItem, $e);
        }
    }
    
    private function templateExists($templateSlug) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM email_templates 
            WHERE slug = ? AND is_active = 1
        ");
        $stmt->execute([$templateSlug]);
        return $stmt->fetchColumn() > 0;
    }
    
    private function handleQueueError($queueItem, $exception) {
        $errorMessage = $exception->getMessage();
        $this->errorCount++;
        
        error_log("Queue processing error - Queue ID: {$queueItem['id']}, Error: $errorMessage");
        
        if ($queueItem['attempts'] >= $queueItem['max_attempts']) {
            $this->markAsComplete($queueItem['id'], 'failed', $errorMessage);
        } else {
            $this->scheduleRetry($queueItem['id'], $queueItem['attempts'], $errorMessage);
        }
    }
    
    private function markAsComplete($queueId, $status, $error = null) {
        $stmt = $this->db->prepare("
            UPDATE email_queue 
            SET status = ?, last_error = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $error, $queueId]);
    }
    
    private function scheduleRetry($queueId, $currentAttempts, $error) {
        $delayMinutes = min(pow(2, $currentAttempts), 120);
        $nextAttempt = date('Y-m-d H:i:s', time() + ($delayMinutes * 60));
        
        $stmt = $this->db->prepare("
            UPDATE email_queue 
            SET status = 'pending', next_attempt_at = ?, last_error = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$nextAttempt, $error, $queueId]);
    }
    
    private function incrementAttempts($queueId) {
        $stmt = $this->db->prepare("
            UPDATE email_queue 
            SET attempts = attempts + 1, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$queueId]);
    }
    
    private function cleanupStaleProcesses() {
        $stmt = $this->db->prepare("
            UPDATE email_queue 
            SET status = 'pending'
            WHERE status = 'processing' 
            AND updated_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            error_log("Cleaned up " . $stmt->rowCount() . " stale processing records");
        }
    }
    
    private function checkLimits() {
        if ((time() - $this->startTime) >= $this->maxProcessingTime) {
            return false;
        }
        
        $memoryLimit = $this->getMemoryLimitBytes();
        if ($memoryLimit > 0 && memory_get_usage(true) > ($memoryLimit * 0.8)) {
            return false;
        }
        
        return true;
    }
    
    private function getMemoryLimitBytes() {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit == -1) return -1;
        
        $unit = strtolower(substr($memoryLimit, -1));
        $value = (int)$memoryLimit;
        
        switch($unit) {
            case 'g': return $value * 1024 * 1024 * 1024;
            case 'm': return $value * 1024 * 1024;
            case 'k': return $value * 1024;
            default: return $value;
        }
    }
    
    private function validateQueueData($data) {
        if (empty($data['to_email'])) {
            throw new Exception('to_email is required');
        }
        
        if (!filter_var($data['to_email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address');
        }
        
        if (isset($data['template'])) {
            if (empty($data['template'])) {
                throw new Exception('Template slug required');
            }
        } else {
            if (empty($data['subject']) || empty($data['content'])) {
                throw new Exception('Subject and content required for custom emails');
            }
        }
    }
    
    public function handleSignal($signal) {
        switch($signal) {
            case SIGTERM:
            case SIGINT:
                error_log("Received shutdown signal, stopping gracefully...");
                $this->isRunning = false;
                break;
        }
    }
    
    public function getQueueStats() {
        $stmt = $this->db->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                MIN(created_at) as oldest,
                MAX(created_at) as newest,
                AVG(attempts) as avg_attempts
            FROM email_queue 
            GROUP BY status
        ");
        $stmt->execute();
        $stats = $stmt->fetchAll();
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'pending' AND next_attempt_at <= NOW() THEN 1 END) as ready,
                COUNT(CASE WHEN attempts >= max_attempts AND status = 'failed' THEN 1 END) as permanently_failed
            FROM email_queue
        ");
        $stmt->execute();
        $global = $stmt->fetch();
        
        return [
            'by_status' => $stats,
            'global' => $global,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    public function cleanupQueue($daysToKeep = 30) {
        $stmt = $this->db->prepare("
            DELETE FROM email_queue 
            WHERE status IN ('sent', 'failed') 
            AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$daysToKeep]);
        
        $deleted = $stmt->rowCount();
        error_log("Queue cleanup: deleted $deleted old records");
        
        return $deleted;
    }
}

if (php_sapi_name() === 'cli') {
    $processor = new EmailQueueProcessor();
    
    $options = getopt('m:c', ['max-emails:', 'cleanup']);
    
    $maxEmails = $options['m'] ?? $options['max-emails'] ?? 100;
    
    if (isset($options['c']) || isset($options['cleanup'])) {
        echo "Cleaning up old queue records...\n";
        $deleted = $processor->cleanupQueue();
        echo "Deleted $deleted old records\n";
        exit(0);
    }
    
    $result = $processor->processQueue($maxEmails);
    
    if ($result['success']) {
        echo "Processing completed - Processed: {$result['processed']}, Errors: {$result['errors']}, Duration: {$result['duration']}s\n";
        exit(0);
    } else {
        echo "Processing failed: {$result['error']}\n";
        exit(1);
    }
}
?>