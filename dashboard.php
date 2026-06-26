<?php
session_start();
require_once 'config/db_config.php';
require_once 'includes/departments.php';

ensureDepartmentsTableExists($conn);

$role = strtolower(trim($_SESSION['role'] ?? 'staff'));
if (!in_array($role, ['admin', 'administrator'], true)) {
    include 'includes/header.php';
    echo '<div class="container py-5"><div class="alert alert-danger">Access denied. Admin dashboard is only available to administrators.</div></div>';
    exit();
}

// ========================
// DASHBOARD STATS
// ========================

$todayDate = date('Y-m-d');

$total_all = $conn->query("SELECT COUNT(*) as total FROM visitors")->fetch_assoc()['total'];

$stmtToday = $conn->prepare("SELECT COUNT(*) as total FROM visitors WHERE DATE(check_in_time) = ?");
$stmtToday->bind_param('s', $todayDate);
$stmtToday->execute();
$total_today = ($stmtToday->get_result()->fetch_assoc()['total'] ?? 0);
$stmtToday->close();

$total_inside = $conn->query("SELECT COUNT(*) as total FROM visitors WHERE status = 'Inside'")->fetch_assoc()['total'];

$total_departments = $conn->query("SELECT COUNT(*) as total FROM departments WHERE status = 'Active'")->fetch_assoc()['total'];

$total_vehicles_inside = $conn->query("SELECT COUNT(*) as total FROM visitors WHERE status = 'Inside' AND (has_motor = 'Yes' OR COALESCE(plate_number, '') <> '')")->fetch_assoc()['total'];

