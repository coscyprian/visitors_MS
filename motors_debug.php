<?php
require_once 'config/db_config.php';
// Simple debug view to show stored plates and normalized form
header('Content-Type: text/html; charset=utf-8');
echo "<h3>Motors table debug (showing normalized plates)</h3>";
echo "<table border=1 cellpadding=6 cellspacing=0><tr><th>ID</th><th>plate_number</th><th>normalized</th><th>motor_type</th><th>model_name</th></tr>";
$res = $conn->query("SELECT id, plate_number, motor_type, model_name FROM motors ORDER BY id DESC LIMIT 200");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $orig = $r['plate_number'];
        $norm = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $orig));
        echo '<tr>';
        echo '<td>' . htmlspecialchars($r['id']) . '</td>';
        echo '<td>' . htmlspecialchars($orig) . '</td>';
        echo '<td>' . htmlspecialchars($norm) . '</td>';
        echo '<td>' . htmlspecialchars($r['motor_type']) . '</td>';
        echo '<td>' . htmlspecialchars($r['model_name']) . '</td>';
        echo '</tr>';
    }
}
echo '</table>';

echo '<p>Use this to compare with the plate you type in the visitor form. If normalization differs, share a sample plate and a sample row from this table.</p>';

?>
