<?php

require_once 'config/db_config.php';

if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    $stmt = $conn->prepare("DELETE FROM visitors WHERE id=?");

    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {

        header("Location: view_visitors.php?success=deleted");
        exit();

    } else {

        echo "Failed to delete visitor.";
    }
}