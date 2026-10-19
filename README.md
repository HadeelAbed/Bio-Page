# Bio Page — Laravel Bio-Link Platform

A high-end editorial bio-link platform (think Linktree) with glassmorphism
cards and gradient-masking. Pure **Laravel Blade + Tailwind CDN + Alpine.js**
— no Inertia, no SPA.

## Tech stack

| Layer    | Choice                                              |
|----------|-----------------------------------------------------|
| Backend  | Laravel 12 (MVC, Eloquent, session auth)            |
| Database | **MySQL / MariaDB** (`bio_page` dev, `bio_page_testing` for tests) |
| Frontend | Blade templates, Tailwind CDN, Alpine.js            |
| Storage  | `public` disk for header-image uploads              |

## Quick start

```bash
# 1. Install PHP dependencies
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Database — MySQL (local dev + production)
#    The project runs on MySQL. With XAMPP's MariaDB running locally,
#    only the values below are needed (root has no password by default):
#        DB_CONNECTION=mysql
#        DB_HOST=127.0.0.1
#        DB_PORT=3306
#        DB_DATABASE=bio_page          # dev database
#        DB_USERNAME=root
#        DB_PASSWORD=                  # empty for local XAMPP
#
#    Create the two databases once (dev + isolated test database):
#        mysql -u root -e "CREATE DATABASE IF NOT EXISTS bio_page CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#        mysql -u root -e "CREATE DATABASE IF NOT EXISTS bio_page_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#
#    The automated suite runs against bio_page_testing on MySQL by default
#    (see phpunit.xml). RefreshDatabase rebuilds it per test, so your dev
#    database is never touched by tests.
#    CI fallback without MySQL: DB_CONNECTION=sqlite DB_DATABASE=':memory:'
#    (the single collation-specific test self-skips on SQLite).

php artisan migrate --seed
php artisan storage:link   # public URL access for header uploads
php artisan serve
```

Seeded demo account — email `test@example.com`, password `password`.

| Surface   | URL                           |
|-----------|-------------------------------|
| Public bio| http://127.0.0.1:8000/hadeel  |
| Login     | http://127.0.0.1:8000/login   |
| Register  | http://127.0.0.1:8000/register|
| Dashboard | http://127.0.0.1:8000/dashboard (auth only) |

## Architecture map (5 development phases)

| Phase | Subject                                   | Files |
|-------|-------------------------------------------|-------|
| 1     | DB: `username` on users, `profiles`, `links` | `database/migrations/2026_10_19_00000*.php` |
| 2     | Models + accessor + factory/seeder        | `app/Models/*`, `database/factories/*`, `database/seeders/*` |
| 3     | Zero-trust controllers + FormRequest      | `app/Http/Requests/*`, `app/Http/Controllers/*` |
| 4     | Routes + reserved-slug protection         | `routes/web.php` |
| 5     | Blade: `bio`, `dashboard`, auth views     | `resources/views/*` |

Design references live in `resources/prototypes/` (the original static mockups).

## Security model (zero-trust)

1. **No `user_id` is ever read from the request.** All mutations are scoped
   to `$request->user()`; ownership comes from the session, never the body.
2. **Link ids are re-resolved** through `$user->links()->whereKey(...)` —
   a forged id belonging to another account matches zero rows and falls
   through to an INSERT under the attacker's own account. Verified by E2E.
3. **Colours are allow-listed** to `/^#[0-9A-Fa-f]{6}$/` — they are interpolated
   into inline styles, so anything else would be a CSS-injection vector.
4. **Uploads are signature-checked** (`image` + mimes + 2 MB), stored under
   UUID names, and old files are garbage-collected on replacement.
5. **The `/{username}` wildcard cannot swallow system slugs** — see the
   negative-lookahead `->where()` constraint at the bottom of `routes/web.php`.
