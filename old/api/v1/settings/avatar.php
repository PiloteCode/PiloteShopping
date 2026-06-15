<?php

define('API_ACCESS', true);
require_once '../config.php';

require_once __DIR__ . '/../../../includes/CsrfProtection.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . SITE_URL);
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

\CsrfProtection::requireValidToken();

session_start();

if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit();
}

$userId = intval($_SESSION['user_id']);

class AvatarManager {
    private $db;
    private $uploadPath;
    private $allowedTypes;
    private $maxSize;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/avatars/';
        $this->allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];
        $this->maxSize = 2 * 1024 * 1024;         
                if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }
    
    public function uploadAvatar($userId, $file) {
        try {
            $this->validateFile($file);
            $detectedExt = $this->detectImageExtension($file['tmp_name']);
            if ($detectedExt === null) {
                throw new Exception('Format de fichier non supporté');
            }
            $filename = 'avatar_' . (int)$userId . '_' . bin2hex(random_bytes(8)) . '.' . $detectedExt;
            $filePath = $this->uploadPath . $filename;
            $publicUrl = '/uploads/avatars/' . $filename;
            
                        $this->resizeImage($file['tmp_name'], $filePath, 200, 200);
            
                        $stmt = $this->db->prepare("SELECT avatar_url FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            $oldAvatarUrl = $user['avatar_url'] ?? null;
            
                        $stmt = $this->db->prepare("
                UPDATE users 
                SET avatar_url = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$publicUrl, $userId]);
            
                        if ($oldAvatarUrl && strpos($oldAvatarUrl, '/uploads/avatars/') === 0) {
                $oldFilePath = $_SERVER['DOCUMENT_ROOT'] . $oldAvatarUrl;
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }
            
            logSecurityEvent($userId, 'avatar_uploaded', [
                'filename' => $filename
            ], 'success');
            
            return [
                'success' => true,
                'avatar_url' => $publicUrl,
                'message' => 'Avatar mis à jour avec succès'
            ];
            
        } catch (Exception $e) {
            logSecurityEvent($userId, 'avatar_upload_failed', [
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    public function removeAvatar($userId) {
        try {
                        $stmt = $this->db->prepare("SELECT avatar_url FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            $avatarUrl = $user['avatar_url'] ?? null;
            
                        if ($avatarUrl && strpos($avatarUrl, '/uploads/avatars/') === 0) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . $avatarUrl;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
                        $stmt = $this->db->prepare("
                UPDATE users 
                SET avatar_url = NULL, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            
                        $stmt = $this->db->prepare("SELECT email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            $defaultAvatarUrl = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user['email']))) . '?s=200&d=mp';
            
            logSecurityEvent($userId, 'avatar_removed', [], 'success');
            
            return [
                'success' => true,
                'default_avatar_url' => $defaultAvatarUrl,
                'message' => 'Avatar supprimé avec succès'
            ];
            
        } catch (Exception $e) {
            logSecurityEvent($userId, 'avatar_remove_failed', [
                'error' => $e->getMessage()
            ], 'failed');
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    private function validateFile($file) {
        if (!is_array($file) || !isset($file['error'])) {
            throw new Exception('Fichier manquant');
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Erreur lors de l\'upload du fichier');
        }
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception('Fichier invalide');
        }
        if ($file['size'] > $this->maxSize) {
            throw new Exception('Le fichier est trop volumineux (max 2MB)');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($detectedMime, $allowedMimes, true)) {
            throw new Exception('Format de fichier non supporté. Utilisez JPG, PNG ou WebP');
        }
        $imageInfo = @getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('Le fichier n\'est pas une image valide');
        }
        if ($imageInfo[0] < 16 || $imageInfo[1] < 16 || $imageInfo[0] > 4096 || $imageInfo[1] > 4096) {
            throw new Exception('Dimensions d\'image invalides (16-4096 px)');
        }
    }

    private function detectImageExtension($path) {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }
        switch ($info[2]) {
            case IMAGETYPE_JPEG: return 'jpg';
            case IMAGETYPE_PNG:  return 'png';
            case IMAGETYPE_WEBP: return 'webp';
            default: return null;
        }
    }
    
    private function getFileExtension($mimeType) {
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp'
        ];
        
        return $extensions[$mimeType] ?? 'jpg';
    }
    
    private function resizeImage($sourcePath, $destinationPath, $width, $height) {
        $imageInfo = getimagesize($sourcePath);
        $sourceWidth = $imageInfo[0];
        $sourceHeight = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
                switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                throw new Exception('Format d\'image non supporté');
        }
        
                $size = min($sourceWidth, $sourceHeight);
        $x = ($sourceWidth - $size) / 2;
        $y = ($sourceHeight - $size) / 2;
        
                $destinationImage = imagecreatetruecolor($width, $height);
        
                if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
            imagealphablending($destinationImage, false);
            imagesavealpha($destinationImage, true);
            $transparent = imagecolorallocatealpha($destinationImage, 255, 255, 255, 127);
            imagefill($destinationImage, 0, 0, $transparent);
        }
        
                imagecopyresampled(
            $destinationImage, $sourceImage,
            0, 0, $x, $y,
            $width, $height, $size, $size
        );
        
                switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($destinationImage, $destinationPath, 90);
                break;
            case 'image/png':
                imagepng($destinationImage, $destinationPath, 9);
                break;
            case 'image/webp':
                imagewebp($destinationImage, $destinationPath, 90);
                break;
        }
        
                imagedestroy($sourceImage);
        imagedestroy($destinationImage);
    }
}

try {
    $avatarManager = new AvatarManager();
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'POST':
                        if (!isset($_FILES['avatar'])) {
                throw new Exception('Aucun fichier fourni');
            }
            
            $result = $avatarManager->uploadAvatar($userId, $_FILES['avatar']);
            break;
            
        case 'DELETE':
                        $result = $avatarManager->removeAvatar($userId);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
            exit();
    }
    
    if ($result['success']) {
        http_response_code(200);
    } else {
        http_response_code(400);
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    logSecurityEvent($userId ?? null, 'avatar_api_error', [
        'error' => $e->getMessage()
    ], 'failed');
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => DEBUG_MODE ? $e->getMessage() : 'Erreur serveur'
    ]);
}
?>