FROM dunglas/frankenphp:php8.4

RUN install-php-extensions gd

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN php artisan package:discover --ansi

CMD ["frankenphp", "php-server", "--root", "public"]