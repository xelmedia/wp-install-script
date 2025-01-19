FROM php:8.2-cli-alpine

# Install necessary dependencies and Xdebug
RUN apk add --no-cache linux-headers \
    $PHPIZE_DEPS \
    curl-dev \
    libzip-dev \
    libxml2-dev \
    oniguruma-dev && \
    pecl install xdebug && \
    docker-php-ext-enable xdebug && \
    docker-php-ext-install curl zip dom mbstring

# Create a non-root user
RUN adduser -D -s /bin/sh appuser

# Set the working directory
WORKDIR /var/www/html/

# Copy project files
COPY . /var/www/html/
COPY resources/composer /usr/local/bin/composer
RUN chown -R appuser:appuser /var/www/html

# Ensure composer is executable before switching users
RUN chmod +x /usr/local/bin/composer

# Switch to the non-root user
USER appuser

# Verify Composer version
RUN /usr/local/bin/composer --version

WORKDIR /var/www/html/