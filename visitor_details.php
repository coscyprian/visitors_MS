<?php

require_once 'config/db_config.php';

if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    $stmt = $conn->prepare("
        SELECT v.*, h.name as host_name
        FROM visitors v
        LEFT JOIN hosts h ON v.host_id = h.id
        WHERE v.id=?
    ");

    $stmt->bind_param("i", $id);

    $stmt->execute();

    $result = $stmt->get_result();

    $visitor = $result->fetch_assoc();
}

?>

<!DOCTYPE html>
<html>
<head>

    <title>Visitor Details</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

</head>

<body class="bg-light">

<div class="container mt-5">

    <div class="card shadow p-4">

        <h2 class="mb-4">Visitor Details</h2>

        <?php if(isset($visitor)): ?>

            <p><strong>Name:</strong> <?= $visitor['full_name'] ?></p>

            <p><strong>Phone:</strong> <?= $visitor['phone_number'] ?></p>

            <p><strong>ID Type:</strong> <?= $visitor['id_type'] ?></p>

            <p><strong>ID Number:</strong> <?= $visitor['id_number'] ?></p>

            <p><strong>Host:</strong> <?= $visitor['host_name'] ?></p>

            <p><strong>Status:</strong> <?= $visitor['status'] ?></p>
    

        <?php else: ?>

            <div class="alert alert-danger">

                Visitor not found.

            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>