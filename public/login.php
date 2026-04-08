<?php
// public/api/auth/login.php

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token invalid']);
    exit;
}

$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $_POST['password'] ?? '';

if (!$email || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Credenciales inválidas']);
    exit;
}

// Rate limiting por IP
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$rateKey = "login:ip:{$ip}";
if (\Saborya\Utils\RateLimiter::isLimited($rateKey, 5, 900)) {
    http_response_code(429);
    echo json_encode(['error' => 'Demasiados intentos. Intenta en 15 minutos']);
    exit;
}

// Consultar usuario
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, verified_at FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    \Saborya\Utils\RateLimiter::isLimited($rateKey, 5, 900); // Registrar intento fallido
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales incorrectas']);
    exit;
}

if (!$user['verified_at']) {
    http_response_code(403);
    echo json_encode(['error' => 'Confirma tu email para continuar']);
    exit;
}

// Login exitoso
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['last_activity'] = time();

\Saborya\Utils\RateLimiter::clear($rateKey);
log_audit($user['id'], 'login_success', 'users', $user['id'], $ip);

$redirect = $user['role'] === 'admin' ? '/admin' : '/home';
echo json_encode(['success' => true, 'redirect' => $redirect, 'user' => ['name' => $user['name'], 'role' => $user['role']]]);

function log_audit($userId, $action, $table, $recordId, $ip) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $table, $recordId, $ip]);
    } catch (Exception $e) { error_log("Audit: " . $e->getMessage()); }
}