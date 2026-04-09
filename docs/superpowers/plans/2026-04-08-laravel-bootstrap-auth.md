# phpKanMaster — Laravel Bootstrap + Auth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Install Laravel 12.x into the Docker stack and configure single-user session authentication with no database users table.

**Architecture:** Laravel serves as the application layer with session-based auth. A custom guard validates credentials against `.env` configuration (single-user mode). PostgREST handles all Kanban data operations directly from the browser.

**Tech Stack:** Laravel 12.x, PHP 8.4, Composer, Bootstrap 5.3 session driver.

---

## Task 1: Install Laravel 12.x via Composer

**Files:**
- Create: `composer.json` (via Laravel installer)
- Create: `composer.lock`
- Create: `vendor/` directory

- [ ] **Step 1: Install Laravel into project root**

```bash
# From project root (docker/app service must be running)
docker-compose exec app composer create-project laravel/laravel /var/www/html 12.* --prefer-dist
```

Expected: Laravel 12.x installed with default structure.

- [ ] **Step 2: Verify Laravel boots**

```bash
docker-compose exec app php artisan --version
```

Expected: `Laravel Framework 12.x.x`

- [ ] **Step 3: Verify PHP extensions**

```bash
docker-compose exec app php -m | grep -E 'pdo|pgsql'
```

Expected: `pdo_pgsql` listed.

- [ ] **Step 4: Commit**

```bash
git add composer.json composer.lock vendor/
git commit -m "feat: install Laravel 12.x framework"
```

---

## Task 2: Configure Environment for Docker Stack

**Files:**
- Modify: `.env.example` (add Laravel config)
- Create: `.env` (local development)

- [ ] **Step 1: Update .env.example with Laravel settings**

```dotenv
# Database (Laravel migrations / reminders command)
DB_CONNECTION=pgsql
DB_HOST=db
DB_PORT=5432
DB_DATABASE=kanban
DB_USERNAME=kanban
DB_PASSWORD=kanban_secret

# PostgREST (browser API)
PGRST_BASE_URL=/api

# Single-user auth
APP_USER=admin
APP_PASSWORD_HASH=$2y$12$...bcrypt_hash_of_password...

# Session
SESSION_DRIVER=database
SESSION_TABLE=sessions
SESSION_LIFETIME=120

# Notification channels (enable/disable)
NOTIFY_PUSHOVER=false
NOTIFY_TWILIO=false
NOTIFY_ROCKETCHAT=false

# Pushover (if enabled)
PUSHOVER_TOKEN=
PUSHOVER_USER_KEY=

# Twilio (if enabled)
TWILIO_ACCOUNT_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM=+15550000000

# RocketChat (if enabled)
ROCKETCHAT_URL=
ROCKETCHAT_TOKEN=
ROCKETCHAT_CHANNEL=#general
```

- [ ] **Step 2: Copy .env.example to .env and generate app key**

```bash
cp .env.example .env
docker-compose exec app php artisan key:generate
```

Expected: `.env` updated with `APP_KEY=base64:...`

- [ ] **Step 3: Commit**

```bash
git add .env.example .env
git commit -m "chore: add Laravel environment configuration"
```

---

## Task 3: Create Single-User Auth Guard

**Files:**
- Create: `app/Providers/SingleUserAuthServiceProvider.php`
- Create: `app/Http/Controllers/Auth/LoginController.php`
- Modify: `bootstrap/app.php` (register provider)
- Modify: `config/auth.php` (add guard + provider)

- [ ] **Step 1: Create auth config**

Edit `config/auth.php`:

```php
// Add to 'guards' array
'single' => [
    'driver' => 'session',
    'provider' => 'single-user',
],

// Add to 'providers' array
'single-user' => [
    'driver' => 'single-user',
],
```

- [ ] **Step 2: Create custom User provider**

`app/Auth/SingleUserProvider.php`:

```php
<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class SingleUserProvider implements UserProvider
{
    public function retrieveById($identifier): ?Authenticatable
    {
        $username = config('auth.credentials.username');
        if ($identifier === $username) {
            return new SingleUser($username);
        }
        return null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->retrieveById($identifier);
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $expectedHash = config('auth.credentials.password_hash');
        return password_verify($credentials['password'], $expectedHash);
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        // No-op for single-user
    }
}
```

- [ ] **Step 3: Create SimpleUser class**

`app/Auth/SingleUser.php`:

