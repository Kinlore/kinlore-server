# Use PHP with Apache (production-ready)
FROM php:8.4-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    libicu-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

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
    opcache \
    intl

# Enable OPcache for performance
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=2" >> /usr/local/etc/php/conf.d/opcache.ini

# Enable Apache modules
RUN a2enmod rewrite headers ssl

# Configure Apache to listen on port 443 for SSL
RUN echo "Listen 443" >> /etc/apache2/ports.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy Apache virtual host configuration
COPY ./docker/kinlore-api.conf /etc/apache2/sites-available/kinlore-api.conf

# Enable the site and disable default
RUN a2dissite 000-default.conf && a2ensite kinlore-api.conf

# Copy PHP configuration for uploads
COPY ./docker/zz-uploads.ini /usr/local/etc/php/conf.d/zz-uploads.ini

# Copy entrypoint script
COPY ./docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Copy application files
COPY . .

# Install PHP dependencies
RUN /usr/bin/composer install --no-dev --optimize-autoloader --no-interaction

# Optimize Laravel
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache && \
    chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Expose HTTP and HTTPS ports
EXPOSE 80 443

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
  CMD curl -f http://localhost/api/health || exit 1

# Custom entrypoint for Laravel initialization, delegates to docker-php-entrypoint
ENTRYPOINT ["/entrypoint.sh"]

# Default command (passed to entrypoint, which delegates to docker-php-entrypoint)
CMD ["apache2-foreground"]