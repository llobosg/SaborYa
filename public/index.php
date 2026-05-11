<?php
// public/index.php - Entry point

// 🪵 Log request inicial
error_log("INDEX_DEBUG: Starting index.php - URI={$_SERVER['REQUEST_URI']}, Method={$_SERVER['REQUEST_METHOD']}");

// 1. Iniciar sesión segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
error_log("INDEX_DEBUG: Session started - ID=" . session_id() . ", User ID=" . ($_SESSION['user_id'] ?? 'none'));

// 2. Cargar configuración
$configPath = realpath(__DIR__ . '/../config/config.php');
if (!$configPath || !file_exists($configPath)) {
    $configPath = __DIR__ . '/../config/config.php';
}
if (!file_exists($configPath)) {
    error_log("INDEX_DEBUG: CONFIG ERROR - config.php not found");
    http_response_code(500);
    echo json_encode(['error' => 'Configuration error']);
    exit;
}
error_log("INDEX_DEBUG: Config loaded from {$configPath}");
require_once $configPath;

// ============================================
// ✅ RUTAS DE CONSUMIDOR - HANDLERS SIMPLES
// ============================================

function consumer_home() {
    // Si ya completó el perfil, redirect a catálogo
    if (!empty($_SESSION['profile_completed'])) {
        redirect('/catalogo');
    }
    // Si no, mostrar onboarding
    $viewPath = __DIR__ . '/../templates/consumer/home.php';
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        // Fallback si el template no existe
        error_log("Template not found: {$viewPath}");
        http_response_code(500);
        echo "Error: Template de onboarding no encontrado";
    }
}

function consumer_catalog() {
    if (empty($_SESSION['user_id'])) { redirect('/registro.php'); }
    
    // Placeholder temporal
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Catálogo - SaborYa</title>';
    echo '<link rel="stylesheet" href="/assets/css/styles.css"></head><body>';
    echo '<div class="app-container"><div class="card auth-card" style="margin-top:20px">';
    echo '<h2>🍕 Catálogo en construcción</h2>';
    echo '<p style="color:var(--color-text-secondary);margin:16px 0">';
    echo 'Estamos preparando los mejores platos para ti.<br>';
    echo 'Mientras tanto, puedes completar tu perfil.';
    echo '</p>';
    echo '<a href="/home" class="btn btn-primary btn-block">⚙️ Mi Perfil</a>';
    echo '</div></div></body></html>';
}

function consumer_cart() {
    if (empty($_SESSION['user_id'])) { redirect('/registro.php'); }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Carrito - SaborYa</title>';
    echo '<link rel="stylesheet" href="/assets/css/styles.css"></head><body>';
    echo '<div class="app-container"><div class="card auth-card" style="margin-top:20px">';
    echo '<h2>🛒 Carrito vacío</h2>';
    echo '<p style="color:var(--color-text-secondary);margin:16px 0">';
    echo 'Aún no has agregado productos.<br>¡Explora el catálogo para comenzar!';
    echo '</p>';
    echo '<a href="/catalogo" class="btn btn-primary btn-block">Ver catálogo 🍕</a>';
    echo '</div></div></body></html>';
}

function consumer_history() {
    if (empty($_SESSION['user_id'])) { redirect('/registro.php'); }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Historial - SaborYa</title>';
    echo '<link rel="stylesheet" href="/assets/css/styles.css"></head><body>';
    echo '<div class="app-container"><div class="card auth-card" style="margin-top:20px">';
    echo '<h2>📜 Historial de pedidos</h2>';
    echo '<p style="color:var(--color-text-secondary);margin:16px 0">';
    echo 'Aquí verás tus pedidos anteriores.<br>¡Aún no has realizado tu primera compra!';
    echo '</p>';
    echo '<a href="/catalogo" class="btn btn-primary btn-block">Hacer mi primer pedido 🎉</a>';
    echo '</div></div></body></html>';
}

// ============================================
// ✅ HANDLERS DE ADMIN (placeholders)
// ============================================
// ===== HANDLERS DE ADMIN (agregar junto a consumer_*) =====
function admin_dashboard() {
    // Verificar autenticación y rol
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin' || !($_SESSION['is_active'] ?? true)) {
        redirect('/login.php?error=unauthorized');
    }
    
    // Actualizar last_login
    if (!empty($_SESSION['user_id'])) {
        try {
            $pdo = getPDO();
            $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = ?")
                ->execute([$_SESSION['user_id']]);
        } catch (Exception $e) { /* No fallar si el log falla */ }
    }
    
    // Renderizar dashboard
    include __DIR__ . '/../templates/admin/dashboard.php';
}

function admin_products() {
    if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'admin') {
        redirect('/login.php?error=unauthorized');
    }
    include __DIR__ . '/../templates/admin/products.php';
}

function admin_accept_invite() {
    // Esta ruta se maneja directamente por public/admin/accept-invite.php
    // Pero la registramos por si acaso
    include __DIR__ . '/../public/admin/accept-invite.php';
}

function admin_kds_monitor() {
    // Placeholder para Kitchen Display System
    consumer_catalog(); // Reutilizar placeholder por ahora
}



function admin_send_campaign() {
    http_response_code(501);
    echo json_encode(['error' => 'Not implemented']);
}

// ============================================
// ✅ ROUTER PRINCIPAL (después de definir handlers)
// ============================================

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// ✅ ============================================
// ✅ FIX: Redirección para ruta raíz "/"
// ✅ ============================================
error_log("INDEX_DEBUG: Request path resolved to: {$requestUri}");

