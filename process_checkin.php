<?php

// ===============================
// INCLUDE FILES
// ===============================
require_once 'config/db_config.php';
require_once 'phpqrcode/qrlib.php';
require_once 'config/sms_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

date_default_timezone_set('Africa/Nairobi');

// ===============================
// CHECK REQUEST
// ===============================
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ===============================
    // GET FORM DATA
    // ===============================
    $full_name    = trim($_POST['full_name']);
    $phone_number = trim($_POST['visitor_phone']);
    $host_id      = intval($_POST['host_id']);
    $purpose      = trim($_POST['purpose']);

    $id_type      = trim($_POST['id_type']);
    $id_number    = trim($_POST['id_number']);

    $has_motor    = $_POST['has_motor'] ?? 'No';
    $motor_type   = $_POST['motor_type'] ?? '';
    $plate_number = $_POST['plate_number'] ?? '';
    $model_name   = $_POST['model_name'] ?? '';

    $status       = "Checked In";
    $check_in_time = date('Y-m-d H:i:s');

    if (!preg_match('/^[A-Za-z]+(?:[\s\'\.-][A-Za-z]+)*$/', $full_name)) {
        $msg = "Jina la mgeni liruhusu herufi tu (bila namba).";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit();
        }
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        exit();
    }

    if (!ctype_digit($phone_number) || strlen($phone_number) > 10) {
        $msg = "Namba ya simu lazima iwe tarakimu na isizozidi 10.";
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $msg]);
            exit();
        }
        echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
        exit();
    }

    $id_type_upper = strtoupper(trim($id_type));

    $matchedById = null;

    // If this ID already exists, treat as returning visitor candidate.
    $idMatchStmt = $conn->prepare("SELECT id, full_name, phone_number, status FROM visitors WHERE UPPER(TRIM(id_type)) = UPPER(TRIM(?)) AND TRIM(id_number) = TRIM(?) ORDER BY id DESC LIMIT 1");
    if ($idMatchStmt) {
        $idMatchStmt->bind_param("ss", $id_type, $id_number);
        $idMatchStmt->execute();
        $idMatchResult = $idMatchStmt->get_result();
        if ($idMatchResult && $idMatchResult->num_rows > 0) {
            $matchedById = $idMatchResult->fetch_assoc();
        }
        $idMatchStmt->close();
    }

    if ($matchedById) {
        // Check if visitor is still inside (not checked out)
        $visitorStatus = strtolower(trim($matchedById['status'] ?? ''));
        if (in_array($visitorStatus, ['inside', 'checked in', 'check in'], true)) {
            $msg = "Mgeni " . ($matchedById['full_name'] ?? 'huyo') . " bado yuko ndani. Tafadhali mpeleke nje kabla ya kumsajili tena.";

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $msg]);
                exit();
            }

            echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
            exit();
        }
        // If visitor has left, allow re-registration (do nothing, proceed)
    }

    // Check for duplicate phone number regardless of ID match
    // This prevents registering same visitor with different ID while still inside
    if ($phone_number !== '') {
        $phoneCheckStmt = $conn->prepare("SELECT id, full_name, status FROM visitors WHERE phone_number = ? ORDER BY id DESC LIMIT 1");
        if ($phoneCheckStmt) {
            $phoneCheckStmt->bind_param("s", $phone_number);
            $phoneCheckStmt->execute();
            $phoneCheckResult = $phoneCheckStmt->get_result();
            if ($phoneCheckResult && $phoneCheckResult->num_rows > 0) {
                $existingPhoneVisitor = $phoneCheckResult->fetch_assoc();
                $existingStatus = strtolower(trim($existingPhoneVisitor['status'] ?? ''));

                // Only block if the visitor with this phone is still inside
                if (in_array($existingStatus, ['inside', 'checked in', 'check in'], true)) {
                    $msg = "Namba ya simu hii inatumika na mgeni " . ($existingPhoneVisitor['full_name'] ?? 'huyo') . " ambaye bado yuko ndani. Tafadhali mpeleke nje kabla ya kumsajili tena.";

                    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => $msg]);
                        exit();
                    }

                    echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
                    exit();
                }
            }
            $phoneCheckStmt->close();
        }
    }


    // Enforce unique NIDA number for new identity.
    if (!$matchedById && $id_type_upper === 'NIDA' && $id_number !== '') {
        $nidaDupStmt = $conn->prepare("SELECT id, full_name FROM visitors WHERE UPPER(TRIM(id_type)) = 'NIDA' AND TRIM(id_number) = TRIM(?) LIMIT 1");
        if ($nidaDupStmt) {
            $nidaDupStmt->bind_param("s", $id_number);
            $nidaDupStmt->execute();
            $nidaDupResult = $nidaDupStmt->get_result();

            if ($nidaDupResult && $nidaDupResult->num_rows > 0) {
                $existingVisitor = $nidaDupResult->fetch_assoc();
                $msg = "Namba ya NIDA tayari imeshatumika na mgeni mwingine (" . ($existingVisitor['full_name'] ?? 'hajulikani') . "). Tafadhali hakiki namba hiyo.";

                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => $msg]);
                    exit();
                }

                echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8');
                exit();
            }
            $nidaDupStmt->close();
        }
    }

    // ===============================
    // SAVE VISITOR
    // ===============================
    $sql = "
        INSERT INTO visitors (
            full_name,
            phone_number,
            id_type,
            id_number,
            host_id,
            purpose,
            has_motor,
            motor_type,
            plate_number,
            status,
            check_in_time
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "ssssissssss",
        $full_name,
        $phone_number,
        $id_type,
        $id_number,
        $host_id,
        $purpose,
        $has_motor,
        $motor_type,
        $plate_number,
        $status,
        $check_in_time
    );

    if ($stmt->execute()) {

        $visitor_id = $conn->insert_id;

        // ===============================
        // SAVE MOTOR (IF AVAILABLE)
        // ===============================
        if ($has_motor == "Yes" && !empty($plate_number)) {

            $plate = strtoupper($plate_number);

            $sql_motor = "
                INSERT INTO motors (
                    plate_number,
                    motor_type,
                    model_name
                )
                VALUES (?, ?, ?)
            ";

            $stmt_motor = $conn->prepare($sql_motor);
            $stmt_motor->bind_param(
                "sss",
                $plate,
                $motor_type,
                $model_name
            );
            $stmt_motor->execute();
        }

        // ===============================
        // GENERATE QR CODE
        // ===============================
        $qr_folder = "qr_codes/";

        if (!file_exists($qr_folder)) {
            mkdir($qr_folder, 0777, true);
        }

        // Use environment variable or construct URL dynamically instead of hardcoded IP
        $base_url = getenv('APP_BASE_URL') ?: ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $qr_data = $scheme . '://' . $base_url . dirname($_SERVER['PHP_SELF']) . '/visitor_details.php?id=' . $visitor_id;

        $qr_file = $qr_folder . time() . "_visitor.png";

        QRcode::png(
            $qr_data,
            $qr_file,
            QR_ECLEVEL_L,
            5
        );

        // SAVE QR TO DATABASE
        $stmt_qr = $conn->prepare(
            "UPDATE visitors SET qr_code = ? WHERE id = ?"
        );
        $stmt_qr->bind_param(
            "si",
            $qr_file,
            $visitor_id
        );
        $stmt_qr->execute();

        // ===============================
        // SAVE VISITOR PHOTO (IF PROVIDED)
        // ===============================
        if (!empty($_POST['visitor_photo'])) {
            $data = $_POST['visitor_photo'];
            if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
                $data = substr($data, strpos($data, ',') + 1);
                $data = base64_decode($data);
                $ext = $type[1];

                // Validate file type (only allow common image formats)
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                if (!in_array(strtolower($ext), $allowed_extensions, true)) {
                    error_log("Invalid image type uploaded: " . $ext);
                } else {
                    // Validate file size (max 5MB)
                    $file_size = strlen($data);
                    $max_size = 5 * 1024 * 1024; // 5MB in bytes
                    if ($file_size > $max_size) {
                        error_log("Image file too large: " . $file_size . " bytes");
                    } else {
                        // Validate that it's actually an image
                        if (imagecreatefromstring($data) === false) {
                            error_log("Invalid image data detected");
                        } else {
                            $photo_folder = 'visitor_photos/';
                            if (!file_exists($photo_folder)) {
                                mkdir($photo_folder, 0777, true);
                            }

                            $photo_file = $photo_folder . time() . "_visitor_{$visitor_id}." . $ext;
                            file_put_contents($photo_file, $data);

                            $stmt_photo = $conn->prepare("UPDATE visitors SET visitor_photo = ? WHERE id = ?");
                            $stmt_photo->bind_param("si", $photo_file, $visitor_id);
                            $stmt_photo->execute();
                        }
                    }
                }
            }
        }

        // ===============================
        // GET HOST DETAILS
        // ===============================
        $stmt_host = $conn->prepare(
            "SELECT name, email, phone_number
             FROM hosts
             WHERE id = ?"
        );

        $stmt_host->bind_param("i", $host_id);
        $stmt_host->execute();

        $host = $stmt_host
            ->get_result()
            ->fetch_assoc();

        // ===============================
        // SEND sms
        // ===============================
        $host_phone = $host['phone_number'] ?? '';

        $message = "Mgeni amekuja:\n"
                 . "Name: $full_name\n"
                 . "Purpose: $purpose\n"
                 . "Time: " . date('H:i');

        if ($host_phone !== '') {
            $smsSent = false;
            if (function_exists('sendSMS')) {
                try {
                    sendSMS($host_phone, $message);
                    $smsSent = true;
                } catch (Throwable $e) {
                    error_log('SMS send failed: ' . $e->getMessage());
                }
            }

            if (!$smsSent) {
                error_log('SMS notification skipped for host phone: ' . $host_phone);
            }
        }
        // ===============================
        // SEND EMAIL
        // ===============================
        if (!empty($host['email'])) {

            $mail = new PHPMailer(true);

            try {

                $mail->isSMTP();
                $mail->Host = 'sandbox.smtp.mailtrap.io';
                $mail->SMTPAuth = true;
                $mail->Username = 'YOUR_USERNAME';
                $mail->Password = 'YOUR_PASSWORD';
                $mail->Port = 2525;

                $mail->setFrom(
                    'mapokezi@system.com',
                    'Visitor Management System'
                );

                $mail->addAddress(
                    $host['email'],
                    $host['name']
                );

                $mail->isHTML(true);

                $mail->Subject =
                    "Mgeni amefika: $full_name";

                $mail->Body = "
                    <h3>Habari {$host['name']}</h3>
                    <p>Mgeni amefika mapokezi.</p>

                    <b>Name:</b> $full_name <br>
                    <b>Purpose:</b> $purpose <br>
                    <b>Time:</b> " . date('H:i') . "
                ";

                $mail->send();

            } catch (Exception $e) {
                error_log(
                    "PHPMailer Error: "
                    . $mail->ErrorInfo
                );
            }
        }

        // ===============================
        // Respond: JSON for AJAX or redirect for normal POST
        // ===============================
        $ts = time();

        // fetch full visitor record with host name
        $stmt_v = $conn->prepare(
            "SELECT v.*, h.name AS host_name
             FROM visitors v
             LEFT JOIN hosts h ON v.host_id = h.id
             WHERE v.id = ?"
        );
        $stmt_v->bind_param("i", $visitor_id);
        $stmt_v->execute();
        $visitor = $stmt_v->get_result()->fetch_assoc();

        // prepare data for response
        $resp = [
            'id' => (int)$visitor['id'],
            'full_name' => $visitor['full_name'] ?? '',
            'phone_number' => $visitor['phone_number'] ?? '',
            'id_type' => $visitor['id_type'] ?? '',
            'id_number' => $visitor['id_number'] ?? '',
            'host_name' => $visitor['host_name'] ?? '',
            'motor_type' => $visitor['motor_type'] ?? '',
            'plate_number' => $visitor['plate_number'] ?? '',
            'model_name' => $visitor['model_name'] ?? '',
            'check_in_time_display' => date('d/m/Y h:i A', strtotime($visitor['check_in_time'])),
            'visitor_photo' => !empty($visitor['visitor_photo']) ? $visitor['visitor_photo'] . '?t=' . $ts : '',
            'qr_code' => $visitor['qr_code'] ?? ''
        ];

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'visitor' => $resp]);
            exit();
        }

        // fallback: redirect for non-AJAX
        header(
            "Location: view_visitors.php?success=registered&new_id={$visitor_id}&ts={$ts}"
        );
        exit();

    } else {
        echo "Failed to register visitor.";
    }
}
?>