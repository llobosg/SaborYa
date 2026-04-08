<?php
// src/Auth/LoginHandler.php

function login_handler() {
    // 1. Verificar método y CSRF
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(400);
        echo json_encode(['error' => 'Solicitud inválida']);
        return;
    }
    
    // 2. Sanitizar inputs
    $email = filter_var(sanitize_input($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    
    if (!$email || strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Email o contraseña inválidos']);
        return;
    }
    
    // 3. Rate limiting (previene brute force)
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'];
    $cacheKey = "login_attempts:$ip";
    
    // Usar Redis si está disponible, fallback a archivo
    $attempts = 0; // Implementar con Redis o DB
    if ($attempts >= MAX_LOGIN_ATTEMPTS) {
        http_response_code(429);
        echo json_encode(['error' => 'Demasiados intentos. Intenta en ' . (LOCKOUT_TIME/60) . ' minutos']);
        return;
    }
    
    // 4. Consultar usuario
    $pdo = getPDO();
    $stmt = $pdo->prepare("SELECT id, name, email, password_hash, role, verified_at 
                          FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // 5. Verificar contraseña (usar password_verify con hash seguro)
    if (!$user || !password_verify($password, $user['password_hash'])) {
        // Incrementar intentos
        // register_login_attempt($ip);
        http_response_code(401);
        echo json_encode(['error' => 'Credenciales incorrectas']);
        return;
    }
    
    // 6. Verificar email confirmado
    if (!$user['verified_at']) {
        http_response_code(403);
        echo json_encode(['error' => 'Confirma tu email para continuar']);
        return;
    }
    
    // 7. Login exitoso - Crear sesión segura
    session_regenerate_id(true); // Prevenir session fixation
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    
    // 8. Log de auditoría
    log_audit($user['id'], 'login_success', null, null, $ip);
    
    // 9. Resetear intentos fallidos
    // reset_login_attempts($ip);
    
    // 10. Redirigir según rol
    $redirect = $user['role'] === 'admin' ? '/admin' : '/home';
    echo json_encode(['success' => true, 'redirect' => $redirect]);
}

// Función helper para logging
function log_audit($userId, $action, $table, $recordId, $ip) {
    $pdo = getPDO();
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, table_name, record_id, ip_address, user_agent) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $userId, $action, $table, $recordId, $ip, 
        $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
}