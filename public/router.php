<?php
// public/router.php - Router para PHP built-in server (Railway)
// Este archivo NO se accede directamente, lo usa el servidor internamente

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// ============================================
// 🪵 LOGGING INICIAL (para debug)
// ============================================
error_log("ROUTER_DEBUG: Request URI={$_SERVER['REQUEST_URI']}, Path={$uri}, Method={$_SERVER['REQUEST_METHOD']}");

// 1. Si es un archivo físico, servirlo directamente (CSS, JS, imágenes)
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    error_log("ROUTER_DEBUG: Serving static file: {$uri}");
    return false; // PHP sirve el archivo estático automáticamente
}

// 2. Mapeo de rutas "amigables" a archivos reales
$routes = [
    '/' => '/registro.php',           // Raíz → Registro (landing principal)
    '/home' => '/index.php',
    '/login' => '/login.php',
    '/registro' => '/registro.php',
    '/catalogo' => '/index.php',
    '/carrito' => '/index.php',
    '/admin' => '/admin/index.php',   // Si existe
];

// 3. Resolver ruta o fallback a index.php (SPA pattern)
$target = $routes[$uri] ?? '/index.php';

// 4. Verificar que el archivo destino existe
if (file_exists(__DIR__ . $target)) {
    error_log("ROUTER_DEBUG: Routing {$uri} → {$target}");
    require __DIR__ . $target;
} else {
    // 404 controlado
    error_log("ROUTER_DEBUG: 404 - File not found: {$target}");
    http_response_code(404);
    header('Content-Type: text/html');
    echo '<!DOCTYPE html><html><head><title>404 - SaborYa</title></head><body>';
    echo '<h1>🍽️ Página no encontrada</h1>';
    echo '<p>La ruta <code>' . htmlspecialchars($uri) . '</code> no existe.</p>';
    echo '<p><a href="/registro.php">Ir al registro</a> | <a href="/login.php">Iniciar sesión</a></p>';
    echo '</body></html>';
    exit;
}