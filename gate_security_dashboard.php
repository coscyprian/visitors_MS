<?php
require_once 'config/db_config.php';
// Session and receptionist config (for vehicle/tariff viewing passcode)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'includes/role_helpers.php';
require_once 'config/reception_config.php';
require_once 'includes/notifications.php';
require_once 'includes/departments.php';

date_default_timezone_set('Africa/Nairobi');

$currentRole = normalizeUserRole($_SESSION['role'] ?? 'Receptionist');
if (!in_array(normalizedRoleKey($currentRole), ['admin', 'security'], true)) {
    include 'includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Access denied. Reception registration dashboard is only available to security and administrators.</div></div>';
    exit();
}

// Ensure notifications table exists
ensureNotificationsTableExists($conn);
ensureDepartmentsTableExists($conn);


$message = '';
$error = '';
$forceViewInside = false;
$current_user_id = (int)($_SESSION['user_id'] ?? 0);

// Handle request to reveal vehicle/tariff details (protected by passcode)
if (isset($_POST['vehicle_pass_submit'])) {
    $entered = trim($_POST['vehicle_pass'] ?? '');
    if ($entered !== '' && isset($vehicle_view_pass) && $entered === $vehicle_view_pass) {
        $_SESSION['show_vehicle'] = true;
        $message = 'Umefanikiwa kuona taarifa za magari.';
    } else {
        $error = 'Passcode si sahihi au haikujazwa.';
    }
}

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $registeredDept = trim($_GET['dept'] ?? '');
    if ($registeredDept !== '') {
        $message = 'Mgeni ameandikishwa kwa mafanikio kwenda idara ya ' . htmlspecialchars($registeredDept) . '. Taarifa imetumwa kwa mapokezi ya idara husika.';
    } else {
        $message = 'Mgeni ameandikishwa kwa mafanikio.';
    }
}

if (isset($_GET['checkedout']) && $_GET['checkedout'] === '1') {
    $message = 'Mgeni ametolewa nje kwa mafanikio.';
}

if (isset($_GET['vehicle_deleted']) && $_GET['vehicle_deleted'] === '1') {
    $message = 'Taarifa za gari zimefutwa kwa mafanikio.';
}

$vehicleView = 'today';
if (isset($_GET['vehicle_view']) && in_array($_GET['vehicle_view'], ['today', 'inside', 'left'], true)) {
    $vehicleView = $_GET['vehicle_view'];
}

$showVehicles = isset($_GET['show_vehicles']) && $_GET['show_vehicles'] == '1';

// Handle delete vehicle information without removing the whole visitor record.
if (isset($_GET['delete_vehicle_id'])) {
    $deleteVehicleId = intval($_GET['delete_vehicle_id']);
    if ($deleteVehicleId > 0) {
        $deleteVehicleStmt = $conn->prepare("UPDATE visitors SET has_motor = 'No', motor_type = '', model_name = '', plate_number = '' WHERE id = ? AND has_motor = 'Yes'");
        if ($deleteVehicleStmt) {
            $deleteVehicleStmt->bind_param('i', $deleteVehicleId);
            if ($deleteVehicleStmt->execute()) {
                $deleteVehicleStmt->close();
                $redirectVehicleView = in_array($_GET['vehicle_view'] ?? '', ['today', 'inside', 'left'], true) ? $_GET['vehicle_view'] : 'today';
                header('Location: gate_security_dashboard.php?show_vehicles=1&vehicle_view=' . urlencode($redirectVehicleView) . '&vehicle_deleted=1');
                exit();
            }
            $error = 'Tatizo wakati wa kufuta taarifa za gari: ' . $deleteVehicleStmt->error;
            $deleteVehicleStmt->close();
        } else {
            $error = 'Imeshindikana kuandaa delete vehicle statement: ' . $conn->error;
        }
    }
}

