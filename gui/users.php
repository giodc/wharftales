<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Redirect to SSL URL if dashboard has SSL enabled and request is from :9000
redirectToSSLIfEnabled();

// Require admin authentication
requireAdmin();

$db = initDatabase();
$currentUser = getCurrentUser();
$users = getAllUsers();
$allSites = getAllSites($db);

// Get site counts for each user
$siteCounts = [];
foreach ($users as $user) {
    // Count owned sites
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM sites WHERE owner_id = ?");
    $stmt->execute([$user['id']]);
    $ownedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Count granted sites
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM site_permissions WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $grantedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $siteCounts[$user['id']] = [
        'owned' => $ownedCount,
        'granted' => $grantedCount,
        'total' => $ownedCount + $grantedCount
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - WharfTales</title>
      <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicon.svg" />
<link rel="shortcut icon" href="/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png" />
<meta name="apple-mobile-web-app-title" content="WharfTales" />
<link rel="manifest" href="/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <div class="container mt-5">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="bi bi-people me-2"></i>User Management</h2>
                    <button class="btn btn-primary" onclick="showCreateUserModal()">
                        <i class="bi bi-person-plus me-2"></i>Add User
                    </button>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Granted Sites</th>
                                        <th>Can Create Sites</th>
                                        <th>2FA</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                            <?php if ($user['id'] == $currentUser['id']): ?>
                                                <span class="badge bg-info">You</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                        <td>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <span class="badge bg-danger">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            $counts = $siteCounts[$user['id']];
                                            if ($user['role'] === 'admin'): 
                                            ?>
                                                <span class="badge bg-info" title="Admins have access to all sites">
                                                    <i class="bi bi-infinity"></i> All Sites
                                                </span>
                                            <?php elseif ($counts['total'] > 0): ?>
                                                <span class="badge bg-success" title="<?= $counts['owned'] ?> owned, <?= $counts['granted'] ?> granted">
                                                    <i class="bi bi-hdd-stack"></i> <?= $counts['total'] ?> site<?= $counts['total'] != 1 ? 's' : '' ?>
                                                </span>
                                                <?php if ($counts['owned'] > 0): ?>
                                                    <small class="text-muted">(<?= $counts['owned'] ?> owned)</small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-dash-circle"></i> No sites
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['can_create_sites']): ?>
                                                <i class="bi bi-check-circle text-success"></i> Yes
                                            <?php else: ?>
                                                <i class="bi bi-x-circle text-danger"></i> No
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['totp_enabled']): ?>
                                                <i class="bi bi-shield-check text-success"></i> Enabled
                                            <?php else: ?>
                                                <i class="bi bi-shield text-muted"></i> Disabled
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($user['last_login']): ?>
                                                <?= date('M d, Y H:i', strtotime($user['last_login'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editUser(<?= $user['id'] ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-info" onclick="manageSitePermissions(<?= $user['id'] ?>)">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <?php if ($user['id'] != $currentUser['id']): ?>
                                                <button class="btn btn-outline-danger" onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createUserForm">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required minlength="3">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required minlength="8">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="can_create_sites" id="canCreateSites" checked>
                                <label class="form-check-label" for="canCreateSites">
                                    Can create sites
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="createUser()">Create User</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" id="editUsername" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role" id="editRole">
                                <option value="user">User</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="can_create_sites" id="editCanCreateSites">
                                <label class="form-check-label" for="editCanCreateSites">
                                    Can create sites
                                </label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateUser()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Site Permissions Modal -->
    <div class="modal fade" id="sitePermissionsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-key me-2"></i>Manage Site Permissions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="permUserId">
                    <p class="text-muted">Grant access to specific sites for user: <strong id="permUsername"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label">Add Site Access</label>
                        <div class="input-group">
                            <select class="form-select" id="siteSelect">
                                <option value="">Select a site...</option>
                                <?php foreach ($allSites as $site): ?>
                                <option value="<?= $site['id'] ?>"><?= htmlspecialchars($site['name']) ?> (<?= htmlspecialchars($site['domain']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <select class="form-select" id="permissionSelect" style="max-width: 150px;">
                                <option value="view">View</option>
                                <option value="edit">Edit</option>
                                <option value="manage">Manage</option>
                            </select>
                            <button class="btn btn-primary" onclick="grantPermission()">
                                <i class="bi bi-plus"></i> Grant
                            </button>
                        </div>
                    </div>

                    <div id="currentPermissions">
                        <h6>Current Permissions</h6>
                        <div id="permissionsList"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/modals.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/app.js?v=3.0.<?= time() ?>"></script>
    <script>
        function showCreateUserModal() {
            new bootstrap.Modal(document.getElementById('createUserModal')).show();
        }

        function createUser() {
            const form = document.getElementById('createUserForm');
            const formData = new FormData(form);
            
            fetch('/api.php?action=create_user', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User created successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            })
            .catch(error => {
                alert('Error creating user: ' + error);
            });
        }

        function editUser(userId) {
            fetch('/api.php?action=get_user&id=' + userId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('editUserId').value = data.user.id;
                    document.getElementById('editUsername').value = data.user.username;
                    document.getElementById('editEmail').value = data.user.email || '';
                    document.getElementById('editRole').value = data.user.role;
                    document.getElementById('editCanCreateSites').checked = data.user.can_create_sites == 1;
                    
                    new bootstrap.Modal(document.getElementById('editUserModal')).show();
                } else {
                    alert('Error loading user: ' + data.error);
                }
            });
        }

        function updateUser() {
            const form = document.getElementById('editUserForm');
            const formData = new FormData(form);
            
            fetch('/api.php?action=update_user', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User updated successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function deleteUser(userId, username) {
            if (!confirm('Are you sure you want to delete user "' + username + '"?')) {
                return;
            }
            
            fetch('/api.php?action=delete_user&id=' + userId, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('User deleted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function manageSitePermissions(userId) {
            document.getElementById('permUserId').value = userId;
            
            fetch('/api.php?action=get_user&id=' + userId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('permUsername').textContent = data.user.username;
                    loadUserPermissions(userId);
                    new bootstrap.Modal(document.getElementById('sitePermissionsModal')).show();
                }
            });
        }

        function loadUserPermissions(userId) {
            fetch('/api.php?action=get_user_permissions&user_id=' + userId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const list = document.getElementById('permissionsList');
                    if (data.permissions.length === 0) {
                        list.innerHTML = '<p class="text-muted">No site permissions granted yet.</p>';
                    } else {
                        let html = '<div class="list-group">';
                        data.permissions.forEach(perm => {
                            const badgeColor = perm.permission === 'manage' ? 'primary' : (perm.permission === 'edit' ? 'info' : 'secondary');
                            html += `
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${perm.site_name}</strong>
                                        <br><small class="text-muted">${perm.domain}</small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <select class="form-select form-select-sm" style="width: 120px;" onchange="changePermission(${userId}, ${perm.site_id}, this.value)">
                                            <option value="view" ${perm.permission === 'view' ? 'selected' : ''}>View</option>
                                            <option value="edit" ${perm.permission === 'edit' ? 'selected' : ''}>Edit</option>
                                            <option value="manage" ${perm.permission === 'manage' ? 'selected' : ''}>Manage</option>
                                        </select>
                                        <button class="btn btn-sm btn-outline-danger" onclick="revokePermission(${userId}, ${perm.site_id})" title="Remove access">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                        list.innerHTML = html;
                    }
                }
            });
        }

        function grantPermission() {
            const userId = document.getElementById('permUserId').value;
            const siteId = document.getElementById('siteSelect').value;
            const permission = document.getElementById('permissionSelect').value;
            
            if (!siteId) {
                alert('Please select a site');
                return;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('site_id', siteId);
            formData.append('permission', permission);
            
            fetch('/api.php?action=grant_site_permission', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadUserPermissions(userId);
                    document.getElementById('siteSelect').value = '';
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }

        function changePermission(userId, siteId, newPermission) {
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('site_id', siteId);
            formData.append('permission', newPermission);
            
            fetch('/api.php?action=grant_site_permission', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message briefly
                    const msg = document.createElement('div');
                    msg.className = 'alert alert-success position-fixed top-0 start-50 translate-middle-x mt-3';
                    msg.style.zIndex = '9999';
                    msg.innerHTML = '<i class="bi bi-check-circle me-2"></i>Permission updated to ' + newPermission;
                    document.body.appendChild(msg);
                    setTimeout(() => msg.remove(), 2000);
                } else {
                    alert('Error: ' + data.error);
                    loadUserPermissions(userId); // Reload to reset dropdown
                }
            });
        }

        function revokePermission(userId, siteId) {
            if (!confirm('Revoke access to this site?')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('site_id', siteId);
            
            fetch('/api.php?action=revoke_site_permission', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadUserPermissions(userId);
                } else {
                    alert('Error: ' + data.error);
                }
            });
        }
    </script>
</body>
</html>
