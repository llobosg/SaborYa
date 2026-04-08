<?php
// public/api/auth/verify-code.php

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$code = preg_replace('/[^0-9]/', '', $_POST['code'] ?? '');
$name = sanitize_input($_POST['name'] ?? '');

if (!$email || strlen($code) !== 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

// Buscar código
$cacheFile = sys_get_temp_dir() . '/saborya_codes/' . md5("code:{$email}");

if (!file_exists($cacheFile)) {
    http_response_code(400);
    echo json_encode(['error' => 'Código no encontrado o expirado']);
    exit;
}

$data = json_decode(file_get_contents($cacheFile), true);

// Verificar expiración
if (time() > $data['expires']) {
    @unlink($cacheFile);
    http_response_code(400);
    echo json_encode(['error' => 'Código expirado']);
    exit;
}

// Verificar código
if (!password_verify($code, $data['hash'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Código incorrecto']);
    exit;
}

// ✅ Código válido - Crear/actualizar usuario
$pdo = getPDO();

$stmt = $pdo->prepare("SELECT id, verified_at FROM users WHERE email = ?");
$stmt->execute([$email]);
$existingUser = $stmt->fetch();

if ($existingUser) {
    if (!$existingUser['verified_at']) {
        $stmt = $pdo->prepare("UPDATE users SET verified_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$existingUser['id']]);
    }
    $userId = $existingUser['id'];
    
    // Actualizar nombre si se proporcionó
    if (!empty($name)) {
        $stmt = $pdo->prepare("UPDATE users SET name = ? WHERE id = ?");
        $stmt->execute([$name, $userId]);
    }
} else {
    // Usuario nuevo
    $tempPassword = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, address, password_hash, verified_at) 
                          VALUES (?, ?, '', '', ?, NOW())");
    $stmt->execute([
        !empty($name) ? $name : explode('@', $email)[0],
        $email,
        password_hash($tempPassword, PASSWORD_DEFAULT)
    ]);
    $userId = $pdo->lastInsertId();
}

// Cleanup
@unlink($cacheFile);

// Iniciar sesión
session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['user_email'] = $email;
$_SESSION['user_role'] = 'consumer';
$_SESSION['verified'] = true;
$_SESSION['last_activity'] = time();

// Log auditoría
log_audit($userId, 'registration_verified', 'users', $userId, $_SERVER['REMOTE_ADDR'] ?? '');

echo json_encode([
    'success' => true,
    'message' => 'Email verificado',
    'redirect' => '/home',
    'user' => ['email' => $email, 'role' => 'consumer']
]);

function log_audit($userId, $action, $table, $recordId, $ip) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $table, $recordId, $ip]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}