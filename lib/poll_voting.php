<?php

/** Lock the shared parent before reading or changing its options and votes. */
function pollWriteLockSql(PDO $pdo): string
{
    if (!$pdo->inTransaction()) {
        throw new \LogicException('Poll writes require a transaction.');
    }

    return $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
}

/** @return array<string,mixed>|null */
function pollLockForWrite(PDO $pdo, int $pollId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM cms_polls WHERE id = ?' . pollWriteLockSql($pdo));
    $stmt->execute([$pollId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** @return list<array<string,mixed>> */
function pollLockedOptions(PDO $pdo, int $pollId): array
{
    $stmt = $pdo->prepare('SELECT * FROM cms_poll_options WHERE poll_id = ? ORDER BY sort_order, id' . pollWriteLockSql($pdo));
    $stmt->execute([$pollId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function pollOptionHasVotes(PDO $pdo, int $pollId, int $optionId): bool
{
    // A locking read sees votes committed while this transaction waited on the parent.
    $stmt = $pdo->prepare('SELECT option_id FROM cms_poll_votes WHERE poll_id = ? AND option_id = ? LIMIT 1' . pollWriteLockSql($pdo));
    $stmt->execute([$pollId, $optionId]);
    return $stmt->fetchColumn() !== false;
}

/**
 * The caller must commit success or roll back rejection/failure. Every poll editor
 * takes the same parent lock, so validation and all selected votes are atomic.
 * @return array{error:string, selected:list<int>, poll?:array<string,mixed>}
 */
function pollStoreVote(PDO $pdo, int $pollId, mixed $singleChoice, mixed $multipleChoices, string $voterHash): array
{
    $poll = pollLockForWrite($pdo, $pollId);
    if ($poll === null) {
        return ['error' => 'closed', 'selected' => []];
    }

    // Re-evaluate publication dates after waiting, using database time like the listing.
    $activeStmt = $pdo->prepare('SELECT id FROM cms_polls WHERE id = ? AND ' . pollPublicVisibilitySql('', 'active') . pollWriteLockSql($pdo));
    $activeStmt->execute([$pollId]);
    if ($activeStmt->fetchColumn() === false) {
        return ['error' => 'closed', 'selected' => []];
    }

    $selected = pollSelectedOptionIds(pollAllowsMultipleChoices($poll) ? $multipleChoices : $singleChoice);
    $options = pollLockedOptions($pdo, $pollId);
    if ($selected === []) {
        return ['error' => 'no_option', 'selected' => []];
    }
    if (count($selected) > pollConfiguredMaxChoices($poll, count($options))) {
        return ['error' => 'too_many_options', 'selected' => $selected];
    }
    $allowed = array_map(static fn (array $option): int => (int)$option['id'], $options);
    if (array_diff($selected, $allowed) !== []) {
        return ['error' => 'invalid_option', 'selected' => $selected];
    }

    $sessionStmt = $pdo->prepare('SELECT id FROM cms_poll_vote_sessions WHERE poll_id = ? AND voter_hash = ? LIMIT 1' . pollWriteLockSql($pdo));
    $sessionStmt->execute([$pollId, $voterHash]);
    $legacyStmt = $pdo->prepare('SELECT option_id FROM cms_poll_votes WHERE poll_id = ? AND ip_hash = ? LIMIT 1' . pollWriteLockSql($pdo));
    $legacyStmt->execute([$pollId, $voterHash]);
    if ($sessionStmt->fetchColumn() !== false || $legacyStmt->fetchColumn() !== false) {
        return ['error' => 'already_voted', 'selected' => $selected];
    }

    $pdo->prepare('INSERT INTO cms_poll_vote_sessions (poll_id, voter_hash) VALUES (?, ?)')->execute([$pollId, $voterHash]);
    $sessionId = (int)$pdo->lastInsertId();
    $insertStmt = $pdo->prepare('INSERT INTO cms_poll_votes (poll_id, option_id, vote_session_id, ip_hash) VALUES (?, ?, ?, ?)');
    foreach ($selected as $optionId) {
        $insertStmt->execute([$pollId, $optionId, $sessionId, $voterHash]);
    }

    return ['error' => '', 'selected' => $selected, 'poll' => $poll];
}

function pollDeletePermanently(PDO $pdo, int $pollId, bool $onlyDeleted = true): bool
{
    $pdo->beginTransaction();
    try {
        $poll = pollLockForWrite($pdo, $pollId);
        if ($poll === null || ($onlyDeleted && empty($poll['deleted_at']))) {
            $pdo->rollBack();
            return false;
        }
        foreach (['cms_poll_votes', 'cms_poll_vote_sessions', 'cms_poll_options'] as $table) {
            $pdo->prepare('DELETE FROM ' . $table . ' WHERE poll_id = ?')->execute([$pollId]);
        }
        $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type = 'poll' AND entity_id = ?")->execute([$pollId]);
        $pdo->prepare('DELETE FROM cms_redirects WHERE new_path = ?')->execute([pollPublicPath($poll)]);
        $pdo->prepare('DELETE FROM cms_polls WHERE id = ?')->execute([$pollId]);
        $pdo->commit();
        return true;
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}
