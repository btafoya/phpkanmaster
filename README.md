# phpKanMaster

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PostgreSQL](https://img.shields.io/badge/PostgreSQL-17-4169E1?logo=postgresql&logoColor=white)](https://www.postgresql.org/)
[![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?logo=docker&logoColor=white)](https://docs.docker.com/compose/)

A personal single-board Kanban task manager with multi-channel reminders.

## Stack

- **Backend:** Laravel 12.x, PHP 8.4
- **Database:** PostgreSQL 17
- **API:** PostgREST (direct browser access via Caddy reverse proxy)
- **Frontend:** Bootstrap 5.3, jQuery 3.7, jQuery UI 1.13.2 Sortable, Summernote 0.9, Flatpickr, SweetAlert2
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

### 2. Set your credentials

The app uses a single user defined in `.env`. Edit these lines:

```dotenv
APP_USER=admin
APP_PASSWORD_HASH=$2y$12$...   # Generate with: docker compose exec app php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
```

You also need to set `JWT_SECRET` for PostgREST authentication:

```dotenv
JWT_SECRET=your_secret_at_least_32_characters
```

### 3. Start the stack

```bash
docker compose up -d --build
```

This builds the PHP image and starts five services: `app` (PHP-FPM), `db` (PostgreSQL 17), `postgrest` (REST API), `caddy` (reverse proxy), and `scheduler` (Laravel cron for reminders). The database schema is created by Laravel migrations.

### 4. Install PHP dependencies

The Dockerfile does not bundle vendor dependencies. Run Composer inside the container:

```bash
docker compose exec -w /var/www/html app composer update
```

### 5. Generate the application key

```bash
docker compose exec app php artisan key:generate --show
```

Copy the output into `.env`:

```dotenv
APP_KEY=base64:...
```

### 6. Run Laravel migrations

```bash
docker compose exec app php artisan migrate
```

### 7. Open the app

```
http://localhost:8181/login
```

Log in with the username and password you set in step 2. The HTTPS port is 8443 if you configure TLS in `docker/caddy/Caddyfile`.

### Automated install

Run the included script instead of manual steps:

```bash
./install.sh
```

The script prompts for username and password, starts the stack, installs dependencies, generates the app key and password hash, and runs migrations.

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
APP_PASSWORD_HASH=$2y$12$...   # bcrypt hash
JWT_SECRET=your_secret_at_least_32_characters
```

### Notification channels

Enable channels in `.env` and set the respective tokens:

```dotenv
NOTIFY_PUSHOVER=false
NOTIFY_TWILIO=false
NOTIFY_ROCKETCHAT=false
```

**Pushover:**
```dotenv
PUSHOVER_TOKEN=your_pushover_token
PUSHOVER_USER_KEY=your_user_key
```

**Twilio:**
```dotenv
TWILIO_ACCOUNT_SID=your_account_sid
TWILIO_AUTH_TOKEN=your_auth_token
TWILIO_FROM=+15550000000
```

**RocketChat:**
```dotenv
ROCKETCHAT_URL=https://your-rocket-chat.example.com
ROCKETCHAT_TOKEN=your_bot_token
ROCKETCHAT_CHANNEL=#general
```

## Architecture

### Request Routing (Caddy)

```
Browser → Caddy :80
  /api/*         → PostgREST:3000 (via /api prefix stripping)
  /api/agent/*   → Laravel (agent token endpoint)
  /*             → PHP-FPM (Laravel) app:9000
```

`docker/caddy/Caddyfile` defines this routing. PostgREST is internal-only (no exposed port).

### Authentication

Laravel uses a custom single-user auth driver (no user table):

- `app/Auth/SingleUser.php` — implements `Authenticatable`
- `app/Auth/SingleUserProvider.php` — validates against `APP_USER` / `APP_PASSWORD_HASH` from `.env`
- `app/Providers/SingleUserAuthServiceProvider.php` — registers the `single-user` driver
- `config/auth.php` — defines `single` guard using `single-user` provider

Guard name is `auth:single` (used in `routes/web.php`).

### Frontend (SPA shell)

`resources/views/kanban.blade.php` is a single Blade view that loads CDN assets and mounts `public/assets/js/app.js`. The JS is a plain static file, not built by Vite.

`window.POSTGREST_URL` is set inline from `env('PGRST_BASE_URL', '/api')` so the JS knows the API base URL.

### Database Schema (PostgreSQL)

Tables are managed by Laravel migrations in `database/migrations/`:

- `tasks` — columns: `id` (uuid), `title`, `description`, `task_column` (enum: new/in_progress/review/on_hold/done), `priority` (low/medium/high), `position`, `category_id`, `parent_id` (for subtasks), `due_date`, `reminder_at`, `reminder_sent`, `pushover_*` fields
- `task_notes` — `id`, `task_id`, `content` (notes), timestamps
- `recurrence_rules` — `id`, `task_id`, `rrule`, `next_occurrence_at`, `end_date`, `active`
- `categories` — `id`, `name`, `color`
- `task_files` — `id`, `task_id`, `filename`, `content` (base64)

PostgREST connects as `kanban_postgrest` user with `anon` role having CRUD permissions on these tables.

Laravel's own tables (`users`, `cache`, `jobs`, `sessions`) are also created by migrations.

## Production Deployment

### Prerequisites

- Docker and Docker Compose
- A domain name pointed to your server (optional, for TLS)
- `docker` and `docker compose` commands available

### Steps

**1. Copy the production environment template:**

```bash
cp .env.production.example .env
```

**2. Set your credentials in `.env`:**

```dotenv
APP_KEY=          # Run: docker compose exec app php artisan key:generate
APP_DEBUG=false
APP_URL=https://your-domain.com

APP_PASSWORD_HASH= # Run: docker compose exec app php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
JWT_SECRET=        # Strong random string, 32+ characters
```

**3. Set database passwords in `docker-compose.yml`:**

In the `db` service, set `POSTGRES_PASSWORD` to a strong random value.
In the `postgrest` service, set `POSTGREST_PASSWORD` environment variable.
Set `JWT_SECRET` in the `postgrest` service.

**4. Start the stack:**

```bash
docker compose up -d --build
```

**5. Run migrations:**

```bash
docker compose exec app php artisan migrate --force
```

**6. Caddy handles TLS automatically** — as long as port 80 and 443 are open, Let's Encrypt certificates are provisioned on first request.

### Key production settings

| Variable | Value | Why |
|---|---|---|
| `APP_ENV` | `production` | Disables debug mode |
| `APP_DEBUG` | `false` | Hides stack traces |
| `LOG_STACK` | `daily` | Rotates logs daily, keeps 14 days |
| `LOG_LEVEL` | `warning` | Reduces log noise |
| `SESSION_ENCRYPT` | `true` | Encrypts session cookies |

### Logs

```bash
# Application logs (rotated daily)
docker compose exec app cat storage/logs/laravel-$(date +%Y-%m-%d).log

# All container logs
docker compose logs -f

# Caddy logs (includes access log)
docker compose logs -f caddy
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