<?php

/**
 * SSL Configuration Management
 * Handles both HTTP and DNS challenge methods for Let's Encrypt
 */

function saveSSLConfig($db, $siteId, $sslConfig) {
    $stmt = $db->prepare("UPDATE sites SET ssl_config = ? WHERE id = ?");
    return $stmt->execute([json_encode($sslConfig), $siteId]);
}

function getSSLConfig($db, $siteId) {
    $stmt = $db->prepare("SELECT ssl_config FROM sites WHERE id = ?");
    $stmt->execute([$siteId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result && $result['ssl_config']) {
        return json_decode($result['ssl_config'], true);
    }
    
    return null;
}

function generateTraefikSSLLabels($site, $sslConfig) {
    $containerName = $site['container_name'];
    $domain = $site['domain'];
    
    $labels = [
        "traefik.enable=true",
        "traefik.http.routers.{$containerName}.rule=Host(`{$domain}`)",
        "traefik.http.routers.{$containerName}.entrypoints=web",
        "traefik.http.services.{$containerName}.loadbalancer.server.port=80"
    ];
    
    if ($site['ssl'] && $sslConfig) {
        // Determine which certificate resolver to use based on challenge type
        $certResolver = 'letsencrypt'; // Default to HTTP challenge
        if (isset($sslConfig['challenge']) && $sslConfig['challenge'] === 'dns') {
            $certResolver = 'letsencrypt-dns'; // Use DNS challenge resolver
        }
        
        // Add HTTPS router
        $labels[] = "traefik.http.routers.{$containerName}-secure.rule=Host(`{$domain}`)";
        $labels[] = "traefik.http.routers.{$containerName}-secure.entrypoints=websecure";
        $labels[] = "traefik.http.routers.{$containerName}-secure.tls=true";
        $labels[] = "traefik.http.routers.{$containerName}-secure.tls.certresolver={$certResolver}";
        
        // Add HTTP to HTTPS redirect
        $labels[] = "traefik.http.routers.{$containerName}.middlewares=redirect-to-https";
        $labels[] = "traefik.http.middlewares.redirect-to-https.redirectscheme.scheme=https";
        $labels[] = "traefik.http.middlewares.redirect-to-https.redirectscheme.permanent=true";
    }
    
    return $labels;
}

function updateTraefikDNSConfig($dnsProvider, $credentials) {
    $envVars = [];
    
    switch ($dnsProvider) {
        case 'cloudflare':
            // API Token method (secure, scoped permissions)
            $envVars['CF_DNS_API_TOKEN'] = $credentials['cf_api_token'] ?? '';
            break;
            
        case 'route53':
            $envVars['AWS_ACCESS_KEY_ID'] = $credentials['aws_access_key'] ?? '';
            $envVars['AWS_SECRET_ACCESS_KEY'] = $credentials['aws_secret_key'] ?? '';
            $envVars['AWS_REGION'] = $credentials['aws_region'] ?? 'us-east-1';
            break;
            
        case 'digitalocean':
            $envVars['DO_AUTH_TOKEN'] = $credentials['do_auth_token'] ?? '';
            break;
            
        case 'gcp':
            $envVars['GCE_PROJECT'] = $credentials['gcp_project'] ?? '';
            $envVars['GCE_SERVICE_ACCOUNT_FILE'] = '/run/secrets/gcp-service-account.json';
            break;
            
        case 'azure':
            $envVars['AZURE_CLIENT_ID'] = $credentials['azure_client_id'] ?? '';
            $envVars['AZURE_CLIENT_SECRET'] = $credentials['azure_client_secret'] ?? '';
            $envVars['AZURE_TENANT_ID'] = $credentials['azure_tenant_id'] ?? '';
            $envVars['AZURE_SUBSCRIPTION_ID'] = $credentials['azure_subscription_id'] ?? '';
            break;
    }
    
    return $envVars;
}

function getTraefikDNSChallengeCommand($dnsProvider) {
    $providerMap = [
        'cloudflare' => 'cloudflare',
        'route53' => 'route53',
        'digitalocean' => 'digitalocean',
        'gcp' => 'gcloud',
        'azure' => 'azure',
        'namecheap' => 'namecheap',
        'godaddy' => 'godaddy'
    ];
    
    $provider = $providerMap[$dnsProvider] ?? $dnsProvider;
    
    return [
        "--certificatesresolvers.letsencrypt.acme.dnschallenge=true",
        "--certificatesresolvers.letsencrypt.acme.dnschallenge.provider={$provider}",
        "--certificatesresolvers.letsencrypt.acme.dnschallenge.delaybeforecheck=0"
    ];
}

function saveGlobalDNSProvider($dnsProvider, $credentials) {
    $configPath = '/app/data/dns-config.json';
    $config = [
        'provider' => $dnsProvider,
        'credentials' => $credentials,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT));
    
    // Update Traefik configuration
    updateTraefikForDNSChallenge($dnsProvider, $credentials);
    
    return true;
}