```php
<?php

namespace App\Auth;

use Illuminate\Contracts\Auth\Authenticatable;

class SingleUser implements Authenticatable
{
    public function __construct(public string $username) {}

    public function getAuthIdentifierName(): string { return 'username'; }
    public function getAuthIdentifier(): string { return $this->username; }
    public function getAuthPassword(): string { return ''; }
    public function getRememberToken(): ?string { return null; }
    public function setRememberToken($value): void {}
    public function getRememberTokenName(): ?string { return null; }
    public function getAuthPasswordName(): string { return 'password'; }
}
```

- [ ] **Step 4: Create service provider**

`app/Providers/SingleUserAuthServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Auth\SingleUserProvider;

class SingleUserAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend('auth.provider.single-user', function () {
            return new SingleUserProvider();
        });
    }

    public function boot(): void
    {
        // Config loaded from auth.php
    }
}
```

- [ ] **Step 5: Register provider in bootstrap/app.php**

```php
use App\Providers\SingleUserAuthServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([SingleUserAuthServiceProvider::class])
    ->withRouting(...)
    ->create();
```

- [ ] **Step 6: Add credentials to config/auth.php**

```php
'credentials' => [
    'username' => env('APP_USER', 'admin'),
    'password_hash' => env('APP_PASSWORD_HASH'),
],
```

- [ ] **Step 7: Commit**

```bash
git add app/Auth/ app/Providers/ config/auth.php bootstrap/app.php
git commit -m "feat: implement single-user auth guard with env credentials"
```

---

## Task 4: Create Login/Logout Controllers

**Files:**
- Create: `app/Http/Controllers/Auth/LoginController.php`
- Create: `resources/views/auth/login.blade.php`

- [ ] **Step 1: Create LoginController**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm(): \Illuminate\View\View
    {
        return view('auth.login');
    }

    public function login(Request $request): \Illuminate\Http\RedirectResponse
    {
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (Auth::guard('single')->attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'username' => 'Invalid username or password.',
        ])->onlyInput('username');
    }

    public function logout(Request $request): \Illuminate\Http\RedirectResponse
    {
        Auth::guard('single')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
```

- [ ] **Step 2: Create login view**

`resources/views/auth/login.blade.php` (Bootstrap 5.3, dark theme):

```blade
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - phpKanMaster</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-card { width: 100%; max-width: 400px; }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="card-body p-4">
            <h2 class="card-title text-center mb-4">phpKanMaster Login</h2>

            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="/login">
                @csrf
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                    <label class="form-check-label" for="remember">Remember me</label>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/Auth/ resources/views/auth/
git commit -m "feat: add login/logout controller and view"
```

---

## Task 5: Configure Routes

**Files:**
- Modify: `routes/web.php`
- Create: `routes/console.php` (for scheduler)

- [ ] **Step 1: Define web routes**

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;

// Auth routes (public)
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login']);
Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

// Protected routes
Route::middleware('auth:single')->group(function () {
    Route::get('/', function () {
        return view('kanban');
    })->name('kanban');
});
```

- [ ] **Step 2: Create console routes for scheduler**

```php
<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:send')->everyMinute();
```

- [ ] **Step 3: Commit**

```bash
git add routes/web.php routes/console.php
git commit -m "feat: configure auth and kanban routes"
```

---

## Task 6: Create Sessions Table Migration

**Files:**
- Create: `database/migrations/*_create_sessions_table.php`

- [ ] **Step 1: Generate migration**

```bash
docker-compose exec app php artisan session:table
```

Expected: Migration created in `database/migrations/`.

- [ ] **Step 2: Run migrations**

```bash
docker-compose exec app php artisan migrate
```

Expected: `sessions` table created.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/*_create_sessions_table.php
git commit -m "feat: add sessions table migration"
```

---

## Task 7: Create Base Kanban View (SPA Shell)

**Files:**
- Create: `resources/views/kanban.blade.php`

- [ ] **Step 1: Create minimal SPA shell**

```blade
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>phpKanMaster</title>

    {{-- Bootstrap 5.3 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- Custom CSS --}}
    <link href="/assets/css/app.css" rel="stylesheet">
</head>
<body>
    {{-- Navbar --}}
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">phpKanMaster</span>
            <div class="d-flex">
                <a href="/logout" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    {{-- Main Board --}}
    <main class="container-fluid mt-3">
        <div id="board" class="row flex-nowrap overflow-auto">
            {{-- Columns rendered by App.Board --}}
        </div>
    </main>

    {{-- jQuery 4.0 --}}
    <script src="https://code.jquery.com/jquery-4.0.0.min.js"></script>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    {{-- App JS --}}
    <script>
        window.POSTGREST_URL = '{{ env('PGRST_BASE_URL', '/api') }}';
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
```

- [ ] **Step 2: Create placeholder CSS**

`public/assets/css/app.css`:

```css
/* phpKanMaster - Board Styles */
#board {
    min-height: calc(100vh - 120px);
}

