ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-apache

# Install PHP extensions
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        zip \
        gd \
        mbstring \
        xml \
        opcache

# Enable Apache modules
RUN a2enmod rewrite ssl

# Copy optimized PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/

# Set working directory
WORKDIR /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache to run on non-privileged port 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/:80/:8080/' /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

# Switch to non-root user
USER www-data:www-data

EXPOSE 8080