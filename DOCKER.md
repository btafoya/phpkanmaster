# Docker Quickstart

## Prerequisites

- Docker Desktop (Mac/Windows) or Docker Engine + Docker Compose (Linux)
- Verify installation: `docker compose version`

## Getting Started

1. **Copy environment file**
   ```bash
   cp .env.example .env
   ```

2. **Edit `.env`** (optional — defaults work for local dev)
   ```dotenv
   DB_PASSWORD=your_secure_password
   POSTGREST_PASSWORD=your_postgrest_password
   APP_USER=admin
   APP_PASSWORD_HASH=$2y$12$... # bcrypt hash of your password
   ```

3. **Start all services**
   ```bash
   docker compose up -d --build
   ```

4. **Verify services are running**
   ```bash
   docker compose ps
   ```
   Expected: All 4 services show `running` or `healthy`

5. **Test endpoints**
   ```bash
   # Test PostgREST (will return 404 until schema exists)
   curl -I http://localhost/api/

   # Test Laravel app (will return 500 until Laravel installed)
   curl -I http://localhost/
   ```

6. **View logs**
   ```bash
   docker compose logs -f
   ```

7. **Stop services**
   ```bash
   docker compose down
   ```

## Services

| Service | Container | Port | Description |
|---------|-----------|------|-------------|
| app | phpkanmaster_app | — | PHP 8.4-FPM, Laravel |
| db | phpkanmaster_db | — | PostgreSQL 17 |
| postgrest | phpkanmaster_postgrest | — | PostgREST (internal only) |
| caddy | phpkanmaster_caddy | 80, 443 | Caddy reverse proxy |

## Architecture

```
                    ┌─────────────────┐
                    │     Caddy       │
                    │   (port 80/443) │
                    └────────┬────────┘
                             │
           ┌─────────────────┼─────────────────┐
           │                 │                 │
    ┌──────▼──────┐  ┌───────▼───────┐  ┌─────▼─────┐
    │   Laravel   │  │   PostgREST   │  │    (API)  │
    │  (PHP-FPM)  │  │   (port 3000) │  │           │
    └─────────────┘  └───────┬───────┘  └───────────┘
                             │
                    ┌────────▼────────┐
                    │   PostgreSQL    │
                    │     (port 5432) │
                    └─────────────────┘
```

- Browser → Caddy → Laravel (all routes except `/api/*`)
- Browser → Caddy → PostgREST (`/api/*` routes, prefix stripped)
- PostgREST → PostgreSQL (internal network)

## Next Steps

After Docker stack is running:
1. Install Laravel 12.x in the `app` service
2. Run database migrations for the Kanban schema
3. Access http://localhost to view the application
