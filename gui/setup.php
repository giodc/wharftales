<?php
require_once 'includes/auth.php';

// Redirect if users already exist
if (hasUsers()) {
    header('Location: /login.php');
    exit;
}

$error = '';
$success = '';

// Handle setup form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrfToken)) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } else {
        $result = createUser($username, $password, $email);
        
        if ($result['success']) {
            $success = 'Admin account created successfully! Redirecting to login...';
            header('Refresh: 2; URL=/login.php');
        } else {
            $error = $result['error'];
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
    <title>Setup - WharfTales</title>
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
            background: #0A0A0A;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .setup-container {
            max-width: 500px;
            width: 100%;
            padding: 0 1rem;
        }
        .setup-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 2.5rem;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .setup-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .setup-header p {
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
        .btn-setup {
            width: 100%;
            padding: 0.75rem;
            font-weight: 600;
            background: #1f2937;
            border: none;
            border-radius: 0.5rem;
            color: white;
            transition: all 0.2s;
        }
        .btn-setup:hover {
            background: #111827;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .alert {
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }
        .info-box {
            background: #f3f4f6;
            border-left: 4px solid #4b5563;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .info-box h6 {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .info-box ul {
            margin: 0;
            padding-left: 1.25rem;
            font-size: 0.875rem;
            color: #4b5563;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <div class="logo-icon">
                    <i class="bi bi-gear"></i>
                </div>
                <h1>Welcome to WharfTales</h1>
                <p>Create your admin account to get started</p>
            </div>

            <div class="info-box">
                <h6><i class="bi bi-info-circle me-2"></i>First Time Setup</h6>
                <ul>
                    <li>This is a one-time setup process</li>
                    <li>Create a strong password (min. 8 characters)</li>
                    <li>Remember your credentials - they cannot be recovered</li>
                </ul>
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
            <?php endif; ?>

            <form method="POST" action="/setup.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="mb-3">
                    <label for="username" class="form-label">
                        <i class="bi bi-person me-1"></i>Username
                    </label>
                    <input type="text" class="form-control" id="username" name="username" 
                           required autofocus minlength="3" placeholder="admin">
                    <small class="text-muted">Minimum 3 characters</small>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">
                        <i class="bi bi-envelope me-1"></i>Email (optional)
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="admin@example.com">
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">
                        <i class="bi bi-lock me-1"></i>Password
                    </label>
                    <input type="password" class="form-control" id="password" name="password" 
                           required minlength="8" placeholder="••••••••">
                    <small class="text-muted">Minimum 8 characters</small>
                </div>

                <div class="mb-4">
                    <label for="password_confirm" class="form-label">
                        <i class="bi bi-lock-fill me-1"></i>Confirm Password
                    </label>
                    <input type="password" class="form-control" id="password_confirm" 
                           name="password_confirm" required minlength="8" placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-setup">
                    <i class="bi bi-check-circle me-2"></i>Create Admin Account
                </button>
            </form>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
