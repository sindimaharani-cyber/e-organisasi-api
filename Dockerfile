FROM dunglas/frankenphp:php8.4

RUN install-php-extensions \
    gd \
    zip \
    pdo_mysql

RUN apt-get update && apt-get install -y unzip zip

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install --no-dev --optimize-autoloader --no-scripts

COPY . .

RUN mkdir -p storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

RUN chmod -R 775 storage bootstrap/cache

CMD ["frankenphp", "php-server", "--root", "public"]