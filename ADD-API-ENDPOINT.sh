#!/bin/bash
# Script to add the missing update_dashboard_traefik API endpoint
# This completes the dashboard SSL 404 fix for setup wizard integration

set -e

echo "Adding update_dashboard_traefik API endpoint to api.php..."

# Backup the file first
sudo cp /opt/wharftales/gui/api.php /opt/wharftales/gui/api.php.backup-$(date +%Y%m%d-%H%M%S)
echo "✓ Backed up api.php"

# Add the case statement (after toggle_mariadb_external_access)
sudo sed -i '/case "toggle_mariadb_external_access":/,/break;/a\    \n    case "update_dashboard_traefik":\n        updateDashboardTraefikHandler($db);\n        break;' /opt/wharftales/gui/api.php

echo "✓ Added API case statement"

# Add the handler function (before the final closing brace)
HANDLER_FUNCTION='
function updateDashboardTraefikHandler($db) {
    try {
        $input = json_decode(file_get_contents("php://input"), true);
        $domain = $input['"'"'domain'"'"'] ?? '"'"''"'"';
        $enableSSL = $input['"'"'ssl'"'"'] ?? '"'"'0'"'"';
        
        if (empty($domain)) {
            jsonResponse([
                "success" => false,
                "error" => "Domain is required"
            ]);
            return;
        }
        
        // Get current user
        global $currentUser;
        if (!isset($currentUser)) {
            $currentUser = getCurrentUser();
        }
        
        // Get main config from database
        $config = getComposeConfig($db, null);
        
        if (!$config) {
            jsonResponse([
                "success" => false,
                "error" => "Main Traefik configuration not found in database"
            ]);
            return;
        }
        
        $content = $config['"'"'compose_yaml'"'"'];
        
        // Build Traefik labels
        $labels = "\n    labels:\n";
        $labels .= "      - traefik.enable=true\n";
        $labels .= "      - traefik.http.routers.webgui.rule=Host(\`{$domain}\`)\n";
        $labels .= "      - traefik.http.routers.webgui.entrypoints=web\n";
        $labels .= "      - traefik.http.services.webgui.loadbalancer.server.port=8080\n";
        
        if ($enableSSL === '"'"'1'"'"') {
            $labels .= "      - traefik.http.routers.webgui-secure.rule=Host(\`{$domain}\`)\n";
            $labels .= "      - traefik.http.routers.webgui-secure.entrypoints=websecure\n";
            $labels .= "      - traefik.http.routers.webgui-secure.tls=true\n";
            $labels .= "      - traefik.http.routers.webgui-secure.tls.certresolver=letsencrypt\n";
            $labels .= "      - traefik.http.middlewares.webgui-redirect.redirectscheme.scheme=https\n";
            $labels .= "      - traefik.http.middlewares.webgui-redirect.redirectscheme.permanent=true\n";
            $labels .= "      - traefik.http.routers.webgui.middlewares=webgui-redirect\n";
        }
        
        // Update docker-compose.yml
        $pattern = '"'"'/(web-gui:.*?)(labels:.*?)(networks:)/s'"'"';
        if (preg_match($pattern, $content)) {
            $content = preg_replace(
                '"'"'/(web-gui:.*?)(labels:.*?)(networks:)/s'"'"',
                '"'"'$1'"'"' . $labels . '"'"'    $3'"'"',
                $content
            );
        } else {
            $content = preg_replace(
                '"'"'/(web-gui:.*?)(    networks:)/s'"'"',
                '"'"'$1'"'"' . $labels . '"'"'$2'"'"',
                $content
            );
        }
        
        // Save to database and regenerate file
        saveComposeConfig($db, $content, $currentUser['"'"'id'"'"'], null);
        generateComposeFile($db, null);
        
        jsonResponse([
            "success" => true,
            "message" => "Dashboard configuration updated! Please restart the web-gui container."
        ]);
        
    } catch (Exception $e) {
        jsonResponse([
            "success" => false,
            "error" => $e->getMessage()
        ]);
    }
}
'

# Add the handler function before the closing PHP tag
sudo sed -i '/^\/\/ Flush ALL output buffer levels/i\'"$HANDLER_FUNCTION"'\n' /opt/wharftales/gui/api.php

echo "✓ Added handler function"
echo ""
echo "✅ API endpoint successfully added!"
echo ""
echo "Setup wizard will now be able to update docker-compose.yml automatically."
echo "No restart required - changes take effect immediately."
