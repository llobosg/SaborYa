<?php
// public/registro.php - Registro con layout mobile/PWA estilo SaborYa
require_once __DIR__ . '/../config/config.php';

// Si ya está logueado, redirigir
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
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Registro - SaborYa 🍽️</title>
    
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <link rel="apple-touch-icon" href="/assets/images/icons/icon-192x192.png">
    <link rel="manifest" href="/manifest.json">
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
    <div class="app-container">
        
        <!-- Logo Header -->
        <div class="logo-header">
            <div class="logo-bubble">
                <span class="logo-icon">🍽️</span>
                <h1>SaborYa</h1>
            </div>
            <p class="logo-subtitle">Alimentos preparados, delivered con amor</p>
        </div>

        <!-- Paso 1: Ingresar Email -->
        <div id="step1-form" class="card auth-card">
            <h2 class="card-title">Crear cuenta ✨</h2>
            <p class="card-subtitle">Regístrate en 30 segundos y comienza a pedir</p>
            
            <form onsubmit="sendCode(event)" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                
                <div class="input-group">
                    <label for="email">Correo electrónico</label>
                    <input type="email" id="email" name="email" required 
                           placeholder="tu@email.com" autocomplete="email"
                           class="input-field" pattern="[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$">
                    <small class="input-help">Te enviaremos un código de verificación 🔐</small>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" id="send-code-btn">
                    <span class="btn-text">Enviar código</span>
                    <span class="btn-loading" style="display:none">
                        <span class="spinner"></span> Enviando...
                    </span>
                </button>
            </form>
            
            <p class="auth-footer">
                ¿Ya tienes cuenta? <a href="/login.php" class="link-auth">Inicia sesión</a>
            </p>
        </div>

        <!-- Paso 2: Verificar Código -->
        <div id="step2-form" class="card auth-card hidden">
            <h2 class="card-title">Verificar correo 📧</h2>
            <p class="card-subtitle">Ingresa el código de 6 dígitos enviado a <span id="masked-email" class="highlight"></span></p>
            
            <form onsubmit="verifyCode(event)" novalidate>
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" id="verify-email" name="email">
                
                <div class="input-group">
                    <label for="code">Código de verificación</label>
                    <input type="text" id="code" name="code" required 
                           placeholder="000000" maxlength="6"
                           pattern="[0-9]{6}" inputmode="numeric"
                           class="input-field code-input" autocomplete="one-time-code">
                </div>
                
                <div class="input-group">
                    <label for="name">Tu nombre (opcional)</label>
                    <input type="text" id="name" name="name" 
                           placeholder="¿Cómo te llamamos?" maxlength="50"
                           class="input-field">
                </div>
                
                <button type="submit" class="btn btn-primary btn-block" id="verify-btn">
                    <span class="btn-text">Verificar y continuar</span>
                    <span class="btn-loading" style="display:none">
                        <span class="spinner"></span> Verificando...
                    </span>
                </button>
            </form>
            
            <div class="auth-footer">
                <button type="button" id="resend-code" class="link-auth">¿No llegó el código? Reenviar</button>
                <span id="resend-timer" class="timer-text" style="display:none"></span>
            </div>
        </div>

        <!-- Toast Container -->
        <div id="toast-container" class="toast-container"></div>
        
    </div>

    <script src="/assets/js/auth.js"></script>
</body>
</html>