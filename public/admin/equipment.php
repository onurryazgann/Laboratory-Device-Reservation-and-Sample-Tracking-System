<?php

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';

$pageTitle = 'Admin Equipment';
$pageCss = 'admin-equipment.css';

if (!function_exists('adminEquipmentH')) {
    function adminEquipmentH($value)
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('selectedEquipmentAdminOption')) {
    function selectedEquipmentAdminOption($currentValue, $expectedValue): string
    {
        return (string) $currentValue === (string) $expectedValue ? 'selected' : '';
    }
}

if (!function_exists('adminEquipmentStatusClass')) {
    function adminEquipmentStatusClass(string $status): string
    {
        if ($status === 'available') {
            return 'is-available';
        }

        if ($status === 'in_use') {
            return 'is-in-use';
        }

        if ($status === 'maintenance') {
            return 'is-maintenance';
        }

        if ($status === 'retired') {
            return 'is-retired';
        }

        return 'is-default';
    }
}

if (!function_exists('adminEquipmentStatusLabel')) {
    function adminEquipmentStatusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'lab_id' => $_GET['lab_id'] ?? '',
    'station_id' => $_GET['station_id'] ?? '',
    'category' => trim($_GET['category'] ?? ''),
    'status' => $_GET['status'] ?? ''
];

$allowedStatuses = ['available', 'in_use', 'maintenance', 'retired'];

if ($filters['lab_id'] !== '' && !filter_var($filters['lab_id'], FILTER_VALIDATE_INT)) {
    $filters['lab_id'] = '';
}

if ($filters['station_id'] !== '' && !filter_var($filters['station_id'], FILTER_VALIDATE_INT)) {
    $filters['station_id'] = '';
}

if ($filters['status'] !== '' && !in_array($filters['status'], $allowedStatuses, true)) {
    $filters['status'] = '';
}

$labs = getAllLabs($pdo);

if (!is_array($labs)) {
    $labs = [];
}

