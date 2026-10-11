# Inertia SSR

ValenceKit uses Inertia SSR to render the first page response on the server. This improves the initial HTML response and keeps Vue hydration on the client.

## Local development

From the project directory:

```bash
npm install
npm run build:ssr
php artisan inertia:start-ssr
```

Keep the SSR command running in a separate terminal. Check its status with:

```bash
php artisan inertia:check-ssr
```

Open `http://valencekit.test` in Chrome and confirm that:

- The page renders normally.
- The page title and content are present before Vue finishes loading.
- The browser console has no errors.
- The document request returns HTTP `200`.

After changing Vue or Inertia code, rebuild the SSR bundle:

```bash
npm run build:ssr
```

Stop the local SSR server when it is no longer needed:

```bash
php artisan inertia:stop-ssr
```

## Server deployment

Run these commands during deployment:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build:ssr
php artisan migrate --force
php artisan optimize
```

Run the SSR process with a process manager such as Supervisor or systemd. Do not depend on an open SSH terminal.

Example Supervisor configuration:

```ini
[program:valencekit-ssr]
command=php /var/www/valencekit/artisan inertia:start-ssr
directory=/var/www/valencekit
user=www-data
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/var/log/valencekit-ssr.log
```

After adding or changing the Supervisor configuration:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl restart valencekit-ssr
php artisan inertia:check-ssr
```

Laravel sends SSR requests to `http://127.0.0.1:13714`, as configured in `config/inertia.php`. Keep that port private and allow the web server to access it locally.

## Sourcemap warning

The `SOURCEMAP_BROKEN` warning from `@inertiajs/vite` is a debugging sourcemap warning. It does not prevent SSR, page rendering, or production builds. It can be ignored unless accurate browser source mapping is required.

