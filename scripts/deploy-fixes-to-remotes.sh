#!/bin/bash

# Deploy WharfTales Fixes to Multiple Remote Servers
# This script runs the fix script on all your remote servers

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo "=========================================="
echo "WharfTales Remote Deployment Script"
echo "=========================================="
echo ""

# List of servers (edit this with your servers)
SERVERS=(
    "root@server1.example.com"
    "root@server2.example.com"
    "root@server3.example.com"
)

# Or read from a file
if [ -f "servers.txt" ]; then
    echo -e "${BLUE}Reading servers from servers.txt...${NC}"
    mapfile -t SERVERS < <(grep -v '^#' servers.txt | grep -v '^$')
fi

echo -e "${YELLOW}Will deploy to ${#SERVERS[@]} servers${NC}"
echo ""

# Confirm
read -p "Continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Aborted."
    exit 1
fi

# Deploy to each server
for server in "${SERVERS[@]}"; do
    echo ""
    echo "=========================================="
    echo -e "${BLUE}Deploying to: $server${NC}"
    echo "=========================================="
    
    ssh "$server" << 'ENDSSH'
        set -e
        
        # Check if WharfTales is installed
        if [ ! -d "/opt/wharftales" ]; then
            echo "ERROR: WharfTales not found at /opt/wharftales"
            exit 1
        fi
        
        cd /opt/wharftales
        
        # Pull latest code
        echo "Pulling latest code..."
        git pull origin main
        
        # Make fix script executable
        chmod +x fix-remote-installation.sh
        
        # Run the fix script
        echo "Running fix script..."
        ./fix-remote-installation.sh
        
        echo "✅ Deployment complete on this server!"
ENDSSH
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Success: $server${NC}"
    else
        echo -e "${RED}❌ Failed: $server${NC}"
    fi
done

echo ""
echo "=========================================="
echo -e "${GREEN}Deployment Complete!${NC}"
echo "=========================================="
echo ""
echo "Summary:"
echo "  Total servers: ${#SERVERS[@]}"
echo ""
echo "Next steps:"
echo "  1. Check each server's dashboard"
echo "  2. Verify sites are working"
echo "  3. Test creating a new site"
echo ""
