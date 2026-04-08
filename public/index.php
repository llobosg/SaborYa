<?php
// public/index.php - Entry point con validación de config

// 1. Iniciar sesión segura
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// 2. Cargar configuración con validación robusta
$configPath = realpath(__DIR__ . '/../config/config.php');
if (!$configPath || !file_exists($configPath)) {
    $configPath = __DIR__ . '/../config/config.php';
}
if (!file_exists($configPath)) {
    // Log de error para debug en Railway
    error_log("CONFIG ERROR: config.php not found at " . __DIR__ . '/../config/config.php');
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Configuration error',
        'details' => getenv('APP_ENV') === 'development' 
            ? 'config.php not found at: ' . $configPath 
            : 'Service temporarily unavailable'
    ]);
    exit;
}
require_once $configPath;

// 3. Middleware de autenticación básico
function requireAuth($role = null) {
    if (empty($_SESSION['user_id'])) {
        redirect('login.php?return_to=' . urlencode($_SERVER['REQUEST_URI']));
    }
    if ($role && ($_SESSION['user_role'] ?? '') !== $role) {
        redirect('login.php?error=unauthorized');
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
];

// Rutas consumidor (requieren auth)
$consumerRoutes = [
    ['GET', '/home', 'consumer_home'],
    ['GET', '/catalogo', 'consumer_catalog'],
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
