<?php
require_once 'config/db_config.php';

// Hakikisha unaseti Timezone kama kawaida
date_default_timezone_set('Africa/Nairobi');

$phone = isset($_GET['phone']) ? $conn->real_escape_string(trim($_GET['phone'])) : '';
$response = ['found' => false];

if (!empty($phone)) {
    // 1. Tunatafuta jina na 'status' ya mwisho ya huyu mgeni
    // Tunatumia ORDER BY id DESC ili kupata rekodi yake ya karibuni zaidi
    $sql = "SELECT full_name, status FROM visitors WHERE phone_number = '$phone' ORDER BY id DESC LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result && $row = $result->fetch_assoc()) {
        $response = [
            'found' => true,
            'full_name' => $row['full_name'],
            'status' => $row['status'] // Hii ni muhimu ili kuzuia double registration
        ];
    }
}

// Hakikisha unatoa JSON format
header('Content-Type: application/json');
echo json_encode($response);