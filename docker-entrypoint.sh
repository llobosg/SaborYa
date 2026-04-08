#!/bin/sh
# docker-entrypoint.sh - Versión definitiva para Railway
set -e

# 1. Expandir PORT con fallback (Railway inyecta PORT dinámicamente)
export PORT="${PORT:-8000}"
export PUBLIC_DIR="${PUBLIC_DIR:-public}"

# 2. Info de inicio para logs de Railway
echo "🚀 SaborYa iniciando en puerto $PORT"
echo "📁 Public dir: $PUBLIC_DIR"
echo "🌍 APP_URL: ${APP_URL:-http://localhost:$PORT}"

# 3. ✅ Construir comando PHP usando la variable $PORT (no el argumento hardcoded)
#    Ignoramos los argumentos de CMD para el puerto y usamos siempre $PORT
exec php -S "0.0.0.0:$PORT" -t "$PUBLIC_DIR"