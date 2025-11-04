<?php
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: /index.php');
    exit;
}

$error = '';
$success = '';
$validToken = false;
$db = initDatabase();

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    // Verify token
    $stmt = $db->prepare('SELECT id, username FROM users WHERE reset_token = ? AND reset_token_expires > NOW()');
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $validToken = true;
    } else {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        // Hash the new password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $stmt = $db->prepare('UPDATE users SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL, failed_attempts = 0, locked_until = NULL WHERE reset_token = ?');
        $result = $stmt->execute([$hashedPassword, $token]);
        
        if ($result) {
            $success = 'Password reset successfully! You can now login with your new password.';
            $validToken = false; // Prevent form from showing again
        } else {
            $error = 'Failed to reset password. Please try again.';
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
    <title>Reset Password - WharfTales</title>
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
        .login-container {
            max-width: 420px;
            width: 100%;
            padding: 0 1rem;
        }
        .login-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 2.5rem;
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .login-header p {
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
        .form-label {
            font-weight: 500;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        .form-control {
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
        }
        .form-control:focus {
            border-color: #4b5563;
            box-shadow: 0 0 0 3px rgba(75, 85, 99, 0.1);
        }
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
            background: #1f2937;
            border: none;
            border-radius: 0.5rem;
            color: white;
            transition: all 0.2s;
        }
        .btn-primary:hover {
            background: #111827;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .alert {
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h1>Reset Password</h1>
                <?php if ($validToken): ?>
                <p>Enter your new password for <strong><?= htmlspecialchars($user['username']) ?></strong></p>
                <?php endif; ?>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center mt-3">
                    <a href="/login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
                    </a>
                </div>
            <?php elseif ($validToken): ?>
                <form method="POST" action="/reset-password-form.php?token=<?= htmlspecialchars($token) ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6" autofocus>
                        <small class="text-muted">Minimum 6 characters</small>
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>Reset Password
                    </button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <a href="/forgot-password.php" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise me-2"></i>Request New Reset Link
                    </a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="/login.php" class="text-decoration-none" style="color: #4b5563; font-size: 0.875rem;">
                    <i class="bi bi-arrow-left me-1"></i>Back to Login
                </a>
            </div>
        </div>

        <div class="text-center mt-4">
            <small style="color: rgba(255,255,255,0.6);">
                WharfTales - Easy App Deployment Platform
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
