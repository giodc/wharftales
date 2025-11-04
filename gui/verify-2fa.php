<?php
require_once 'includes/auth.php';

// Check if 2FA verification is needed
if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: /login.php');
    exit;
}

// Check if 2FA session has expired (5 minutes)
if (time() - ($_SESSION['2fa_timestamp'] ?? 0) > 300) {
    unset($_SESSION['2fa_user_id']);
    unset($_SESSION['2fa_username']);
    unset($_SESSION['2fa_timestamp']);
    header('Location: /login.php?error=2fa_expired');
    exit;
}

$error = '';
$success = '';

// Handle 2FA verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($code)) {
        $error = 'Please enter your verification code.';
    } else {
        // Verify the 2FA code
        if (verify2FACode($_SESSION['2fa_user_id'], $code)) {
            // Complete the login
            $userId = $_SESSION['2fa_user_id'];
            $username = $_SESSION['2fa_username'];
            
            // Clear 2FA session data
            unset($_SESSION['2fa_user_id']);
            unset($_SESSION['2fa_username']);
            unset($_SESSION['2fa_timestamp']);
            
            // Set actual session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $userId;
            $_SESSION['username'] = $username;
            $_SESSION['last_regeneration'] = time();
            
            // Update last login
            $db = initAuthDatabase();
            $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$userId]);
            
            logAudit('login_2fa', 'user', $userId);
            
            // Redirect to original page or dashboard
            $redirect = $_SESSION['redirect_after_login'] ?? '/index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid verification code. Please try again.';
        }
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - WharfTales</title>
          <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="WharfTales" />
<link rel="manifest" href="/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .verify-container {
            max-width: 420px;
            width: 100%;
            padding: 0 1rem;
        }
        .verify-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 2.5rem;
        }
        .verify-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .verify-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .verify-header p {
            color: #6b7280;
            font-size: 0.875rem;
        }
        .logo-icon {
            width: 64px;
            height: 64px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 0.5rem;
            color: white;
            font-size: 2rem;
        }
        .form-control {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            text-align: center;
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #4b5563;
            box-shadow: 0 0 0 3px rgba(75, 85, 99, 0.1);
        }
        .btn-verify {
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
            background: #1f2937;
            border: none;
            border-radius: 0.5rem;
            color: white;
            transition: all 0.2s;
        }
        .btn-verify:hover {
            background: #111827;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .info-box {
            background: #f3f4f6;
            border-left: 4px solid #4b5563;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-header">
                <div class="logo-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h1>Two-Factor Authentication</h1>
                <p>Enter the 6-digit code from your authenticator app</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/verify-2fa.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="mb-3">
                    <input type="text" class="form-control" id="code" name="code" 
                           required autofocus maxlength="8" pattern="[0-9A-Z]{6,8}" 
                           placeholder="000000" autocomplete="off">
                </div>

                <button type="submit" class="btn btn-verify mb-3">
                    <i class="bi bi-check-circle me-2"></i>Verify Code
                </button>
            </form>

            <div class="info-box">
                <i class="bi bi-info-circle me-2"></i>
                <strong>Can't access your authenticator?</strong><br>
                Use one of your backup codes instead.
            </div>

            <div class="text-center">
                <a href="/login.php" class="text-muted text-decoration-none">
                    <i class="bi bi-arrow-left me-1"></i>Back to login
                </a>
            </div>
        </div>

        <div class="text-center mt-4">
            <small style="color: rgba(255,255,255,0.6);">
                <i class="bi bi-shield-check me-1"></i>
                Secure two-factor authentication
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit when 6 digits are entered
        document.getElementById('code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
            if (this.value.length === 6 || this.value.length === 8) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
