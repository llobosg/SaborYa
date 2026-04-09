<?php
// public/api/auth/send-code.php - CON LOGGING PARA DEBUG

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Utils/BrevoMailer.php';
require_once __DIR__ . '/../../../src/Utils/RateLimiter.php';

use Saborya\Utils\BrevoMailer;
use Saborya\Utils\RateLimiter;

// ============================================
// 🪵 FUNCIÓN DE LOGGING (escribe en archivo + error_log)
// ============================================
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = "[{$timestamp}] [{$ip}] {$message}";
    
    if ($data !== null) {
        $logEntry .= "\n  DATA: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    // 1. Escribir en error_log (aparece en railway logs)
    error_log("SABORYA_DEBUG: " . $logEntry);
    
    // 2. Escribir en archivo local (para debug avanzado)
    $logFile = sys_get_temp_dir() . '/saborya_debug.log';
    file_put_contents($logFile, $logEntry . "\n---\n", FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/json');

// ============================================
// 🪵 LOG 1: Request recibido
// ============================================
debugLog("=== INICIO send-code.php ===", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'raw_input_length' => strlen(file_get_contents('php://input') ?: ''),
    'headers' => [
        'X-CSRF-Token' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'not set',
        'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'not set'
    ]
]);

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Método no permitido", ['received_method' => $_SERVER['REQUEST_METHOD']]);
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ============================================
// 🪵 LOG 2: Leer cuerpo raw y parsear JSON
// ============================================
$rawBody = file_get_contents('php://input');
debugLog("Raw body recibido", [
    'length' => strlen($rawBody),
    'first_200_chars' => substr($rawBody, 0, 200),
    'is_valid_json' => json_decode($rawBody) !== null ? 'yes' : 'no',
    'json_error' => json_last_error_msg()
]);

$input = json_decode($rawBody, true);
debugLog("JSON parseado", [
    'is_array' => is_array($input),
    'keys' => array_keys($input ?? []),
    'full_parsed' => $input
]);

// ============================================
// 🪵 LOG 3: Validar CSRF
// ============================================
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
debugLog("CSRF Token", [
    'from_json' => $input['csrf_token'] ?? 'not in json',
    'from_header' => $_SERVER['HTTP_X_CSRF_TOKEN'] ?? 'not in header',
    'final_used' => $csrfToken !== '' ? '***' : 'EMPTY'
]);

if (!verify_csrf($csrfToken)) {
    debugLog("ERROR: CSRF validation failed", [
        'session_csrf' => $_SESSION['csrf_token'] ?? 'NOT SET IN SESSION',
        'received_token' => $csrfToken !== '' ? '***' : 'EMPTY'
    ]);
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token invalid']);
    exit;
}
debugLog("✅ CSRF validation passed");

// ============================================
// 🪵 LOG 4: Validar email (EL PUNTO CRÍTICO)
// ============================================
$emailRaw = $input['email'] ?? '';
$emailTrimmed = trim($emailRaw);

debugLog("Email recibido", [
    'raw_from_json' => $emailRaw,
    'after_trim' => $emailTrimmed,
    'is_empty' => empty($emailTrimmed),
    'type' => gettype($emailRaw),
    'length' => strlen($emailRaw)
]);

$email = filter_var($emailTrimmed, FILTER_VALIDATE_EMAIL);

debugLog("Email validation result", [
    'input_to_filter_var' => $emailTrimmed,
    'filter_var_result' => $email,
    'is_valid' => $email !== false ? 'YES ✓' : 'NO ✗'
]);

if (!$email) {
    debugLog("ERROR: Email validation failed", [
        'original_input' => $input['email'] ?? 'NOT IN INPUT',
        'after_trim' => $emailTrimmed,
        'filter_var_returned' => $email,
        'possible_causes' => [
            'empty_string' => empty($emailTrimmed),
            'wrong_encoding' => mb_detect_encoding($emailTrimmed),
            'hidden_chars' => bin2hex($emailTrimmed)
        ]
    ]);
    
    http_response_code(400);
    echo json_encode([
        'error' => 'Email inválido',
        'debug' => APP_ENV === 'development' ? [
            'received' => $input['email'] ?? null,
            'trimmed' => $emailTrimmed,
            'filter_result' => $email
        ] : null
    ]);
    exit;
}

debugLog("✅ Email validado correctamente: {$email}");

// ============================================
// 🪵 LOG 5: Rate limiting
// ============================================
$rateKey = "register:email:{$email}";
if (RateLimiter::isLimited($rateKey, 3, 3600)) {
    $remaining = RateLimiter::getRemainingSeconds($rateKey, 3600);
    debugLog("ERROR: Rate limit exceeded", ['email' => maskEmail($email), 'retry_after' => $remaining]);
    
    http_response_code(429);
    echo json_encode([
        'error' => 'Demasiados intentos',
        'retry_after' => $remaining,
        'message' => "Intenta en " . ceil($remaining / 60) . " minutos"
    ]);
    exit;
}
debugLog("✅ Rate limit OK");

// ============================================
// 🪵 LOG 6: Generar y guardar código
// ============================================
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$codeHash = password_hash($code, PASSWORD_DEFAULT);
$expiresAt = time() + 300;

$cacheDir = sys_get_temp_dir() . '/saborya_codes';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0700, true);
$cacheFile = $cacheDir . '/' . md5("code:{$email}");

