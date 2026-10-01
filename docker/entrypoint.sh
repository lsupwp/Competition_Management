#!/bin/bash
set -e

# Ensure security modules are enabled (safe if already on)
a2enmod rewrite headers >/dev/null 2>&1 || true

if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Upload dirs must be writable by Apache (www-data).
# Bind mounts (WSL/Docker Desktop) often keep host UID after git checkout,
# so chown alone is not enough — force directory mode so www-data can write.
mkdir -p uploads/avatars uploads/teams
chown -R www-data:www-data uploads 2>/dev/null || true
chmod -R u+rwX,g+rwX,o+rwX uploads 2>/dev/null || true
find uploads -type d -exec chmod 777 {} \; 2>/dev/null || true
find uploads -type f -exec chmod 666 {} \; 2>/dev/null || true

exec "$@"
