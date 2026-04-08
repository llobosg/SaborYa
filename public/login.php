<?php
// public/login.php - Formulario de login (render)
require_once __DIR__ . '/../config/config.php';

// Si ya está logueado, redirigir según rol
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? 'consumer';
    redirect($role === 'admin' ? '/admin' : '/home');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - <?= h(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="manifest" href="/manifest.json">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <img src="/assets/images/logo.svg" alt="<?= h(APP_NAME) ?>" class="login-logo">
                <h1>¡Bienvenido de vuelta! 👋</h1>
                <p>Ingresa para continuar con tu pedido</p>
            </div>
            
            <?php if (!empty($_GET['error'])): ?>
                <div class="toast toast--error" role="alert">
                    <span>❌</span>
                    <span><?= h($_GET['error'] === 'invalid' ? 'Credenciales incorrectas' : 'Ha ocurrido un error') ?></span>
                </div>
            <?php endif; ?>
            
            <form action="/api/auth/login" method="POST" class="login-form" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="form-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="tu@email.com" autocomplete="email">
                </div>
                
                <div class="form-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="••••••••" autocomplete="current-password">
                    <button type="button" class="toggle-password" aria-label="Mostrar contraseña">👁️</button>
                </div>
                
                <div class="form-options">
                    <label class="checkbox">
                        <input type="checkbox" name="remember">
                        <span>Recordarme</span>
                    </label>
                    <a href="/recuperar-password" class="link-forgot">¿Olvidaste tu contraseña?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block">
                    Iniciar Sesión
                </button>
            </form>
            
            <div class="login-footer">
                <p>¿No tienes cuenta? <a href="/registro.php" class="link-register">Regístrate aquí</a></p>
            </div>
        </div>
    </div>
    
    <script src="/assets/js/app.js"></script>
    <script>
        // Toggle password visibility
        document.querySelector('.toggle-password')?.addEventListener('click', function() {
            const input = document.getElementById('password');
            input.type = input.type === 'password' ? 'text' : 'password';
            this.textContent = input.type === 'password' ? '👁️' : '🙈';
        });
    </script>
</body>
</html>