if ($requestUri === '/' || $requestUri === '/index.php') {
    error_log("INDEX_DEBUG: Root path detected - Redirecting to /registro.php");
    
    // Redirigir a registro como landing principal
    header('Location: /registro.php', true, 302);
    exit;  // ✅ IMPORTANTE: detener ejecución después de redirect
}

// 3. Middleware de autenticación básico
function requireAuth($role = null) {
    if (empty($_SESSION['user_id'])) {
        redirect('login.php?return_to=' . urlencode($_SERVER['REQUEST_URI']));
    }
    if ($role && ($_SESSION['user_role'] ?? '') !== $role) {
        redirect('login.php?error=unauthorized');
    }
}

// Agregar esta función junto a consumer_home, consumer_cart, etc.

function consumer_catalogo() {
    if (empty($_SESSION['user_id'])) { redirect('/registro.php'); }
    
    // Verificar si completó el perfil
    if (empty($_SESSION['profile_completed'])) {
        redirect('/home');
    }
    
    // Renderizar template
    $viewPath = __DIR__ . '/../templates/consumer/catalogo.php';
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        // Fallback: mostrar placeholder
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Catálogo - SaborYa</title>';
        echo '<link rel="stylesheet" href="/assets/css/styles.css"></head><body>';
        echo '<div class="app-container"><div class="card auth-card" style="margin-top:20px">';
        echo '<h2>🍕 Catálogo en construcción</h2>';
        echo '<p style="color:var(--color-text-secondary);margin:16px 0">';
        echo 'Estamos preparando los mejores platos para ti.<br>';
        echo 'Mientras tanto, revisa tu perfil o espera novedades.';
        echo '</p>';
        echo '<div style="display:flex;gap:10px;flex-wrap:wrap">';
        echo '<a href="/home" class="btn btn-primary" style="flex:1">⚙️ Mi Perfil</a>';
        echo '<a href="/carrito" class="btn" style="flex:1;background:rgba(255,255,255,0.1)">🛒 Carrito</a>';
        echo '</div></div></div></body></html>';
    }
}

// 4. Enrutador simple
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Rutas públicas
$publicRoutes = [
    ['GET', '/login.php', 'login_form'],
    ['GET', '/registro.php', 'register_form'],
    ['POST', '/api/auth/send-code', 'send_registration_code'],
    ['POST', '/api/auth/verify-code', 'verify_registration_code'],
    ['POST', '/api/auth/recover-password', 'request_password_recovery'],
    ['POST', '/api/auth/reset-password', 'reset_password'],
    ['GET', '/admin/accept-invite.php', 'admin_accept_invite'],
];

// Rutas consumidor (requieren auth)
$consumerRoutes = [
    ['GET', '/home', 'consumer_home'],
    ['GET', '/catalogo', 'consumer_catalogo'],
    ['GET', '/carrito', 'consumer_cart'],
    ['POST', '/api/cart/add', 'cart_add'],
    ['POST', '/api/cart/abandoned-check', 'cart_abandoned_check'],
    ['POST', '/api/orders/create', 'order_create'],
    ['GET', '/historial', 'consumer_history'],
];

// Rutas admin (requieren auth + role)
$adminRoutes = [
    ['GET', '/admin', 'admin_dashboard'],
    ['GET', '/admin/kds', 'admin_kds_monitor'],
    ['POST', '/api/admin/orders/update-status', 'admin_update_order_status'],
    ['POST', '/api/admin/orders/edit', 'admin_edit_order'],
    ['GET', '/admin/productos', 'admin_products'],
    ['POST', '/api/admin/campaigns/send', 'admin_send_campaign'],
];

// 5. Resolver ruta
function resolveRoute($method, $path, $routes) {
    foreach ($routes as [$m, $p, $handler]) {
        if ($method === $m && rtrim($path, '/') === rtrim($p, '/')) {
            return $handler;
        }
    }
    return null;
}

$handler = null;

// Verificar rutas públicas primero
foreach ($publicRoutes as [$m, $p, $h]) {
    if ($method === $m && rtrim($request, '/') === rtrim($p, '/')) {
        $handler = $h;
        break;
    }
}

// Si no es pública, verificar autenticación
if (!$handler) {
    if (!empty($_SESSION['user_id'])) {
        $role = $_SESSION['user_role'] ?? 'consumer';
        $routes = ($role === 'admin') ? $adminRoutes : $consumerRoutes;
        $handler = resolveRoute($method, $request, $routes);
        
        if ($handler && $role === 'admin') {
            requireAuth('admin');
        } elseif ($handler) {
            requireAuth();
        }
    }
}

// 6. Ejecutar handler o mostrar 404
if ($handler && function_exists($handler)) {
    // Incluir vista o ejecutar lógica
    $viewPath = __DIR__ . '/../templates/' . 
                (str_contains($handler, 'admin_') ? 'admin/' : 'consumer/') .
                str_replace(['consumer_', 'admin_'], '', $handler) . '.php';
    
    if (file_exists($viewPath)) {
        include $viewPath;
    } else {
        // Handler es función de API
        require_once __DIR__ . '/../src/' . 
            str_replace('_', '/', $handler) . '.php';
        call_user_func($handler);
    }
} else {
    // 404
    http_response_code(404);
    include __DIR__ . '/../templates/components/404.php';
}