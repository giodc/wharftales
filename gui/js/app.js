let createModal, editModal, passwordModal, updateModal, twoFactorModal;

// Version check - if you see this in console, the new JS is loaded
console.log("WharfTales JS v5.5 loaded - Fixed function name conflicts!");

// Helper function for API calls with proper error handling
async function apiCall(url, options = {}) {
    try {
        const response = await fetch(url, options);
        
        // Check if response is HTML (likely login page redirect)
        const contentType = response.headers.get("content-type");
        if (contentType && contentType.includes("text/html")) {
            throw new Error("SESSION_EXPIRED");
        }
        
        // Check for authentication error
        if (response.status === 401) {
            throw new Error("SESSION_EXPIRED");
        }
        
        // Try to parse JSON robustly
        const text = await response.text();
        let result;
        try {
            result = JSON.parse(text);
        } catch (e) {
            console.error("Failed to parse API response. Raw response:", text);
            // If response was not OK but we failed to parse JSON, throw with status
            if (!response.ok) {
                throw new Error(`Server returned error ${response.status} (invalid JSON)`);
            }
            throw new Error("Server returned invalid response. Please check console for details.");
        }

        return result;
    } catch (error) {
        if (error.message === "SESSION_EXPIRED") {
            showAlert("danger", "Session expired. Redirecting to login...");
            setTimeout(() => window.location.href = "/login.php", 1500);
            throw error;
        }
        throw error;
    }
}

document.addEventListener("DOMContentLoaded", function() {
    // Initialize modals only if they exist on the page
    const createModalEl = document.getElementById("createModal");
    const editModalEl = document.getElementById("editModal");
    const passwordModalEl = document.getElementById("passwordModal");
    const updateModalEl = document.getElementById("updateModal");
    const twoFactorModalEl = document.getElementById("twoFactorModal");
    
    if (createModalEl) createModal = new bootstrap.Modal(createModalEl);
    if (editModalEl) editModal = new bootstrap.Modal(editModalEl);
    if (passwordModalEl) passwordModal = new bootstrap.Modal(passwordModalEl);
    if (updateModalEl) updateModal = new bootstrap.Modal(updateModalEl);
    if (twoFactorModalEl) twoFactorModal = new bootstrap.Modal(twoFactorModalEl);
    
    // Check for updates on page load
    checkForUpdatesBackground();
    
    // Auto-generate domain from name (only on dashboard)
    const nameInput = document.querySelector("input[name=\"name\"]");
    if (nameInput) {
        nameInput.addEventListener("input", function(e) {
            const domainInput = document.querySelector("input[name=\"domain\"]");
            if (domainInput) {
                const domain = e.target.value.toLowerCase()
                    .replace(/[^a-z0-9\s]/g, "")
                    .replace(/\s+/g, "-")
                    .substring(0, 20);
                domainInput.value = domain;
            }
        });
    }

    // Handle domain suffix changes (only on dashboard)
    const domainSuffixSelect = document.querySelector("select[name=\"domain_suffix\"]");
    if (domainSuffixSelect) {
        domainSuffixSelect.addEventListener("change", function(e) {
            const customField = document.getElementById("customDomainField");
            const sslCheck = document.getElementById("sslCheck");
            const domainSuffix = e.target.value;

            if (domainSuffix === "custom") {
                customField.style.display = "block";
                sslCheck.disabled = false;
            } else {
                customField.style.display = "none";
                
                // Enable SSL for custom wildcard domains (starting with .)
                if (domainSuffix.startsWith(".")) {
                    sslCheck.disabled = false;
                } else {
                    sslCheck.disabled = true;
                    sslCheck.checked = false;
                    // Hide SSL challenge options when SSL is disabled
                    const sslChallengeOptions = document.getElementById("sslChallengeOptions");
                    if (sslChallengeOptions) {
                        sslChallengeOptions.style.display = "none";
                    }
                }
            }
        });
    }

    // Update site statuses (only on dashboard)
    if (document.querySelector(".status-badge")) {
        updateAllSiteStatuses();
        setInterval(updateAllSiteStatuses, 30000); // Check every 30 seconds
    }
    
    // Add password strength checker on input (for password modal)
    const newPasswordField = document.getElementById('new_password');
    if (newPasswordField) {
        newPasswordField.addEventListener('input', function() {
            checkPasswordStrength(this.value);
        });
    }
});

