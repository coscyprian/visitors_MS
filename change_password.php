<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_config.php';
include 'includes/header.php';

$msg = "";
$uid = $_SESSION['user_id'];

// Logic ya kusasisha (Update)
if (isset($_POST['update_profile'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_pwd = $_POST['new_password'] ?? '';

    if (!empty($new_pwd)) {
        $hashed_pwd = password_hash($new_pwd, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
        $stmt->bind_param("ssi", $new_username, $hashed_pwd, $uid);
    } else {
        $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->bind_param("si", $new_username, $uid);
    }
    
    if ($stmt->execute()) {
        $_SESSION['username'] = $new_username;
        $msg = "<div class='alert alert-success border-0 shadow-sm small'><i class='fas fa-check-circle me-2'></i>Changes saved successfully!</div>";
    } else {
        $msg = "<div class='alert alert-danger border-0 shadow-sm small'>Error: Username already exists.</div>";
    }
}
?>

<style>
    /* Ultra Compact Design */
    .settings-container { max-width: 420px; margin: 0 auto; }
    .settings-card { border-radius: 12px; background: var(--card-bg); border: 1px solid rgba(0,0,0,0.08); transition: 0.3s; }
    
    .form-label { font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 5px; }
    body.dark-mode .form-label { color: #cbd5e1 !important; }
    
    .input-group-text { background: rgba(0,0,0,0.02); border-right: none; color: #6366f1; font-size: 0.9rem; }
    body.dark-mode .input-group-text { background: #334155; border-color: #475569; color: #818cf8; }
    
    .form-control { border-radius: 8px; font-size: 0.9rem; padding: 10px; border: 1px solid rgba(0,0,0,0.1); background: var(--card-bg); color: var(--text-color); }
    .form-control:focus { box-shadow: none; border-color: #6366f1; }
    
    #strength-meter { height: 4px; border-radius: 10px; background: #e2e8f0; margin-top: 6px; overflow: hidden; }
    #strength-bar { height: 100%; width: 0%; transition: 0.4s; }
    
    .btn-update { border-radius: 8px; padding: 10px; font-weight: 700; font-size: 0.85rem; background: #6366f1; border: none; letter-spacing: 0.5px; }
</style>

<div class="container py-5">
    <div class="settings-container">
        
        <div class="text-center mb-4">
            <h5 class="fw-bold mb-1">Account Security</h5>
            <p class="text-muted small">Update your account credentials below</p>
        </div>

        <div class="card settings-card shadow-sm">
            <div class="card-body p-4">
                <?php echo $msg; ?>
                
                <form method="POST">
                    <!-- Username -->
                    <div class="mb-3">
                        <label class="form-label text-uppercase">Username</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text"><i class="fas fa-at"></i></span>
                            <input type="text" name="new_username" class="form-control" placeholder="Enter new username" required>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label class="form-label text-uppercase">New Password</label>
                        <div class="input-group shadow-sm">
                            <span class="input-group-text"><i class="fas fa-key"></i></span>
                            <input type="password" name="new_password" id="pwdInput" class="form-control" 
                                   placeholder="Min. 6 characters" oninput="checkStrength(this.value)">
                            <button class="btn btn-outline-light border text-muted" type="button" onclick="togglePwd()">
                                <i class="fas fa-eye" id="eyeIcon"></i>
                            </button>
                        </div>
                        <div id="strength-meter">
                            <div id="strength-bar"></div>
                        </div>
                        <small id="strength-text" class="text-muted mt-1 d-block" style="font-size: 9px;">Password Health</small>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary btn-update w-100">
                        SAVE SETTINGS
                    </button>
                </form>
            </div>
        </div>

        <div class="text-center mt-4">
            <a href="dashboard.php" class="text-decoration-none text-muted small">
                <i class="fas fa-arrow-left me-1"></i> Return to Dashboard
            </a>
        </div>

    </div>
</div>

<script>
function togglePwd() {
    const input = document.getElementById('pwdInput');
    const icon = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

function checkStrength(pwd) {
    const bar = document.getElementById('strength-bar');
    const text = document.getElementById('strength-text');
    let strength = 0;
    if (pwd.length > 5) strength++;
    if (/[A-Z]/.test(pwd)) strength++;
    if (/[0-9]/.test(pwd)) strength++;
    if (/[^A-Za-z0-9]/.test(pwd)) strength++;

    switch(strength) {
        case 0: bar.style.width = '0%'; text.innerText = 'Password Health'; break;
        case 1: bar.style.width = '25%'; bar.style.backgroundColor = '#ef4444'; text.innerText = 'Too weak'; break;
        case 2: bar.style.width = '50%'; bar.style.backgroundColor = '#fbbf24'; text.innerText = 'Fair'; break;
        case 3: bar.style.width = '75%'; bar.style.backgroundColor = '#6366f1'; text.innerText = 'Good'; break;
        case 4: bar.style.width = '100%'; bar.style.backgroundColor = '#22c55e'; text.innerText = 'Excellent'; break;
    }
}
</script>