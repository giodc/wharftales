FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite ssl

# Install required packages
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    git \
    unzip \
    zip \
    curl \
    gnupg \
    && docker-php-ext-install pdo pdo_sqlite pdo_mysql mysqli \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install Node.js and npm
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Copy application files
COPY . /var/www/html/

# Copy PHP configuration
COPY php-session.ini /usr/local/etc/php/conf.d/php-session.ini

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create necessary directories for the application
RUN mkdir -p /var/www/html/data /var/www/html/nginx/sites /var/www/html/ssl \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/nginx/sites /var/www/html/ssl

# Create app directories
RUN mkdir -p /app/apps/php/sites /app/apps/wordpress/sites /app/apps/laravel/sites /app/nginx/sites \
    && chown -R www-data:www-data /app

# Configure Apache to run on non-privileged port 8080
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:8080/' /etc/apache2/sites-available/000-default.conf \
    && sed -i 's/:80/:8080/' /etc/apache2/sites-available/default-ssl.conf 2>/dev/null || true

# Add www-data to docker group (use host's docker GID)
ARG DOCKER_GID=988
RUN groupadd -g ${DOCKER_GID} docker || groupmod -g ${DOCKER_GID} docker || true \
    && usermod -aG docker www-data

# Expose non-privileged port
EXPOSE 8080

# Don't switch to www-data here - Apache will run as www-data via its config
# but the container needs to start as root to access docker socket
CMD ["apache2-foreground"]
