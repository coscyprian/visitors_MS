<?php
require_once 'config/db_config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/notifications.php';
require_once 'includes/departments.php';

date_default_timezone_set('Africa/Nairobi');
ensureNotificationsTableExists($conn);
ensureDepartmentsTableExists($conn);

$message = '';
$error = '';
$current_user_id = (int)($_SESSION['user_id'] ?? 0);
$sessionRole = strtolower(trim((string)($_SESSION['role'] ?? '')));
$isAdmin = in_array($sessionRole, ['admin', 'administrator'], true);

function usersTableHasColumn($conn, $columnName) {
    $safeColumn = $conn->real_escape_string($columnName);
    $rs = $conn->query("SHOW COLUMNS FROM users LIKE '{$safeColumn}'");
    return $rs && $rs->num_rows > 0;
}

$activeDepartmentRows = getDepartments($conn, true);
$activeDepartmentNames = [];
$activeDepartmentIdMap = [];
foreach ($activeDepartmentRows as $row) {
    $activeDepartmentNames[] = $row['name'];
    $activeDepartmentIdMap[strtolower(trim((string)$row['name']))] = (int)$row['id'];
}

$activeDepartmentMap = [];
foreach ($activeDepartmentNames as $deptName) {
    $activeDepartmentMap[strtolower(trim($deptName))] = $deptName;
}

if ($current_user_id <= 0) {
    header('Location: login.php');
    exit();
}

if (!$isAdmin && $sessionRole !== 'receptionist') {
    include 'includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Access denied. Department dashboard is only available to receptionists and administrators.</div></div>';
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_my_department'])) {
    $selectedDepartment = trim($_POST['my_department'] ?? '');
    $selectedDepartmentId = getDepartmentIdByName($conn, $selectedDepartment);
    if ($selectedDepartment === '') {
        $error = 'Chagua idara kabla ya kuhifadhi.';
    } elseif (!in_array($selectedDepartment, $activeDepartmentNames, true)) {
        $error = 'Idara uliyochagua haipo au haijawa active.';
    } elseif ($selectedDepartmentId === null) {
        $error = 'Idara uliyochagua haipo kwenye mfumo.';
    } else {
        if (!usersTableHasColumn($conn, 'department')) {
            $conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(150) NULL");
        }

        if (usersTableHasColumn($conn, 'department')) {
            $setDeptStmt = $conn->prepare("UPDATE users SET department = ?, department_id = ? WHERE id = ?");
            if ($setDeptStmt) {
                $setDeptStmt->bind_param('sii', $selectedDepartment, $selectedDepartmentId, $current_user_id);
                if ($setDeptStmt->execute()) {
                    $setDeptStmt->close();
                    header('Location: department_dashboard.php?dept_set=1');
                    exit();
                }
                $error = 'Imeshindikana kuhifadhi idara yako: ' . $setDeptStmt->error;
                $setDeptStmt->close();
            } else {
                $error = 'Imeshindikana kuandaa uhifadhi wa idara yako.';
            }
        } else {
            $error = 'Imeshindikana kuongeza sehemu ya idara kwenye akaunti za watumiaji.';
        }
    }
}

$displayColumn = 'id';
foreach (['name', 'full_name', 'username', 'email'] as $candidate) {
    if (usersTableHasColumn($conn, $candidate)) {
        $displayColumn = $candidate;
        break;
    }
}
$hasUserDepartment = usersTableHasColumn($conn, 'department');
$hasUserDepartmentId = usersTableHasColumn($conn, 'department_id');

$userSql = "SELECT {$displayColumn} AS display_name";
if ($hasUserDepartment) {
    $userSql .= ", department";
}
if ($hasUserDepartmentId) {
    $userSql .= ", department_id";
}
$userSql .= " FROM users WHERE id = ? LIMIT 1";

$userStmt = $conn->prepare($userSql);
$currentUser = null;
if ($userStmt) {
    $userStmt->bind_param('i', $current_user_id);
    $userStmt->execute();
    $rs = $userStmt->get_result();
    $currentUser = $rs ? $rs->fetch_assoc() : null;
    $userStmt->close();
}

$displayName = trim((string)($currentUser['display_name'] ?? ''));
if ($displayName === '') {
    $displayName = 'Mtumiaji';
}

