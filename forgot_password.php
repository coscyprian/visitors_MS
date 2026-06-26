<?php
require_once 'config/db_config.php';

$message = "";
$resetLink = "";

if (isset($_POST['submit'])) {
    $email = trim($_POST['email'] ?? '');

    if ($email !== '') {
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        if ($checkStmt) {
            $checkStmt->bind_param('s', $email);
            $checkStmt->execute();
            $userRs = $checkStmt->get_result();
            $exists = $userRs && $userRs->num_rows > 0;
            $checkStmt->close();

            if ($exists) {
                $token = bin2hex(random_bytes(50));

                $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE email = ?");
                if ($updateStmt) {
                    $updateStmt->bind_param('ss', $token, $email);
                    $updateStmt->execute();
                    $updated = $updateStmt->affected_rows > 0;
                    $updateStmt->close();

                    if ($updated) {
                        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                        $basePath = rtrim(dirname($_SERVER['PHP_SELF'] ?? '/'), '/\\');
                        $resetLink = $scheme . '://' . $host . $basePath . '/reset_password.php?token=' . urlencode($token);
                    }
                }
            }
        }
    }

    if ($resetLink !== '') {
        $safeLink = htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8');
        $message = "Link ya kubadili password ipo hapa: <a href=\"{$safeLink}\">Bofya hapa ubadili password</a>";
    } else {
        $message = "Ikiwa email ipo kwenye mfumo, link ya kubadili password imetumwa.";
    }
}
?>

<h3>Forgot Password</h3>

<form method="POST">
    <input type="email" name="email" placeholder="Enter email" required>
    <button type="submit" name="submit">Send Reset Link</button>
</form>

<p><?= $message ?></p>