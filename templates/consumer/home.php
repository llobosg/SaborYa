<?php
// templates/consumer/home.php - Onboarding post-registro (MEJORADO)

// Verificar autenticación
if (empty($_SESSION['user_id']) || !$_SESSION['verified']) {
    redirect('/registro.php');
}

$userEmail = $_SESSION['user_email'] ?? '';
$csrfToken = csrf_token();
$userName = $_SESSION['user_name'] ?? explode('@', $userEmail)[0];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FF6B35">
    <title>Completa tu Perfil - SaborYa 🍽️</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        /* Toggle password styles */
        .password-wrapper { position: relative; }
        .toggle-password {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; cursor: pointer; padding: 4px;
            color: var(--color-text-secondary); font-size: 1.2rem;
            display: flex; align-items: center; justify-content: center;
        }
        .toggle-password:hover { color: var(--color-primary); }
        .password-strength { font-size: 0.75rem; margin-top: 4px; min-height: 16px; }
        .strength-weak { color: var(--color-error); }
        .strength-medium { color: var(--color-warning); }
        .strength-strong { color: var(--color-success); }
        .match-error { color: var(--color-error); font-size: 0.75rem; margin-top: 4px; display: none; }
        .match-error.show { display: block; }
        .checkbox-row { display: flex; align-items: center; gap: 8px; color: var(--color-text-secondary); font-size: 0.9rem; cursor: pointer; }
        .checkbox-row input { width: 18px; height: 18px; accent-color: var(--color-primary); cursor: pointer; }

        /* Animación suave para toggle */
        .toggle-password { transition: transform 0.2s, color 0.2s; }
        .toggle-password:active { transform: translateY(-50%) scale(0.9); }

        /* Efecto focus en inputs de password */
        .password-wrapper:focus-within {
            box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.2);
            border-radius: var(--border-radius);
        }

        /* Animación para password strength */
        .password-strength {
            transition: color 0.3s ease;
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="app-container">
        
        <!-- Header de Bienvenida -->
        <div class="logo-header">
            <div class="logo-bubble">
                <span class="logo-icon">🎉</span>
                <h1>¡Hola, <?= h($userName) ?>!</h1>
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
                
                <!-- Contraseña + Toggle 👁️ -->
                <div class="input-group">
                    <label for="password">Crea tu contraseña 🔐</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" required 
                               placeholder="Mínimo 6 caracteres" minlength="6"
                               class="input-field" pattern=".{6,}" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggle-password" aria-label="Mostrar contraseña">👁️</button>
                    </div>
                    <small class="input-help">La usarás para iniciar sesión en el futuro</small>
                    <div id="password-strength" class="password-strength"></div>
                </div>
                
                <!-- Confirmar Contraseña ✅ -->
                <div class="input-group">
                    <label for="password-confirm">Confirma tu contraseña 🔁</label>
                    <div class="password-wrapper">
                        <input type="password" id="password-confirm" name="password_confirm" required 
                               placeholder="Repite tu contraseña" minlength="6"
                               class="input-field" pattern=".{6,}" autocomplete="new-password">
                        <button type="button" class="toggle-password" id="toggle-confirm" aria-label="Mostrar contraseña">👁️</button>
                    </div>
                    <small id="match-error" class="match-error">❌ Las contraseñas no coinciden</small>
                </div>
                
                <!-- Teléfono -->
                <div class="input-group">
                    <label for="phone">Teléfono de contacto 📱</label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="+56 9 1234 5678" pattern="[0-9+\s()\-]{9,}"
                           class="input-field">
                    <small class="input-help">Para avisarte cuando tu pedido esté listo</small>
                </div>
                
                <!-- Dirección -->
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
    // ===== Toggle Password Visibility =====
    function setupPasswordToggle(inputId, toggleId) {
        const input = document.getElementById(inputId);
        const toggle = document.getElementById(toggleId);
        if (!input || !toggle) return;
        
        toggle.addEventListener('click', () => {
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            toggle.textContent = isPassword ? '🙈' : '👁️';
            toggle.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
        });
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        setupPasswordToggle('password', 'toggle-password');
        setupPasswordToggle('password-confirm', 'toggle-confirm');
    });
    
    // ===== Password Strength Meter =====
    document.getElementById('password')?.addEventListener('input', function() {
        const val = this.value;
        const strengthEl = document.getElementById('password-strength');
        if (!strengthEl) return;
        
        let score = 0;
        if (val.length >= 6) score++;
        if (val.length >= 10) score++;
        if (/[A-Z]/.test(val)) score++;
        if (/[0-9]/.test(val)) score++;
        if (/[^A-Za-z0-9]/.test(val)) score++;
        
        if (val.length === 0) {
            strengthEl.textContent = '';
            strengthEl.className = 'password-strength';
        } else if (score <= 2) {
            strengthEl.textContent = '🔴 Débil: usa mayúsculas, números o símbolos';
            strengthEl.className = 'password-strength strength-weak';
        } else if (score <= 4) {
            strengthEl.textContent = '🟡 Media: buena, pero puedes mejorarla';
            strengthEl.className = 'password-strength strength-medium';
        } else {
            strengthEl.textContent = '🟢 ¡Fuerte! Contraseña segura ✅';
            strengthEl.className = 'password-strength strength-strong';
        }
    });
    
    // ===== Password Match Validation =====
    function checkPasswordMatch() {
        const p1 = document.getElementById('password')?.value || '';
        const p2 = document.getElementById('password-confirm')?.value || '';
        const errorEl = document.getElementById('match-error');
        const confirmInput = document.getElementById('password-confirm');
        
        if (!errorEl || !confirmInput) return;
        
        if (p2.length > 0 && p1 !== p2) {
            errorEl.classList.add('show');
            confirmInput.style.borderColor = 'var(--color-error)';
            return false;
        } else {
            errorEl.classList.remove('show');
            confirmInput.style.borderColor = '';
            return true;
        }
    }
    
    ['password', 'password-confirm'].forEach(id => {
        document.getElementById(id)?.addEventListener('input', checkPasswordMatch);
    });
    
    // ===== Submit Handler =====
    window.completeProfile = async function(e) {
        e.preventDefault();
        
        // Validar match antes de enviar
        if (!checkPasswordMatch()) {
            showToast('Las contraseñas no coinciden ❌', 'error');
            document.getElementById('password-confirm')?.focus();
            return;
        }
        
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
    
    // Skip onboarding
    window.skipOnboarding = function(e) {
        e.preventDefault();
        showToast('Puedes completar tu perfil después en Configuración ⚙️', 'info');
        setTimeout(() => { window.location.href = '/catalogo'; }, 1500);
    };
    
    // Toast helper
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
    </script>
</body>
</html>