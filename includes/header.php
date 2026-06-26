<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/role_helpers.php';

$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $current_page != 'login.php') {
    header("Location: login.php");
    exit();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$role = normalizeUserRole($_SESSION['role'] ?? 'Receptionist');
$roleLabel = getRoleLabel($role);
$roleKey = normalizedRoleKey($role);
$pageTitles = [
    'dashboard.php' => 'Dashboard',
    'view_visitors.php' => 'View Visitors',
    'users.php' => 'Users',
    'department_dashboard.php' => 'Department Dashboard',
    'departments.php' => 'Departments',
    'reports.php' => 'Reports',
    'gate_security_dashboard.php' => 'Gate Security Dashboard',
    'change_password.php' => 'Change Password',
    'logout.php' => 'Logout',
];
$currentPageTitle = $pageTitles[$current_page] ?? 'Overview';
$loginWelcomeMessage = '';
if (isset($_SESSION['login_welcome'])) {
    $loginWelcomeMessage = trim((string)$_SESSION['login_welcome']);
    unset($_SESSION['login_welcome']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>VMS PRO - Control Panel</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --sidebar-width: 280px;
    --sidebar-collapsed: 90px;
    --primary: #6366f1;
    --primary-light: #818cf8;
    --accent: #a855f7;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --bg: #f8fafc;
    --bg-alt: #f1f5f9;
    --sidebar-bg: #ffffff;
    --text-main: #1e293b;
    --text-light: #64748b;
    --card-bg: #ffffff;
    --glass-border: rgba(0, 0, 0, 0.05);
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
    --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
}

/* DARK MODE - PREMIUM LOOK */
body.dark-mode {
    --bg: #0f172a;
    --bg-alt: #1e293b;
    --sidebar-bg: #1e293b;
    --text-main: #f1f5f9;
    --text-light: #cbd5e1;
    --card-bg: rgba(30, 41, 59, 0.8);
    --glass-border: rgba(255, 255, 255, 0.1);
}

* {
    scrollbar-width: thin;
    scrollbar-color: var(--primary) transparent;
}

::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: transparent;
}

::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 10px;
}

body {
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: var(--bg);
    color: var(--text-main);
    transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 100vh;
    overflow-x: hidden;
}

/* BACKGROUND ANIMATION */
body::before, body::after {
    content: "";
    position: fixed;
    border-radius: 50%;
    z-index: -1;
    filter: blur(120px);
    opacity: 0;
    transition: opacity 0.6s ease;
}

body.dark-mode::before {
    width: 600px;
    height: 600px;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.3), rgba(168, 85, 247, 0.2));
    top: -200px;
    right: -200px;
    opacity: 0.5;
    animation: float 30s ease-in-out infinite;
}

body.dark-mode::after {
    width: 500px;
    height: 500px;
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(34, 197, 94, 0.15));
    bottom: -200px;
    left: -200px;
    opacity: 0.4;
    animation: float 40s ease-in-out infinite reverse;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    50% { transform: translate(100px, 50px) scale(1.05); }
}

/* SIDEBAR STYLING */
.sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    position: fixed;
    background: var(--sidebar-bg);
    backdrop-filter: blur(20px);
    border-right: 1px solid var(--glass-border);
    padding: 25px 18px;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 1000;
    overflow-y: auto;
    box-shadow: var(--shadow-md);
}

body.collapsed .sidebar {
    width: var(--sidebar-width);
}

body.collapsed .sidebar span,
body.collapsed .sidebar hr {
    display: initial;
}

body.collapsed .sidebar a {
    justify-content: flex-start;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 15px 18px 40px;
    font-weight: 900;
    font-size: 1.35rem;
    color: var(--primary);
    letter-spacing: -0.5px;
    margin-bottom: 10px;
}

.sidebar-brand i {
    font-size: 1.8rem;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 14px 18px;
    color: var(--text-light);
    text-decoration: none;
    border-radius: 14px;
    margin-bottom: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.sidebar a::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    width: 4px;
    height: 100%;
    background: var(--primary);
    transform: scaleY(0);
    transform-origin: top;
    transition: transform 0.3s ease;
}

.sidebar a:hover {
    background: rgba(99, 102, 241, 0.1);
    color: var(--primary);
    padding-left: 22px;
}

.sidebar a:hover::before {
    transform: scaleY(1);
}

.sidebar a.active {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.2), rgba(168, 85, 247, 0.1));
    color: var(--primary);
    font-weight: 700;
}

.sidebar a.active::before {
    transform: scaleY(1);
}

.sidebar a i {
    font-size: 1.2rem;
    transition: transform 0.3s ease;
}

.sidebar a:hover i {
    transform: scale(1.1);
}

.sidebar hr {
    border-color: var(--glass-border);
    margin: 20px 0;
}

.sidebar a.text-danger:hover {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger);
}

