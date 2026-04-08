# Dockerfile - Versión corregida para Railway
FROM php:8.2-cli

# 1. Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libonig-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    ca-certificates \
    curl \
    && rm -rf /var/lib/apt/lists/*

# 2. Instalar extensiones PHP esenciales
RUN docker-php-ext-install -j$(nproc) pdo pdo_mysql curl mbstring

# 3. GD opcional (no falla el build si no compila)
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install gd || true

# 4. Directorio de trabajo
WORKDIR /app

# 5. Copiar composer.json primero para aprovechar cache de capas
COPY composer.json composer.lock* ./
RUN if [ -f composer.lock ]; then \
        composer install --no-dev --no-interaction --optimize-autoloader; \
    else \
        composer install --no-dev --no-interaction --optimize-autoloader; \
    fi

# 6. Copiar el resto del proyecto
COPY . .

# 7. Permisos para logs y uploads
RUN mkdir -p logs uploads/products uploads/receipts && \
    chmod -R 775 logs uploads

# 8. HEALTHCHECK nativo de Docker (Railway lo respeta)
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost:${PORT:-8000}/health || exit 1

# 9. ENTRYPOINT script para manejo robusto de PORT
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh
ENTRYPOINT ["docker-entrypoint.sh"]

# 10. CMD por defecto (se pasa al entrypoint)
CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]