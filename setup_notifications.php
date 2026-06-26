<?php
/**
 * Database Setup Script for Notifications System
 * This script ensures all necessary tables and columns exist
 */

require_once 'config/db_config.php';
require_once 'includes/notifications.php';

// Set to true if you want to see detailed output
$verbose = true;

$setup_steps = [];

// Step 1: Ensure notifications table exists
if (ensureNotificationsTableExists($conn)) {
    $setup_steps[] = [
        'step' => 'Create/Verify Notifications Table',
        'status' => 'SUCCESS',
        'message' => 'Notifications table is ready'
    ];
} else {
    $setup_steps[] = [
        'step' => 'Create/Verify Notifications Table',
        'status' => 'ERROR',
        'message' => 'Failed to create notifications table'
    ];
}

// Step 2: Ensure department column exists in visitors table
$check_dept = $conn->query("SHOW COLUMNS FROM visitors LIKE 'department'");
if ($check_dept && $check_dept->num_rows === 0) {
    // Column doesn't exist, add it
    if ($conn->query("ALTER TABLE visitors ADD COLUMN department VARCHAR(255)")) {
        $setup_steps[] = [
            'step' => 'Add Department Column to Visitors',
            'status' => 'SUCCESS',
            'message' => 'Department column added to visitors table'
        ];
    } else {
        $setup_steps[] = [
            'step' => 'Add Department Column to Visitors',
            'status' => 'ERROR',
            'message' => 'Failed to add department column: ' . $conn->error
        ];
    }
} else {
    $setup_steps[] = [
        'step' => 'Add Department Column to Visitors',
        'status' => 'SUCCESS',
        'message' => 'Department column already exists'
    ];
}

// Step 3: Verify users table has department column
$check_user_dept = $conn->query("SHOW COLUMNS FROM users LIKE 'department'");
if ($check_user_dept && $check_user_dept->num_rows === 0) {
    // Column doesn't exist, add it
    if ($conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(100) DEFAULT NULL")) {
        $setup_steps[] = [
            'step' => 'Add Department Column to Users',
            'status' => 'SUCCESS',
            'message' => 'Department column added to users table'
        ];
    } else {
        $setup_steps[] = [
            'step' => 'Add Department Column to Users',
            'status' => 'ERROR',
            'message' => 'Failed to add department column to users: ' . $conn->error
        ];
    }
} else {
    $setup_steps[] = [
        'step' => 'Add Department Column to Users',
        'status' => 'SUCCESS',
        'message' => 'Department column already exists in users table'
    ];
}

// Step 4: Verify users table has role column
$check_role = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
if ($check_role && $check_role->num_rows === 0) {
    // Column doesn't exist, add it
    if ($conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'Staff'")) {
        $setup_steps[] = [
            'step' => 'Add Role Column to Users',
            'status' => 'SUCCESS',
            'message' => 'Role column added to users table'
        ];
    } else {
        $setup_steps[] = [
            'step' => 'Add Role Column to Users',
            'status' => 'ERROR',
            'message' => 'Failed to add role column to users: ' . $conn->error
        ];
    }
} else {
    $setup_steps[] = [
        'step' => 'Add Role Column to Users',
        'status' => 'SUCCESS',
        'message' => 'Role column already exists in users table'
    ];
}

?>
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mfumo wa Taarifa - Usanidi wa Database</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .setup-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .setup-header {
            text-align: center;
            margin-bottom: 30px;
            color: #667eea;
        }
        .setup-header h1 {
            font-weight: 700;
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .step-item {
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #ddd;
            border-radius: 5px;
            background: #f8f9fa;
        }
        .step-item.success {
            border-left-color: #10b981;
            background: #f0fdf4;
        }
        .step-item.error {
            border-left-color: #ef4444;
            background: #fef2f2;
        }
        .step-status {
            font-weight: 700;
            font-size: 0.85rem;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        .step-status.success {
            background: #10b981;
            color: white;
        }
        .step-status.error {
            background: #ef4444;
            color: white;
        }
        .step-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: #2d3748;
        }
        .step-message {
            font-size: 0.9rem;
            color: #718096;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-header">
            <h1><i class="fas fa-cogs me-2"></i>Usanidi wa Sistem ya Taarifa</h1>
            <p class="text-muted">Ukagua na kuandaa database kwa mfumo wa taarifa wa wageni</p>
        </div>

        <div class="steps-container">
            <?php foreach ($setup_steps as $step): ?>
            <div class="step-item <?= strtolower($step['status']) ?>">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="step-title"><?= htmlspecialchars($step['step']) ?></div>
                        <div class="step-message"><?= htmlspecialchars($step['message']) ?></div>
                    </div>
                    <span class="step-status <?= strtolower($step['status']) ?>"><?= $step['status'] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 p-3 bg-info rounded text-white">
            <strong><i class="fas fa-info-circle me-2"></i>Habari:</strong>
            <p class="mb-0">Mfumo wa taarifa umesanidiwa kwa mafanikio. Nenda <a href="gate_security_dashboard.php" class="text-white fw-bold">sahifasini ya dashboard</a> kuanza kumkubaliana na taarifa za wageni.</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
