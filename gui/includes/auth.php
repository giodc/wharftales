<?php
/**
 * WharfTales Authentication System
 * Lightweight, secure authentication for WharfTales
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Session lifetime: 24 hours (86400 seconds)
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    
    // Security settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');
    
    // Use strict session ID
    ini_set('session.use_strict_mode', 1);
    
    session_start();
}

/**
 * Initialize authentication database
 */
function initAuthDatabase() {
    $dbPath = $_ENV['DB_PATH'] ?? '/app/data/database.sqlite';
    $dir = dirname($dbPath);
    if (!is_dir($dir)) { mkdir($dir, 0755, true); }
    if (!is_writable($dir)) { @chmod($dir, 0775); }
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Enable WAL mode for better concurrency
    $db->exec('PRAGMA journal_mode = WAL;');
    $db->exec('PRAGMA synchronous = NORMAL;');
    $db->exec('PRAGMA busy_timeout = 5000;');
    
    // Create users table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        email TEXT,
        role TEXT DEFAULT 'user',
        totp_secret TEXT,
        totp_enabled INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME,
        failed_attempts INTEGER DEFAULT 0,
        locked_until DATETIME
    )");
    
    // Migrate existing users table if columns are missing
    try {
        $db->exec("ALTER TABLE users ADD COLUMN role TEXT DEFAULT 'user'");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    try {
        $db->exec("ALTER TABLE users ADD COLUMN totp_secret TEXT");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    try {
        $db->exec("ALTER TABLE users ADD COLUMN totp_enabled INTEGER DEFAULT 0");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    try {
        $db->exec("ALTER TABLE users ADD COLUMN reset_token TEXT");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    try {
        $db->exec("ALTER TABLE users ADD COLUMN reset_token_expires DATETIME");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    try {
        $db->exec("ALTER TABLE users ADD COLUMN totp_backup_codes TEXT");
    } catch (PDOException $e) {
        // Column already exists, ignore
    }
    
    // Create sessions table for tracking
    $db->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL,
        ip_address TEXT NOT NULL,
        success INTEGER DEFAULT 0,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Create audit_log table
    $db->exec("CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        action TEXT NOT NULL,
        resource_type TEXT,
        resource_id INTEGER,
        details TEXT,
        ip_address TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    return $db;
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']);
}

/**
 * Require authentication - redirect to login if not authenticated
 */
function requireAuth() {
    if (!isLoggedIn()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/';
        header('Location: /login.php');
        exit;
    }
    
    // Regenerate session ID periodically for security
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

/**
 * Check if account is locked due to failed attempts
 */
function isAccountLocked($db, $username) {
    $stmt = $db->prepare("SELECT locked_until FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $user['locked_until']) {
        $lockedUntil = strtotime($user['locked_until']);
        if ($lockedUntil > time()) {
            return true;
        } else {
            // Unlock account
            $stmt = $db->prepare("UPDATE users SET locked_until = NULL, failed_attempts = 0 WHERE username = ?");
            $stmt->execute([$username]);
        }
    }
    
    return false;
}

/**
 * Check rate limiting based on IP
 */
function checkRateLimit($db, $ipAddress) {
    // Allow max 5 attempts per IP in last 15 minutes
    $stmt = $db->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                         WHERE ip_address = ? 
                         AND attempted_at > datetime('now', '-15 minutes')
                         AND success = 0");
    $stmt->execute([$ipAddress]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['attempts'] < 5;
}

/**
 * Log login attempt
 */
function logLoginAttempt($db, $username, $ipAddress, $success) {
    $stmt = $db->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, ?)");
    $stmt->execute([$username, $ipAddress, $success ? 1 : 0]);
}

/**
 * Authenticate user
 */
function authenticateUser($username, $password) {
    $db = initAuthDatabase();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Check rate limiting
    if (!checkRateLimit($db, $ipAddress)) {
        return ['success' => false, 'error' => 'Too many failed attempts. Please try again later.'];
    }
    
    // Check if account is locked
    if (isAccountLocked($db, $username)) {
        return ['success' => false, 'error' => 'Account is temporarily locked. Please try again later.'];
    }
    
    // Get user from database
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        logLoginAttempt($db, $username, $ipAddress, false);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }
    
    // Verify password
    if (!password_verify($password, $user['password_hash'])) {
        // Increment failed attempts
        $failedAttempts = $user['failed_attempts'] + 1;
        $lockedUntil = null;
        
        // Lock account after 5 failed attempts for 15 minutes
        if ($failedAttempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        }
        
        $stmt = $db->prepare("UPDATE users SET failed_attempts = ?, locked_until = ? WHERE id = ?");
        $stmt->execute([$failedAttempts, $lockedUntil, $user['id']]);
        
        logLoginAttempt($db, $username, $ipAddress, false);
        return ['success' => false, 'error' => 'Invalid username or password.'];
    }
    
    // Successful login - check if 2FA is enabled
    // Reset failed attempts
    $stmt = $db->prepare("UPDATE users SET failed_attempts = 0, locked_until = NULL WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Check if 2FA is enabled
    if ($user['totp_enabled'] == 1) {
        // Store temporary session data for 2FA verification
        session_regenerate_id(true);
        $_SESSION['2fa_user_id'] = $user['id'];
        $_SESSION['2fa_username'] = $user['username'];
        $_SESSION['2fa_timestamp'] = time();
        
        logLoginAttempt($db, $username, $ipAddress, false); // Not fully logged in yet
        
        return ['success' => true, 'requires_2fa' => true, 'user' => $user];
    }
    
    // No 2FA - complete login
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['last_regeneration'] = time();
    
    // Update last login
    $stmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    logLoginAttempt($db, $username, $ipAddress, true);
    logAudit('login', 'user', $user['id']);
    
    return ['success' => true, 'user' => $user];
}

/**
 * Logout user
 */
function logoutUser() {
    $_SESSION = array();
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}

/**
 * Create new user
 */
function createUser($username, $password, $email = null) {
    $db = initAuthDatabase();
    
    // Validate password strength
    if (strlen($password) < 8) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters long.'];
    }
    
    // Check if username already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Username already exists.'];
    }
    
    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Check if this is the first user (should be admin)
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $isFirstUser = $result['count'] == 0;
    $role = $isFirstUser ? 'admin' : 'user';
    
    // Insert user
    try {
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, email, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $passwordHash, $email, $role]);
        
        return ['success' => true, 'user_id' => $db->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => 'Failed to create user: ' . $e->getMessage()];
    }
}

/**
 * Check if any users exist
 */
function hasUsers() {
    $db = initAuthDatabase();
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'] > 0;
}

/**
 * Get current user info
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    $db = initAuthDatabase();
    $stmt = $db->prepare("SELECT id, username, email, role, totp_enabled, created_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if user is admin
 */
function isAdmin() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $db = initAuthDatabase();
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $user && $user['role'] === 'admin';
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        http_response_code(403);
        die('Access denied. Admin privileges required.');
    }
}

