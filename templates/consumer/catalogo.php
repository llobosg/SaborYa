<?php
// templates/consumer/catalogo.php - Placeholder temporal
if (empty($_SESSION['user_id']) || empty($_SESSION['profile_completed'])) {
    redirect('/home');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#FF6B35">
    <title>Catálogo - SaborYa 🍕</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
    <div class="app-container">
        <div class="logo-header">
            <div class="logo-bubble">
                <span class="logo-icon">🍕</span>
                <h1>Catálogo</h1>
            </div>
            <p class="logo-subtitle">Próximamente: los mejores platos para ti</p>
        </div>
        
        <div class="card auth-card">
            <h2 class="card-title">🚧 En desarrollo</h2>
            <p style="color:var(--color-text-secondary);margin:16px 0;line-height:1.6">
                Estamos preparando una experiencia increíble con:<br><br>
                ✅ Platos preparados con ingredientes frescos<br>
                ✅ Filtros por categoría, precio y preferencias<br>
                ✅ Carrito inteligente con recordatorios<br>
                ✅ Entrega rápida a tu puerta 🚴
            </p>
            
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">
                <a href="/home" class="btn btn-primary" style="flex:1">⚙️ Mi Perfil</a>
                <a href="/carrito" class="btn" style="flex:1;background:rgba(255,255,255,0.1)">🛒 Carrito</a>
            </div>
        </div>
        
        <div id="toast-container" class="toast-container"></div>
    </div>
</body>
</html>