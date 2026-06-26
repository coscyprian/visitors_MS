<?php

require_once 'config/db_config.php';

if (isset($_GET['code'])) {

    $qr = $_GET['code'];

    // toa VISITOR_ID:
    $visitor_id = str_replace("VISITOR_ID:", "", $qr);

    // tafuta visitor
    $stmt = $conn->prepare("
        SELECT v.*, h.name as host_name
        FROM visitors v
        LEFT JOIN hosts h ON v.host_id = h.id
        WHERE v.id = ?
    ");

    $stmt->bind_param("i", $visitor_id);

    $stmt->execute();

    $result = $stmt->get_result();

    $visitor = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>QR Scanner - Visitor System</title>

    <script src="https://unpkg.com/html5-qrcode"></script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7fe;
            font-family: Arial;
        }

        #reader {
            width: 400px;
            margin: auto;
            margin-top: 50px;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .result-box {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>

<body>

<div class="container text-center mt-5">

    <h3 class="fw-bold">QR Code Scanner</h3>
    <p class="text-muted">Scan Visitor QR to Check-in / Check-out</p>

    <!-- CAMERA AREA -->
    <div id="reader"></div>
    <?php if(isset($visitor)): ?>

<div class="card p-4 mt-4 shadow">

    <h3>Visitor Details</h3>

    <p><strong>Name:</strong> <?= $visitor['full_name'] ?></p>

    <p><strong>Phone:</strong> <?= $visitor['phone_number'] ?></p>

    <p><strong>ID Type:</strong> <?= $visitor['id_type'] ?></p>

    <p><strong>ID Number:</strong> <?= $visitor['id_number'] ?></p>

    <p><strong>Host:</strong> <?= $visitor['host_name'] ?></p>

    <p><strong>Status:</strong> <?= $visitor['status'] ?></p>

</div>

<?php endif; ?>

    <!-- RESULT AREA -->
    <div class="result-box">
        <h5 id="result">Waiting for scan...</h5>
    </div>

</div>

<script>

function onScanSuccess(decodedText) {

    window.location.href =
    "qr_scanner.php?code=" + encodeURIComponent(decodedText);

}

let html5QrcodeScanner = new Html5QrcodeScanner(
    "reader",
    { fps: 10, qrbox: 250 }
);

html5QrcodeScanner.render(onScanSuccess);

</script>

    // SEND TO SERVER
    fetch('process_scan.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'qr_data=' + encodeURIComponent(decodedText)
    })
    .then(res => res.text())
    .then(data => {
        alert(data);
    });
}

// START CAMERA SCANNER
let html5QrcodeScanner = new Html5QrcodeScanner(
    "reader",
    { fps: 10, qrbox: 250 }
);

html5QrcodeScanner.render(onScanSuccess);
</script>

</body>
</html>