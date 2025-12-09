ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim \
    unzip \
    git \
    curl \
    libzip-dev \
    libonig-dev \
    supervisor \
    nginx \
    gnupg \
    && rm -rf /var/lib/apt/lists/*

# Install Node.js (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm@latest

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl gd opcache bcmath

# Install Composer globally
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer
RUN chmod +x /usr/local/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy existing application directory permissions
RUN groupadd -g 1000 www
RUN useradd -u 1000 -ms /bin/bash -g www www

# Copy existing application directory contents
COPY --chown=www:www . /var/www/html

# Copy Nginx config
COPY nginx.conf /etc/nginx/sites-available/default

# Copy PHP-FPM config
COPY php.ini /usr/local/etc/php/conf.d/laravel.ini

# Copy supervisor config
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Configure Nginx to run on non-privileged port 8080
RUN sed -i 's/listen 80/listen 8080/' /etc/nginx/sites-available/default \
    && sed -i 's/listen \[::\]:80/listen [::]:8080/' /etc/nginx/sites-available/default

# Create necessary directories with proper permissions for www user
RUN mkdir -p /var/log/nginx /var/lib/nginx /run /var/log/supervisor \
    && chown -R www:www /var/www/html /var/log/nginx /var/lib/nginx /run /var/log/supervisor \
    && chmod -R 755 /var/log/nginx /var/lib/nginx /run /var/log/supervisor

# Switch to non-root user
# Note: Supervisor will run as www user and manage nginx/php-fpm processes
USER www:www

# Expose ports (8080 for nginx, 9000 for php-fpm)
EXPOSE 9000 8080
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]