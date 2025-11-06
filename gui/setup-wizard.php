<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';
require_once 'includes/telemetry.php';

// Redirect to SSL URL if dashboard has SSL enabled and request is from :9000
redirectToSSLIfEnabled();

// Require authentication
requireAuth();

// Only admins can access setup wizard
requireAdmin();

$db = initDatabase();

// Check if setup is already completed
$setupCompleted = getSetting($db, 'setup_completed', '0');

// Allow re-running setup if explicitly requested
$forceSetup = isset($_GET['force']) && $_GET['force'] === '1';

// Only redirect away if setup is explicitly completed (not on fresh install)
if ($setupCompleted === '1' && !$forceSetup) {
    header('Location: /');
    exit;
}

// Get current settings
$customWildcardDomain = getSetting($db, 'custom_wildcard_domain', '');
$dashboardDomain = getSetting($db, 'dashboard_domain', '');
$dashboardSsl = getSetting($db, 'dashboard_ssl', '0');
$letsencryptEmail = getSetting($db, 'letsencrypt_email', '');
$telemetryEnabled = isTelemetryEnabled();
$installationId = getInstallationId();

$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard - WharfTales</title>
        <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
        <link rel="shortcut icon" href="/favicon.ico" />
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
        <meta name="apple-mobile-web-app-title" content="WharfTales" />
        <link rel="manifest" href="/site.webmanifest" /> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background:rgb(106, 106, 106);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .setup-container {
            max-width: 800px;
            width: 100%;
            padding: 20px;
        }
        .setup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #434343 0%, #000000 100%        );
            color: white;
            padding: 30px;
            text-align: center;
        }
        .setup-step {
            display: none;
            padding: 40px;
        }
        .setup-step.active {
            display: block;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            padding: 20px 40px;
            background: #f8f9fa;
        }
        .step-dot {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #6c757d;
            position: relative;
        }
        .step-dot.active {
            background: #000;
            color: white;
        }
        .step-dot.completed {
            background: #28a745;
            color: white;
        }
        .step-dot::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            background: #dee2e6;
            left: 100%;
            top: 50%;
            z-index: -1;
        }
        .step-dot:last-child::after {
            display: none;
        }
        .btn-setup {
            padding: 12px 30px;
            font-size: 16px;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <h1><i class="bi bi-rocket-takeoff me-2"></i>Welcome to WharfTales!</h1>
                <p class="mb-0">Let's get your deployment platform configured</p>
            </div>

            <div class="step-indicator">
                <div class="step-dot active" data-step="1">1</div>
                <div class="step-dot" data-step="2">2</div>
                <div class="step-dot" data-step="3">3</div>
                <div class="step-dot" data-step="4">4</div>
                <div class="step-dot" data-step="5">5</div>
                <div class="step-dot" data-step="6">6</div>
            </div>

            <!-- Step 1: Welcome -->
            <div class="setup-step active" data-step="1">
                <h3><i class="bi bi-hand-wave me-2"></i>Welcome, <?= htmlspecialchars($currentUser['username']) ?>!</h3>
                <p class="lead">This quick setup wizard will help you configure WharfTales for your environment.</p>
                
                <div class="info-box">
                    <strong><i class="bi bi-info-circle me-2"></i>What we'll configure:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Custom wildcard domain for your applications</li>
                        <li>Custom domain for this dashboard (optional)</li>
                        <li>SSL certificate for dashboard (optional)</li>
                        <li>Let's Encrypt email for SSL certificates</li>
                        <li>Anonymous usage statistics (optional)</li>
                    </ul>
                </div>

                <p class="text-muted">Don't worry, you can skip any step and configure these settings later from the Settings page.</p>

                <div class="d-flex justify-content-between mt-4">
                    <a href="/?skip_setup=1" class="btn btn-outline-secondary btn-setup">
                        <i class="bi bi-skip-forward me-2"></i>Skip Setup
                    </a>
                    <button class="btn btn-dark btn-setup" onclick="nextStep()">
                        Get Started <i class="bi bi-arrow-right ms-2"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Custom Wildcard Domain -->
            <div class="setup-step" data-step="2">
                <h3><i class="bi bi-globe me-2"></i>Custom Wildcard Domain</h3>
                <p>Set up a custom wildcard domain for your applications (e.g., *.apps.yourdomain.com)</p>

                <div class="info-box">
                    <strong><i class="bi bi-lightbulb me-2"></i>How it works:</strong>
                    <p class="mb-2 mt-2">Instead of using .test.local or port-based domains, you can use a custom wildcard domain.</p>
                    <p class="mb-0"><strong>Example:</strong> If you set <code>*.apps.example.com</code>, your apps will be accessible at:<br>
                    <code>mysite.apps.example.com</code>, <code>blog.apps.example.com</code>, etc.</p>
                </div>

                <div class="alert alert-warning">
                    <strong><i class="bi bi-exclamation-triangle me-2"></i>DNS Setup Required:</strong>
                    <p class="mb-0">You need to create a wildcard DNS A record pointing to your server's IP address:<br>
                    <code>*.apps.example.com → <?= $_SERVER['SERVER_ADDR'] ?? 'YOUR_SERVER_IP' ?></code></p>
                </div>

                <div class="mb-3">
                    <label class="form-label">Wildcard Domain (optional)</label>
                    <input type="text" class="form-control" id="customWildcard" 
                           placeholder="*.apps.example.com" 
                           value="<?= htmlspecialchars($customWildcardDomain) ?>">
                    <small class="text-muted">Leave empty to skip this step</small>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button class="btn btn-outline-secondary btn-setup" onclick="prevStep()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <div>
                        <button class="btn btn-outline-secondary btn-setup me-2" onclick="skipStep()">
                            Skip
                        </button>
                        <button class="btn btn-dark btn-setup" onclick="saveAndNext('customWildcard', 'custom_wildcard_domain')">
                            Next <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Dashboard Domain -->
            <div class="setup-step" data-step="3">
                <h3><i class="bi bi-display me-2"></i>Custom Dashboard Domain</h3>
                <p>Access this dashboard using a custom domain instead of IP address or localhost</p>

                <div class="info-box">
                    <strong><i class="bi bi-lightbulb me-2"></i>Why use a custom domain?</strong>
                    <ul class="mb-0 mt-2">
                        <li>Easier to remember and access</li>
                        <li>Required for SSL certificate</li>
                        <li>Professional appearance</li>
                    </ul>
                </div>

                <div class="alert alert-warning">
                    <strong><i class="bi bi-exclamation-triangle me-2"></i>DNS Setup Required:</strong>
                    <p class="mb-0">Create a DNS A record pointing to your server:<br>
                    <code>deploy.example.com → <?= $_SERVER['SERVER_ADDR'] ?? 'YOUR_SERVER_IP' ?></code></p>
                </div>

                <div class="mb-3">
                    <label class="form-label">Dashboard Domain (optional)</label>
                    <input type="text" class="form-control" id="dashboardDomain" 
                           placeholder="deploy.example.com" 
                           value="<?= htmlspecialchars($dashboardDomain) ?>">
                    <small class="text-muted">Leave empty to skip this step</small>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button class="btn btn-outline-secondary btn-setup" onclick="prevStep()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <div>
                        <button class="btn btn-outline-secondary btn-setup me-2" onclick="skipStep()">
                            Skip
                        </button>
                        <button class="btn btn-dark btn-setup" onclick="saveAndNext('dashboardDomain', 'dashboard_domain')">
                            Next <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 4: Dashboard SSL -->
            <div class="setup-step" data-step="4">
                <h3><i class="bi bi-shield-check me-2"></i>Enable SSL for Dashboard</h3>
                <p>Secure your dashboard with a free Let's Encrypt SSL certificate</p>

                <div class="info-box">
                    <strong><i class="bi bi-lightbulb me-2"></i>Benefits of SSL:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Encrypted connection (HTTPS)</li>
                        <li>Browser security warnings removed</li>
                        <li>Required for production use</li>
                    </ul>
                </div>

                <div class="alert alert-info" id="sslRequirement">
                    <i class="bi bi-info-circle me-2"></i>
                    <span id="sslMessage">SSL requires a custom dashboard domain. Please go back and configure it first.</span>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="enableDashboardSsl" <?= $dashboardSsl === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="enableDashboardSsl">
                        <strong>Enable SSL for Dashboard</strong>
                    </label>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button class="btn btn-outline-secondary btn-setup" onclick="prevStep()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <div>
                        <button class="btn btn-outline-secondary btn-setup me-2" onclick="skipStep()">
                            Skip
                        </button>
                        <button class="btn btn-dark btn-setup" onclick="saveSslAndNext()">
                            Next <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 5: Let's Encrypt Email -->
            <div class="setup-step" data-step="5">
                <h3><i class="bi bi-envelope me-2"></i>Let's Encrypt Email</h3>
                <p>Provide an email address for SSL certificate notifications and recovery</p>

                <div class="info-box">
                    <strong><i class="bi bi-lightbulb me-2"></i>Why is this needed?</strong>
                    <ul class="mb-0 mt-2">
                        <li>Certificate expiration notifications</li>
                        <li>Important security updates</li>
                        <li>Account recovery</li>
                    </ul>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="letsencryptEmail" 
                           placeholder="admin@example.com" 
                           value="<?= htmlspecialchars($letsencryptEmail) ?>">
                    <small class="text-muted">This email will be used for all SSL certificates</small>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button class="btn btn-outline-secondary btn-setup" onclick="prevStep()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <div>
                        <button class="btn btn-outline-secondary btn-setup me-2" onclick="skipStep()">
                            Skip
                        </button>
                        <button class="btn btn-dark btn-setup" onclick="saveAndNext('letsencryptEmail', 'letsencrypt_email')">
                            Next <i class="bi bi-arrow-right ms-2"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 6: Telemetry -->
            <div class="setup-step" data-step="6">
                <h3><i class="bi bi-graph-up me-2"></i>Anonymous Usage Statistics</h3>
                <p>Help improve WharfTales by sharing anonymous usage data</p>

                <div class="info-box">
                    <strong><i class="bi bi-shield-check me-2"></i>Privacy First:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Completely anonymous - no personal data collected</li>
                        <li>Only basic metrics: version, site count, PHP version</li>
                        <li>Helps us understand usage patterns and prioritize features</li>
                        <li>You can disable this anytime from Settings</li>
                    </ul>
                </div>

                <div class="alert alert-info">
                    <strong><i class="bi bi-info-circle me-2"></i>What we collect:</strong>
                    <ul class="mb-0 mt-2">
                        <li><strong>Installation ID:</strong> <code><?= htmlspecialchars($installationId) ?></code></li>
                        <li><strong>WharfTales version</strong></li>
                        <li><strong>PHP version</strong></li>
                        <li><strong>Number of sites</strong> (not their names or domains)</li>
                    </ul>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" id="enableTelemetry" <?= $telemetryEnabled ? 'checked' : '' ?>>
                    <label class="form-check-label" for="enableTelemetry">
                        <strong>Enable anonymous usage statistics</strong>
                    </label>
                </div>

                <div class="d-flex justify-content-between mt-4">
                    <button class="btn btn-outline-secondary btn-setup" onclick="prevStep()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <button class="btn btn-success btn-setup" onclick="completeTelemetryAndSetup()">
                        <i class="bi bi-check-circle me-2"></i>Complete Setup
                    </button>
                </div>
            </div>

            <!-- Step 7: Completion -->
            <div class="setup-step" data-step="7">
                <div class="text-center">
                    <i class="bi bi-check-circle-fill success-icon"></i>
                    <h3 class="mt-3">Setup Complete!</h3>
                    <p class="lead">WharfTales is now configured and ready to use.</p>

                    <div class="info-box text-start mt-4">
                        <strong><i class="bi bi-info-circle me-2"></i>What's next?</strong>
                        <ul class="mb-0 mt-2">
                            <li>Deploy your first application</li>
                            <li>Invite team members (Users page)</li>
                            <li>Enable 2FA for your account (recommended)</li>
                            <li>Review settings anytime from the Settings page</li>
                        </ul>
                    </div>

                    <a href="/" class="btn btn-primary btn-lg btn-setup mt-4">
                        <i class="bi bi-rocket-takeoff me-2"></i>Go to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        const totalSteps = 7;

        function updateStepIndicator() {
            document.querySelectorAll('.step-dot').forEach(dot => {
                const step = parseInt(dot.dataset.step);
                dot.classList.remove('active', 'completed');
                if (step === currentStep) {
                    dot.classList.add('active');
                } else if (step < currentStep) {
                    dot.classList.add('completed');
                    dot.innerHTML = '<i class="bi bi-check"></i>';
                } else {
                    dot.textContent = step;
                }
            });
        }

        function showStep(step) {
            document.querySelectorAll('.setup-step').forEach(s => s.classList.remove('active'));
            document.querySelector(`.setup-step[data-step="${step}"]`).classList.add('active');
            currentStep = step;
            updateStepIndicator();

            // Check if dashboard domain is set for SSL step
            if (step === 4) {
                const dashboardDomain = document.getElementById('dashboardDomain')?.value || '';
                const sslCheckbox = document.getElementById('enableDashboardSsl');
                const sslMessage = document.getElementById('sslMessage');
                
                if (!dashboardDomain) {
                    sslCheckbox.disabled = true;
                    sslCheckbox.checked = false;
                    sslMessage.textContent = 'SSL requires a custom dashboard domain. Please go back and configure it first.';
                } else {
                    sslCheckbox.disabled = false;
                    sslMessage.textContent = `SSL will be configured for: ${dashboardDomain}`;
                }
            }
        }

        function nextStep() {
            if (currentStep < totalSteps) {
                showStep(currentStep + 1);
            }
        }

        function prevStep() {
            if (currentStep > 1) {
                showStep(currentStep - 1);
            }
        }

        function skipStep() {
            nextStep();
        }

        async function saveAndNext(inputId, settingKey) {
            const value = document.getElementById(inputId).value.trim();
            
            if (value) {
                try {
                    const response = await fetch('/api.php?action=update_setting', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ key: settingKey, value: value })
                    });
                    
                    const result = await response.json();
                    if (!result.success) {
                        alert('Failed to save setting: ' + (result.error || 'Unknown error'));
                        return;
                    }
                } catch (error) {
                    alert('Network error: ' + error.message);
                    return;
                }
            }
            
            nextStep();
        }

        async function saveSslAndNext() {
            const enabled = document.getElementById('enableDashboardSsl').checked;
            const dashboardDomain = document.getElementById('dashboardDomain')?.value || '';
            
            // Save SSL setting
            try {
                const response = await fetch('/api.php?action=update_setting', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key: 'dashboard_ssl', value: enabled ? '1' : '0' })
                });
                
                const result = await response.json();
                if (!result.success) {
                    alert('Failed to save setting: ' + (result.error || 'Unknown error'));
                    return;
                }
            } catch (error) {
                alert('Network error: ' + error.message);
                return;
            }
            
            // If dashboard domain is set, update docker-compose with Traefik labels
            if (dashboardDomain) {
                try {
                    const configResponse = await fetch('/api.php?action=update_dashboard_traefik', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ 
                            domain: dashboardDomain, 
                            ssl: enabled ? '1' : '0' 
                        })
                    });
                    
                    const configResult = await configResponse.json();
                    if (!configResult.success) {
                        console.warn('Failed to update Traefik configuration:', configResult.error);
                        // Don't block progression - user can fix this later
                    }
                } catch (error) {
                    console.warn('Error updating Traefik config:', error.message);
                    // Don't block progression
                }
            }
            
            nextStep();
        }

        async function completeTelemetryAndSetup() {
            const telemetryEnabled = document.getElementById('enableTelemetry').checked;
            
            // Save telemetry preference
            try {
                const response = await fetch('/api.php?action=update_setting', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key: 'telemetry_enabled', value: telemetryEnabled ? '1' : '0' })
                });
                
                const result = await response.json();
                if (!result.success) {
                    alert('Failed to save telemetry setting: ' + (result.error || 'Unknown error'));
                    return;
                }
            } catch (error) {
                alert('Network error: ' + error.message);
                return;
            }
            
            // Mark setup as completed
            try {
                const response = await fetch('/api.php?action=update_setting', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ key: 'setup_completed', value: '1' })
                });
                
                const result = await response.json();
                if (!result.success) {
                    alert('Failed to complete setup: ' + (result.error || 'Unknown error'));
                    return;
                }
            } catch (error) {
                alert('Network error: ' + error.message);
                return;
            }
            
            showStep(7);
        }

        // Initialize
        updateStepIndicator();
    </script>
</body>
</html>
