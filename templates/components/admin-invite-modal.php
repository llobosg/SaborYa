<?php
// 🔒 Bloquear cualquier acceso que no sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

try {
    // 1. Cargar configuración con ruta absoluta
    $configPath = realpath(__DIR__ . '/../../../config/config.php');
    if (!$configPath || !file_exists($configPath)) {
        throw new Exception('Config file not found at: ' . __DIR__ . '/../../../config/config.php');
    }
    require_once $configPath;

    // 2. Cargar BrevoMailer explícitamente (evita "Class not found" fatal error)
    $brevoPath = realpath(__DIR__ . '/../../../src/Utils/BrevoMailer.php');
    if ($brevoPath && file_exists($brevoPath)) {
        require_once $brevoPath;
    }

    // 3. Validar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405); echo json_encode(['error' => 'Method not allowed']); exit;
    }

    // 4. Validar autenticación admin
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        http_response_code(403); echo json_encode(['error' => 'Unauthorized']); exit;
    }

    // 5. Parsear JSON
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    if (!$input || json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400); echo json_encode(['error' => 'Invalid JSON payload']); exit;
    }

    // 6. CSRF
    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!function_exists('verify_csrf') || !verify_csrf($csrfToken)) {
        http_response_code(403); echo json_encode(['error' => 'CSRF token invalid']); exit;
    }

    // 7. Validar inputs
    $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $name = sanitize_input($input['name'] ?? '') ?: explode('@', $email)[0];
    $role = in_array($input['role'] ?? '', ['admin', 'supervisor']) ? $input['role'] : 'admin';

    if (!$email) {
        http_response_code(400); echo json_encode(['error' => 'Email inválido']); exit;
    }

    // 8. Lógica DB
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    if ($existing && $existing['role'] === 'admin') {
        echo json_encode(['error' => 'Este email ya tiene acceso de administrador']); exit;
    }

    if ($existing) {
        $pdo->prepare("UPDATE users SET role = ?, is_active = 0 WHERE id = ?")->execute([$role, $existing['id']]);
        $userId = $existing['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, role, is_active, password_hash) VALUES (?, ?, ?, 0, '')");
        $stmt->execute([$name, $email, $role]);
        $userId = $pdo->lastInsertId();
    }

    // 9. Generar token único + expiración
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    $pdo->prepare("UPDATE users SET invite_token = ?, invite_expires_at = ? WHERE id = ?")
        ->execute([$token, $expires, $userId]);

    // 10. Enviar email (Brevo) - Con fallback si falla
    $inviteLink = APP_URL . "/admin/accept-invite.php?token={$token}";
    $emailSent = false;
    $emailError = '';

    if (class_exists('\Saborya\Utils\BrevoMailer')) {
        try {
            $mailer = new \Saborya\Utils\BrevoMailer();
            $subject = 'Invitación a administrar SaborYa 🍽️';
            $body = "<p>Hola {$name},</p><p>Activa tu cuenta de administrador aquí: <a href='{$inviteLink}'>Enlace de activación</a></p><p>Expira en 24h.</p>";
            $emailSent = $mailer->sendTransactional($email, $subject, $body);
        } catch (Exception $e) {
            $emailError = $e->getMessage();
            error_log("Brevo send failed for {$email}: " . $emailError);
        }
    } else {
        error_log("BrevoMailer class not loaded. Invite created but email not sent.");
    }

    // 11. Log de auditoría
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], 'admin_invite_sent', 'users', $userId, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) { /* No fallar si el log falla */ }

    // 12. Respuesta exitosa
    $maskedEmail = substr($email, 0, 1) . '***@' . explode('@', $email)[1];
    echo json_encode([
        'success' => true,
        'message' => $emailSent ? 'Invitación enviada correctamente' : 'Invitación creada (revisa logs si el email no llegó)',
        'email' => $maskedEmail,
        'expires_in' => '24 horas',
        'debug' => APP_ENV === 'development' ? ['email_sent' => $emailSent, 'error' => $emailError] : null
    ]);

} catch (Exception $e) {
    // Log detallado para Railway
    error_log("Admin Invite API CRASH: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno al procesar invitación',
        'details' => APP_ENV === 'development' ? $e->getMessage() : null
    ]);
}