// public/assets/js/login.js - Lógica del login (separada del HTML)

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
        const response = await fetch('/api/auth/login.php', {  // ✅ .php al final
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',  // ✅ Enviar cookies de sesión
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
        console.error('Login error:', error);
        showToast(error.message, 'error');
    } finally {
        btn.disabled = false;
        btn.querySelector('.btn-text').style.display = 'inline';
        btn.querySelector('.btn-loading').style.display = 'none';
    }
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