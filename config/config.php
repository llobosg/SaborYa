<?php
// config/config.php - NO ACCESIBLE DESDE WEB (fuera de /public)

// Cargar .env local si existe (Railway inyecta variables directamente)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && !str_starts_with(trim($line), '#')) {
            [$key, $value] = explode('=', $line, 2);
            putenv(trim($key) . '=' . trim($value));
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// === CONSTANTES DE BASE DE DATOS (Railway) ===
define('DB_HOST', getenv('MYSQLHOST') ?: 'localhost');
define('DB_PORT', getenv('MYSQLPORT') ?: '3306');
define('DB_NAME', getenv('MYSQLDATABASE') ?: 'saborya_db');
define('DB_USER', getenv('MYSQLUSER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: '');
define('DB_CHARSET', 'utf8mb4');

// === CONSTANTES DE APP ===
define('APP_ENV', getenv('APP_ENV') ?: 'production');
define('APP_URL', rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/'));
define('APP_NAME', 'SaborYa');
define('APP_TIMEZONE', 'America/Santiago');

// === SEGURIDAD ===
define('JWT_SECRET', getenv('JWT_SECRET') ?: bin2hex(random_bytes(32)));
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hora
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutos

// === BREVO (Email) ===
define('BREVO_API_KEY', getenv('BREVO_API_KEY') ?: '');
define('BREVO_FROM_EMAIL', getenv('BREVO_FROM_EMAIL') ?: 'no-reply@saborya.app');
define('BREVO_FROM_NAME', getenv('BREVO_FROM_NAME') ?: APP_NAME);

// === MERCADO PAGO ===
define('MP_ACCESS_TOKEN', getenv('MP_ACCESS_TOKEN') ?: '');
define('MP_PUBLIC_KEY', getenv('MP_PUBLIC_KEY') ?: '');
define('MP_WEBHOOK_SECRET', getenv('MP_WEBHOOK_SECRET') ?: '');

// === NOTIFICACIONES ===
define('FCM_SERVER_KEY', getenv('FCM_SERVER_KEY') ?: '');
define('WA_API_TOKEN', getenv('WA_API_TOKEN') ?: ''); // Meta Cloud API
define('WA_PHONE_ID', getenv('WA_PHONE_ID') ?: '');

// === ABANDONED CART ===
define('CART_ABANDON_DELAY_MIN', 60); // Minutos para marcar como abandonado
define('ABANDONED_NOTIFICATION_DELAY_MIN', 30); // Esperar 30min después de abandonar para notificar

// === CONFIGURACIÓN DE ERRORES ===
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php-errors.log');
}

// Configurar cookies de sesión para funcionar en subrutas (/api/, /assets/, etc.)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Mejorar configuración de cookies para PWA/API
if (APP_ENV === 'production') {
    ini_set('session.cookie_samesite', 'Lax');  // Permitir cookies en fetch con credentials
    ini_set('session.cookie_secure', '1');      // Solo HTTPS en producción
    ini_set('session.cookie_httponly', '1');    // Prevenir acceso JS a la cookie
} else {
    // En desarrollo local, permitir cookies sin HTTPS
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', '0');
    ini_set('session.cookie_httponly', '1');
}

// === FUNCIONES DE SEGURIDAD ===
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf($token) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    return htmlspecialchars(trim($data ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function redirect($path) {
    $url = APP_URL . '/' . ltrim($path, '/');
    if (!headers_sent()) {
        header("Location: $url", true, 302);
        exit;
    }
    echo "<script>window.location.href='$url'</script>";
    exit;
}

// === CONEXIÓN PDO SEGURA ===
function getPDO() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;port=%s;dbname=%s;charset=%s",
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '"+APP_TIMEZONE+"'"
        ];
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }
    return $pdo;
}