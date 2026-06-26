<?php
require_once 'config/db_config.php';
header('Content-Type: application/json');

// accept plate param and normalize: remove non-alphanumeric, uppercase
$plate = trim($_GET['plate'] ?? '');
if ($plate === '') {
    echo json_encode(['found' => false]);
    exit;
}
$norm = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $plate));
$plate_sql = $conn->real_escape_string($norm);

// Compare normalized plate (remove non-alphanumerics) to improve matching
$sql = "SELECT plate_number, motor_type, model_name FROM motors WHERE REPLACE(REPLACE(REPLACE(REPLACE(UPPER(plate_number),' ',''),'-',''),'.',''),'/','') = '$plate_sql' LIMIT 1";
$res = $conn->query($sql);
if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    echo json_encode([
        'found' => true,
        'plate_number' => $row['plate_number'],
        'motor_type' => $row['motor_type'],
        'model_name' => $row['model_name'],
        'matched' => 'exact'
    ]);
} else {
    // fallback: try a LIKE match on the normalized plate (covers formatting differences)
    $like_sql = "SELECT plate_number, motor_type, model_name FROM motors WHERE REPLACE(REPLACE(REPLACE(REPLACE(UPPER(plate_number),' ',''),'-',''),'.',''),'/','') LIKE '%$plate_sql%' LIMIT 1";
    $res2 = $conn->query($like_sql);
    if ($res2 && $res2->num_rows > 0) {
        $row = $res2->fetch_assoc();
        echo json_encode([
            'found' => true,
            'plate_number' => $row['plate_number'],
            'motor_type' => $row['motor_type'],
            'model_name' => $row['model_name'],
            'matched' => 'like',
            'sql' => $like_sql
        ]);
        exit;
    }

    $out = ['found' => false];
    if (isset($_GET['debug']) && $_GET['debug']) {
        $out['plate_sql'] = $plate_sql;
        $out['sql'] = $sql;
    }
    echo json_encode($out);
}

exit;
