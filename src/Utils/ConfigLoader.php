<?php
// src/Utils/ConfigLoader.php
namespace Saborya\Utils;

class ConfigLoader {
    private static ?array $config = null;
    
    public static function load(string $path = null): array {
        if (self::$config !== null) {
            return self::$config;
        }
        
        $path = $path ?? __DIR__ . '/../../config/config.php';
        
        // Resolver ruta absoluta
        $resolved = realpath($path);
        if (!$resolved || !is_file($resolved)) {
            $resolved = $path; // fallback
        }
        
        if (!is_file($resolved)) {
            throw new \RuntimeException(
                "Configuration file not found: $resolved\n" .
                "Working dir: " . getcwd() . "\n" .
                "Script dir: " . __DIR__
            );
        }
        
        // Cargar y retornar variables definidas
        $varsBefore = get_defined_vars();
        require $resolved;
        $varsAfter = get_defined_vars();
        
        self::$config = array_diff_key($varsAfter, $varsBefore);
        return self::$config;
    }
    
    public static function get(string $key, $default = null) {
        $config = self::load();
        return $config[$key] ?? $default;
    }
}