file_put_contents($cacheFile, json_encode([
    'hash' => $codeHash,
    'expires' => $expiresAt,
    'created' => time()
]));

debugLog("Código generado y guardado", [
    'email' => maskEmail($email),
    'code_length' => strlen($code),
    'cache_file' => $cacheFile,
    'file_exists' => file_exists($cacheFile),
    'expires_in_seconds' => 300
]);

// ============================================
// 🪵 LOG 7: Preparar y enviar email con Brevo
// ============================================
$userName = explode('@', $email)[0];
$userName = ucfirst(preg_replace('/[^a-z]/i', ' ', $userName));

debugLog("Enviando email con Brevo", [
    'to' => maskEmail($email),
    'user_name' => $userName,
    'brevo_key_configured' => !empty(BREVO_API_KEY) ? 'YES' : 'NO ⚠️',
    'from_email' => BREVO_FROM_EMAIL ?? 'not set'
]);

try {
    $mailer = new BrevoMailer();
    $sent = $mailer->sendRegistrationCode($email, $code, $userName);
    
    debugLog("Brevo sendRegistrationCode result", [
        'returned' => $sent ? 'true ✓' : 'false ✗',
        'brevo_api_key_present' => !empty(BREVO_API_KEY)
    ]);
    
    if (!$sent) {
        debugLog("ERROR: Brevo failed to send", [
            'email' => maskEmail($email),
            'api_key_length' => strlen(BREVO_API_KEY ?? ''),
            'api_key_prefix' => substr(BREVO_API_KEY ?? '', 0, 10) . '...'
        ]);
        throw new Exception('Brevo API error - check logs');
    }
    
    // Limpiar rate limit tras éxito
    RateLimiter::clear($rateKey);
    debugLog("✅ Email enviado exitosamente + rate limit cleared");
    
    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Código enviado',
        'email_masked' => maskEmail($email),
        'expires_in' => 300
    ]);
    
} catch (Exception $e) {
    debugLog("EXCEPTION en envío de email", [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Error enviando código',
        'details' => (defined('APP_ENV') && APP_ENV === 'development') ? $e->getMessage() : null
    ]);
}

// ============================================
// 🪵 LOG FINAL: Request completado
// ============================================
debugLog("=== FIN send-code.php ===\n");

// Helpers
function maskEmail(string $email): string {
    [$user, $domain] = explode('@', $email);
    $masked = strlen($user) > 2 
        ? $user[0] . str_repeat('*', strlen($user) - 2) . $user[-1]
        : str_repeat('*', strlen($user));
    return "{$masked}@{$domain}";
}