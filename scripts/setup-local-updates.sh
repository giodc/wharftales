#!/bin/bash
# Quick setup script for local update testing

echo "=========================================="
echo "WharfTales Update System - Local Setup"
echo "=========================================="
echo ""

# Check if versions.json exists
if [ ! -f "/opt/wharftales/versions.json" ]; then
    echo "Creating versions.json..."
    cat > /opt/wharftales/versions.json << 'EOF'
{
  "wharftales": {
    "latest": "0.0.4",
    "min_supported": "0.0.1",
    "update_url": "https://raw.githubusercontent.com/giodc/wharftales/main/scripts/upgrade.sh",
    "changelog_url": "https://github.com/giodc/wharftales/releases",
    "release_notes": "Added comprehensive auto-update system. Includes automatic version checking, manual and automatic update modes, backup system, and update notifications.",
    "released_at": "2025-10-30"
  }
}
EOF
    echo "✓ Created versions.json"
else
    echo "✓ versions.json already exists"
fi

# Update database to use local file
echo ""
echo "Configuring database to use local versions.json..."
sqlite3 /opt/wharftales/data/database.sqlite "INSERT OR REPLACE INTO settings (key, value) VALUES ('versions_url', '/opt/wharftales/versions.json');"

if [ $? -eq 0 ]; then
    echo "✓ Database updated"
else
    echo "✗ Failed to update database"
    exit 1
fi

# Test the configuration
echo ""
echo "Testing update check..."
CURRENT_VERSION=$(cat /opt/wharftales/VERSION 2>/dev/null || echo "unknown")
echo "Current version: $CURRENT_VERSION"

if [ -f "/opt/wharftales/versions.json" ]; then
    LATEST_VERSION=$(cat /opt/wharftales/versions.json | grep -o '"latest": "[^"]*"' | cut -d'"' -f4)
    echo "Latest version in versions.json: $LATEST_VERSION"
fi

echo ""
echo "=========================================="
echo "Setup Complete!"
echo "=========================================="
echo ""
echo "Next steps:"
echo "1. Go to Settings → System Updates in the web UI"
echo "2. Click 'Check Now' to test the update check"
echo ""
echo "To switch to GitHub-hosted versions.json later:"
echo "1. Commit and push versions.json to your repository"
echo "2. Go to Settings → System Updates"
echo "3. Change 'Versions URL' back to:"
echo "   https://raw.githubusercontent.com/giodc/wharftales/main/versions.json"
echo ""
echo "Current configuration:"
sqlite3 /opt/wharftales/data/database.sqlite "SELECT key, value FROM settings WHERE key = 'versions_url';"
echo ""
