#!/bin/bash
# Fix Traefik v3.0 to v2.11 on remote servers
# Run this on the affected server

set -e

echo "🔧 Fixing Traefik version on remote server..."
echo ""

cd /opt/wharftales

# Pull latest changes
echo "📥 Pulling latest changes from GitHub..."
git pull origin master

# Force fix if still v3.0
if grep -q "traefik:v3.0" docker-compose.yml; then
    echo "⚠️  Still using v3.0, applying manual fix..."
    sed -i 's|image: traefik:v3.0|image: traefik:v2.11|g' docker-compose.yml
fi

# Verify
echo ""
echo "✓ Docker compose file:"
grep "image: traefik" docker-compose.yml

# Restart containers
echo ""
echo "🔄 Restarting containers with v2.11..."
docker-compose down
docker-compose pull traefik
docker-compose up -d

echo ""
echo "⏳ Waiting for containers to start..."
sleep 5

# Check for errors
echo ""
echo "📋 Checking Traefik logs for errors..."
if docker logs wharftales_traefik 2>&1 | grep -q "client version 1.24 is too old"; then
    echo "❌ ERROR: Still seeing Docker API errors!"
    docker logs wharftales_traefik --tail 10
    exit 1
else
    echo "✅ SUCCESS! No Docker API errors found"
    echo ""
    echo "Running container version:"
    docker inspect wharftales_traefik --format='{{.Config.Image}}'
    echo ""
    echo "🎉 Traefik v2.11 is now running!"
fi
