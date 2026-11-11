ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli

RUN docker-php-ext-install mysqli
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
COPY composer.json ./
COPY src/ ./src/
COPY demo/ ./demo/
COPY tests/ ./tests/
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts

CMD ["sh", "tests/check.sh"]
