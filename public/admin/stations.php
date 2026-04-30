<?php

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';

$pageTitle = 'Admin Stations';
$pageCss = 'admin-stations.css';

$filters = [
    'q' => trim($_GET['q'] ?? ''),
    'lab_id' => $_GET['lab_id'] ?? '',
    'station_type_id' => $_GET['station_type_id'] ?? '',
    'status' => $_GET['status'] ?? ''
];

$labs = getAllLabs($pdo);

$stationTypes = $pdo->query("
    SELECT station_type_id, type_name
    FROM station_types
    ORDER BY type_name ASC
")->fetchAll();

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

function selectedStationAdminOption($currentValue, $expectedValue): string
{
    return (string) $currentValue === (string) $expectedValue ? 'selected' : '';
}

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="page-section">
    <div class="container">

        <!-- HERO -->
        <div class="admin-card">

            <div class="admin-page-header">

                <div>
                    <h1 class="admin-page-title">
                        Workstation Governance Center
                    </h1>

                    <p class="section-subtitle">
                        Monitor station infrastructure, manage operational status,
                        and oversee workstation ecosystem across all laboratories.
                    </p>
                </div>

            </div>

        </div>

        <!-- FILTERS -->
        <div class="admin-card admin-filters">

            <h2>Filters</h2>

            <form method="GET" action="">

                <div class="grid grid-2">

                    <div class="form-group">
                        <label for="q" class="form-label">Search</label>
                        <input
                            type="text"
                            id="q"
                            name="q"
                            class="form-control"
                            value="<?= htmlspecialchars($filters['q']) ?>"
                            placeholder="Station, lab, faculty or department"
                        >
                    </div>

                    <div class="form-group">
                        <label for="lab_id" class="form-label">
                            Laboratory
                        </label>

                        <select
                            id="lab_id"
                            name="lab_id"
                            class="form-control"
                        >
                            <option value="">All laboratories</option>

                            <?php foreach ($labs as $lab): ?>
                                <option
                                    value="<?= (int) $lab['lab_id'] ?>"
                                    <?= selectedStationAdminOption($filters['lab_id'], $lab['lab_id']) ?>
                                >
                                    <?= htmlspecialchars($lab['lab_code'] . ' - ' . $lab['lab_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="grid grid-2">

                    <div class="form-group">
                        <label for="station_type_id" class="form-label">
                            Station Type
                        </label>

                        <select
                            id="station_type_id"
                            name="station_type_id"
                            class="form-control"
                        >
                            <option value="">All station types</option>

                            <?php foreach ($stationTypes as $type): ?>
                                <option
                                    value="<?= (int) $type['station_type_id'] ?>"
                                    <?= selectedStationAdminOption($filters['station_type_id'], $type['station_type_id']) ?>
                                >
                                    <?= htmlspecialchars($type['type_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="status" class="form-label">
                            Status
                        </label>

                        <select
                            id="status"
                            name="status"
                            class="form-control"
                        >
                            <option value="">All statuses</option>

                            <?php foreach ($allowedStatuses as $status): ?>
                                <option
                                    value="<?= htmlspecialchars($status) ?>"
                                    <?= selectedStationAdminOption($filters['status'], $status) ?>
                                >
                                    <?= htmlspecialchars(ucfirst($status)) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="admin-actions">

                    <button type="submit" class="btn btn-primary">
                        Apply Filters
                    </button>

                    <a href="stations.php" class="btn btn-outline">
                        Clear Filters
                    </a>

                </div>

            </form>

        </div>

        <!-- SUMMARY -->
        <div class="admin-card">

            <h2>Results Summary</h2>

            <p style="margin-bottom:0;">
                Total stations shown:
                <strong><?= count($stations) ?></strong>
            </p>

        </div>

        <!-- TABLE -->
        <div class="admin-card">

            <h2>Station List</h2>

            <?php if (count($stations) > 0): ?>

                <div class="table-wrapper admin-table-wrapper">

                    <table class="table">

                        <thead>
                            <tr>
                                <th>ID</th>
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

                            <?php foreach ($stations as $station): ?>

                                <tr>

                                    <td><?= (int) $station['station_id'] ?></td>

                                    <td><?= htmlspecialchars($station['station_code']) ?></td>

                                    <td><?= htmlspecialchars($station['station_name']) ?></td>

                                    <td>
                                        <span class="badge badge-info">
                                            <?= htmlspecialchars($station['type_name']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($station['lab_code'] . ' - ' . $station['lab_name']) ?>
                                    </td>

                                    <td><?= htmlspecialchars($station['faculty_name']) ?></td>

                                    <td><?= htmlspecialchars($station['department_name']) ?></td>

                                    <td>
                                        <?= (int) $station['capacity'] ?>
                                    </td>

                                    <td>

                                        <?php if ($station['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>

                                        <?php elseif ($station['status'] === 'maintenance'): ?>
                                            <span class="badge badge-warning">Maintenance</span>

                                        <?php else: ?>
                                            <span class="badge badge-info">Passive</span>
                                        <?php endif; ?>

                                    </td>

                                    <td>
                                        <?= (int) $station['equipment_count'] ?>
                                    </td>

                                    <td>

                                        <div class="admin-action-cell">

                                            <a
                                                href="../station-detail.php?id=<?= (int) $station['station_id'] ?>"
                                                class="btn btn-outline"
                                            >
                                                View
                                            </a>

                                            <?php if ($station['status'] === 'active'): ?>
                                                <a
                                                    href="../reserve.php?lab_id=<?= (int) $station['lab_id'] ?>&station_id=<?= (int) $station['station_id'] ?>"
                                                    class="btn btn-primary"
                                                >
                                                    Reserve
                                                </a>
                                            <?php endif; ?>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-success">
                    No station found.
                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>