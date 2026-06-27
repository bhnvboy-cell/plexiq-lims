FROM php:8.1-apache-bookworm

LABEL maintainer="PlexiQ LIMS <info@plexiq.ai>"
LABEL description="PlexiQ LIMS - Docker Image"

# Install system dependencies and PHP extensions
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpq-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libzip-dev \
        unzip \
        git \
        curl \
        cron \
    ; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j$(nproc) \
        pgsql \
        pdo_pgsql \
        gd \
        zip \
        mbstring \
        json \
        bcmath \
    ; \
    pecl install apcu; \
    docker-php-ext-enable apcu; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

COPY . .

# Create storage directories with proper permissions
RUN mkdir -p storage/coa storage/logs storage/sessions storage/installer && \
    chown -R www-data:www-data storage bootstrap/cache && \
    chmod -R 775 storage

# Configure Apache virtual host
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

EXPOSE 80

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
