<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/response_helper.php';

if (!isLoggedIn()) {
    jsonError('Authentication is required.', 401);
}

if (!isAdmin()) {
    jsonError('Admin access is required.', 403);
}

$usersCount = $pdo->query("SELECT COUNT(*) AS total FROM users")->fetch()['total'];
$labsCount = $pdo->query("SELECT COUNT(*) AS total FROM laboratories")->fetch()['total'];
$stationsCount = $pdo->query("SELECT COUNT(*) AS total FROM workstations")->fetch()['total'];
$activeReservations = $pdo->query("SELECT COUNT(*) AS total FROM reservations WHERE status = 'active'")->fetch()['total'];

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
    INNER JOIN users u ON r.user_id = u.user_id
    INNER JOIN laboratories l ON r.lab_id = l.lab_id
    INNER JOIN workstations w ON r.station_id = w.station_id
    ORDER BY r.created_at DESC
    LIMIT 5
");
$latestReservations = $stmt->fetchAll();

jsonSuccess('Dashboard stats loaded.', [
    'total_users' => (int) $usersCount,
    'total_labs' => (int) $labsCount,
    'total_stations' => (int) $stationsCount,
    'active_reservations' => (int) $activeReservations,
    'latest_reservations' => $latestReservations
]);