function getGlobalDNSProvider() {
    $configPath = '/app/data/dns-config.json';
    
    if (file_exists($configPath)) {
        $config = json_decode(file_get_contents($configPath), true);
        return $config;
    }
    
    return null;
}

function updateTraefikForDNSChallenge($dnsProvider, $credentials) {
    $composePath = '/opt/wharftales/docker-compose.yml';
    
    if (!file_exists($composePath)) {
        error_log("Docker compose file not found at: $composePath");
        return false;
    }
    
    // Read current docker-compose.yml
    $composeContent = file_get_contents($composePath);
    
    // Get environment variables for DNS provider
    $envVars = updateTraefikDNSConfig($dnsProvider, $credentials);
    
    // Save environment file
    $envFile = '/app/data/traefik-dns.env';
    $envContent = "";
    foreach ($envVars as $key => $value) {
        $envContent .= "{$key}={$value}\n";
    }
    
    // Check if we can write to the file
    $canWrite = (!file_exists($envFile) || is_writable($envFile));
    
    if ($canWrite) {
        // Try to write directly
        $result = @file_put_contents($envFile, $envContent);
        if ($result !== false) {
            @chmod($envFile, 0664);
        } else {
            $canWrite = false; // Failed, use alternative method
        }
    }
    
    if (!$canWrite) {
        // Use alternative method: temp file + shell command
        $tempFile = tempnam(sys_get_temp_dir(), 'traefik_dns_');
        file_put_contents($tempFile, $envContent);
        
        // Use shell to write (runs with container's default permissions)
        exec("cat " . escapeshellarg($tempFile) . " > " . escapeshellarg($envFile) . " 2>&1", $output, $ret);
        unlink($tempFile);
        
        if ($ret !== 0) {
            error_log("Failed to write traefik-dns.env: " . implode("\n", $output));
            return false;
        }
        
        // Try to set proper permissions on the newly created file
        @chmod($envFile, 0664);
    }
    
    // Parse and modify the docker-compose.yml content
    $lines = explode("\n", $composeContent);
    $newLines = [];
    $inTraefikService = false;
    $inTraefikCommand = false;
    $dnsResolverAdded = false;
    $envFileAdded = false;
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        
        // Detect traefik service
        if (preg_match('/^  traefik:\s*$/', $line)) {
            $inTraefikService = true;
            $newLines[] = $line;
            continue;
        }
        
        // Exit traefik service when we hit another top-level service
        if ($inTraefikService && preg_match('/^  \w+:\s*$/', $line)) {
            $inTraefikService = false;
        }
        
        // Detect command section in traefik
        if ($inTraefikService && preg_match('/^\s+command:\s*$/', $line)) {
            $inTraefikCommand = true;
            $newLines[] = $line;
            continue;
        }
        
        // Exit command section
        if ($inTraefikCommand && !preg_match('/^\s+- "/', $line) && !preg_match('/^\s+#/', $line)) {
            $inTraefikCommand = false;
        }
        
        // Skip existing letsencrypt-dns resolver lines to avoid duplicates
        if ($inTraefikCommand && strpos($line, 'letsencrypt-dns') !== false) {
            continue;
        }
        
        // Add DNS resolver after the HTTP resolver (letsencrypt) storage line
        if ($inTraefikCommand && !$dnsResolverAdded && strpos($line, 'certificatesresolvers.letsencrypt.acme.storage') !== false) {
            $newLines[] = $line;
            // Add DNS resolver configuration
            $newLines[] = '      - "--certificatesresolvers.letsencrypt-dns.acme.dnschallenge=true"';
            $newLines[] = '      - "--certificatesresolvers.letsencrypt-dns.acme.dnschallenge.provider=' . $dnsProvider . '"';
            $newLines[] = '      - "--certificatesresolvers.letsencrypt-dns.acme.dnschallenge.delaybeforecheck=0"';
            $newLines[] = '      - "--certificatesresolvers.letsencrypt-dns.acme.email=info@giodc.com"';
            $newLines[] = '      - "--certificatesresolvers.letsencrypt-dns.acme.storage=/letsencrypt/acme-dns.json"';
            $dnsResolverAdded = true;
            continue;
        }
        
        // Add env_file before volumes in traefik service
        if ($inTraefikService && !$envFileAdded && preg_match('/^\s+volumes:\s*$/', $line)) {
            if (strpos($composeContent, 'traefik-dns.env') === false) {
                $newLines[] = '    env_file:';
                $newLines[] = '      - ./data/traefik-dns.env';
            }
            $envFileAdded = true;
        }
        
        $newLines[] = $line;
    }
    
    // Write updated docker-compose.yml
    $newContent = implode("\n", $newLines);
    file_put_contents($composePath, $newContent);
    
    // Recreate Traefik container with new configuration
    exec('cd /opt/wharftales && docker-compose up -d --force-recreate traefik 2>&1', $output, $returnCode);
    
    if ($returnCode !== 0) {
        error_log("Failed to restart Traefik: " . implode("\n", $output));
        return false;
    }
    
    return true;
}

