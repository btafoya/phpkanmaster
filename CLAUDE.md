# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

phpKanMaster is a single-user personal Kanban task manager. The entire stack runs in Docker. All CRUD operations on tasks/categories bypass Laravel and go directly from the browser to PostgREST via the `/api/*` proxy path. Laravel's only responsibilities are authentication and serving the SPA shell (`kanban.blade.php`).

## Claude Code Behaviour Guidelines

- Avoid ownership-dodging behaviour: if you encounter an issue, take responsibility for it and work towards a solution instead of passing it on to someone else. Don't say things like "not caused by my changes" or say that it's "a pre-existing issue". Instead, acknowledge the problem and take initiative to fix it. Also, don't give up with excuses like "known limitation" and don't mark it for "future work".
- Avoid premature stopping: if you encounter a problem, don't stop at the first obstacle. Instead, keep pushing forward and find a way to overcome it. Don't say things like "good stopping point" or "natural checkpoint". Instead, keep going until you have a complete solution.
- Avoid permission-seeking behaviour: if you have the knowledge and capability to solve a problem, push through. Don't say things like "should I continue?" or "want me to keep going?". Instead, take initiative and act towards the solution.
- Do plan multi-step approaches before acting (plan which files to read and in what order, which tools to use, etc).
- Do recall and apply project-specific conventions from CLAUDE.md files.
- Do catch your own mistakes by applying reasoning loops and self-checks, and fix them before committing or asking for help.

### Use of tools

Adhere to the following guidelines when using tools:

- Always use a **Research-First approach**: Before using any tool, conduct thorough research to understand the context and requirements. This ensures you use the most appropriate tool for the task at hand. Never use an Edit-First approach. You should prefer making surgical edits to the codebase instead of rewriting whole files or doing large, sweeping changes.
- Use **Reasoning Loops** very frequently. Don't be lazy and skip them. Reasoning loops are essential for ensuring the quality and accuracy of your work.

### Thinking Depth

When working on tasks that require complex problem-solving, always apply the highest **level of thinking depth**.

When thinking is shallow, the model outputs to the cheapest action available. We don't want that. We don't mind consuming more tokens if it means a better output. So always apply the highest level of thinking depth.

Never reason from assumptions, always reason from the actual data. You need to read and understand the actual code, publication or documentation in order to make informed decisions. Don't rely on assumptions or guesses, as they can lead to mistakes and misunderstandings.

## Critical Rules

### Never

- Concatenate user input into SQL queries
- Commit `.env`, credentials, API keys, or tokens
- Auto-deploy to production without explicit approval
- Hardcode credentials (use environment variables)
- Do project-wide search-and-replace renames without a plan
- Include AI attribution in commits ("Generated with Claude Code", "Co-Authored-By")

### ALWAYS

- Run PHPStan before committing: `/usr/bin/php vendor/bin/phpstan analyse app --memory-limit=256M`
- Run PHPUnit before committing: `/usr/bin/php vendor/bin/phpunit`
- Verify no secrets before any commit
- Log errors with context before re-throwing
- Commit as author: **btafoya** — no AI attribution in messages
- Follow `.claude/skills/*/SKILL.md` for quality standards

## Available Skills

Use these skills when relevant to the task at hand:

| Skill | When to Use | Path |
|-------|-------------|
| `jquery` | jQuery DOM manipulation, events, AJAX, animations (project uses jQuery 4.0) | .claude/skills/jquery/ |
| `bootstrap-overview` | Bootstrap 5 setup, components, accessibility | .claude/skills/bootstrap-overview/ |
| `bootstrap-components` | Bootstrap 5 component details and usage | .claude/skills/bootstrap-components |
| `bootstrap-layout` | Bootstrap 5 grid and layout system | .claude/skills/bootstrap-layout |
| `pwa-expert` | Service worker, PWA features, offline support | .claude/skills/pwa-expert |
| `launch-readiness-auditor` | Pre-launch quality and completeness audit | .claude/skills/launch-readiness-auditor |
| `graphify` | Knowledge graph for codebase architecture questions |

## Common Commands

All commands run inside Docker unless otherwise noted.

