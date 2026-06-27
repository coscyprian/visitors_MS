
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

                    <a href="gate_security_dashboard.php" class="active" title="Gate Security Dashboard" aria-label="Gate Security Dashboard">
            <i class="fas fa-shield-alt"></i><span>Gate Security Dashboard</span>
        </a>
        
    
    <hr style="border-color: var(--glass-border); margin: 20px 0;">

    <a href="#" onclick="toggleDarkMode()" title="Dark Mode" aria-label="Dark Mode">
        <i class="fas fa-moon"></i><span id="mode-text">Dark Mode</span>
    </a>

    <a href="change_password.php" class="" title="Change Password" aria-label="Change Password">
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
            <h5 class="fw-bold mb-0">Gate Security Dashboard</h5>
        </div>
        
        <div class="user-profile d-flex align-items-center gap-3">
            <div class="text-end d-none d-sm-block">
                <p class="small text-muted mb-0">Welcome Security:</p>
                <p class="fw-bold mb-0" style="font-size: 0.9rem;">ignas kayombo</p>
            </div>
            <div class="avatar-box" style="width: 40px; height: 40px; background: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white;">
                <i class="fas fa-user"></i>
            </div>
        </div>
    </div>

    
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
<style>
/* PREMIUM DASHBOARD STYLING */

.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #06b6d4 100%);
    color: #ffffff;
    padding: 28px 24px;
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.28);
}

.dashboard-header h3,
.dashboard-header p {
    color: #ffffff;
}

.registration-card {
    border: none;
    overflow: hidden;
    transition: all 0.3s ease;
}

.registration-card:hover {
    box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
    transform: translateY(-5px);
}

.registration-card h5 {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    margin: -16px -16px 20px -16px;
    font-weight: 700;
    font-size: 1.3rem;
}

/* Form Label Styling */
.registration-card .form-label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
    font-size: 0.95rem;
}

.registration-card .form-control,
.registration-card .form-select {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 15px;
    font-size: 1rem;
    transition: all 0.3s ease;
}

.registration-card .form-control:focus,
.registration-card .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Submit Button */
.btn-register {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
    color: white;
    font-weight: 700;
    font-size: 1.1rem;
    padding: 14px 30px;
    border-radius: 12px;
    transition: all 0.3s ease;
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.btn-register:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.4);
    color: white;
}

/* View Selection Buttons */
.view-buttons .btn {
    border-radius: 12px;
    font-weight: 600;
    padding: 12px 20px;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.view-buttons .btn-outline-primary {
    border-color: #667eea;
    color: #667eea;
}

.view-buttons .btn-outline-primary:hover,
.view-buttons .btn-outline-primary.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: transparent;
    color: white;
    box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
}

.view-buttons .btn-outline-success {
    border-color: #10b981;
    color: #10b981;
}

.view-buttons .btn-outline-success:hover,
.view-buttons .btn-outline-success.active {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    border-color: transparent;
    color: white;
    box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
}

.view-buttons .btn-outline-danger {
    border-color: #ef4444;
    color: #ef4444;
}

.view-buttons .btn-outline-danger:hover,
.view-buttons .btn-outline-danger.active {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    border-color: transparent;
    color: white;
    box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
}

.view-buttons .btn-outline-warning {
    border-color: #f59e0b;
    color: #f59e0b;
}

.view-buttons .btn-outline-warning:hover,
.view-buttons .btn-outline-warning.active {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    border-color: transparent;
    color: white;
    box-shadow: 0 10px 25px rgba(245, 158, 11, 0.3);
}

.view-buttons .btn-info {
    background: linear-gradient(135deg, #06b6d4 0%, #0ea5e9 100%);
    border: none;
    font-weight: 600;
    box-shadow: 0 10px 25px rgba(6, 182, 212, 0.3);
}

.view-buttons .btn-info:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 35px rgba(6, 182, 212, 0.4);
}

