FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    gd \
    zip

RUN apt-get update && apt-get install -y unzip zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN php artisan optimize:clear

CMD ["frankenphp", "php-server", "--root", "public"]