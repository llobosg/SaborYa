<?php
// public/api/admin/invite.php - Crear invitación para nuevo admin
require_once __DIR__ . '/../../../config/config.php';

header('Content-Type: application/json');

// Solo POST + solo admin autenticado
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
}
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!verify_csrf($csrfToken)) {
    http_response_code(403); echo json_encode(['error' => 'CSRF invalid']); exit;
}

$email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$name = sanitize_input($input['name'] ?? '');
$role = in_array($input['role'] ?? '', ['admin', 'supervisor']) ? $input['role'] : 'admin';

if (!$email) {
    http_response_code(400); echo json_encode(['error' => 'Email inválido']); exit;
}

try {
    $pdo = getPDO();
    
    // Verificar si el usuario ya existe
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['role'] === 'admin') {
            echo json_encode(['error' => 'Este email ya tiene acceso de administrador']);
            exit;
        }
        // Actualizar rol si era consumer
        $pdo->prepare("UPDATE users SET role = ?, is_active = 0 WHERE id = ?")
            ->execute([$role, $existing['id']]);
        $userId = $existing['id'];
    } else {
        // Crear nuevo usuario admin (inactivo hasta aceptar invitación)
        $stmt = $pdo->prepare("INSERT INTO users (name, email, role, is_active, password_hash) VALUES (?, ?, ?, 0, '')");
        $stmt->execute([$name ?: explode('@', $email)[0], $email, $role]);
        $userId = $pdo->lastInsertId();
    }
    
    // Generar token único + expiración (24 horas)
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $pdo->prepare("UPDATE users SET invite_token = ?, invite_expires_at = ? WHERE id = ?")
        ->execute([$token, $expires, $userId]);
    
    // Enviar email de invitación con Brevo
    $inviteLink = APP_URL . "/admin/accept-invite.php?token={$token}";
    $mailer = new \Saborya\Utils\BrevoMailer();
    $sent = $mailer->sendTransactional(
        $email,
        'Invitación a administrar SaborYa 🍽️',
        renderInviteEmail($name, $inviteLink)
    );
    
    if (!$sent) {
        throw new Exception('Brevo failed to send invitation');
    }
    
    // Log de auditoría
    log_audit($_SESSION['user_id'], 'admin_invite_sent', 'users', $userId, $_SERVER['REMOTE_ADDR'] ?? '');
    
    echo json_encode([
        'success' => true,
        'message' => 'Invitación enviada',
        'email' => maskEmail($email),
        'expires_in' => '24 horas'
    ]);
    
} catch (Exception $e) {
    error_log("Admin invite error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error al enviar invitación']);
}

// Helpers
function renderInviteEmail($name, $link) {
    $appName = APP_NAME ?? 'SaborYa';
    return <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto">
  <div style="background:#FF6B35;padding:20px;text-align:center">
    <h1 style="color:white;margin:0">🍽️ {$appName}</h1>
  </div>
  <div style="padding:30px">
    <h2>¡Hola, {$name}! 👋</h2>
    <p>Has sido invitado a administrar <strong>{$appName}</strong>.</p>
    <p>Para activar tu cuenta y establecer tu contraseña, haz clic en el botón:</p>
    <p style="text-align:center;margin:30px 0">
      <a href="{$link}" style="background:#FF6B35;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold">
        Activar mi cuenta →
      </a>
    </p>
    <p style="color:#666;font-size:14px">
      🔒 Este enlace expira en 24 horas<br>
      Si no solicitaste esta invitación, ignora este email.
    </p>
  </div>
</body>
</html>
HTML;
}

function maskEmail($email) {
    [$u, $d] = explode('@', $email);
    return substr($u,0,1) . '***@' . $d;
}

function log_audit($userId, $action, $table, $recordId, $ip) {
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $table, $recordId, $ip]);
    } catch (Exception $e) { error_log("Audit: " . $e->getMessage()); }
}