/* MAIN CONTENT */
.main-content {
    margin-left: var(--sidebar-width);
    padding: 35px 40px;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    min-height: 100vh;
}

body.collapsed .main-content {
    margin-left: var(--sidebar-collapsed);
}

/* TOPBAR */
.topbar {
    background: var(--card-bg);
    backdrop-filter: blur(15px);
    border: 1px solid var(--glass-border);
    padding: 18px 28px;
    border-radius: 18px;
    margin-bottom: 35px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
}

.topbar:hover {
    box-shadow: var(--shadow-lg);
}

/* CARDS */
.card {
    background: var(--card-bg);
    backdrop-filter: blur(15px);
    border: 1px solid var(--glass-border);
    border-radius: 20px;
    box-shadow: var(--shadow-md);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-5px);
    border-color: rgba(99, 102, 241, 0.2);
}

.card-header {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.05));
    border-bottom: 1px solid var(--glass-border);
    border-radius: 20px 20px 0 0;
    padding: 20px 25px;
    font-weight: 700;
    color: var(--primary);
}

.card-body {
    padding: 25px;
}

/* FORMS */
.form-label {
    font-weight: 600;
    color: var(--text-main);
    margin-bottom: 10px;
    font-size: 0.95rem;
}

.form-control,
.form-select {
    background: var(--bg-alt);
    border: 1.5px solid var(--glass-border);
    border-radius: 12px;
    padding: 12px 16px;
    color: var(--text-main);
    font-weight: 500;
    transition: all 0.3s ease;
}

body.dark-mode .form-control,
body.dark-mode .form-select {
    background: rgba(15, 23, 42, 0.5);
}

.form-control:focus,
.form-select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    background: var(--card-bg);
}

/* BUTTONS */
.btn {
    font-weight: 700;
    border-radius: 12px;
    padding: 12px 24px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    box-shadow: var(--shadow-md);
}

.btn-primary:hover {
    transform: translateY(-3px);
    box-shadow: var(--shadow-lg);
    color: white;
}

.btn-outline-primary {
    border: 2px solid var(--primary);
    color: var(--primary);
}

.btn-outline-primary:hover {
    background: var(--primary);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, var(--success), #059669);
    color: white;
}

