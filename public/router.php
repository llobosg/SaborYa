<?php
// public/router.php - Router para PHP built-in server (pretty URLs)

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 1. Si es un archivo físico, servirlo directamente
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false; // PHP sirve el archivo estático
}

// 2. Mapeo de rutas "pretty" a archivos reales
$routes = [
    '/health' => '/health.php',
    '/login' => '/login.php',
    '/registro' => '/registro.php',
    '/home' => '/index.php',
    '/catalogo' => '/index.php',
    '/carrito' => '/index.php',
];

// 3. Resolver ruta o fallback a index.php (SPA pattern)
$target = $routes[$uri] ?? '/index.php';

// 4. Incluir el archivo destino
if (file_exists(__DIR__ . $target)) {
    require __DIR__ . $target;
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found', 'path' => $uri]);
}