<?php
require_once 'config/db_config.php';

$token = $_GET['token'] ?? '';
$message = "";
$isValidToken = false;

$token = trim($token);
if ($token !== '') {
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND token_expiry > NOW() LIMIT 1");
    if ($checkStmt) {
        $checkStmt->bind_param('s', $token);
        $checkStmt->execute();
        $checkRs = $checkStmt->get_result();
        $isValidToken = $checkRs && $checkRs->num_rows > 0;
        $checkStmt->close();
    }
}

if (!$isValidToken) {
    $message = "Link si sahihi au muda wake umeisha. <a href=\"forgot_password.php\">Omba link mpya</a>.";
}

if ($isValidToken && isset($_POST['reset'])) {
    $plainPassword = trim($_POST['password'] ?? '');
    if (strlen($plainPassword) < 8) {
        $message = "Password lazima iwe na angalau herufi 8.";
    } else {
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);

        $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, token_expiry = NULL WHERE reset_token = ? AND token_expiry > NOW()");
        if ($updateStmt) {
            $updateStmt->bind_param('ss', $password, $token);
            $updateStmt->execute();
            if ($updateStmt->affected_rows > 0) {
                $message = "Password reset successful. Login now.";
            } else {
                $message = "Invalid or expired token";
            }
            $updateStmt->close();
        } else {
            $message = "Tatizo limetokea wakati wa kubadilisha password.";
        }
    }
}
?>

<h3>Reset Password</h3>

<?php if ($isValidToken): ?>
<form method="POST">
    <input type="password" name="password" placeholder="New password" required>
    <button type="submit" name="reset">Reset</button>
</form>
<?php endif; ?>

<p><?= $message ?></p>