.column {
    min-width: 300px;
    max-width: 300px;
    background: #2b3035;
    border-radius: 8px;
    padding: 12px;
    margin-right: 16px;
}

.card-task {
    background: #3a414a;
    border: none;
    border-left: 4px solid #6c757d;
    margin-bottom: 12px;
    cursor: grab;
}

.card-task:active {
    cursor: grabbing;
}

.card-task.priority-high {
    border-left-color: #dc3545;
}

.card-task.priority-medium {
    border-left-color: #ffc107;
}

.card-task.priority-low {
    border-left-color: #28a745;
}
```

- [ ] **Step 3: Create placeholder JS**

`public/assets/js/app.js`:

```javascript
/**
 * phpKanMaster - Main Application
 */

window.App = {
    Api: {},
    Board: {},
    Modal: {},
    DnD: {},
    Alerts: {}
};

console.log('phpKanMaster initialized');
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/kanban.blade.php public/assets/
git commit -m "feat: add kanban SPA shell with placeholder assets"
```

---

## Task 8: Generate Password Hash and Update .env.example

**Files:**
- Modify: `.env.example`

- [ ] **Step 1: Generate bcrypt hash**

```bash
docker-compose exec app php artisan tinker --execute="echo password_hash('admin', PASSWORD_BCRYPT);"
```

Or use PHP directly:

```bash
docker-compose exec app php -r "echo password_hash('admin', PASSWORD_BCRYPT) . PHP_EOL;"
```

- [ ] **Step 2: Update .env.example with example hash**

```dotenv
APP_USER=admin
APP_PASSWORD_HASH=$2y$12$... (replace with generated hash)
```

- [ ] **Step 3: Document in README**

Add to `README.md`:

```markdown
## Setup

1. Copy `.env.example` to `.env`
2. Generate password hash:
   ```bash
   php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
   ```
3. Update `APP_PASSWORD_HASH` in `.env`
```

- [ ] **Step 4: Commit**

```bash
git add .env.example README.md
git commit -m "docs: add password hash generation instructions"
```

---

## Task 9: Verify Auth Flow End-to-End

**Files:**
- No file changes — validation task

- [ ] **Step 1: Start Docker stack**

```bash
docker-compose up -d
```

- [ ] **Step 2: Access login page**

```bash
curl -I http://localhost/login
```

Expected: `200 OK`

- [ ] **Step 3: Test login redirect**

```bash
curl -I http://localhost/
```

Expected: `302 Found` redirecting to `/login`

- [ ] **Step 4: Test authenticated access**

```bash
# Using cookies after login (manual test in browser)
```

Expected: Kanban board loads at `/`

- [ ] **Step 5: Test logout**

```bash
curl -I http://localhost/logout
```

Expected: Redirects to `/login`

- [ ] **Step 6: Document verification**

Add to `README.md`:

```markdown
## Verification

- [x] Login page accessible at `/login`
- [x] Unauthenticated `/` redirects to login
- [x] Authenticated users see kanban board
- [x] Logout invalidates session
```

- [ ] **Step 7: Commit**

```bash
git add README.md
git commit -m "docs: document auth verification results"
```

---

## Self-Review Checklist

- [ ] All file paths are exact and create-able
- [ ] No placeholders (TBD, TODO, etc.)
- [ ] Each task is independently testable
- [ ] Commands include expected output
- [ ] No references to undefined types/functions
- [ ] Auth guard uses `single` guard name consistently
- [ ] Session driver set to `database` for persistence
- [ ] CSRF protection enabled for POST /login

---

## Next Plan

After this plan completes, the next plan is **PostgREST Schema + API Integration** which:
1. Creates database schema (tasks, categories, task_files tables)
2. Configures PostgREST anon role permissions
3. Implements `App.Api` JavaScript module
4. Tests CRUD operations via browser console