/**
 * Get all users (admin only)
 */
function getAllUsers() {
    $db = initAuthDatabase();
    $stmt = $db->query("SELECT id, username, email, role, totp_enabled, created_at, last_login FROM users ORDER BY created_at ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add permissions for each user
    $mainDb = initDatabase();
    foreach ($users as &$user) {
        $stmt = $mainDb->prepare("SELECT permission_key, permission_value FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add can_create_sites permission for backward compatibility
        $canCreateSites = true; // Default
        foreach ($permissions as $perm) {
            if ($perm['permission_key'] === 'can_create_sites') {
                $canCreateSites = $perm['permission_value'] == 1;
                break;
            }
        }
        $user['can_create_sites'] = $canCreateSites ? 1 : 0;
    }
    
    return $users;
}

/**
 * Get user by ID
 */
function getUserById($userId) {
    $db = initAuthDatabase();
    $stmt = $db->prepare("SELECT id, username, email, role, totp_enabled, totp_secret, created_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        // Add permissions from user_permissions table
        $mainDb = initDatabase();
        $stmt = $mainDb->prepare("SELECT permission_key, permission_value FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add can_create_sites permission for backward compatibility
        $canCreateSites = true; // Default
        foreach ($permissions as $perm) {
            if ($perm['permission_key'] === 'can_create_sites') {
                $canCreateSites = $perm['permission_value'] == 1;
                break;
            }
        }
        $user['can_create_sites'] = $canCreateSites ? 1 : 0;
    }
    
    return $user;
}

/**
 * Update user
 */
function updateUser($userId, $data) {
    $db = initAuthDatabase();
    
    $updates = [];
    $params = [];
    
    if (isset($data['role'])) {
        $updates[] = "role = ?";
        $params[] = $data['role'];
    }
    
    if (isset($data['can_create_sites'])) {
        $updates[] = "can_create_sites = ?";
        $params[] = $data['can_create_sites'] ? 1 : 0;
    }
    
    if (isset($data['email'])) {
        $updates[] = "email = ?";
        $params[] = $data['email'];
    }
    
    if (empty($updates)) {
        return ['success' => false, 'error' => 'No fields to update'];
    }
    
    $params[] = $userId;
    $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete user
 */
function deleteUser($userId) {
    $db = initAuthDatabase();
    
    // Prevent deleting the last admin
    $stmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $db->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user['role'] === 'admin' && $result['count'] <= 1) {
        return ['success' => false, 'error' => 'Cannot delete the last admin user'];
    }
    
    try {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Check if user can create sites
 */
function canCreateSites($userId = null) {
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) {
        return false;
    }
    
    // Admins can always create sites
    if (isAdmin()) {
        return true;
    }
    
    // Check user_permissions table
    $mainDb = initDatabase();
    $stmt = $mainDb->prepare("SELECT permission_value FROM user_permissions WHERE user_id = ? AND permission_key = 'can_create_sites'");
    $stmt->execute([$userId]);
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Default to true if no permission record exists (backward compatibility)
    return $permission ? ($permission['permission_value'] == 1) : true;
}

/**
 * Check if user can access site
 */
function canAccessSite($userId, $siteId, $permission = 'view') {
    // Admins can access everything
    if (isAdmin()) {
        return true;
    }
    
    $mainDb = initDatabase();
    
    // Check if user owns the site
    $stmt = $mainDb->prepare("SELECT owner_id FROM sites WHERE id = ?");
    $stmt->execute([$siteId]);
    $site = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($site && $site['owner_id'] == $userId) {
        return true;
    }
    
    // Check site permissions - use mainDb, not authDb
    $stmt = $mainDb->prepare("SELECT permission_level FROM site_permissions WHERE user_id = ? AND site_id = ?");
    $stmt->execute([$userId, $siteId]);
    $perm = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$perm) {
        return false;
    }
    
    // Permission hierarchy: manage > edit > view
    $permLevels = ['view' => 1, 'edit' => 2, 'manage' => 3];
    $requiredLevel = $permLevels[$permission] ?? 1;
    $userLevel = $permLevels[$perm['permission_level']] ?? 0;
    
    return $userLevel >= $requiredLevel;
}

/**
 * Check if user can manage site (edit settings, change PHP version, etc.)
 */
function canManageSite($userId, $siteId) {
    return canAccessSite($userId, $siteId, 'manage');
}

/**
 * Get sites accessible by user
 */
function getUserSites($userId) {
    $mainDb = initDatabase();
    
    // Admins see all sites
    if (isAdmin()) {
        $stmt = $mainDb->query("SELECT * FROM sites ORDER BY created_at DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get owned sites and sites with permissions
    $stmt = $mainDb->prepare("
        SELECT DISTINCT s.* 
        FROM sites s
        LEFT JOIN site_permissions sp ON s.id = sp.site_id
        WHERE s.owner_id = ? OR sp.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Grant site permission to user
 */
function grantSitePermission($userId, $siteId, $permission = 'view') {
    $db = initDatabase(); // Use main database, not auth database
    
    try {
        $stmt = $db->prepare("INSERT OR REPLACE INTO site_permissions (user_id, site_id, permission_level, granted_by) VALUES (?, ?, ?, ?)");
        $grantedBy = $_SESSION['user_id'] ?? null;
        $stmt->execute([$userId, $siteId, $permission, $grantedBy]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Revoke site permission from user
 */
function revokeSitePermission($userId, $siteId) {
    $db = initDatabase(); // Use main database, not auth database
    
    try {
        $stmt = $db->prepare("DELETE FROM site_permissions WHERE user_id = ? AND site_id = ?");
        $stmt->execute([$userId, $siteId]);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Get site permissions for a user
 */
function getSitePermissions($siteId) {
    $mainDb = initDatabase(); // Use main database for site_permissions
    $authDb = initAuthDatabase(); // Use auth database for users
    
    $stmt = $mainDb->prepare("
        SELECT u.id, u.username, u.email, sp.permission_level, sp.created_at
        FROM site_permissions sp
        JOIN users u ON sp.user_id = u.id
        WHERE sp.site_id = ?
        ORDER BY u.username
    ");
    $stmt->execute([$siteId]);
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Rename permission_level to permission for backward compatibility
    foreach ($permissions as &$perm) {
        $perm['permission'] = $perm['permission_level'];
        unset($perm['permission_level']);
    }
    
    return $permissions;
}

/**
 * Log audit event
 */
function logAudit($action, $resourceType = null, $resourceId = null, $details = null) {
    if (!isLoggedIn()) {
        return;
    }
    
    $db = initAuthDatabase();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    $stmt = $db->prepare("INSERT INTO audit_log (user_id, action, resource_type, resource_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $action,
        $resourceType,
        $resourceId,
        $details ? json_encode($details) : null,
        $ipAddress
    ]);
}

// ============================================
// 2FA / TOTP Functions
// ============================================

require_once __DIR__ . '/totp.php';

/**
 * Enable 2FA for user
 */
function enable2FA($userId, $secret, $backupCodes) {
    $db = initAuthDatabase();
    
    try {
        $stmt = $db->prepare("UPDATE users SET totp_secret = ?, totp_enabled = 1, totp_backup_codes = ? WHERE id = ?");
        $stmt->execute([$secret, json_encode($backupCodes), $userId]);
        logAudit('2fa_enabled', 'user', $userId);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Disable 2FA for user
 */
function disable2FA($userId) {
    $db = initAuthDatabase();
    
    try {
        $stmt = $db->prepare("UPDATE users SET totp_secret = NULL, totp_enabled = 0, totp_backup_codes = NULL WHERE id = ?");
        $stmt->execute([$userId]);
        logAudit('2fa_disabled', 'user', $userId);
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Verify 2FA code
 */
function verify2FACode($userId, $code) {
    $db = initAuthDatabase();
    
    $stmt = $db->prepare("SELECT totp_secret, totp_backup_codes FROM users WHERE id = ? AND totp_enabled = 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user || !$user['totp_secret']) {
        return false;
    }
    
    $totp = new TOTP();
    
    // Try TOTP code first
    if ($totp->verifyCode($user['totp_secret'], $code)) {
        return true;
    }
    
    // Try backup codes
    if ($user['totp_backup_codes']) {
        $backupCodes = json_decode($user['totp_backup_codes'], true);
        if (is_array($backupCodes) && in_array($code, $backupCodes)) {
            // Remove used backup code
            $backupCodes = array_diff($backupCodes, [$code]);
            $stmt = $db->prepare("UPDATE users SET totp_backup_codes = ? WHERE id = ?");
            $stmt->execute([json_encode(array_values($backupCodes)), $userId]);
            logAudit('2fa_backup_code_used', 'user', $userId);
            return true;
        }
    }
    
    return false;
}

/**
 * Generate backup codes
 */
function generateBackupCodes($count = 10) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return $codes;
}

/**
 * Check if user has 2FA enabled
 */
function has2FAEnabled($userId) {
    $db = initAuthDatabase();
    $stmt = $db->prepare("SELECT totp_enabled FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return isset($user['totp_enabled']) && $user['totp_enabled'] == 1;
}
