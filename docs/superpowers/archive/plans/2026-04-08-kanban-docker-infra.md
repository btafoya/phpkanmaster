# phpKanMaster — Docker Infrastructure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the complete Docker stack (PHP 8.4-FPM, PostgreSQL 17, PostgREST, Caddy) that runs Laravel and exposes PostgREST at `/api/*`.

**Architecture:** Four services orchestrated via docker-compose.yml. Caddy reverse-proxies requests: `/api/*` → PostgREST (path stripping), all other requests → Laravel via PHP-FPM. PostgREST is internal-only (no host ports exposed).

**Tech Stack:** Docker Compose, Caddy, PHP 8.4-FPM, PostgreSQL 17, PostgREST latest.

---

### Task 1: Create docker-compose.yml

**Files:**
- Create: `docker-compose.yml`

- [ ] **Step 1: Write docker-compose.yml**

```yaml
services:
  app:
    build:
      context: ./docker/php
      dockerfile: Dockerfile
    container_name: phpkanmaster_app
    restart: unless-stopped
    working_dir: /var/www/html
    volumes:
      - .:/var/www/html
      - ./docker/php/php.ini:/usr/local/etc/php/conf.d/custom.ini
    depends_on:
      db:
        condition: service_healthy
      postgrest:
        condition: service_started
    networks:
      - phpkanmaster

  db:
    image: postgres:17
    container_name: phpkanmaster_db
    restart: unless-stopped
    environment:
      POSTGRES_DB: kanban
      POSTGRES_USER: kanban
      POSTGRES_PASSWORD: ${DB_PASSWORD:-kanban_secret}
      POSTGRES_INITDB_ARGS: "--encoding=UTF8 --lc-collate=C --lc-ctype=C"
    volumes:
      - pgdata:/var/lib/postgresql/data
      - ./docker/db/init:/docker-entrypoint-initdb.d
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U kanban -d kanban"]
      interval: 5s
      timeout: 5s
      retries: 5
    networks:
      - phpkanmaster

  postgrest:
    image: postgrest/postgrest:latest
    container_name: phpkanmaster_postgrest
    restart: unless-stopped
    environment:
      PGRST_DB_URI: postgres://kanban_postgrest:${POSTGREST_PASSWORD:-postgrest_secret}@db:5432/kanban
      PGRST_DB_SCHEMA: public
      PGRST_DB_ANON_ROLE: anon
      PGRST_DB_EXTRA_SEARCH_PATH: public
      PGRST_SERVER_PORT: 3000
      PGRST_SERVER_HOST: 0.0.0.0
    depends_on:
      db:
        condition: service_healthy
    networks:
      - phpkanmaster
    # No ports exposed — accessed only via Caddy

  caddy:
    image: caddy:latest
    container_name: phpkanmaster_caddy
    restart: unless-stopped
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./docker/caddy/Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      - app
      - postgrest
    networks:
      - phpkanmaster

volumes:
  pgdata:
  caddy_data:
  caddy_config:

networks:
  phpkanmaster:
    driver: bridge
```

- [ ] **Step 2: Create .env.example for Docker credentials**

```dotenv
# Database
DB_PASSWORD=kanban_secret

# PostgREST (used in PGRST_DB_URI)
POSTGREST_PASSWORD=postgrest_secret
POSTGREST_JWT_SECRET=

# Application
APP_USER=admin
APP_PASSWORD_HASH=$2y$12$...bcrypt_hash_of_password...
```

- [ ] **Step 3: Commit**

```bash
git add docker-compose.yml .env.example
git commit -m "feat: add docker-compose.yml with app, db, postgrest, caddy services"
```

---

### Task 2: Create PHP 8.4-FPM Dockerfile

**Files:**
- Create: `docker/php/Dockerfile`
- Create: `docker/php/php.ini`

- [ ] **Step 1: Write Dockerfile**

```dockerfile
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
```

- [ ] **Step 2: Write php.ini customizations**

```ini
; docker/php/php.ini
; Custom PHP configuration for phpKanMaster

memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 60
max_input_time = 60

; Error reporting (adjust for production)
display_errors = Off
log_errors = On
error_log = /proc/self/fd/2

; Timezone
date.timezone = UTC
```

- [ ] **Step 3: Commit**

```bash
git add docker/php/
git commit -m "feat: add PHP 8.4-FPM Dockerfile with pdo_pgsql and Composer"
```

---

### Task 3: Create Caddyfile Reverse Proxy Config

**Files:**
- Create: `docker/caddy/Caddyfile`

- [ ] **Step 1: Write Caddyfile**

```
:80 {
    # PostgREST API — strip /api prefix before forwarding
    handle /api/* {
        uri strip_prefix /api
        reverse_proxy postgrest:3000
    }

    # Laravel app — all other requests
    handle {
        root * /var/www/html/public

        # Try files, fall back to index.php
        try_files {path} {path}/ /index.php

        php_fastcgi app:9000 {
            resolve_root_symlink
            env PATH /usr/local/bin:/usr/bin:/bin
        }

        file_server
    }

    # Logging
    log {
        output stdout
        format json
    }
}
```

