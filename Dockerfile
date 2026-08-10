FROM dunglas/frankenphp:1-php8.2

RUN install-php-extensions pdo_pgsql pgsql mbstring bcmath intl pcntl opcache

ENV PORT=80

WORKDIR /app

COPY . .

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
