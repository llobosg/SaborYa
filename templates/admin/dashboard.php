<?php
// templates/admin/dashboard.php - Panel principal de administración
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
    redirect('/login.php?error=unauthorized');
}
$userName = $_SESSION['user_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SaborYa Admin</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        /* Layout admin: sidebar + main content */
        .admin-layout { display: flex; min-height: 100vh; }
        .admin-sidebar { width: 240px; background: var(--color-surface); border-right: 1px solid var(--color-border); padding: 20px; display: flex; flex-direction: column; gap: 8px; }
        .admin-main { flex: 1; padding: 24px; overflow-y: auto; }
        .nav-item { padding: 12px 16px; border-radius: 8px; color: var(--color-text-primary); text-decoration: none; display: flex; align-items: center; gap: 10px; transition: background 0.2s; }
        .nav-item:hover, .nav-item.active { background: rgba(255, 107, 53, 0.1); color: var(--color-primary); }
        .nav-item svg { width: 20px; height: 20px; }
        .stat-card { background: var(--color-surface); border-radius: 16px; padding: 20px; border: 1px solid var(--color-border); }
        .stat-value { font-size: 2rem; font-weight: bold; color: var(--color-primary); }
        .stat-label { color: var(--color-text-secondary); font-size: 0.9rem; }
        .grid-4 { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 768px) { .admin-layout { flex-direction: column; } .admin-sidebar { width: 100%; flex-direction: row; overflow-x: auto; padding: 12px; } }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
                <span style="font-size:1.5rem">🍽️</span>
                <strong>SaborYa Admin</strong>
            </div>
            
            <a href="/admin/dashboard" class="nav-item active">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                Dashboard
            </a>
            <a href="/admin/productos" class="nav-item">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path><line x1="3" y1="6" x2="21" y2="6"></line><path d="M16 10a4 4 0 0 1-8 0"></path></svg>
                Productos
            </a>
            <a href="#" class="nav-item" onclick="alert('Próximamente: Pedidos')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                Pedidos
            </a>
            <a href="#" class="nav-item" onclick="alert('Próximamente: Reportes')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                Reportes
            </a>
            <a href="#" class="nav-item" onclick="alert('Próximamente: Configuración')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                Configuración
            </a>
            
            <div style="margin-top:auto;padding-top:20px;border-top:1px solid var(--color-border)">
                <div style="font-size:0.9rem;color:var(--color-text-secondary);margin-bottom:8px">
                    👤 <?= h($userName) ?><br>
                    <small><?= h($_SESSION['user_email'] ?? '') ?></small>
                </div>
                <a href="/logout.php" class="nav-item" style="color:var(--color-error)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    Cerrar sesión
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="admin-main">
            <h1 style="margin-bottom:24px">👋 Hola, <?= h($userName) ?></h1>
            
            <!-- Stats Grid -->
            <div class="grid-4">
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Productos Activos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Pedidos Hoy</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">$0</div>
                    <div class="stat-label">Ventas Hoy</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">0</div>
                    <div class="stat-label">Usuarios Nuevos</div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card" style="margin-bottom:24px">
                <h3 style="margin-bottom:16px">Acciones Rápidas</h3>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <a href="/admin/productos?new=1" class="btn btn-primary">➕ Nuevo Producto</a>
                    <a href="#" class="btn" onclick="alert('Próximamente: Ver pedidos')">📦 Ver Pedidos</a>
                    <a href="#" class="btn" onclick="openInviteModal(); return false;">👥 Invitar Admin</a>
                </div>
            </div>
            
            <!-- Recent Activity Placeholder -->
            <div class="card">
                <h3 style="margin-bottom:16px">Actividad Reciente</h3>
                <p style="color:var(--color-text-secondary)">No hay actividad reciente. ¡Comienza agregando tu primer producto!</p>
            </div>
        </main>
    </div>
    <?php include __DIR__ . '/../components/admin-invite-modal.php'; ?>
</body>
</html>