<?php
// templates/components/admin-invite-modal.php - Modal para invitar nuevos admins
?>
<div id="invite-admin-modal" class="sub-modal hidden" style="z-index:300">
    <div class="modal-header">
        <span>👥 Invitar Administrador</span>
        <button class="icon-btn-home" onclick="closeInviteModal()">✕</button>
    </div>
    <div class="modal-content-scroll">
        <form id="invite-admin-form" onsubmit="sendInvite(event)" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div class="input-group">
                <label for="invite-name">Nombre completo</label>
                <input type="text" id="invite-name" name="name" required 
                       placeholder="Ej: María González" class="input-field" maxlength="100">
            </div>
            
            <div class="input-group">
                <label for="invite-email">Correo electrónico *</label>
                <input type="email" id="invite-email" name="email" required 
                       placeholder="nuevo@saborya.app" class="input-field">
                <small class="input-help">Recibirá un email con enlace para activar su cuenta</small>
            </div>
            
            <div class="input-group">
                <label for="invite-role">Rol</label>
                <select id="invite-role" name="role" class="input-field">
                    <option value="admin">Administrador (acceso completo)</option>
                    <option value="supervisor">Supervisor (solo lectura + pedidos)</option>
                </select>
                <small class="input-help">Los supervisores no pueden crear/eliminar productos</small>
            </div>
            
            <div style="background:rgba(255,107,53,0.1);border:1px solid rgba(255,107,53,0.3);border-radius:8px;padding:12px;margin:16px 0">
                <small style="color:var(--color-text-secondary)">
                    🔐 El enlace de invitación expira en <strong>24 horas</strong><br>
                    📧 El destinatario deberá establecer su contraseña al aceptar
                </small>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block" id="invite-btn">
                <span class="btn-text">Enviar invitación ✉️</span>
                <span class="btn-loading" style="display:none">
                    <span class="spinner"></span> Enviando...
                </span>
            </button>
        </form>
        
        <div id="invite-result" class="toast-container" style="position:static;margin-top:16px"></div>
    </div>
</div>

<script>
// ===== Modal Controls =====
window.openInviteModal = function() {
    document.getElementById('invite-admin-modal')?.classList.remove('hidden');
    document.getElementById('invite-name')?.focus();
};

window.closeInviteModal = function() {
    const modal = document.getElementById('invite-admin-modal');
    if (modal) modal.classList.add('hidden');
    // Reset form
    const form = document.getElementById('invite-admin-form');
    if (form) {
        form.reset();
        document.getElementById('invite-result').innerHTML = '';
    }
};

// Close on escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeInviteModal();
});

// Close on backdrop click
document.getElementById('invite-admin-modal')?.addEventListener('click', (e) => {
    if (e.target.id === 'invite-admin-modal') closeInviteModal();
});

// ===== Send Invite API =====
window.sendInvite = async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const email = document.getElementById('invite-email')?.value.trim();
    const name = document.getElementById('invite-name')?.value.trim();
    const role = document.getElementById('invite-role')?.value;
    const csrfToken = form.querySelector('[name="csrf_token"]')?.value;
    const btn = document.getElementById('invite-btn');
    const resultDiv = document.getElementById('invite-result');
    
    // Validations
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showInviteResult('Ingresa un email válido 📧', 'error');
        return;
    }
    
    // Loading state
    btn.disabled = true;
    btn.querySelector('.btn-text').style.display = 'none';
    btn.querySelector('.btn-loading').style.display = 'inline-flex';
    resultDiv.innerHTML = '';
    
    try {
        const response = await fetch('/api/admin/invite.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'include',
            body: JSON.stringify({ email, name, role, csrf_token: csrfToken })
        });
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || 'Error al enviar invitación');
        }
        
        // Success
       if (navigator.clipboard && data.invite_link) {
            navigator.clipboard.writeText(data.invite_link).then(() => {
                showInviteResult('✅ Invitación enviada + enlace copiado al portapapeles 📋', 'success');
            }).catch(() => {
                // Fallback si no hay permisos de clipboard
                showInviteResult(`✅ Invitación enviada a ${data.email}`, 'success');
            });
        }
        form.reset();
        
        // Auto-close after 3 seconds
        setTimeout(() => {
            closeInviteModal();
            // Optional: show global toast
            if (window.showToast) {
                showToast('Invitación de admin enviada ✉️', 'success');
            }
        }, 3000);
        
    } catch (error) {
        console.error('Invite error:', error);
        showInviteResult(error.message || 'Error de conexión', 'error');
    } finally {
        btn.disabled = false;
        btn.querySelector('.btn-text').style.display = 'inline';
        btn.querySelector('.btn-loading').style.display = 'none';
    }
};

function showInviteResult(message, type = 'info') {
    const container = document.getElementById('invite-result');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
    toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;
    
    container.innerHTML = '';
    container.appendChild(toast);
    
    // Auto-remove after 5 seconds for errors
    if (type !== 'success') {
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
}
</script>