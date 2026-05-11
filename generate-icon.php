<?php
// generate-icon-logo.php - Icono con diseño simple (círculo + cubierto)

$size = 192;
$colorBg = [255, 107, 53];    // Naranja SaborYa
$colorFg = [255, 255, 255];   // Blanco para detalles
$output = "public/assets/images/icons/icon-{$size}x{$size}.png";

// Generar PNG con diseño
if (generateLogoPng($size, $colorBg, $colorFg, $output)) {
    echo "✅ Generado con logo: {$output}\n";
} else {
    echo "❌ Error\n";
    exit(1);
}

// ============================================
// FUNCIÓN: PNG con diseño simple (círculo + cubierto)
// ============================================
function generateLogoPng($size, $bgRgb, $fgRgb, $outputPath) {
    try {
        $center = $size / 2;
        $radius = $size * 0.35;
        
        // Crear datos de píxeles
        $rawData = '';
        for ($y = 0; $y < $size; $y++) {
            $rawData .= "\x00"; // Filter: None
            for ($x = 0; $x < $size; $x++) {
                $dx = $x - $center;
                $dy = $y - $center;
                $dist = sqrt($dx*$dx + $dy*$dy);
                
                // Círculo blanco con borde naranja
                if ($dist < $radius - 8) {
                    // Interior blanco
                    $rgb = $fgRgb;
                } elseif ($dist < $radius) {
                    // Borde naranja
                    $rgb = $bgRgb;
                } else {
                    // Fondo naranja
                    $rgb = $bgRgb;
                }
                
                // Dibujar cubierto simple en el centro (tenedor)
                if ($dist < $radius * 0.6 && $x > $center - 10 && $x < $center + 10) {
                    // "Mango" del tenedor
                    if ($y > $center + 5 && $y < $center + 30) {
                        $rgb = $bgRgb;
                    }
                    // "Dientes" del tenedor
                    if ($y > $center - 25 && $y < $center + 5) {
                        if (($x - $center) % 8 < 3) {
                            $rgb = $bgRgb;
                        }
                    }
                }
                
                $rawData .= chr($rgb[0]) . chr($rgb[1]) . chr($rgb[2]);
            }
        }
        
        // Comprimir y construir PNG (igual que antes)
        $compressed = gzcompress($rawData, 9);
        if ($compressed === false) throw new Exception('Compression failed');
        
        $png = "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A";
        $png .= makeChunk('IHDR', pack('NNCCCCC', $size, $size, 8, 2, 0, 0, 0));
        $png .= makeChunk('IDAT', $compressed);
        $png .= makeChunk('IEND', '');
        
        // Escribir archivo
        $dir = dirname($outputPath);
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        
        $bytes = file_put_contents($outputPath, $png);
        if ($bytes === false || $bytes < 100) throw new Exception('Write failed');
        
        // Verificar header PNG
        $check = file_get_contents($outputPath, false, null, 0, 8);
        if ($check !== "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A") {
            throw new Exception('Invalid PNG header');
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Logo PNG error: " . $e->getMessage());
        return false;
    }
}

// Helper makeChunk (igual que antes)
function makeChunk($type, $data) {
    $length = pack('N', strlen($data));
    $crc = pack('N', crc32($type . $data));
    return $length . $type . $data . $crc;
}