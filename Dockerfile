# syntax=docker/dockerfile:1
#
# One image per role, built from one file:
#   app  = php-fpm with the Laravel site + plain-PHP API. Also runs the queue worker and the scheduler.
#   web  = Caddy: terminates HTTPS, serves static files, hands PHP to `app`.

# --- 1. PHP dependencies (production only) ---------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# --- 2. Front-end build (Vite + Tailwind) ----------------------------------
FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json .npmrc ./
RUN npm ci --ignore-scripts
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# --- 3. App: php-fpm -------------------------------------------------------
FROM php:8.4-fpm-alpine AS app
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions pdo_mysql zip intl bcmath opcache pcntl \
    && apk add --no-cache su-exec \
    && rm /usr/local/bin/install-php-extensions

WORKDIR /var/www/html
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

COPY --chown=www-data:www-data . .
COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build
RUN mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

# --- 4. Web: Caddy with automatic HTTPS ------------------------------------
FROM caddy:2-alpine AS web
COPY docker/Caddyfile /etc/caddy/Caddyfile
COPY --from=app /var/www/html/public /var/www/html/public
COPY --from=app /var/www/html/api/public /var/www/html/api/public

# --- 5. OMR: Audiveris reads PDF scores ------------------------------------
# Separate image because Audiveris ships as an Ubuntu x86-64 package with its own Java runtime.
# It shares the `storage` volume with the app and handles one PDF at a time.
FROM --platform=linux/amd64 ubuntu:24.04 AS omr
ARG AUDIVERIS_VERSION=5.11.0
RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl libgtk-3-0t64 tesseract-ocr tesseract-ocr-eng tesseract-ocr-deu tesseract-ocr-fra tesseract-ocr-ita \
    && curl -fsSL -o /tmp/audiveris.deb "https://github.com/Audiveris/audiveris/releases/download/${AUDIVERIS_VERSION}/Audiveris-${AUDIVERIS_VERSION}-ubuntu24.04-x86_64.deb" \
    && mkdir -p /usr/share/applications /usr/share/desktop-directories /etc/xdg/menus/applications-merged \
    && apt-get install -y /tmp/audiveris.deb \
    && rm -rf /tmp/audiveris.deb /var/lib/apt/lists/*
# Audiveris uses Tesseract's legacy engine; the apt language packs only contain the newer one
# ("Tesseract (legacy) engine requested, but components are not present"). This file has both.
RUN curl -fsSL -o /usr/share/tesseract-ocr/5/tessdata/eng.traineddata https://github.com/tesseract-ocr/tessdata/raw/main/eng.traineddata
ENV TESSDATA_PREFIX=/usr/share/tesseract-ocr/5/tessdata
COPY docker/omr/watch.sh /usr/local/bin/omr-watch
RUN chmod +x /usr/local/bin/omr-watch
CMD ["omr-watch"]
