#!/bin/sh
set -e

# Install/update composer dependencies if vendor is missing or composer.lock changed
if [ ! -d vendor ] || [ composer.lock -nt vendor/autoload.php ]; then
    echo "Installing composer dependencies..."
    composer install --optimize-autoloader --no-interaction
fi

# Ensure var directory exists with proper permissions
mkdir -p var/cache var/log
chmod -R 777 var

exec "$@"
