<?php
// public/api/auth/send-code.php

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Utils/BrevoMailer.php';
require_once __DIR__ . '/../../../src/Utils/RateLimiter.php';

use Saborya\Utils\BrevoMailer;
use Saborya\Utils\RateLimiter;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF básico
if (!verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token invalid']);
    exit;
}

// Validar email
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Email inválido']);
    exit;
}

// Rate limiting
$rateKey = "register:email:{$email}";
if (RateLimiter::isLimited($rateKey, 3, 3600)) {
    $remaining = RateLimiter::getRemainingSeconds($rateKey, 3600);
    http_response_code(429);
    echo json_encode([
        'error' => 'Demasiados intentos',
        'retry_after' => $remaining,
        'message' => "Intenta en " . ceil($remaining / 60) . " minutos"
    ]);
    exit;
}

// Generar código
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$codeHash = password_hash($code, PASSWORD_DEFAULT);
$expiresAt = time() + 300;

// Guardar código (archivo temporal)
$cacheDir = sys_get_temp_dir() . '/saborya_codes';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0700, true);
$cacheFile = $cacheDir . '/' . md5("code:{$email}");
file_put_contents($cacheFile, json_encode([
    'hash' => $codeHash,
    'expires' => $expiresAt,
    'created' => time()
]));

// Nombre para personalizar email
$userName = explode('@', $email)[0];
$userName = ucfirst(preg_replace('/[^a-z]/i', ' ', $userName));

// Enviar email
try {
    $mailer = new BrevoMailer();
    $sent = $mailer->sendRegistrationCode($email, $code, $userName);
    
    if (!$sent) {
        error_log("Brevo failed to send to {$email}");
        throw new Exception('Brevo API error');
    }
    
    RateLimiter::clear($rateKey);
    
    echo json_encode([
        'success' => true,
        'message' => 'Código enviado',
        'email_masked' => maskEmail($email),
        'expires_in' => 300
    ]);
    
} catch (Exception $e) {
    error_log("Registration code error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error enviando código',
        'details' => (defined('APP_ENV') && APP_ENV === 'development') ? $e->getMessage() : null
    ]);
}

function maskEmail(string $email): string {
    [$user, $domain] = explode('@', $email);
    $masked = strlen($user) > 2 
        ? $user[0] . str_repeat('*', strlen($user) - 2) . $user[-1]
        : str_repeat('*', strlen($user));
    return "{$masked}@{$domain}";
}