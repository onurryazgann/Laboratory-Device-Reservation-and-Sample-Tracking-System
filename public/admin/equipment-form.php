<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/auth_helper.php';
require_once __DIR__ . '/../../helpers/lab_helper.php';
require_once __DIR__ . '/../../helpers/equipment_helper.php';
require_once __DIR__ . '/../../includes/csrf.php';

if (!isLoggedIn()) {
    header('Location: ' . BASE_URL . 'login.php');
    exit;
}

if (!isAdmin()) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$pageTitle = 'Equipment Form';
$errors = [];
$successMessage = '';

$statusOptions = getEquipmentStatusOptions();
$equipmentTypes = getEquipmentTypes($pdo);
$labs = getAllLabs($pdo);

$stations = $pdo->query("
    SELECT
        w.station_id,
        w.lab_id,
        w.station_code,
        w.station_name,
        l.lab_code,
        l.lab_name
    FROM workstations w
    INNER JOIN laboratories l ON w.lab_id = l.lab_id
    ORDER BY l.lab_code ASC, w.station_code ASC
")->fetchAll();

$equipmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$isEditMode = $equipmentId !== null && $equipmentId !== false && $equipmentId > 0;

$form = [
    'equipment_type_id' => '',
    'lab_id' => '',
    'station_id' => '',
    'asset_code' => '',
    'brand' => '',
    'model' => '',
    'status' => 'available',
    'notes' => '',
];

if ($isEditMode) {
    $existingEquipment = getEquipmentById($pdo, (int) $equipmentId);

    if (!$existingEquipment) {
        header('Location: ' . BASE_URL . 'admin/equipment.php');
        exit;
    }

    $form = [
        'equipment_type_id' => (string) $existingEquipment['equipment_type_id'],
        'lab_id' => (string) $existingEquipment['lab_id'],
        'station_id' => (string) ($existingEquipment['station_id'] ?? ''),
        'asset_code' => (string) $existingEquipment['asset_code'],
        'brand' => (string) ($existingEquipment['brand'] ?? ''),
        'model' => (string) ($existingEquipment['model'] ?? ''),
        'status' => (string) $existingEquipment['status'],
        'notes' => (string) ($existingEquipment['notes'] ?? ''),
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfVerify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Security token is invalid. Please refresh the page and try again.';
    }

    $form = [
        'equipment_type_id' => trim((string) ($_POST['equipment_type_id'] ?? '')),
        'lab_id' => trim((string) ($_POST['lab_id'] ?? '')),
        'station_id' => trim((string) ($_POST['station_id'] ?? '')),
        'asset_code' => strtoupper(trim((string) ($_POST['asset_code'] ?? ''))),
        'brand' => trim((string) ($_POST['brand'] ?? '')),
        'model' => trim((string) ($_POST['model'] ?? '')),
        'status' => trim((string) ($_POST['status'] ?? 'available')),
        'notes' => trim((string) ($_POST['notes'] ?? '')),
    ];

    if (!filter_var($form['equipment_type_id'], FILTER_VALIDATE_INT)) {
        $errors[] = 'Equipment type is required.';
    }

    if (!filter_var($form['lab_id'], FILTER_VALIDATE_INT)) {
        $errors[] = 'Laboratory is required.';
    }

    if ($form['station_id'] !== '' && !filter_var($form['station_id'], FILTER_VALIDATE_INT)) {
        $errors[] = 'Selected station is invalid.';
    }

    if ($form['asset_code'] === '') {
        $errors[] = 'Asset code is required.';
    }

    if (!array_key_exists($form['status'], $statusOptions)) {
        $errors[] = 'Valid equipment status is required.';
    }

    if (!$errors && $form['station_id'] !== '') {
        $stmt = $pdo->prepare("
            SELECT station_id
            FROM workstations
            WHERE station_id = :station_id
              AND lab_id = :lab_id
            LIMIT 1
        ");

        $stmt->execute([
            ':station_id' => (int) $form['station_id'],
            ':lab_id' => (int) $form['lab_id'],
        ]);

        if (!$stmt->fetch()) {
            $errors[] = 'Selected station does not belong to the selected laboratory.';
        }
    }

    if (!$errors) {
        try {
            if ($isEditMode) {
                $stmt = $pdo->prepare("
                    UPDATE equipment_instances
                    SET
                        equipment_type_id = :equipment_type_id,
                        lab_id = :lab_id,
                        station_id = :station_id,
                        asset_code = :asset_code,
                        brand = :brand,
                        model = :model,
                        status = :status,
                        notes = :notes
                    WHERE equipment_id = :equipment_id
                ");

                $stmt->execute([
                    ':equipment_type_id' => (int) $form['equipment_type_id'],
                    ':lab_id' => (int) $form['lab_id'],
                    ':station_id' => $form['station_id'] !== '' ? (int) $form['station_id'] : null,
                    ':asset_code' => $form['asset_code'],
                    ':brand' => $form['brand'] !== '' ? $form['brand'] : null,
                    ':model' => $form['model'] !== '' ? $form['model'] : null,
                    ':status' => $form['status'],
                    ':notes' => $form['notes'] !== '' ? $form['notes'] : null,
                    ':equipment_id' => (int) $equipmentId,
                ]);

                $successMessage = 'Equipment updated successfully.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO equipment_instances
                        (equipment_type_id, lab_id, station_id, asset_code, brand, model, status, notes)
                    VALUES
                        (:equipment_type_id, :lab_id, :station_id, :asset_code, :brand, :model, :status, :notes)
                ");

                $stmt->execute([
                    ':equipment_type_id' => (int) $form['equipment_type_id'],
                    ':lab_id' => (int) $form['lab_id'],
                    ':station_id' => $form['station_id'] !== '' ? (int) $form['station_id'] : null,
                    ':asset_code' => $form['asset_code'],
                    ':brand' => $form['brand'] !== '' ? $form['brand'] : null,
                    ':model' => $form['model'] !== '' ? $form['model'] : null,
                    ':status' => $form['status'],
                    ':notes' => $form['notes'] !== '' ? $form['notes'] : null,
                ]);

                $equipmentId = (int) $pdo->lastInsertId();
                $isEditMode = true;
                $successMessage = 'Equipment created successfully.';
            }
        } catch (Throwable $e) {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $errors[] = 'Database error: ' . $e->getMessage();
            } else {
                $errors[] = 'Equipment could not be saved.';
            }
        }
    }
}

require_once __DIR__ . '/../../includes/header.php';
?>

<main class="page-container admin-form-page">
    <section class="card">
        <h1><?= $isEditMode ? 'Edit Equipment' : 'Add Equipment' ?></h1>
        <p>Manage physical equipment assets, laboratory ownership, station assignment and asset status.</p>

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
                Equipment Type
                <select name="equipment_type_id" required>
                    <option value="">Select equipment type</option>
                    <?php foreach ($equipmentTypes as $type): ?>
                        <option value="<?= (int) $type['equipment_type_id'] ?>"
                            <?= (string) $form['equipment_type_id'] === (string) $type['equipment_type_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($type['category'] . ' - ' . $type['equipment_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

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
                Station
                <select name="station_id">
                    <option value="">No station / lab-level equipment</option>
                    <?php foreach ($stations as $station): ?>
                        <option value="<?= (int) $station['station_id'] ?>"
                            <?= (string) $form['station_id'] === (string) $station['station_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($station['lab_code'] . ' - ' . $station['station_code'] . ' - ' . $station['station_name'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label>
                Asset Code
                <input type="text" name="asset_code" value="<?= htmlspecialchars($form['asset_code'], ENT_QUOTES, 'UTF-8') ?>" required>
            </label>

            <label>
                Brand
                <input type="text" name="brand" value="<?= htmlspecialchars($form['brand'], ENT_QUOTES, 'UTF-8') ?>">
            </label>

            <label>
                Model
                <input type="text" name="model" value="<?= htmlspecialchars($form['model'], ENT_QUOTES, 'UTF-8') ?>">
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
                    <?= $isEditMode ? 'Update Equipment' : 'Create Equipment' ?>
                </button>

                <a class="btn btn-secondary" href="<?= BASE_URL ?>admin/equipment.php">
                    Back to Equipment
                </a>
            </div>
        </form>
    </section>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>