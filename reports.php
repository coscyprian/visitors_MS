<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_config.php';
require_once 'includes/departments.php';

ensureDepartmentsTableExists($conn);

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'includes/header.php';

// Validate CSRF token for GET requests
$csrf_token = $_GET['csrf_token'] ?? '';
if (!empty($csrf_token) && (!is_string($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token))) {
    die('Invalid request. Please try again.');
}

$start_date = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
$end_date = trim((string)($_GET['end_date'] ?? date('Y-m-d')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date)) {
    $start_date = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    $end_date = date('Y-m-d');
}

if ($start_date > $end_date) {
    $tmp = $start_date;
    $start_date = $end_date;
    $end_date = $tmp;
}

$status_filter = trim((string)($_GET['status'] ?? 'all'));
$visitor_type_filter = trim((string)($_GET['visitor_type'] ?? 'all'));
$motor_filter = trim((string)($_GET['has_motor'] ?? 'all'));
$department_filter = trim((string)($_GET['department'] ?? 'all'));
$is_generated = isset($_GET['generated']) && $_GET['generated'] === '1';

$allowed_status = ['all', 'Inside', 'Checked In', 'Left'];
if (!in_array($status_filter, $allowed_status, true)) {
    $status_filter = 'all';
}

$allowed_visitor_type = ['all', 'Kiraia', 'Kijeshi'];
if (!in_array($visitor_type_filter, $allowed_visitor_type, true)) {
    $visitor_type_filter = 'all';
}

$allowed_motor = ['all', 'Yes', 'No'];
if (!in_array($motor_filter, $allowed_motor, true)) {
    $motor_filter = 'all';
}

$departments = [];
$deptRs = $conn->query("SELECT name AS dept FROM departments WHERE status = 'Active' ORDER BY name ASC");
if ($deptRs) {
    while ($d = $deptRs->fetch_assoc()) {
        $departments[] = $d['dept'];
    }
}

if ($department_filter !== 'all' && !in_array($department_filter, $departments, true)) {
    $department_filter = 'all';
}

$where = ["DATE(v.check_in_time) BETWEEN ? AND ?"];
$types = 'ss';
$params = [$start_date, $end_date];

if ($status_filter !== 'all') {
    $where[] = "v.status = ?";
    $types .= 's';
    $params[] = $status_filter;
}

if ($visitor_type_filter !== 'all') {
    $where[] = "v.visitor_type = ?";
    $types .= 's';
    $params[] = $visitor_type_filter;
}

if ($motor_filter !== 'all') {
    $where[] = "v.has_motor = ?";
    $types .= 's';
    $params[] = $motor_filter;
}

if ($department_filter !== 'all') {
    $where[] = "COALESCE(NULLIF(TRIM(d.name), ''), NULLIF(TRIM(v.department), ''), '') = ?";
    $types .= 's';
    $params[] = $department_filter;
}

$sql = "SELECT
            v.id,
            v.check_in_time,
            v.check_out_time,
            v.full_name,
            v.phone_number,
            v.visitor_type,
            v.id_type,
            v.id_number,
            v.purpose,
            v.status,
            v.has_motor,
            v.motor_type,
            v.plate_number,
            v.model_name,
            COALESCE(NULLIF(TRIM(d.name), ''), NULLIF(TRIM(v.department), ''), '') AS visitor_department
        FROM visitors v
        LEFT JOIN departments d ON v.department_id = d.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY v.check_in_time DESC";

$stmt = $conn->prepare($sql);
$rows = [];

if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($result && $r = $result->fetch_assoc()) {
        $rows[] = $r;
    }
    $stmt->close();
}

$summary = [
    'total' => count($rows),
    'inside' => 0,
    'left' => 0,
    'with_motor' => 0,
];

foreach ($rows as $r) {
    if (in_array($r['status'], ['Inside', 'Checked In'], true)) {
        $summary['inside']++;
    }
    if (($r['status'] ?? '') === 'Left') {
        $summary['left']++;
    }
    if (($r['has_motor'] ?? 'No') === 'Yes') {
        $summary['with_motor']++;
    }
}

