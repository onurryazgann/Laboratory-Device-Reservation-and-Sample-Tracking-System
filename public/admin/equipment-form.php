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

$pageTitle = $isEditMode ? 'Edit Equipment' : 'Add Equipment';
$pageCss = 'admin-forms.css';

$totalEquipmentTypes = count($equipmentTypes);
$totalLabs = count($labs);
$totalStations = count($stations);

$currentStatusLabel = $statusOptions[$form['status']] ?? 'Available';

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="page-container adminform-page">
    <section class="adminform-hero reveal-on-scroll">
        <div class="adminform-hero-content">
            <span class="adminform-eyebrow">
                Equipment Registry
            </span>

            <h1 class="adminform-title">
                <?= $isEditMode ? 'Edit equipment asset.' : 'Add a new equipment asset.' ?>
            </h1>

            <p class="adminform-description">
                Manage device identity, laboratory ownership, station assignment and operational status from one clean admin workspace.
            </p>

            <div class="adminform-hero-actions">
                <a class="adminform-btn adminform-btn-primary" href="<?= BASE_URL ?>admin/equipment.php">
                    Equipment List
                </a>

                <a class="adminform-btn adminform-btn-light" href="<?= BASE_URL ?>admin/index.php">
                    Admin Dashboard
                </a>
            </div>
        </div>

        <div class="adminform-hero-visual" aria-hidden="true">
            <div class="adminform-mini-panel">
                <div class="adminform-mini-header">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>

                <div class="adminform-mini-body">
                    <div class="adminform-mini-title-row">
                        <div>
                            <small>Asset Workflow</small>
                            <strong><?= $isEditMode ? 'Update Mode' : 'Create Mode' ?></strong>
                        </div>

                        <span><?= htmlspecialchars($currentStatusLabel, ENT_QUOTES, 'UTF-8') ?></span>
                    </div>

                    <div class="adminform-mini-list">
                        <div class="adminform-mini-item is-active">
                            <span>01</span>
                            <div>
                                <strong>Asset Identity</strong>
                                <small>Type, code, brand and model.</small>
                            </div>
                        </div>

                        <div class="adminform-mini-item">
                            <span>02</span>
                            <div>
                                <strong>Laboratory Link</strong>
                                <small>Assign lab-level or station-level ownership.</small>
                            </div>
                        </div>

                        <div class="adminform-mini-item">
                            <span>03</span>
                            <div>
                                <strong>Operational Status</strong>
                                <small>Track available, maintenance or passive assets.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="adminform-floating-chip adminform-chip-one">
                <span>✓</span>
                <?= $isEditMode ? 'Editable Asset' : 'New Asset' ?>
            </div>

            <div class="adminform-floating-chip adminform-chip-two">
                <span>↗</span>
                Station Aware
            </div>
        </div>
    </section>

    <section class="adminform-kpi-grid reveal-on-scroll" aria-label="Equipment form summary">
        <div class="adminform-kpi-card">
            <span>Equipment Types</span>
            <strong><?= (int) $totalEquipmentTypes ?></strong>
            <p>Registered equipment categories available for assignment.</p>
        </div>

        <div class="adminform-kpi-card">
            <span>Laboratories</span>
            <strong><?= (int) $totalLabs ?></strong>
            <p>Laboratory ownership records connected to assets.</p>
        </div>

        <div class="adminform-kpi-card">
            <span>Stations</span>
            <strong><?= (int) $totalStations ?></strong>
            <p>Available workstation targets for station-level equipment.</p>
        </div>
    </section>

    <section class="adminform-card reveal-on-scroll">
        <div class="adminform-section-header">
            <div>
                <span class="adminform-section-label">
                    <?= $isEditMode ? 'Edit Form' : 'Create Form' ?>
                </span>

                <h2>
                    <?= $isEditMode ? 'Update equipment information.' : 'Create equipment information.' ?>
                </h2>

                <p>
                    Required fields are equipment type, laboratory, asset code and status. Station is optional for lab-level shared equipment.
                </p>
            </div>

            <span class="adminform-status-pill">
                <?= $isEditMode ? 'Editing' : 'Creating' ?>
            </span>
        </div>

        <?php if ($successMessage !== ''): ?>
            <div class="adminform-alert adminform-alert-success">
                <?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="adminform-alert adminform-alert-error">
                <strong>Please check the following:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="adminform-form" novalidate>
            <?= csrfInput() ?>

            <div class="adminform-section">
                <div class="adminform-section-title">
                    <span>01</span>
                    <div>
                        <h3>Asset Identity</h3>
                        <p>Define the equipment category and its unique asset code.</p>
                    </div>
                </div>

                <div class="adminform-grid">
                    <label class="adminform-field">
                        <span>Equipment Type</span>
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

                    <label class="adminform-field">
                        <span>Asset Code</span>
                        <input
                            type="text"
                            name="asset_code"
                            value="<?= htmlspecialchars($form['asset_code'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: CENG-PC-001"
                            required
                        >
                    </label>
                </div>
            </div>

            <div class="adminform-section">
                <div class="adminform-section-title">
                    <span>02</span>
                    <div>
                        <h3>Laboratory Placement</h3>
                        <p>Connect the equipment to a laboratory and optionally to a specific station.</p>
                    </div>
                </div>

                <div class="adminform-grid">
                    <label class="adminform-field">
                        <span>Laboratory</span>
                        <select name="lab_id" required data-adminform-lab-select>
                            <option value="">Select laboratory</option>
                            <?php foreach ($labs as $lab): ?>
                                <option value="<?= (int) $lab['lab_id'] ?>"
                                    <?= (string) $form['lab_id'] === (string) $lab['lab_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lab['lab_code'] . ' - ' . $lab['lab_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="adminform-field">
                        <span>Station</span>
                        <select name="station_id" data-adminform-station-select>
                            <option value="">No station / lab-level equipment</option>
                            <?php foreach ($stations as $station): ?>
                                <option
                                    value="<?= (int) $station['station_id'] ?>"
                                    data-lab-id="<?= (int) $station['lab_id'] ?>"
                                    <?= (string) $form['station_id'] === (string) $station['station_id'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($station['lab_code'] . ' - ' . $station['station_code'] . ' - ' . $station['station_name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
            </div>

            <div class="adminform-section">
                <div class="adminform-section-title">
                    <span>03</span>
                    <div>
                        <h3>Device Details</h3>
                        <p>Add brand and model information for easier inventory reading.</p>
                    </div>
                </div>

                <div class="adminform-grid">
                    <label class="adminform-field">
                        <span>Brand</span>
                        <input
                            type="text"
                            name="brand"
                            value="<?= htmlspecialchars($form['brand'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: Dell, Cisco, Tektronix"
                        >
                    </label>

                    <label class="adminform-field">
                        <span>Model</span>
                        <input
                            type="text"
                            name="model"
                            value="<?= htmlspecialchars($form['model'], ENT_QUOTES, 'UTF-8') ?>"
                            placeholder="Example: OptiPlex 7010"
                        >
                    </label>
                </div>
            </div>

            <div class="adminform-section">
                <div class="adminform-section-title">
                    <span>04</span>
                    <div>
                        <h3>Status & Notes</h3>
                        <p>Set whether the asset is usable, under maintenance or passive.</p>
                    </div>
                </div>

                <div class="adminform-grid">
                    <label class="adminform-field">
                        <span>Status</span>
                        <select name="status" required>
                            <?php foreach ($statusOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                                    <?= $form['status'] === $value ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label class="adminform-field adminform-field-muted">
                        <span>Current Mode</span>
                        <input
                            type="text"
                            value="<?= $isEditMode ? 'Existing equipment record' : 'New equipment record' ?>"
                            disabled
                        >
                    </label>

                    <label class="adminform-field adminform-field-full">
                        <span>Notes</span>
                        <textarea
                            name="notes"
                            rows="5"
                            placeholder="Optional internal notes about condition, location, maintenance or usage."
                        ><?= htmlspecialchars($form['notes'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </label>
                </div>
            </div>

            <div class="adminform-actions">
                <button type="submit" class="adminform-btn adminform-btn-primary">
                    <?= $isEditMode ? 'Update Equipment' : 'Create Equipment' ?>
                </button>

                <a class="adminform-btn adminform-btn-light" href="<?= BASE_URL ?>admin/equipment.php">
                    Back to Equipment
                </a>
            </div>
        </form>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const labSelect = document.querySelector('[data-adminform-lab-select]');
    const stationSelect = document.querySelector('[data-adminform-station-select]');

    if (!labSelect || !stationSelect) {
        return;
    }

    const allStationOptions = Array.from(stationSelect.options);

    function syncStationOptions() {
        const selectedLabId = labSelect.value;
        const currentStationValue = stationSelect.value;

        allStationOptions.forEach(function (option, index) {
            if (index === 0) {
                option.hidden = false;
                option.disabled = false;
                return;
            }

            const optionLabId = option.getAttribute('data-lab-id');
            const shouldShow = selectedLabId === '' || optionLabId === selectedLabId;

            option.hidden = !shouldShow;
            option.disabled = !shouldShow;
        });

        const selectedOption = stationSelect.options[stationSelect.selectedIndex];

        if (selectedOption && selectedOption.disabled) {
            stationSelect.value = '';
        } else {
            stationSelect.value = currentStationValue;
        }
    }

    labSelect.addEventListener('change', function () {
        stationSelect.value = '';
        syncStationOptions();
    });

    syncStationOptions();

    const revealItems = document.querySelectorAll('.reveal-on-scroll');

    if ('IntersectionObserver' in window && revealItems.length > 0) {
        const observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            });
        }, {
            threshold: 0.12
        });

        revealItems.forEach(function (item) {
            observer.observe(item);
        });
    } else {
        revealItems.forEach(function (item) {
            item.classList.add('is-visible');
        });
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>