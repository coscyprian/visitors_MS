<?php
require_once 'config/db_config.php';

$start_date = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
$end_date = trim((string)($_GET['end_date'] ?? date('Y-m-d')));
$status_filter = trim((string)($_GET['status'] ?? 'all'));
$visitor_type_filter = trim((string)($_GET['visitor_type'] ?? 'all'));
$motor_filter = trim((string)($_GET['has_motor'] ?? 'all'));
$department_filter = trim((string)($_GET['department'] ?? 'all'));

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
    $where[] = "TRIM(COALESCE(v.department, '')) = ?";
    $types .= 's';
    $params[] = $department_filter;
}

$sql = "SELECT
            v.check_in_time,
            v.check_out_time,
            v.full_name,
            v.phone_number,
            v.visitor_type,
            v.id_type,
            v.id_number,
            COALESCE(h.name, 'N/A') AS host_name,
            COALESCE(TRIM(v.department), '') AS visitor_department,
            COALESCE(TRIM(h.department), '') AS host_department,
            v.purpose,
            v.status,
            v.has_motor,
            v.motor_type,
            v.plate_number,
            v.model_name
        FROM visitors v
        LEFT JOIN hosts h ON v.host_id = h.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY v.check_in_time DESC";

$stmt = $conn->prepare($sql);
$result = null;
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
}

$filename = 'visitor-report-'.date('YmdHis').'.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store, no-cache');

$output = fopen('php://output', 'w');
fputcsv($output, ['Date/Time In', 'Date/Time Out', 'Visitor Name', 'Contact', 'Visitor Type', 'ID Type', 'ID Number', 'Host', 'Department', 'Purpose', 'Status', 'Has Motor', 'Motor Type', 'Plate Number', 'Model/Brand']);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $dept = $row['visitor_department'] !== '' ? $row['visitor_department'] : ($row['host_department'] !== '' ? $row['host_department'] : '-');
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($row['check_in_time'])),
            !empty($row['check_out_time']) ? date('d/m/Y H:i', strtotime($row['check_out_time'])) : '-',
            $row['full_name'],
            $row['phone_number'],
            $row['visitor_type'],
            $row['id_type'],
            $row['id_number'],
            $row['host_name'],
            $dept,
            $row['purpose'],
            $row['status'],
            $row['has_motor'],
            $row['motor_type'],
            $row['plate_number'],
            $row['model_name'],
        ]);
    }
}

fclose($output);
if ($stmt) {
    $stmt->close();
}
exit();
