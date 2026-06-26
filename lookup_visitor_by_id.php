<?php
require_once 'config/db_config.php';

date_default_timezone_set('Africa/Nairobi');

header('Content-Type: application/json; charset=UTF-8');

$idType = trim((string)($_GET['id_type'] ?? ''));
$idNumber = trim((string)($_GET['id_number'] ?? ''));
$normalizedIdNumber = preg_replace('/[\s-]+/', '', $idNumber);

if ($normalizedIdNumber === '') {
    echo json_encode([
        'found' => false,
        'message' => 'id_number is required',
    ]);
    exit;
}

$sql = "SELECT
        id,
        full_name,
        visitor_type,
        phone_number,
        id_type,
        id_number,
        army_no,
        army_rank,
        army_unit,
        department,
        has_motor,
        motor_type,
        model_name,
        plate_number,
        status,
        check_in_time
     FROM visitors
     ORDER BY id DESC
     LIMIT 1";

if ($idType !== '') {
    $sql = "SELECT
        id,
        full_name,
        visitor_type,
        phone_number,
        id_type,
        id_number,
        army_no,
        army_rank,
        army_unit,
        department,
        has_motor,
        motor_type,
        model_name,
        plate_number,
        status,
        check_in_time
     FROM visitors
     WHERE UPPER(TRIM(id_type)) = UPPER(TRIM(?))
       AND REPLACE(REPLACE(TRIM(id_number), ' ', ''), '-', '') = ?
     ORDER BY id DESC
     LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'found' => false,
            'message' => 'Failed to prepare query',
        ]);
        exit;
    }
    $stmt->bind_param('ss', $idType, $normalizedIdNumber);
} else {
    $sql = "SELECT
        id,
        full_name,
        visitor_type,
        phone_number,
        id_type,
        id_number,
        army_no,
        army_rank,
        army_unit,
        department,
        has_motor,
        motor_type,
        model_name,
        plate_number,
        status,
        check_in_time
     FROM visitors
     WHERE REPLACE(REPLACE(TRIM(id_number), ' ', ''), '-', '') = ?
     ORDER BY id DESC
     LIMIT 1";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode([
            'found' => false,
            'message' => 'Failed to prepare query',
        ]);
        exit;
    }
    $stmt->bind_param('s', $normalizedIdNumber);
}

$stmt->execute();
$result = $stmt->get_result();
$row = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    echo json_encode([
        'found' => false,
    ]);
    exit;
}

echo json_encode([
    'found' => true,
    'visitor' => [
        'id' => (int)$row['id'],
        'full_name' => (string)($row['full_name'] ?? ''),
        'visitor_type' => (string)($row['visitor_type'] ?? ''),
        'phone_number' => (string)($row['phone_number'] ?? ''),
        'id_type' => (string)($row['id_type'] ?? ''),
        'id_number' => (string)($row['id_number'] ?? ''),
        'army_no' => (string)($row['army_no'] ?? ''),
        'army_rank' => (string)($row['army_rank'] ?? ''),
        'army_unit' => (string)($row['army_unit'] ?? ''),
        'department' => (string)($row['department'] ?? ''),
        'has_motor' => (string)($row['has_motor'] ?? 'No'),
        'motor_type' => (string)($row['motor_type'] ?? ''),
        'model_name' => (string)($row['model_name'] ?? ''),
        'plate_number' => (string)($row['plate_number'] ?? ''),
        'status' => (string)($row['status'] ?? ''),
        'last_check_in_time' => (string)($row['check_in_time'] ?? ''),
    ],
]);
