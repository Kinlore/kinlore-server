# PHP-FPM behind Caddy, plus the queue worker
FROM php:8.5-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    caddy \
    supervisor \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    libzip-dev \
    postgresql-dev \
    oniguruma-dev \
    icu-dev \
    zip \
    unzip \
    bash \
    ffmpeg \
    libheif-tools

# Install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp

RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    intl

# OPcache is part of PHP since 8.5; only its settings are needed
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=2" >> /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Caddy: web server and, with KINLORE_DOMAIN, automatic HTTPS. Certificates live in /data.
COPY ./docker/Caddyfile /etc/caddy/Caddyfile
ENV XDG_DATA_HOME=/data XDG_CONFIG_HOME=/config

# Copy Supervisor configuration
COPY ./docker/supervisord.conf /etc/supervisord.conf

RUN mkdir -p /var/log/supervisor

# Copy PHP configuration for uploads
COPY ./docker/zz-uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini
COPY ./docker/zz-kinlore-fpm.conf /usr/local/etc/php-fpm.d/zz-kinlore-fpm.conf

# Copy entrypoint script
COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Copy application files
COPY . .

# Install PHP dependencies
RUN /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction

# Config is cached by the entrypoint at run time, so the image holds no environment values
RUN php artisan route:cache && \
    php artisan view:cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# HTTP, and HTTPS (TCP and HTTP/3) when KINLORE_DOMAIN is set
EXPOSE 80 443 443/udp

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost:8080/api/health || exit 1

# Start via entrypoint (runs supervisor: Caddy, PHP-FPM, queue worker)
ENTRYPOINT ["/entrypoint.sh"]