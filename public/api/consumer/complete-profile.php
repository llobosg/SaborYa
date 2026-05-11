<?php
// public/api/consumer/complete-profile.php - API para completar perfil post-registro

require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

// Solo POST autenticado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Verificar sesión
if (empty($_SESSION['user_id']) || !$_SESSION['verified']) {
    http_response_code(401);
    echo json_encode(['error' => 'No autenticado']);
    exit;
}

// CSRF
$input = json_decode(file_get_contents('php://input'), true);
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token invalid']);
    exit;
}

$userId = (int)($input['user_id'] ?? $_SESSION['user_id']);
$password = $input['password'] ?? '';
$phone = sanitize_input($input['phone'] ?? '');
$address = sanitize_input($input['address'] ?? '');
$notifyEmail = !empty($input['notify_email']);
$notifyWhatsapp = !empty($input['notify_whatsapp']);
$notifyPush = !empty($input['notify_push']);

// Validar contraseña
if (strlen($password) < 6) {
    http_response_code(400);
    echo json_encode(['error' => 'La contraseña debe tener al menos 6 caracteres']);
    exit;
}

try {
    $pdo = getPDO();
    
    // Actualizar usuario con password y datos adicionales
    $stmt = $pdo->prepare("
        UPDATE users 
        SET 
            password_hash = ?,
            phone = COALESCE(NULLIF(?, ''), phone),
            address = COALESCE(NULLIF(?, ''), address),
            notify_email = ?,
            notify_whatsapp = ?,
            notify_push = ?,
            profile_completed = 1,
            updated_at = NOW()
        WHERE id = ? AND verified_at IS NOT NULL
    ");
    
    $stmt->execute([
        password_hash($password, PASSWORD_DEFAULT),
        $phone,
        $address,
        $notifyEmail ? 1 : 0,
        $notifyWhatsapp ? 1 : 0,
        $notifyPush ? 1 : 0,
        $userId
    ]);
    
    if ($stmt->rowCount() === 0) {
        error_log("Complete profile: No rows updated for user_id={$userId}");
        http_response_code(400);
        echo json_encode(['error' => 'No se pudo actualizar el perfil']);
        exit;
    }
    
    // Actualizar sesión
    $_SESSION['profile_completed'] = true;
    
    // Log de auditoría
    log_audit($userId, 'profile_completed', 'users', $userId, $_SERVER['REMOTE_ADDR'] ?? '');
    
    echo json_encode([
        'success' => true,
        'message' => 'Perfil completado',
        'redirect' => '/catalogo'
    ]);
    
} catch (Exception $e) {
    error_log("Complete profile error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al guardar perfil',
        'details' => APP_ENV === 'development' ? $e->getMessage() : null
    ]);
}

function log_audit($userId, $action, $table, $recordId, $ip) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $table, $recordId, $ip]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}