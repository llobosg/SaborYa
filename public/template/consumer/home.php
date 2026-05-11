<?php
// templates/consumer/home.php - Onboarding post-registro

// Verificar autenticación
if (empty($_SESSION['user_id']) || !$_SESSION['verified']) {
    redirect('/registro.php');
}

$userEmail = $_SESSION['user_email'] ?? '';
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FF6B35">
    <title>Completa tu Perfil - SaborYa 🍽️</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
    <div class="app-container">
        
        <!-- Header de Bienvenida -->
        <div class="logo-header">
            <div class="logo-bubble">
                <span class="logo-icon">🎉</span>
                <h1>¡Bienvenido!</h1>
            </div>
            <p class="logo-subtitle">Casi estás listo para pedir en SaborYa</p>
        </div>

        <!-- Card de Onboarding -->
        <div class="card auth-card">
            <h2 class="card-title">Completa tu perfil ✨</h2>
            <p class="card-subtitle">Estos datos nos ayudarán a entregarte una mejor experiencia</p>
            
            <form onsubmit="completeProfile(event)" novalidate>
                <input type="hidden" name="csrf_token" value="<?= h($csrfToken) ?>">
                <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
                
                <!-- Contraseña (obligatorio) -->
                <div class="input-group">
                    <label for="password">Crea tu contraseña 🔐</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="Mínimo 6 caracteres" minlength="6"
                           class="input-field" pattern=".{6,}">
                    <small class="input-help">La usarás para iniciar sesión en el futuro</small>
                </div>
                
                <!-- Teléfono (opcional pero recomendado) -->
                <div class="input-group">
                    <label for="phone">Teléfono de contacto 📱</label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="+56 9 1234 5678" pattern="[0-9+\s()\-]{9,}"
                           class="input-field">
                    <small class="input-help">Para avisarte cuando tu pedido esté listo</small>
                </div>
                
                <!-- Dirección de entrega -->
                <div class="input-group">
                    <label for="address">Dirección de entrega 🏠</label>
                    <textarea id="address" name="address" rows="2" 
                              placeholder="Calle, número, comuna, referencia"
                              class="input-field" style="resize:vertical"></textarea>
                </div>
                
                <!-- Preferencias de notificación -->
                <div class="input-group">
                    <label>¿Cómo quieres recibir novedades? 🔔</label>
                    <div style="display:flex; flex-direction:column; gap:8px; margin-top:8px">
                        <label class="checkbox-row">
                            <input type="checkbox" name="notify_email" value="1" checked>
                            <span>📧 Correo electrónico</span>
                        </label>
                        <label class="checkbox-row">
                            <input type="checkbox" name="notify_whatsapp" value="1">
                            <span>💬 WhatsApp (opcional)</span>
                        </label>
                        <label class="checkbox-row">
                            <input type="checkbox" name="notify_push" value="1" checked>
                            <span>🔔 Notificaciones push</span>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="complete-btn">
                    <span class="btn-text">¡Listo, vamos a pedir! 🍕</span>
                    <span class="btn-loading" style="display:none">
                        <span class="spinner"></span> Guardando...
                    </span>
                </button>
            </form>
            
            <p class="auth-footer" style="margin-top:16px">
                <a href="#" onclick="skipOnboarding(event)" class="link-auth">Prefiero completar después</a>
            </p>
        </div>
        
        <div id="toast-container" class="toast-container"></div>
        
    </div>
    
    <script>
    // Lógica de onboarding
    window.completeProfile = async function(e) {
        e.preventDefault();
        
        const form = e.target;
        const password = document.getElementById('password').value;
        const btn = document.getElementById('complete-btn');
        
        if (password.length < 6) {
            showToast('La contraseña debe tener al menos 6 caracteres', 'error');
            return;
        }
        
        // Loading state
        btn.disabled = true;
        btn.querySelector('.btn-text').style.display = 'none';
        btn.querySelector('.btn-loading').style.display = 'inline-flex';
        
        try {
            const formData = new FormData(form);
            const data = Object.fromEntries(formData.entries());
            
            const response = await fetch('/api/consumer/complete-profile.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': data.csrf_token
                },
                credentials: 'include',
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || 'Error al guardar perfil');
            }
            
            showToast('¡Perfil completado! 🎉', 'success');
            
            // Redirect a catálogo después de 1.5s
            setTimeout(() => {
                window.location.href = '/catalogo';
            }, 1500);
            
        } catch (error) {
            console.error('Complete profile error:', error);
            showToast(error.message, 'error');
            btn.disabled = false;
            btn.querySelector('.btn-text').style.display = 'inline';
            btn.querySelector('.btn-loading').style.display = 'none';
        }
    };
    
    // Saltar onboarding (redirigir igual pero sin guardar preferencias)
    window.skipOnboarding = function(e) {
        e.preventDefault();
        showToast('Puedes completar tu perfil después en Configuración ⚙️', 'info');
        setTimeout(() => {
            window.location.href = '/catalogo';
        }, 1500);
    };
    
    // Toast helper (mismo estilo que auth.js)
    function showToast(message, type = 'info') {
        const container = document.getElementById('toast-container');
        if (!container) return;
        
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `<span class="toast-icon">${type==='success'?'✅':'❌'}</span><span class="toast-message">${message}</span>`;
        container.appendChild(toast);
        
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }
    
    // CSS para checkbox rows
    const style = document.createElement('style');
    style.textContent = `
        .checkbox-row {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--color-text-secondary);
            font-size: 0.9rem;
            cursor: pointer;
        }
        .checkbox-row input {
            width: 18px;
            height: 18px;
            accent-color: var(--color-primary);
            cursor: pointer;
        }
    `;
    document.head.appendChild(style);
    // Auto-focus en password al cargar
    document.addEventListener('DOMContentLoaded', () => {
        document.getElementById('password')?.focus();
        
        // Validación en tiempo real de contraseña
        const passwordInput = document.getElementById('password');
        const passwordHelp = document.createElement('small');
        passwordHelp.className = 'input-help';
        passwordHelp.style.color = 'var(--color-warning)';
        passwordInput.parentNode.appendChild(passwordHelp);
        
        passwordInput.addEventListener('input', function() {
            const len = this.value.length;
            if (len === 0) {
                passwordHelp.textContent = '';
            } else if (len < 6) {
                passwordHelp.textContent = `⚠️ Faltan ${6-len} caracteres`;
                this.style.borderColor = 'var(--color-warning)';
            } else {
                passwordHelp.textContent = '✅ Contraseña segura';
                passwordHelp.style.color = 'var(--color-success)';
                this.style.borderColor = 'var(--color-success)';
            }
        });
    });
    </script>
</body>
</html>