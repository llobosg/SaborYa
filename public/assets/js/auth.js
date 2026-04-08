// public/assets/js/auth.js
document.addEventListener('DOMContentLoaded', () => {
    const step1Form = document.getElementById('step1-form');
    const step2Form = document.getElementById('step2-form');
    const sendCodeBtn = document.getElementById('send-code-btn');
    const verifyBtn = document.getElementById('verify-btn');
    const resendBtn = document.getElementById('resend-code');
    const resendTimer = document.getElementById('resend-timer');
    const toastContainer = document.getElementById('toast-container');
    
    let currentEmail = '';
    let resendCountdown = 0;
    
    // Paso 1: Enviar código
    step1Form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const email = document.getElementById('email').value.trim();
        const csrfToken = step1Form.querySelector('[name="csrf_token"]').value;
        
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showToast('Ingresa un email válido', 'error');
            return;
        }
        
        setLoading(sendCodeBtn, true);
        
        try {
            const response = await fetch('/api/auth/send-code', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body: JSON.stringify({email, csrf_token: csrfToken})
            });
            const data = await response.json();
            
            if (!response.ok) throw new Error(data.error || 'Error al enviar código');
            
            currentEmail = email;
            document.getElementById('verify-email').value = email;
            document.getElementById('masked-email').textContent = data.email_masked;
            
            step1Form.style.display = 'none';
            step2Form.style.display = 'block';
            
            showToast('¡Código enviado! Revisa tu correo 📧', 'success');
            startResendTimer(60);
            
        } catch (error) {
            console.error('Send code error:', error);
            showToast(error.message || 'Error de conexión', 'error');
        } finally {
            setLoading(sendCodeBtn, false);
        }
    });
    
    // Paso 2: Verificar código
    step2Form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = document.getElementById('code').value.trim();
        const name = document.getElementById('name').value.trim();
        const csrfToken = step2Form.querySelector('[name="csrf_token"]').value;
        
        if (!/^\d{6}$/.test(code)) {
            showToast('Ingresa el código de 6 dígitos', 'error');
            return;
        }
        
        setLoading(verifyBtn, true);
        
        try {
            const response = await fetch('/api/auth/verify-code', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body: JSON.stringify({email: currentEmail, code, name: name || null, csrf_token: csrfToken})
            });
            const data = await response.json();
            
            if (!response.ok) throw new Error(data.error || 'Código inválido');
            
            showToast('¡Bienvenido a SaborYa! 🍕', 'success');
            setTimeout(() => { window.location.href = data.redirect || '/home'; }, 1500);
            
        } catch (error) {
            console.error('Verify error:', error);
            showToast(error.message, 'error');
            document.getElementById('code').focus();
            document.getElementById('code').select();
        } finally {
            setLoading(verifyBtn, false);
        }
    });
    
    // Reenviar código
    resendBtn?.addEventListener('click', async () => {
        if (resendCountdown > 0) return;
        const csrfToken = step2Form.querySelector('[name="csrf_token"]').value;
        setLoading(resendBtn, true);
        
        try {
            const response = await fetch('/api/auth/send-code', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken},
                body: JSON.stringify({email: currentEmail, csrf_token: csrfToken})
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Error al reenviar');
            
            showToast('Nuevo código enviado 📧', 'success');
            startResendTimer(60);
        } catch (error) {
            showToast(error.message, 'error');
        } finally {
            setLoading(resendBtn, false);
        }
    });
    
    // Helpers
    function setLoading(button, loading) {
        if (!button) return;
        const text = button.querySelector('.btn-text');
        const loader = button.querySelector('.btn-loading');
        if (loading) {
            button.disabled = true;
            text.style.display = 'none';
            loader.style.display = 'inline';
        } else {
            button.disabled = false;
            text.style.display = 'inline';
            loader.style.display = 'none';
        }
    }
    
    function startResendTimer(seconds) {
        resendCountdown = seconds;
        resendBtn.style.display = 'none';
        resendTimer.style.display = 'inline';
        
        const update = () => {
            if (resendCountdown <= 0) {
                resendTimer.style.display = 'none';
                resendBtn.style.display = 'inline';
                return;
            }
            const mins = Math.floor(resendCountdown / 60);
            const secs = resendCountdown % 60;
            resendTimer.textContent = `Reenviar en ${mins}:${secs.toString().padStart(2, '0')}`;
            resendCountdown--;
            setTimeout(update, 1000);
        };
        update();
    }
    
    function showToast(message, type = 'info') {
        if (!toastContainer) return;
        const toast = document.createElement('div');
        toast.className = `toast toast--${type}`;
        toast.innerHTML = `<span>${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span><span>${message}</span>`;
        toastContainer.appendChild(toast);
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }
    
    // UX mejoras
    document.getElementById('email')?.focus();
    const codeInput = document.getElementById('code');
    codeInput?.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/[^0-9]/g, '');
        if (e.target.value.length === 6) step2Form.requestSubmit();
    });
    codeInput?.addEventListener('paste', (e) => {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        const digits = pasted.replace(/[^0-9]/g, '').slice(0, 6);
        codeInput.value = digits;
        if (digits.length === 6) step2Form.requestSubmit();
    });
});