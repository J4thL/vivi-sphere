# Vivi Sphere

Salon website and admin dashboard.

- **Public site**: server-rendered Blade pages (`/`, `/stylists/{stylist}`, `/terms`, `/privacy`)
- **Dashboard**: React app served at `/app` (source: `resources/js/dashboard/`, see below)
- **API**: Laravel 12 + Sanctum (`routes/api.php`)

## Local setup

```bash
composer run setup   # installs deps, creates .env, key, migrates, builds assets
composer run dev     # server + queue + logs + vite
php artisan db:seed --class=SalonDemoSeeder   # optional demo data (local only)
```

## Checks

```bash
php artisan test            # PHP feature tests
./vendor/bin/pint --test    # PHP code style (run ./vendor/bin/pint to fix)
npm run lint                # JavaScript lint
npm run build               # production build
```

The same steps run on every push and pull request (`.github/workflows/ci.yml`).

## Dashboard code layout

`resources/js/dashboard/`

- `App.jsx`: login state, data loading, session handling
- `Dashboard.jsx`: the signed-in shell (sidebar, header) and all shared state and actions
- `tabs/`: one file per screen (Overview, Appointments, Clients, ...). Each receives the shared state as `ctx`
- `AuthPages.jsx`: login, register and password-reset screens
- `api.js`, `helpers.js`, `theme.jsx`, `ui.jsx`: API calls, data helpers, colors, shared components

## Deploy

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build && rm -rf node_modules
cp .env.example .env   # then set APP_ENV=production, APP_DEBUG=false, APP_URL, DB_*, MAIL_*
php artisan key:generate
php artisan migrate --force
php artisan config:cache route:cache view:cache
php artisan storage:link
```

### Security notes

- Responses carry security headers and a Content-Security-Policy (`app/Http/Middleware/SecurityHeaders.php`).
  If you add third-party scripts (analytics, chat widgets), allow their origins there.
- Behind a load balancer or proxy, configure trusted proxies so HTTPS is detected and HSTS is sent.
- API tokens expire after 7 days (`SANCTUM_EXPIRATION`), and a password reset signs out every device.
  When a token expires the dashboard returns to the login screen and says so.

Do not run `DatabaseSeeder` or `SalonDemoSeeder` in production.
