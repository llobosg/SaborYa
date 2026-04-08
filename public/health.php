<?php
// public/health.php - MINIMALISTA para Railway healthcheck
header('Content-Type: application/json');
http_response_code(200);
echo json_encode([
    'status' => 'healthy',
    'service' => 'saborya',
    'port' => getenv('PORT') ?: 'unknown',
    'timestamp' => time(),
    'php_version' => PHP_VERSION
], JSON_PRETTY_PRINT);
exit;
