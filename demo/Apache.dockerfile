FROM php:8.4-apache
RUN a2enmod rewrite
COPY --from=composer/composer:latest-bin /composer /usr/bin/composer
RUN pecl install xdebug && docker-php-ext-enable xdebug

WORKDIR /var/www/html
COPY composer.json .
RUN composer install