$dept_visitors = $conn->query(" 
    SELECT COALESCE(NULLIF(TRIM(d.name), ''), NULLIF(TRIM(v.department), ''), 'Unknown') AS department, COUNT(*) total
    FROM visitors v
    LEFT JOIN departments d ON v.department_id = d.id
    WHERE DATE(v.check_in_time) = CURDATE()
    GROUP BY COALESCE(NULLIF(TRIM(d.name), ''), NULLIF(TRIM(v.department), ''), 'Unknown')
    ORDER BY total DESC
");

$department_labels = [];
$department_counts = [];
while ($dept_visitors && ($row = $dept_visitors->fetch_assoc())) {
    $department_labels[] = $row['department'];
    $department_counts[] = (int)$row['total'];
}

// ========================
// MONTHLY DATA
// ========================

$months_labels = [];
$visitor_counts = [];

for ($i = 5; $i >= 0; $i--) {
    $m_label = date('M', strtotime("-$i months"));
    $m_query = date('Y-m', strtotime("-$i months"));

    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM visitors WHERE DATE_FORMAT(check_in_time, '%Y-%m') = ?");
    $stmt->bind_param('s', $m_query);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $months_labels[] = $m_label;
    $visitor_counts[] = (int)($result['total'] ?? 0);
}

include 'includes/header.php';
?>
<!-- Fonts & Icons -->
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: linear-gradient(135deg, #eef2ff, #f8fafc);
}
.dashboard-card {
    border-radius: 18px;
    padding: 20px;
    color: #fff;
    position: relative;
    overflow: hidden;
    transition: 0.3s ease-in-out;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
}

.dashboard-card:hover {
    transform: translateY(-5px);
}

.dashboard-card i {
    font-size: 35px;
    opacity: 0.3;
    position: absolute;
    right: 15px;
    top: 15px;
}

.card-title {
    font-size: 14px;
    opacity: 0.9;
}

.card-value {
    font-size: 28px;
    font-weight: bold;
}

/* COLORS */
.card-blue {
    background: linear-gradient(135deg, #4e54c8, #8f94fb);
}

.card-green {
    background: linear-gradient(135deg, #00b09b, #96c93d);
}

.card-orange {
    background: linear-gradient(135deg, #f7971e, #ffd200);
}

.card-red {
    background: linear-gradient(135deg, #ff416c, #ff4b2b);
}

/* HEADER */
.page-title {
    font-weight: 700;
    font-size: 22px;
    color: #0f172a;
}
.subtitle {
    color: #64748b;
    font-size: 14px;
}

/* CARDS */
.stat-card {
    border-radius: 20px;
    padding: 20px;
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.3);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.08);
}

.stat-title {
    font-size: 13px;
    font-weight: 600;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
}

/* COLORED BACKGROUNDS */
.bg-blue {
    background: linear-gradient(135deg, #6366f1, #3b82f6);
    color: white;
}

.bg-green {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.bg-cyan {
    background: linear-gradient(135deg, #06b6d4, #0ea5e9);
    color: white;
}

.bg-orange {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

/* Fix text contrast */
.stat-card[class*="bg-"] .stat-title {
    color: rgba(255,255,255,0.85);
}
.stat-card[class*="bg-"] .stat-value {
    color: white;
}

.icon-box {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 22px;
    padding: 10px;
    border-radius: 12px;
    color: white;
}

.icon-blue { background: rgba(255,255,255,0.2); }
.icon-green { background: rgba(255,255,255,0.2); }
.icon-cyan { background: rgba(255,255,255,0.2); }
.icon-orange { background: rgba(255,255,255,0.2); }

/* CHART CARD */
.chart-card {
    border-radius: 20px;
    background: white;
    padding: 20px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.05);
}

.chart-title {
    font-weight: 600;
    color: #0f172a;
    margin-bottom: 10px;
}

.chart-container {
    height: 320px;
}
@keyframes fadeUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.dashboard-card {
    animation: fadeUp 0.6s ease-in-out;
}
</style>

<div class="container-fluid py-4">

    <!-- HEADER -->
    <div class="mb-4">
        <div class="page-title">Admin Dashboard</div>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <div class="subtitle">Welcome back 👋 Monitor visitor insights and security analytics in real time</div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4 col-sm-12">
            <div class="stat-card bg-blue">
                <div class="stat-title">Vehicles Inside</div>
                <div class="stat-value"><?php echo $total_vehicles_inside; ?></div>
                <div class="icon-box icon-blue"><i class="fas fa-car-side"></i></div>
            </div>
        </div>
        <!-- Staff Accounts card removed as requested -->
        <div class="col-md-4 col-sm-12">
            <div class="stat-card bg-orange">
                <div class="stat-title">Active Departments</div>
                <div class="stat-value"><?php echo $total_departments; ?></div>
                <div class="icon-box icon-orange"><i class="fas fa-layer-group"></i></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12">
            <div class="card shadow-sm" style="border-radius: 22px;">
                <div class="card-body p-4 d-flex flex-column flex-md-row align-items-start gap-3">
                    <div>
                        <h5 class="fw-bold mb-1">Quick Admin Actions</h5>
                        <p class="text-muted small mb-0">Use these shortcuts to manage your users, departments and reports.</p>
                    </div>
                    <div class="d-flex flex-nowrap gap-2 ms-auto overflow-auto" style="white-space: nowrap;">
                        <a href="users.php" class="btn btn-primary btn-sm">Manage Users</a>
                        <a href="departments.php" class="btn btn-outline-primary btn-sm">Department</a>
                        <a href="reports.php" class="btn btn-success btn-sm">Reports & Export</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CARDS -->
    <div class="row g-4">

        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-blue">
                <div class="stat-title">Total Visitors</div>
                <div class="stat-value"><?php echo $total_all; ?></div>
                <div class="icon-box icon-blue"><i class="fas fa-users"></i></div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-green">
                <div class="stat-title">Today's Visitors</div>
                <div class="stat-value"><?php echo $total_today; ?></div>
                <div class="icon-box icon-green"><i class="fas fa-calendar-day"></i></div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card bg-cyan">
                <div class="stat-title">Currently Inside</div>
                <div class="stat-value"><?php echo $total_inside; ?></div>
                <div class="icon-box icon-cyan"><i class="fas fa-user-check"></i></div>
            </div>
        </div>

        <!-- Total Hosts card removed as requested -->

    </div>


    <!-- CHART -->
    <div class="row mt-4">
        <div class="col-xl-8 col-lg-12 mb-4">
            <div class="chart-card">
                <div class="chart-title">Visitor Analytics (Last 6 Months)</div>
                <div class="chart-container">
                    <canvas id="chart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-12 mb-4">
            <div class="chart-card" style="min-height: 350px;">
                <div class="chart-title">Today: Visitors by Department</div>
                <div class="chart-container" style="height: 300px;">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const ctx = document.getElementById('chart').getContext('2d');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?php echo json_encode($months_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($visitor_counts); ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99,102,241,0.08)',
                fill: true,
                tension: 0.5,
                borderWidth: 3,
                pointRadius: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: "#eee" } },
                x: { grid: { display: false } }
            }
        }
    });

    const deptCtx = document.getElementById('deptChart').getContext('2d');
    new Chart(deptCtx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($department_labels); ?>,
            datasets: [{
                label: 'Visitors',
                data: <?php echo json_encode($department_counts); ?>,
                backgroundColor: 'rgba(79, 70, 229, 0.75)',
                borderColor: 'rgba(79, 70, 229, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { precision: 0 } },
                x: { grid: { display: false } }
            }
        }
    });
});
</script>