function showCreateModal() {
    // Initialize SSL options based on current domain suffix selection
    const domainSuffixSelect = document.querySelector("select[name=\"domain_suffix\"]");
    if (domainSuffixSelect) {
        toggleSSLOptions(domainSuffixSelect.value);
    }
    createModal.show();
}

function toggleSSLOptions(domainSuffix) {
    const sslCheck = document.getElementById("sslCheck");
    const sslChallengeOptions = document.getElementById("sslChallengeOptions");
    
    // Enable SSL for custom domains and custom wildcard domains (starting with .)
    const isCustomDomain = domainSuffix === "custom" || domainSuffix.startsWith(".");
    
    if (!isCustomDomain) {
        sslCheck.disabled = true;
        sslCheck.checked = false;
        sslChallengeOptions.style.display = "none";
    } else {
        sslCheck.disabled = false;
    }
}

function toggleSSLChallengeOptions() {
    const sslCheck = document.getElementById("sslCheck");
    const sslChallengeOptions = document.getElementById("sslChallengeOptions");
    
    if (sslCheck.checked) {
        sslChallengeOptions.style.display = "block";
    } else {
        sslChallengeOptions.style.display = "none";
        document.getElementById("dnsProviderOptions").style.display = "none";
    }
}

function toggleDNSProviderOptions(challengeMethod) {
    const dnsProviderOptions = document.getElementById("dnsProviderOptions");
    
    if (challengeMethod === "dns") {
        dnsProviderOptions.style.display = "block";
    } else {
        dnsProviderOptions.style.display = "none";
        // Hide all DNS provider fields
        document.querySelectorAll(".dns-provider-fields").forEach(el => {
            el.style.display = "none";
        });
    }
}

function showDNSProviderFields(provider) {
    // Hide all DNS provider fields first
    document.querySelectorAll(".dns-provider-fields").forEach(el => {
        el.style.display = "none";
    });
    
    // Show the selected provider's fields
    if (provider) {
        const fieldId = provider + "Fields";
        const field = document.getElementById(fieldId);
        if (field) {
            field.style.display = "block";
        }
    }
}

// Cloudflare auth toggle removed - now only uses API Token

function toggleTypeOptions(type) {
    const wpOptions = document.getElementById("wordpressOptions");
    const phpOptions = document.getElementById("phpOptions");
    const laravelOptions = document.getElementById("laravelOptions");
    const mariadbOptions = document.getElementById("mariadbOptions");
    const phpVersionRow = document.getElementById("phpVersionRow");
    const domainSslRow = document.getElementById("domainSslRow");
    const domainInput = document.getElementById("domainInput");

    // Hide all options first
    wpOptions.style.display = "none";
    phpOptions.style.display = "none";
    laravelOptions.style.display = "none";
    mariadbOptions.style.display = "none";
    phpVersionRow.style.display = "none";
    
    // Show domain/SSL by default and make domain required
    domainSslRow.style.display = "flex";
    if (domainInput) domainInput.required = true;

    if (type === "wordpress") {
        wpOptions.style.display = "block";
        phpVersionRow.style.display = "block"; // Show PHP version for WordPress

        // Generate strong password
        const passwordField = document.querySelector("input[name=\"wp_password\"]");
        if (!passwordField.value) {
            passwordField.value = generatePassword(16);
        }
    } else if (type === "php") {
        phpOptions.style.display = "block";
        phpVersionRow.style.display = "block"; // Show PHP version for PHP
        
        // Add GitHub repo input listener
        setTimeout(() => {
            const phpGithubRepo = document.querySelector("input[name=\"php_github_repo\"]");
            if (phpGithubRepo && !phpGithubRepo.dataset.listenerAdded) {
                phpGithubRepo.addEventListener("input", function() {
                    const phpGithubOptions = document.getElementById("phpGithubOptions");
                    phpGithubOptions.style.display = this.value.trim() ? "flex" : "none";
                });
                phpGithubRepo.dataset.listenerAdded = "true";
            }
        }, 100);
    } else if (type === "laravel") {
        laravelOptions.style.display = "block";
        phpVersionRow.style.display = "block"; // Show PHP version for Laravel
        
        // Add GitHub repo input listener
        setTimeout(() => {
            const laravelGithubRepo = document.querySelector("input[name=\"laravel_github_repo\"]");
            if (laravelGithubRepo && !laravelGithubRepo.dataset.listenerAdded) {
                laravelGithubRepo.addEventListener("input", function() {
                    const laravelGithubOptions = document.getElementById("laravelGithubOptions");
                    laravelGithubOptions.style.display = this.value.trim() ? "flex" : "none";
                });
                laravelGithubRepo.dataset.listenerAdded = "true";
            }
        }, 100);
    } else if (type === "mariadb") {
        mariadbOptions.style.display = "block";
        
        // Hide domain and SSL for database services (not needed)
        domainSslRow.style.display = "none";
        if (domainInput) domainInput.required = false;
        
        // Auto-generate passwords if empty
        const rootPasswordField = document.querySelector("input[name=\"mariadb_root_password\"]");
        const userPasswordField = document.querySelector("input[name=\"mariadb_password\"]");
        if (!rootPasswordField.value) {
            rootPasswordField.value = generatePassword(24);
        }
        if (!userPasswordField.value) {
            userPasswordField.value = generatePassword(20);
        }
    }
}

