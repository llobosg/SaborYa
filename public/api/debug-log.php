<?php
// public/api/debug-log.php - Recepción de logs del frontend (solo dev)
require_once __DIR__ . '/../../config/config.php';

// Solo permitir en desarrollo o desde localhost
if (APP_ENV === 'production' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    exit;
}

header('Content-Type: application/json');
$input = json_decode(file_get_contents('php://input'), true);

if ($input) {
    error_log("FRONTEND_LOG: " . json_encode($input));
    echo json_encode(['received' => true]);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'No data']);
}