$exportParams = [
    'start_date' => $start_date,
    'end_date' => $end_date,
    'status' => $status_filter,
    'visitor_type' => $visitor_type_filter,
    'has_motor' => $motor_filter,
    'department' => $department_filter,
    'csrf_token' => $_SESSION['csrf_token']
];
$exportUrl = 'export.php?' . http_build_query($exportParams);
?>

<style>
    /* CSS ZA MUONEKANO WA SCREEN (DASHBOARD) */
    .report-header-print { display: none; }
    .card { border-radius: 15px; border: none; }
    
    /* ==========================================
       WORLD CLASS PRINT ARCHITECTURE
       ========================================== */
    @media print {
        /* 1. Futa kila kitu cha mfumo (Universal Wipe) */
        body * { visibility: hidden; }
        
        /* 2. Onyesha eneo la ripoti pekee na lianzie juu kabisa */
        #reportPaper, #reportPaper * { visibility: visible; }
        #reportPaper {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            padding: 0;
            margin: 0;
        }

        /* 3. Design ya Header (Professional Look) */
        .report-header-print {
            display: block !important;
            border-bottom: 3px solid #1a237e;
            margin-bottom: 25px;
            padding-bottom: 15px;
        }
        
        .company-name {
            color: #1a237e;
            font-size: 28px;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0;
        }

        /* 4. Table Styling (Executive Look) */
        .table {
            width: 100% !important;
            border-collapse: collapse !important;
        }

        .table thead th {
            background-color: #1a237e !important;
            color: white !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            padding: 12px 8px !important;
            text-transform: uppercase;
            font-size: 11px;
            border: 1px solid #1a237e !important;
        }

        .table tbody td {
            padding: 10px 8px !important;
            border: 1px solid #e0e0e0 !important;
            color: #333 !important;
            font-size: 11px;
        }

        /* Zebra striping kwa ajili ya ripoti ndefu */
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Ficha vitu visivyotakiwa kabisa */
        .no-print, .btn, .dataTables_filter, .dataTables_info { display: none !important; }
        
        @page { size: auto; margin: 1.5cm; }
    }
</style>

