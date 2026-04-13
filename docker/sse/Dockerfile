FROM php:8.4-cli

# Install PostgreSQL client library
RUN apt-get update && apt-get install -y libpq-dev && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql pcntl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Install dependencies first (cached layer)
COPY composer.json ./
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Copy server code
COPY server.php ./

# Non-root user for safety
RUN useradd -m sse && chown -R sse:sse /app
USER sse

EXPOSE 8080

CMD ["php", "server.php"]