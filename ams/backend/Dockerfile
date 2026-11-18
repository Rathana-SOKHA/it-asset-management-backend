FROM node:22-alpine AS frontend
WORKDIR /app
COPY package*.json ./
RUN npm install
COPY . .
RUN npm run build

FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts
COPY . .
RUN composer dump-autoload --no-dev --optimize

FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
        libicu-dev libzip-dev unzip \
    && docker-php-ext-install bcmath intl pdo_mysql zip \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=dependencies /app/vendor ./vendor
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN rm -f bootstrap/cache/packages.php bootstrap/cache/services.php \
    && composer dump-autoload --no-dev --optimize \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80
