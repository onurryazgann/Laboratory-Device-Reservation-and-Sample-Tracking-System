<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';
require_once __DIR__ . '/../../includes/csrf.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$pageTitle = 'Station Form';
$errors = [];
$successMessage = '';

$statusOptions = [
    'active' => 'Active',
    'maintenance' => 'Maintenance',
    'passive' => 'Passive',
];

$labs = getAllLabs($pdo);

$stationTypes = $pdo->query("
    SELECT station_type_id, type_name, description
    FROM station_types
    ORDER BY type_name ASC
")->fetchAll();

$stationId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEditMode = $stationId !== null && $stationId !== false && $stationId > 0;

$form = [
    'lab_id' => '',
    'station_type_id' => '',
    'station_code' => '',
    'station_name' => '',
    'capacity' => '1',
    'status' => 'active',
    'notes' => '',
];

if ($isEditMode) {
    $stmt = $pdo->prepare("
        SELECT
            station_id,
            lab_id,
            station_type_id,
            station_code,
            station_name,
            capacity,
            status,
            notes
        FROM workstations
        WHERE station_id = :station_id
        LIMIT 1
    ");

    $stmt->execute([
        ':station_id' => (int) $stationId,
    ]);

    $existingStation = $stmt->fetch();

    if (!$existingStation) {
        header('Location: ' . BASE_URL . 'admin/stations.php');
        exit;
    }

    $form = [
        'lab_id' => (string) $existingStation['lab_id'],
        'station_type_id' => (string) $existingStation['station_type_id'],
        'station_code' => (string) $existingStation['station_code'],
        'station_name' => (string) $existingStation['station_name'],
        'capacity' => (string) $existingStation['capacity'],
        'status' => (string) $existingStation['status'],
        'notes' => (string) ($existingStation['notes'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Security token is invalid. Please refresh the page and try again.';
    }

    $form = [
        'lab_id' => trim((string) ($_POST['lab_id'] ?? '')),
        'station_type_id' => trim((string) ($_POST['station_type_id'] ?? '')),
        'station_code' => strtoupper(trim((string) ($_POST['station_code'] ?? ''))),
        'station_name' => trim((string) ($_POST['station_name'] ?? '')),
        'capacity' => trim((string) ($_POST['capacity'] ?? '1')),
        'status' => trim((string) ($_POST['status'] ?? 'active')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    if (!filter_var($form['lab_id'], FILTER_VALIDATE_INT)) {
        $errors[] = 'Laboratory is required.';
    }

    if (!filter_var($form['station_type_id'], FILTER_VALIDATE_INT)) {
        $errors[] = 'Station type is required.';
    }

    if ($form['station_code'] === '') {
        $errors[] = 'Station code is required.';
    }

    if ($form['station_name'] === '') {
        $errors[] = 'Station name is required.';
    }

    if (!filter_var($form['capacity'], FILTER_VALIDATE_INT) || (int) $form['capacity'] < 1) {
        $errors[] = 'Capacity must be a positive number.';
    }

    if (!array_key_exists($form['status'], $statusOptions)) {
        $errors[] = 'Valid station status is required.';
    }

    if (!$errors) {
        try {
            if ($isEditMode) {
                $stmt = $pdo->prepare("
                    UPDATE workstations
                    SET
                        lab_id = :lab_id,
                        station_type_id = :station_type_id,
                        station_code = :station_code,
                        station_name = :station_name,
                        capacity = :capacity,
                        status = :status,
                        notes = :notes
                    WHERE station_id = :station_id
                ");

                $stmt->execute([
                    ':lab_id' => (int) $form['lab_id'],
                    ':station_type_id' => (int) $form['station_type_id'],
                    ':station_code' => $form['station_code'],
                    ':station_name' => $form['station_name'],
                    ':capacity' => (int) $form['capacity'],
                    ':status' => $form['status'],
                    ':notes' => $form['notes'] !== '' ? $form['notes'] : null,
                    ':station_id' => (int) $stationId,
                ]);

                $successMessage = 'Station updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO workstations
                        (lab_id, station_type_id, station_code, station_name, capacity, status, notes)
                    VALUES
                        (:lab_id, :station_type_id, :station_code, :station_name, :capacity, :status, :notes)
                ");

                $stmt->execute([
                    ':lab_id' => (int) $form['lab_id'],
                    ':station_type_id' => (int) $form['station_type_id'],
                    ':station_code' => $form['station_code'],
                    ':station_name' => $form['station_name'],
                    ':capacity' => (int) $form['capacity'],
                    ':status' => $form['status'],
                    ':notes' => $form['notes'] !== '' ? $form['notes'] : null,
                ]);

                $stationId = (int) $pdo->lastInsertId();
                $isEditMode = true;
                $successMessage = 'Station created successfully.';
            }
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $errors[] = 'Database error: ' . $e->getMessage();
            } else {
                $errors[] = 'Station could not be saved.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="page-container">
    <section class="card">
        <h1><?= $isEditMode ? 'Edit Station' : 'Add Station' ?></h1>
        <p>Manage workstation code, laboratory connection, station type, capacity and status.</p>

        <?php if ($successMessage !== ''): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-error">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="form-grid">
            <?= csrfInput() ?>

            <label>
                Laboratory
                <select name="lab_id" required>
                    <option value="">Select laboratory</option>
                    <?php foreach ($labs as $lab): ?>
                        <option value="<?= (int) $lab['lab_id'] ?>"
                            <?= (string) $form['lab_id'] === (string) $lab['lab_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lab['lab_code'] . ' - ' . $lab['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Station Type
                <select name="station_type_id" required>
                    <option value="">Select station type</option>
                    <?php foreach ($stationTypes as $type): ?>
                        <option value="<?= (int) $type['station_type_id'] ?>"
                            <?= (string) $form['station_type_id'] === (string) $type['station_type_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars(formatStationTypeName($type['type_name']), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Station Code
                <input type="text" name="station_code" value="<?= htmlspecialchars($form['station_code'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>

            <label>
                Station Name
                <input type="text" name="station_name" value="<?= htmlspecialchars($form['station_name'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>

            <label>
                Capacity
                <input type="number" name="capacity" min="1" value="<?= htmlspecialchars($form['capacity'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>

            <label>
                Status
                <select name="status" required>
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                            <?= $form['status'] === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="form-full">
                Notes
                <textarea name="notes" rows="4"><?= htmlspecialchars($form['notes'], ENT_QUOTES, 'UTF-8') ?></textarea>
            </label>

            <div class="form-actions form-full">
                <button type="submit" class="btn btn-primary">
                    <?= $isEditMode ? 'Update Station' : 'Create Station' ?>
                </button>

                <a class="btn btn-secondary" href="<?= BASE_URL ?>admin/stations.php">
                    Back to Stations
                </a>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>