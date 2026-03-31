<?php
require_once __DIR__ . '/../db.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu anket nemáte potřebné oprávnění.');
verifyCsrf();

$pdo = db_connect();
$id = inputInt('post', 'id');
$defaultRedirect = BASE_URL . '/admin/polls.php';
$redirectTarget = internalRedirectTarget(trim((string)($_POST['redirect'] ?? '')), $defaultRedirect);

$redirectToForm = static function (?int $pollId, string $errorCode, string $backUrl) use ($defaultRedirect): never {
    $params = ['err' => $errorCode];
    if ($pollId !== null) {
        $params['id'] = (string)$pollId;
    }
    if ($backUrl !== '' && $backUrl !== $defaultRedirect) {
        $params['redirect'] = $backUrl;
    }

    header('Location: ' . BASE_URL . appendUrlQuery('/admin/polls_form.php', $params));
    exit;
};

$question = trim((string)($_POST['question'] ?? ''));
$submittedSlug = trim((string)($_POST['slug'] ?? ''));
$description = trim((string)($_POST['description'] ?? ''));
$status = in_array((string)($_POST['status'] ?? ''), ['active', 'closed'], true)
    ? (string)$_POST['status']
    : 'active';
$metaTitle = mb_substr(trim((string)($_POST['meta_title'] ?? '')), 0, 160);
$metaDescription = trim((string)($_POST['meta_description'] ?? ''));
$isValidDate = static function (string $value): bool {
    $dateTime = DateTime::createFromFormat('Y-m-d', $value);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors)
        && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0);

    return $dateTime !== false && !$hasErrors && $dateTime->format('Y-m-d') === $value;
};
$isValidTime = static function (string $value): bool {
    $dateTime = DateTime::createFromFormat('H:i', $value);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors)
        && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0);

    return $dateTime !== false && !$hasErrors && $dateTime->format('H:i') === $value;
};
$composeDateTime = static function (string $date, string $time): ?string {
    $dateTime = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $time);
    $errors = DateTime::getLastErrors();
    $hasErrors = is_array($errors)
        && (((int)($errors['warning_count'] ?? 0)) > 0 || ((int)($errors['error_count'] ?? 0)) > 0);

    if ($dateTime === false || $hasErrors || $dateTime->format('Y-m-d H:i') !== ($date . ' ' . $time)) {
        return null;
    }

    return $dateTime->format('Y-m-d H:i:s');
};

$startDate = null;
if (!empty($_POST['start_date'])) {
    $startDateInput = trim((string)$_POST['start_date']);
    $startTimeInput = trim((string)($_POST['start_time'] ?? '00:00')) ?: '00:00';
    if (!$isValidDate($startDateInput) || !$isValidTime($startTimeInput)) {
        $redirectToForm($id, 'dates', $redirectTarget);
    }
    $startDate = $composeDateTime($startDateInput, $startTimeInput);
    if ($startDate === null) {
        $redirectToForm($id, 'dates', $redirectTarget);
    }
}
$endDate = null;
if (!empty($_POST['end_date'])) {
    $endDateInput = trim((string)$_POST['end_date']);
    $endTimeInput = trim((string)($_POST['end_time'] ?? '00:00')) ?: '00:00';
    if (!$isValidDate($endDateInput) || !$isValidTime($endTimeInput)) {
        $redirectToForm($id, 'dates', $redirectTarget);
    }
    $endDate = $composeDateTime($endDateInput, $endTimeInput);
    if ($endDate === null) {
        $redirectToForm($id, 'dates', $redirectTarget);
    }
}
if ($startDate !== null && $endDate !== null && $endDate <= $startDate) {
    $redirectToForm($id, 'range', $redirectTarget);
}

$optionTexts = $_POST['options'] ?? [];
$optionIds = $_POST['option_ids'] ?? [];
$validOptions = [];
foreach ($optionTexts as $index => $text) {
    $normalizedText = trim((string)$text);
    if ($normalizedText === '') {
        continue;
    }

    $validOptions[] = [
        'id' => (int)($optionIds[$index] ?? 0),
        'text' => $normalizedText,
        'sort' => count($validOptions),
    ];
}

if ($question === '' || count($validOptions) < 2) {
    $redirectToForm($id, 'required', $redirectTarget);
}
if (count($validOptions) > 10) {
    $redirectToForm($id, 'max_options', $redirectTarget);
}