// Handle checkout (move visitor from inside to left)
if (isset($_GET['checkout_id'])) {
    $checkoutId = intval($_GET['checkout_id']);
    if ($checkoutId > 0) {
        $checkOutTime = date('Y-m-d H:i:s');
        $checkoutStmt = $conn->prepare("UPDATE visitors SET status = 'Left', check_out_time = ? WHERE id = ? AND status IN ('Inside', 'Checked In')");
        if ($checkoutStmt) {
            $checkoutStmt->bind_param('si', $checkOutTime, $checkoutId);
            if ($checkoutStmt->execute()) {
                $checkoutStmt->close();
                if ($showVehicles) {
                    header('Location: gate_security_dashboard.php?show_vehicles=1&vehicle_view=left&checkedout=1');
                } else {
                    $nextView = in_array($_GET['view'] ?? '', ['today', 'inside', 'left'], true) ? $_GET['view'] : 'left';
                    header('Location: gate_security_dashboard.php?view=' . urlencode($nextView) . '&checkedout=1');
                }
                exit();
            }
            $error = 'Tatizo wakati wa kumtoa mgeni nje: ' . $checkoutStmt->error;
            $checkoutStmt->close();
        } else {
            $error = 'Imeshindikana kuandaa checkout statement: ' . $conn->error;
        }
    }
}

// Load host and department data
$departments = [];
$allDepartments = getDepartments($conn, false);
$activeDepartments = getDepartments($conn, true);
$activeDepartmentMap = [];

