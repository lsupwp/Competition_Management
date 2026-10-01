#!/bin/bash
set -e

if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Always ensure upload dirs exist and are writable by Apache (www-data)
mkdir -p uploads/avatars uploads/teams
chown -R www-data:www-data uploads
chmod -R 775 uploads

exec "$@"