$userDepartment = trim($currentUser['department'] ?? '');
if (!$hasUserDepartment || $userDepartment === '') {
    $departmentId = isset($currentUser['department_id']) ? (int)$currentUser['department_id'] : 0;
    if ($departmentId > 0) {
        $deptRow = getDepartmentById($conn, $departmentId);
        $userDepartment = trim((string)($deptRow['name'] ?? ''));
    }
    if ($userDepartment === '') {
        $error = 'Akaunti yako haina idara. Tafadhali weka idara kwa mtumiaji wako.';
    }
} else {
    $normalizedUserDepartment = strtolower(trim($userDepartment));
    if (isset($activeDepartmentMap[$normalizedUserDepartment])) {
        // Use canonical department name from departments table.
        $userDepartment = $activeDepartmentMap[$normalizedUserDepartment];
    }
}

$selectedDepartment = $userDepartment;
if ($isAdmin) {
    $requestedDepartment = trim($_GET['department'] ?? '');
    if ($requestedDepartment !== '' && isset($activeDepartmentMap[strtolower($requestedDepartment)])) {
        $selectedDepartment = $activeDepartmentMap[strtolower($requestedDepartment)];
    } elseif ($selectedDepartment !== '' && isset($activeDepartmentMap[strtolower($selectedDepartment)])) {
        $selectedDepartment = $activeDepartmentMap[strtolower($selectedDepartment)];
    } elseif (count($activeDepartmentNames) > 0) {
        $selectedDepartment = $activeDepartmentNames[0];
    }

    if ($selectedDepartment !== '') {
        $error = '';
    }
}

$scope = (($_GET['scope'] ?? 'today') === 'all') ? 'all' : 'today';

$today = date('Y-m-d');

$deptStats = [
    'total_today' => 0,
    'inside_today' => 0,
    'left_today' => 0,
];

$departmentVisitors = [];
$departmentNotifications = [];

