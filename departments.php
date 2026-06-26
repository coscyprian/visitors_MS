<?php
require_once 'config/db_config.php';
require_once 'includes/role_helpers.php';
require_once 'includes/departments.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$role = normalizeUserRole($_SESSION['role'] ?? 'Receptionist');
if (!isAdminRole($role)) {
    echo '<div class="container py-5"><div class="alert alert-danger">Access denied. Administrator account required.</div></div>';
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

include 'includes/header.php';

ensureDepartmentsTableExists($conn);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $postedCsrf = $_POST['csrf_token'] ?? '';
    if (!is_string($postedCsrf) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
        $error = 'Ombi limekataliwa. Tafadhali refresh ukurasa ujaribu tena.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        if ($name === '' || $code === '') {
            $error = 'Jaza jina na code ya idara ili kuongeza.';
        } else {
            [$ok, $msg] = addDepartment($conn, $name, $code, $status);
            if ($ok) {
                $message = $msg;
            } else {
                $error = $msg;
            }
        }
    }
    if ($action === 'update') {
        $dept_id = intval($_POST['department_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $status = $_POST['status'] ?? 'Active';
        if ($dept_id <= 0 || $name === '' || $code === '') {
            $error = 'Taarifa za kuhariri idara hazijakamilika.';
        } else {
            [$ok, $msg] = updateDepartment($conn, $dept_id, $name, $code, $status);
            if ($ok) {
                $message = $msg;
            } else {
                $error = $msg;
            }
        }
    }
    if ($action === 'delete') {
        $dept_id = intval($_POST['department_id'] ?? 0);
        if ($dept_id > 0) {
            [$ok, $msg] = deleteDepartment($conn, $dept_id);
            if ($ok) {
                $message = $msg;
            } else {
                $error = $msg;
            }
        }
    }
    }
}

$departments = getDepartments($conn, false);
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 gap-3">
        <div>
            <h2 class="fw-bold mb-1">Department Management</h2>
            <p class="text-muted small mb-0">Add, edit and remove department names for visitor assignments.</p>
        </div>
        <button class="btn btn-primary" onclick="openDepartmentModal()">
            <i class="fas fa-building me-2"></i>New Department
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
                            <th>Name</th>
                            <th>Code</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($departments) === 0): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">No departments defined yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($dept['id']); ?></td>
                                    <td><?php echo htmlspecialchars($dept['name']); ?></td>
                                    <td><?php echo htmlspecialchars($dept['code']); ?></td>
                                    <td><?php echo htmlspecialchars($dept['status']); ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary me-2" onclick='editDepartment(<?php echo json_encode($dept); ?>)'>
                                            <i class="fas fa-pen"></i> Edit
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this department?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="department_id" value="<?php echo htmlspecialchars($dept['id']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
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

<div class="modal fade" id="deptModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deptModalLabel">New Department</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="department_id" id="department_id" value="0">
          <input type="hidden" name="action" id="department_action" value="create">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
          <div class="mb-3">
            <label class="form-label">Department Name</label>
            <input type="text" class="form-control" name="name" id="department_name" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Department Code</label>
            <input type="text" class="form-control" name="code" id="department_code" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" name="status" id="department_status">
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="saveDepartmentBtn">Save Department</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const deptModal = new bootstrap.Modal(document.getElementById('deptModal'));
function openDepartmentModal() {
    document.getElementById('deptModalLabel').innerText = 'Create Department';
    document.getElementById('department_action').value = 'create';
    document.getElementById('department_id').value = '0';
    document.getElementById('department_name').value = '';
    document.getElementById('department_code').value = '';
    document.getElementById('department_status').value = 'Active';
    document.getElementById('saveDepartmentBtn').innerText = 'Save Department';
    deptModal.show();
}
function editDepartment(dept) {
    document.getElementById('deptModalLabel').innerText = 'Edit Department';
    document.getElementById('department_action').value = 'update';
    document.getElementById('department_id').value = dept.id;
    document.getElementById('department_name').value = dept.name;
    document.getElementById('department_code').value = dept.code;
    document.getElementById('department_status').value = dept.status;
    document.getElementById('saveDepartmentBtn').innerText = 'Update Department';
    deptModal.show();
}
</script>
