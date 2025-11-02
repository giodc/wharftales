#!/bin/bash
echo "=== WharfTales Dashboard Diagnostics ==="
echo ""

echo "1. Checking if containers are running..."
docker ps | grep -E "(traefik|wharftales_gui)"
echo ""

echo "2. Checking dashboard settings in database..."
docker exec wharftales_gui php -r "
require '/var/www/html/includes/functions.php';
\$db = initDatabase();
\$domain = getSetting(\$db, 'dashboard_domain', 'NOT SET');
\$ssl = getSetting(\$db, 'dashboard_ssl', '0');
echo 'Dashboard Domain: ' . \$domain . PHP_EOL;
echo 'SSL Enabled: ' . (\$ssl === '1' ? 'YES' : 'NO') . PHP_EOL;
"
echo ""

echo "3. Checking Traefik labels on web-gui container..."
docker inspect wharftales_gui | grep -A 20 "Labels"
echo ""

echo "4. Checking DNS resolution for dashboard.wharftales.org..."
nslookup dashboard.wharftales.org || echo "DNS lookup failed"
echo ""

echo "5. Checking if Traefik is listening on ports 80 and 443..."
docker exec traefik netstat -tlnp 2>/dev/null | grep -E ":(80|443)" || echo "netstat not available, checking with ss..."
docker exec traefik ss -tlnp 2>/dev/null | grep -E ":(80|443)" || echo "Port check failed"
echo ""

echo "6. Checking Traefik logs for errors..."
docker logs traefik --tail 50 2>&1 | grep -i "error\|dashboard"
echo ""

echo "7. Checking web-gui logs..."
docker logs wharftales_gui --tail 30
echo ""

echo "8. Testing HTTP access to dashboard domain..."
curl -I http://dashboard.wharftales.org 2>&1 | head -10
echo ""

echo "9. Testing HTTPS access to dashboard domain..."
curl -I https://dashboard.wharftales.org 2>&1 | head -10
echo ""

echo "10. Checking Traefik configuration..."
docker exec traefik cat /etc/traefik/traefik.yml 2>/dev/null || echo "Traefik config not accessible"
echo ""

echo "=== Diagnostics Complete ==="
