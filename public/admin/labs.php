<?php

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';

$pageTitle = 'Admin Laboratories';
$pageCss = 'admin-labs.css';

if (!function_exists('adminLabsH')) {
    function adminLabsH($value)
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'faculty_id' => $_GET['faculty_id'] ?? '',
    'department_id' => $_GET['department_id'] ?? '',
    'lab_type' => trim($_GET['lab_type'] ?? '')
];

$faculties = getActiveFaculties($pdo);
$departments = getActiveDepartments($pdo);
$labTypes = getLabTypes($pdo);
$labs = getAllLabs($pdo, $filters);

if (!is_array($labs)) {
    $labs = [];
}

$totalLabs = count($labs);
$activeLabs = 0;
$totalStations = 0;
$activeStations = 0;
$departmentKeys = [];

foreach ($labs as $lab) {
    if (!array_key_exists('is_active', $lab) || (int) ($lab['is_active'] ?? 1) === 1) {
        $activeLabs++;
    }

    $totalStations += (int) ($lab['total_station_count'] ?? 0);
    $activeStations += (int) ($lab['active_station_count'] ?? 0);

    $departmentKey = $lab['department_id'] ?? $lab['department_name'] ?? null;

    if ($departmentKey !== null && $departmentKey !== '') {
        $departmentKeys[(string) $departmentKey] = true;
    }
}

$departmentCount = count($departmentKeys);

$itemsPerPage = 8;
$currentPage = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);

if (!$currentPage || $currentPage < 1) {
    $currentPage = 1;
}

$totalPages = max((int) ceil($totalLabs / $itemsPerPage), 1);

if ($currentPage > $totalPages) {
    $currentPage = $totalPages;
}

$offset = ($currentPage - 1) * $itemsPerPage;
$pagedLabs = array_slice($labs, $offset, $itemsPerPage);

$startItem = $totalLabs > 0 ? $offset + 1 : 0;
$endItem = $totalLabs > 0 ? min($offset + count($pagedLabs), $totalLabs) : 0;

$paginationFilters = [];