function generatePassword(length) {
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let password = "";
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}

function toggleCustomDbFields() {
    const dbType = document.getElementById("wpDbType").value;
    const customDbFields = document.getElementById("customDbFields");
    
    if (dbType === "custom") {
        customDbFields.style.display = "block";
    } else {
        customDbFields.style.display = "none";
    }
}

async function createSite(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());

    // Convert checkbox values
    data.ssl = formData.has("ssl");
    data.wp_optimize = formData.has("wp_optimize");

    const submitBtn = event.target.querySelector("button[type=\"submit\"]");
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Deploying...";
    submitBtn.disabled = true;

    try {
        const result = await apiCall("api.php?action=create_site", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data)
        });

        if (result.success) {
            if (result.warning) {
                showAlert("warning", result.message + (result.error_details ? "<br><small>Error: " + result.error_details + "</small>" : ""));
                createModal.hide();
                setTimeout(() => location.reload(), 3000);
            } else {
                showAlert("success", result.message || "Application deployed successfully!");
                createModal.hide();
                setTimeout(() => location.reload(), 1500);
            }
        } else {
            showAlert("danger", result.error || "Failed to create application");
        }
    } catch (error) {
        if (error.message !== "SESSION_EXPIRED") {
            console.error("API Error:", error);
            showAlert("danger", "Network error: " + error.message + ". Please check console for details.");
        }
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

async function editSite(id) {
    try {
        const result = await apiCall("api.php?action=get_site&id=" + id);

        if (result.success) {
            const site = result.site;
            
            // Basic fields
            document.getElementById("editSiteId").value = site.id;
            document.getElementById("editName").value = site.name;
            document.getElementById("editDomain").value = site.domain;
            document.getElementById("editSsl").checked = site.ssl == 1;
            document.getElementById("editStatus").value = site.status;
            
            // Type fields
            document.getElementById("editType").value = site.type;
            document.getElementById("editTypeDisplay").value = site.type.charAt(0).toUpperCase() + site.type.slice(1);
            
            // Container info
            document.getElementById("editContainerName").value = site.container_name;
            document.getElementById("editContainerNameDisplay").textContent = site.container_name;
            
            // Created date
            const createdDate = new Date(site.created_at);
            document.getElementById("editCreatedAt").textContent = createdDate.toLocaleString();
            
            // GitHub deployment settings (only for PHP and Laravel)
            const githubSection = document.getElementById("editGithubSection");
            if (site.type === 'php' || site.type === 'laravel') {
                githubSection.style.display = 'block';
                
                // Populate GitHub fields
                document.getElementById("editGithubRepo").value = site.github_repo || '';
                document.getElementById("editGithubBranch").value = site.github_branch || 'main';
                document.getElementById("editGithubToken").value = ''; // Never show existing token
                
                // Show GitHub info if repo is configured
                const githubInfo = document.getElementById("editGithubInfo");
                if (site.github_repo) {
                    githubInfo.style.display = 'block';
                    document.getElementById("editGithubCommit").textContent = site.github_last_commit ? site.github_last_commit.substring(0, 7) : '-';
                    document.getElementById("editGithubLastPull").textContent = site.github_last_pull ? new Date(site.github_last_pull).toLocaleString() : 'Never';
                } else {
                    githubInfo.style.display = 'none';
                }
            } else {
                githubSection.style.display = 'none';
            }
            
            editModal.show();
        } else {
            showAlert("danger", result.error || "Failed to load site data");
        }
    } catch (error) {
        if (error.message !== "SESSION_EXPIRED") {
            showAlert("danger", "Network error: " + error.message);
        }
    }
}

async function updateSite(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const data = Object.fromEntries(formData.entries());
    data.ssl = formData.has("ssl");

    const submitBtn = event.target.querySelector("button[type=\"submit\"]");
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Updating...";
    submitBtn.disabled = true;

    try {
        const result = await apiCall("api.php?action=update_site", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify(data)
        });

        if (result.success) {
            if (result.needs_restart || result.domain_changed) {
                showAlert("warning", result.message);
            } else {
                showAlert("success", result.message || "Application updated successfully!");
            }
            editModal.hide();
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert("danger", result.error || "Failed to update application");
        }
    } catch (error) {
        if (error.message !== "SESSION_EXPIRED") {
            showAlert("danger", "Network error: " + error.message);
        }
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

function viewSite(domain, ssl) {
    const protocol = ssl ? "https" : "http";
    let url;
    
    // Handle different domain formats
    if (domain.includes(":")) {
        // Port-based domain
        url = protocol + "://" + window.location.hostname + domain;
    } else if (domain.includes(".test.local") || domain.includes(".localhost")) {
        // Local domain
        url = protocol + "://" + domain;
    } else {
        // Custom domain
        url = protocol + "://" + domain;
    }
    
    window.open(url, "_blank");
}

async function deleteSite(id) {
    if (!confirm("Are you sure you want to delete this application? This action cannot be undone.")) {
        return;
    }

    try {
        const result = await apiCall("api.php?action=delete_site&id=" + id, {
            method: "GET"
        });

        if (result.success) {
            showAlert("success", "Application deleted successfully");
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert("danger", result.error || "Failed to delete application");
        }
    } catch (error) {
        if (error.message !== "SESSION_EXPIRED") {
            showAlert("danger", "Network error: " + error.message);
        }
    }
}

async function updateAllSiteStatuses() {
    const statusBadges = document.querySelectorAll(".status-badge");

    for (let badge of statusBadges) {
        const card = badge.closest(".card");
        const siteId = getSiteIdFromCard(card);

        if (siteId) {
            try {
                const response = await fetch("api.php?action=site_status&id=" + siteId);
                const result = await response.json();

                if (result.status) {
                    updateStatusBadge(badge, result.status);
                    updateStatusIndicator(card, result.status);
                }
            } catch (error) {
                console.error("Failed to update status for site", siteId, error);
            }
        }
    }
}

function getSiteIdFromCard(card) {
    const editBtn = card.querySelector("button[onclick*=\"editSite\"]");
    if (editBtn) {
        const match = editBtn.getAttribute("onclick").match(/editSite\((\d+)\)/);
        return match ? match[1] : null;
    }
    return null;
}

function updateStatusBadge(badge, status) {
    const statusClass = status === "running" ? "bg-success" : (status === "starting" ? "bg-warning" : "bg-danger");
    badge.className = "badge status-badge " + statusClass;
    badge.innerHTML = "<i class=\"bi bi-circle-fill me-1\"></i>" + status.charAt(0).toUpperCase() + status.slice(1);
}

function updateStatusIndicator(card, status) {
    const indicator = card.querySelector(".status-indicator");
    if (indicator) {
        indicator.className = "status-indicator status-" + status;
    }
}

function showAlert(type, message) {
    const alertHtml = "<div class=\"alert alert-" + type + " alert-dismissible fade show position-fixed\" style=\"top: 20px; right: 20px; z-index: 9999; min-width: 300px;\">" + message + "<button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button></div>";

    document.body.insertAdjacentHTML("beforeend", alertHtml);

    setTimeout(() => {
        const alert = document.querySelector(".alert:last-of-type");
        if (alert) {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }
    }, 5000);
}

function showPasswordModal() {
    document.getElementById("passwordForm").reset();
    passwordModal.show();
}

async function changePassword(event) {
    event.preventDefault();

    const formData = new FormData(event.target);
    const currentPassword = formData.get("current_password");
    const newPassword = formData.get("new_password");
    const confirmPassword = formData.get("confirm_password");

    // Validate passwords match
    if (newPassword !== confirmPassword) {
        showAlert("danger", "New passwords do not match");
        return;
    }

    // Validate password length
    if (newPassword.length < 6) {
        showAlert("danger", "Password must be at least 6 characters long");
        return;
    }

    const submitBtn = event.target.querySelector("button[type=\"submit\"]");
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Changing...";
    submitBtn.disabled = true;

    try {
        const result = await apiCall("api.php?action=change_password", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                current_password: currentPassword,
                new_password: newPassword
            })
        });

        if (result.success) {
            showAlert("success", "Password changed successfully!");
            passwordModal.hide();
            event.target.reset();
        } else {
            showAlert("danger", result.error || "Failed to change password");
        }
    } catch (error) {
        if (error.message !== "SESSION_EXPIRED") {
            showAlert("danger", "Network error: " + error.message);
        }
    } finally {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// ============================================
// UPDATE SYSTEM FUNCTIONS
// ============================================

async function checkForUpdatesBackground() {
    try {
        const response = await fetch("api.php?action=check_updates");
        const result = await response.json();
        
        if (result.success && result.data.update_available) {
            const updateLink = document.getElementById("updateLink");
            if (updateLink) {
                updateLink.style.display = "block";
            }
        }
    } catch (error) {
        console.error("Failed to check for updates:", error);
    }
}

async function showUpdateModal() {
    console.log("showUpdateModal called");
    
    if (!updateModal) {
        console.error("Update modal not initialized");
        return;
    }
    
    updateModal.show();
    
    const updateContent = document.getElementById("updateContent");
    if (!updateContent) {
        console.error("Update content element not found");
        return;
    }
    
    console.log("Fetching update info from API...");
    
    try {
        const response = await fetch("api.php?action=get_update_info");
        console.log("API response status:", response.status);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        console.log("API result:", result);
        
        if (result.success) {
            displayUpdateInfo(result.info, result.changelog);
        } else {
            console.error("API returned error:", result.error);
            updateContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to load update information: ${result.error || 'Unknown error'}
                </div>
            `;
        }
    } catch (error) {
        console.error("Error in showUpdateModal:", error);
        updateContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Network error: ${error.message}
            </div>
        `;
    }
}

function displayUpdateInfo(info, changelog) {
    console.log("displayUpdateInfo called with:", { info, changelog });
    
    const updateBtn = document.getElementById("performUpdateBtn");
    const updateContent = document.getElementById("updateContent");
    
    if (!updateContent) {
        console.error("Update content element not found in displayUpdateInfo");
        return;
    }
    
    if (!info) {
        console.error("No update info provided");
        updateContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Invalid update information received
            </div>
        `;
        return;
    }
    
    let html = `
        <div class="row mb-3">
            <div class="col-md-6">
                <h6 class="text-muted">Current Version</h6>
                <h4>${info.current_version}</h4>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Latest Version</h6>
                <h4>${info.remote_version || "Unknown"}</h4>
            </div>
        </div>
    `;
    
    if (info.update_available) {
        html += `
            <div class="alert alert-success">
                <i class="bi bi-check-circle me-2"></i>
                <strong>Update Available!</strong> A new version is ready to install.
            </div>
        `;
        if (updateBtn) {
            updateBtn.style.display = "block";
        }
    } else {
        html += `
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i>
                You are running the latest version.
            </div>
        `;
        if (updateBtn) {
            updateBtn.style.display = "none";
        }
    }
    
    if (info.has_local_changes) {
        let changesHtml = '';
        if (info.changes && info.changes.length > 0) {
            changesHtml = '<br><small class="mt-2 d-block">Modified files:<br><code class="d-block mt-1">' + 
                          info.changes.join('<br>') + '</code></small>';
        }
        html += `
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Warning:</strong> Local changes detected. They will be stashed before updating.
                ${changesHtml}
            </div>
        `;
    }
    
    if (changelog && changelog.length > 0) {
        html += `
            <h6 class="mt-4 mb-3">Recent Changes</h6>
            <div class="bg-light p-3 rounded" style="max-height: 200px; overflow-y: auto;">
                <ul class="list-unstyled mb-0">
        `;
        changelog.forEach(line => {
            html += `<li class="mb-1"><code class="text-dark">${line}</code></li>`;
        });
        html += `
                </ul>
            </div>
        `;
    }
    
    html += `
        <div class="mt-3">
            <small class="text-muted">
                <i class="bi bi-clock me-1"></i>
                Last checked: ${new Date(info.last_check * 1000).toLocaleString()}
            </small>
        </div>
    `;
    
    updateContent.innerHTML = html;
}

async function performUpdate() {
    const updateBtn = document.getElementById("performUpdateBtn");
    const updateContent = document.getElementById("updateContent");
    
    if (!updateBtn || !updateContent) {
        console.error("Update UI elements not found");
        return;
    }
    
    const originalText = updateBtn.innerHTML;
    
    if (!confirm("Are you sure you want to update? This will pull the latest changes from Git and may restart services.")) {
        return;
    }
    
    updateBtn.innerHTML = "<span class=\"spinner-border spinner-border-sm me-2\"></span>Updating...";
    updateBtn.disabled = true;
    
    updateContent.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary mb-3" role="status"></div>
            <h5>Installing Update...</h5>
            <p class="text-muted">Please wait, this may take a moment.</p>
        </div>
    `;
    
    try {
        const response = await fetch("api.php?action=perform_update", {
            method: "POST"
        });
        
        const result = await response.json();
        
        if (result.success) {
            updateContent.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>Update Successful!</strong><br>
                    ${result.message}
                </div>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle me-2"></i>
                    The page will reload in 3 seconds...
                </div>
            `;
            
            setTimeout(() => {
                location.reload();
            }, 3000);
        } else {
            updateContent.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Update Failed!</strong><br>
                    ${result.error}
                </div>
            `;
            updateBtn.innerHTML = originalText;
            updateBtn.disabled = false;
        }
    } catch (error) {
        updateContent.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Network Error!</strong><br>
                ${error.message}
            </div>
        `;
        updateBtn.innerHTML = originalText;
        updateBtn.disabled = false;
    }
}

// ============================================
// Two-Factor Authentication Functions
// ============================================

async function show2FAModal() {
    twoFactorModal.show();
    await load2FAStatus();
}

async function load2FAStatus() {
    const content = document.getElementById("twoFactorContent");
    
    try {
        // Check current 2FA status from user session/API
        const response = await fetch("api.php?action=get_user");
        const result = await response.json();
        
        if (result.success && result.user) {
            const user = result.user;
            
            if (user.totp_enabled) {
                // 2FA is enabled
                content.innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-shield-check me-2"></i>
                        <strong>Two-Factor Authentication is Enabled</strong>
                        <p class="mb-0 mt-2">Your account is protected with 2FA.</p>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Disable 2FA</h6>
                            <p class="text-muted">Enter your password to disable two-factor authentication.</p>
                            <form id="disable2FAForm" onsubmit="disable2FA(event)">
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-shield-x me-2"></i>Disable 2FA
                                </button>
                            </form>
                        </div>
                    </div>
                `;
            } else {
                // 2FA is not enabled
                content.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="bi bi-shield-exclamation me-2"></i>
                        <strong>Two-Factor Authentication is Disabled</strong>
                        <p class="mb-0 mt-2">Enable 2FA to add an extra layer of security to your account.</p>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <h6 class="card-title">Enable 2FA</h6>
                            <p class="text-muted">Scan the QR code with your authenticator app and enter the code to enable 2FA.</p>
                            <button type="button" class="btn btn-primary" onclick="setup2FA()">
                                <i class="bi bi-shield-plus me-2"></i>Setup Two-Factor Authentication
                            </button>
                        </div>
                    </div>
                `;
            }
        }
    } catch (error) {
        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Error loading 2FA status: ${error.message}
            </div>
        `;
    }
}

async function setup2FA() {
    const content = document.getElementById("twoFactorContent");
    content.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="mt-2">Generating QR code...</p>
        </div>
    `;
    
    try {
        const response = await fetch("api.php?action=setup_2fa", { method: "POST" });
        const result = await response.json();
        
        if (result.success) {
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Step 1: Add to Authenticator App</h6>
                        <p class="text-muted small">Scan this QR code with your authenticator app:</p>
                        <div class="text-center mb-3 p-3 bg-white border rounded" id="qr-container">
                            <img src="${result.qr_code_url}" alt="QR Code" class="img-fluid" style="max-width: 250px; min-height: 250px;" 
                                 onload="console.log('QR code loaded successfully')"
                                 onerror="console.error('QR code failed to load'); this.style.display='none'; document.getElementById('qr-error').style.display='block';">
                            <div id="qr-error" style="display: none;" class="alert alert-warning mt-2 mb-0">
                                <small><i class="bi bi-exclamation-triangle me-1"></i>QR code could not be loaded. Please use manual entry below.</small>
                            </div>
                        </div>
                        
                        <div class="alert alert-secondary">
                            <p class="mb-2 small"><strong>Manual Entry:</strong></p>
                            <p class="mb-1 small">Account: <strong>WharfTales</strong></p>
                            <p class="mb-2 small">Secret Key:</p>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control font-monospace" id="secretKey" value="${result.secret}" readonly>
                                <button class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText('${result.secret}'); showAlert('success', 'Secret copied to clipboard!')">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </div>
                        </div>
                        
                        <p class="text-muted small">
                            <i class="bi bi-info-circle me-1"></i>
                            Supported apps: Google Authenticator, Microsoft Authenticator, Authy, 1Password
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6>Step 2: Verify Code</h6>
                        <p class="text-muted small">Enter the 6-digit code from your authenticator app:</p>
                        <form id="enable2FAForm" onsubmit="enable2FA(event)">
                            <div class="mb-3">
                                <input type="text" class="form-control form-control-lg text-center font-monospace" 
                                       name="code" maxlength="6" pattern="[0-9]{6}" 
                                       placeholder="000000" required autofocus
                                       style="letter-spacing: 0.5rem; font-size: 2rem;">
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-check-circle me-2"></i>Enable 2FA
                            </button>
                            <button type="button" class="btn btn-secondary w-100 mt-2" onclick="load2FAStatus()">
                                <i class="bi bi-arrow-left me-2"></i>Cancel
                            </button>
                        </form>
                    </div>
                </div>
            `;
        } else {
            throw new Error(result.error || "Failed to setup 2FA");
        }
    } catch (error) {
        content.innerHTML = `
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i>
                Error: ${error.message}
            </div>
            <button class="btn btn-secondary" onclick="load2FAStatus()">Back</button>
        `;
    }
}

async function enable2FA(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const submitBtn = event.target.querySelector("button[type='submit']");
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verifying...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch("api.php?action=enable_2fa", {
            method: "POST",
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const content = document.getElementById("twoFactorContent");
            content.innerHTML = `
                <div class="alert alert-success">
                    <i class="bi bi-check-circle me-2"></i>
                    <strong>2FA Enabled Successfully!</strong>
                </div>
                
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-title"><i class="bi bi-key me-2"></i>Backup Codes</h6>
                        <p class="text-danger small"><strong>Important:</strong> Save these backup codes in a secure location. Each code can be used once if you lose access to your authenticator app.</p>
                        <div class="row">
                            ${result.backup_codes.map(code => `
                                <div class="col-6 col-md-4 mb-2">
                                    <code class="d-block p-2 bg-white border rounded">${code}</code>
                                </div>
                            `).join('')}
                        </div>
                        <button class="btn btn-primary mt-3" onclick="twoFactorModal.hide(); location.reload();">
                            <i class="bi bi-check-circle me-2"></i>Done
                        </button>
                    </div>
                </div>
            `;
        } else {
            throw new Error(result.error || "Failed to enable 2FA");
        }
    } catch (error) {
        showAlert("danger", error.message);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

async function disable2FA(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    const submitBtn = event.target.querySelector("button[type='submit']");
    const originalText = submitBtn.innerHTML;
    
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Disabling...';
    submitBtn.disabled = true;
    
    try {
        const response = await fetch("api.php?action=disable_2fa", {
            method: "POST",
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert("success", "2FA has been disabled successfully");
            twoFactorModal.hide();
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error || "Failed to disable 2FA");
        }
    } catch (error) {
        showAlert("danger", error.message);
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    }
}

// Password visibility toggle
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    const icon = document.getElementById(fieldId + '_icon');
    
    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Generate random secure password
function generateRandomPassword() {
    const length = 16;
    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+-=[]{}|;:,.<>?';
    let password = '';
    
    // Ensure at least one of each type
    const lowercase = 'abcdefghijklmnopqrstuvwxyz';
    const uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numbers = '0123456789';
    const special = '!@#$%^&*()_+-=[]{}|;:,.<>?';
    
    password += lowercase[Math.floor(Math.random() * lowercase.length)];
    password += uppercase[Math.floor(Math.random() * uppercase.length)];
    password += numbers[Math.floor(Math.random() * numbers.length)];
    password += special[Math.floor(Math.random() * special.length)];
    
    // Fill the rest randomly
    for (let i = password.length; i < length; i++) {
        password += charset[Math.floor(Math.random() * charset.length)];
    }
    
    // Shuffle the password
    password = password.split('').sort(() => Math.random() - 0.5).join('');
    
    // Set the password in both fields
    const newPasswordField = document.getElementById('new_password');
    const confirmPasswordField = document.getElementById('confirm_password');
    
    newPasswordField.value = password;
    confirmPasswordField.value = password;
    
    // Show the password temporarily
    newPasswordField.type = 'text';
    confirmPasswordField.type = 'text';
    document.getElementById('new_password_icon').classList.remove('bi-eye');
    document.getElementById('new_password_icon').classList.add('bi-eye-slash');
    document.getElementById('confirm_password_icon').classList.remove('bi-eye');
    document.getElementById('confirm_password_icon').classList.add('bi-eye-slash');
    
    // Check password strength
    checkPasswordStrength(password);
    
    // Show success message
    showAlert('success', 'Secure password generated! Make sure to save it somewhere safe.');
    
    // Copy to clipboard
    navigator.clipboard.writeText(password).then(() => {
        console.log('Password copied to clipboard');
    }).catch(err => {
        console.error('Failed to copy password:', err);
    });
}

// Check password strength
function checkPasswordStrength(password) {
    const strengthDiv = document.getElementById('password_strength');
    const strengthBar = document.getElementById('password_strength_bar');
    const strengthText = document.getElementById('password_strength_text');
    
    if (!password) {
        strengthDiv.style.display = 'none';
        return;
    }
    
    strengthDiv.style.display = 'block';
    
    let strength = 0;
    let feedback = [];
    
    // Length check
    if (password.length >= 8) strength += 20;
    if (password.length >= 12) strength += 10;
    if (password.length >= 16) strength += 10;
    
    // Character variety
    if (/[a-z]/.test(password)) strength += 15;
    if (/[A-Z]/.test(password)) strength += 15;
    if (/[0-9]/.test(password)) strength += 15;
    if (/[^a-zA-Z0-9]/.test(password)) strength += 15;
    
    // Determine strength level
    let level = 'Weak';
    let color = 'danger';
    
    if (strength >= 80) {
        level = 'Very Strong';
        color = 'success';
    } else if (strength >= 60) {
        level = 'Strong';
        color = 'success';
    } else if (strength >= 40) {
        level = 'Medium';
        color = 'warning';
    }
    
    strengthBar.style.width = strength + '%';
    strengthBar.className = 'progress-bar bg-' + color;
    strengthText.textContent = 'Password strength: ' + level;
    strengthText.className = 'text-' + color;
}


// GitHub Deployment Functions
async function checkGithubUpdates() {
    const siteId = document.getElementById("editSiteId").value;
    
    try {
        const result = await apiCall(`api.php?action=check_github_updates&id=${siteId}`);
        
        if (result.success) {
            if (result.has_updates) {
                showAlert("info", `Updates available! Local: ${result.local_commit}, Remote: ${result.remote_commit}`);
            } else {
                showAlert("success", "You're up to date! No new commits available.");
            }
        } else {
            showAlert("danger", result.error || "Failed to check for updates");
        }
    } catch (error) {
        if (error.message !== "SESSION_EXPIRED") {
            showAlert("danger", "Network error: " + error.message);
        }
    }
}

async function pullFromGithub() {
    const siteId = document.getElementById("editSiteId").value;
    const siteName = document.getElementById("editName").value;
    
    if (!confirm(`Pull latest changes from GitHub for "${siteName}"?\n\nThis will update all files in the container.`)) {
        return;
    }
    
    try {
        showAlert("info", "Pulling latest changes from GitHub...");
        
        const result = await apiCall(`api.php?action=pull_from_github&id=${siteId}`, {
            method: "POST"
        });
        
        if (result.success) {
            showAlert("success", result.message || "Successfully pulled latest changes!");
            
            // Update the commit hash display
            if (result.commit_hash) {
                document.getElementById("editGithubCommit").textContent = result.commit_hash.substring(0, 7);
            }
            if (result.pull_time) {
                document.getElementById("editGithubLastPull").textContent = new Date(result.pull_time).toLocaleString();
            }
            
            // Reload site list
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert("danger", result.error || "Failed to pull from GitHub");
        }
    } catch (error) {
        if (error.message !== "SESSION_EXPIRED") {
            showAlert("danger", "Network error: " + error.message);
        }
    }
}
