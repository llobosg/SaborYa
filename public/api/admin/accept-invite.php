<?php
// public/admin/accept-invite.php - Página para aceptar invitación de admin
require_once __DIR__ . '/../config/config.php';

$token = $_GET['token'] ?? '';
$error = null;
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Procesar aceptación
    $inputToken = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf($csrfToken)) {
        $error = 'Token CSRF inválido';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Las contraseñas no coinciden';
    } elseif (!$inputToken) {
        $error = 'Token de invitación faltante';
    } else {
        try {
            $pdo = getPDO();
            
            // Validar token
            $stmt = $pdo->prepare("SELECT id, name, email, invite_expires_at FROM users WHERE invite_token = ? AND is_active = 0");
            $stmt->execute([$inputToken]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'Invitación no válida o ya utilizada';
            } elseif (strtotime($user['invite_expires_at']) < time()) {
                $error = 'Esta invitación ha expirado';
            } else {
                // Activar usuario con password
                $stmt = $pdo->prepare("UPDATE users SET 
                    password_hash = ?, 
                    invite_token = NULL, 
                    invite_expires_at = NULL, 
                    is_active = 1, 
                    verified_at = NOW(),
                    updated_at = NOW()
                    WHERE id = ?");
                $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
                
                // Iniciar sesión automáticamente
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_role'] = 'admin';
                $_SESSION['last_activity'] = time();
                
                $success = true;
                
                // Log
                log_audit($user['id'], 'admin_invite_accepted', 'users', $user['id'], $_SERVER['REMOTE_ADDR'] ?? '');
                
                // Redirect después de 2 segundos
                header("Refresh: 2; url=/admin/dashboard");
            }
        } catch (Exception $e) {
            error_log("Accept invite error: " . $e->getMessage());
            $error = 'Error al procesar la invitación';
        }
    }
} elseif ($token) {
    // Validar token en GET (para mostrar la página)
    try {
        $pdo = getPDO();
        $stmt = $pdo->prepare("SELECT name, email, invite_expires_at FROM users WHERE invite_token = ? AND is_active = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = 'Invitación no válida';
        } elseif (strtotime($user['invite_expires_at']) < time()) {
            $error = 'Esta invitación ha expirado';
        }
    } catch (Exception $e) {
        $error = 'Error al validar invitación';
    }
} else {
    $error = 'Token de invitación requerido';
}

// Si hay error o éxito, mostrar mensaje; si no, mostrar formulario
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#FF6B35">
    <title>Activar Cuenta Admin - SaborYa</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        .password-wrapper { position: relative; }
        .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--color-text-secondary); }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="logo-header">
            <div class="logo-bubble">
                <span class="logo-icon">👨‍🍳</span>
                <h1>SaborYa Admin</h1>
            </div>
            <p class="logo-subtitle">Activa tu cuenta para comenzar</p>
        </div>

        <div class="card auth-card">
            <?php if ($error): ?>
                <div class="toast toast--error" style="margin-bottom:16px">❌ <?= h($error) ?></div>
            <?php elseif ($success): ?>
                <div class="toast toast--success" style="margin-bottom:16px">✅ ¡Cuenta activada! Redirigiendo...</div>
                <p style="text-align:center">Serás redirigido al panel de administración en unos segundos...</p>
                <p style="text-align:center"><a href="/admin/dashboard">Ir ahora →</a></p>
            <?php else: ?>
                <h2 class="card-title">¡Bienvenido, <?= h($user['name'] ?? 'Admin') ?>! 👋</h2>
                <p class="card-subtitle">Establece tu contraseña para acceder al panel de administración</p>
                
                <form method="POST" onsubmit="return validateForm(this)" novalidate>
                    <input type="hidden" name="token" value="<?= h($token) ?>">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    
                    <div class="input-group">
                        <label for="email">Correo</label>
                        <input type="email" value="<?= h($user['email'] ?? '') ?>" disabled class="input-field">
                        <small class="input-help">Este es tu usuario para iniciar sesión</small>
                    </div>
                    
                    <div class="input-group">
                        <label for="password">Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" id="password" name="password" required minlength="6" class="input-field" placeholder="Mínimo 6 caracteres">
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">👁️</button>
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label for="password_confirm">Confirmar contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" id="password_confirm" name="password_confirm" required minlength="6" class="input-field" placeholder="Repite tu contraseña">
                            <button type="button" class="toggle-password" onclick="togglePassword('password_confirm')">👁️</button>
                        </div>
                        <small id="match-error" style="color:var(--color-error);font-size:0.75rem;display:none">❌ Las contraseñas no coinciden</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Activar mi cuenta →</button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function togglePassword(id) {
        const input = document.getElementById(id);
        input.type = input.type === 'password' ? 'text' : 'password';
    }
    
    function validateForm(form) {
        const p1 = form.password.value;
        const p2 = form.password_confirm.value;
        const errorEl = document.getElementById('match-error');
        
        if (p1 !== p2) {
            errorEl.style.display = 'block';
            return false;
        }
        errorEl.style.display = 'none';
        return true;
    }
    
    // Validación en tiempo real
    ['password', 'password_confirm'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', function() {
            const p1 = document.getElementById('password')?.value || '';
            const p2 = document.getElementById('password_confirm')?.value || '';
            const errorEl = document.getElementById('match-error');
            if (p2.length > 0 && p1 !== p2) {
                errorEl.style.display = 'block';
            } else {
                errorEl.style.display = 'none';
            }
        });
    });
    </script>
</body>
</html>