<?php

declare(strict_types=1);

/**
 * Equipment helper functions.
 * These functions are used for listing and reading equipment assets.
 */

if (!function_exists('getEquipmentStatusOptions')) {
    function getEquipmentStatusOptions(): array
    {
        return [
            'available' => 'Available',
            'maintenance' => 'Maintenance',
            'passive' => 'Passive',
        ];
    }
}

if (!function_exists('formatEquipmentStatus')) {
    function formatEquipmentStatus(?string $status): string
    {
        $statuses = getEquipmentStatusOptions();

        if ($status === null || trim($status) === '') {
            return 'Unknown';
        }

        return $statuses[$status] ?? ucwords(str_replace('_', ' ', $status));
    }
}

if (!function_exists('getEquipmentTypes')) {
    function getEquipmentTypes(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT
                equipment_type_id,
                equipment_name,
                category,
                description
            FROM equipment_types
            ORDER BY category ASC, equipment_name ASC
        ");

        return $stmt->fetchAll();
    }
}

if (!function_exists('getEquipmentTypeById')) {
    function getEquipmentTypeById(PDO $pdo, int $equipmentTypeId): ?array
    {
        $stmt = $pdo->prepare("
            SELECT
                equipment_type_id,
                equipment_name,
                category,
                description
            FROM equipment_types
            WHERE equipment_type_id = :equipment_type_id
            LIMIT 1
        ");

        $stmt->execute([
            ':equipment_type_id' => $equipmentTypeId,
        ]);

        $equipmentType = $stmt->fetch();

        return $equipmentType ?: null;
    }
}

if (!function_exists('getEquipmentById')) {
    function getEquipmentById(PDO $pdo, int $equipmentId): ?array
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
                et.category,
                et.description AS equipment_type_description,

                l.lab_code,
                l.lab_name,
                l.location,

                w.station_code,
                w.station_name
            FROM equipment_instances ei
            INNER JOIN equipment_types et
                ON ei.equipment_type_id = et.equipment_type_id
            INNER JOIN laboratories l
                ON ei.lab_id = l.lab_id
            LEFT JOIN workstations w
                ON ei.station_id = w.station_id
                AND ei.lab_id = w.lab_id
            WHERE ei.equipment_id = :equipment_id
            LIMIT 1
        ");

        $stmt->execute([
            ':equipment_id' => $equipmentId,
        ]);

        $equipment = $stmt->fetch();

        return $equipment ?: null;
    }
}

if (!function_exists('getEquipmentByStation')) {
    function getEquipmentByStation(PDO $pdo, int $stationId): array
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
            WHERE ei.station_id = :station_id
            ORDER BY et.category ASC, et.equipment_name ASC, ei.asset_code ASC
        ");

        $stmt->execute([
            ':station_id' => $stationId,
        ]);

        return $stmt->fetchAll();
    }
}

if (!function_exists('getEquipmentByLab')) {
    function getEquipmentByLab(PDO $pdo, int $labId): array
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
                et.category,

                w.station_code,
                w.station_name
            FROM equipment_instances ei
            INNER JOIN equipment_types et
                ON ei.equipment_type_id = et.equipment_type_id
            LEFT JOIN workstations w
                ON ei.station_id = w.station_id
                AND ei.lab_id = w.lab_id
            WHERE ei.lab_id = :lab_id
            ORDER BY w.station_code ASC, et.category ASC, et.equipment_name ASC, ei.asset_code ASC
        ");

        $stmt->execute([
            ':lab_id' => $labId,
        ]);

        return $stmt->fetchAll();
    }
}

if (!function_exists('getEquipmentCategories')) {
    function getEquipmentCategories(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT DISTINCT category
            FROM equipment_types
            ORDER BY category ASC
        ");

        return $stmt->fetchAll();
    }
}

if (!function_exists('getEquipmentList')) {
    function getEquipmentList(PDO $pdo, array $filters = []): array
    {
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
                AND ei.lab_id = w.lab_id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['q'])) {
            $sql .= "
                AND (
                    ei.asset_code LIKE :search
                    OR ei.brand LIKE :search
                    OR ei.model LIKE :search
                    OR et.equipment_name LIKE :search
                    OR et.category LIKE :search
                    OR l.lab_code LIKE :search
                    OR l.lab_name LIKE :search
                    OR w.station_code LIKE :search
                    OR w.station_name LIKE :search
                )
            ";

            $params[':search'] = '%' . trim((string) $filters['q']) . '%';
        }

        if (!empty($filters['lab_id'])) {
            $sql .= " AND ei.lab_id = :lab_id";
            $params[':lab_id'] = (int) $filters['lab_id'];
        }

        if (!empty($filters['station_id'])) {
            $sql .= " AND ei.station_id = :station_id";
            $params[':station_id'] = (int) $filters['station_id'];
        }

        if (!empty($filters['category'])) {
            $sql .= " AND et.category = :category";
            $params[':category'] = trim((string) $filters['category']);
        }

        if (!empty($filters['status'])) {
            $allowedStatuses = array_keys(getEquipmentStatusOptions());

            if (in_array($filters['status'], $allowedStatuses, true)) {
                $sql .= " AND ei.status = :status";
                $params[':status'] = $filters['status'];
            }
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

        return $stmt->fetchAll();
    }
}

if (!function_exists('countEquipmentByStatus')) {
    function countEquipmentByStatus(PDO $pdo): array
    {
        $stmt = $pdo->query("
            SELECT
                status,
                COUNT(*) AS total_count
            FROM equipment_instances
            GROUP BY status
            ORDER BY status ASC
        ");

        return $stmt->fetchAll();
    }
}