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

# Default command
CMD ["php-fpm", "-F"]
