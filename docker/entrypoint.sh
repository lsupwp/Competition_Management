#!/bin/bash
set -e

if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Create uploads directories if not exist and set permissions
if [ ! -d "uploads/avatars" ]; then
    mkdir -p uploads/avatars
fi
if [ ! -d "uploads/teams" ]; then
    mkdir -p uploads/teams
fi
chown -R www-data:www-data uploads
chmod -R 775 uploads

exec "$@"