/* Stats Cards */
.stat-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2);
    transition: all 0.3s ease;
}

.stat-box:nth-child(2) {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    box-shadow: 0 15px 35px rgba(16, 185, 129, 0.2);
}

.stat-box:nth-child(3) {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    box-shadow: 0 15px 35px rgba(245, 158, 11, 0.2);
}

.stat-box:nth-child(4) {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    box-shadow: 0 15px 35px rgba(239, 68, 68, 0.2);
}

.stat-box:hover {
    transform: translateY(-8px);
    box-shadow: 0 25px 45px rgba(0, 0, 0, 0.15);
}

.stat-box .text-uppercase {
    font-size: 0.85rem;
    opacity: 0.9;
    font-weight: 600;
    letter-spacing: 1px;
}

.stat-box .fs-3 {
    font-size: 2.5rem !important;
    font-weight: 800;
    margin-top: 10px;
}

/* Table Styling */
.table-responsive {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
}

.table {
    margin-bottom: 0;
}

.table thead {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.table thead th {
    border: none;
    font-weight: 700;
    padding: 15px 10px;
    font-size: 0.95rem;
}

.table tbody tr {
    border-bottom: 1px solid #e2e8f0;
    transition: all 0.3s ease;
}

.table tbody tr:hover {
    background-color: #f8fafc;
}

.table tbody td {
    padding: 12px 10px;
    vertical-align: middle;
}

/* Badge Styling */
.badge {
    border-radius: 8px;
    padding: 6px 12px;
    font-weight: 600;
}

.badge.bg-info {
    background: linear-gradient(135deg, #06b6d4 0%, #0ea5e9 100%) !important;
}

/* Alert Styling */
.alert {
    border-radius: 12px;
    border: none;
    padding: 15px 20px;
    font-weight: 500;
}

.alert-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #065f46;
}

.alert-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #7f1d1d;
}

/* Radio Button Styling */
.form-check-input {
    width: 20px;
    height: 20px;
    border: 2px solid #cbd5e1;
    cursor: pointer;
    transition: all 0.3s ease;
}

.form-check-input:checked {
    background-color: #667eea;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.form-check-label {
    font-weight: 500;
    color: #2d3748;
    cursor: pointer;
}

/* Vehicle Fields Section */
#vehicleFields {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    border-radius: 12px;
    padding: 20px;
    border-left: 4px solid #f59e0b;
}

/* Modal Styling */
.modal-content {
    border-radius: 16px;
    border: none;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
}

.modal-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

.modal-title {
    font-weight: 700;
    font-size: 1.3rem;
}

.btn-close {
    filter: invert(1);
}

/* Search Form */
.search-form {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
}

.search-form .form-control {
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px 15px;
}

.search-form .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

/* Inline Display Section */
.inline-display {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    margin-top: 20px;
}

.inline-display h6 {
    color: #667eea;
    font-weight: 700;
    font-size: 1.2rem;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 3px solid #667eea;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .dashboard-header h3 {
        font-size: 1.8rem;
    }

    .dashboard-header p {
        font-size: 0.95rem;
    }
}
</style>

