#!/bin/bash
# Pre-build PHP base images for faster site creation

set -e

echo "Building PHP base images for wharftales..."

PHP_VERSIONS=("7.4" "8.0" "8.1" "8.2" "8.3" "8.4")

for VERSION in "${PHP_VERSIONS[@]}"; do
    echo ""
    echo "==================================="
    echo "Building PHP $VERSION base image..."
    echo "==================================="
    
    # Build PHP image
    docker build \
        --build-arg PHP_VERSION=$VERSION \
        -t wharftales/php:$VERSION-apache \
        -f /opt/wharftales/apps/php/Dockerfile \
        /opt/wharftales/apps/php/ || echo "Warning: PHP $VERSION build failed (image may not exist)"
    
    # Build WordPress image
    docker build \
        --build-arg PHP_VERSION=$VERSION \
        -t wharftales/wordpress:$VERSION-fpm \
        -f /opt/wharftales/apps/wordpress/Dockerfile \
        /opt/wharftales/apps/wordpress/ || echo "Warning: WordPress PHP $VERSION build failed (image may not exist)"
    
    # Build Laravel image
    docker build \
        --build-arg PHP_VERSION=$VERSION \
        -t wharftales/laravel:$VERSION-fpm \
        -f /opt/wharftales/apps/laravel/Dockerfile \
        /opt/wharftales/apps/laravel/ || echo "Warning: Laravel PHP $VERSION build failed (image may not exist)"
done

echo ""
echo "==================================="
echo "Base images built successfully!"
echo "==================================="
echo ""
echo "Available images:"
docker images | grep wharftales
