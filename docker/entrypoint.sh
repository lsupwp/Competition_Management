#!/bin/bash
set -e

if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

exec "$@"
