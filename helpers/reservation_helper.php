<?php

function normalizeDateTimeForDatabase(?string $value): ?string
{
    $value = trim($value ?? '');

    if ($value === '') {
        return null;
    }

    try {
        $value = str_replace('T', ' ', $value);
        return (new DateTime($value))->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

function isValidReservationInterval(?string $startTime, ?string $endTime): bool
{
    if ($startTime === null || $endTime === null) {
        return false;
    }

    try {
        $start = new DateTime($startTime);
        $end = new DateTime($endTime);

        return $end > $start;
    } catch (Exception $e) {
        return false;
    }
}

function isReservationStartInFuture(?string $startTime): bool
{
    if ($startTime === null) {
        return false;
    }

    try {
        $start = new DateTime($startTime);
        $now = new DateTime();

        return $start >= $now;
    } catch (Exception $e) {
        return false;
    }
}

function getReservationStatusOptions(): array
{
    return [
        'active',
        'cancelled',
        'completed',
    ];
}

function isValidReservationStatus(string $status): bool
{
    return in_array($status, getReservationStatusOptions(), true);
}

function getReservationStationContext(PDO $pdo, int $stationId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            w.station_id,
            w.lab_id,
            w.station_type_id,
            w.station_code,
            w.station_name,
            w.capacity,
            w.status AS station_status,
            w.notes AS station_notes,

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
        LIMIT 1
    ");

    $stmt->execute([
        ':station_id' => $stationId,
    ]);

    $station = $stmt->fetch();

    return $station ?: null;
}

function checkAvailability(
    PDO $pdo,
    int $stationId,
    string $startTime,
    string $endTime,
    ?int $excludeReservationId = null
): bool {
    $sql = "
        SELECT COUNT(*) AS conflict_count
        FROM reservations
        WHERE station_id = :station_id
        AND status = 'active'
        AND start_time < :end_time
        AND end_time > :start_time
    ";

    $params = [
        ':station_id' => $stationId,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
    ];

    if ($excludeReservationId !== null) {
        $sql .= " AND reservation_id != :reservation_id";
        $params[':reservation_id'] = $excludeReservationId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $row = $stmt->fetch();

    return (int) ($row['conflict_count'] ?? 0) === 0;
}

function getConflictingReservations(
    PDO $pdo,
    int $stationId,
    string $startTime,
    string $endTime,
    ?int $excludeReservationId = null
): array {
    $sql = "
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
        AND r.start_time < :end_time
        AND r.end_time > :start_time
    ";

    $params = [
        ':station_id' => $stationId,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
    ];

    if ($excludeReservationId !== null) {
        $sql .= " AND r.reservation_id != :reservation_id";
        $params[':reservation_id'] = $excludeReservationId;
    }

    $sql .= " ORDER BY r.start_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function createReservation(
    PDO $pdo,
    int $userId,
    int $labId,
    int $stationId,
    string $startTime,
    string $endTime,
    ?string $purpose = null
): int {
    $stmt = $pdo->prepare("
        INSERT INTO reservations (
            user_id,
            lab_id,
            station_id,
            start_time,
            end_time,
            purpose,
            status
        ) VALUES (
            :user_id,
            :lab_id,
            :station_id,
            :start_time,
            :end_time,
            :purpose,
            'active'
        )
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':lab_id' => $labId,
        ':station_id' => $stationId,
        ':start_time' => $startTime,
        ':end_time' => $endTime,
        ':purpose' => $purpose,
    ]);

    return (int) $pdo->lastInsertId();
}

function addReservationStatusHistory(
    PDO $pdo,
    int $reservationId,
    ?string $oldStatus,
    string $newStatus,
    ?int $changedBy,
    ?string $note = null
): void {
    $stmt = $pdo->prepare("
        INSERT INTO reservation_status_history (
            reservation_id,
            old_status,
            new_status,
            changed_by,
            note
        ) VALUES (
            :reservation_id,
            :old_status,
            :new_status,
            :changed_by,
            :note
        )
    ");

    $stmt->execute([
        ':reservation_id' => $reservationId,
        ':old_status' => $oldStatus,
        ':new_status' => $newStatus,
        ':changed_by' => $changedBy,
        ':note' => $note,
    ]);
}

function syncExpiredReservations(PDO $pdo, ?int $changedBy = null): int
{
    $startedTransaction = !$pdo->inTransaction();

    try {
        if ($startedTransaction) {
            $pdo->beginTransaction();
        }

        $stmt = $pdo->query("
            SELECT reservation_id
            FROM reservations
            WHERE status = 'active'
            AND end_time < NOW()
            FOR UPDATE
        ");

        $expiredReservationIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($expiredReservationIds)) {
            if ($startedTransaction) {
                $pdo->commit();
            }

            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($expiredReservationIds), '?'));

        $updateStmt = $pdo->prepare("
            UPDATE reservations
            SET status = 'completed'
            WHERE reservation_id IN ($placeholders)
        ");

        $updateStmt->execute(array_map('intval', $expiredReservationIds));

        foreach ($expiredReservationIds as $reservationId) {
            addReservationStatusHistory(
                $pdo,
                (int) $reservationId,
                'active',
                'completed',
                $changedBy,
                'Reservation automatically completed because the end time has passed.'
            );
        }

        if ($startedTransaction) {
            $pdo->commit();
        }

        return count($expiredReservationIds);
    } catch (Exception $e) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }
}

function getUserReservations(PDO $pdo, int $userId, string $status = 'all'): array
{
    $sql = "
        SELECT
            r.reservation_id,
            r.user_id,
            r.lab_id,
            r.station_id,
            r.start_time,
            r.end_time,
            r.purpose,
            r.status,
            r.created_at,
            r.updated_at,

            l.lab_name,
            l.lab_code,
            l.lab_type,
            l.location,

            w.station_code,
            w.station_name,
            w.capacity,
            w.status AS station_status
        FROM reservations r
        INNER JOIN laboratories l
            ON r.lab_id = l.lab_id
        INNER JOIN workstations w
            ON r.station_id = w.station_id
            AND r.lab_id = w.lab_id
        WHERE r.user_id = :user_id
    ";

    $params = [
        ':user_id' => $userId,
    ];

    if (isValidReservationStatus($status)) {
        $sql .= " AND r.status = :status";
        $params[':status'] = $status;
    }

    $sql .= " ORDER BY r.start_time DESC, r.reservation_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function getReservationDetail(PDO $pdo, int $reservationId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            r.reservation_id,
            r.user_id,
            r.lab_id,
            r.station_id,
            r.start_time,
            r.end_time,
            r.purpose,
            r.status,
            r.created_at,
            r.updated_at,

            CONCAT(u.first_name, ' ', u.last_name) AS user_full_name,
            u.email AS user_email,
            u.phone AS user_phone,

            l.lab_name,
            l.lab_code,
            l.lab_type,
            l.location,

            w.station_code,
            w.station_name,
            w.capacity,
            w.status AS station_status
        FROM reservations r
        INNER JOIN users u
            ON r.user_id = u.user_id
        INNER JOIN laboratories l
            ON r.lab_id = l.lab_id
        INNER JOIN workstations w
            ON r.station_id = w.station_id
            AND r.lab_id = w.lab_id
        WHERE r.reservation_id = :reservation_id
        LIMIT 1
    ");

    $stmt->execute([
        ':reservation_id' => $reservationId,
    ]);

    $reservation = $stmt->fetch();

    return $reservation ?: null;
}

function getReservationStatusHistory(PDO $pdo, int $reservationId): array
{
    $stmt = $pdo->prepare("
        SELECT
            h.history_id,
            h.reservation_id,
            h.old_status,
            h.new_status,
            h.changed_by,
            h.changed_at,
            h.note,
            CONCAT(u.first_name, ' ', u.last_name) AS changed_by_name
        FROM reservation_status_history h
        LEFT JOIN users u
            ON h.changed_by = u.user_id
        WHERE h.reservation_id = :reservation_id
        ORDER BY h.changed_at DESC, h.history_id DESC
    ");

    $stmt->execute([
        ':reservation_id' => $reservationId,
    ]);

    return $stmt->fetchAll();
}

function cancelReservation(PDO $pdo, int $reservationId): void
{
    $stmt = $pdo->prepare("
        UPDATE reservations
        SET status = 'cancelled'
        WHERE reservation_id = :reservation_id
        AND status = 'active'
    ");

    $stmt->execute([
        ':reservation_id' => $reservationId,
    ]);
}

function getAdminReservations(PDO $pdo, array $filters = []): array
{
    $sql = "
        SELECT
            r.reservation_id,
            r.user_id,
            r.lab_id,
            r.station_id,
            r.start_time,
            r.end_time,
            r.purpose,
            r.status,
            r.created_at,
            r.updated_at,

            CONCAT(u.first_name, ' ', u.last_name) AS user_full_name,
            u.email AS user_email,

            l.lab_name,
            l.lab_code,

            w.station_code,
            w.station_name
        FROM reservations r
        INNER JOIN users u
            ON r.user_id = u.user_id
        INNER JOIN laboratories l
            ON r.lab_id = l.lab_id
        INNER JOIN workstations w
            ON r.station_id = w.station_id
            AND r.lab_id = w.lab_id
        WHERE 1 = 1
    ";

    $params = [];

    if (!empty($filters['status']) && isValidReservationStatus($filters['status'])) {
        $sql .= " AND r.status = :status";
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['lab_id']) && filter_var($filters['lab_id'], FILTER_VALIDATE_INT)) {
        $sql .= " AND r.lab_id = :lab_id";
        $params[':lab_id'] = (int) $filters['lab_id'];
    }

    if (!empty($filters['q'])) {
        $searchValue = '%' . trim($filters['q']) . '%';

        $sql .= "
            AND (
                CONCAT(u.first_name, ' ', u.last_name) LIKE :search_user_name
                OR u.email LIKE :search_email
                OR l.lab_name LIKE :search_lab_name
                OR l.lab_code LIKE :search_lab_code
                OR w.station_code LIKE :search_station_code
                OR w.station_name LIKE :search_station_name
                OR r.purpose LIKE :search_purpose
            )
        ";

        $params[':search_user_name'] = $searchValue;
        $params[':search_email'] = $searchValue;
        $params[':search_lab_name'] = $searchValue;
        $params[':search_lab_code'] = $searchValue;
        $params[':search_station_code'] = $searchValue;
        $params[':search_station_name'] = $searchValue;
        $params[':search_purpose'] = $searchValue;
    }

    if (!empty($filters['date_from'])) {
        $dateFrom = normalizeDateTimeForDatabase($filters['date_from'] . ' 00:00:00');

        if ($dateFrom !== null) {
            $sql .= " AND r.start_time >= :date_from";
            $params[':date_from'] = $dateFrom;
        }
    }

    if (!empty($filters['date_to'])) {
        $dateTo = normalizeDateTimeForDatabase($filters['date_to'] . ' 23:59:59');

        if ($dateTo !== null) {
            $sql .= " AND r.start_time <= :date_to";
            $params[':date_to'] = $dateTo;
        }
    }

    $sql .= " ORDER BY r.start_time DESC, r.reservation_id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function updateReservationStatus(PDO $pdo, int $reservationId, string $newStatus): void
{
    if (!isValidReservationStatus($newStatus)) {
        throw new InvalidArgumentException('Invalid reservation status.');
    }

    $stmt = $pdo->prepare("
        UPDATE reservations
        SET status = :new_status
        WHERE reservation_id = :reservation_id
    ");

    $stmt->execute([
        ':new_status' => $newStatus,
        ':reservation_id' => $reservationId,
    ]);
}

if (!function_exists('getReservationSlotStartHours')) {
    function getReservationSlotStartHours(): array
    {
        return [8, 10, 12, 14, 16, 18, 20, 22];
    }
}

if (!function_exists('getReservationDateWindowDays')) {
    function getReservationDateWindowDays(): int
    {
        return 15;
    }
}

if (!function_exists('isReservationDateInsideAllowedWindow')) {
    function isReservationDateInsideAllowedWindow(DateTimeInterface $date): bool
    {
        $selectedDate = new DateTime($date->format('Y-m-d'));
        $today = new DateTime('today');
        $lastSelectableDate = (clone $today)->modify('+' . (getReservationDateWindowDays() - 1) . ' days');

        return $selectedDate >= $today && $selectedDate <= $lastSelectableDate;
    }
}

if (!function_exists('validateFixedReservationSlot')) {
    function validateFixedReservationSlot(?string $startTime, ?string $endTime): array
    {
        if ($startTime === null || trim($startTime) === '') {
            return [
                'valid' => false,
                'message' => 'Start time is required.'
            ];
        }

        if ($endTime === null || trim($endTime) === '') {
            return [
                'valid' => false,
                'message' => 'End time is required.'
            ];
        }

        try {
            $start = new DateTime($startTime);
            $end = new DateTime($endTime);
        } catch (Exception $e) {
            return [
                'valid' => false,
                'message' => 'Invalid reservation date or time.'
            ];
        }

        $now = new DateTime();

        if ($start <= $now) {
            return [
                'valid' => false,
                'message' => 'Reservation start time must be in the future.'
            ];
        }

        if (!isReservationDateInsideAllowedWindow($start)) {
            return [
                'valid' => false,
                'message' => 'Reservation date must be within the next 15 days.'
            ];
        }

        $durationSeconds = $end->getTimestamp() - $start->getTimestamp();
        $expectedDurationSeconds = 2 * 60 * 60;

        if ($durationSeconds !== $expectedDurationSeconds) {
            return [
                'valid' => false,
                'message' => 'Reservation duration must be exactly 2 hours.'
            ];
        }

        $startHour = (int) $start->format('G');
        $startMinute = (int) $start->format('i');
        $startSecond = (int) $start->format('s');

        $endMinute = (int) $end->format('i');
        $endSecond = (int) $end->format('s');

        if (!in_array($startHour, getReservationSlotStartHours(), true)) {
            return [
                'valid' => false,
                'message' => 'Reservation must start at one of the allowed slot hours.'
            ];
        }

        if ($startMinute !== 0 || $startSecond !== 0 || $endMinute !== 0 || $endSecond !== 0) {
            return [
                'valid' => false,
                'message' => 'Reservation slots must start and end at exact hours.'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Reservation slot is valid.'
        ];
    }
}

if (!function_exists('isFixedReservationSlotValid')) {
    function isFixedReservationSlotValid(?string $startTime, ?string $endTime): bool
    {
        $validation = validateFixedReservationSlot($startTime, $endTime);

        return $validation['valid'] === true;
    }
}

if (!function_exists('buildReservationSlotLabel')) {
    function buildReservationSlotLabel(DateTimeInterface $start, DateTimeInterface $end): string
    {
        $startLabel = $start->format('H:i');

        if ($end->format('H:i') === '00:00') {
            $endLabel = '24:00';
        } else {
            $endLabel = $end->format('H:i');
        }

        return $startLabel . ' - ' . $endLabel;
    }
}