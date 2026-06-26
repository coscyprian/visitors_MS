<?php
require_once 'config/db_config.php';
require_once 'includes/departments.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $current_page != 'login.php') {
    header("Location: login.php");
    exit();
}

$role = strtolower(trim($_SESSION['role'] ?? 'staff'));
if (!in_array($role, ['admin', 'administrator'], true)) {
    echo '<div class="container py-5"><div class="alert alert-danger">Access denied. You need administrator rights.</div></div>';
    exit();
}

if ($conn->query("SHOW COLUMNS FROM users LIKE 'role'")->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'Receptionist'");
}
if ($conn->query("SHOW COLUMNS FROM users LIKE 'email'")->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN email VARCHAR(150) DEFAULT NULL");
}
// Ensure users table has department column
if ($conn->query("SHOW COLUMNS FROM users LIKE 'department'")->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN department VARCHAR(100) DEFAULT NULL");
}
// Persist generated duties for each user
if ($conn->query("SHOW COLUMNS FROM users LIKE 'user_duties'")->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN user_duties TEXT DEFAULT NULL");
}

// Harmonize old saved duty text so legacy records no longer show "kufuatilia" wording.
$conn->query("UPDATE users
    SET user_duties = REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(user_duties, 'Kupokea na kufuatilia wageni', 'Kupokea wageni'),
                'Kupokea na kufatilia wageni', 'Kupokea wageni'
            ),
            'Kufuatilia wageni wote na usalama wa mfumo.', 'Kusimamia wageni wote na usalama wa mfumo.'
        ),
        'kufutilia', ''
    )
    WHERE user_duties LIKE '%fuatilia%'
       OR user_duties LIKE '%fatilia%'
       OR user_duties LIKE '%futilia%'");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function normalizeManagedRole($role) {
    $value = strtolower(trim((string)$role));
    if (in_array($value, ['admin', 'administrator'], true)) {
        return 'Admin';
    }
    if (in_array($value, ['security', 'gate security', 'gate_security', 'gate-security'], true)) {
        return 'Security';
    }
    return 'Receptionist';
}

function buildUserDuties($role, $department) {
    $normalizedRole = strtolower(trim((string)$role));
    $dept = trim((string)$department);

    if ($normalizedRole === 'admin') {
        return implode("\n", [
            'Kutengeneza, kuhariri, na kufuta users.',
            'Kusimamia idara, dashboards, na reports.',
            'Kusimamia wageni wote na usalama wa mfumo.',
            'Kuweka sera na ruhusa za watumiaji.'
        ]);
    }

    if ($normalizedRole === 'security') {
        return implode("\n", [
            'Kusajili wageni wanaoingia getini.',
            'Kudhibiti check-in/check-out ya wageni.',
            'Kutumia Reception Registration Dashboard kwa usajili wa wageni.',
            'Kuthibitisha taarifa za wageni na magari wakati wa kuingia.'
        ]);
    }

    $deptLabel = $dept !== '' ? " kwa idara ya {$dept}" : '';
    return implode("\n", [
        'Kupokea wageni wa idara husika' . $deptLabel . '.',
        'Kuona taarifa mpya za wageni wa idara yake.',
        'Kuangalia Department Dashboard ya idara yake.',
        'Kushirikiana na mapokezi kwenye taarifa za wageni.'
    ]);
}

// Ensure departments source exists and load active departments for assignment
ensureDepartmentsTableExists($conn);
$departments = [];
$departmentRows = getDepartments($conn, true);
foreach ($departmentRows as $d) {
    $name = trim($d['name'] ?? '');
    if ($name !== '') {
        $departments[] = $name;
    }
}

$message = '';
$error = '';

