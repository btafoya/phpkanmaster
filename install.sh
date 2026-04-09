#!/usr/bin/env bash
set -euo pipefail

# phpKanMaster installation script
# Run this from the project root after cloning

echo "phpKanMaster Installation"
echo "========================="
echo

# Check if .env exists
if [ -f .env ]; then
    echo ".env already exists. Backing up to .env.backup"
    cp .env .env.backup
fi

# Copy .env.example if it doesn't exist
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "Created .env from .env.example"
    else
        echo "Error: .env.example not found"
        exit 1
    fi
fi

# Prompt for username
read -rp "Enter username [admin]: " APP_USER_INPUT
APP_USER="${APP_USER_INPUT:-admin}"

# Prompt for password
while true; do
    read -rsp "Enter password: " PASSWORD
    echo
    read -rsp "Confirm password: " PASSWORD_CONFIRM
    echo

    if [ "$PASSWORD" = "$PASSWORD_CONFIRM" ]; then
        if [ -n "$PASSWORD" ]; then
            break
        else
            echo "Password cannot be empty. Try again."
        fi
    else
        echo "Passwords do not match. Try again."
    fi
done

# Start the stack
echo
echo "Starting Docker stack..."
docker compose up -d --build

# Wait for app container to be ready
echo "Waiting for app container..."
until docker compose exec -T app php -v &>/dev/null; do
    echo -n "."
    sleep 2
done
echo " ready"

# Install dependencies
echo
echo "Installing PHP dependencies..."
docker compose -f docker-compose.yml exec -T -w /var/www/html app composer update

# Generate app key
echo
echo "Generating application key..."
APP_KEY=$(docker compose exec -T app php artisan key:generate --show)

# Generate password hash
echo "Generating password hash..."
PASSWORD_HASH=$(docker compose exec -T app php -r "echo password_hash('$PASSWORD', PASSWORD_BCRYPT);")

# Update .env with username and password hash
sed -i.bak "s/^APP_USER=.*/APP_USER=$APP_USER/" .env
sed -i.bak "s|^APP_PASSWORD_HASH=.*|APP_PASSWORD_HASH=$PASSWORD_HASH|" .env
sed -i.bak "s|^APP_KEY=.*|$APP_KEY|" .env

# Run migrations
echo
echo "Running Laravel migrations..."
docker compose exec -T app php artisan migrate --force

echo
echo "========================="
echo "Installation complete!"
echo "========================="
echo
echo "Access the app at: http://localhost:8181/login"
echo "Username: $APP_USER"
echo "Password: (what you entered)"
echo
echo "To stop the stack: docker compose down"
echo "To view logs: docker compose logs -f"