<?php
// public/api/admin/invite.php - API para invitar nuevos administradores
// ============================================================
// 🔒 BLOQUEO ABSOLUTO: Solo permitir método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("INVITE_API_DEBUG: GET/OTHER request blocked - URI: {$_SERVER['REQUEST_URI']} - Method: {$_SERVER['REQUEST_METHOD']}");
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit; // ← IMPORTANTE: detener ejecución inmediatamente
}

error_log("INVITE_API_DEBUG: POST request received - Starting processing");
header('Content-Type: application/json');

try {
    // ============================================================
    // 1. Cargar configuración con rutas absolutas y validación
    $configPath = realpath(__DIR__ . '/../../../config/config.php');
    if (!$configPath || !file_exists($configPath)) {
        throw new Exception('Config file not found at: ' . ($configPath ?: 'NULL'));
    }
    require_once $configPath;
    error_log("INVITE_API_DEBUG: Config loaded from {$configPath}");

    // ============================================================
    // 2. Cargar BrevoMailer explícitamente (evita "Class not found")
    $brevoPath = realpath(__DIR__ . '/../../../src/Utils/BrevoMailer.php');
    if ($brevoPath && file_exists($brevoPath)) {
        require_once $brevoPath;
        error_log("INVITE_API_DEBUG: BrevoMailer loaded from {$brevoPath}");
    } else {
        error_log("INVITE_API_DEBUG: BrevoMailer NOT found at {$brevoPath}");
    }

    // ============================================================
    // 3. Validar autenticación y rol de administrador
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['user_id'])) {
        error_log("INVITE_API_DEBUG: Unauthorized - No user_id in session");
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized: No active session']);
        exit;
    }
    
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        error_log("INVITE_API_DEBUG: Forbidden - User role is: " . ($_SESSION['user_role'] ?? 'none'));
        http_response_code(403);
        echo json_encode(['error' => 'Forbidden: Admin role required']);
        exit;
    }
    error_log("INVITE_API_DEBUG: Auth OK - User ID: {$_SESSION['user_id']}, Role: {$_SESSION['user_role']}");

    // ============================================================
    // 4. Parsear cuerpo JSON con manejo de errores
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        throw new Exception('Empty request body');
    }
    
    $input = json_decode($rawInput, true);
    if (!$input || json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON parse error: ' . json_last_error_msg());
    }
    error_log("INVITE_API_DEBUG: JSON parsed successfully");

    // ============================================================
    // 5. Validar CSRF token
    $csrfToken = $input['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!function_exists('verify_csrf')) {
        throw new Exception('verify_csrf function not defined');
    }
    if (!verify_csrf($csrfToken)) {
        error_log("INVITE_API_DEBUG: CSRF validation failed");
        http_response_code(403);
        echo json_encode(['error' => 'CSRF token invalid']);
        exit;
    }
    error_log("INVITE_API_DEBUG: CSRF validation passed");

    // ============================================================
    // 6. Validar y sanitizar inputs
    $email = filter_var(trim($input['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $name = sanitize_input($input['name'] ?? '');
    $role = in_array($input['role'] ?? '', ['admin', 'supervisor']) ? $input['role'] : 'admin';
    
    if (!$email) {
        http_response_code(400);
        echo json_encode(['error' => 'Email inválido', 'received' => $input['email'] ?? 'empty']);
        exit;
    }
    
    // Si no se proporciona nombre, usar parte del email
    if (empty($name)) {
        $name = explode('@', $email)[0];
    }
    
    error_log("INVITE_API_DEBUG: Inputs validated - Email: " . maskEmail($email) . ", Name: {$name}, Role: {$role}");

    // ============================================================
    // 7. Operaciones de base de datos
    $pdo = getPDO();
    
    // Verificar si el usuario ya existe
    $stmt = $pdo->prepare("SELECT id, role, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        if ($existing['role'] === 'admin' && ($existing['is_active'] ?? true)) {
            echo json_encode(['error' => 'Este email ya tiene acceso de administrador activo']);
            exit;
        }
        // Actualizar rol si ya existe pero no es admin activo
        $stmt = $pdo->prepare("UPDATE users SET role = ?, is_active = 0, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$role, $existing['id']]);
        $userId = $existing['id'];
        error_log("INVITE_API_DEBUG: Existing user updated - ID: {$userId}");
    } else {
        // Crear nuevo usuario (inactivo hasta aceptar invitación)
        $tempPassword = bin2hex(random_bytes(32)); // Placeholder, se setea al aceptar
        $stmt = $pdo->prepare("INSERT INTO users (name, email, role, is_active, password_hash, created_at) VALUES (?, ?, ?, 0, ?, NOW())");
        $stmt->execute([$name, $email, $role, password_hash($tempPassword, PASSWORD_DEFAULT)]);
        $userId = $pdo->lastInsertId();
        error_log("INVITE_API_DEBUG: New user created - ID: {$userId}");
    }

    // ============================================================
    // 8. Generar token único de invitación + expiración (24 horas)
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $pdo->prepare("UPDATE users SET invite_token = ?, invite_expires_at = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$token, $expires, $userId]);
    
    $inviteLink = APP_URL . "/admin/accept-invite.php?token={$token}";
    error_log("INVITE_API_DEBUG: Invite token generated - Expires: {$expires}");

    // ============================================================
    // 9. Enviar email de invitación con Brevo (con fallback seguro)
    $emailSent = false;
    $emailError = '';
    
    if (class_exists('\Saborya\Utils\BrevoMailer')) {
        try {
            $mailer = new \Saborya\Utils\BrevoMailer();
            $subject = 'Invitación a administrar SaborYa 🍽️';
            
            $htmlBody = <<<HTML
<!DOCTYPE html>
<html>
<body style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#f9f9f9">
  <div style="background:#FF6B35;padding:24px;text-align:center">
    <h1 style="color:white;margin:0;font-size:24px">🍽️ SaborYa Admin</h1>
  </div>
  <div style="padding:32px 24px;background:white">
    <h2 style="color:#2C3E50;margin:0 0 16px">¡Hola, {$name}! 👋</h2>
    <p style="color:#555;line-height:1.6;margin:0 0 24px">
        Has sido invitado a administrar <strong>SaborYa</strong>.<br>
        Para activar tu cuenta y establecer tu contraseña, haz clic en el botón:
    </p>
    <p style="text-align:center;margin:32px 0">
      <a href="{$inviteLink}" style="background:#FF6B35;color:white;padding:14px 28px;border-radius:8px;text-decoration:none;font-weight:bold;font-size:16px;display:inline-block">
        Activar mi cuenta →
      </a>
    </p>
    <p style="color:#666;font-size:14px;margin:24px 0 0">
        🔒 Este enlace expira en <strong>24 horas</strong><br>
        📧 Si no solicitaste esta invitación, ignora este email.
    </p>
  </div>
  <div style="background:#f9f9f9;padding:16px 24px;text-align:center;font-size:12px;color:#999">
    © 2026 SaborYa • Hecho con 🍕 en Chile
  </div>
</body>
</html>
HTML;
            
            $textContent = "Hola {$name},\n\nActiva tu cuenta de administrador de SaborYa aquí:\n{$inviteLink}\n\nExpira en 24 horas.\n\nSi no solicitaste esto, ignora este email.";
            
            $emailSent = $mailer->sendTransactional($email, $subject, $htmlBody, $textContent);
            error_log("INVITE_API_DEBUG: Brevo sendTransactional returned: " . ($emailSent ? 'true' : 'false'));
            
        } catch (Exception $e) {
            $emailError = $e->getMessage();
            error_log("INVITE_API_DEBUG: Brevo exception - " . $emailError);
        }
    } else {
        $emailError = 'BrevoMailer class not loaded';
        error_log("INVITE_API_DEBUG: {$emailError}");
    }

    // ============================================================
    // 10. Log de auditoría (no fallar si esto falla)
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'], 
            'admin_invite_sent', 
            'users', 
            $userId, 
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        error_log("INVITE_API_DEBUG: Audit log created");
    } catch (Exception $e) {
        error_log("INVITE_API_DEBUG: Audit log failed - " . $e->getMessage());
        // No propagar error: el log es opcional
    }

    // ============================================================
    // 11. Respuesta exitosa
    $maskedEmail = maskEmail($email);
    
    echo json_encode([
        'success' => true,
        'message' => $emailSent 
            ? 'Invitación enviada correctamente' 
            : 'Invitación creada (revisa logs si el email no llegó)',
        'email' => $maskedEmail,
        'expires_in' => '24 horas',
        'debug' => APP_ENV === 'development' ? [
            'email_sent' => $emailSent,
            'email_error' => $emailError,
            'invite_link' => $inviteLink
        ] : null
    ]);
    
    error_log("INVITE_API_DEBUG: Success response sent");

} catch (Exception $e) {
    // ============================================================
    // 12. Manejo de errores: log detallado + respuesta segura
    $errorMsg = "INVITE_API CRASH: " . $e->getMessage() . 
                " | File: " . $e->getFile() . 
                " | Line: " . $e->getLine() .
                " | Trace: " . $e->getTraceAsString();
    
    error_log($errorMsg);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno al procesar invitación',
        'details' => APP_ENV === 'development' ? $e->getMessage() : null,
        'debug_trace' => APP_ENV === 'development' ? [
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ]);
}

// ============================================================
// Helpers locales (solo para este archivo)
function maskEmail(string $email): string {
    if (!str_contains($email, '@')) return '***';
    [$user, $domain] = explode('@', $email, 2);
    if (strlen($user) <= 2) return '***@' . $domain;
    return $user[0] . str_repeat('*', strlen($user) - 2) . $user[-1] . '@' . $domain;
}