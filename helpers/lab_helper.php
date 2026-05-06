<?php

function getActiveFaculties(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            faculty_id,
            faculty_name
        FROM faculties
        WHERE is_active = 1
        ORDER BY faculty_name ASC
    ");

    return $stmt->fetchAll();
}

function getActiveDepartments(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            d.department_id,
            d.faculty_id,
            d.department_name,
            f.faculty_name
        FROM departments d
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE d.is_active = 1
        AND f.is_active = 1
        ORDER BY d.department_name ASC
    ");

    return $stmt->fetchAll();
}

function getLabTypes(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT DISTINCT
            l.lab_type
        FROM laboratories l
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
        ORDER BY l.lab_type ASC
    ");

    return $stmt->fetchAll();
}

function getAllLabs(PDO $pdo, array $filters = []): array
{
    $sql = "
        SELECT
            l.lab_id,
            l.department_id,
            l.lab_name,
            l.lab_code,
            l.lab_type,
            l.location,
            l.phone,
            l.description,
            l.is_active,
            l.created_at,

            d.department_name,
            d.is_active AS department_is_active,

            f.faculty_id,
            f.faculty_name,
            f.is_active AS faculty_is_active,

            COUNT(DISTINCT w.station_id) AS total_station_count,

            COALESCE(
                SUM(CASE WHEN w.status = 'active' THEN 1 ELSE 0 END),
                0
            ) AS active_station_count,

            COALESCE(
                SUM(CASE WHEN w.status = 'maintenance' THEN 1 ELSE 0 END),
                0
            ) AS maintenance_station_count,

            COALESCE(
                SUM(CASE WHEN w.status = 'passive' THEN 1 ELSE 0 END),
                0
            ) AS passive_station_count
        FROM laboratories l
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        LEFT JOIN workstations w
            ON l.lab_id = w.lab_id
        WHERE l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
    ";

    $params = [];

    if (!empty($filters['q'])) {
        $sql .= "
            AND (
                l.lab_name LIKE :search
                OR l.lab_code LIKE :search
                OR l.lab_type LIKE :search
                OR l.location LIKE :search
                OR d.department_name LIKE :search
                OR f.faculty_name LIKE :search
            )
        ";

        $params[':search'] = '%' . trim($filters['q']) . '%';
    }

    if (!empty($filters['faculty_id']) && filter_var($filters['faculty_id'], FILTER_VALIDATE_INT)) {
        $sql .= " AND f.faculty_id = :faculty_id";
        $params[':faculty_id'] = (int) $filters['faculty_id'];
    }

    if (!empty($filters['department_id']) && filter_var($filters['department_id'], FILTER_VALIDATE_INT)) {
        $sql .= " AND d.department_id = :department_id";
        $params[':department_id'] = (int) $filters['department_id'];
    }

    if (!empty($filters['lab_type'])) {
        $sql .= " AND l.lab_type = :lab_type";
        $params[':lab_type'] = trim($filters['lab_type']);
    }

    $sql .= "
        GROUP BY
            l.lab_id,
            l.department_id,
            l.lab_name,
            l.lab_code,
            l.lab_type,
            l.location,
            l.phone,
            l.description,
            l.is_active,
            l.created_at,
            d.department_name,
            d.is_active,
            f.faculty_id,
            f.faculty_name,
            f.is_active
        ORDER BY l.lab_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function getLabById(PDO $pdo, int $labId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            l.lab_id,
            l.department_id,
            l.lab_name,
            l.lab_code,
            l.lab_type,
            l.location,
            l.phone,
            l.description,
            l.is_active,
            l.created_at,

            d.department_name,
            d.is_active AS department_is_active,

            f.faculty_id,
            f.faculty_name,
            f.is_active AS faculty_is_active
        FROM laboratories l
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE l.lab_id = :lab_id
        AND l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':lab_id' => $labId,
    ]);

    $lab = $stmt->fetch();

    return $lab ?: null;
}

