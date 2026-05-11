// public/assets/js/auth.js - DEBUG VERSION con logging exhaustivo
(function() {
    'use strict';
    
    // 🪵 Logger centralizado
    const LOG = {
        tag: '[SaborYa.auth]',
        debug: (msg, data) => {
            console.log(`${LOG.tag} ${msg}`, data || '');
            // También enviar a error_log del servidor si es posible
            if (navigator.sendBeacon) {
                try {
                    navigator.sendBeacon('/api/debug-log', JSON.stringify({
                        msg, data, ts: Date.now(), url: location.href
                    }));
                } catch(e) {}
            }
        },
        error: (msg, err) => {
            console.error(`${LOG.tag} ERROR: ${msg}`, err);
        }
    };
    
    LOG.debug('=== auth.js loaded ===', {
        url: window.location.href,
        userAgent: navigator.userAgent,
        domReady: document.readyState
    });
    
    // Esperar DOM ready explícitamente
    function onReady(callback) {
        if (document.readyState !== 'loading') {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback);
        }
    }
    
    onReady(() => {
        LOG.debug('DOM ready, initializing...');
        
        try {
            // ===== Elementos del DOM (declarados UNA SOLA VEZ en scope de función) =====
            const elements = {
                step1Form: document.getElementById('step1-form'),
                step2Form: document.getElementById('step2-form'),
                sendCodeBtn: document.getElementById('send-code-btn'),
                verifyBtn: document.getElementById('verify-btn'),
                resendBtn: document.getElementById('resend-code'),
                resendTimer: document.getElementById('resend-timer'),
                codeInput: document.getElementById('code'),
                emailInput: document.getElementById('email'),
                nameInput: document.getElementById('name'),
                toastContainer: document.getElementById('toast-container')
            };
            
            LOG.debug('DOM elements captured', {
                found: Object.entries(elements).filter(([k,v]) => v !== null).map(([k]) => k),
                missing: Object.entries(elements).filter(([k,v]) => v === null).map(([k]) => k)
            });
            
            let currentEmail = '';
            let resendCountdown = 0;
            
            // ===== Helpers =====
            function validateEmail(email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email || '');
            }
            
            function setLoading(button, loading) {
                LOG.debug('setLoading', { button: button?.id, loading });
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
            
            function showToast(message, type = 'info') {
                LOG.debug('showToast', { message, type });
                const container = elements.toastContainer;
                if (!container) {
                    LOG.error('Toast container not found');
                    return;
                }
                
                const toast = document.createElement('div');
                toast.className = `toast toast--${type}`;
                const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
                
                toast.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-message">${message}</span>`;
                container.appendChild(toast);
                
                setTimeout(() => {
                    toast.style.opacity = '0';
                    toast.style.transform = 'translateY(-10px)';
                    setTimeout(() => toast.remove(), 300);
                }, 3500);
            }
            
            function startResendTimer(seconds) {
                LOG.debug('startResendTimer', { seconds });
                resendCountdown = seconds;
                if (elements.resendBtn) elements.resendBtn.style.display = 'none';
                if (elements.resendTimer) elements.resendTimer.style.display = 'block';
                
                const update = () => {
                    if (resendCountdown <= 0) {
                        if (elements.resendTimer) elements.resendTimer.style.display = 'none';
                        if (elements.resendBtn) elements.resendBtn.style.display = 'inline';
                        return;
                    }
                    const mins = Math.floor(resendCountdown / 60);
                    const secs = resendCountdown % 60;
                    if (elements.resendTimer) {
                        elements.resendTimer.textContent = `Reenviar en ${mins}:${secs.toString().padStart(2, '0')}`;
                    }
                    resendCountdown--;
                    setTimeout(update, 1000);
                };
                update();
            }
            
            // ===== Paso 1: Enviar código =====
            window.sendCode = async function(e) {
                LOG.debug('=== sendCode called ===');
                if (e) e.preventDefault();
                
                const email = (elements.emailInput?.value || '').trim();
                const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
                
                LOG.debug('sendCode inputs', { email: email ? email.substring(0,3)+'***' : 'empty', csrfToken: csrfToken ? 'present' : 'missing' });
                
                if (!validateEmail(email)) {
                    LOG.error('Email validation failed', { email });
                    showToast('Ingresa un email válido 📧', 'error');
                    elements.emailInput?.focus();
                    return;
                }
                
                setLoading(elements.sendCodeBtn, true);
                
                try {
                    LOG.debug('Fetching /api/auth/send-code.php');
                    const response = await fetch('/api/auth/send-code.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        credentials: 'include',
                        body: JSON.stringify({ email, csrf_token: csrfToken })
                    });
                    
                    LOG.debug('Response received', { status: response.status, ok: response.ok });
                    
                    const data = await response.json();
                    LOG.debug('Response JSON parsed', { data: { success: data.success, error: data.error } });
                    
                    if (!response.ok) {
                        throw new Error(data.error || data.message || 'Error al enviar código');
                    }
                    
                    currentEmail = email;
                    if (document.getElementById('verify-email')) {
                        document.getElementById('verify-email').value = email;
                    }
                    if (document.getElementById('masked-email')) {
                        document.getElementById('masked-email').textContent = data.email_masked;
                    }
                    
                    if (elements.step1Form) elements.step1Form.classList.add('hidden');
                    if (elements.step2Form) elements.step2Form.classList.remove('hidden');
                    
                    showToast('¡Código enviado! Revisa tu correo ✨', 'success');
                    
                    setTimeout(() => {
                        elements.codeInput?.focus();
                    }, 300);
                    
                    startResendTimer(60);
                    
                } catch (error) {
                    LOG.error('sendCode error', error);
                    showToast(error.message || 'Error de conexión', 'error');
                } finally {
                    setLoading(elements.sendCodeBtn, false);
                }
            };
            
            // ===== Paso 2: Verificar código =====
            window.verifyCode = async function(e) {
                LOG.debug('=== verifyCode called ===');
                if (e) e.preventDefault();
                
                // ✅ Usar elementos ya declarados (NO re-declarar form/verifyBtn)
                const code = (elements.codeInput?.value || '').trim();
                const name = (elements.nameInput?.value || '').trim();
                const csrfToken = elements.step2Form?.querySelector('[name="csrf_token"]')?.value || '';
                
                LOG.debug('verifyCode inputs', { 
                    codeLength: code.length, 
                    name: name || 'empty',
                    csrfToken: csrfToken ? 'present' : 'missing'
                });
                
                if (!/^\d{6}$/.test(code)) {
                    LOG.error('Code validation failed', { code });
                    showToast('Ingresa el código de 6 dígitos 🔢', 'error');
                    elements.codeInput?.focus();
                    return;
                }
                
                setLoading(elements.verifyBtn, true);
                
                try {
                    LOG.debug('Fetching /api/auth/verify-code.php');
                    const response = await fetch('/api/auth/verify-code.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        credentials: 'include',
                        body: JSON.stringify({
                            email: currentEmail,
                            code,
                            name: name || null,
                            csrf_token: csrfToken
                        })
                    });
                    
                    LOG.debug('Response received', { status: response.status, ok: response.ok });
                    
                    const data = await response.json();
                    LOG.debug('Response JSON', { success: data.success, error: data.error, redirect: data.redirect });
                    
                    if (!response.ok) {
                        throw new Error(data.error || data.message || 'Código inválido');
                    }
                    
                    // Toast según estado
                    if (data.already_verified) {
                        showToast('✅ Ya estás verificado, redirigiendo...', 'success');
                    } else {
                        showToast('¡Bienvenido a SaborYa! 🍕✨', 'success');
                    }
                    
                    // ✅ Redirect robusto (usando elementos ya declarados)
                    const redirectUrl = data.redirect || '/home';
                    LOG.debug('Redirecting', { url: redirectUrl });
                    
                    // Deshabilitar UI para prevenir re-submit
                    if (elements.step2Form) {
                        elements.step2Form.style.pointerEvents = 'none';
                        elements.step2Form.style.opacity = '0.7';
                    }
                    if (elements.verifyBtn) {
                        elements.verifyBtn.disabled = true;
                        const btnText = elements.verifyBtn.querySelector('.btn-text');
                        if (btnText) btnText.textContent = 'Redirigiendo...';
                    }
                    
                    // Redirect inmediato
                    window.location.href = redirectUrl;
                    
                    // Fallbacks
                    setTimeout(() => {
                        if (!window.location.href.includes(redirectUrl)) {
                            LOG.debug('Fallback 1: location.replace');
                            window.location.replace(redirectUrl);
                        }
                    }, 2000);
                    
                    setTimeout(() => {
                        if (!window.location.href.includes(redirectUrl)) {
                            LOG.debug('Fallback 2: location.assign');
                            window.location.assign(redirectUrl);
                        }
                    }, 4000);
                    
                } catch (error) {
                    LOG.error('verifyCode error', error);
                    showToast(error.message, 'error');
                    elements.codeInput?.focus();
                    elements.codeInput?.select();
                } finally {
                    setLoading(elements.verifyBtn, false);
                }
            };
            
            // ===== Reenviar código =====
            elements.resendBtn?.addEventListener('click', async () => {
                LOG.debug('Resend button clicked');
                if (resendCountdown > 0) {
                    LOG.debug('Resend blocked by countdown');
                    return;
                }
                
                const csrfToken = elements.step2Form?.querySelector('[name="csrf_token"]')?.value || '';
                
                setLoading(elements.resendBtn, true);
                elements.resendBtn.disabled = true;
                
                try {
                    const response = await fetch('/api/auth/send-code.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-Token': csrfToken
                        },
                        credentials: 'include',
                        body: JSON.stringify({ email: currentEmail, csrf_token: csrfToken })
                    });
                    
                    const data = await response.json();
                    
                    if (!response.ok) {
                        throw new Error(data.error || 'Error al reenviar');
                    }
                    
                    showToast('Nuevo código enviado 📧✨', 'success');
                    startResendTimer(60);
                    
                } catch (error) {
                    LOG.error('Resend error', error);
                    showToast(error.message, 'error');
                } finally {
                    setLoading(elements.resendBtn, false);
                    elements.resendBtn.disabled = false;
                }
            });
            
            // ===== UX: Auto-advance en código =====
            if (elements.codeInput) {
                LOG.debug('Setting up codeInput listeners');
                
                // Input event: solo números + auto-submit
                elements.codeInput.addEventListener('input', (e) => {
                    e.target.value = e.target.value.replace(/[^0-9]/g, '');
                    if (e.target.value.length === 6 && typeof window.verifyCode === 'function') {
                        LOG.debug('Auto-submit triggered by input');
                        setTimeout(() => window.verifyCode({ preventDefault: () => {} }), 200);
                    }
                });
                
                // Paste event: AL MISMO NIVEL que input (no anidado)
                elements.codeInput.addEventListener('paste', (e) => {
                    LOG.debug('Paste event detected');
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text');
                    const digits = pasted.replace(/[^0-9]/g, '').slice(0, 6);
                    elements.codeInput.value = digits;
                    
                    if (digits.length === 6 && typeof window.verifyCode === 'function') {
                        LOG.debug('Auto-submit triggered by paste');
                        setTimeout(() => window.verifyCode({ preventDefault: () => {} }), 200);
                    }
                });
                
                // Atributos para teclado numérico
                elements.codeInput.setAttribute('inputmode', 'numeric');
                elements.codeInput.setAttribute('pattern', '[0-9]{6}');
                elements.codeInput.setAttribute('autocomplete', 'one-time-code');
            }
            
            // Prevenir zoom en iOS
            document.querySelectorAll('input').forEach(input => {
                input.addEventListener('focus', function() {
                    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                        const vp = document.querySelector('meta[name="viewport"]');
                        if (vp) vp.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no');
                    }
                });
                input.addEventListener('blur', function() {
                    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
                        const vp = document.querySelector('meta[name="viewport"]');
                        if (vp) vp.setAttribute('content', 'width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes');
                    }
                });
            });
            
            // Auto-focus inicial
            elements.emailInput?.focus();
            
            LOG.debug('=== auth.js initialization complete ===');
            
        } catch (initError) {
            LOG.error('Initialization failed', initError);
            console.error('FATAL: auth.js init error:', initError);
        }
    });
})();