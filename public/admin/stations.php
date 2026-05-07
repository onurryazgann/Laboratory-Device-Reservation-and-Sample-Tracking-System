<?php

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';

$pageTitle = 'Admin Stations';
$pageCss = 'admin-stations.css';

if (!function_exists('adminStationsH')) {
    function adminStationsH($value)
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('selectedStationAdminOption')) {
    function selectedStationAdminOption($currentValue, $expectedValue): string
    {
        return (string) $currentValue === (string) $expectedValue ? 'selected' : '';
    }
}

if (!function_exists('adminStationStatusClass')) {
    function adminStationStatusClass(string $status): string
    {
        if ($status === 'active') {
            return 'is-active';
        }

        if ($status === 'maintenance') {
            return 'is-maintenance';
        }

        if ($status === 'passive') {
            return 'is-passive';
        }

        return 'is-default';
    }
}

if (!function_exists('adminStationStatusLabel')) {
    function adminStationStatusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'lab_id' => $_GET['lab_id'] ?? '',
    'station_type_id' => $_GET['station_type_id'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$allowedStatuses = ['active', 'maintenance', 'passive'];

if ($filters['lab_id'] !== '' && !filter_var($filters['lab_id'], FILTER_VALIDATE_INT)) {
    $filters['lab_id'] = '';
}

if ($filters['station_type_id'] !== '' && !filter_var($filters['station_type_id'], FILTER_VALIDATE_INT)) {
    $filters['station_type_id'] = '';
}

if ($filters['status'] !== '' && !in_array($filters['status'], $allowedStatuses, true)) {
    $filters['status'] = '';
}

$labs = getAllLabs($pdo);

if (!is_array($labs)) {
    $labs = [];
}

$stationTypes = $pdo->query("
    SELECT station_type_id, type_name
    FROM station_types
    ORDER BY type_name ASC
")->fetchAll();

if (!is_array($stationTypes)) {
    $stationTypes = [];
}

$sql = "
    SELECT
        w.station_id,
        w.lab_id,
        w.station_type_id,
        w.station_code,
        w.station_name,
        w.capacity,
        w.status,
        w.notes,
        st.type_name,
        l.lab_code,
        l.lab_name,
        l.lab_type,
        l.location,
        d.department_name,
        f.faculty_name,
        COUNT(ei.equipment_id) AS equipment_count
    FROM workstations w
    INNER JOIN station_types st
        ON w.station_type_id = st.station_type_id
    INNER JOIN laboratories l
        ON w.lab_id = l.lab_id
    INNER JOIN departments d
        ON l.department_id = d.department_id
    INNER JOIN faculties f
        ON d.faculty_id = f.faculty_id
    LEFT JOIN equipment_instances ei
        ON w.station_id = ei.station_id
    WHERE 1 = 1
";

$params = [];

if ($filters['q'] !== '') {
    $sql .= "
        AND (
            w.station_code LIKE :search_station_code
            OR w.station_name LIKE :search_station_name
            OR st.type_name LIKE :search_type_name
            OR l.lab_code LIKE :search_lab_code
            OR l.lab_name LIKE :search_lab_name
            OR d.department_name LIKE :search_department_name
            OR f.faculty_name LIKE :search_faculty_name
        )
    ";

    $searchValue = '%' . $filters['q'] . '%';

    $params[':search_station_code'] = $searchValue;
    $params[':search_station_name'] = $searchValue;
    $params[':search_type_name'] = $searchValue;
    $params[':search_lab_code'] = $searchValue;
    $params[':search_lab_name'] = $searchValue;
    $params[':search_department_name'] = $searchValue;
    $params[':search_faculty_name'] = $searchValue;
}

if ($filters['lab_id'] !== '') {
    $sql .= " AND w.lab_id = :lab_id";
    $params[':lab_id'] = (int) $filters['lab_id'];
}

if ($filters['station_type_id'] !== '') {
    $sql .= " AND w.station_type_id = :station_type_id";
    $params[':station_type_id'] = (int) $filters['station_type_id'];
}

if ($filters['status'] !== '') {
    $sql .= " AND w.status = :status";
    $params[':status'] = $filters['status'];
}

$sql .= "
    GROUP BY
        w.station_id,
        w.lab_id,
        w.station_type_id,
        w.station_code,
        w.station_name,
        w.capacity,
        w.status,
        w.notes,
        st.type_name,
        l.lab_code,
        l.lab_name,
        l.lab_type,
        l.location,
        d.department_name,
        f.faculty_name
    ORDER BY l.lab_code ASC, w.station_code ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$stations = $stmt->fetchAll();

if (!is_array($stations)) {
    $stations = [];
}

$totalStations = count($stations);
$activeStations = 0;
$maintenanceStations = 0;
$passiveStations = 0;
$totalEquipment = 0;

foreach ($stations as $station) {
    $status = $station['status'] ?? '';

    if ($status === 'active') {
        $activeStations++;
    } elseif ($status === 'maintenance') {
        $maintenanceStations++;
    } elseif ($status === 'passive') {
        $passiveStations++;
    }

    $totalEquipment += (int) ($station['equipment_count'] ?? 0);
}

$itemsPerPage = 8;
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

if (!$currentPage || $currentPage < 1) {
    $currentPage = 1;
}

$totalPages = max((int) ceil($totalStations / $itemsPerPage), 1);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;
$pagedStations = array_slice($stations, $offset, $itemsPerPage);

$startItem = $totalStations > 0 ? $offset + 1 : 0;
$endItem = $totalStations > 0 ? min($offset + count($pagedStations), $totalStations) : 0;

$paginationFilters = [];

foreach ($filters as $key => $value) {
    if ($value !== '' && $value !== null) {
        $paginationFilters[$key] = $value;
    }
}

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="adminstations-page">
    <div class="container">

        <!-- HERO -->
        <div class="adminstations-hero">

            <div class="adminstations-hero-content">

                <span class="adminstations-eyebrow">
                    Station Governance
                </span>

                <h1>
                    Workstation Operations Center
                </h1>

                <p>
                    Monitor laboratory workstations, review operational status,
                    and manage the reservation-ready station infrastructure from one clean admin workspace.
                </p>

                <div class="adminstations-hero-actions">
                    <a href="station-form.php" class="adminstations-btn adminstations-btn-primary">
                        + Add Station
                    </a>

                    <a href="labs.php" class="adminstations-btn adminstations-btn-light">
                        Manage Laboratories
                    </a>
                </div>

            </div>

            <div class="adminstations-hero-visual">

                <div class="adminstations-mini-panel">

                    <div class="adminstations-mini-header">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>

                    <div class="adminstations-mini-body">

                        <div class="adminstations-mini-title">
                            <div>
                                <small>Operational Status</small>
                                <strong>Station Overview</strong>
                            </div>

                            <span class="adminstations-live-badge">
                                Live
                            </span>
                        </div>

                        <div class="adminstations-mini-list">

                            <div class="adminstations-mini-item is-active">
                                <span>01</span>
                                <div>
                                    <strong><?= (int) $activeStations ?> Active Stations</strong>
                                    <small>Reservation-ready workstations in this view.</small>
                                </div>
                            </div>

                            <div class="adminstations-mini-item">
                                <span>02</span>
                                <div>
                                    <strong><?= (int) $maintenanceStations ?> Maintenance</strong>
                                    <small>Stations temporarily unavailable for use.</small>
                                </div>
                            </div>

                            <div class="adminstations-mini-item">
                                <span>03</span>
                                <div>
                                    <strong><?= (int) $totalEquipment ?> Equipment Linked</strong>
                                    <small>Equipment assigned to listed stations.</small>
                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="adminstations-floating-chip adminstations-chip-one">
                        <span>✓</span>
                        Station Ready
                    </div>

                    <div class="adminstations-floating-chip adminstations-chip-two">
                        <span>↗</span>
                        Reservation Units
                    </div>

                </div>

            </div>

        </div>

        <!-- KPI -->
        <div class="adminstations-kpi-grid">

            <div class="adminstations-kpi-card">
                <span>Total Stations</span>
                <strong><?= (int) $totalStations ?></strong>
                <p>Station records shown after active filters are applied.</p>
            </div>

            <div class="adminstations-kpi-card is-success">
                <span>Active Stations</span>
                <strong><?= (int) $activeStations ?></strong>
                <p>Available stations that can be used for reservation workflows.</p>
            </div>

            <div class="adminstations-kpi-card is-warning">
                <span>Maintenance</span>
                <strong><?= (int) $maintenanceStations ?></strong>
                <p>Stations marked as temporarily unavailable or under service.</p>
            </div>

            <div class="adminstations-kpi-card is-error">
                <span>Passive Stations</span>
                <strong><?= (int) $passiveStations ?></strong>
                <p>Inactive stations excluded from normal reservation use.</p>
            </div>

        </div>

        <!-- FILTERS -->
        <div class="adminstations-filter-card">

            <div class="adminstations-section-header">
                <div>
                    <span class="adminstations-section-label">
                        Search & Filter
                    </span>

                    <h2>
                        Find a workstation record.
                    </h2>

                    <p>
                        Search by station code, station name, laboratory, faculty, department or station type.
                    </p>
                </div>

                <span class="adminstations-count-pill">
                    <?= (int) $totalStations ?> results
                </span>
            </div>

            <form method="GET" action="" class="adminstations-filter-form">

                <div class="adminstations-filter-grid">

                    <div class="adminstations-field">
                        <label for="q">Search</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            value="<?= adminStationsH($filters['q']) ?>"
                            placeholder="Station, lab, faculty or department"
                        >
                    </div>

                    <div class="adminstations-field">
                        <label for="lab_id">Laboratory</label>

                        <select id="lab_id" name="lab_id">
                            <option value="">All laboratories</option>

                            <?php foreach ($labs as $lab): ?>
                                <option
                                    value="<?= (int) $lab['lab_id'] ?>"
                                    <?= selectedStationAdminOption($filters['lab_id'], $lab['lab_id']) ?>
                                >
                                    <?= adminStationsH(($lab['lab_code'] ?? '-') . ' - ' . ($lab['lab_name'] ?? '-')) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="adminstations-field">
                        <label for="station_type_id">Station Type</label>

                        <select id="station_type_id" name="station_type_id">
                            <option value="">All station types</option>

                            <?php foreach ($stationTypes as $type): ?>
                                <option
                                    value="<?= (int) $type['station_type_id'] ?>"
                                    <?= selectedStationAdminOption($filters['station_type_id'], $type['station_type_id']) ?>
                                >
                                    <?= adminStationsH($type['type_name'] ?? '-') ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="adminstations-field">
                        <label for="status">Status</label>

                        <select id="status" name="status">
                            <option value="">All statuses</option>

                            <?php foreach ($allowedStatuses as $status): ?>
                                <option
                                    value="<?= adminStationsH($status) ?>"
                                    <?= selectedStationAdminOption($filters['status'], $status) ?>
                                >
                                    <?= adminStationsH(adminStationStatusLabel($status)) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="adminstations-filter-actions">
                    <button type="submit" class="adminstations-btn adminstations-btn-primary">
                        Apply Filters
                    </button>

                    <a href="stations.php" class="adminstations-btn adminstations-btn-outline">
                        Clear Filters
                    </a>
                </div>

            </form>

        </div>

        <!-- STATION LIST -->
        <div class="adminstations-list-section">

            <div class="adminstations-section-header">
                <div>
                    <span class="adminstations-section-label">
                        Station List
                    </span>

                    <h2>
                        Reservation-ready workstation records.
                    </h2>

                    <p>
                        Showing
                        <strong><?= (int) $startItem ?></strong>
                        —
                        <strong><?= (int) $endItem ?></strong>
                        of
                        <strong><?= (int) $totalStations ?></strong>
                        stations.
                    </p>
                </div>

                <span class="adminstations-count-pill">
                    Page <?= (int) $currentPage ?> / <?= (int) $totalPages ?>
                </span>
            </div>

            <?php if ($totalStations > 0): ?>

                <div class="adminstations-table-wrapper">

                    <table class="adminstations-table">

                        <thead>
                            <tr>
                                <th>Station Code</th>
                                <th>Station Name</th>
                                <th>Type</th>
                                <th>Laboratory</th>
                                <th>Faculty</th>
                                <th>Department</th>
                                <th>Capacity</th>
                                <th>Status</th>
                                <th>Equipment</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($pagedStations as $station): ?>
                                <?php
                                    $stationId = (int) ($station['station_id'] ?? 0);
                                    $labId = (int) ($station['lab_id'] ?? 0);
                                    $status = (string) ($station['status'] ?? '');
                                    $statusClass = adminStationStatusClass($status);
                                ?>

                                <tr>

                                    <td>
                                        <span class="adminstations-code-pill">
                                            <?= adminStationsH($station['station_code'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminstations-title">
                                            <?= adminStationsH($station['station_name'] ?? '-') ?>
                                        </span>

                                        <span class="adminstations-muted">
                                            ID: <?= (int) $stationId ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminstations-type-badge">
                                            <?= adminStationsH($station['type_name'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminstations-title">
                                            <?= adminStationsH($station['lab_code'] ?? '-') ?>
                                        </span>

                                        <span class="adminstations-muted">
                                            <?= adminStationsH($station['lab_name'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminstations-title">
                                            <?= adminStationsH($station['faculty_name'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminstations-muted">
                                            <?= adminStationsH($station['department_name'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminstations-capacity">
                                            <?= (int) ($station['capacity'] ?? 0) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminstations-status-badge <?= adminStationsH($statusClass) ?>">
                                            <?= adminStationsH(adminStationStatusLabel($status)) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminstations-count-badge">
                                            <?= (int) ($station['equipment_count'] ?? 0) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="adminstations-action-group">

                                            <a
                                                href="../station-detail.php?id=<?= (int) $stationId ?>"
                                                class="adminstations-btn adminstations-btn-outline adminstations-btn-sm"
                                            >
                                                View
                                            </a>

                                            <?php if ($status === 'active'): ?>
                                                <a
                                                    href="../reserve.php?lab_id=<?= (int) $labId ?>&station_id=<?= (int) $stationId ?>"
                                                    class="adminstations-btn adminstations-btn-primary adminstations-btn-sm"
                                                >
                                                    Reserve
                                                </a>
                                            <?php else: ?>
                                                <span class="adminstations-btn adminstations-btn-disabled adminstations-btn-sm">
                                                    Locked
                                                </span>
                                            <?php endif; ?>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <?php if ($totalPages > 1): ?>

                    <div class="adminstations-pagination">

                        <div class="adminstations-pagination-info">
                            Showing <?= (int) $startItem ?> to <?= (int) $endItem ?> of <?= (int) $totalStations ?> results
                        </div>

                        <div class="adminstations-pagination-list">

                            <?php
                                $prevPage = max($currentPage - 1, 1);
                                $nextPage = min($currentPage + 1, $totalPages);

                                $prevQuery = http_build_query(array_merge($paginationFilters, ['page' => $prevPage]));
                                $nextQuery = http_build_query(array_merge($paginationFilters, ['page' => $nextPage]));
                            ?>

                            <a
                                href="stations.php?<?= adminStationsH($prevQuery) ?>"
                                class="adminstations-page-link <?= $currentPage <= 1 ? 'is-disabled' : '' ?>"
                            >
                                Prev
                            </a>

                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <?php
                                    $pageQuery = http_build_query(array_merge($paginationFilters, ['page' => $page]));
                                ?>

                                <a
                                    href="stations.php?<?= adminStationsH($pageQuery) ?>"
                                    class="adminstations-page-link <?= $page === $currentPage ? 'is-active' : '' ?>"
                                >
                                    <?= (int) $page ?>
                                </a>
                            <?php endfor; ?>

                            <a
                                href="stations.php?<?= adminStationsH($nextQuery) ?>"
                                class="adminstations-page-link <?= $currentPage >= $totalPages ? 'is-disabled' : '' ?>"
                            >
                                Next
                            </a>

                        </div>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="adminstations-empty-state">

                    <div class="adminstations-empty-icon">
                        0
                    </div>

                    <h3>
                        No station found.
                    </h3>

                    <p>
                        No workstation record matches the current filters. Clear filters or search with a broader
                        station, laboratory, faculty or department keyword.
                    </p>

                    <a href="stations.php" class="adminstations-btn adminstations-btn-primary">
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>