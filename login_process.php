
<?php

session_start();
require_once 'config/db_config.php';
require_once 'includes/role_helpers.php';

// Rate limiting: Track failed login attempts
$login_attempts_key = 'login_attempts_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$max_attempts = 5;
$lockout_time = 900; // 15 minutes in seconds

if (isset($_SESSION[$login_attempts_key])) {
    $attempts = $_SESSION[$login_attempts_key];
    if ($attempts['count'] >= $max_attempts && (time() - $attempts['first_attempt']) < $lockout_time) {
        $remaining_time = $lockout_time - (time() - $attempts['first_attempt']);
        echo "Too many failed login attempts. Please try again in " . ceil($remaining_time / 60) . " minutes.";
        exit();
    } elseif ((time() - $attempts['first_attempt']) >= $lockout_time) {
        // Reset attempts after lockout period
        unset($_SESSION[$login_attempts_key]);
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $conn->prepare("
        SELECT * FROM users
        WHERE username = ?
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        $user = $result->fetch_assoc();

        $hasAdmin = $conn->query("SELECT 1 FROM users WHERE role = 'Admin' LIMIT 1")->num_rows > 0;

        if (password_verify($password, $user['password'])) {
            if (empty($user['role'])) {
                $user['role'] = 'Receptionist';
                $updateRole = $conn->prepare("UPDATE users SET role = 'Receptionist' WHERE id = ?");
                $updateRole->bind_param('i', $user['id']);
                $updateRole->execute();
                $updateRole->close();
            }

            session_regenerate_id(true);

            $displayName = trim((string)($user['full_name'] ?? $user['username'] ?? 'Mtumiaji'));
            $departmentName = trim((string)($user['department'] ?? ''));
            $departmentShort = '';
            if ($departmentName !== '') {
                $parts = preg_split('/\s+/', $departmentName) ?: [];
                foreach ($parts as $part) {
                    $part = trim((string)$part);
                    if ($part !== '') {
                        $departmentShort .= strtoupper(substr($part, 0, 1));
                    }
                }
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_name'] = $displayName;
            $_SESSION['role'] = normalizeUserRole($user['role'] ?? 'Receptionist');
            $_SESSION['department'] = $departmentName;

            $_SESSION['login_welcome'] = 'Karibu, ' . $displayName . '!';

            // Clear login attempts on successful login
            unset($_SESSION[$login_attempts_key]);

            header('Location: ' . getRoleHomePage($_SESSION['role']));
            exit();

        } else {
            // Track failed login attempt
            if (!isset($_SESSION[$login_attempts_key])) {
                $_SESSION[$login_attempts_key] = [
                    'count' => 1,
                    'first_attempt' => time()
                ];
            } else {
                $_SESSION[$login_attempts_key]['count']++;
            }
            echo "Wrong password";
        }

    } else {
        // Track failed login attempt
        if (!isset($_SESSION[$login_attempts_key])) {
            $_SESSION[$login_attempts_key] = [
                'count' => 1,
                'first_attempt' => time()
            ];
        } else {
            $_SESSION[$login_attempts_key]['count']++;
        }
        echo "User not found";
    }

}
?>

