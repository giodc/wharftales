ARG PHP_VERSION=8.3
FROM wordpress:6.4-php${PHP_VERSION}-fpm

# Install optimization tools and extensions
RUN apt-get update && apt-get install -y \
    nginx \
    redis-tools \
    libmemcached-dev \
    libz-dev \
    libpq-dev \
    libjpeg-dev \
    libpng-dev \
    libfreetype6-dev \
    libssl-dev \
    libmcrypt-dev \
    libzip-dev supervisor \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions for performance
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        mysqli \
        pdo_mysql \
        zip \
        opcache \
        bcmath \
        exif

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Memcached extension
RUN pecl install memcached && docker-php-ext-enable memcached

# Copy optimized PHP configuration
COPY php.ini /usr/local/etc/php/conf.d/wordpress.ini

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/sites-available/default


# Copy WordPress files from source
RUN cp -a /usr/src/wordpress/. /var/www/html/ && chown -R www-data:www-data /var/www/html

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Nginx to run on non-privileged port 8080
RUN sed -i 's/listen 80/listen 8080/' /etc/nginx/sites-available/default \
    && sed -i 's/listen \[::\]:80/listen [::]:8080/' /etc/nginx/sites-available/default

# Create necessary directories with proper permissions for www-data
RUN mkdir -p /var/log/nginx /var/lib/nginx /run /var/log/supervisor \
    && chown -R www-data:www-data /var/log/nginx /var/lib/nginx /run /var/log/supervisor \
    && chmod -R 755 /var/log/nginx /var/lib/nginx /run /var/log/supervisor

# Copy supervisor configuration
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY start-wordpress.sh /usr/local/bin/start-wordpress.sh
COPY create-wp-config.sh /usr/local/bin/create-wp-config.sh

# Make scripts executable
RUN chmod +x /usr/local/bin/start-wordpress.sh /usr/local/bin/create-wp-config.sh

# Switch to non-root user
USER www-data:www-data

EXPOSE 8080 9000

CMD ["create-wp-config.sh"]
