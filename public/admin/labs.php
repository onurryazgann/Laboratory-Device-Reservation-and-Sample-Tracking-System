<?php

require_once __DIR__ . '/../../includes/admin_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';

$pageTitle = 'Admin Laboratories';
$pageCss = 'admin-labs.css';

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

require_once __DIR__ . '/../../includes/header.php';

?>

<section class="page-section">
    <div class="container">

        <!-- HERO -->
        <div class="admin-card">

            <div class="admin-page-header">

                <div>
                    <h1 class="admin-page-title">
                        Laboratory Infrastructure Center
                    </h1>

                    <p class="section-subtitle">
                        Monitor institutional laboratory structure, filter by academic hierarchy,
                        and oversee laboratory ecosystem governance.
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
                            placeholder="Laboratory, code, faculty or department"
                        >
                    </div>

                    <div class="form-group">
                        <label for="lab_type" class="form-label">
                            Laboratory Type
                        </label>

                        <select
                            id="lab_type"
                            name="lab_type"
                            class="form-control"
                        >
                            <option value="">All types</option>

                            <?php foreach ($labTypes as $type): ?>
                                <option
                                    value="<?= htmlspecialchars($type['lab_type']) ?>"
                                    <?= $filters['lab_type'] === $type['lab_type'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($type['lab_type']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="grid grid-2">

                    <div class="form-group">
                        <label for="faculty_id" class="form-label">
                            Faculty
                        </label>

                        <select
                            id="faculty_id"
                            name="faculty_id"
                            class="form-control"
                        >
                            <option value="">All faculties</option>

                            <?php foreach ($faculties as $faculty): ?>
                                <option
                                    value="<?= (int) $faculty['faculty_id'] ?>"
                                    <?= (string) $filters['faculty_id'] === (string) $faculty['faculty_id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($faculty['faculty_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                    <div class="form-group">
                        <label for="department_id" class="form-label">
                            Department
                        </label>

                        <select
                            id="department_id"
                            name="department_id"
                            class="form-control"
                        >
                            <option value="">All departments</option>

                            <?php foreach ($departments as $department): ?>
                                <option
                                    value="<?= (int) $department['department_id'] ?>"
                                    <?= (string) $filters['department_id'] === (string) $department['department_id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($department['department_name']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </div>

                </div>

                <div class="admin-actions">

                    <button type="submit" class="btn btn-primary">
                        Apply Filters
                    </button>

                    <a href="labs.php" class="btn btn-outline">
                        Clear Filters
                    </a>

                </div>

            </form>

        </div>

        <!-- SUMMARY -->
        <div class="admin-card">

            <h2>Results Summary</h2>

            <p style="margin-bottom:0;">
                Total laboratories shown:
                <strong><?= count($labs) ?></strong>
            </p>

        </div>

        <!-- TABLE -->
        <div class="admin-card">

            <h2>Laboratory List</h2>

            <?php if (count($labs) > 0): ?>

                <div class="table-wrapper admin-table-wrapper">

                    <table class="table">

                        <thead>
                            <tr>
                                <th>ID</th>
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

                            <?php foreach ($labs as $lab): ?>

                                <tr>

                                    <td>
                                        <?= (int) $lab['lab_id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($lab['lab_code']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($lab['lab_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($lab['faculty_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($lab['department_name']) ?>
                                    </td>

                                    <td>
                                        <span class="badge badge-info">
                                            <?= htmlspecialchars($lab['lab_type']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($lab['location'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($lab['phone'] ?? '-') ?>
                                    </td>

                                    <td>
                                        <span class="badge badge-success">
                                            <?= (int) $lab['active_station_count'] ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= (int) $lab['total_station_count'] ?>
                                    </td>

                                    <td>
                                        <a
                                            href="../lab-detail.php?id=<?= (int) $lab['lab_id'] ?>"
                                            class="btn btn-outline"
                                        >
                                            View Public Detail
                                        </a>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="alert alert-success">
                    No laboratory found.
                </div>

            <?php endif; ?>

        </div>

    </div>
</section>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>