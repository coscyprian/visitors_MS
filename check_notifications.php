<?php
/**
 * MFUMO WA TAARIFA - ZANA YA KUAKAGUA
 * Inatumika kuhadba na kutatua tatizo la taarifa
 */

require_once 'config/db_config.php';
require_once 'includes/notifications.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure notifications table exists
ensureNotificationsTableExists($conn);

$user_id = $_SESSION['user_id'] ?? 0;
$diagnostics = [];
$errors = [];

// Check 1: Database connection
$diagnostics[] = [
    'name' => 'Database Connection',
    'status' => $conn ? 'OK' : 'ERROR',
    'details' => $conn ? 'Muingiliano na database umefanikiwa' : 'Tatizo: Haiwezi kuingia database'
];

// Check 2: Notifications table exists
$check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
$table_exists = $check_table && $check_table->num_rows > 0;
$diagnostics[] = [
    'name' => 'Notifications Table',
    'status' => $table_exists ? 'OK' : 'MISSING',
    'details' => $table_exists ? 'Jedwali la notifications linakuwepo' : 'Jedwali la notifications halipo'
];

// Check 3: Department column in visitors
$dept_col = $conn->query("SHOW COLUMNS FROM visitors LIKE 'department'");
$dept_exists = $dept_col && $dept_col->num_rows > 0;
$diagnostics[] = [
    'name' => 'Department in Visitors Table',
    'status' => $dept_exists ? 'OK' : 'MISSING',
    'details' => $dept_exists ? 'Sehemu department linakuwepo katika visitors' : 'Sehemu department halipo katika visitors'
];

// Check 4: Department column in users
$user_dept = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
$user_dept_exists = $user_dept && $user_dept->num_rows > 0;
$diagnostics[] = [
    'name' => 'Department in Users Table',
    'status' => $user_dept_exists ? 'OK' : 'MISSING',
    'details' => $user_dept_exists ? 'Sehemu department linakuwepo katika users' : 'Sehemu department halipo katika users'
];

// Check 5: Role column in users
$role_col = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
$role_exists = $role_col && $role_col->num_rows > 0;
$diagnostics[] = [
    'name' => 'Role in Users Table',
    'status' => $role_exists ? 'OK' : 'MISSING',
    'details' => $role_exists ? 'Sehemu role linakuwepo katika users' : 'Sehemu role halipo katika users'
];

// Check 6: Users with receptionist role
if ($user_dept_exists && $role_exists) {
    $receptionist_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role LIKE '%receptionist%' OR role LIKE '%staff%'")->fetch_assoc()['count'];
    $diagnostics[] = [
        'name' => 'Receptionists/Staff',
        'status' => $receptionist_count > 0 ? 'OK' : 'WARNING',
        'details' => "Idadi ya receptionists/staff: " . $receptionist_count . " (Lazima kuwe zaidi ya 0)"
    ];
}

// Check 7: Notifications table records
if ($table_exists) {
    $notif_count = $conn->query("SELECT COUNT(*) as count FROM notifications")->fetch_assoc()['count'];
    $diagnostics[] = [
        'name' => 'Notification Records',
        'status' => 'INFO',
        'details' => "Jumla ya taarifa katika database: " . $notif_count
    ];
}

// Check 8: Current user session
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT id, username, role, department FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    if ($user) {
        $diagnostics[] = [
            'name' => 'Current User',
            'status' => 'OK',
            'details' => "Jina: " . htmlspecialchars($user['username']) . " | Role: " . htmlspecialchars($user['role']) . " | Department: " . ($user['department'] ? htmlspecialchars($user['department']) : 'N/A')
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zana ya Kuakagua - Mfumo wa Taarifa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .diagnostic-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 30px;
            max-width: 900px;
            margin: 0 auto;
        }
        .diagnostic-header {
            text-align: center;
            margin-bottom: 30px;
            color: #667eea;
        }
        .diagnostic-header h1 {
            font-weight: 700;
            font-size: 1.8rem;
        }
        .check-item {
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #ddd;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .check-item.ok {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .check-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .check-item.warning {
            border-left-color: #f59e0b;
            background: #fffbeb;
        }
        .check-item.info {
            border-left-color: #0ea5e9;
            background: #f0f9ff;
        }
        .check-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.8rem;
        }
        .check-status.ok {
            background: #10b981;
            color: white;
        }
        .check-status.error {
            background: #ef4444;
            color: white;
        }
        .check-status.warning {
            background: #f59e0b;
            color: white;
        }
        .check-status.info {
            background: #0ea5e9;
            color: white;
        }
        .check-name {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2d3748;
        }
        .check-details {
            font-size: 0.9rem;
            color: #718096;
            margin-top: 5px;
        }
        .summary-box {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #0ea5e9;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .footer-links {
            margin-top: 20px;
            text-align: center;
        }
        .footer-links a {
            margin: 0 10px;
            text-decoration: none;
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="diagnostic-container">
        <div class="diagnostic-header">
            <h1><i class="fas fa-heartbeat me-2"></i>Zana ya Kuakagua - Taarifa System</h1>
            <p class="text-muted">Ukagua hali ya mfumo wa taarifa</p>
        </div>

        <div class="summary-box">
            <strong><i class="fas fa-info-circle me-2"></i>Muhtasari:</strong>
            <p class="mb-0">
                Hii inaonyesha takwimu za mfumo wako. Kila item lazima kiwe "OK" kwa ajili ya taarifa kuwe kazi vizuri.
            </p>
        </div>

        <div class="checks-container">
            <?php foreach ($diagnostics as $diag): 
                $status_lower = strtolower($diag['status']);
            ?>
            <div class="check-item <?= $status_lower ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="check-name"><?= htmlspecialchars($diag['name']) ?></div>
                        <div class="check-details"><?= htmlspecialchars($diag['details']) ?></div>
                    </div>
                    <span class="check-status <?= $status_lower ?>"><?= $diag['status'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 p-3 bg-light rounded">
            <strong><i class="fas fa-lightbulb me-2"></i>Nasaha:</strong>
            <ul class="mb-0">
                <li>Kila kitu lazima kiwe "OK" kwa taarifa kuwe kazi kamili</li>
                <li>Kama kuna "MISSING", tembea <a href="setup_notifications.php">setup_notifications.php</a></li>
                <li>Kama kuna "WARNING", jaribu kuongeza waandishi wa huduma na department</li>
                <li>Tembea "QUICK_START.txt" kwa mwongozo haraka</li>
            </ul>
        </div>

        <div class="footer-links">
            <a href="setup_notifications.php"><i class="fas fa-wrench me-1"></i>Usanidi</a>
            <a href="gate_security_dashboard.php"><i class="fas fa-home me-1"></i>Dashboard</a>
            <a href="users.php"><i class="fas fa-users me-1"></i>Watumiaji</a>
            <a href="javascript:location.reload()"><i class="fas fa-redo me-1"></i>Kuboreshe</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
