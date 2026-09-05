<?php

function reservationBookingDate(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value ? $date : null;
}

function reservationBookingMinutes(string $value): ?int
{
    if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])(?::00)?$/D', $value, $parts) !== 1) {
        return null;
    }
    return (int)$parts[1] * 60 + (int)$parts[2];
}

function reservationBookingTime(int $minutes): string
{
    return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
}

/** @param list<array<string,mixed>> $bookings */
function reservationBookingPeakOverlap(array $bookings, string $start, string $end): int
{
    $events = [];
    foreach ($bookings as $booking) {
        if (isset($booking['status']) && !in_array($booking['status'], ['pending', 'confirmed'], true)) {
            continue;
        }
        $from = max($start, (string)$booking['start_time']);
        $until = min($end, (string)$booking['end_time']);
        if ($from >= $until) {
            continue;
        }
        $events[$from] = ($events[$from] ?? 0) + 1;
        $events[$until] = ($events[$until] ?? 0) - 1;
    }
    ksort($events, SORT_STRING);
    $current = 0;
    $peak = 0;
    // Group equal endpoints: a booking ending at a boundary releases its place immediately.
    foreach ($events as $delta) {
        $current += $delta;
        $peak = max($peak, $current);
    }
    return $peak;
}

/**
 * @param array<string,mixed> $resource
 * @param array<string,mixed>|null $hours
 * @param list<array<string,mixed>> $slots
 * @return array{error:string,start_time:string,end_time:string,limit:int}
 */
function reservationValidateBookingTime(
    array $resource,
    ?array $hours,
    array $slots,
    bool $blocked,
    string $date,
    string $start,
    ?string $end,
    int $partySize,
    bool $enforceAdvanceWindow = true,
    ?DateTimeImmutable $now = null
): array {
    $result = ['error' => '', 'start_time' => '', 'end_time' => '', 'limit' => 0];
    $fail = static fn (string $error): array => array_replace($result, ['error' => $error]);
    $day = reservationBookingDate($date);
    if ($day === null) {
        return $fail('date');
    }
    if ((int)($resource['is_active'] ?? 0) !== 1) {
        return $fail('resource');
    }
    if ($partySize < 1 || ((int)$resource['capacity'] > 0 && $partySize > (int)$resource['capacity'])) {
        return $fail('party_size');
    }
    if ($blocked || $hours === null || !empty($hours['is_closed'])) {
        return $fail('closed');
    }
    $open = reservationBookingMinutes((string)$hours['open_time']);
    $close = reservationBookingMinutes((string)$hours['close_time']);
    $from = reservationBookingMinutes($start);
    $until = $end !== null ? reservationBookingMinutes($end) : null;
    if ($open === null || $close === null || $open >= $close || $from === null) {
        return $fail('time');
    }
    $mode = (string)$resource['slot_mode'];
    if ($mode === 'duration') {
        $duration = (int)$resource['slot_duration_min'];
        if ($duration < 1 || ($from - $open) % $duration !== 0) {
            return $fail('duration');
        }
        if ($end === null) {
            $until = $from + $duration;
        }
        if ($until === null || $until - $from !== $duration) {
            return $fail('duration');
        }
    }
    if ($until === null || $from < $open || $until > $close || $from >= $until) {
        return $fail('time');
    }
    $limit = (int)$resource['max_concurrent'];
    if ($mode === 'slots') {
        $limit = 0;
        foreach ($slots as $slot) {
            if (reservationBookingMinutes((string)$slot['start_time']) === $from
                && reservationBookingMinutes((string)$slot['end_time']) === $until) {
                $limit = (int)$slot['max_bookings'];
                break;
            }
        }
        if ($limit < 1) {
            return $fail('slot');
        }
    } elseif ($mode === 'range') {
        if (($from - $open) % 30 !== 0 || ($until - $open) % 30 !== 0) {
            return $fail('time');
        }
    } elseif ($mode !== 'duration') {
        return $fail('time');
    }
    if ($limit < 1) {
        return $fail('capacity');
    }
    $startTime = reservationBookingTime($from);
    $endTime = reservationBookingTime($until);
    $startAt = new DateTimeImmutable($date . ' ' . $startTime);
    $endAt = new DateTimeImmutable($date . ' ' . $endTime);
    // Reject wall times normalized across a daylight-saving gap.
    if ($startAt->format('Y-m-d H:i:s') !== $date . ' ' . $startTime
        || $endAt->format('Y-m-d H:i:s') !== $date . ' ' . $endTime) {
        return $fail('time');
    }
    if ($enforceAdvanceWindow) {
        $now ??= new DateTimeImmutable();
        $today = $now->setTime(0, 0);
        $maxDay = $today->modify('+' . max(0, (int)$resource['max_advance_days']) . ' days');
        if ($day < $today || $day > $maxDay
            || $startAt->getTimestamp() - $now->getTimestamp() < max(0, (int)$resource['min_advance_hours']) * 3600) {
            return $fail('advance');
        }
    }
    return ['error' => '', 'start_time' => $startTime, 'end_time' => $endTime, 'limit' => $limit];
}

