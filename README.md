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

Do not run `DatabaseSeeder` or `SalonDemoSeeder` in production.