foreach ($filters as $key => $value) {
    if ($value !== '' && $value !== null) {
        $paginationFilters[$key] = $value;
    }
}

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="adminlabs-page">
    <div class="container">

        <!-- HERO -->
        <div class="adminlabs-hero">

            <div class="adminlabs-hero-content">

                <span class="adminlabs-eyebrow">
                    Laboratory Governance
                </span>

                <h1>
                    Laboratory Infrastructure Center
                </h1>

                <p>
                    Monitor institutional laboratory structure, filter by academic hierarchy,
                    and oversee the full laboratory ecosystem from one clean admin workspace.
                </p>

                <div class="adminlabs-hero-actions">
                    <a href="lab-form.php" class="adminlabs-btn adminlabs-btn-primary">
                        + Add Laboratory
                    </a>

                    <a href="../labs.php" class="adminlabs-btn adminlabs-btn-light">
                        View Public Labs
                    </a>
                </div>

            </div>

            <div class="adminlabs-hero-visual">

                <div class="adminlabs-mini-panel">

                    <div class="adminlabs-mini-header">
                        <span></span>
                        <span></span>
                        <span></span>
                    </div>

                    <div class="adminlabs-mini-body">

                        <div class="adminlabs-mini-title">
                            <div>
                                <small>Directory Status</small>
                                <strong>Laboratory Overview</strong>
                            </div>

                            <span class="adminlabs-live-badge">
                                Live
                            </span>
                        </div>

                        <div class="adminlabs-mini-list">

                            <div class="adminlabs-mini-item is-active">
                                <span>01</span>
                                <div>
                                    <strong><?= (int) $totalLabs ?> Laboratories</strong>
                                    <small>Total records matching the current filters.</small>
                                </div>
                            </div>

                            <div class="adminlabs-mini-item">
                                <span>02</span>
                                <div>
                                    <strong><?= (int) $activeStations ?> Active Stations</strong>
                                    <small>Available operational station capacity.</small>
                                </div>
                            </div>

                            <div class="adminlabs-mini-item">
                                <span>03</span>
                                <div>
                                    <strong><?= (int) $departmentCount ?> Departments</strong>
                                    <small>Academic units represented in this view.</small>
                                </div>
                            </div>

                        </div>

                    </div>

                    <div class="adminlabs-floating-chip adminlabs-chip-one">
                        <span>✓</span>
                        Labs Ready
                    </div>

                    <div class="adminlabs-floating-chip adminlabs-chip-two">
                        <span>↗</span>
                        Managed Directory
                    </div>

                </div>

            </div>

        </div>

        <!-- KPI -->
        <div class="adminlabs-kpi-grid">

            <div class="adminlabs-kpi-card">
                <span>Total Laboratories</span>
                <strong><?= (int) $totalLabs ?></strong>
                <p>Laboratory records shown after active filters are applied.</p>
            </div>

            <div class="adminlabs-kpi-card is-success">
                <span>Active Laboratories</span>
                <strong><?= (int) $activeLabs ?></strong>
                <p>Laboratories currently available in the system directory.</p>
            </div>

            <div class="adminlabs-kpi-card is-info">
                <span>Active Stations</span>
                <strong><?= (int) $activeStations ?></strong>
                <p>Operational station count across the filtered laboratories.</p>
            </div>

            <div class="adminlabs-kpi-card is-warning">
                <span>Total Stations</span>
                <strong><?= (int) $totalStations ?></strong>
                <p>All stations connected to the listed laboratories.</p>
            </div>

        </div>

        <!-- FILTERS -->
        <div class="adminlabs-filter-card">

            <div class="adminlabs-section-header">
                <div>
                    <span class="adminlabs-section-label">
                        Search & Filter
                    </span>

                    <h2>
                        Find a laboratory record.
                    </h2>

                    <p>
                        Search by laboratory name, code, faculty, department, type or location.
                    </p>
                </div>

                <span class="adminlabs-count-pill">
                    <?= (int) $totalLabs ?> results
                </span>
            </div>

            <form method="GET" action="" class="adminlabs-filter-form">

                <div class="adminlabs-filter-grid">

                    <div class="adminlabs-field">
                        <label for="q">Search</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            value="<?= adminLabsH($filters['q']) ?>"
                            placeholder="Laboratory, code, faculty or department"
                        >
                    </div>

                    <div class="adminlabs-field">
                        <label for="lab_type">Laboratory Type</label>

                        <select id="lab_type" name="lab_type">
                            <option value="">All types</option>

                            <?php foreach ($labTypes as $type): ?>
                                <?php $typeValue = $type['lab_type'] ?? ''; ?>

                                <option
                                    value="<?= adminLabsH($typeValue) ?>"
                                    <?= $filters['lab_type'] === $typeValue ? 'selected' : '' ?>
                                >
                                    <?= adminLabsH(ucwords(str_replace('_', ' ', $typeValue))) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="adminlabs-field">
                        <label for="faculty_id">Faculty</label>

                        <select id="faculty_id" name="faculty_id">
                            <option value="">All faculties</option>

                            <?php foreach ($faculties as $faculty): ?>
                                <option
                                    value="<?= (int) $faculty['faculty_id'] ?>"
                                    <?= (string) $filters['faculty_id'] === (string) $faculty['faculty_id'] ? 'selected' : '' ?>
                                >
                                    <?= adminLabsH($faculty['faculty_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="adminlabs-field">
                        <label for="department_id">Department</label>

                        <select id="department_id" name="department_id">
                            <option value="">All departments</option>

                            <?php foreach ($departments as $department): ?>
                                <option
                                    value="<?= (int) $department['department_id'] ?>"
                                    <?= (string) $filters['department_id'] === (string) $department['department_id'] ? 'selected' : '' ?>
                                >
                                    <?= adminLabsH($department['department_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="adminlabs-filter-actions">
                    <button type="submit" class="adminlabs-btn adminlabs-btn-primary">
                        Apply Filters
                    </button>

                    <a href="labs.php" class="adminlabs-btn adminlabs-btn-outline">
                        Clear Filters
                    </a>
                </div>

            </form>

        </div>

        <!-- LABORATORY LIST -->
        <div class="adminlabs-list-section">

            <div class="adminlabs-section-header">
                <div>
                    <span class="adminlabs-section-label">
                        Laboratory List
                    </span>

                    <h2>
                        Institutional laboratory records.
                    </h2>

                    <p>
                        Showing
                        <strong><?= (int) $startItem ?></strong>
                        —
                        <strong><?= (int) $endItem ?></strong>
                        of
                        <strong><?= (int) $totalLabs ?></strong>
                        laboratories.
                    </p>
                </div>

                <span class="adminlabs-count-pill">
                    Page <?= (int) $currentPage ?> / <?= (int) $totalPages ?>
                </span>
            </div>

            <?php if ($totalLabs > 0): ?>

                <div class="adminlabs-table-wrapper">

                    <table class="adminlabs-table">

                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Laboratory</th>
                                <th>Faculty</th>
                                <th>Department</th>
                                <th>Type</th>
                                <th>Location</th>
                                <th>Phone</th>
                                <th>Active Stations</th>
                                <th>Total Stations</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($pagedLabs as $lab): ?>
                                <?php
                                    $labCode = $lab['lab_code'] ?? '-';
                                    $labName = $lab['lab_name'] ?? '-';
                                    $facultyName = $lab['faculty_name'] ?? '-';
                                    $departmentName = $lab['department_name'] ?? '-';
                                    $labType = $lab['lab_type'] ?? '-';
                                    $location = $lab['location'] ?? '-';
                                    $phone = $lab['phone'] ?? '-';
                                    $activeStationCount = (int) ($lab['active_station_count'] ?? 0);
                                    $totalStationCount = (int) ($lab['total_station_count'] ?? 0);
                                    $labId = (int) ($lab['lab_id'] ?? 0);
                                ?>

                                <tr>

                                    <td>
                                        <span class="adminlabs-code-pill">
                                            <?= adminLabsH($labCode) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminlabs-lab-name">
                                            <?= adminLabsH($labName) ?>
                                        </span>

                                        <span class="adminlabs-muted">
                                            ID: <?= (int) $labId ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminlabs-lab-name">
                                            <?= adminLabsH($facultyName) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminlabs-muted">
                                            <?= adminLabsH($departmentName) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminlabs-type-badge">
                                            <?= adminLabsH(ucwords(str_replace('_', ' ', $labType))) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminlabs-contact-cell">
                                            <?= adminLabsH($location) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminlabs-contact-cell">
                                            <?= adminLabsH($phone) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminlabs-station-badge">
                                            <?= (int) $activeStationCount ?>
                                        </span>
                                    </td>

                                    <td>
                                        <span class="adminlabs-total-count">
                                            <?= (int) $totalStationCount ?>
                                        </span>
                                    </td>

                                    <td>
                                        <div class="adminlabs-action-group">
                                            <a
                                                href="../lab-detail.php?id=<?= (int) $labId ?>"
                                                class="adminlabs-btn adminlabs-btn-outline adminlabs-btn-sm"
                                            >
                                                View Detail
                                            </a>
                                        </div>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

                <?php if ($totalPages > 1): ?>

                    <div class="adminlabs-pagination">

                        <div class="adminlabs-pagination-info">
                            Showing <?= (int) $startItem ?> to <?= (int) $endItem ?> of <?= (int) $totalLabs ?> results
                        </div>

                        <div class="adminlabs-pagination-list">

                            <?php
                                $prevPage = max($currentPage - 1, 1);
                                $nextPage = min($currentPage + 1, $totalPages);

                                $prevQuery = http_build_query(array_merge($paginationFilters, ['page' => $prevPage]));
                                $nextQuery = http_build_query(array_merge($paginationFilters, ['page' => $nextPage]));
                            ?>

                            <a
                                href="labs.php?<?= adminLabsH($prevQuery) ?>"
                                class="adminlabs-page-link <?= $currentPage <= 1 ? 'is-disabled' : '' ?>"
                            >
                                Prev
                            </a>

                            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                                <?php
                                    $pageQuery = http_build_query(array_merge($paginationFilters, ['page' => $page]));
                                ?>

                                <a
                                    href="labs.php?<?= adminLabsH($pageQuery) ?>"
                                    class="adminlabs-page-link <?= $page === $currentPage ? 'is-active' : '' ?>"
                                >
                                    <?= (int) $page ?>
                                </a>
                            <?php endfor; ?>

                            <a
                                href="labs.php?<?= adminLabsH($nextQuery) ?>"
                                class="adminlabs-page-link <?= $currentPage >= $totalPages ? 'is-disabled' : '' ?>"
                            >
                                Next
                            </a>

                        </div>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="adminlabs-empty-state">

                    <div class="adminlabs-empty-icon">
                        0
                    </div>

                    <h3>
                        No laboratory found.
                    </h3>

                    <p>
                        No laboratory record matches the current filters. Clear the filters or try
                        searching with a broader laboratory, faculty or department keyword.
                    </p>

                    <a href="labs.php" class="adminlabs-btn adminlabs-btn-primary">
                        Clear Filters
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>