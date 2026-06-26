<?php
require_once __DIR__ . '/../config/db_config.php';

$sql = "SELECT id, full_name, phone_number, visitor_photo, check_in_time FROM visitors ORDER BY id DESC LIMIT 50";
$res = $conn->query($sql);
if (!$res) {
    echo "Query error: " . $conn->error . "\n";
    exit(1);
}

$rows = [];
while ($r = $res->fetch_assoc()) {
    $file = $r['visitor_photo'];
    $exists = ($file && file_exists(__DIR__ . '/../' . $file)) ? 'yes' : 'no';
    $rows[] = [
        'id' => $r['id'],
        'name' => $r['full_name'],
        'phone' => $r['phone_number'],
        'photo' => $r['visitor_photo'],
        'file_exists' => $exists,
        'check_in_time' => $r['check_in_time']
    ];
}

foreach ($rows as $r) {
    echo "ID: {$r['id']} | {$r['name']} | {$r['phone']} | photo: {$r['photo']} | exists: {$r['file_exists']} | in: {$r['check_in_time']}\n";
}

?>