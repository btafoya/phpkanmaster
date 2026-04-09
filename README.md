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

## Quick Start

### 1. Clone and Configure

```bash
git clone <repository>
cd phpkanmaster
cp .env.example .env
```

### 2. Generate password hash

```bash
php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
```

Update `APP_PASSWORD_HASH` in `.env` with the generated hash.

### 3. Start Docker stack

```bash
docker compose up -d --build
```

### 4. Access application

- Login: http://localhost:8181/login
- Default username: `admin` (or as configured in `.env`)

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