function getStationsByLab(PDO $pdo, int $labId): array
{
    $stmt = $pdo->prepare("
        SELECT
            w.station_id,
            w.lab_id,
            w.station_type_id,
            w.station_code,
            w.station_name,
            w.capacity,
            w.status,
            w.status AS station_status,
            w.notes,

            st.type_name,

            COUNT(ei.equipment_id) AS total_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'available' THEN 1 ELSE 0 END),
                0
            ) AS available_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'maintenance' THEN 1 ELSE 0 END),
                0
            ) AS maintenance_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'passive' THEN 1 ELSE 0 END),
                0
            ) AS passive_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'available' THEN 1 ELSE 0 END),
                0
            ) AS equipment_count
        FROM workstations w
        INNER JOIN station_types st
            ON w.station_type_id = st.station_type_id
        LEFT JOIN equipment_instances ei
            ON w.station_id = ei.station_id
            AND w.lab_id = ei.lab_id
        INNER JOIN laboratories l
            ON w.lab_id = l.lab_id
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE w.lab_id = :lab_id
        AND l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
        GROUP BY
            w.station_id,
            w.lab_id,
            w.station_type_id,
            w.station_code,
            w.station_name,
            w.capacity,
            w.status,
            w.notes,
            st.type_name
        ORDER BY
            CASE w.status
                WHEN 'active' THEN 1
                WHEN 'maintenance' THEN 2
                WHEN 'passive' THEN 3
                ELSE 4
            END,
            w.station_code ASC
    ");

    $stmt->execute([
        ':lab_id' => $labId,
    ]);

    return $stmt->fetchAll();
}

function getActiveStationsByLab(PDO $pdo, int $labId): array
{
    $stmt = $pdo->prepare("
        SELECT
            w.station_id,
            w.lab_id,
            w.station_type_id,
            w.station_code,
            w.station_name,
            w.capacity,
            w.status,
            w.status AS station_status,
            w.notes,

            st.type_name,

            COUNT(ei.equipment_id) AS total_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'available' THEN 1 ELSE 0 END),
                0
            ) AS available_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'available' THEN 1 ELSE 0 END),
                0
            ) AS equipment_count
        FROM workstations w
        INNER JOIN station_types st
            ON w.station_type_id = st.station_type_id
        LEFT JOIN equipment_instances ei
            ON w.station_id = ei.station_id
            AND w.lab_id = ei.lab_id
        INNER JOIN laboratories l
            ON w.lab_id = l.lab_id
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE w.lab_id = :lab_id
        AND w.status = 'active'
        AND l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
        GROUP BY
            w.station_id,
            w.lab_id,
            w.station_type_id,
            w.station_code,
            w.station_name,
            w.capacity,
            w.status,
            w.notes,
            st.type_name
        ORDER BY w.station_code ASC
    ");

    $stmt->execute([
        ':lab_id' => $labId,
    ]);

    return $stmt->fetchAll();
}

function getLabEquipmentSummary(PDO $pdo, int $labId): array
{
    $stmt = $pdo->prepare("
        SELECT
            et.equipment_name,
            et.category,

            COUNT(ei.equipment_id) AS total_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'available' THEN 1 ELSE 0 END),
                0
            ) AS available_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'maintenance' THEN 1 ELSE 0 END),
                0
            ) AS maintenance_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'passive' THEN 1 ELSE 0 END),
                0
            ) AS passive_count
        FROM equipment_instances ei
        INNER JOIN equipment_types et
            ON ei.equipment_type_id = et.equipment_type_id
        INNER JOIN laboratories l
            ON ei.lab_id = l.lab_id
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE ei.lab_id = :lab_id
        AND l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
        GROUP BY
            et.equipment_name,
            et.category
        ORDER BY
            et.category ASC,
            et.equipment_name ASC
    ");

    $stmt->execute([
        ':lab_id' => $labId,
    ]);

    return $stmt->fetchAll();
}

function getStationById(PDO $pdo, int $stationId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            w.station_id,
            w.lab_id,
            w.station_type_id,
            w.station_code,
            w.station_name,
            w.capacity,
            w.status,
            w.status AS station_status,
            w.notes,

            st.type_name,

            l.lab_name,
            l.lab_code,
            l.lab_type,
            l.location,
            l.phone,
            l.description AS lab_description,
            l.is_active AS lab_is_active,

            d.department_id,
            d.department_name,
            d.is_active AS department_is_active,

            f.faculty_id,
            f.faculty_name,
            f.is_active AS faculty_is_active
        FROM workstations w
        INNER JOIN station_types st
            ON w.station_type_id = st.station_type_id
        INNER JOIN laboratories l
            ON w.lab_id = l.lab_id
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE w.station_id = :station_id
        AND l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':station_id' => $stationId,
    ]);

    $station = $stmt->fetch();

    return $station ?: null;
}

