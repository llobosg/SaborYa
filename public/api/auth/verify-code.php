<?php
// public/api/auth/verify-code.php - CON LOGGING PARA DEBUG

require_once __DIR__ . '/../../../config/config.php';

// ============================================
// 🪵 FUNCIÓN DE LOGGING (igual que send-code.php)
// ============================================
function debugLog($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $logEntry = "[{$timestamp}] [{$ip}] {$message}";
    
    if ($data !== null) {
        $logEntry .= "\n  DATA: " . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    
    error_log("SABORYA_DEBUG_VERIFY: " . $logEntry);
    $logFile = sys_get_temp_dir() . '/saborya_debug.log';
    file_put_contents($logFile, $logEntry . "\n---\n", FILE_APPEND | LOCK_EX);
}

header('Content-Type: application/json');

// ============================================
// 🪵 LOG 1: Request recibido
// ============================================
debugLog("=== INICIO verify-code.php ===", [
    'method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
    'raw_input_length' => strlen(file_get_contents('php://input') ?: '')
]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("ERROR: Método no permitido");
    http_response_code(405);
    exit;
}

// ============================================
// 🪵 LOG 2: Parsear JSON body
// ============================================
$rawBody = file_get_contents('php://input');
$input = json_decode($rawBody, true);

debugLog("JSON parseado", [
    'raw_length' => strlen($rawBody),
    'is_array' => is_array($input),
    'keys_received' => array_keys($input ?? []),
    'full_input' => $input
]);

// ============================================
// 🪵 LOG 3: Extraer y validar inputs (EL PUNTO CRÍTICO)
// ============================================
$emailRaw = $input['email'] ?? '';
$codeRaw = $input['code'] ?? '';
$nameRaw = $input['name'] ?? '';

$email = filter_var(trim($emailRaw), FILTER_VALIDATE_EMAIL);
$code = preg_replace('/[^0-9]/', '', $codeRaw);
$name = sanitize_input($nameRaw);

debugLog("Inputs extraídos y procesados", [
    'email' => [
        'raw' => $emailRaw,
        'trimmed' => trim($emailRaw),
        'validated' => $email,
        'is_valid' => $email !== false
    ],
    'code' => [
        'raw' => $codeRaw,
        'cleaned' => $code,
        'length' => strlen($code),
        'is_6_digits' => strlen($code) === 6
    ],
    'name' => $name
]);

// Validación crítica que estaba fallando
if (!$email || strlen($code) !== 6) {
    debugLog("ERROR: Validación fallida - Datos inválidos", [
        'email_valid' => $email !== false ? 'YES' : 'NO',
        'code_length' => strlen($code),
        'code_is_6' => strlen($code) === 6,
        'raw_email' => $emailRaw,
        'raw_code' => $codeRaw
    ]);
    
    http_response_code(400);
    echo json_encode([
        'error' => 'Datos inválidos',
        'debug' => APP_ENV === 'development' ? [
            'email_received' => $emailRaw,
            'email_valid' => $email !== false,
            'code_received' => $codeRaw,
            'code_cleaned' => $code,
            'code_length' => strlen($code)
        ] : null
    ]);
    exit;
}

debugLog("✅ Validación de inputs exitosa");

// ============================================
// 🪵 LOG 4: Buscar código en cache
// ============================================
$cacheFile = sys_get_temp_dir() . '/saborya_codes/' . md5("code:{$email}");

debugLog("Buscando código en cache", [
    'email' => maskEmail($email),
    'cache_file' => $cacheFile,
    'file_exists' => file_exists($cacheFile)
]);

if (!file_exists($cacheFile)) {
    debugLog("ERROR: Archivo de código no encontrado", [
        'email' => maskEmail($email),
        'expected_path' => $cacheFile,
        'temp_dir_contents' => array_slice(scandir(sys_get_temp_dir() . '/saborya_codes' ?? ''), 0, 10)
    ]);
    http_response_code(400);
    echo json_encode(['error' => 'Código no encontrado o expirado']);
    exit;
}

$data = json_decode(file_get_contents($cacheFile), true);

// ============================================
// 🪵 LOG 5: Verificar expiración
// ============================================
debugLog("Verificando expiración del código", [
    'current_time' => time(),
    'code_expires_at' => $data['expires'] ?? 'not set',
    'is_expired' => time() > ($data['expires'] ?? 0),
    'created_at' => $data['created'] ?? 'not set'
]);

if (time() > $data['expires']) {
    @unlink($cacheFile);
    debugLog("ERROR: Código expirado");
    http_response_code(400);
    echo json_encode(['error' => 'Código expirado']);
    exit;
}

// ============================================
// 🪵 LOG 6: Verificar código con hash
// ============================================
$codeMatch = password_verify($code, $data['hash']);

debugLog("Verificando código contra hash", [
    'code_entered' => $code,
    'hash_stored' => substr($data['hash'] ?? '', 0, 20) . '...',
    'password_verify_result' => $codeMatch ? 'MATCH ✓' : 'NO MATCH ✗'
]);

if (!$codeMatch) {
    debugLog("ERROR: Código incorrecto");
    http_response_code(400);
    echo json_encode(['error' => 'Código incorrecto']);
    exit;
}

debugLog("✅ Código verificado correctamente");

// ============================================
// 🪵 LOG 7: Crear/actualizar usuario
// ============================================
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT id, verified_at FROM users WHERE email = ?");
$stmt->execute([$email]);
$existingUser = $stmt->fetch();

debugLog("Buscando usuario en DB", [
    'email' => maskEmail($email),
    'user_exists' => $existingUser ? 'YES' : 'NO',
    'existing_verified' => $existingUser['verified_at'] ?? 'N/A'
]);

if ($existingUser) {
    if (!$existingUser['verified_at']) {
        $stmt = $pdo->prepare("UPDATE users SET verified_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$existingUser['id']]);
        debugLog("Usuario existente marcado como verificado", ['user_id' => $existingUser['id']]);
    }
    $userId = $existingUser['id'];
    
    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->execute([$name, $userId]);
        debugLog("Nombre de usuario actualizado", ['name' => $name]);
    }
} else {
    $tempPassword = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, address, password_hash, verified_at) 
                          VALUES (?, ?, '', '', ?, NOW())");
    $stmt->execute([
        !empty($name) ? $name : explode('@', $email)[0],
        $email,
        password_hash($tempPassword, PASSWORD_DEFAULT)
    ]);
    $userId = $pdo->lastInsertId();
    debugLog("Nuevo usuario creado", ['user_id' => $userId, 'email' => maskEmail($email)]);
}

