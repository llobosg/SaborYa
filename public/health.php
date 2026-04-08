<?php
// public/health.php - Endpoint para health checks
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

try {
    // Verificar conexión a DB
    $pdo = getPDO();
    $pdo->query("SELECT 1");
    
    // Verificar configuración crítica
    $checks = [
        'database' => true,
        'brevo' => !empty(BREVO_API_KEY),
        'mercado_pago' => !empty(MP_ACCESS_TOKEN),
        'storage_writable' => is_writable(__DIR__ . '/../uploads'),
    ];
    
    $status = array_all($checks) ? 'healthy' : 'degraded';
    http_response_code($status === 'healthy' ? 200 : 503);
    
    echo json_encode([
        'status' => $status,
        'timestamp' => date('c'),
        'version' => '1.0.0',
        'checks' => $checks
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(503);
    echo json_encode([
        'status' => 'unhealthy',
        'error' => APP_ENV === 'development' ? $e->getMessage() : 'Service unavailable'
    ]);
}

function array_all($array) {
    foreach ($array as $value) {
        if (!$value) return false;
    }
    return true;
}