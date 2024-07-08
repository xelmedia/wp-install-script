FROM php:8.2-cli-alpine

RUN apk add --update linux-headers

RUN apk add --no-cache $PHPIZE_DEPS \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug

RUN apk add curl-dev libzip-dev libxml2-dev oniguruma-dev
RUN docker-php-ext-install  \
    curl \
    zip \
    dom \
    mbstring

COPY . /var/www/html/
COPY resources/composer /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer
RUN /usr/local/bin/composer --version
WORKDIR /var/www/html/