- [ ] **Step 2: Test Caddyfile syntax (optional but recommended)**

```bash
docker run --rm -v $(pwd)/docker/caddy/Caddyfile:/etc/caddy/Caddyfile:ro caddy:latest caddy validate --config /etc/caddy/Caddyfile
```

Expected: `Exit with 0` or "Validation succeeded"

- [ ] **Step 3: Commit**

```bash
git add docker/caddy/Caddyfile
git commit -m "feat: add Caddyfile with /api/* → PostgREST routing"
```

---

### Task 4: Create PostgreSQL Init Script for Roles

**Files:**
- Create: `docker/db/init/01-roles.sql`

- [ ] **Step 1: Write init script**

```sql
-- docker/db/init/01-roles.sql
-- Executed once on first container start (creates pgdata volume)

-- Create anon role (used by PostgREST for unauthenticated access)
CREATE ROLE anon NOLOGIN;

-- Create login role for PostgREST connection (grants anon)
CREATE ROLE kanban_postgrest LOGIN PASSWORD 'postgrest_secret';
GRANT anon TO kanban_postgrest;

-- Create login role for Laravel migrations (optional, can use same as postgrest)
CREATE ROLE kanban_app LOGIN PASSWORD 'kanban_secret';
GRANT anon TO kanban_app;
```

- [ ] **Step 2: Commit**

```bash
git add docker/db/init/01-roles.sql
git commit -m "feat: add PostgreSQL init script for anon and login roles"
```

---

### Task 5: Create PostgREST Configuration File (optional, env vars primary)

**Files:**
- Create: `docker/postgrest/postgrest.conf` (optional reference)

- [ ] **Step 1: Write config file (for documentation + override flexibility)**

```ini
# docker/postgrest/postgrest.conf
# PostgREST configuration — primarily set via environment in docker-compose.yml

# Database connection (required)
db-uri = "postgres://kanban_postgrest:postgrest_secret@db:5432/kanban"

# Schema (required)
db-schema = "public"

# Anonymous role (required)
db-anon-role = "anon"

# Extra search path
db-extra-search-path = "public"

# Server settings
server-host = "0.0.0.0"
server-port = 3000

# Optional: Enable logging
# db-plan-enabled = true
# db-tx-end = commit
```

- [ ] **Step 2: Commit**

```bash
git add docker/postgrest/postgrest.conf
git commit -m "docs: add PostgREST reference configuration file"
```

---

### Task 6: Create .gitignore for Docker Artifacts

**Files:**
- Modify: `.gitignore` (or create if doesn't exist)

- [ ] **Step 1: Write .gitignore**

```gitignore
# Environment files
.env
.env.local
.env.*.local

# Docker volumes
caddy_data/
caddy_config/
pgdata/

# IDE
.idea/
.vscode/
*.swp
*.swo

# OS
.DS_Store
Thumbs.db

# Logs
*.log
storage/logs/

# Vendor (if not committing Laravel yet)
vendor/
node_modules/
```

- [ ] **Step 2: Commit**

```bash
git add .gitignore
git commit -m "chore: add .gitignore for Docker volumes and environment files"
```

---

### Task 7: Verify Stack Boots (Integration Test)

**Files:**
- No file changes — validation task

- [ ] **Step 1: Build and start all services**

```bash
docker-compose up -d --build
```

Expected: All four containers start without errors.

- [ ] **Step 2: Check container health**

```bash
docker-compose ps
```

Expected: All services show `healthy` or `running`.

- [ ] **Step 3: Verify PostgreSQL is accepting connections**

```bash
docker-compose exec db pg_isready -U kanban -d kanban
```

Expected: `accepting connections`

- [ ] **Step 4: Verify PostgREST is running**

```bash
docker-compose exec postgrest wget -qO- http://localhost:3000/ || echo "PostgREST is up (may return 404 until schema exists)"
```

Expected: Connection succeeds (404 is OK — no tables yet).

- [ ] **Step 5: Verify Caddy routing**

```bash
curl -I http://localhost/api/
```

Expected: 500 or 404 (no schema yet) — but Caddy responds.

```bash
curl -I http://localhost/
```

Expected: 500 or 404 (Laravel not installed yet) — but Caddy responds.

- [ ] **Step 6: Stop services (optional — leave running if continuing)**

```bash
docker-compose down
```

- [ ] **Step 7: Document success**

Add a note to `README.md` or create `DOCKER.md`:

```markdown
# Docker Quickstart

1. Copy `.env.example` to `.env`
2. Run `docker-compose up -d --build`
3. Access http://localhost (Laravel) or http://localhost/api/* (PostgREST)
```

- [ ] **Step 8: Commit**

```bash
git add README.md
git commit -m "docs: add Docker quickstart instructions"
```

---

## Self-Review Checklist

- [ ] All file paths are exact and create-able
- [ ] No placeholders (TBD, TODO, etc.)
- [ ] Each task is independently testable
- [ ] Commands include expected output
- [ ] No references to undefined types/functions

---

## Next Plan

After this plan completes, the next plan is **Laravel Bootstrap + Auth** which installs Laravel 12.x into the `app` service and configures the single-user session guard.