// Cleanup
@unlink($cacheFile);
debugLog("Archivo de código eliminado (cleanup)");

// ============================================
// 🪵 LOG 8: Iniciar sesión
// ============================================
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_email'] = $email;
$_SESSION['user_role'] = 'consumer';
$_SESSION['verified'] = true;
$_SESSION['last_activity'] = time();

debugLog("Sesión iniciada", [
    'new_session_id' => session_id(),
    'user_id' => $userId,
    'user_role' => 'consumer'
]);

// Log auditoría
log_audit($userId, 'registration_verified', 'users', $userId, $_SERVER['REMOTE_ADDR'] ?? '');

// Respuesta exitosa
debugLog("=== FIN verify-code.php - ÉXITO ===");
echo json_encode([
    'success' => true,
    'message' => 'Email verificado',
    'redirect' => '/home',
    'user' => ['email' => maskEmail($email), 'role' => 'consumer']
]);

// Helpers
function log_audit($userId, $action, $table, $recordId, $ip) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $table, $recordId, $ip]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

function maskEmail(string $email): string {
    [$user, $domain] = explode('@', $email);
    $masked = strlen($user) > 2 
        ? $user[0] . str_repeat('*', strlen($user) - 2) . $user[-1]
        : str_repeat('*', strlen($user));
    return "{$masked}@{$domain}";
}