function reservationBookingValidationMessage(string $error): string
{
    return match ($error) {
        'date' => 'Vyberte skutečné datum rezervace.',
        'resource' => 'Vybraný rezervační zdroj již není dostupný.',
        'guest' => 'Tento zdroj již nepovoluje rezervace bez přihlášení.',
        'party_size' => 'Počet osob musí být kladný a nesmí překročit kapacitu zdroje.',
        'closed' => 'V tomto dni je zdroj uzavřený nebo blokovaný. Vyberte jiný den.',
        'slot' => 'Vyberte jeden ze skutečně vypsaných časových slotů.',
        'duration' => 'Vyberte čas začátku a délku podle vypsaných termínů zdroje.',
        'advance' => 'Termín nesplňuje povolený předstih rezervace. Vyberte jiný termín.',
        'capacity' => 'Vybraný čas byl právě obsazen. Nabídka byla aktualizována, vyberte prosím jiný čas.',
        default => 'Vyberte platný začátek a pozdější konec v otevírací době zdroje.',
    };
}

/** @return array<string,mixed>|null */
function reservationLockBookingResource(PDO $pdo, int $resourceId): ?array
{
    if (!$pdo->inTransaction()) {
        throw new LogicException('Reservation validation requires a transaction held through INSERT.');
    }
    $stmt = $pdo->prepare('SELECT * FROM cms_res_resources WHERE id = ? FOR UPDATE');
    $stmt->execute([$resourceId]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($resource) ? $resource : null;
}

/** @return array<string,mixed> */
function reservationValidateBookingInsert(
    PDO $pdo,
    int $resourceId,
    string $date,
    string $start,
    ?string $end,
    int $partySize,
    bool $enforceAdvanceWindow = true,
    bool $isGuest = false
): array {
    // Both entrypoints lock the same existing row, including for an empty booking day.
    $resource = reservationLockBookingResource($pdo, $resourceId);
    if ($resource === null) {
        return ['error' => 'resource'];
    }
    if ($enforceAdvanceWindow && $isGuest && empty($resource['allow_guests'])) {
        return ['error' => 'guest'];
    }
    $day = reservationBookingDate($date);
    if ($day === null) {
        return ['error' => 'date'];
    }
    $dayOfWeek = (int)$day->format('N') - 1;
    $stmt = $pdo->prepare('SELECT * FROM cms_res_hours WHERE resource_id = ? AND day_of_week = ? FOR UPDATE');
    $stmt->execute([$resourceId, $dayOfWeek]);
    $hours = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare('SELECT id FROM cms_res_blocked WHERE resource_id = ? AND blocked_date = ? FOR UPDATE');
    $stmt->execute([$resourceId, $date]);
    $blocked = $stmt->fetchColumn() !== false;
    $stmt = $pdo->prepare('SELECT * FROM cms_res_slots WHERE resource_id = ? AND day_of_week = ? ORDER BY start_time, id FOR UPDATE');
    $stmt->execute([$resourceId, $dayOfWeek]);
    $validation = reservationValidateBookingTime(
        $resource,
        is_array($hours) ? $hours : null,
        $stmt->fetchAll(PDO::FETCH_ASSOC),
        $blocked,
        $date,
        $start,
        $end,
        $partySize,
        $enforceAdvanceWindow
    );
    $validation['resource'] = $resource;
    if ($validation['error'] !== '') {
        return $validation;
    }
    // A locking read sees the latest committed bookings after waiting for the resource lock.
    $stmt = $pdo->prepare(
        "SELECT start_time, end_time FROM cms_res_bookings
         WHERE resource_id = ? AND booking_date = ? AND status IN ('pending', 'confirmed')
           AND start_time < ? AND end_time > ? FOR UPDATE"
    );
    $stmt->execute([$resourceId, $date, $validation['end_time'], $validation['start_time']]);
    if (reservationBookingPeakOverlap($stmt->fetchAll(PDO::FETCH_ASSOC), $validation['start_time'], $validation['end_time']) >= $validation['limit']) {
        $validation['error'] = 'capacity';
    }
    return $validation;
}

function reservationCompareAndSetBookingStatus(
    PDO $pdo,
    int $bookingId,
    string $fromStatus,
    string $toStatus,
    string $adminNote = '',
    ?string $calendarToken = null
): bool {
    $allowed = [
        'pending' => ['confirmed', 'rejected', 'cancelled', 'completed'],
        'confirmed' => ['cancelled', 'completed', 'no_show'],
        'completed' => ['no_show'],
    ];
    if (!in_array($toStatus, $allowed[$fromStatus] ?? [], true)) {
        return false;
    }
    $set = ['status = ?', 'updated_at = NOW()'];
    $params = [$toStatus];
    if ($adminNote !== '') {
        $set[] = 'admin_note = ?';
        $params[] = $adminNote;
    }
    if ($toStatus === 'cancelled') {
        $set[] = 'cancelled_at = NOW()';
    }
    if ($calendarToken !== null) {
        $set[] = 'calendar_token = ?';
        $params[] = $calendarToken;
    }
    $params[] = $bookingId;
    $params[] = $fromStatus;
    $stmt = $pdo->prepare('UPDATE cms_res_bookings SET ' . implode(', ', $set) . ' WHERE id = ? AND status = ?');
    $stmt->execute($params);
    return $stmt->rowCount() === 1;
}

function reservationRememberStatusConflict(int $bookingId, string $action, string $adminNote): void
{
    $_SESSION['reservation_status_conflict'] = [
        'booking_id' => $bookingId,
        'action' => $action,
        'admin_note' => $adminNote,
    ];
}

/** @return array{action:string,admin_note:string}|null */
function reservationTakeStatusConflict(int $bookingId): ?array
{
    $flash = $_SESSION['reservation_status_conflict'] ?? null;
    if (!is_array($flash) || ($flash['booking_id'] ?? null) !== $bookingId) {
        return null;
    }
    unset($_SESSION['reservation_status_conflict']);
    return [
        'action' => is_string($flash['action'] ?? null) ? $flash['action'] : '',
        'admin_note' => is_string($flash['admin_note'] ?? null) ? $flash['admin_note'] : '',
    ];
}

/** @param array<string,mixed> $booking */
function reservationBookingCanBeCancelled(array $booking, ?int $now = null): bool
{
    if (!in_array($booking['status'] ?? '', ['pending', 'confirmed'], true)) {
        return false;
    }
    $start = strtotime((string)$booking['booking_date'] . ' ' . (string)$booking['start_time']);
    $now ??= time();
    return $start !== false && $start > $now
        && $start - $now >= max(0, (int)$booking['cancellation_hours']) * 3600;
}

/** @param array<string,mixed> $booking */
function reservationCancelBooking(PDO $pdo, array $booking, ?int $userId = null, ?string $token = null): bool
{
    $pdo->beginTransaction();
    try {
        $resource = reservationLockBookingResource($pdo, (int)$booking['resource_id']);
        $stmt = $pdo->prepare('SELECT * FROM cms_res_bookings WHERE id = ? AND resource_id = ? FOR UPDATE');
        $stmt->execute([(int)$booking['id'], (int)$booking['resource_id']]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($resource === null || !is_array($current)
            || ($userId === null && $token === null)
            || ($userId !== null && (int)$current['user_id'] !== $userId)
            || ($token !== null && !hash_equals((string)$current['confirmation_token'], $token))) {
            $pdo->rollBack();
            return false;
        }
        $current['cancellation_hours'] = $resource['cancellation_hours'];
        if (!reservationBookingCanBeCancelled($current)
            || !reservationCompareAndSetBookingStatus($pdo, (int)$current['id'], (string)$current['status'], 'cancelled')) {
            $pdo->rollBack();
            return false;
        }
        $pdo->commit();
        return true;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