/**
 * Update dashboard Traefik labels in docker-compose.yml
 * This updates the web-gui service with the correct domain and SSL configuration
 */
function updateDashboardTraefikConfig($dashboardDomain, $enableSSL = false) {
    $composePath = '/opt/wharftales/docker-compose.yml';
    
    if (\!file_exists($composePath)) {
        error_log("Docker compose file not found at: $composePath");
        throw new Exception("Docker compose file not found");
    }
    
    // Read current docker-compose.yml
    $composeContent = file_get_contents($composePath);
    
    // Parse and modify the docker-compose.yml content
    $lines = explode("\n", $composeContent);
    $newLines = [];
    $inWebGuiService = false;
    $inLabels = false;
    $labelsAdded = false;
    
    for ($i = 0; $i < count($lines); $i++) {
        $line = $lines[$i];
        
        // Detect web-gui service
        if (preg_match('/^  web-gui:\s*$/', $line)) {
            $inWebGuiService = true;
            $newLines[] = $line;
            continue;
        }
        
        // Exit web-gui service when we hit another top-level service
        if ($inWebGuiService && preg_match('/^  \w+:\s*$/', $line) && \!preg_match('/^    /', $line)) {
            $inWebGuiService = false;
            $inLabels = false;
        }
        
        // Detect labels section in web-gui
        if ($inWebGuiService && preg_match('/^\s+labels:\s*$/', $line)) {
            $inLabels = true;
            $newLines[] = $line;
            
            // Add or replace all Traefik labels
            $newLines[] = '      - traefik.enable=true';
            $newLines[] = '      - traefik.http.routers.webgui.rule=Host(`' . $dashboardDomain . '`)';
            $newLines[] = '      - traefik.http.routers.webgui.entrypoints=web';
            $newLines[] = '      - traefik.http.services.webgui.loadbalancer.server.port=8080';
            
            if ($enableSSL) {
                // Add HTTPS router
                $newLines[] = '      - traefik.http.routers.webgui-secure.rule=Host(`' . $dashboardDomain . '`)';
                $newLines[] = '      - traefik.http.routers.webgui-secure.entrypoints=websecure';
                $newLines[] = '      - traefik.http.routers.webgui-secure.tls=true';
                $newLines[] = '      - traefik.http.routers.webgui-secure.tls.certresolver=letsencrypt';
                
                // Add HTTP to HTTPS redirect middleware
                $newLines[] = '      - traefik.http.middlewares.webgui-redirect.redirectscheme.scheme=https';
                $newLines[] = '      - traefik.http.middlewares.webgui-redirect.redirectscheme.permanent=true';
                $newLines[] = '      - traefik.http.routers.webgui.middlewares=webgui-redirect';
            }
            
            $labelsAdded = true;
            
            // Skip all existing label lines until we exit the labels section
            $i++;
            while ($i < count($lines)) {
                $nextLine = $lines[$i];
                // If we hit a line that's not a label (doesn't start with proper indentation + -), we're done
                if (\!preg_match('/^\s+- /', $nextLine)) {
                    // This line is not a label, so we need to process it normally
                    $i--; // Go back one line so it gets processed in the main loop
                    break;
                }
                $i++;
            }
            $inLabels = false;
            continue;
        }
        
        $newLines[] = $line;
    }
    
    // Write updated docker-compose.yml
    $newContent = implode("\n", $newLines);
    
    // Backup current file
    $backupPath = $composePath . '.backup-' . date('YmdHis');
    copy($composePath, $backupPath);
    
    file_put_contents($composePath, $newContent);
    
    return true;
}

/**
 * Restart Traefik to apply configuration changes
 */
function restartTraefik() {
    exec('cd /opt/wharftales && docker-compose up -d --force-recreate traefik web-gui 2>&1', $output, $returnCode);
    
    if ($returnCode \!== 0) {
        error_log("Failed to restart Traefik: " . implode("\n", $output));
        return false;
    }
    
    return true;
}
