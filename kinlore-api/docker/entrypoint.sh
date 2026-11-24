#!/bin/bash
set -e

echo "🚀 Starting Kinlore API..."

# Wait for database to be ready
echo "⏳ Waiting for database..."
until php artisan db:show 2>/dev/null; do
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

echo "✅ Initialization complete, starting Caddy + PHP-FPM..."

# Artisan above ran as root; PHP-FPM and the queue worker run as www-data and must write here
chown -R www-data:www-data storage bootstrap/cache

# Start Supervisor (Caddy, PHP-FPM and the queue worker)
exec /usr/bin/supervisord -c /etc/supervisord.conf
