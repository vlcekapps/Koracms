<?php

declare(strict_types=1);

/**
 * Included by main's HTTP suite; defining this function performs no I/O.
 * @param array{cookie:string,csrf:string} $adminSession
 * @return list<string>
 */
function rcReservationAuditHttpChecks(PDO $pdo, string $baseUrl, array $adminSession): array
{
    $issues = [];
    $prefix = 'rc-reservation-' . bin2hex(random_bytes(8));
    $resourceId = $accountId = 0;
    // Only this fixture contact is deliberately non-deliverable; the admin session is unchanged.
    $fixtureEmail = $prefix . '.invalid';
    $oldModule = getSetting('module_reservations', '0');
    $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $blockedDate = (new DateTimeImmutable('+4 days'))->format('Y-m-d');
    $check = static function (bool $condition, string $label) use (&$issues): void {
        if (!$condition) {
            $issues[] = 'Reservations audit: ' . $label;
        }
    };
    $get = static fn (string $path): array => fetchUrl($baseUrl . BASE_URL . $path, $adminSession['cookie'], 0);
    $post = static fn (string $path, array $fields): array => postUrl(
        $baseUrl . BASE_URL . $path,
        ['csrf_token' => $adminSession['csrf']] + $fields,
        $adminSession['cookie'],
        0
    );
    $dom = static function (string $html) use ($check): DOMXPath {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $xpath = new DOMXPath($doc);
        foreach ($xpath->query('//*[@aria-describedby or @aria-labelledby]') as $node) {
            foreach (['aria-describedby', 'aria-labelledby'] as $attribute) {
                foreach (preg_split('/\s+/', trim($node->getAttribute($attribute)), -1, PREG_SPLIT_NO_EMPTY) as $id) {
                    $check($xpath->query('//*[@id="' . $id . '"]')->length === 1, 'missing or duplicate ARIA target ' . $id);
                }
            }
        }
        return $xpath;
    };
    $snapshot = static function (int $bookingId) use ($pdo): array {
        $stmt = $pdo->prepare('SELECT * FROM cms_res_bookings WHERE id = ?');
        $stmt->execute([$bookingId]);
        $eventStmt = $pdo->prepare('SELECT * FROM cms_res_booking_events WHERE booking_id = ? ORDER BY id');
        $eventStmt->execute([$bookingId]);
        return [$stmt->fetch(), $eventStmt->fetchAll()];
    };
    $insertBooking = static function (string $date, string $status, string $start = '09:00:00', string $end = '10:00:00') use ($pdo, &$resourceId, $prefix): int {
        $pdo->prepare('INSERT INTO cms_res_bookings (resource_id, guest_name, booking_date, start_time, end_time, status, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)')->execute([$resourceId, $prefix, $date, $start, $end, $status, $prefix]);
        return (int)$pdo->lastInsertId();
    };

    try {
        foreach (['cms_res_bookings' => ['guest_name', 'guest_email', 'guest_phone'], 'cms_res_blocked' => ['reason']] as $table => $columns) {
            $stmt = $pdo->prepare('SELECT COLUMN_NAME, IS_NULLABLE FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            $metadata = array_column($stmt->fetchAll(), 'IS_NULLABLE', 'COLUMN_NAME');
            foreach ($columns as $column) {
                $check(($metadata[$column] ?? '') === 'NO', 'live canonical NOT NULL constraint missing: ' . $table . '.' . $column);
            }
        }
        if ($issues !== []) {
            return $issues;
        }
        saveSetting('module_reservations', '1');
        $pdo->prepare("INSERT INTO cms_users (email, password, first_name, role) VALUES (?, ?, ?, 'public')")
            ->execute([$fixtureEmail, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $prefix]);
        $accountId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO cms_res_resources (name, slug, capacity, slot_mode, max_concurrent,
            min_advance_hours, max_advance_days, allow_guests, requires_approval, reminders_enabled)
            VALUES (?, ?, 5, 'range', 1, 0, 30, 0, 0, 0)")->execute([$prefix, $prefix]);
        $resourceId = (int)$pdo->lastInsertId();
        $hours = [];
        for ($day = 0; $day < 7; $day++) {
            $pdo->prepare("INSERT INTO cms_res_hours (resource_id, day_of_week, open_time, close_time)
                VALUES (?, ?, '09:00:00', '11:00:00')")->execute([$resourceId, $day]);
            $hours[$day] = ['open_time' => '09:00', 'close_time' => '11:00'];
        }

        foreach (['user', 'guest'] as $index => $mode) {
            $date = (new DateTimeImmutable('+' . ($index + 2) . ' days'))->format('Y-m-d');
            $fields = ['mode' => $mode, 'resource_id' => (string)$resourceId, 'user_id' => (string)$accountId,
                'guest_name' => $prefix, 'guest_email' => '', 'guest_phone' => '', 'booking_date' => $date,
                'start_time' => '09:00', 'end_time' => '09:30', 'party_size' => '1', 'notes' => $prefix . '-' . $mode];
            $response = $post('/admin/res_booking_add.php', $fields);
            $stmt = $pdo->prepare('SELECT * FROM cms_res_bookings WHERE resource_id = ? AND notes = ?');
            $stmt->execute([$resourceId, $fields['notes']]);
            $rows = $stmt->fetchAll();
            $check(httpIntegrationStatusCode($response) === 302 && count($rows) === 1, 'manual ' . $mode . ' booking failed on canonical schema');
            if (count($rows) === 1) {
                $row = $rows[0];
                $check($row['guest_name'] === ($mode === 'user' ? '' : $prefix)
                    && $row['guest_email'] === '' && $row['guest_phone'] === '', 'manual ' . $mode . ' contact did not retain empty strings');
                $check($mode === 'user' ? (int)$row['user_id'] === $accountId : $row['user_id'] === null, 'manual customer association is incorrect');
                $check($row['notes'] === $fields['notes'], 'manual booking lost notes');
            }
        }

        $resourceFields = ['id' => (string)$resourceId, 'name' => $prefix, 'slug' => $prefix, 'slot_mode' => 'range',
            'capacity' => '5', 'max_concurrent' => '1', 'min_advance_hours' => '0', 'max_advance_days' => '30',
            'hours' => $hours, 'reminder_hours_before' => '24', 'blocked_dates' => [$blockedDate],
            'blocked_reasons' => [''], 'blocked_ids' => ['0']];
        $response = $post('/admin/res_resource_save.php', $resourceFields);
        $stmt = $pdo->prepare('SELECT id, reason FROM cms_res_blocked WHERE resource_id = ? AND blocked_date = ?');
        $stmt->execute([$resourceId, $blockedDate]);
        $block = $stmt->fetch();
        $check(httpIntegrationStatusCode($response) === 302 && is_array($block) && $block['reason'] === '', 'blank blocked-day reason could not be inserted');
        if (is_array($block)) {
            $pdo->prepare('UPDATE cms_res_blocked SET reason = ? WHERE id = ? AND resource_id = ?')->execute(['Previous reason', $block['id'], $resourceId]);
            $resourceFields['blocked_ids'] = [(string)$block['id']];
            $response = $post('/admin/res_resource_save.php', $resourceFields);
            $stmt->execute([$resourceId, $blockedDate]);
            $updated = $stmt->fetch();
            $check(httpIntegrationStatusCode($response) === 302 && is_array($updated) && $updated['reason'] === '', 'existing blocked-day reason could not be cleared');
        }

        $insertBooking($tomorrow, 'confirmed', '09:30:00', '11:00:00');
        $response = $get('/reservations/book.php?slug=' . rawurlencode($prefix) . '&date=' . $tomorrow);
        $check(httpIntegrationStatusCode($response) === 200, 'public range form did not render');
        $xpath = $dom($response['body']);
        $check($xpath->query('//select[@name="start_time"]/option[@value="09:00"]')->length === 1
            && $xpath->query('//select[@name="start_time"]/option[@value="09:30"]')->length === 0
            && $xpath->query('//select[@name="end_time"]/option[@value="09:30"]')->length === 1, 'public form lost a valid adjacent end boundary');
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM cms_res_bookings WHERE resource_id = ? AND booking_date = ?');
        $stmt->execute([$resourceId, $tomorrow]);
        $beforeCount = (int)$stmt->fetchColumn();
        $fields = ['resource_id' => (string)$resourceId, 'mode' => 'guest', 'guest_name' => $prefix,
            'guest_email' => '', 'guest_phone' => '', 'booking_date' => $tomorrow,
            'start_time' => '09:00', 'end_time' => '10:00', 'party_size' => '1', 'notes' => $prefix . '-adjacent'];
        $response = $post('/admin/res_booking_add.php', $fields);
        $stmt->execute([$resourceId, $tomorrow]);
        $check(httpIntegrationStatusCode($response) === 200 && (int)$stmt->fetchColumn() === $beforeCount, 'overlapping term was not rejected without insertion');
        $xpath = $dom($response['body']);
        $check($xpath->query('//*[@role="alert"]')->length > 0
            && $xpath->query('//input[@name="end_time" and @aria-invalid="true"]')->length === 1, 'overlap error is not accessible at the end control');
        $fields['end_time'] = '09:30';
        $response = $post('/admin/res_booking_add.php', $fields);
        $stmt->execute([$resourceId, $tomorrow]);
        $check(httpIntegrationStatusCode($response) === 302 && (int)$stmt->fetchColumn() === $beforeCount + 1, 'valid adjacent term did not persist');

        foreach (['today', '+5 days'] as $day) {
            $date = (new DateTimeImmutable($day))->format('Y-m-d');
            $bookingId = $insertBooking($date, 'confirmed');
            $before = $snapshot($bookingId);
            $response = $post('/admin/res_booking_save.php', ['booking_id' => (string)$bookingId, 'action' => 'no_show',
                'confirm_reservation_status_no_show' => '1', 'admin_note' => 'Must not persist']);
            $location = httpIntegrationHeaderValue($response, 'Location');
            $check(httpIntegrationStatusCode($response) === 302 && str_contains($location, 'error=no_show_not_available')
                && $snapshot($bookingId) === $before, 'premature no-show changed status, notes or history: ' . $day);
            $detail = $get('/admin/res_booking_detail.php?id=' . $bookingId . '&error=no_show_not_available&action=no_show');
            $xpath = $dom($detail['body']);
            $check(httpIntegrationStatusCode($detail) === 200 && $xpath->query('//*[@role="alert" and @aria-atomic="true"]')->length === 1
                && $xpath->query('//*[@id="reservation-status-error"]')->length === 1, 'temporal no-show error did not render an atomic alert');
        }

        foreach (['approve' => 'confirmed', 'reject' => 'rejected', 'no_show' => 'no_show'] as $action => $expectedStatus) {
            $date = $action === 'no_show' ? (new DateTimeImmutable('yesterday'))->format('Y-m-d')
                : (new DateTimeImmutable('+8 days'))->format('Y-m-d');
            $bookingId = $insertBooking($date, $action === 'no_show' ? 'completed' : 'pending');
            if ($action === 'approve') {
                $list = $get('/admin/res_bookings.php?resource_id=' . $resourceId . '&status=pending');
                $xpath = $dom($list['body']);
                $check(httpIntegrationStatusCode($list) === 200
                    && $xpath->query('//form[@action="res_booking_save.php"]')->length === 0, 'list still offers an unusable consent-free status POST');
                $links = $xpath->query('//td[@class="actions"]/a[contains(@href, "id=' . $bookingId . '&")]');
                $check($links->length === 1, 'pending row is missing its detail review link');
                if ($links->length === 1) {
                    $link = $links->item(0);
                    $check(str_contains($link->textContent, '#' . $bookingId), 'review link has no accessible booking identity');
                    parse_str((string)parse_url($link->getAttribute('href'), PHP_URL_QUERY), $params);
                    $check(str_contains($params['redirect'] ?? '', 'status=pending'), 'review link lost list filters');
                    $detail = $get('/admin/' . $link->getAttribute('href'));
                    $review = $dom($detail['body']);
                    foreach (['approve', 'reject'] as $reviewAction) {
                        $check(
                            $review->query('//input[@name="confirm_reservation_status_' . $reviewAction . '" and @required and not(@checked)]')->length === 1,
                            'detail review lacks fresh confirmation: ' . $reviewAction
                        );
                    }
                }
            }
            $before = $snapshot($bookingId);
            $fields = ['booking_id' => (string)$bookingId, 'action' => $action];
            $response = $post('/admin/res_booking_save.php', $fields);
            $check(httpIntegrationStatusCode($response) === 302
                && str_contains(httpIntegrationHeaderValue($response, 'Location'), 'error=status_confirm_required')
                && $snapshot($bookingId) === $before, 'unconfirmed status action changed data: ' . $action);
            $detail = $get('/admin/res_booking_detail.php?id=' . $bookingId . '&error=status_confirm_required&action=' . $action);
            $xpath = $dom($detail['body']);
            $check(
                httpIntegrationStatusCode($detail) === 200 && $xpath->query('//*[@role="alert" and @aria-atomic="true"]')->length === 1
                && $xpath->query('//input[@name="confirm_reservation_status_' . $action . '" and @aria-invalid="true" and not(@checked)]')->length === 1,
                'confirmation error is not rendered and associated: ' . $action
            );
            $fields['confirm_reservation_status_' . $action] = '1';
            $response = $post('/admin/res_booking_save.php', $fields);
            $after = $snapshot($bookingId);
            $check(httpIntegrationStatusCode($response) === 302 && ($after[0]['status'] ?? '') === $expectedStatus
                && count($after[1]) === 1, 'confirmed status action did not persist once: ' . $action);
        }
    } catch (Throwable $exception) {
        $issues[] = 'Reservations audit: ' . get_class($exception) . ': ' . $exception->getMessage();
    } finally {
        try {
            if ($resourceId > 0) {
                $owner = $pdo->prepare('SELECT slug FROM cms_res_resources WHERE id = ?');
                $owner->execute([$resourceId]);
                if ($owner->fetchColumn() !== $prefix) {
                    throw new RuntimeException('Reservation HTTP cleanup refused a foreign resource');
                }
                $pdo->beginTransaction();
                try {
                    $pdo->prepare('DELETE e FROM cms_res_booking_events e JOIN cms_res_bookings b ON b.id = e.booking_id WHERE b.resource_id = ?')->execute([$resourceId]);
                    foreach (['cms_res_bookings', 'cms_res_resource_locations', 'cms_res_hours', 'cms_res_slots', 'cms_res_blocked'] as $table) {
                        $pdo->prepare('DELETE FROM ' . $table . ' WHERE resource_id = ?')->execute([$resourceId]);
                    }
                    $pdo->prepare('DELETE FROM cms_res_resources WHERE id = ? AND slug = ?')->execute([$resourceId, $prefix]);
                    $pdo->commit();
                } catch (Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $exception;
                }
            }
            if ($accountId > 0) {
                $pdo->prepare('DELETE FROM cms_users WHERE id = ? AND email = ?')->execute([$accountId, $fixtureEmail]);
            }
        } finally {
            saveSetting('module_reservations', $oldModule);
        }
    }
    return $issues;
}
