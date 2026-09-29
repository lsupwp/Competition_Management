#!/bin/bash
set -e

if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Create uploads directory if not exists and set permissions
if [ ! -d "uploads/avatars" ]; then
    mkdir -p uploads/avatars
fi
chown -R www-data:www-data uploads
chmod -R 775 uploads

exec "$@"
