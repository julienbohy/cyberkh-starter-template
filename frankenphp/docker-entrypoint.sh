#!/bin/sh
set -e

# When the container serves the application (the default command, or frankenphp): in dev, the
# bind-mounted checkout may not have its dependencies yet.
if [ "${1#-}" != "$1" ] || [ "$1" = 'frankenphp' ]; then
    if [ -z "$(ls -A 'vendor/' 2>/dev/null)" ]; then
        composer install --prefer-dist --no-progress --no-interaction
    fi

    # The CSS, before the first page: in dev, var/ is a volume of its own, empty at first.
    if [ "$APP_ENV" = 'dev' ] && [ ! -f var/tailwind/app.built.css ]; then
        php bin/console tailwind:build
    fi
fi

exec docker-php-entrypoint "$@"
