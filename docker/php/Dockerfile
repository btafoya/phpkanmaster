FROM php:8.4-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_pgsql

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create system user for Laravel (optional, for permissions)
RUN useradd -G www-data -u 1000 -d /home/laravel -m -s /bin/bash laravel || exit 0

# Configure PHP-FPM to listen on all interfaces
RUN echo "[global]" > /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "daemonize = no" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "[www]" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "listen = 0.0.0.0:9000" >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    mkdir -p /tmp && chmod 1777 /tmp

# Default command
CMD ["php-fpm", "-F"]