foreach ($activeDepartments as $dRow) {
    $departments[] = $dRow['name'];
    $activeDepartmentMap[strtolower(trim((string)$dRow['name']))] = (int)$dRow['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_visitor'])) {
    $full_name = $conn->real_escape_string(trim($_POST['full_name']));
    $visitor_type = $conn->real_escape_string(trim($_POST['visitor_type'] ?? ''));
    $phone_number = $conn->real_escape_string(trim($_POST['phone_number']));
    $id_type = $conn->real_escape_string(trim($_POST['id_type']));
    $id_number = $conn->real_escape_string(trim($_POST['id_number']));
    $army_no = $conn->real_escape_string(trim($_POST['army_no'] ?? ''));
    $army_rank = $conn->real_escape_string(trim($_POST['army_rank'] ?? ''));
    $army_unit = $conn->real_escape_string(trim($_POST['army_unit'] ?? ''));
    $department = $conn->real_escape_string(trim($_POST['department']));
    $departmentId = $activeDepartmentMap[strtolower(trim($department))] ?? getDepartmentIdByName($conn, $department);
    $host_id = null; // No staff/host selection - set to NULL for optional foreign key
    $has_motor = isset($_POST['has_motor']) && $_POST['has_motor'] === 'Yes' ? 'Yes' : 'No';
    $motor_type = $conn->real_escape_string(trim($_POST['motor_type'] ?? ''));
    if ($motor_type !== '' && !in_array($motor_type, ['Kijeshi', 'Kiraia'], true)) {
        $motor_type = '';
    }
    $model_name = $conn->real_escape_string(trim($_POST['model_name'] ?? ''));
    $plate_number = $conn->real_escape_string(trim($_POST['plate_number'] ?? ''));

    // Basic required fields
    if (empty($full_name) || empty($visitor_type) || empty($phone_number) || empty($id_type) || empty($id_number) || empty($department)) {
        $error = 'Tafadhali jaza taarifa zote muhimu za mgeni, kitambulisho, na idara.';
    // Full name should contain letters and common separators only
    } elseif (!preg_match('/^[A-Za-z]+(?:[\s\'\.-][A-Za-z]+)*$/', $full_name)) {
        $error = 'Jina la mgeni liruhusu herufi tu (bila namba).';
    // Phone must be digits and max 10
    } elseif (!ctype_digit($phone_number) || strlen($phone_number) > 10) {
        $error = 'Namba ya simu lazima iwe tarakimu na isizozidi 10.';
    // ID number max length 20
    } elseif (strlen($id_number) > 20) {
        $error = 'Namba ya kitambulisho haizidi herufi/nombo 20.';
    // If they have a vehicle, plate number required and vehicle type must be selected
    } elseif ($has_motor === 'Yes' && (empty($plate_number) || empty($motor_type))) {
        $error = 'Ikiwa kuna gari, weka namba ya gari na aina ya gari (Kijeshi au Kiraia).';
    } elseif ($has_motor === 'Yes' && $motor_type === 'Kiraia' && empty($model_name)) {
        $error = 'Andika aina ya gari kwa mgeni wa kiraia.';
    } elseif ($visitor_type === 'Kijeshi' && (empty($army_no) || empty($army_rank) || empty($army_unit))) {
        $error = 'Weka namba ya jeshi, cheo, na kikosi kwa wageni wa kijeshi.';
    } elseif ($departmentId === null) {
        $error = 'Idara uliyochagua haipo kwenye mfumo.';
    } else {
        $id_type_upper = strtoupper(trim($id_type));
        $matchedById = null;

        // Returning visitor check by ID type + ID number.
        $idMatchStmt = $conn->prepare("SELECT id, full_name, phone_number FROM visitors WHERE UPPER(TRIM(id_type)) = UPPER(TRIM(?)) AND TRIM(id_number) = TRIM(?) ORDER BY id DESC LIMIT 1");
        if ($idMatchStmt) {
            $idMatchStmt->bind_param('ss', $id_type, $id_number);
            $idMatchStmt->execute();
            $idMatchResult = $idMatchStmt->get_result();
            if ($idMatchResult && $idMatchResult->num_rows > 0) {
                $matchedById = $idMatchResult->fetch_assoc();
            }
            $idMatchStmt->close();
        }

        if ($matchedById) {
            $matchedPhone = trim((string)($matchedById['phone_number'] ?? ''));
            if ($matchedPhone !== '' && $phone_number !== '' && $matchedPhone !== $phone_number) {
                $error = 'Kitambulisho hiki tayari ni cha ' . htmlspecialchars($matchedById['full_name'] ?? 'mgeni mwingine') . '. Namba ya simu haiendani na mmiliki wake.';
            }
        }

        if (empty($error) && !$matchedById) {
            // Phone number must be unique for a new identity.
            $dupPhoneStmt = $conn->prepare("SELECT id, full_name FROM visitors WHERE phone_number = ? LIMIT 1");
            if ($dupPhoneStmt) {
                $dupPhoneStmt->bind_param('s', $phone_number);
                $dupPhoneStmt->execute();
                $dupPhoneResult = $dupPhoneStmt->get_result();
                if ($dupPhoneResult && $dupPhoneResult->num_rows > 0) {
                    $existing = $dupPhoneResult->fetch_assoc();
                    $error = 'Namba ya simu tayari imeshatumika na mgeni mwingine (' . htmlspecialchars($existing['full_name'] ?? 'hajulikani') . ').';
                }
                $dupPhoneStmt->close();
            }
        }

        // NIDA number must be unique for all visitors with NIDA ID type.
        if (empty($error) && !$matchedById && $id_type_upper === 'NIDA') {
            $dupNidaStmt = $conn->prepare("SELECT id, full_name FROM visitors WHERE UPPER(TRIM(id_type)) = 'NIDA' AND TRIM(id_number) = TRIM(?) LIMIT 1");
            if ($dupNidaStmt) {
                $dupNidaStmt->bind_param('s', $id_number);
                $dupNidaStmt->execute();
                $dupNidaResult = $dupNidaStmt->get_result();
                if ($dupNidaResult && $dupNidaResult->num_rows > 0) {
                    $existing = $dupNidaResult->fetch_assoc();
                    $error = 'Namba ya NIDA tayari imeshatumika na mgeni mwingine (' . htmlspecialchars($existing['full_name'] ?? 'hajulikani') . ').';
                }
                $dupNidaStmt->close();
            }
        }

        if (!empty($error)) {
            // Stop here and show error message above form.
        } else {
        $purpose = 'Reception registration';
        $status = 'Inside';
        $check_in_time = date('Y-m-d H:i:s');

        $stmt = $conn->prepare("INSERT INTO visitors (
            full_name,
            visitor_type,
            phone_number,
            id_type,
            id_number,
            army_no,
            army_rank,
            army_unit,
            host_id,
            purpose,
            has_motor,
            motor_type,
            model_name,
            plate_number,
            department,
            department_id,
            status,
            check_in_time
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param(
                'ssssssssissssssiss',
                $full_name,
                $visitor_type,
                $phone_number,
                $id_type,
                $id_number,
                $army_no,
                $army_rank,
                $army_unit,
                $host_id,
                $purpose,
                $has_motor,
                $motor_type,
                $model_name,
                $plate_number,
                $department,
                $departmentId,
                $status,
                $check_in_time
            );
            if ($stmt->execute()) {
                $visitor_id = $conn->insert_id;
                
                // Create notifications for receptionists in this department
                createDepartmentNotification($conn, $visitor_id, $department, $full_name, $departmentId);
                
                header('Location: gate_security_dashboard.php?view=inside&registered=1&dept=' . urlencode($department));
                exit();
            } else {
                $error = 'Tatizo wakati wa kuandika kwa database: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = 'Imeshindikana kuandaa statement: ' . $conn->error;
        }
        }
    }
}

// Dashboard stats
$today = date('Y-m-d');
$stats = [
    'total_today' => 0,
    'inside' => 0,
    'vehicles_today' => 0,
    'checked_out_today' => 0,
];

$statQuery = $conn->query("SELECT
        SUM(DATE(check_in_time) = '$today') AS total_today,
        SUM(status IN ('Inside', 'Checked In')) AS inside,
        SUM(DATE(check_in_time) = '$today' AND has_motor = 'Yes') AS vehicles_today,
    SUM(status = 'Left' AND DATE(check_out_time) = '$today') AS checked_out_today
    FROM visitors");
if ($statQuery) {
    $stats = $statQuery->fetch_assoc();
}

$search = '';
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

$searchName = '';
if (isset($_GET['search_name'])) {
    $searchName = trim($_GET['search_name']);
    if ($searchName !== '') {
        // If searching by name, override view to show search results
    }
}

// Which view to show: 'today' or 'inside'
$view = isset($_GET['view']) ? ($_GET['view'] === 'inside' ? 'inside' : ($_GET['view'] === 'left' ? 'left' : ($_GET['view'] === 'today' ? 'today' : 'none'))) : 'none';
if ($forceViewInside) {
    $view = 'inside';
}

$searchSql = '';
if ($search !== '') {
    $safeSearch = $conn->real_escape_string($search);
    $searchSql = " AND (v.phone_number LIKE '%$safeSearch%' OR v.plate_number LIKE '%$safeSearch%')";
}

$searchNameSql = '';
if ($searchName !== '') {
    $safeName = $conn->real_escape_string($searchName);
    $searchNameSql = " AND v.full_name LIKE '%$safeName%'";
}

$recentVisitors = [];
$whereClause = '1=0';

// If searching by name, show all matching visitors (not filtered by view)
if ($searchName !== '') {
    $whereClause = "1=1";
    $searchSql = $searchNameSql;  // Use name search instead
} elseif ($view === 'inside') {
    $whereClause = "v.status IN ('Inside', 'Checked In')";
} elseif ($view === 'today') {
    $whereClause = "DATE(v.check_in_time) = '$today'";
} elseif ($view === 'left') {
    $whereClause = "v.status = 'Left' AND DATE(v.check_out_time) = '$today'";
}
$rs = $conn->query("SELECT v.*, v.department AS visitor_department
    FROM visitors v
    WHERE $whereClause $searchSql
    ORDER BY v.id DESC
    LIMIT 50");
if ($rs) {
    while ($row = $rs->fetch_assoc()) {
        $recentVisitors[] = $row;
    }
}

$vehicleVisitors = [];
if ($showVehicles) {
    if ($vehicleView === 'inside') {
        $baseVehicleWhere = "v.status IN ('Inside', 'Checked In') AND v.has_motor = 'Yes'";
    } elseif ($vehicleView === 'left') {
        $baseVehicleWhere = "v.status = 'Left' AND DATE(v.check_out_time) = '$today' AND v.has_motor = 'Yes'";
    } else {
        $baseVehicleWhere = "DATE(v.check_in_time) = '$today' AND v.has_motor = 'Yes'";
    }

    if ($searchName !== '') {
        $safeName = $conn->real_escape_string($searchName);
        $vwhere = $baseVehicleWhere . " AND v.full_name LIKE '%$safeName%'";
    } else {
        $vwhere = $baseVehicleWhere;
    }

    $vrs = $conn->query("SELECT v.id, v.full_name, v.phone_number, v.plate_number, v.check_in_time, v.check_out_time, v.status, v.department AS visitor_department FROM visitors v WHERE $vwhere ORDER BY v.id DESC LIMIT 200");
    if ($vrs) {
        while ($r = $vrs->fetch_assoc()) {
            $vehicleVisitors[] = $r;
        }
    }
}

include 'includes/header.php';
?>

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
    <?php 
    if ($current_user_id > 0) {
        $notifications = getUnreadNotifications($conn, $current_user_id, 5);
        if (count($notifications) > 0): 
    ?>
    <div class="row mb-4 justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="alert alert-info border-0 shadow-sm" role="alert">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="alert-heading mb-2"><i class="fas fa-bell me-2"></i>Taarifa za Wageni</h5>
                        <p class="mb-0">Una <strong><?= count($notifications) ?></strong> taarifa mpya za wageni waliofika idara husika.</p>
                    </div>
                    <button type="button" class="btn-close" onclick="markAllNotificationsAsRead()" title="Weka zote kama zilizosomwa"></button>
                </div>
                <hr>
                <div id="notificationsContainer" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item p-2 mb-2 bg-light rounded d-flex justify-content-between align-items-center" id="notif-<?= $notif['id'] ?>">
                        <div class="flex-grow-1">
                            <div class="font-weight-bold"><?= htmlspecialchars($notif['visitor_name'] ?? 'Mgeni') ?></div>
                            <small class="text-muted">Idara: <?= htmlspecialchars($notif['department'] ?? '') ?></small>
                            <?php if (!empty($notif['message'])): ?>
                                <br>
                                <small><?= htmlspecialchars($notif['message']) ?></small>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted"><?= date('H:i, d M', strtotime($notif['created_at'])) ?></small>
                        </div>
                        <button type="button" class="btn btn-sm btn-close" onclick="deleteNotification(<?= $notif['id'] ?>)" title="Futa taarifa hii"></button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; 
    } 
    ?>
    <!-- END NOTIFICATIONS SECTION -->

    <div class="row g-4 justify-content-center">
        <div class="col-12 col-xl-10">
            <div class="registration-card shadow-lg p-4">
                <h5><i class="fas fa-clipboard-list me-2"></i>Usajili wa Mapokezi</h5>
                <?php if ($message): ?>
                    <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="mb-3">
                        <label class="form-label">Aina ya Mgeni</label>
                        <select name="visitor_type" id="visitorTypeSelect" class="form-select" required onchange="toggleMilitaryFields(this.value)">
                            <option value="">Chagua</option>
                            <option value="Kiraia" <?= (($_POST['visitor_type'] ?? '') === 'Kiraia') ? 'selected' : '' ?>>Mgeni wa Kiraia</option>
                            <option value="Kijeshi" <?= (($_POST['visitor_type'] ?? '') === 'Kijeshi') ? 'selected' : '' ?>>Mgeni wa Kijeshi</option>
                        </select>
                    </div>
                    <div id="militaryFields" style="display: <?= (($_POST['visitor_type'] ?? '') === 'Kijeshi') ? 'block' : 'none' ?>;">
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Army No</label>
                                <input type="text" id="armyNoInput" name="army_no" class="form-control" value="<?= htmlspecialchars($_POST['army_no'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Rank</label>
                                <input type="text" id="armyRankInput" name="army_rank" class="form-control" value="<?= htmlspecialchars($_POST['army_rank'] ?? '') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Kikosi</label>
                                <input type="text" id="armyUnitInput" name="army_unit" class="form-control" value="<?= htmlspecialchars($_POST['army_unit'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Jina la Mgeni</label>
                        <input type="text" id="fullNameInput" name="full_name" class="form-control" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" pattern="[A-Za-z]+([ '\\.-][A-Za-z]+)*" title="Jina litumie herufi tu bila namba" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Namba ya Simu</label>
                        <input type="text" id="phoneNumberInput" name="phone_number" maxlength="10" pattern="[0-9]+" inputmode="numeric" class="form-control" value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>" required>
                        <div class="form-text">Nambari zisizozidi tarakimu 10.</div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Aina ya Kitambulisho</label>
                            <select id="idTypeSelect" name="id_type" class="form-select" required>
                                <option value="">Chagua</option>
                                <?php foreach (['Passport','NIDA','Driving License','Other'] as $type): ?>
                                    <option value="<?= $type ?>" <?= (($_POST['id_type'] ?? '') === $type) ? 'selected' : '' ?>><?= $type ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Namba ya Kitambulisho</label>
                            <input type="text" id="idNumberInput" name="id_number" maxlength="20" class="form-control" value="<?= htmlspecialchars($_POST['id_number'] ?? '') ?>" required>
                            <div class="form-text">Namba ya kitambulisho haizidi herufi/nombo 20.</div>
                            <div id="visitorLookupStatus" class="form-text"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Idara</label>
                        <select id="departmentSelect" name="department" class="form-select" required>
                            <option value="">Chagua idara</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= (($_POST['department'] ?? '') === $dept) ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label d-block">Je, ameingia na gari?</label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="has_motor" id="hasMotorYes" value="Yes" <?= (($_POST['has_motor'] ?? 'No') === 'Yes') ? 'checked' : '' ?> onclick="toggleVehicleFields(true)">
                            <label class="form-check-label" for="hasMotorYes">Ndiyo</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="has_motor" id="hasMotorNo" value="No" <?= (($_POST['has_motor'] ?? 'No') === 'Yes') ? '' : 'checked' ?> onclick="toggleVehicleFields(false)">
                            <label class="form-check-label" for="hasMotorNo">Hapana</label>
                        </div>
                    </div>

                    <div id="vehicleFields" style="display: <?= (($_POST['has_motor'] ?? 'No') === 'Yes') ? 'block' : 'none' ?>;">
                        <div class="mb-3">
                            <label class="form-label">Namba ya gari</label>
                            <input type="text" id="plateNumberInput" name="plate_number" class="form-control" value="<?= htmlspecialchars($_POST['plate_number'] ?? '') ?>" placeholder="Ingiza namba ya gari">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Aina ya Gari</label>
                            <select id="motorTypeSelect" name="motor_type" class="form-select" onchange="handleMotorTypeChange(this.value)">
                                <option value="">Chagua aina</option>
                                <option value="Kijeshi" <?= (($_POST['motor_type'] ?? '') === 'Kijeshi') ? 'selected' : '' ?>>Kijeshi</option>
                                <option value="Kiraia" <?= (($_POST['motor_type'] ?? '') === 'Kiraia') ? 'selected' : '' ?>>Kiraia</option>
                            </select>
                        </div>
                        <div id="civilianVehicleDetails" style="display: <?= (($_POST['motor_type'] ?? '') === 'Kiraia') ? 'block' : 'none' ?>;">
                            <div class="mb-3">
                                <label class="form-label">Aina ya gari la kiraia</label>
                                <input type="text" id="modelNameInput" name="model_name" class="form-control" value="<?= htmlspecialchars($_POST['model_name'] ?? '') ?>" placeholder="Mfano: Toyota Land Cruiser">
                            </div>
                        </div>
                    </div>

                    <button type="submit" name="register_visitor" value="1" class="btn btn-register w-100"><i class="fas fa-save me-2"></i>Weka Usajili</button>
                </form>
            </div>

            <div class="registration-card shadow-lg p-4 mt-4">
                <h5><i class="fas fa-eye me-2"></i>Chagua nini kuonyeshwa</h5>
                <div class="d-grid gap-2 mb-4 view-buttons">
                    <a href="?view=today" class="btn btn-outline-primary <?= ($view==='today') ? 'active' : '' ?>"><i class="fas fa-calendar-day me-2"></i>Wageni Waliosajiliwa Leo</a>
                    <a href="?view=inside" class="btn btn-outline-success <?= ($view==='inside') ? 'active' : '' ?>"><i class="fas fa-sign-in-alt me-2"></i>Walioko Ndani</a>
                    <a href="?view=left" class="btn btn-outline-danger <?= ($view==='left') ? 'active' : '' ?>"><i class="fas fa-sign-out-alt me-2"></i>Waliotoka Leo</a>
                    <a href="?show_vehicles=1&vehicle_view=today" class="btn btn-outline-warning <?= ($showVehicles && $vehicleView==='today') ? 'active' : '' ?>"><i class="fas fa-car me-2"></i>Magari ya Leo</a>
                    <a href="?show_vehicles=1&vehicle_view=inside" class="btn btn-outline-warning <?= ($showVehicles && $vehicleView==='inside') ? 'active' : '' ?>"><i class="fas fa-warehouse me-2"></i>Magari Yalioko Ndani</a>
                    <a href="?show_vehicles=1&vehicle_view=left" class="btn btn-outline-warning <?= ($showVehicles && $vehicleView==='left') ? 'active' : '' ?>"><i class="fas fa-road me-2"></i>Magari Yaliyotoka Nje</a>
                </div>

                <form method="get" class="d-flex gap-2 search-form">
                    <input type="text" name="search_name" class="form-control flex-grow-1" placeholder="Tafuta mgeni kwa jina..." value="<?= htmlspecialchars($_GET['search_name'] ?? '') ?>">
                    <button type="submit" class="btn btn-info"><i class="fas fa-search me-2"></i>Tafuta</button>
                </form>

                <div class="inline-display">
                    <?php if ($showVehicles): ?>
                        <!-- Show vehicles with tariffs -->
                        <h6 class="mb-3">
                            <?php
                            if ($vehicleView === 'inside') {
                                echo 'Magari Yalioko Ndani';
                            } elseif ($vehicleView === 'left') {
                                echo 'Magari Yaliyotoka Nje Leo';
                            } else {
                                echo 'Magari ya Leo';
                            }
                            ?>
                        </h6>
                        <?php if (count($vehicleVisitors) === 0): ?>
                            <p class="text-muted">Hakuna taarifa za magari kwa mtazamo huu.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Jina</th>
                                            <th>Simu</th>
                                            <th>Idara</th>
                                            <th>Namba ya Gari</th>
                                            <th>Status</th>
                                            <th>Time</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vehicleVisitors as $vv): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($vv['full_name']) ?></td>
                                                <td><?= htmlspecialchars($vv['phone_number']) ?></td>
                                                <td><?= htmlspecialchars($vv['visitor_department'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($vv['plate_number'] ?: '-') ?></td>
                                                <td>
                                                    <?php if (in_array($vv['status'], ['Inside', 'Checked In'], true)): ?>
                                                        <span class="badge bg-success">Ndani</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Ametoka</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= date('H:i, d-m', strtotime($vv['check_in_time'])) ?></td>
                                                <td class="d-flex gap-1 flex-wrap">
                                                    <?php if (in_array($vv['status'], ['Inside', 'Checked In'], true)): ?>
                                                        <a href="?checkout_id=<?= intval($vv['id']) ?>&show_vehicles=1&vehicle_view=inside" class="btn btn-sm btn-danger" onclick="return confirm('Una uhakika unataka kumtoa mgeni nje?')">Mtoa Nje</a>
                                                    <?php endif; ?>
                                                    <a href="?delete_vehicle_id=<?= intval($vv['id']) ?>&show_vehicles=1&vehicle_view=<?= urlencode($vehicleView) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Una uhakika unataka kufuta taarifa za gari hili?')">Toa Gari Nje</a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($searchName !== ''): ?>
                        <!-- Show search by name results -->
                        <h6 class="mb-3">Matokeo ya Utafutaji: "<?= htmlspecialchars($searchName) ?>"</h6>
                        <?php if (count($recentVisitors) === 0): ?>
                            <p class="text-muted">Hakuna mgeni aliyefound kwa jina hili.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Jina</th>
                                            <th>Simu</th>
                                            <th>Idara</th>
                                            <th>Gari</th>
                                            <th>Check In</th>
                                            <th>Check Out</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentVisitors as $v): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($v['full_name']) ?></td>
                                                <td><?= htmlspecialchars($v['phone_number']) ?></td>
                                                <td><?= htmlspecialchars($v['visitor_department'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($v['has_motor'] === 'Yes' ? ($v['plate_number'] ?: 'Ndiyo') : 'Hapana') ?></td>
                                                <td><?= date('H:i, d-m', strtotime($v['check_in_time'])) ?></td>
                                                <td><?= $v['check_out_time'] ? date('H:i, d-m', strtotime($v['check_out_time'])) : '-' ?></td>
                                                <td>
                                                    <?php if (in_array($v['status'], ['Inside', 'Checked In'], true)): ?>
                                                        <a href="?checkout_id=<?= intval($v['id']) ?>&view=inside" class="btn btn-sm btn-danger" onclick="return confirm('Una uhakika unataka kumtoa mgeni nje?')">Mtoa Nje</a>
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
                    <?php elseif ($view !== 'none'): ?>
                        <!-- Show view-based results (today/inside/left) -->
                        <h6 class="mb-3">
                            <?php 
                            if ($view === 'today') echo 'Wageni Waliosajiliwa Leo';
                            elseif ($view === 'inside') echo 'Wageni Walioko Ndani';
                            elseif ($view === 'left') echo 'Wageni Waliotoka Leo';
                            ?>
                        </h6>
                        <?php if (count($recentVisitors) === 0): ?>
                            <p class="text-muted">Hakuna mgeni wa mtazamo ulioteuliwa.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Jina</th>
                                            <th>Simu</th>
                                            <th>Idara</th>
                                            <th>Gari</th>
                                            <th>Check In</th>
                                            <th>Check Out</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentVisitors as $v): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($v['full_name']) ?></td>
                                                <td><?= htmlspecialchars($v['phone_number']) ?></td>
                                                <td><?= htmlspecialchars($v['visitor_department'] ?: '-') ?></td>
                                                <td><?= htmlspecialchars($v['has_motor'] === 'Yes' ? ($v['plate_number'] ?: 'Ndiyo') : 'Hapana') ?></td>
                                                <td><?= date('H:i, d-m', strtotime($v['check_in_time'])) ?></td>
                                                <td><?= $v['check_out_time'] ? date('H:i, d-m', strtotime($v['check_out_time'])) : '-' ?></td>
                                                <td>
                                                    <?php if (in_array($v['status'], ['Inside', 'Checked In'], true)): ?>
                                                        <a href="?checkout_id=<?= intval($v['id']) ?>&view=inside" class="btn btn-sm btn-danger" onclick="return confirm('Una uhakika unataka kumtoa mgeni nje?')">Mtoa Nje</a>
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
                    <?php else: ?>
                        <p class="text-muted">Chagua mtazamo kuonyesha wageni.</p>
                    <?php endif; ?>
                </div>

                    <?php if ($view !== 'none' && $searchName === ''): ?>
                    <div class="registration-card shadow-lg p-4 mt-4">
                        <h5><i class="fas fa-chart-bar me-2"></i>Taarifa za mgeni leo</h5>
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <div class="stat-box">
                                    <div class="text-uppercase text-muted small">Wageni leo</div>
                                    <div class="fs-3"><?= intval($stats['total_today']) ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="stat-box">
                                    <div class="text-uppercase text-muted small">Walioko ndani</div>
                                    <div class="fs-3"><?= intval($stats['inside']) ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="stat-box">
                                    <div class="text-uppercase text-muted small">Magari leo</div>
                                    <div class="fs-3"><?= intval($stats['vehicles_today']) ?></div>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="stat-box">
                                    <div class="text-uppercase text-muted small">Waliotoka leo</div>
                                    <div class="fs-3"><?= intval($stats['checked_out_today']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

        </div>
    </div>
</div>

<script>
const csrfToken = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>';

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
        <?php if (!empty($_SESSION['show_vehicle'])): ?>
            <?php if (count($vehicleVisitors) === 0): ?>
                <p class="text-muted">Hakuna taarifa za magari kwa mtazamo huu.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Jina</th>
                                <th>Simu</th>
                                <th>Namba ya Gari</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehicleVisitors as $vv): ?>
                                <tr>
                                    <td><?= htmlspecialchars($vv['full_name']) ?></td>
                                    <td><?= htmlspecialchars($vv['phone_number']) ?></td>
                                    <td><?= htmlspecialchars($vv['plate_number'] ?: '-') ?></td>
                                    <td><?= date('H:i, d-m', strtotime($vv['check_in_time'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <form method="post" class="mb-0">
                <div class="mb-3">
                    <label class="form-label">Passcode</label>
                    <input type="password" name="vehicle_pass" class="form-control" required>
                </div>
                <button type="submit" name="vehicle_pass_submit" class="btn btn-primary">Wangia</button>
            </form>
            <p class="small text-muted mt-3">Tafadhali ingiza passcode ili kuona namba za gari.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
