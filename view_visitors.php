<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/role_helpers.php';

$currentRole = normalizeUserRole($_SESSION['role'] ?? 'Receptionist');
if (!isAdminRole($currentRole)) {
    include 'includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Access denied. Visitors page is only available to administrators.</div></div>';
    exit();
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 1. LAZIMISHA MUDA WA TANZANIA
date_default_timezone_set('Africa/Nairobi');

require_once 'config/db_config.php';
$conn->query("SET time_zone = '+03:00'");

$id = 0;
if (isset($_GET['checkout_id'])) {
    $id = intval($_GET['checkout_id']);
}

if ($id > 0) {
    // Validate CSRF token for checkout
    $csrf_token = $_GET['csrf_token'] ?? '';
    if (!is_string($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die('Invalid request. Please try again.');
    }
    
    $muda_sasa = date('Y-m-d H:i:s');
    $stmt = $conn->prepare(
        "UPDATE visitors SET status='Left', check_out_time=? WHERE id=?"
    );
    $stmt->bind_param("si", $muda_sasa, $id);
    if ($stmt->execute()) {
        header("Location: view_visitors.php?success=checkedout");
        exit();
    }
}

include 'includes/header.php';
?>
<?php if (isset($_GET['success']) && $_GET['success'] == 'deleted'): ?>

<div class="container mt-3">
    <div class="alert alert-danger alert-dismissible fade show">

        Visitor deleted successfully.

        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>

    </div>
</div>

<?php endif; ?>
<?php if (isset($_GET['success']) && $_GET['success'] == 'registered'): ?>

<div class="container mt-3">
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas fa-check-circle"></i>
        Successfully registered visitor.

        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>

<?php endif; ?>




<?php
// 3. SEARCH LOGIC
$search = isset($_GET['search']) 
    ? trim($conn->real_escape_string($_GET['search'])) 
    : "";

$active_tab = isset($_GET['active_tab']) 
    ? $_GET['active_tab'] 
    : 'inside';

$new_id = isset($_GET['new_id']) ? intval($_GET['new_id']) : 0;
$ts_q = isset($_GET['ts']) ? intval($_GET['ts']) : time();
?>

<!-- CSS NA FONTS -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    :root { --primary: #4318ff; --dark-header: #1b1b21; --bg-body: #f4f7fe; }
    body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: var(--bg-body); color: #2b3674; }
    .glass-card { background: #fff; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); padding: 25px; border: none; margin-bottom: 20px; }
    .nav-pills-custom .nav-link { color: #a3aed0; font-weight: 600; padding: 12px 25px; border-radius: 12px; border: none; }
    .nav-pills-custom .nav-link.active { background-color: var(--primary) !important; color: white !important; }
    .custom-table { width: 100%; border-collapse: collapse; }
    .custom-table thead th { background-color: var(--dark-header); color: #fff; padding: 15px; border-right: 1px solid #444; }
    .custom-table tbody td { padding: 15px; border-bottom: 1px solid #eee; border-right: 1px solid #1b1b21; }
    .search-box { background: #f4f7fe; border-radius: 12px; padding: 10px 15px; border: 1px solid #e0e5f2; }
    #user_alert { display: none; font-size: 13px; font-weight: bold; margin-top: 8px; padding: 8px; border-radius: 8px; }
</style>

<div class="container py-5">
    <div class="row align-items-center mb-4">
        <div class="col-md-7">
            <h2 class="fw-bold">Visitor Management.</h2>
            <p class="text-muted small">Real-Time (Tanzania): <span class="text-primary fw-bold"><?= date('d M, Y | H:i A') ?></span></p>
        </div>
    </div>

    <div class="glass-card">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <ul class="nav nav-pills nav-pills-custom">
                <li class="nav-item">
                    <button class="nav-link <?= ($active_tab == 'inside') ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#inside" onclick="updateTab('inside')">NDANI</button>
                </li>
                <li class="nav-item ms-2">
                    <button class="nav-link <?= ($active_tab == 'history') ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#history" onclick="updateTab('history')">HISTORIA</button>
                </li>
            </ul>
            <form method="GET" class="d-flex">
                <input type="hidden" name="active_tab" id="active_tab_input" value="<?= $active_tab ?>">
                <input type="text" name="search" class="form-control search-box" placeholder="Tafuta..." value="<?= htmlspecialchars($search) ?>">
            </form>
        </div>

        <div class="tab-content">
            <!-- TAB 1: NDANI -->
            <div class="tab-pane fade <?= ($active_tab == 'inside') ? 'show active' : '' ?>" id="inside">
    <div class="table-responsive">
        <table class="custom-table">
            <thead>
                <tr>
                    <th>Visitor</th>
                    <th>Kitambulisho</th>
                    <th>Department</th>
                    <th>Check In</th>
                    <th>Check Out</th>
                </tr>
            </thead>

            <tbody>
            <?php
            $sql_in = "SELECT v.*
           FROM visitors v
           WHERE v.status IN ('Inside', 'Checked In')";

if (!empty($search)) {
    $stmt_search = $conn->prepare("SELECT v.* FROM visitors v WHERE v.status IN ('Inside', 'Checked In') AND v.full_name LIKE ? ORDER BY v.id DESC");
    $search_param = '%' . $search . '%';
    $stmt_search->bind_param('s', $search_param);
    $stmt_search->execute();
    $res_in = $stmt_search->get_result();
} else {
    $sql_in .= " ORDER BY v.id DESC";
    $res_in = $conn->query($sql_in);
}

            while($row = $res_in->fetch_assoc()):
                $is_new = ($new_id > 0 && $row['id'] == $new_id);
            ?>
                <tr id="visitor_row_<?= $row['id'] ?>" <?= ($is_new) ? 'style="background:#fff7e6;"' : '' ?>>
                    <td>
                        <strong><?= htmlspecialchars($row['full_name']) ?></strong><br>
                        <small><?= htmlspecialchars($row['phone_number']) ?></small>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['id_type']) ?><br>
                        <small><?= htmlspecialchars($row['id_number']) ?></small>
                    </td>

                    <td>
                        <?= htmlspecialchars($row['department'] ?? '---') ?>
                    </td>

                    <td>
                        <?= date('d/m/Y h:i A', strtotime($row['check_in_time'])) ?>
                    </td>

                    <td>
                        <?= !empty($row['check_out_time']) ? date('d/m/Y h:i A', strtotime($row['check_out_time'])) : '---' ?>
                    </td>

                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($new_id > 0): ?>
    <script>
        // scroll to and briefly highlight new visitor
        window.addEventListener('load', function () {
            var el = document.getElementById('visitor_row_<?= $new_id ?>');
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.style.transition = 'box-shadow 0.4s ease';
                el.style.boxShadow = '0 0 0 4px rgba(255,200,0,0.6)';
                setTimeout(function () { el.style.boxShadow = ''; el.style.background = ''; }, 3000);
            }
        });
    </script>
<?php endif; ?>

            <!-- TAB 2: HISTORIA -->
            <div class="tab-pane fade <?= ($active_tab == 'history') ? 'show active' : '' ?>" id="history">
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead><tr><th>Visitor</th><th>kitambulisho</th><th>Department</th><th>Check In</th><th>Check Out</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php
                            $sql_h = "SELECT v.* FROM visitors v WHERE (v.status = 'Left' OR v.check_out_time IS NOT NULL)";
                            $search = "";
if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}
if (!empty($search)) {
    $stmt_search_h = $conn->prepare("SELECT v.* FROM visitors v WHERE (v.status = 'Left' OR v.check_out_time IS NOT NULL) AND v.full_name LIKE ? ORDER BY v.id DESC");
    $search_param_h = '%' . $search . '%';
    $stmt_search_h->bind_param('s', $search_param_h);
    $stmt_search_h->execute();
    $res_h = $stmt_search_h->get_result();
} else {
                            $sql_h .= " ORDER BY v.id DESC";
                            $res_h = $conn->query($sql_h);
}
                            while($row = $res_h->fetch_assoc()):
                                $out_time = ($row['check_out_time']) ? date('d/m H:i A', strtotime($row['check_out_time'])) : "---";
                            ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($row['full_name']) ?></strong><br><small><?= htmlspecialchars($row['phone_number']) ?></small></td>
                                    <td>
    <?= htmlspecialchars($row['id_type']) ?><br>
    <small><?= htmlspecialchars($row['id_number']) ?></small>
</td>
                                    <td><?= htmlspecialchars($row['department'] ?? '---') ?></td>
                                    <td class="small text-success fw-bold"><?= date('d/m H:i A', strtotime($row['check_in_time'])) ?></td>
                                    <td class="small text-danger fw-bold"><?= $out_time ?></td>
                                    <td><?= ($row['status'] == 'Inside') ? '<span class="badge bg-primary">NDANI</span>' : '<span class="badge bg-secondary">ALITOKA</span>' ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function updateTab(tab) {
    const activeTabInput = document.getElementById('active_tab_input');
    if (activeTabInput) {
        activeTabInput.value = tab;
    }
}
</script>