if (isset($_GET['success'])) {
    if ($_GET['success'] === 'create') {
        $message = 'New user account created successfully.';
    } elseif ($_GET['success'] === 'update') {
        $message = 'User account updated successfully.';
    } elseif ($_GET['success'] === 'delete') {
        $message = 'User account deleted successfully.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (!is_string($postedCsrf) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
        $error = 'Ombi limekataliwa. Tafadhali refresh ukurasa ujaribu tena.';
    }

    $action = $_POST['action'] ?? '';

    if ($error === '' && $action === 'create') {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $department_id = getDepartmentIdByName($conn, $department);
        $password = trim($_POST['password'] ?? '');
        $role = normalizeManagedRole($_POST['role'] ?? 'Receptionist');
        $user_duties = buildUserDuties($role, $department);

        if ($username === '' || $full_name === '' || $password === '') {
            $error = 'Please fill in username, full name and password.';
        } elseif ($department !== '' && $department_id === null) {
            $error = 'Idara uliyochagua haipo kwenye mfumo.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, email, role, department, department_id, user_duties) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('ssssssis', $username, $hashed, $full_name, $email, $role, $department, $department_id, $user_duties);
            try {
                $stmt->execute();
                $stmt->close();
                header('Location: users.php?success=create');
                exit();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $error = 'Username already exists. Please choose a different username.';
                } else {
                    $error = 'Failed to create account: ' . $e->getMessage();
                }
                $stmt->close();
            }
        }
    }

    if ($error === '' && $action === 'update') {
        $user_id = intval($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role = normalizeManagedRole($_POST['role'] ?? 'Receptionist');
        $department = trim($_POST['department'] ?? '');
        $department_id = getDepartmentIdByName($conn, $department);
        $password = trim($_POST['password'] ?? '');
        $user_duties = buildUserDuties($role, $department);

        if ($user_id <= 0 || $username === '' || $full_name === '') {
            $error = 'Username and full name are required.';
        } elseif ($department !== '' && $department_id === null) {
            $error = 'Idara uliyochagua haipo kwenye mfumo.';
        } else {
            if ($password !== '') {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, department = ?, department_id = ?, user_duties = ?, password = ? WHERE id = ?");
                $stmt->bind_param('sssssissi', $username, $full_name, $email, $role, $department, $department_id, $user_duties, $hashed, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, department = ?, department_id = ?, user_duties = ? WHERE id = ?");
                $stmt->bind_param('sssssisi', $username, $full_name, $email, $role, $department, $department_id, $user_duties, $user_id);
            }
            try {
                $stmt->execute();
                $stmt->close();
                header('Location: users.php?success=update');
                exit();
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $error = 'Username already exists. Please choose a different username.';
                } else {
                    $error = 'Failed to update account: ' . $e->getMessage();
                }
                $stmt->close();
            }
        }
    }

    if ($error === '' && $action === 'delete') {
        $user_id = intval($_POST['user_id'] ?? 0);
        if ($user_id > 0) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param('i', $user_id);
            if ($stmt->execute()) {
                $stmt->close();
                header('Location: users.php?success=delete');
                exit();
            } else {
                $error = 'Unable to delete user: ' . $stmt->error;
                $stmt->close();
            }
        }
    }
}

$users = [];
$result = $conn->query("SELECT id, username, full_name, email, role, department, user_duties FROM users ORDER BY id DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">User Management</h2>
            <p class="text-muted small mb-0">Create, edit and assign roles for Admin, Security and Receptionist accounts.</p>
        </div>
        <button class="btn btn-primary" onclick="openUserModal()">
            <i class="fas fa-user-plus me-2"></i>New User
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($message); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Department</th>
                            <th>Kazi za User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) === 0): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No user accounts found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['department'] ?? '-'); ?></td>
                                    <td style="min-width: 280px; white-space: normal;"><?php
                                        $duties = trim((string)($user['user_duties'] ?? ''));
                                        if ($duties === '') {
                                            $duties = buildUserDuties((string)($user['role'] ?? 'Receptionist'), (string)($user['department'] ?? ''));
                                        }
                                        echo nl2br(htmlspecialchars($duties));
                                    ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-2" onclick='editUser(<?php echo json_encode($user); ?>)'>
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this account?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="userModalLabel">New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="user_id" id="user_id" value="0">
          <input type="hidden" name="action" id="user_action" value="create">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" class="form-control" name="username" id="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" name="full_name" id="full_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" name="email" id="email">
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
                        <select name="role" id="role" class="form-select" required onchange="updateRoleDutyPreview()">
                <option value="Admin">Admin</option>
                                <option value="Security">Security</option>
                <option value="Receptionist">Receptionist</option>
            </select>
          </div>
                    <div class="mb-3">
                        <label class="form-label">Department</label>
                                                <select name="department" id="department" class="form-select" onchange="updateRoleDutyPreview()">
                                <option value="">None</option>
                                <?php foreach ($departments as $d): ?>
                                        <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                                <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kazi za User</label>
                        <div class="alert alert-info py-2 mb-0">
                                <div id="roleDutyTitle" class="fw-semibold mb-1">Majukumu ya mtumiaji</div>
                                <ul id="roleDutyList" class="mb-0 ps-3"></ul>
                        </div>
                    </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" name="password" id="password" placeholder="Leave blank to keep current password">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="submitUserBtn">Save User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const userModal = new bootstrap.Modal(document.getElementById('userModal'));

