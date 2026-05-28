<?php

define('API_ACCESS', true);
require_once '../../config.php';
require_once 'TrackingManager.php';

header('Content-Type: image/png');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$trackingToken = $_GET['token'] ?? null;

if (empty($trackingToken)) {
        outputTransparentPixel();
    exit();
}

try {
        $trackingManager = new TrackingManager();
    
        $success = $trackingManager->trackOpen($trackingToken);
    
        outputTransparentPixel();
    
} catch (Exception $e) {
    error_log("Email tracking error: " . $e->getMessage());
    outputTransparentPixel();
}

function outputTransparentPixel() {
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    
    header('Content-Length: ' . strlen($pixel));
    echo $pixel;
}
?>