FROM php:8.3-fpm

# System deps for popular PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev unzip git bash \
 && rm -rf /var/lib/apt/lists/*

# Core extensions you need for Symfony + Doctrine + Intl + Zip
RUN docker-php-ext-configure intl \
 && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_mysql \
    intl \
    zip \
    opcache

# (optional) Xdebug for local dev
# RUN pecl install xdebug && docker-php-ext-enable xdebug

WORKDIR /var/www/app
