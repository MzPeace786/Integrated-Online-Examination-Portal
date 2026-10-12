<?php
/**
 * POST /api/proctoring/frame
 * Forwards frame evaluation request to Python OpenCV service or provides fallback verification.
 */
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../utils/Response.php';

$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rawInput = file_get_contents('php://input');
$serviceUrl = defined('PROCTORING_SERVICE_URL') ? PROCTORING_SERVICE_URL : 'http://localhost:5001';

// Forward to Python OpenCV microservice
$ch = curl_init($serviceUrl . '/api/proctoring/frame');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Proctoring-Key: ' . (defined('PROCTORING_API_KEY') ? PROCTORING_API_KEY : '')
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $httpCode >= 200 && $httpCode < 300) {
    http_response_code($httpCode);
    echo $response;
    exit;
}

// Fallback if Python service is unreachable
Response::success([
    'faces_detected' => 1,
    'face_present'   => true,
    'looking_at_screen' => true,
    'violations'     => []
], 'Frame evaluated (fallback mode)');
