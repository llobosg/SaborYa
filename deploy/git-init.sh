#!/bin/bash
# deploy/git-init.sh - Inicialización segura para OSX

set -e  # Salir si hay error

echo "🚀 Inicializando repositorio SaborYa..."

# 1. Verificar que estamos en la raíz del proyecto
if [ ! -f "composer.json" ]; then
    echo "❌ Ejecutar este script desde la raíz del proyecto"
    exit 1
fi

# 2. Inicializar git si no existe
if [ ! -d ".git" ]; then
    git init
fi

# 3. Configurar usuario (si no está configurado)
if [ -z "$(git config user.name)" ]; then
    echo "📝 Configura tu nombre de Git:"
    read -r name
    git config user.name "$name"
fi

if [ -z "$(git config user.email)" ]; then
    echo "📧 Configura tu email de Git:"
    read -r email
    git config user.email "$email"
fi

# 4. Agregar archivos (respetando .gitignore)
git add .

# 5. Primer commit
git commit -m "chore: initial commit - SaborYa MVP structure

- Estructura de carpetas segura (/public pattern)
- Esquema de base de datos con índices
- Configuración para Railway + Brevo + MercadoPago
- Sistema de autenticación con verificación por email
- PWA ready con manifest + service worker
- Seguridad: CSRF, sanitización, headers HTTP

✨ Ready for development!"

# 6. Conectar con GitHub (opcional)
echo ""
echo "🔗 ¿Conectar con repositorio GitHub? (s/N)"
read -r connect

if [[ "$connect" =~ ^[sS]$ ]]; then
    echo "📦 Ingresa la URL de tu repositorio GitHub:"
    read -r repo_url
    
    git remote add origin "$repo_url"
    
    # Verificar conexión SSH/HTTPS
    if git ls-remote origin &>/dev/null; then
        echo "✅ Conectado a GitHub"
        
        # Push inicial
        echo "📤 ¿Hacer push a main? (s/N)"
        read -r push_now
        if [[ "$push_now" =~ ^[sS]$ ]]; then
            git branch -M main
            git push -u origin main
            echo "✨ ¡Deploy inicial completado! Railway detectará los cambios."
        fi
    else
        echo "⚠️ No se pudo conectar. Verifica la URL o tus credenciales."
    fi
fi

# 7. Verificar estructura de seguridad
echo ""
echo "🔍 Verificando estructura de seguridad..."
if [ -d "public" ] && [ ! -d "public/config" ]; then
    echo "✅ Patrón /public correcto - config.php está protegido"
else
    echo "⚠️ Revisa que config/ esté FUERA de public/"
fi

if [ -f "public/.htaccess" ]; then
    echo "✅ .htaccess presente para protección Apache"
fi

echo ""
echo "🎉 ¡Listo! Próximos pasos:"
echo "  1. Copiar .env.example a .env y configurar variables"
echo "  2. Ejecutar: composer install"
echo "  3. Importar schema.sql en tu DB de Railway"
echo "  4. Configurar Brevo + MercadoPago en variables de entorno"
echo "  5. npm install (si usas build de assets)"
echo ""
echo "💡 Tip: Usa 'npm run dev' para hot-reload en desarrollo"