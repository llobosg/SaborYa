<?php
// verify-icons.php - Verifica que los iconos PWA son válidos

$icons = [
    'public/assets/images/icons/icon-192x192.png' => [192, 192],
    'public/assets/images/icons/icon-512x512.png' => [512, 512],
];

$pngSignature = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
$allOk = true;

foreach ($icons as $path => [$expW, $expH]) {
    echo "Verificando: {$path}\n";
    
    if (!file_exists($path)) {
        echo "  ❌ No existe\n";
        $allOk = false;
        continue;
    }
    
    $size = filesize($path);
    echo "  Tamaño: {$size} bytes\n";
    
    if ($size < 100) {
        echo "  ❌ Archivo demasiado pequeño\n";
        $allOk = false;
        continue;
    }
    
    $header = file_get_contents($path, false, null, 0, 8);
    if ($header !== $pngSignature) {
        echo "  ❌ No es un PNG válido (header incorrecto)\n";
        $allOk = false;
        continue;
    }
    echo "  ✅ Header PNG válido\n";
    
    // Leer dimensiones del IHDR chunk
    $ihdr = file_get_contents($path, false, null, 8, 25); // Saltar signature + leer IHDR
    if (strlen($ihdr) >= 25 && substr($ihdr, 4, 4) === 'IHDR') {
        $dims = unpack('Nwidth/Nheight', substr($ihdr, 16, 8));
        echo "  Dimensiones: {$dims['width']}x{$dims['height']}\n";
        if ($dims['width'] == $expW && $dims['height'] == $expH) {
            echo "  ✅ Dimensiones correctas\n";
        } else {
            echo "  ❌ Dimensiones incorrectas (esperado {$expW}x{$expH})\n";
            $allOk = false;
        }
    }
    echo "\n";
}

if ($allOk) {
    echo "✨ Todos los iconos son válidos. ¡Listo para PWA!\n";
    exit(0);
} else {
    echo "⚠️ Algunos iconos tienen problemas. Revisa arriba.\n";
    exit(1);
}