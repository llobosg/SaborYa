<?php
// public/login.php - Login con layout mobile/PWA
require_once __DIR__ . '/../config/config.php';

if (!empty($_SESSION['user_id'])) {
    redirect($_SESSION['user_role'] === 'admin' ? '/admin' : '/home');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FF6B35">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title>Login - SaborYa 🍽️</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/assets/images/icons/icon-192x192.png">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
    <div class="app-container">
        
        <div class="logo-header">
            <div class="logo-bubble">
                <span class="logo-icon">🍽️</span>
                <h1>SaborYa</h1>
            </div>
            <p class="logo-subtitle">¡Bienvenido de vuelta! 👋</p>
        </div>

        <div class="card auth-card">
            <h2 class="card-title">Iniciar sesión</h2>
            <p class="card-subtitle">Ingresa tus credenciales para continuar</p>
            
            <form onsubmit="handleLogin(event)" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="input-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="tu@email.com" autocomplete="email"
                           class="input-field">
                </div>
                
                <div class="input-group">
                    <label for="password">Contraseña</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="••••••••" autocomplete="current-password"
                           class="input-field">
                </div>
                
                <div style="text-align: right; margin-bottom: var(--spacing-lg);">
                    <a href="/recuperar-password" class="link-auth" style="font-size: 0.9rem;">¿Olvidaste tu contraseña?</a>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" id="login-btn">
                    <span class="btn-text">Ingresar</span>
                    <span class="btn-loading" style="display:none">
                        <span class="spinner"></span> Verificando...
                    </span>
                </button>
            </form>
            
            <p class="auth-footer">
                ¿No tienes cuenta? <a href="/registro.php" class="link-auth">Regístrate gratis</a>
            </p>
        </div>
        
        <div id="toast-container" class="toast-container"></div>
        
    </div>

    <script>
    // Login simple con mismo estilo de Toasts
    window.handleLogin = async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value.trim();
        const password = document.getElementById('password').value;
        const csrfToken = document.querySelector('[name="csrf_token"]').value;
        const btn = document.getElementById('login-btn');
        
        if (!email || password.length < 6) {
            showToast('Completa todos los campos', 'error');
            return;
        }
        
        // Loading state
        btn.disabled = true;
        btn.querySelector('.btn-text').style.display = 'none';
        btn.querySelector('.btn-loading').style.display = 'inline-flex';
        
        try {
            const response = await fetch('/api/auth/login', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ email, password, csrf_token: csrfToken })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Credenciales incorrectas');
            }
            
            showToast('¡Bienvenido! 🎉', 'success');
            setTimeout(() => {
                window.location.href = data.redirect || '/home';
            }, 1000);
            
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display = 'inline';
            btn.querySelector('.btn-loading').style.display = 'none';
        }
    };
    
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `<span class="toast-icon">${type==='success'?'✅':'❌'}</span><span class="toast-message">${message}</span>`;
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }
    </script>
</body>
</html>