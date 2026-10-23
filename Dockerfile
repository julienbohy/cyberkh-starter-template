#syntax=docker/dockerfile:1

# The image of the application: FrankenPHP (Caddy and PHP) serves it, in dev and in prod.
# The Caddyfile is the one of the base image, configured by environment variables:
# https://frankenphp.dev/docs/docker/ and https://frankenphp.dev/docs/config/

FROM dunglas/frankenphp:1-php8.5 AS frankenphp_upstream

# What dev and prod share.
FROM frankenphp_upstream AS frankenphp_base

WORKDIR /app

# The cache and the logs of the container are its own: in dev, the checkout is bind-mounted over /app.
VOLUME /app/var/

RUN apt-get update && apt-get install -y --no-install-recommends \
        file \
        git \
        unzip \
    && rm -rf /var/lib/apt/lists/*

RUN set -eux; \
    install-php-extensions \
        @composer \
        apcu \
        intl \
        opcache \
        pdo_pgsql \
        zip \
    ;

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --link frankenphp/conf.d/10-app.ini $PHP_INI_DIR/conf.d/
COPY --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint

ENTRYPOINT ["docker-entrypoint"]
# Given again: a new ENTRYPOINT clears the CMD of the base image.
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]

HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1

# The image of the development: the code comes from the bind-mounted checkout.
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev
# Enabled from .env.local, with XDEBUG_MODE=debug.
ENV XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
    install-php-extensions \
        xdebug \
    ;

COPY --link frankenphp/conf.d/20-app.dev.ini $PHP_INI_DIR/conf.d/

# The image of the production: the code, the dependencies and the assets are in it.
FROM frankenphp_base AS frankenphp_prod

ENV APP_ENV=prod
# Every request goes through the long-running kernel of public/index.php (symfony/runtime).
ENV FRANKENPHP_CONFIG="worker ./public/index.php"
ENV FRANKENPHP_WORKER=1

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --link frankenphp/conf.d/20-app.prod.ini $PHP_INI_DIR/conf.d/

# The dependencies first, so that a change of the code does not install them again.
COPY --link composer.* symfony.* ./
RUN set -eux; \
    composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

COPY --link . ./
RUN rm -Rf frankenphp/

RUN set -eux; \
    mkdir -p var/cache var/log; \
    composer dump-autoload --classmap-authoritative --no-dev; \
    composer dump-env prod; \
    composer run-script --no-dev post-install-cmd; \
    php bin/console tailwind:build --minify; \
    php bin/console asset-map:compile; \
    chmod +x bin/console; \
    sync
