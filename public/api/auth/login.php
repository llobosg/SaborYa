<?php
// public/api/auth/login.php - API ENDPOINT (NO HTML, solo JSON)

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../src/Utils/RateLimiter.php';

use Saborya\Utils\RateLimiter;

header('Content-Type: application/json');

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// ✅ Parsear JSON body (fetch con application/json no llena $_POST)
$input = json_decode(file_get_contents('php://input'), true);

// CSRF validation
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token invalid']);
    exit;
}

// Validar inputs
$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$password = $input['password'] ?? '';

if (!$email || strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'Credenciales inválidas']);
    exit;
}

// Rate limiting por IP
$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
$rateKey = "login:ip:{$ip}";
if (RateLimiter::isLimited($rateKey, 5, 900)) {
    http_response_code(429);
    echo json_encode(['error' => 'Demasiados intentos. Intenta en 15 minutos']);
    exit;
}

// Consultar usuario
$pdo = getPDO();
$stmt = $pdo->prepare("SELECT id, name, password_hash, role, verified_at FROM users WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$user = $stmt->fetch();

// Verificar contraseña
if (!$user || !password_verify($password, $user['password_hash'])) {
    RateLimiter::isLimited($rateKey, 5, 900); // Registrar intento fallido
    http_response_code(401);
    echo json_encode(['error' => 'Credenciales incorrectas']);
    exit;
}

// Verificar email confirmado
if (!$user['verified_at']) {
    http_response_code(403);
    echo json_encode(['error' => 'Confirma tu email para continuar']);
    exit;
}

// ✅ Login exitoso - Crear sesión
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_role'] = $user['role'];
$_SESSION['last_activity'] = time();

// Limpiar rate limit
RateLimiter::clear($rateKey);

// Log de auditoría
log_audit($user['id'], 'login_success', 'users', $user['id'], $ip);

// Respuesta JSON
$redirect = $user['role'] === 'admin' ? '/admin' : '/home';
echo json_encode([
    'success' => true, 
    'redirect' => $redirect, 
    'user' => ['name' => $user['name'], 'role' => $user['role']]
]);

// Helper para logging
function log_audit($userId, $action, $table, $recordId, $ip) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $table, $recordId, $ip]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}