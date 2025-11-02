<?php
// Fix dashboard SSL configuration
require_once '/var/www/html/includes/functions.php';

$db = initDatabase();

// Set the correct domain
$domain = 'dashboard.wharftales.org';
$enableSSL = '1';

echo "Setting dashboard domain to: {$domain}\n";
echo "Enabling SSL...\n";

// Update settings
setSetting($db, 'dashboard_domain', $domain);
setSetting($db, 'dashboard_ssl', $enableSSL);

echo "Settings updated!\n";
echo "\nNext steps:\n";
echo "1. Go to Settings page in the dashboard\n";
echo "2. Update the dashboard domain to: {$domain}\n";
echo "3. Check 'Enable SSL (Let's Encrypt)'\n";
echo "4. Click 'Update Dashboard Domain'\n";
echo "5. Run: docker-compose restart web-gui\n";
echo "\nOr run this command directly:\n";
echo "cd /opt/wharftales && docker-compose restart web-gui\n";
