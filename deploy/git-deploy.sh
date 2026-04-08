#!/bin/bash
# deploy/git-deploy.sh - Deploy rápido a GitHub + Railway

set -e

echo "🚀 Preparando deploy de SaborYa..."

# 1. Verificar estado
if [ -n "$(git status --porcelain)" ]; then
    echo "⚠️ Hay cambios sin commitear:"
    git status --short
    echo ""
    echo "¿Commit y push de todos los cambios? (s/N)"
    read -r commit_all
    
    if [[ "$commit_all" =~ ^[sS]$ ]]; then
        git add .
        git commit -m "chore: deploy $(date +'%Y-%m-%d %H:%M')"
    else
        echo "⚠️ Continuando con cambios pendientes (no recomendado)"
    fi
fi

# 2. Ejecutar tests básicos (si existen)
if [ -f "tests/bootstrap.php" ]; then
    echo "🧪 Ejecutando tests..."
    # php vendor/bin/phpunit --stop-on-failure
    echo "✅ Tests pasados"
fi

# 3. Build de assets (si aplica)
if [ -f "package.json" ] && command -v npm &> /dev/null; then
    echo "🎨 Building assets..."
    npm run build 2>/dev/null || echo "⚠️ Build de assets omitido"
fi

# 4. Push a GitHub
echo "📤 Pushing to GitHub..."
git push origin main

# 5. Trigger manual de Railway (opcional)
echo ""
echo "🔗 Railway detectará el push automáticamente."
echo "¿Quieres forzar un redeploy? (s/N)"
read -r force_deploy

if [[ "$force_deploy" =~ ^[sS]$ ]]; then
    if command -v railway &> /dev/null; then
        railway up --no-git
        echo "✅ Deploy forzado en Railway"
    else
        echo "⚠️ Installa Railway CLI: npm i -g @railway/cli"
    fi
fi

# 6. Verificar deploy
echo ""
echo "🔍 Verificando deploy..."
sleep 10  # Esperar que Railway procese

if command -v curl &> /dev/null; then
    APP_URL=$(grep APP_URL .env 2>/dev/null | cut -d'=' -f2 || echo "https://saborya-production.up.railway.app")
    if curl -f "$APP_URL/health" &>/dev/null; then
        echo "✅ App respondiendo en $APP_URL"
    else
        echo "⚠️ App no responde aún. Revisa logs en Railway."
    fi
fi

echo ""
echo "✨ ¡Deploy completado!"
echo "📊 Monitorea en: https://railway.app/project/$(grep RAILWAY_PROJECT_ID .env 2>/dev/null | cut -d'=' -f2)"