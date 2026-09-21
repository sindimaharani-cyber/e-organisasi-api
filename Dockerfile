FROM dunglas/frankenphp:php8.4

RUN install-php-extensions gd

WORKDIR /app

COPY . .

RUN composer install --no-dev --optimize-autoloader

RUN php artisan config:cache
RUN php artisan route:cache
RUN php artisan view:cache

CMD ["frankenphp", "php-server", "--root", "public"]