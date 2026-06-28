#!/usr/bin/env bash
set -e

# Replace the ${PORT} variable in Apache configuration with the actual PORT environment variable provided by Render.
# If PORT is not set, default to 80.
LISTEN_PORT=${PORT:-80}
sed -i "s/\${PORT}/$LISTEN_PORT/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

echo "Caching configurations..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

echo "Running migrations..."
php artisan migrate --force || { echo "WARNING: Migration failed, but continuing startup..."; }

echo "Starting Apache..."
apache2-foreground
