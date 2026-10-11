# Vivi Sphere

Salon website and admin dashboard.

- **Public site**: server-rendered Blade pages (`/`, `/stylists/{stylist}`, `/terms`, `/privacy`)
- **Dashboard**: React app served at `/app` (source: `resources/js/salon-admin-dashboard.jsx`)
- **API**: Laravel 12 + Sanctum (`routes/api.php`)

## Local setup

```bash
composer run setup   # installs deps, creates .env, key, migrates, builds assets
composer run dev     # server + queue + logs + vite
php artisan db:seed --class=SalonDemoSeeder   # optional demo data (local only)
```

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

Do not run `DatabaseSeeder` or `SalonDemoSeeder` in production.
