<?php
// public/migrate_users.php - Migración segura de tabla users
// Ejecutar UNA VEZ y luego eliminar este archivo

require_once __DIR__ . '/../config/config.php';



header('Content-Type: text/plain; charset=utf-8');
echo "🚀 Iniciando migración de tabla users...\n\n";

$pdo = getPDO();
$columns = [
    'phone' => "VARCHAR(20) DEFAULT '' AFTER email",
    'address' => "TEXT AFTER phone", 
    'notify_email' => "TINYINT(1) DEFAULT 1 AFTER address",
    'notify_whatsapp' => "TINYINT(1) DEFAULT 0 AFTER notify_email",
    'notify_push' => "TINYINT(1) DEFAULT 1 AFTER notify_whatsapp",
    'profile_completed' => "TINYINT(1) DEFAULT 0 AFTER notify_push",
];

foreach ($columns as $col => $def) {
    // Verificar si la columna ya existe
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND COLUMN_NAME = ?
    ");
    $stmt->execute([DB_NAME, $col]);
    $exists = $stmt->fetchColumn();
    
    if ($exists) {
        echo "✅ {$col}: Ya existe\n";
    } else {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
            echo "✅ {$col}: Agregada exitosamente\n";
        } catch (PDOException $e) {
            echo "⚠️ {$col}: Error - " . $e->getMessage() . "\n";
        }
    }
}

// Crear índice (MySQL 8.0+ soporta IF NOT EXISTS en CREATE INDEX)
try {
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_email_verified ON users(email, verified_at)");
    echo "✅ Índice idx_email_verified: Verificado/Creado\n";
} catch (PDOException $e) {
    echo "⚠️ Índice: " . $e->getMessage() . "\n";
}

echo "\n✨ Migración completada. Puedes eliminar este archivo.\n";