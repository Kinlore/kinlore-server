#!/bin/bash
set -e

echo "🚀 Starting Kinlore API..."

# A config cache from the build folder or an earlier start holds old settings (an old APP_URL, for example).
# Remove it before the first artisan call, so every start reads the environment of this container.
rm -f bootstrap/cache/config.php

# An empty bind mount (deploy/docker/bind-mounts.yml) has none of the folders Laravel writes to
mkdir -p storage/app/private storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views storage/logs

# Wait for database to be ready
echo "⏳ Waiting for database..."
# About 2 minutes, then stop with the error: a wrong DB_PASSWORD never gets better by waiting
tries=0
# artisan prints the connection error on stdout, so keep both streams
until db_error="$(php artisan db:show 2>&1)"; do
  tries=$((tries + 1))
  if [ "$tries" -ge 60 ]; then
    echo "❌ Database not reachable after $tries tries. Last error:" >&2
    echo "$db_error" >&2
    exit 1
  fi
  echo "   Database not ready, retrying in 2 seconds..."
  sleep 2
done
echo "✅ Database is ready!"

# Run migrations
echo "🔄 Running database migrations..."
php artisan migrate --force   # set -e stops the start when this fails
echo "✅ Migrations completed successfully!"

# Run seeders (UserSeeder skips when any user exists)
echo "🌱 Running database seeders..."
php artisan db:seed --force
echo "✅ Seeders completed!"

# Without SETUP_TOKEN, the setup page asks for a generated token: print it for 'docker compose logs api'
if [ -z "$SETUP_TOKEN" ]; then
  echo "🔑 Checking the setup token..."
  php artisan kinlore:setup-token
fi

# Create the storage bucket if it doesn't exist (optional)
echo "🪣 Checking storage bucket..."
php artisan tinker --execute="
try {
    if (!Storage::disk('seaweedfs')->exists('.kinlore-initialized')) {
        Storage::disk('seaweedfs')->put('.kinlore-initialized', 'Bucket initialized at ' . now());
        echo 'Storage bucket initialized\n';
    } else {
        echo 'Storage bucket already exists\n';
    }
} catch (Exception \$e) {
    echo 'Storage check skipped: ' . \$e->getMessage() . '\n';
}
"

# Cache configuration (production optimization)
if [ "$APP_ENV" = "production" ]; then
  echo "⚡ Caching configuration for production..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  echo "✅ Cache optimizations applied!"
fi

# Clear caches (development)
if [ "$APP_ENV" = "local" ]; then
  echo "🧹 Clearing caches for development..."
  php artisan config:clear
  php artisan route:clear
  php artisan view:clear
fi

# Web server mode (see docker/Caddyfile)
if [ -n "$KINLORE_DOMAIN" ]; then
  export KINLORE_SITE="$KINLORE_DOMAIN"
  echo "🔒 Serving https://$KINLORE_DOMAIN with automatic HTTPS"
else
  export KINLORE_SITE=":80"
  echo "🌐 Serving plain HTTP on port 80 (put a reverse proxy in front for HTTPS)"
fi
# Laravel wants commas, Caddy wants spaces; "*" means every address
CADDY_TRUSTED_PROXIES="$(echo "${TRUSTED_PROXIES:-private_ranges}" | tr ',' ' ')"
[ "$CADDY_TRUSTED_PROXIES" = "*" ] && CADDY_TRUSTED_PROXIES="0.0.0.0/0 ::/0"
export CADDY_TRUSTED_PROXIES

# The address in invitation and setup links. Compose takes APP_URL from the shell that runs it, then from the .env
# next to docker-compose.yml; kinlore-api/.env does not set it.
php artisan tinker --execute="
echo '🌐 Public address (APP_URL): ' . config('app.url') . PHP_EOL;
if (! App\\Support\\PublicUrl::isConfigured()) {
    echo '⚠️  APP_URL is a placeholder. Links use the address of each request. Set APP_URL in the .env next to docker-compose.yml.' . PHP_EOL;
}
" || true

echo "✅ Initialization complete, starting Caddy + PHP-FPM..."

# Artisan above ran as root; PHP-FPM and the queue worker run as www-data and must write here
chown -R www-data:www-data storage bootstrap/cache

# Start Supervisor (Caddy, PHP-FPM and the queue worker)
exec /usr/bin/supervisord -c /etc/supervisord.conf
