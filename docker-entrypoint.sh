#!/bin/sh
# docker-entrypoint.sh
set -e

# Expandir PORT con fallback
export PORT="${PORT:-8000}"
export PUBLIC_DIR="${PUBLIC_DIR:-public}"

# Info de inicio para logs
echo "🚀 SaborYa iniciando en puerto $PORT"
echo "📁 Public dir: $PUBLIC_DIR"

# Ejecutar el comando recibido (CMD del Dockerfile)
exec "$@"