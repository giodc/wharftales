<?php
// Navigation component - requires $currentUser to be set
if (!isset($currentUser)) {
    $currentUser = getCurrentUser();
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold d-flex align-items-center" href="/">
<svg width="32" height="32" id="Logo" data-name="Logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1024 1024">
  <path d="M651.36,159.58c-174.5,0-319.2,127.49-346.09,294.4-22.4-8.08-46.56-12.49-71.75-12.49-116.79,0-211.46,94.68-211.46,211.46s94.68,211.46,211.46,211.46l417.84-3.66c193.62,0,350.59-156.96,350.59-350.59s-156.96-350.59-350.59-350.59ZM758.92,431.26c-19.21,0-34.78-15.57-34.78-34.78s15.57-34.78,34.78-34.78,34.78,15.57,34.78,34.78-15.57,34.78-34.78,34.78Z"/>
</svg>
        Wharftales
            <span class="badge bg-secondary ms-2 fw-normal" style="font-size: 0.7rem;">
               <?php 
                    $versionFile = '/var/www/html/../VERSION';
                    echo file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.0.0';
                ?>
            </span>
        </a>
        <?php
                // Check for updates notification
                $updateNotification = getSetting($db, 'update_notification', '0');
                if ($updateNotification === '1'):
                    $cachedUpdate = getSetting($db, 'cached_update_info', null);
                    $updateData = $cachedUpdate ? json_decode($cachedUpdate, true) : null;
                ?>
                <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link position-relative fw-semibold" href="/settings.php#updates" title="Update Available">
                        Update Available
                        <?php if ($updateData && isset($updateData['latest_version'])): ?>
                        <span class="badge bg-success text-light ms-1"><?= htmlspecialchars($updateData['latest_version']) ?></span>
                        <?php endif; ?>
                       
                    </a>
                </li></ul>
                <?php endif; ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active fw-semibold' : '' ?>" href="/">
                        <i class="bi bi-grid me-1"></i>Dashboard
                    </a>
                </li>
                <?php if (isAdmin()): ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'users.php' ? 'active fw-semibold' : '' ?>" href="/users.php">
                        <i class="bi bi-people me-1"></i>Users
                    </a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'settings.php' ? 'active fw-semibold' : '' ?>" href="/settings.php">
                        <i class="bi bi-gear me-1"></i>Settings
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($currentUser['username']) ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="userDropdown">
                        <li>
                            <a class="dropdown-item" href="#" onclick="showPasswordModal(); return false;">
                                <i class="bi bi-key me-2"></i>Change Password
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="show2FAModal(); return false;">
                                <i class="bi bi-shield-check me-2"></i>Two-Factor Auth
                            </a>
                        </li>
                        <?php if (isAdmin()): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <span class="dropdown-item-text text-muted small">
                                <i class="bi bi-shield-fill me-1"></i>Admin Account
                            </span>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="/logout.php">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>
