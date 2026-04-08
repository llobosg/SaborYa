<?php
// public/registro.php
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
    <title>Registro - SaborYa</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <meta name="theme-color" content="#FF6B35">
</head>
<body class="register-page">
    <div class="auth-container">
        <div class="auth-header">
            <a href="/" class="logo-link">
                <img src="/assets/images/logo.svg" alt="SaborYa" class="logo" onerror="this.parentElement.innerHTML='🍽️ SaborYa'">
            </a>
            <h1>Crear cuenta 🍽️</h1>
            <p>Regístrate en 30 segundos y comienza a pedir</p>
        </div>
        
        <!-- Paso 1: Email -->
        <form id="step1-form" class="auth-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" required placeholder="tu@email.com" autocomplete="email">
                <small class="form-help">Te enviaremos un código de verificación</small>
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="send-code-btn">
                <span class="btn-text">Enviar código</span>
                <span class="btn-loading" style="display:none">Enviando...</span>
            </button>
        </form>
        
        <!-- Paso 2: Código -->
        <form id="step2-form" class="auth-form" style="display:none" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" id="verify-email" name="email">
            
            <div class="form-group">
                <label>Revisa tu correo 📧</label>
                <p class="form-help">Enviamos un código a <strong id="masked-email"></strong></p>
            </div>
            
            <div class="form-group">
                <label for="code">Código de verificación</label>
                <input type="text" id="code" name="code" required placeholder="000000" maxlength="6" 
                       pattern="[0-9]{6}" inputmode="numeric" 
                       style="text-align:center;font-size:24px;letter-spacing:8px">
            </div>
            
            <div class="form-group">
                <label for="name">Tu nombre (opcional)</label>
                <input type="text" id="name" name="name" placeholder="¿Cómo te llamamos?" maxlength="50">
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-lg" id="verify-btn">
                <span class="btn-text">Verificar y continuar</span>
                <span class="btn-loading" style="display:none">Verificando...</span>
            </button>
            
            <div class="form-footer">
                <button type="button" id="resend-code" class="link-button">¿No llegó el código? Reenviar</button>
                <span id="resend-timer" style="display:none;color:#666"></span>
            </div>
        </form>
        
        <div class="auth-footer">
            <p>¿Ya tienes cuenta? <a href="/login.php" class="link-login">Inicia sesión</a></p>
        </div>
    </div>
    
    <div id="toast-container" class="toast-container"></div>
    <script src="/assets/js/auth.js"></script>
</body>
</html>