$existingPoll = null;
$existingOptions = [];
if ($id !== null) {
    $existingStmt = $pdo->prepare("SELECT * FROM cms_polls WHERE id = ?");
    $existingStmt->execute([$id]);
    $existingPoll = $existingStmt->fetch() ?: null;
    if (!$existingPoll) {
        header('Location: ' . $defaultRedirect);
        exit;
    }

    $existingOptionsStmt = $pdo->prepare(
        "SELECT o.id, o.option_text, o.sort_order,
                (SELECT COUNT(*) FROM cms_poll_votes WHERE option_id = o.id) AS vote_count
         FROM cms_poll_options o
         WHERE o.poll_id = ?
         ORDER BY o.sort_order, o.id"
    );
    $existingOptionsStmt->execute([$id]);
    $existingOptions = $existingOptionsStmt->fetchAll();

    $submittedExistingIds = [];
    foreach ($validOptions as $option) {
        if ($option['id'] > 0) {
            $submittedExistingIds[] = $option['id'];
        }
    }

    foreach ($existingOptions as $existingOption) {
        $existingOptionId = (int)$existingOption['id'];
        $voteCount = (int)($existingOption['vote_count'] ?? 0);
        if (!in_array($existingOptionId, $submittedExistingIds, true) && $voteCount > 0) {
            $redirectToForm($id, 'has_votes', $redirectTarget);
        }
    }
}

$slug = pollSlug($submittedSlug !== '' ? $submittedSlug : $question);
if ($slug === '') {
    $redirectToForm($id, 'slug', $redirectTarget);
}

$uniqueSlug = uniquePollSlug($pdo, $slug, $id);
if ($submittedSlug !== '' && $uniqueSlug !== $slug) {
    $redirectToForm($id, 'slug', $redirectTarget);
}
$slug = $uniqueSlug;

try {
    $pdo->beginTransaction();

    if ($existingPoll !== null) {
        $pdo->prepare(
            "UPDATE cms_polls
             SET question = ?, slug = ?, description = ?, status = ?, start_date = ?, end_date = ?,
                 meta_title = ?, meta_description = ?
             WHERE id = ?"
        )->execute([
            $question,
            $slug,
            $description !== '' ? $description : null,
            $status,
            $startDate,
            $endDate,
            $metaTitle,
            $metaDescription !== '' ? $metaDescription : null,
            $id,
        ]);

        $existingMap = [];
        foreach ($existingOptions as $existingOption) {
            $existingMap[(int)$existingOption['id']] = $existingOption;
        }

        $submittedExistingIds = [];
        foreach ($validOptions as $option) {
            if ($option['id'] > 0) {
                $submittedExistingIds[] = $option['id'];
            }
        }

        foreach ($existingMap as $existingOptionId => $existingOption) {
            if (!in_array($existingOptionId, $submittedExistingIds, true)) {
                $pdo->prepare("DELETE FROM cms_poll_options WHERE id = ? AND poll_id = ?")
                    ->execute([$existingOptionId, $id]);
            }
        }

        $updateOptionStmt = $pdo->prepare(
            "UPDATE cms_poll_options
             SET option_text = ?, sort_order = ?
             WHERE id = ? AND poll_id = ?"
        );
        $insertOptionStmt = $pdo->prepare(
            "INSERT INTO cms_poll_options (poll_id, option_text, sort_order)
             VALUES (?, ?, ?)"
        );

        foreach ($validOptions as $option) {
            if ($option['id'] > 0 && isset($existingMap[$option['id']])) {
                $updateOptionStmt->execute([$option['text'], $option['sort'], $option['id'], $id]);
            } else {
                $insertOptionStmt->execute([$id, $option['text'], $option['sort']]);
            }
        }

        $pdo->commit();

        saveRevision(
            $pdo,
            'poll',
            (int)$id,
            pollRevisionSnapshot($existingPoll, $existingOptions),
            pollRevisionSnapshot([
                'question' => $question,
                'slug' => $slug,
                'description' => $description,
                'status' => $status,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'meta_title' => $metaTitle,
                'meta_description' => $metaDescription,
            ], $validOptions)
        );

        upsertPathRedirect($pdo, pollPublicPath($existingPoll), pollPublicPath(['id' => $id, 'slug' => $slug]));
        logAction('poll_edit', "id={$id} question={$question} slug={$slug} status={$status}");
    } else {
        $pdo->prepare(
            "INSERT INTO cms_polls (
                question, slug, description, status, start_date, end_date, meta_title, meta_description
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $question,
            $slug,
            $description !== '' ? $description : null,
            $status,
            $startDate,
            $endDate,
            $metaTitle,
            $metaDescription !== '' ? $metaDescription : null,
        ]);

        $id = (int)$pdo->lastInsertId();
        $insertOptionStmt = $pdo->prepare(
            "INSERT INTO cms_poll_options (poll_id, option_text, sort_order)
             VALUES (?, ?, ?)"
        );
        foreach ($validOptions as $option) {
            $insertOptionStmt->execute([$id, $option['text'], $option['sort']]);
        }

        $pdo->commit();
        logAction('poll_add', "id={$id} question={$question} slug={$slug} status={$status}");
    }
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('poll save: ' . $e->getMessage());
    $redirectToForm($id, 'save', $redirectTarget);
}

header('Location: ' . $redirectTarget);
exit;