function getRoleDutyItems(role, department) {
    const normalizedRole = String(role || '').toLowerCase();
    const dept = String(department || '').trim();

    if (normalizedRole === 'admin') {
        return {
            title: 'Majukumu ya Admin',
            items: [
                'Kutengeneza, kuhariri, na kufuta users.',
                'Kusimamia idara, dashboards, na reports.',
                'Kusimamia wageni wote na usalama wa mfumo.',
                'Kuweka sera na ruhusa za watumiaji.'
            ]
        };
    }

    if (normalizedRole === 'security') {
        return {
            title: 'Majukumu ya Security',
            items: [
                'Kusajili wageni wanaoingia getini.',
                'Kudhibiti check-in/check-out ya wageni.',
                'Kutumia Reception Registration Dashboard kwa usajili wa wageni.',
                'Kuthibitisha taarifa za wageni na magari wakati wa kuingia.'
            ]
        };
    }

    const deptLabel = dept !== '' ? (' (' + dept + ')') : '';
    return {
        title: 'Majukumu ya Receptionist wa Idara' + deptLabel,
        items: [
            'Kupokea wageni wa idara husika.',
            'Kuona taarifa mpya za wageni wa idara yake.',
            'Kuangalia Department Dashboard ya idara yake.',
            'Kushirikiana na mapokezi kwenye taarifa za wageni.'
        ]
    };
}

function updateRoleDutyPreview() {
    const role = document.getElementById('role').value;
    const department = document.getElementById('department').value;
    const data = getRoleDutyItems(role, department);

    document.getElementById('roleDutyTitle').innerText = data.title;

    const list = document.getElementById('roleDutyList');
    list.innerHTML = '';
    data.items.forEach((item) => {
        const li = document.createElement('li');
        li.innerText = item;
        list.appendChild(li);
    });
}

function openUserModal() {
    document.getElementById('userModalLabel').innerText = 'Create New User';
    document.getElementById('user_action').value = 'create';
    document.getElementById('user_id').value = '0';
    document.getElementById('username').value = '';
    document.getElementById('full_name').value = '';
    document.getElementById('email').value = '';
    document.getElementById('role').value = 'Receptionist';
    document.getElementById('department').value = '';
    document.getElementById('password').placeholder = 'Enter password';
    document.getElementById('password').value = '';
    document.getElementById('submitUserBtn').innerText = 'Create User';
    updateRoleDutyPreview();
    userModal.show();
}
function editUser(user) {
    document.getElementById('userModalLabel').innerText = 'Edit User';
    document.getElementById('user_action').value = 'update';
    document.getElementById('user_id').value = user.id;
    document.getElementById('username').value = user.username;
    document.getElementById('full_name').value = user.full_name;
    document.getElementById('email').value = user.email;
    const userRole = String(user.role || '').toLowerCase();
    if (userRole === 'admin' || userRole === 'administrator') {
        document.getElementById('role').value = 'Admin';
    } else if (userRole === 'security' || userRole === 'gate security' || userRole === 'gate_security' || userRole === 'gate-security') {
        document.getElementById('role').value = 'Security';
    } else {
        document.getElementById('role').value = 'Receptionist';
    }
    document.getElementById('department').value = user.department ?? '';
    document.getElementById('password').placeholder = 'Leave blank to keep password';
    document.getElementById('password').value = '';
    document.getElementById('submitUserBtn').innerText = 'Save Changes';
    updateRoleDutyPreview();
    userModal.show();
}

document.addEventListener('DOMContentLoaded', updateRoleDutyPreview);
</script>
