<?php
// src/Utils/RateLimiter.php

namespace Saborya\Utils;

class RateLimiter {
    /**
     * Verificar si una clave ha excedido el límite de intentos
     */
    public static function isLimited(string $key, int $maxAttempts = 3, int $windowSeconds = 3600): bool {
        $cacheDir = sys_get_temp_dir() . '/saborya_cache';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $file = $cacheDir . '/' . md5($key);
        $now = time();
        
        $attempts = [];
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            if (is_array($data)) {
                $attempts = array_filter($data, fn($t) => $t > ($now - $windowSeconds));
            }
        }
        
        if (count($attempts) >= $maxAttempts) {
            return true;
        }
        
        $attempts[] = $now;
        file_put_contents($file, json_encode($attempts));
        
        if (empty($attempts) || max($attempts) < ($now - $windowSeconds * 2)) {
            @unlink($file);
        }
        
        return false;
    }
    
    public static function getRemainingSeconds(string $key, int $windowSeconds = 3600): int {
        $cacheDir = sys_get_temp_dir() . '/saborya_cache';
        $file = $cacheDir . '/' . md5($key);
        
        if (!file_exists($file)) return 0;
        
        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data) || empty($data)) return 0;
        
        $oldest = min($data);
        $remaining = ($oldest + $windowSeconds) - time();
        
        return max(0, $remaining);
    }
    
    public static function clear(string $key): void {
        $cacheDir = sys_get_temp_dir() . '/saborya_cache';
        $file = $cacheDir . '/' . md5($key);
        @unlink($file);
    }
}