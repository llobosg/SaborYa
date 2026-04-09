// public/assets/js/auth.js - Lógica de registro con UX mejorada

document.addEventListener('DOMContentLoaded', () => {
    const step1Form = document.getElementById('step1-form');
    const step2Form = document.getElementById('step2-form');
    const sendCodeBtn = document.getElementById('send-code-btn');
    const verifyBtn = document.getElementById('verify-btn');
    const resendBtn = document.getElementById('resend-code');
    const resendTimer = document.getElementById('resend-timer');
    
    let currentEmail = '';
    let resendCountdown = 0;
    
    // ===== Paso 1: Enviar código =====
    window.sendCode = async function(e) {
        e.preventDefault();
        
        const email = document.getElementById('email').value.trim();
        const csrfToken = document.querySelector('[name="csrf_token"]').value;
        
        if (!validateEmail(email)) {
            showToast('Ingresa un email válido 📧', 'error');
            document.getElementById('email').focus();
            return;
        }
        
        // UI: Loading state
        setLoading(sendCodeBtn, true);
        
        try {
            const response = await fetch('/api/auth/send-code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken  // ← Mantener este header también
                },
                credentials: 'include',  // ✅ AGREGAR ESTA LÍNEA (envía cookies de sesión)
                body: JSON.stringify({ email, csrf_token: csrfToken })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Error al enviar código');
            }
            
            // Éxito: guardar email y mostrar paso 2
            currentEmail = email;
            document.getElementById('verify-email').value = email;
            document.getElementById('masked-email').textContent = data.email_masked;
            
            // Transición animada entre pasos
            step1Form.classList.add('hidden');
            step2Form.classList.remove('hidden');
            
            showToast('¡Código enviado! Revisa tu correo ✨', 'success');
            
            // Auto-focus en input de código
            setTimeout(() => {
                document.getElementById('code')?.focus();
            }, 300);
            
            // Iniciar countdown para reenvío
            startResendTimer(60);
            
        } catch (error) {
            console.error('Send code error:', error);
            showToast(error.message || 'Error de conexión', 'error');
        } finally {
            setLoading(sendCodeBtn, false);
        }
    };
    
    // ===== Paso 2: Verificar código =====
    window.verifyCode = async function(e) {
        e.preventDefault();
        
        const code = document.getElementById('code').value.trim();
        const name = document.getElementById('name').value.trim();
        const csrfToken = step2Form.querySelector('[name="csrf_token"]').value;
        
        if (!/^\d{6}$/.test(code)) {
            showToast('Ingresa el código de 6 dígitos 🔢', 'error');
            document.getElementById('code').focus();
            return;
        }
        
        setLoading(verifyBtn, true);
        
        try {
            const response = await fetch('/api/auth/verify-code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'include',  // ✅ AGREGAR
                body: JSON.stringify({
                    email: currentEmail,
                    code,
                    name: name || null,
                    csrf_token: csrfToken
                })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || data.message || 'Código inválido');
            }
            
            showToast('¡Bienvenido a SaborYa! 🍕✨', 'success');
            
            // Redirect con breve delay para mostrar toast
            setTimeout(() => {
                window.location.href = data.redirect || '/home';
            }, 1500);
            
        } catch (error) {
            console.error('Verify code error:', error);
            showToast(error.message, 'error');
            // Enfocar y seleccionar código para reintentar rápido
            const codeInput = document.getElementById('code');
            codeInput?.focus();
            codeInput?.select();
        } finally {
            setLoading(verifyBtn, false);
        }
    };
    
    // ===== Reenviar código =====
    resendBtn?.addEventListener('click', async () => {
        if (resendCountdown > 0) return;
        
        const csrfToken = step2Form.querySelector('[name="csrf_token"]').value;
        
        setLoading(resendBtn, true);
        resendBtn.disabled = true;
        
        try {
            const response = await fetch('/api/auth/send-code.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                credentials: 'include',  // ✅ AGREGAR
                body: JSON.stringify({ email: currentEmail, csrf_token: csrfToken })
            });
            
            const data = await response.json();
            
            if (!response.ok) {
                throw new Error(data.error || 'Error al reenviar');
            }
            
            showToast('Nuevo código enviado 📧✨', 'success');
            startResendTimer(60);
            
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            setLoading(resendBtn, false);
            resendBtn.disabled = false;
        }
    });
    
    // ===== Helpers =====
    
    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    function setLoading(button, loading) {
        if (!button) return;
        const text = button.querySelector('.btn-text');
        const loader = button.querySelector('.btn-loading');
        
        if (loading) {
            button.disabled = true;
            if (text) text.style.display = 'none';
            if (loader) loader.style.display = 'inline-flex';
        } else {
            button.disabled = false;
            if (text) text.style.display = 'inline';
            if (loader) loader.style.display = 'none';
        }
    }
    
    function startResendTimer(seconds) {
        resendCountdown = seconds;
        resendBtn.style.display = 'none';
        resendTimer.style.display = 'block';
        
        const updateTimer = () => {
            if (resendCountdown <= 0) {
                resendTimer.style.display = 'none';
                resendBtn.style.display = 'inline';
                return;
            }
            
            const mins = Math.floor(resendCountdown / 60);
            const secs = resendCountdown % 60;
            resendTimer.textContent = `Reenviar en ${mins}:${secs.toString().padStart(2, '0')}`;
            
            resendCountdown--;
            setTimeout(updateTimer, 1000);
        };
        
        updateTimer();
    }
    
    function showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container');
        if (!toastContainer) return;
        
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        
        const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
        
        toast.innerHTML = `
            <span class="toast-icon">${icon}</span>
            <span class="toast-message">${message}</span>
        `;
        
        toastContainer.appendChild(toast);
        
        // Auto-remover después de 3.5 segundos
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    }
    
    // ===== UX Mejoras para Mobile =====
    
    // Auto-focus inicial
    document.getElementById('email')?.focus();
    
    // Auto-advance en código de 6 dígitos
    const codeInput = document.getElementById('code');
    codeInput?.addEventListener('input', (e) => {
        // Solo permitir números
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
        
        // Auto-submit si completa 6 dígitos
        if (e.target.value.length === 6) {
            setTimeout(() => {
                // ✅ Método compatible: llamar directamente a la función window.verifyCode
                if (typeof window.verifyCode === 'function') {
                    const fakeEvent = { preventDefault: () => {} };
                    window.verifyCode(fakeEvent);
                }
            }, 200);
        }

        // Permitir pegar código completo
        codeInput?.addEventListener('paste', (e) => {
            e.preventDefault();
            const pasted = (e.clipboardData || window.clipboardData).getData('text');
            const digits = pasted.replace(/[^0-9]/g, '').slice(0, 6);
            codeInput.value = digits;
            if (digits.length === 6) {
                setTimeout(() => {
                    if (typeof window.verifyCode === 'function') {
                        const fakeEvent = { preventDefault: () => {} };
                        window.verifyCode(fakeEvent);
                    }
                }, 200);
            }
        });
    });
    
    // Permitir pegar código completo
    codeInput?.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        const digits = pasted.replace(/[^0-9]/g, '').slice(0, 6);
        codeInput.value = digits;
        if (digits.length === 6) {
            setTimeout(() => step2Form.requestSubmit(), 200);
        }
    });
    
    // Prevenir zoom en iOS al enfocar inputs
    document.querySelectorAll('input').forEach(input => {
        input.addEventListener('focus', function() {
            if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                const viewport = document.querySelector('meta[name="viewport"]');
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
            }
        });
        input.addEventListener('blur', function() {
            if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                const viewport = document.querySelector('meta[name="viewport"]');
                viewport.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes');
            }
        });
    });
    
    // Soporte para teclado numérico en código
    codeInput?.setAttribute('inputmode', 'numeric');
    codeInput?.setAttribute('pattern', '[0-9]{6}');
    codeInput?.setAttribute('autocomplete', 'one-time-code');
});