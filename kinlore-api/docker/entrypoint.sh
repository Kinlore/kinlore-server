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
php artisan migrate --force

# Check if migrations succeeded
if [ $? -eq 0 ]; then
  echo "✅ Migrations completed successfully!"
else
  echo "❌ Migrations failed!"
  exit 1
fi

# Run seeders (UserSeeder is idempotent - skips if user exists)
echo "🌱 Running database seeders..."
php artisan db:seed --force
echo "✅ Seeders completed!"

# Create MinIO bucket if it doesn't exist (optional)
echo "🪣 Checking MinIO bucket..."
php artisan tinker --execute="
try {
    if (!Storage::disk('seaweedfs')->exists('.kinlore-initialized')) {
        Storage::disk('seaweedfs')->put('.kinlore-initialized', 'Bucket initialized at ' . now());
        echo 'MinIO bucket initialized\n';
    } else {
        echo 'MinIO bucket already exists\n';
    }
} catch (Exception \$e) {
    echo 'MinIO check skipped: ' . \$e->getMessage() . '\n';
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

# Start Apache in foreground
echo "✅ Starting Apache..."
exec apache2-foreground
