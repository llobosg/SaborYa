#!/bin/sh
# docker-entrypoint.sh - Manejo robusto de PORT para Railway

set -e

# 1. Definir PORT con fallback
export PORT="${PORT:-8000}"

# 2. Verificar que el directorio público existe
if [ ! -d "$PUBLIC_DIR" ]; then
    export PUBLIC_DIR="${PUBLIC_DIR:-public}"
fi

# 3. Mostrar info de inicio (útil para logs de Railway)
echo "🚀 SaborYa iniciando en puerto $PORT"
echo "📁 Directorio público: $PUBLIC_DIR"
echo "🌍 APP_URL: ${APP_URL:-http://localhost:$PORT}"

# 4. Verificar conexión a DB (opcional, para fail fast)
if [ -n "$MYSQLHOST" ] && [ -n "$MYSQLDATABASE" ]; then
    echo "🔍 Verificando conexión a MySQL..."
    # Intentar conexión simple sin bloquear si falla
    php -r "
        \$pdo = new PDO('mysql:host=getenv('MYSQLHOST');port='.getenv('MYSQLPORT'), getenv('MYSQLUSER'), getenv('MYSQLPASSWORD'));
        echo '✅ MySQL conectado\n';
    " 2>/dev/null || echo "⚠️ MySQL no disponible aún (puede estar iniciando)"
fi

# 5. Ejecutar el comando recibido (CMD del Dockerfile)
exec "$@"