.btn-danger {
    background: linear-gradient(135deg, var(--danger), #dc2626);
    color: white;
}

.btn-warning {
    background: linear-gradient(135deg, var(--warning), #d97706);
    color: white;
}

.btn-info {
    background: linear-gradient(135deg, #06b6d4, #0ea5e9);
    color: white;
}

/* HAMBURGER */
.hamburger {
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
    font-size: 1.1rem;
    box-shadow: var(--shadow-md);
}

.hamburger:hover {
    transform: scale(1.05);
    box-shadow: var(--shadow-lg);
}

/* USER PROFILE */
.user-profile {
    display: flex;
    align-items: center;
    gap: 16px;
}

.avatar-box {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.2rem;
    box-shadow: var(--shadow-md);
    transition: all 0.3s ease;
}

.avatar-box:hover {
    transform: scale(1.05);
}

/* TABLES */
.table {
    color: var(--text-main);
}

.table thead {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(168, 85, 247, 0.05));
    font-weight: 700;
}

.table thead th {
    border-color: var(--glass-border);
    color: var(--primary);
    padding: 16px;
}

.table tbody tr {
    border-color: var(--glass-border);
    transition: all 0.2s ease;
}

.table tbody tr:hover {
    background: rgba(99, 102, 241, 0.05);
}

.table tbody td {
    padding: 14px 16px;
    vertical-align: middle;
}

/* BADGES */
.badge {
    font-weight: 700;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 0.85rem;
}

.badge.bg-success {
    background: linear-gradient(135deg, var(--success), #059669) !important;
}

.badge.bg-danger {
    background: linear-gradient(135deg, var(--danger), #dc2626) !important;
}

.badge.bg-warning {
    background: linear-gradient(135deg, var(--warning), #d97706) !important;
}

/* ALERTS */
.alert {
    border-radius: 15px;
    border: none;
    padding: 16px 20px;
    font-weight: 500;
    backdrop-filter: blur(10px);
}

.alert-success {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
    color: var(--success);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.05));
    color: var(--danger);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.alert-warning {
    background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.05));
    color: var(--warning);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.alert-info {
    background: linear-gradient(135deg, rgba(6, 182, 212, 0.1), rgba(14, 165, 233, 0.05));
    color: #0ea5e9;
    border: 1px solid rgba(6, 182, 212, 0.2);
}

/* RESPONSIVE */
@media (max-width: 768px) {
    .sidebar {
        width: var(--sidebar-width);
    }
    
    .sidebar span,
    .sidebar hr {
        display: initial;
    }
    
    .main-content {
        margin-left: var(--sidebar-width);
        padding: 20px;
    }
    
    .topbar {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}

</style>
</head>

<body>

<div class="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fas fa-shield-halved"></i></div>
        <span>VMS PRO</span>
    </div>

    <?php if (isAdminRole($role)): ?>
        <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>" title="Dashboard" aria-label="Dashboard">
            <i class="fas fa-grid-2"></i><span>Dashboard</span>
        </a>

        <a href="view_visitors.php" class="<?= $current_page == 'view_visitors.php' ? 'active' : '' ?>" title="View Visitors" aria-label="View Visitors">
            <i class="fas fa-user-group"></i><span>View Visitors</span>
        </a>

        <a href="users.php" class="<?= $current_page == 'users.php' ? 'active' : '' ?>" title="Users" aria-label="Users">
            <i class="fas fa-users-cog"></i><span>Users</span>
        </a>

        <a href="department_dashboard.php" class="<?= $current_page == 'department_dashboard.php' ? 'active' : '' ?>" title="Department Dashboard" aria-label="Department Dashboard">
            <i class="fas fa-building-user"></i><span>Department Dashboard</span>
        </a>

        <a href="departments.php" class="<?= $current_page == 'departments.php' ? 'active' : '' ?>" title="Departments" aria-label="Departments">
            <i class="fas fa-building"></i><span>Departments</span>
        </a>

        <a href="reports.php" class="<?= $current_page == 'reports.php' ? 'active' : '' ?>" title="Reports" aria-label="Reports">
            <i class="fas fa-chart-pie"></i><span>Reports</span>
        </a>
    <?php else: ?>
        <?php if ($roleKey === 'security'): ?>
        <a href="gate_security_dashboard.php" class="<?= $current_page == 'gate_security_dashboard.php' ? 'active' : '' ?>" title="Gate Security Dashboard" aria-label="Gate Security Dashboard">
            <i class="fas fa-shield-alt"></i><span>Gate Security Dashboard</span>
        </a>
        <?php else: ?>
        <a href="department_dashboard.php" class="<?= $current_page == 'department_dashboard.php' ? 'active' : '' ?>" title="Department Dashboard" aria-label="Department Dashboard">
            <i class="fas fa-building-user"></i><span>Department Dashboard</span>
        </a>
        <?php endif; ?>

    <?php endif; ?>

    <hr style="border-color: var(--glass-border); margin: 20px 0;">

    <a href="#" onclick="toggleDarkMode()" title="Dark Mode" aria-label="Dark Mode">
        <i class="fas fa-moon"></i><span id="mode-text">Dark Mode</span>
    </a>

    <a href="change_password.php" class="<?= $current_page == 'change_password.php' ? 'active' : '' ?>" title="Change Password" aria-label="Change Password">
        <i class="fas fa-key"></i><span>Change Password</span>
    </a>

    <a href="logout.php" class="text-danger mt-auto" title="Logout" aria-label="Logout">
        <i class="fas fa-power-off"></i><span>Logout</span>
    </a>
</div>

<div class="main-content">
    <div class="topbar d-flex justify-content-between align-items-center animate__animated animate__fadeInDown">
        <div class="d-flex align-items-center gap-3">
            <div class="hamburger" onclick="toggleSidebar()">
                <i class="fas fa-bars"></i>
            </div>
            <h5 class="fw-bold mb-0"><?= htmlspecialchars($currentPageTitle) ?></h5>
        </div>
        
        <div class="user-profile d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <p class="small text-muted mb-0">Welcome <?= htmlspecialchars($roleLabel) ?>:</p>
                <p class="fw-bold mb-0" style="font-size: 0.9rem;"><?= htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['username'] ?? 'Administrator') ?></p>
            </div>
            <div class="avatar-box" style="width: 40px; height: 40px; background: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-user"></i>
            </div>
        </div>
    </div>

    <?php if ($loginWelcomeMessage !== ''): ?>
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-handshake me-2"></i><?= htmlspecialchars($loginWelcomeMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

<script>
// Logic zako za zamani zimebaki vile vile lakini zimeboreshwa kidogo
function toggleSidebar() {
    document.body.classList.toggle('collapsed');
    localStorage.setItem('sidebarStatus', document.body.classList.contains('collapsed') ? 'collapsed' : 'expanded');
}

function toggleDarkMode() {
    const isDark = document.body.classList.toggle('dark-mode');
    localStorage.setItem('theme', isDark ? 'dark' : 'light');
    updateModeText(isDark);
}

function updateModeText(isDark) {
    const text = document.getElementById('mode-text');
    const icon = document.querySelector('a[onclick="toggleDarkMode()"] i');
    if(isDark) {
        text.innerText = 'Light Mode';
        icon.className = 'fas fa-sun';
    } else {
        text.innerText = 'Dark Mode';
        icon.className = 'fas fa-moon';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        updateModeText(true);
    }
    // Keep labels visible for all sidebar items.
    localStorage.setItem('sidebarStatus', 'expanded');
    document.body.classList.remove('collapsed');
});
</script>