<div class="container-fluid py-4 no-print">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="h3 fw-bold text-gray-800">Report Center</h2>
            <p class="text-muted small mb-0">Generate, print or download visitor records for security audits.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= htmlspecialchars($exportUrl, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-success shadow-sm px-4">
                <i class="fas fa-file-csv me-2"></i> Download CSV
            </a>
            <button onclick="window.print()" class="btn btn-dark shadow-sm px-4">
                <i class="fas fa-file-pdf me-2"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" action="reports.php#reportPaper" class="row g-3">
                <input type="hidden" name="generated" value="1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">START DATE</label>
                    <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">END DATE</label>
                    <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">STATUS</label>
                    <select name="status" class="form-select">
                        <?php foreach (['all', 'Inside', 'Checked In', 'Left'] as $s): ?>
                            <option value="<?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?>" <?= ($status_filter === $s) ? 'selected' : '' ?>><?= htmlspecialchars($s, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">VISITOR TYPE</label>
                    <select name="visitor_type" class="form-select">
                        <?php foreach (['all', 'Kiraia', 'Kijeshi'] as $t): ?>
                            <option value="<?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?>" <?= ($visitor_type_filter === $t) ? 'selected' : '' ?>><?= htmlspecialchars($t, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold">HAS MOTOR</label>
                    <select name="has_motor" class="form-select">
                        <?php foreach (['all', 'Yes', 'No'] as $m): ?>
                            <option value="<?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?>" <?= ($motor_filter === $m) ? 'selected' : '' ?>><?= htmlspecialchars($m, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">DEPARTMENT</label>
                    <select name="department" class="form-select">
                        <option value="all">all</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') ?>" <?= ($department_filter === $dept) ? 'selected' : '' ?>><?= htmlspecialchars($dept, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">GENERATE REPORT</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($is_generated): ?>
    <div class="alert alert-success shadow-sm">
        <i class="fas fa-check-circle me-2"></i>
        Report imezalishwa kwa tarehe ulizochagua: <strong><?= htmlspecialchars($start_date, ENT_QUOTES, 'UTF-8') ?></strong> hadi <strong><?= htmlspecialchars($end_date, ENT_QUOTES, 'UTF-8') ?></strong>.
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">TOTAL RECORDS</div><div class="fs-4 fw-bold"><?= $summary['total'] ?></div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">INSIDE NOW</div><div class="fs-4 fw-bold"><?= $summary['inside'] ?></div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">LEFT</div><div class="fs-4 fw-bold"><?= $summary['left'] ?></div></div></div></div>
        <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">WITH MOTOR</div><div class="fs-4 fw-bold"><?= $summary['with_motor'] ?></div></div></div></div>
    </div>
</div>

<!-- ==========================================
     INVOICE/REPORT PAPER (Hapa ndo kila kitu)
     ========================================== -->
<div id="reportPaper" class="container-fluid">
    
    <!-- Professional Header -->
    <div class="report-header-print text-center">
        <div class="row align-items-center">
            <div class="col-12">
                <h1 class="company-name">VMS PRO SYSTEM</h1>
                <p class="mb-1 fw-bold">VISITOR ATTENDANCE LOG REPORT</p>
                <div class="d-flex justify-content-center gap-3 small text-muted">
                    <span><strong>PERIOD:</strong> <?= date('d M, Y', strtotime($start_date)) ?> - <?= date('d M, Y', strtotime($end_date)) ?></span>
                    <span>|</span>
                    <span><strong>GENERATED BY:</strong> Programmer</span>
                    <span>|</span>
                    <span><strong>STATUS:</strong> <?= htmlspecialchars($status_filter, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Data Table -->
    <div class="card border-0">
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Date/Time In</th>
                        <th>Visitor</th>
                        <th>ID Details</th>
                        <th>Contact</th>
                        <th>Type</th>
                        <th>Host</th>
                        <th>Department</th>
                        <th>Purpose</th>
                        <th>Vehicle</th>
                        <th>Status</th>
                        <th>Date/Time Out</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($rows) > 0) {
                        foreach ($rows as $row) {
                            $dept = $row['visitor_department'] !== '' ? $row['visitor_department'] : '-';
                            $vehicle = ($row['has_motor'] === 'Yes')
                                ? trim(($row['motor_type'] ?: 'Gari') . ' ' . ($row['plate_number'] ?: '') . ' ' . ($row['model_name'] ?: ''))
                                : 'No';
                            echo "<tr>
                                    <td>" . date('d/m/Y H:i', strtotime($row['check_in_time'])) . "</td>
                                    <td class='fw-bold text-dark'>" . htmlspecialchars($row['full_name']) . "</td>
                                    <td>" . htmlspecialchars(($row['id_type'] ?: '-') . ' / ' . ($row['id_number'] ?: '-')) . "</td>
                                    <td>" . htmlspecialchars($row['phone_number'] ?: '-') . "</td>
                                    <td>" . htmlspecialchars($row['visitor_type'] ?: '-') . "</td>
                                    <td>-</td>
                                    <td>" . htmlspecialchars($dept) . "</td>
                                    <td>" . htmlspecialchars($row['purpose'] ?: '-') . "</td>
                                    <td>" . htmlspecialchars($vehicle) . "</td>
                                    <td>" . htmlspecialchars($row['status'] ?: '-') . "</td>
                                    <td>" . (!empty($row['check_out_time']) ? date('d/m/Y H:i', strtotime($row['check_out_time'])) : '-') . "</td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='11' class='text-center py-4 text-muted'>No records found for the selected filters.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Footer for Print -->
    <div class="mt-4 pt-3 border-top d-none d-print-block">
        <div class="row">
            <div class="col-6 small text-muted">
                Printed on: <?= date('d/m/Y H:i:s') ?>
            </div>
            <div class="col-6 text-end small text-muted">
                VMS PRO | Page 1 of 1
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($is_generated): ?>
<script>
window.addEventListener('load', function () {
    const reportSection = document.getElementById('reportPaper');
    if (reportSection) {
        reportSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
</script>
<?php endif; ?>