$stations = $pdo->query("
    SELECT
        w.station_id,
        w.station_code,
        w.station_name,
        l.lab_code,
        l.lab_name
    FROM workstations w
    INNER JOIN laboratories l
        ON w.lab_id = l.lab_id
    ORDER BY l.lab_code ASC, w.station_code ASC
")->fetchAll();

if (!is_array($stations)) {
    $stations = [];
}

$categories = $pdo->query("
    SELECT DISTINCT category
    FROM equipment_types
    WHERE category IS NOT NULL AND category <> ''
    ORDER BY category ASC
")->fetchAll();

if (!is_array($categories)) {
    $categories = [];
}

$categoryValues = array_map(
    static fn($category) => (string) ($category['category'] ?? ''),
    $categories
);

if ($filters['category'] !== '' && !in_array($filters['category'], $categoryValues, true)) {
    $filters['category'] = '';
}

$sql = "
    SELECT
        ei.equipment_id,
        ei.equipment_type_id,
        ei.lab_id,
        ei.station_id,
        ei.asset_code,
        ei.brand,
        ei.model,
        ei.status,
        ei.notes,
        et.equipment_name,
        et.category,
        l.lab_code,
        l.lab_name,
        w.station_code,
        w.station_name
    FROM equipment_instances ei
    INNER JOIN equipment_types et
        ON ei.equipment_type_id = et.equipment_type_id
    INNER JOIN laboratories l
        ON ei.lab_id = l.lab_id
    LEFT JOIN workstations w
        ON ei.station_id = w.station_id
    WHERE 1 = 1
";

$params = [];

if ($filters['q'] !== '') {
    $sql .= "
        AND (
            ei.asset_code LIKE :search_asset_code
            OR ei.brand LIKE :search_brand
            OR ei.model LIKE :search_model
            OR et.equipment_name LIKE :search_equipment_name
            OR et.category LIKE :search_category
            OR l.lab_code LIKE :search_lab_code
            OR l.lab_name LIKE :search_lab_name
            OR w.station_code LIKE :search_station_code
            OR w.station_name LIKE :search_station_name
        )
    ";

    $searchValue = '%' . $filters['q'] . '%';

    $params[':search_asset_code'] = $searchValue;
    $params[':search_brand'] = $searchValue;
    $params[':search_model'] = $searchValue;
    $params[':search_equipment_name'] = $searchValue;
    $params[':search_category'] = $searchValue;
    $params[':search_lab_code'] = $searchValue;
    $params[':search_lab_name'] = $searchValue;
    $params[':search_station_code'] = $searchValue;
    $params[':search_station_name'] = $searchValue;
}

if ($filters['lab_id'] !== '') {
    $sql .= " AND ei.lab_id = :lab_id";
    $params[':lab_id'] = (int) $filters['lab_id'];
}

if ($filters['station_id'] !== '') {
    $sql .= " AND ei.station_id = :station_id";
    $params[':station_id'] = (int) $filters['station_id'];
}

if ($filters['category'] !== '') {
    $sql .= " AND et.category = :category";
    $params[':category'] = $filters['category'];
}

if ($filters['status'] !== '') {
    $sql .= " AND ei.status = :status";
    $params[':status'] = $filters['status'];
}

$sql .= "
    ORDER BY
        l.lab_code ASC,
        w.station_code ASC,
        et.category ASC,
        et.equipment_name ASC,
        ei.asset_code ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$equipmentList = $stmt->fetchAll();

if (!is_array($equipmentList)) {
    $equipmentList = [];
}

$totalEquipment = count($equipmentList);
$availableEquipment = 0;
$inUseEquipment = 0;
$maintenanceEquipment = 0;
$retiredEquipment = 0;
$linkedStations = [];

foreach ($equipmentList as $equipment) {
    $status = $equipment['status'] ?? '';

    if ($status === 'available') {
        $availableEquipment++;
    } elseif ($status === 'in_use') {
        $inUseEquipment++;
    } elseif ($status === 'maintenance') {
        $maintenanceEquipment++;
    } elseif ($status === 'retired') {
        $retiredEquipment++;
    }

    if (!empty($equipment['station_id'])) {
        $linkedStations[(string) $equipment['station_id']] = true;
    }
}

$linkedStationCount = count($linkedStations);

$itemsPerPage = 8;
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

if (!$currentPage || $currentPage < 1) {
    $currentPage = 1;
}

$totalPages = max((int) ceil($totalEquipment / $itemsPerPage), 1);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;
$pagedEquipment = array_slice($equipmentList, $offset, $itemsPerPage);

$startItem = $totalEquipment > 0 ? $offset + 1 : 0;
$endItem = $totalEquipment > 0 ? min($offset + count($pagedEquipment), $totalEquipment) : 0;

$paginationFilters = [];

foreach ($filters as $key => $value) {
    if ($value !== '' && $value !== null) {
        $paginationFilters[$key] = $value;
    }
}

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="adminequipment-page">
    <div class="container">

        <!-- HERO -->
        <div class="adminequipment-hero">

            <div class="adminequipment-hero-content">

                <span class="adminequipment-eyebrow">
                    Equipment Governance
                </span>

                <h1>
                    Equipment Asset Operations Center
                </h1>

                <p>
                    Monitor physical inventory, review asset lifecycle state,
                    and keep laboratory devices aligned with station-based reservation workflows.
                </p>

                <div class="adminequipment-hero-actions">
                    <a href="equipment-form.php" class="adminequipment-btn adminequipment-btn-primary">
                        + Add Equipment
                    </a>

                    <a href="stations.php" class="adminequipment-btn adminequipment-btn-light">
                        Manage Stations
                    </a>
                </div>

            </div>

            <div class="adminequipment-hero-visual">

                <div class="adminequipment-mini-panel">

                    <div class="adminequipment-mini-header">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>

                    <div class="adminequipment-mini-body">

                        <div class="adminequipment-mini-title">
                            <div>
                                <small>Asset Lifecycle</small>
                                <strong>Equipment Overview</strong>
                            </div>

                            <span class="adminequipment-live-badge">
                                Live
                            </span>
                        </div>

                        <div class="adminequipment-mini-list">

                            <div class="adminequipment-mini-item is-active">
                                <span>01</span>
                                <div>
                                    <strong><?= (int) $availableEquipment ?> Available</strong>
                                    <small>Assets ready for laboratory use.</small>
                                </div>
                            </div>

                            <div class="adminequipment-mini-item">
                                <span>02</span>
                                <div>
                                    <strong><?= (int) $maintenanceEquipment ?> Maintenance</strong>
                                    <small>Assets requiring service or inspection.</small>
                                </div>
                            </div>

                            <div class="adminequipment-mini-item">
                                <span>03</span>
                                <div>
                                    <strong><?= (int) $linkedStationCount ?> Linked Stations</strong>
                                    <small>Stations with equipment in this view.</small>
                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="adminequipment-floating-chip adminequipment-chip-one">
                        <span>✓</span>
                        Inventory Ready
                    </div>

                    <div class="adminequipment-floating-chip adminequipment-chip-two">
                        <span>↗</span>
                        Asset Tracking
                    </div>

                </div>

            </div>

        </div>

        <!-- KPI -->
        <div class="adminequipment-kpi-grid">

            <div class="adminequipment-kpi-card">
                <span>Total Equipment</span>
                <strong><?= (int) $totalEquipment ?></strong>
                <p>Equipment records shown after active filters are applied.</p>
            </div>

            <div class="adminequipment-kpi-card is-success">
                <span>Available</span>
                <strong><?= (int) $availableEquipment ?></strong>
                <p>Assets currently ready for use in laboratories or stations.</p>
            </div>

            <div class="adminequipment-kpi-card is-warning">
                <span>Maintenance</span>
                <strong><?= (int) $maintenanceEquipment ?></strong>
                <p>Equipment records marked as temporarily unavailable.</p>
            </div>

            <div class="adminequipment-kpi-card is-error">
                <span>Retired</span>
                <strong><?= (int) $retiredEquipment ?></strong>
                <p>Assets removed from normal operational usage.</p>
            </div>

        </div>

        <!-- FILTERS -->
        <div class="adminequipment-filter-card">

            <div class="adminequipment-section-header">
                <div>
                    <span class="adminequipment-section-label">
                        Search & Filter
                    </span>

                    <h2>
                        Find an equipment record.
                    </h2>

                    <p>
                        Search by asset code, equipment name, brand, model, laboratory or station.
                    </p>
                </div>

                <span class="adminequipment-count-pill">
                    <?= (int) $totalEquipment ?> results
                </span>
            </div>

            <form method="GET" action="" class="adminequipment-filter-form">

                <div class="adminequipment-filter-grid">

                    <div class="adminequipment-field">
                        <label for="q">Search</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            value="<?= adminEquipmentH($filters['q']) ?>"
                            placeholder="Asset, equipment, brand, model or station"
                        >
                    </div>

                    <div class="adminequipment-field">
                        <label for="category">Category</label>

                        <select id="category" name="category">
                            <option value="">All categories</option>

                            <?php foreach ($categories as $category): ?>
                                <?php $categoryValue = (string) ($category['category'] ?? ''); ?>

                                <option
                                    value="<?= adminEquipmentH($categoryValue) ?>"
                                    <?= selectedEquipmentAdminOption($filters['category'], $categoryValue) ?>
                                >
                                    <?= adminEquipmentH(ucwords(str_replace('_', ' ', $categoryValue))) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="adminequipment-field">
                        <label for="lab_id">Laboratory</label>

                        <select id="lab_id" name="lab_id">
                            <option value="">All laboratories</option>

                            <?php foreach ($labs as $lab): ?>
                                <option
                                    value="<?= (int) $lab['lab_id'] ?>"
                                    <?= selectedEquipmentAdminOption($filters['lab_id'], $lab['lab_id']) ?>
                                >
                                    <?= adminEquipmentH(($lab['lab_code'] ?? '-') . ' - ' . ($lab['lab_name'] ?? '-')) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="adminequipment-field">
                        <label for="station_id">Station</label>

                        <select id="station_id" name="station_id">
                            <option value="">All stations</option>

                            <?php foreach ($stations as $station): ?>
                                <option
                                    value="<?= (int) $station['station_id'] ?>"
                                    <?= selectedEquipmentAdminOption($filters['station_id'], $station['station_id']) ?>
                                >
                                    <?= adminEquipmentH(
                                        ($station['lab_code'] ?? '-') . ' - ' .
                                        ($station['station_code'] ?? '-') . ' - ' .
                                        ($station['station_name'] ?? '-')
                                    ) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="adminequipment-field">
                        <label for="status">Status</label>

                        <select id="status" name="status">
                            <option value="">All statuses</option>

                            <?php foreach ($allowedStatuses as $status): ?>
                                <option
                                    value="<?= adminEquipmentH($status) ?>"
                                    <?= selectedEquipmentAdminOption($filters['status'], $status) ?>
                                >
                                    <?= adminEquipmentH(adminEquipmentStatusLabel($status)) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="adminequipment-filter-actions">
                    <button type="submit" class="adminequipment-btn adminequipment-btn-primary">
                        Apply Filters
                    </button>

                    <a href="equipment.php" class="adminequipment-btn adminequipment-btn-outline">
                        Clear Filters
                    </a>
                </div>

            </form>

        </div>

        <!-- EQUIPMENT LIST -->
        <div class="adminequipment-list-section">

            <div class="adminequipment-section-header">
                <div>
                    <span class="adminequipment-section-label">
                        Equipment List
                    </span>

                    <h2>
                        Physical asset inventory records.
                    </h2>

                    <p>
                        Showing
                        <strong><?= (int) $startItem ?></strong>
                        —
                        <strong><?= (int) $endItem ?></strong>
                        of
                        <strong><?= (int) $totalEquipment ?></strong>
                        equipment records.
                    </p>
                </div>

                <span class="adminequipment-count-pill">
                    Page <?= (int) $currentPage ?> / <?= (int) $totalPages ?>
                </span>
            </div>

            <?php if ($totalEquipment > 0): ?>

                <div class="adminequipment-table-wrapper">

                    <table class="adminequipment-table">

                        <thead>
                            <tr>
                                <th>Asset Code</th>
                                <th>Equipment</th>
                                <th>Category</th>
                                <th>Laboratory</th>
                                <th>Station</th>
                                <th>Brand</th>
                                <th>Model</th>
                                <th>Status</th>
                                <th>Notes</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($pagedEquipment as $equipment): ?>
                                <?php
                                    $equipmentId = (int) ($equipment['equipment_id'] ?? 0);
                                    $stationId = (int) ($equipment['station_id'] ?? 0);
                                    $status = (string) ($equipment['status'] ?? '');
                                    $statusClass = adminEquipmentStatusClass($status);
                                ?>

                                <tr>

                                    <td>
                                        <span class="adminequipment-code-pill">
                                            <?= adminEquipmentH($equipment['asset_code'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminequipment-title">
                                            <?= adminEquipmentH($equipment['equipment_name'] ?? '-') ?>
                                        </span>

                                        <span class="adminequipment-muted">
                                            ID: <?= (int) $equipmentId ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminequipment-category-badge">
                                            <?= adminEquipmentH(ucwords(str_replace('_', ' ', (string) ($equipment['category'] ?? '-')))) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminequipment-title">
                                            <?= adminEquipmentH($equipment['lab_code'] ?? '-') ?>
                                        </span>

                                        <span class="adminequipment-muted">
                                            <?= adminEquipmentH($equipment['lab_name'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php if ($stationId > 0): ?>
                                            <a
                                                href="../station-detail.php?id=<?= (int) $stationId ?>"
                                                class="adminequipment-title"
                                            >
                                                <?= adminEquipmentH($equipment['station_code'] ?? '-') ?>
                                            </a>

                                            <span class="adminequipment-muted">
                                                <?= adminEquipmentH($equipment['station_name'] ?? '-') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="adminequipment-muted">
                                                Unassigned
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td>
                                        <span class="adminequipment-title">
                                            <?= adminEquipmentH($equipment['brand'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminequipment-muted">
                                            <?= adminEquipmentH($equipment['model'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminequipment-status-badge <?= adminEquipmentH($statusClass) ?>">
                                            <?= adminEquipmentH(adminEquipmentStatusLabel($status)) ?>
                                        </span>
                                    </td>

                                    <td class="adminequipment-notes">
                                        <span>
                                            <?= adminEquipmentH($equipment['notes'] ?? '-') ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="adminequipment-action-group">

                                            <?php if ($stationId > 0): ?>
                                                <a
                                                    href="../station-detail.php?id=<?= (int) $stationId ?>"
                                                    class="adminequipment-btn adminequipment-btn-outline adminequipment-btn-sm"
                                                >
                                                    Station
                                                </a>
                                            <?php endif; ?>

                                            <a
                                                href="equipment-form.php?id=<?= (int) $equipmentId ?>"
                                                class="adminequipment-btn adminequipment-btn-primary adminequipment-btn-sm"
                                            >
                                                Edit
                                            </a>

                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <?php if ($totalPages > 1): ?>

                    <div class="adminequipment-pagination">

                        <div class="adminequipment-pagination-info">
                            Showing <?= (int) $startItem ?> to <?= (int) $endItem ?> of <?= (int) $totalEquipment ?> results
                        </div>

                        <div class="adminequipment-pagination-list">

                            <?php
                                $prevPage = max($currentPage - 1, 1);
                                $nextPage = min($currentPage + 1, $totalPages);

                                $prevQuery = http_build_query(array_merge($paginationFilters, ['page' => $prevPage]));
                                $nextQuery = http_build_query(array_merge($paginationFilters, ['page' => $nextPage]));
                            ?>

                            <a
                                href="equipment.php?<?= adminEquipmentH($prevQuery) ?>"
                                class="adminequipment-page-link <?= $currentPage <= 1 ? 'is-disabled' : '' ?>"
                            >
                                Prev
                            </a>

                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <?php
                                    $pageQuery = http_build_query(array_merge($paginationFilters, ['page' => $page]));
                                ?>

                                <a
                                    href="equipment.php?<?= adminEquipmentH($pageQuery) ?>"
                                    class="adminequipment-page-link <?= $page === $currentPage ? 'is-active' : '' ?>"
                                >
                                    <?= (int) $page ?>
                                </a>
                            <?php endfor; ?>

                            <a
                                href="equipment.php?<?= adminEquipmentH($nextQuery) ?>"
                                class="adminequipment-page-link <?= $currentPage >= $totalPages ? 'is-disabled' : '' ?>"
                            >
                                Next
                            </a>

                        </div>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="adminequipment-empty-state">

                    <div class="adminequipment-empty-icon">
                        0
                    </div>

                    <h3>
                        No equipment found.
                    </h3>

                    <p>
                        No equipment record matches the current filters. Clear filters or search with a broader
                        asset code, device name, brand, model, laboratory or station keyword.
                    </p>

                    <a href="equipment.php" class="adminequipment-btn adminequipment-btn-primary">
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>