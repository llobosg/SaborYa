# Dockerfile - Versión definitiva para Railway + Composer
FROM php:8.2-cli

# 1. Instalar dependencias del sistema + herramientas para Composer
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libonig-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    ca-certificates \
    curl \
    wget \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar extensiones PHP esenciales
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql curl mbstring zip

# 3. GD opcional (no falla el build si no compila)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd || true

# 4. ✅ INSTALAR COMPOSER (esto faltaba)
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 5. Directorio de trabajo
WORKDIR /app

# 6. Copiar archivos de Composer PRIMERO (para aprovechar cache de capas)
COPY composer.json composer.lock* ./

# 7. Instalar dependencias PHP con Composer
#    --no-interaction: evita prompts
#    --optimize-autoloader: mejor rendimiento en producción
#    --ignore-platform-reqs: evita errores por extensión faltante en build
RUN if [ -f composer.lock ]; then \
        composer install --no-dev --no-interaction --optimize-autoloader --ignore-platform-reqs; \
    else \
        composer install --no-dev --no-interaction --optimize-autoloader --ignore-platform-reqs; \
    fi

# 8. Copiar el resto del proyecto
COPY . .

# 9. Permisos para logs y uploads
RUN mkdir -p logs uploads/products uploads/receipts && \
    chmod -R 775 logs uploads

# 10. HEALTHCHECK nativo de Docker
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:${PORT:-8000}/health || exit 1

# 11. ENTRYPOINT robusto (el script construye el comando con $PORT)
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

# 12. CMD vacío o placeholder (el entrypoint lo ignora para el puerto)
#     Lo dejamos por compatibilidad con Docker, pero no afecta la ejecución
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public", "router.php"]