```bash
# Start/stop stack
docker compose up -d --build
docker compose down

# Laravel artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan cache:clear
docker compose exec app php artisan tinker

# Composer update
docker compose -f docker-compose.yml exec -w /var/www/html app composer update

# Run tests (uses in-memory SQLite — does NOT require Docker)
npm test                              # JS unit tests (Vitest)
npm run test:phpunit                  # PHPUnit tests
npm run test:phpstan                  # PHPStan static analysis
npm run test:all                      # all three above, in sequence
php artisan test --filter TestName   # single PHPUnit test

# Code style (Laravel Pint)
./vendor/bin/pint

# Frontend assets (Vite — only needed if editing resources/js or resources/css)
npm run dev     # dev mode with HMR
npm run build   # production build

# Logs
docker compose logs -f app
docker compose logs -f caddy

# Generate a bcrypt password hash for `.env`:
php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
```

## Local url

<http://localhost:8181/>

## Database migration files go in the Laravel migration database/migrations/ folder, NOT docker/db/init/

## Architecture

### Request Routing (Caddy)

```
Browser → Caddy :80
  /api/*    → strip prefix → PostgREST:3000 → PostgreSQL
  /*        → PHP-FPM (Laravel) app:9000
```

`docker/caddy/Caddyfile` defines this split. PostgREST is internal-only (no exposed port).

### Authentication

Laravel uses a custom single-user auth driver, not Eloquent:

- `app/Auth/SingleUser.php` — implements `Authenticatable`, identity is just the username string
- `app/Auth/SingleUserProvider.php` — validates against `APP_USER` / `APP_PASSWORD_HASH` from `.env`
- `app/Providers/SingleUserAuthServiceProvider.php` — registers the `single-user` driver
- `config/auth.php` — defines `single` guard using `single-user` provider; credentials are read from `auth.credentials.*`
- Guard name is `auth:single` (used in `routes/web.php`)

There is no user database table involved in authentication.

### Frontend (SPA shell)

`resources/views/kanban.blade.php` is a single Blade view that loads CDN assets and mounts `public/assets/js/app.js`. The JS is **not** built by Vite — it's a plain file served statically.

`window.POSTGREST_URL` is set inline from `env('PGRST_BASE_URL', '/api')` so the JS knows the API base URL.

`public/assets/js/app.js` is the entire frontend, structured as a single `window.App` object with namespaces:

- `App.Api` — PostgREST fetch wrappers (tasks, categories, task_files)
- `App.Board` — renders columns, task cards, category filters
- `App.DnD` — jQuery UI Sortable drag-and-drop, syncs `task_column`/`position` via PATCH
- `App.Modal.Task` / `App.Modal.Category` — Bootstrap modals for CRUD
- `App.Alerts` — SweetAlert2 toast and confirm mixins

CDN libraries loaded in `kanban.blade.php`: Bootstrap 5.3, jQuery 4.0, jQuery UI Sortable, Summernote 0.9 (rich text), Flatpickr (date picker), SweetAlert2.

### Database Schema (PostgreSQL)

Tables in the `public` schema (managed outside Laravel migrations — see `docker/db/init/`):

- `tasks` — columns: `id`, `title`, `description`, `task_column` (enum: new/in_progress/review/on_hold/done), `priority` (low/medium/high), `position`, `category_id`, `due_date`, `reminder_at`, `pushover_priority`
- `categories` — `id`, `name`, `color`
- `task_files` — `id`, `task_id`, `filename`, `content` (base64)

PostgREST connects as `kanban_postgrest` user with `anon` role having CRUD permissions on these tables.

Laravel's own migrations only create `users`, `cache`, and `jobs` tables (unused for core functionality).

### Environment Variables

Key variables in `.env`:

| Variable | Purpose |
|---|---|
| `APP_USER` | Login username |
| `APP_PASSWORD_HASH` | Bcrypt hash of login password |
| `PGRST_BASE_URL` | PostgREST base URL seen by browser (default: `/api`) |
| `DB_*` | PostgreSQL connection for Laravel |
| `POSTGREST_PASSWORD` | Password for `kanban_postgrest` DB user |
| `NOTIFY_PUSHOVER/TWILIO/ROCKETCHAT` | Enable notification channels |

### Tests

Tests use in-memory SQLite (`phpunit.xml`), not PostgreSQL. The custom `SingleUserProvider` is the main auth component worth testing. Feature tests use `auth:single` guard — see `tests/TestCase.php` for base setup.

## graphify

Knowledge graph at `graphify-out/`. Read `graphify-out/GRAPH_REPORT.md` before architecture questions. Run `python3 -c "from graphify.watch import _rebuild_code; from pathlib import Path; _rebuild_code(Path('.'))"` after code changes.