if ($error === '' && $selectedDepartment !== '') {
    $selectedDepartmentId = $activeDepartmentIdMap[strtolower(trim($selectedDepartment))] ?? getDepartmentIdByName($conn, $selectedDepartment);
    $canUseVisitorDepartmentId = $selectedDepartmentId !== null && tableHasColumn($conn, 'visitors', 'department_id');
    $canUseNotificationDepartmentId = $selectedDepartmentId !== null && tableHasColumn($conn, 'notifications', 'department_id');

    $statsDateSql = $scope === 'today' ? " AND DATE(check_in_time) = ?" : '';
    $statsWhereSql = $canUseVisitorDepartmentId ? "department_id = ?" : "TRIM(LOWER(department)) = TRIM(LOWER(?))";
    $statStmt = $conn->prepare("SELECT
        COUNT(*) AS total_today,
        SUM(status IN ('Inside', 'Checked In')) AS inside_today,
        SUM(status = 'Left' AND DATE(check_out_time) = ?) AS left_today
        FROM visitors
        WHERE {$statsWhereSql}" . $statsDateSql);
    if ($statStmt) {
        if ($scope === 'today') {
            if ($canUseVisitorDepartmentId) {
                $statStmt->bind_param('sis', $today, $selectedDepartmentId, $today);
            } else {
                $statStmt->bind_param('sss', $today, $selectedDepartment, $today);
            }
        } else {
            if ($canUseVisitorDepartmentId) {
                $statStmt->bind_param('si', $today, $selectedDepartmentId);
            } else {
                $statStmt->bind_param('ss', $today, $selectedDepartment);
            }
        }
        $statStmt->execute();
        $statRs = $statStmt->get_result();
        $deptStats = $statRs ? ($statRs->fetch_assoc() ?: $deptStats) : $deptStats;
        $statStmt->close();
    }

    $visitorDateSql = $scope === 'today' ? " AND DATE(check_in_time) = ?" : '';
    $visitorWhereSql = $canUseVisitorDepartmentId ? "department_id = ?" : "TRIM(LOWER(department)) = TRIM(LOWER(?))";
    $visStmt = $conn->prepare("SELECT id, full_name, phone_number, id_type, id_number, check_in_time, check_out_time, status
        FROM visitors
        WHERE {$visitorWhereSql}" . $visitorDateSql . "
        ORDER BY check_in_time DESC
        LIMIT 100");
    if ($visStmt) {
        if ($scope === 'today') {
            if ($canUseVisitorDepartmentId) {
                $visStmt->bind_param('is', $selectedDepartmentId, $today);
            } else {
                $visStmt->bind_param('ss', $selectedDepartment, $today);
            }
        } else {
            if ($canUseVisitorDepartmentId) {
                $visStmt->bind_param('i', $selectedDepartmentId);
            } else {
                $visStmt->bind_param('s', $selectedDepartment);
            }
        }
        $visStmt->execute();
        $visRs = $visStmt->get_result();
        while ($visRs && ($row = $visRs->fetch_assoc())) {
            $departmentVisitors[] = $row;
        }
        $visStmt->close();
    }

    $notifWhereSql = $canUseNotificationDepartmentId ? "department_id = ?" : "TRIM(LOWER(department)) = TRIM(LOWER(?))";
    $notifSql = "SELECT id, visitor_name, message, status, created_at FROM notifications WHERE {$notifWhereSql}";
    if (!$isAdmin) {
        $notifSql .= " AND user_id = ?";
    }
    $notifSql .= " ORDER BY created_at DESC LIMIT 50";
    $notifStmt = $conn->prepare($notifSql);
    if ($notifStmt) {
        if ($isAdmin) {
            if ($canUseNotificationDepartmentId) {
                $notifStmt->bind_param('i', $selectedDepartmentId);
            } else {
                $notifStmt->bind_param('s', $selectedDepartment);
            }
        } else {
            if ($canUseNotificationDepartmentId) {
                $notifStmt->bind_param('ii', $selectedDepartmentId, $current_user_id);
            } else {
                $notifStmt->bind_param('si', $selectedDepartment, $current_user_id);
            }
        }
        $notifStmt->execute();
        $notifRs = $notifStmt->get_result();
        while ($notifRs && ($row = $notifRs->fetch_assoc())) {
            $departmentNotifications[] = $row;
        }
        $notifStmt->close();
    }
}

if (isset($_GET['marked']) && $_GET['marked'] === '1') {
    $message = 'Taarifa zote zimewekwa kama zimesomwa.';
}
if (isset($_GET['dept_set']) && $_GET['dept_set'] === '1') {
    $message = 'Idara yako imehifadhiwa kwa mafanikio.';
}

include 'includes/header.php';
?>

<style>
.department-header {
    background: linear-gradient(135deg, #0f766e 0%, #0ea5a4 55%, #22c55e 100%);
    color: #fff;
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 14px 30px rgba(15, 118, 110, 0.25);
}

.dashboard-card {
    border: none;
    border-radius: 16px;
    box-shadow: 0 10px 24px rgba(0, 0, 0, 0.08);
}

.dashboard-card h5 {
    background: linear-gradient(135deg, #0f766e 0%, #0ea5a4 100%);
    color: #fff;
    padding: 16px 20px;
    margin: -16px -16px 18px -16px;
    border-radius: 12px 12px 0 0;
}

.stat-box {
    border-radius: 12px;
    color: #fff;
    padding: 16px;
    text-align: center;
    font-weight: 700;
}

.stat-total { background: linear-gradient(135deg, #0f766e 0%, #0ea5a4 100%); }
.stat-inside { background: linear-gradient(135deg, #16a34a 0%, #22c55e 100%); }
.stat-left { background: linear-gradient(135deg, #b45309 0%, #f59e0b 100%); }

.table thead {
    background: #0f766e;
    color: #fff;
}
</style>

<div class="container-xl py-5">
    <div class="row mb-4">
        <div class="col-12">
            <div class="department-header">
                <h3 class="mb-1"><i class="fas fa-building me-2"></i>Dashboard ya Idara</h3>
                <p class="mb-0">Karibu <?= htmlspecialchars($displayName) ?> | Idara: <strong><?= htmlspecialchars($selectedDepartment !== '' ? $selectedDepartment : '-') ?></strong></p>
            </div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card p-3">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Chagua Idara</label>
                        <select name="department" class="form-select" required>
                            <?php foreach ($activeDepartmentNames as $deptName): ?>
                                <option value="<?= htmlspecialchars($deptName) ?>" <?= $selectedDepartment === $deptName ? 'selected' : '' ?>><?= htmlspecialchars($deptName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Muda wa Taarifa</label>
                        <select name="scope" class="form-select">
                            <option value="today" <?= $scope === 'today' ? 'selected' : '' ?>>Leo Tu</option>
                            <option value="all" <?= $scope === 'all' ? 'selected' : '' ?>>Historia Yote</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-success">Onesha</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-12 d-flex gap-2 flex-wrap">
            <a href="gate_security_dashboard.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-2"></i>Rudi Mapokezi</a>
            <button type="button" class="btn btn-outline-primary" onclick="markAllNotificationsAsRead()"><i class="fas fa-check-double me-2"></i>Weka Taarifa Zote Zimesomwa</button>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($userDepartment === ''): ?>
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="dashboard-card p-4">
                <h5><i class="fas fa-sitemap me-2"></i>Weka Idara Yako</h5>
                <p class="text-muted">Chagua idara yako ili uanze kuona wageni na taarifa za idara hiyo.</p>
                <form method="post" class="row g-3">
                    <div class="col-md-9">
                        <label class="form-label">Idara</label>
                        <select name="my_department" class="form-select" required>
                            <option value="">Chagua idara</option>
                            <?php foreach ($activeDepartmentNames as $deptName): ?>
                                <option value="<?= htmlspecialchars($deptName) ?>"><?= htmlspecialchars($deptName) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-grid">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" name="set_my_department" value="1" class="btn btn-success">Hifadhi</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error === ''): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-box stat-total">Wageni Leo<br><span class="fs-3"><?= intval($deptStats['total_today']) ?></span></div>
        </div>
        <div class="col-md-4">
            <div class="stat-box stat-inside">Walioko Ndani<br><span class="fs-3"><?= intval($deptStats['inside_today']) ?></span></div>
        </div>
        <div class="col-md-4">
            <div class="stat-box stat-left">Waliotoka Leo<br><span class="fs-3"><?= intval($deptStats['left_today']) ?></span></div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="dashboard-card p-4">
                <h5><i class="fas fa-users me-2"></i>Wageni wa Idara Yako Leo</h5>
                <?php if (count($departmentVisitors) === 0): ?>
                    <p class="text-muted mb-0">
                        <?= $scope === 'today'
                            ? 'Hakuna wageni waliowasili leo kwenye idara hii.'
                            : 'Hakuna wageni waliopatikana kwenye idara hii.' ?>
                    </p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Jina</th>
                                    <th>Simu</th>
                                    <th>Kitambulisho</th>
                                    <th>Muda wa Kuwasili</th>
                                    <th>Hali</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($departmentVisitors as $v): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($v['full_name']) ?></td>
                                        <td><?= htmlspecialchars($v['phone_number']) ?></td>
                                        <td><?= htmlspecialchars($v['id_type'] . ': ' . $v['id_number']) ?></td>
                                        <td><?= date('H:i, d-m', strtotime($v['check_in_time'])) ?></td>
                                        <td>
                                            <?php if (in_array($v['status'], ['Inside', 'Checked In'], true)): ?>
                                                <span class="badge bg-success">Ndani</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Ametoka</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="dashboard-card p-4">
                <h5><i class="fas fa-bell me-2"></i>Ujumbe wa Taarifa</h5>
                <?php if (count($departmentNotifications) === 0): ?>
                    <p class="text-muted mb-0">Bado hakuna taarifa kwa idara yako.</p>
                <?php else: ?>
                    <div style="max-height: 420px; overflow-y: auto;">
                        <?php foreach ($departmentNotifications as $n): ?>
                            <div class="border rounded p-2 mb-2" id="notif-<?= intval($n['id']) ?>">
                                <div class="fw-semibold"><?= htmlspecialchars($n['visitor_name'] ?: 'Mgeni') ?></div>
                                <div class="small"><?= htmlspecialchars($n['message'] ?: 'Mgeni amewasili katika idara yako.') ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <small class="text-muted"><?= date('H:i, d M', strtotime($n['created_at'])) ?></small>
                                    <div class="d-flex gap-2 align-items-center">
                                        <span class="badge <?= $n['status'] === 'unread' ? 'bg-warning text-dark' : 'bg-info' ?>">
                                            <?= $n['status'] === 'unread' ? 'Haijasomwa' : 'Imesomwa' ?>
                                        </span>
                                        <button type="button" class="btn btn-sm btn-close" onclick="deleteNotification(<?= intval($n['id']) ?>)"></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>';

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
            if (notifElement) notifElement.remove();
        } else {
            alert('Tatizo wakati wa kufuta taarifa');
        }
    })
    .catch(() => {
        alert('Tatizo wakati wa kumfikia seva');
    });
}

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
            window.location.href = 'department_dashboard.php?marked=1';
        } else {
            alert('Tatizo wakati wa kuweka taarifa zimesomwa');
        }
    })
    .catch(() => {
        alert('Tatizo wakati wa kumfikia seva');
    });
}
</script>
