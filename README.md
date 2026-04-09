# phpKanMaster

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://docs.docker.com/compose/)
[![Last Commit](https://img.shields.io/github/last-commit/btafoya/phpkanmaster)](https://github.com/btafoya/phpkanmaster/commits/main)

A personal single-board Kanban task manager with multi-channel reminders.

## Stack

- **Backend:** Laravel 12.x, PHP 8.4
- **Database:** PostgreSQL 17
- **API:** PostgREST (direct browser access via Caddy reverse proxy)
- **Frontend:** Bootstrap 5.3, jQuery 3.7, jQuery UI Sortable, Summernote, Flatpickr, SweetAlert2
- **Web Server:** Caddy (reverse proxy with TLS)
- **Containerization:** Docker + Docker Compose

## Installation guide

You need Docker and Docker Compose. No local PHP or PostgreSQL required — everything runs in containers.

### 1. Clone and configure

```bash
git clone https://github.com/btafoya/phpkanmaster
cd phpkanmaster
cp .env.example .env
```

### 2. Set your login credentials

The app uses a single user defined in `.env`. Edit these two lines — leave the password hash blank for now if you don't have PHP locally:

```dotenv
APP_USER=admin
APP_PASSWORD_HASH=$2y$12$...   # filled in at step 5
```

### 3. Start the stack

```bash
docker compose up -d --build
```

This builds the PHP image and starts five services: `app` (PHP-FPM), `db` (PostgreSQL 17), `postgrest` (REST API), `caddy` (reverse proxy), and `scheduler` (Laravel cron). The database schema — tables for tasks, categories, and file attachments — is created automatically from `docker/db/init/` on first run.

### 4. Install PHP dependencies

The Dockerfile does not bundle vendor dependencies. Run Composer inside the container before any `artisan` commands:

```bash
docker compose -f docker-compose.yml exec -w /var/www/html app composer update
```

### 5. Generate the application key and password hash

```bash
docker compose exec app php artisan key:generate --show
docker compose exec app php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
```

Copy both outputs into `.env`:

```dotenv
APP_KEY=base64:...
APP_PASSWORD_HASH=$2y$12$...
```

### 6. Run Laravel migrations

The DB init scripts handle the kanban schema. Laravel still needs its own tables for sessions and the job queue:

```bash
docker compose exec app php artisan migrate
```

### 7. Open the app

```
http://localhost:8181/login
```

Log in with the username and password you set in step 2. The HTTPS port is 8443 if you configure TLS in `docker/caddy/Caddyfile`.

## Docker Services

| Service | Description |
|---------|-------------|
| `app` | PHP 8.4-FPM running Laravel |
| `db` | PostgreSQL 17 database |
| `postgrest` | PostgREST API (internal only) |
| `caddy` | Reverse proxy (port 80/443) |
| `scheduler` | Laravel scheduler (`schedule:work`) for reminders |

## Configuration

### Single-user authentication

Edit `.env`:

```dotenv
APP_USER=admin
APP_PASSWORD_HASH=$2y$12$...
```

### Notification channels

```dotenv
NOTIFY_PUSHOVER=false
NOTIFY_TWILIO=false
NOTIFY_ROCKETCHAT=false
```

## Development

### Run migrations

```bash
docker compose exec app php artisan migrate
```

### Clear cache

```bash
docker compose exec app php artisan cache:clear
```

### View logs

```bash
docker compose logs -f app
docker compose logs -f scheduler
```

## Verification

- [x] Login page accessible at `/login`
- [x] Unauthenticated `/` redirects to login
- [x] Authenticated users see kanban board
- [x] Logout invalidates session

## License

MIT
