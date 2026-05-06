<?php

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';

$pageTitle = 'Admin Dashboard';
$pageCss = 'admin-dashboard.css';
$pageJs = 'admin-dashboard.js';

$totalUsers = (int) $pdo->query("SELECT COUNT(*) AS total FROM users")->fetch()['total'];
$totalLabs = (int) $pdo->query("SELECT COUNT(*) AS total FROM laboratories")->fetch()['total'];
$totalStations = (int) $pdo->query("SELECT COUNT(*) AS total FROM workstations")->fetch()['total'];

$totalActiveReservations = (int) $pdo->query("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE status = 'active'
")->fetch()['total'];

$stmt = $pdo->query("
    SELECT
        r.reservation_id,
        r.start_time,
        r.end_time,
        r.status,
        CONCAT(u.first_name, ' ', u.last_name) AS user_full_name,
        l.lab_name,
        w.station_code,
        w.station_name
    FROM reservations r
    INNER JOIN users u
        ON r.user_id = u.user_id
    INNER JOIN laboratories l
        ON r.lab_id = l.lab_id
    INNER JOIN workstations w
        ON r.station_id = w.station_id
    ORDER BY r.created_at DESC
    LIMIT 5
");

$latestReservations = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="page-section">
    <div class="container">

        <!-- HERO -->
        <div class="admin-card">

            <div class="admin-page-header">

                <div>
                    <h1 class="admin-page-title">
                        Admin Control Center
                    </h1>

                    <p class="section-subtitle">
                        Welcome, <?= htmlspecialchars(getCurrentUserName()) ?>.
                        Monitor laboratories, reservations, users and operational system health.
                    </p>
                </div>

                <div class="badge badge-info">
                    <?= htmlspecialchars($_SESSION['role_name']) ?>
                </div>

            </div>

        </div>

        <!-- KPI -->
        <div class="admin-kpi-grid">

            <div class="card card-hover">
                <h3>Total Users</h3>
                <span data-dashboard-stat="total_users" class="admin-kpi-value">
                    <?= $totalUsers ?>
                </span>
            </div>

            <div class="card card-hover">
                <h3>Laboratories</h3>
                <span data-dashboard-stat="total_labs" class="admin-kpi-value">
                    <?= $totalLabs ?>
                </span>
            </div>

            <div class="card card-hover">
                <h3>Stations</h3>
                <span data-dashboard-stat="total_stations" class="admin-kpi-value">
                    <?= $totalStations ?>
                </span>
            </div>

            <div class="card card-hover">
                <h3>Active Reservations</h3>
                <span data-dashboard-stat="active_reservations" class="admin-kpi-value">
                    <?= $totalActiveReservations ?>
                </span>
            </div>

        </div>

        <!-- QUICK ACTIONS -->
        <div class="admin-card">

            <h2>Quick Actions</h2>

            <div class="admin-actions">

                <a href="reservations.php" class="btn btn-primary">
                    Manage Reservations
                </a>

                <a href="labs.php" class="btn btn-outline">
                    Manage Laboratories
                </a>

                <a href="stations.php" class="btn btn-outline">
                    Manage Stations
                </a>

                <a href="users.php" class="btn btn-outline">
                    Manage Users
                </a>

            </div>

        </div>

        <!-- LATEST -->
        <div class="admin-card">

            <h2>
                Latest Reservations
            </h2>

            <?php if (count($latestReservations) > 0): ?>

                <div class="table-wrapper admin-table-wrapper">

                    <table class="table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Laboratory</th>
                                <th>Station</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody id="latestReservationsBody">

                            <?php foreach ($latestReservations as $reservation): ?>

                                <tr>

                                    <td>
                                        <?= htmlspecialchars($reservation['user_full_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reservation['lab_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reservation['station_code'] . ' - ' . $reservation['station_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reservation['start_time']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($reservation['end_time']) ?>
                                    </td>

                                    <td>

                                        <?php if ($reservation['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php elseif ($reservation['status'] === 'cancelled'): ?>
                                            <span class="badge badge-warning">Cancelled</span>
                                        <?php else: ?>
                                            <span class="badge badge-info">
                                                <?= htmlspecialchars($reservation['status']) ?>
                                            </span>
                                        <?php endif; ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>
                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-success">
                    No reservation found.
                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>