function getStationEquipment(PDO $pdo, int $stationId): array
{
    $stmt = $pdo->prepare("
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
            et.category
        FROM equipment_instances ei
        INNER JOIN equipment_types et
            ON ei.equipment_type_id = et.equipment_type_id
        INNER JOIN workstations w
            ON ei.station_id = w.station_id
            AND ei.lab_id = w.lab_id
        INNER JOIN laboratories l
            ON w.lab_id = l.lab_id
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE ei.station_id = :station_id
        AND l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
        ORDER BY
            CASE ei.status
                WHEN 'available' THEN 1
                WHEN 'maintenance' THEN 2
                WHEN 'passive' THEN 3
                ELSE 4
            END,
            et.category ASC,
            et.equipment_name ASC,
            ei.asset_code ASC
    ");

    $stmt->execute([
        ':station_id' => $stationId,
    ]);

    return $stmt->fetchAll();
}

function getStationEquipmentSummary(PDO $pdo, int $stationId): array
{
    $stmt = $pdo->prepare("
        SELECT
            COUNT(ei.equipment_id) AS total_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'available' THEN 1 ELSE 0 END),
                0
            ) AS available_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'maintenance' THEN 1 ELSE 0 END),
                0
            ) AS maintenance_equipment_count,

            COALESCE(
                SUM(CASE WHEN ei.status = 'passive' THEN 1 ELSE 0 END),
                0
            ) AS passive_equipment_count
        FROM equipment_instances ei
        INNER JOIN workstations w
            ON ei.station_id = w.station_id
            AND ei.lab_id = w.lab_id
        INNER JOIN laboratories l
            ON w.lab_id = l.lab_id
        INNER JOIN departments d
            ON l.department_id = d.department_id
        INNER JOIN faculties f
            ON d.faculty_id = f.faculty_id
        WHERE ei.station_id = :station_id
        AND l.is_active = 1
        AND d.is_active = 1
        AND f.is_active = 1
    ");

    $stmt->execute([
        ':station_id' => $stationId,
    ]);

    $summary = $stmt->fetch();

    return $summary ?: [
        'total_equipment_count' => 0,
        'available_equipment_count' => 0,
        'maintenance_equipment_count' => 0,
        'passive_equipment_count' => 0,
    ];
}

function getCurrentReservationByStation(PDO $pdo, int $stationId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            r.reservation_id,
            r.user_id,
            r.lab_id,
            r.station_id,
            r.start_time,
            r.end_time,
            r.status,
            r.purpose,

            CONCAT(u.first_name, ' ', u.last_name) AS user_full_name,
            u.email AS user_email
        FROM reservations r
        INNER JOIN users u
            ON r.user_id = u.user_id
        WHERE r.station_id = :station_id
        AND r.status = 'active'
        AND r.start_time <= NOW()
        AND r.end_time >= NOW()
        ORDER BY r.start_time ASC
        LIMIT 1
    ");

    $stmt->execute([
        ':station_id' => $stationId,
    ]);

    $reservation = $stmt->fetch();

    return $reservation ?: null;
}

function getUpcomingReservationsByStation(PDO $pdo, int $stationId, int $limit = 5): array
{
    $limit = max(1, min(50, $limit));

    $stmt = $pdo->prepare("
        SELECT
            r.reservation_id,
            r.user_id,
            r.lab_id,
            r.station_id,
            r.start_time,
            r.end_time,
            r.status,
            r.purpose,

            CONCAT(u.first_name, ' ', u.last_name) AS user_full_name,
            u.email AS user_email
        FROM reservations r
        INNER JOIN users u
            ON r.user_id = u.user_id
        WHERE r.station_id = :station_id
        AND r.status = 'active'
        AND r.end_time >= NOW()
        ORDER BY r.start_time ASC
        LIMIT $limit
    ");

    $stmt->execute([
        ':station_id' => $stationId,
    ]);

    return $stmt->fetchAll();
}

function getStationComputedAvailability(PDO $pdo, int $stationId): array
{
    $station = getStationById($pdo, $stationId);

    if (!$station) {
        return [
            'is_available' => false,
            'status_label' => 'Not Found',
            'reason' => 'Station was not found or its laboratory is not active.',
            'current_reservation' => null,
        ];
    }

    if ($station['status'] !== 'active') {
        return [
            'is_available' => false,
            'status_label' => ucfirst($station['status']),
            'reason' => 'Station status is not active.',
            'current_reservation' => null,
        ];
    }

    $currentReservation = getCurrentReservationByStation($pdo, $stationId);

    if ($currentReservation) {
        return [
            'is_available' => false,
            'status_label' => 'Reserved Now',
            'reason' => 'Station is currently reserved.',
            'current_reservation' => $currentReservation,
        ];
    }

    return [
        'is_available' => true,
        'status_label' => 'Available',
        'reason' => 'Station is active and not currently reserved.',
        'current_reservation' => null,
    ];
}
function formatStationTypeName(?string $typeName): string
{
    $labels = [
        'computer_desk' => 'Computer Desk',
        'network_desk' => 'Network Desk',
        'electronics_bench' => 'Electronics Bench',
        'machine_station' => 'Machine Station',
        'general_study_desk' => 'General Study Desk',
    ];

    if ($typeName === null || trim($typeName) === '') {
        return 'Unknown Type';
    }

    return $labels[$typeName] ?? ucwords(str_replace('_', ' ', $typeName));
}