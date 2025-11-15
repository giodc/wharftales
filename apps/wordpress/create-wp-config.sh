#\!/bin/bash
# This script creates wp-config.php from environment variables if it doesn't exist

if [ \! -f /var/www/html/wp-config.php ]; then
    if [ -n "$WORDPRESS_DB_HOST" ]; then
        # Create wp-config.php from wp-config-sample.php
        cp /var/www/html/wp-config-sample.php /var/www/html/wp-config.php
        
        # Add SSL and URL configuration at the beginning if HTTPS is enabled
        if [ -n "$WORDPRESS_URL" ] && [[ "$WORDPRESS_URL" == https* ]]; then
            # Insert SSL configuration right after <?php
            sed -i '2i\
// Handle SSL behind reverse proxy (Traefik)\
if (!empty($_SERVER['"'"'HTTP_X_FORWARDED_PROTO'"'"']) && $_SERVER['"'"'HTTP_X_FORWARDED_PROTO'"'"'] === '"'"'https'"'"') {\
    $_SERVER['"'"'HTTPS'"'"'] = '"'"'on'"'"';\
}\
\
// Force SSL for admin and login\
define('"'"'FORCE_SSL_ADMIN'"'"', true);\
\
// Set WordPress URLs\
define('"'"'WP_HOME'"'"', '"'"$WORDPRESS_URL"'"');\
define('"'"'WP_SITEURL'"'"', '"'"$WORDPRESS_URL"'"');\
' /var/www/html/wp-config.php
        elif [ -n "$WORDPRESS_URL" ]; then
            # HTTP site - just set URLs
            sed -i '2i\
// Set WordPress URLs\
define('"'"'WP_HOME'"'"', '"'"$WORDPRESS_URL"'"');\
define('"'"'WP_SITEURL'"'"', '"'"$WORDPRESS_URL"'"');\
' /var/www/html/wp-config.php
        fi
        
        # Replace database settings
        sed -i "s/database_name_here/$WORDPRESS_DB_NAME/" /var/www/html/wp-config.php
        sed -i "s/username_here/$WORDPRESS_DB_USER/" /var/www/html/wp-config.php
        sed -i "s/password_here/$WORDPRESS_DB_PASSWORD/" /var/www/html/wp-config.php
        sed -i "s/localhost/$WORDPRESS_DB_HOST/" /var/www/html/wp-config.php
        
        # Generate unique keys and salts using PHP
        php -r "
        \$keys = array('AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT');
        \$config = file_get_contents('/var/www/html/wp-config.php');
        foreach (\$keys as \$key) {
            \$secret = bin2hex(random_bytes(32));
            \$config = preg_replace(
                '/define\(\s*\'' . \$key . '\',\s*\'put your unique phrase here\'\s*\);/',
                'define( \\'' . \$key . '\\', \\'' . \$secret . '\\' );',
                \$config
            );
        }
        file_put_contents('/var/www/html/wp-config.php', \$config);
        "
        
        chown www-data:www-data /var/www/html/wp-config.php
        chmod 640 /var/www/html/wp-config.php
    fi
fi

# Start supervisor
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