<div class="container-xl py-5">
    <div class="row mb-5">
        <div class="col-12">
            <div class="dashboard-header">
                <h3><i class="fas fa-user-tie me-3"></i>Receptionist Dashboard</h3>
                <p class="mb-0"><i class="fas fa-bell me-2"></i>Usajili wa wageni na huduma za mapokezi.</p>
            </div>
        </div>
    </div>

    <!-- NOTIFICATIONS SECTION -->
        <!-- END NOTIFICATIONS SECTION -->

   <?php
   if(isset($_POST['register_visitor'])){

    $visitor_type = $_POST['visitor_type'];
    $full_name = trim($_POST['full_name']);
    $phone_number = trim($_POST['phone_number']);
    $id_type = $_POST['id_type'];
    $id_number = trim($_POST['id_number']);
    $department = $_POST['department'];

    $has_motor = $_POST['has_motor'] ?? "No";
    $plate_number = $_POST['plate_number'] ?? "";
    $motor_type = $_POST['motor_type'] ?? "";
    $model_name = $_POST['model_name'] ?? "";

    $army_no = $_POST['army_no'] ?? "";
    $army_rank = $_POST['army_rank'] ?? "";
    $army_unit = $_POST['army_unit'] ?? "";

    //==================================================
    // CHECK IF VISITOR IS ALREADY INSIDE
    //==================================================

    $check = $conn->prepare("
        SELECT id
        FROM visitors
        WHERE id_type=?
        AND id_number=?
        AND checkout_time IS NULL
        LIMIT 1
    ");

    $check->bind_param("ss",$id_type,$id_number);
    $check->execute();

    $result = $check->get_result();

    if($result->num_rows > 0){

        echo "<div class='alert alert-danger'>
        Visitor tayari yupo ndani.
        </div>";

    }else{

        $insert = $conn->prepare("
        INSERT INTO visitors(

            visitor_type,
            full_name,
            phone_number,
            id_type,
            id_number,
            department,

            has_motor,
            plate_number,
            motor_type,
            model_name,

            army_no,
            army_rank,
            army_unit,

            checkin_time

        )

        VALUES(

        ?,?,?,?,?,?,
        ?,?,?,?,
        ?,?,?,NOW()

        )

        ");

        $insert->bind_param(

            "sssssssssssss",

            $visitor_type,
            $full_name,
            $phone_number,
            $id_type,
            $id_number,
            $department,

            $has_motor,
            $plate_number,
            $motor_type,
            $model_name,

            $army_no,
            $army_rank,
            $army_unit

        );

        if($insert->execute()){

            echo "<div class='alert alert-success'>
            Visitor registered successfully.
            </div>";

        }

    }

}
   ?>

            <div class="registration-card shadow-lg p-4 mt-4">
                <h5><i class="fas fa-eye me-2"></i>Chagua nini kuonyeshwa</h5>
                <div class="d-grid gap-2 mb-4 view-buttons">
                    <a href="?view=today" class="btn btn-outline-primary "><i class="fas fa-calendar-day me-2"></i>Wageni Waliosajiliwa Leo</a>
                    <a href="?view=inside" class="btn btn-outline-success "><i class="fas fa-sign-in-alt me-2"></i>Walioko Ndani</a>
                    <a href="?view=left" class="btn btn-outline-danger "><i class="fas fa-sign-out-alt me-2"></i>Waliotoka Leo</a>
                    <a href="?show_vehicles=1&vehicle_view=today" class="btn btn-outline-warning "><i class="fas fa-car me-2"></i>Magari ya Leo</a>
                    <a href="?show_vehicles=1&vehicle_view=inside" class="btn btn-outline-warning "><i class="fas fa-warehouse me-2"></i>Magari Yalioko Ndani</a>
                    <a href="?show_vehicles=1&vehicle_view=left" class="btn btn-outline-warning "><i class="fas fa-road me-2"></i>Magari Yaliyotoka Nje</a>
                </div>

                <form method="get" class="d-flex gap-2 search-form">
                    <input type="text" name="search_name" class="form-control flex-grow-1" placeholder="Tafuta mgeni kwa jina..." value="">
                    <button type="submit" class="btn btn-info"><i class="fas fa-search me-2"></i>Tafuta</button>
                </form>

                <div class="inline-display">
                                            <p class="text-muted">Chagua mtazamo kuonyesha wageni.</p>
                                    </div>

                    
        </div>
    </div>
</div>

<script>
const csrfToken = '941906922831871543f97340f974f079fcb7a46624c5e52768946d039cf69065';

function toggleVehicleFields(show) {
    document.getElementById('vehicleFields').style.display = show ? 'block' : 'none';
}

function toggleMilitaryFields(type) {
    document.getElementById('militaryFields').style.display = (type === 'Kijeshi') ? 'block' : 'none';
}

function handleMotorTypeChange(type) {
    const plateInput = document.getElementById('plateNumberInput');
    const civilianDetails = document.getElementById('civilianVehicleDetails');

    if (type === 'Kijeshi') {
        civilianDetails.style.display = 'none';
        if (plateInput.value.trim() === '') {
            plateInput.value = 'JW';
        } else if (!plateInput.value.trim().toUpperCase().startsWith('JW')) {
            plateInput.value = 'JW' + plateInput.value.trim();
        }
        plateInput.placeholder = 'Anza na JW';
    } else if (type === 'Kiraia') {
        civilianDetails.style.display = 'block';
        if (plateInput.value.trim().toUpperCase().startsWith('JW')) {
            plateInput.value = plateInput.value.trim().substring(2);
        }
        plateInput.placeholder = 'Andika namba ya gari / aina ya gari';
    } else {
        civilianDetails.style.display = 'none';
        plateInput.placeholder = 'Ingiza namba ya gari';
    }
}

let lookupTimer = null;

function setLookupMessage(message, tone) {
    const statusEl = document.getElementById('visitorLookupStatus');
    if (!statusEl) return;

    statusEl.classList.remove('text-success', 'text-danger', 'text-muted');
    statusEl.classList.add(tone || 'text-muted');
    statusEl.textContent = message || '';
}

function setSelectValueIfExists(selectId, value) {
    const selectEl = document.getElementById(selectId);
    if (!selectEl) return;

    const found = Array.from(selectEl.options).some(option => option.value === value);
    if (found) {
        selectEl.value = value;
    }
}

function lookupVisitorById() {
    const idTypeEl = document.getElementById('idTypeSelect');
    const idNumberEl = document.getElementById('idNumberInput');
    if (!idTypeEl || !idNumberEl) return;

    const idType = idTypeEl.value.trim();
    const idNumber = idNumberEl.value.trim();

    if (idNumber.length < 3) {
        setLookupMessage('', 'text-muted');
        return;
    }

    setLookupMessage('Inatafuta taarifa za mgeni aliyewahi kusajiliwa...', 'text-muted');

    const lookupUrl = 'lookup_visitor_by_id.php?id_type=' + encodeURIComponent(idType) + '&id_number=' + encodeURIComponent(idNumber);

    fetch(lookupUrl, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (!data.found || !data.visitor) {
            setLookupMessage('Hakuna taarifa za mgeni huyu kwenye mfumo bado.', 'text-muted');
            return;
        }

        const visitor = data.visitor;

        const fullNameEl = document.getElementById('fullNameInput');
        const phoneEl = document.getElementById('phoneNumberInput');
        const visitorTypeEl = document.getElementById('visitorTypeSelect');
        const armyNoEl = document.getElementById('armyNoInput');
        const armyRankEl = document.getElementById('armyRankInput');
        const armyUnitEl = document.getElementById('armyUnitInput');
        const plateEl = document.getElementById('plateNumberInput');
        const motorTypeEl = document.getElementById('motorTypeSelect');
        const modelEl = document.getElementById('modelNameInput');

        if (fullNameEl && visitor.full_name) fullNameEl.value = visitor.full_name;
        if (phoneEl && visitor.phone_number) phoneEl.value = visitor.phone_number;
        if (visitorTypeEl && visitor.visitor_type) {
            visitorTypeEl.value = visitor.visitor_type;
            toggleMilitaryFields(visitor.visitor_type);
        }
        if (idTypeEl && visitor.id_type) {
            idTypeEl.value = visitor.id_type;
        }
        if (armyNoEl) armyNoEl.value = visitor.army_no || '';
        if (armyRankEl) armyRankEl.value = visitor.army_rank || '';
        if (armyUnitEl) armyUnitEl.value = visitor.army_unit || '';

        if (visitor.department) {
            setSelectValueIfExists('departmentSelect', visitor.department);
        }

        const hasMotorYes = document.getElementById('hasMotorYes');
        const hasMotorNo = document.getElementById('hasMotorNo');
        if ((visitor.has_motor || '').toUpperCase() === 'YES') {
            if (hasMotorYes) hasMotorYes.checked = true;
            toggleVehicleFields(true);
            if (motorTypeEl && visitor.motor_type) {
                motorTypeEl.value = visitor.motor_type;
                handleMotorTypeChange(visitor.motor_type);
            }
            if (plateEl) plateEl.value = visitor.plate_number || '';
            if (modelEl) modelEl.value = visitor.model_name || '';
        } else {
            if (hasMotorNo) hasMotorNo.checked = true;
            toggleVehicleFields(false);
            if (plateEl) plateEl.value = '';
            if (modelEl) modelEl.value = '';
            if (motorTypeEl) motorTypeEl.value = '';
        }

        setLookupMessage('Mgeni amepatikana. Taarifa zimejazwa moja kwa moja.', 'text-success');
    })
    .catch(error => {
        console.error('Lookup error:', error);
        setLookupMessage('Tatizo wakati wa kutafuta taarifa za mgeni.', 'text-danger');
    });
}

const idTypeSelect = document.getElementById('idTypeSelect');
const idNumberInput = document.getElementById('idNumberInput');

if (idTypeSelect && idNumberInput) {
    idTypeSelect.addEventListener('change', function() {
        lookupVisitorById();
    });

    idNumberInput.addEventListener('input', function() {
        clearTimeout(lookupTimer);
        lookupTimer = setTimeout(lookupVisitorById, 400);
    });

    idNumberInput.addEventListener('blur', lookupVisitorById);

    if (idNumberInput.value.trim().length >= 3) {
        lookupVisitorById();
    }
}

const fullNameInput = document.getElementById('fullNameInput');
const phoneNumberInput = document.getElementById('phoneNumberInput');

if (fullNameInput) {
    fullNameInput.addEventListener('input', function() {
        // Keep letters, space, apostrophe, dot and hyphen only.
        this.value = this.value.replace(/[^A-Za-z\s'\.-]/g, '');
    });
}

if (phoneNumberInput) {
    phoneNumberInput.addEventListener('input', function() {
        this.value = this.value.replace(/\D/g, '').slice(0, 10);
    });
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

/**
 * Delete notification and remove from UI
 */
function deleteNotification(notificationId) {
    fetch('notifications_api.php?action=delete', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-CSRF-Token': csrfToken,
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notifElement = document.getElementById('notif-' + notificationId);
            if (notifElement) {
                notifElement.remove();
                
                // If no more notifications, refresh page to hide notification container
                if (document.querySelectorAll('.notification-item').length === 0) {
                    location.reload();
                }
            }
        } else {
            alert('Tatizo wakati wa kufuta taarifa');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Tatizo wakati wa kumuuona seva');
    });
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsAsRead() {
    fetch('notifications_api.php?action=mark_all_read', {
        method: 'POST',
        headers: {
            'X-CSRF-Token': csrfToken,
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Tatizo wakati wa kuziweka arufa kama zilizosomwa');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Tatizo wakati wa kumuuona seva');
    });
}

</script>

<!-- Vehicle / Tariff Modal -->
<div class="modal fade" id="vehicleModal" tabindex="-1" aria-labelledby="vehicleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="vehicleModalLabel">Onyesha Magari</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
                    <form method="post" class="mb-0">
                <div class="mb-3">
                    <label class="form-label">Passcode</label>
                    <input type="password" name="vehicle_pass" class="form-control" required>
                </div>
                <button type="submit" name="vehicle_pass_submit" class="btn btn-primary">Wangia</button>
            </form>
            <p class="small text-muted mt-3">Tafadhali ingiza passcode ili kuona namba za gari.</p>
              </div>
    </div>
  </div>
</div>
