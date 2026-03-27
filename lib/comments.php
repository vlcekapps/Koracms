<?php
// Komentářový systém – extrahováno z db.php

// ────────────────────────────── Pomocné funkce ────────────────────────────────

/** Formátuje datum česky: 18. března 2026, 14:30 */
function commentStatusDefinitions(): array
{
    return [
        'pending' => ['label' => 'Čekající', 'public' => false],
        'approved' => ['label' => 'Schválený', 'public' => true],
        'spam' => ['label' => 'Spam', 'public' => false],
        'trash' => ['label' => 'Koš', 'public' => false],
    ];
}

function normalizeCommentStatus(string $status): string
{
    $normalized = trim(mb_strtolower($status));
    return array_key_exists($normalized, commentStatusDefinitions()) ? $normalized : 'pending';
}

function commentStatusLabel(string $status): string
{
    $definitions = commentStatusDefinitions();
    $normalized = normalizeCommentStatus($status);
    return $definitions[$normalized]['label'];
}

function commentStatusIsPublic(string $status): bool
{
    $definitions = commentStatusDefinitions();
    $normalized = normalizeCommentStatus($status);
    return !empty($definitions[$normalized]['public']);
}

function commentStatusApprovalValue(string $status): int
{
    return commentStatusIsPublic($status) ? 1 : 0;
}

function commentsEnabledGlobally(): bool
{
    return getSetting('comments_enabled', '1') === '1';
}

function commentModerationMode(): string
{
    $mode = trim(getSetting('comment_moderation_mode', 'always'));
    return in_array($mode, ['always', 'known', 'none'], true) ? $mode : 'always';
}

function commentCloseDays(): int
{
    return max(0, (int)getSetting('comment_close_days', '0'));
}

function commentNotifyAdminEnabled(): bool
{
    return getSetting('comment_notify_admin', '1') === '1';
}

function commentNotifyAuthorOnApproveEnabled(): bool
{
    return getSetting('comment_notify_author_approve', '0') === '1';
}

function commentNotificationEmail(): string
{
    $candidates = [
        trim(getSetting('comment_notify_email', '')),
        trim(getSetting('contact_email', '')),
        trim(getSetting('admin_email', '')),
    ];

    foreach ($candidates as $candidate) {
        if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return $candidate;
        }
    }

    return '';
}

/**
 * @return list<string>
 */
function commentListSetting(string $key): array
{
    $value = str_replace("\r", '', getSetting($key, ''));
    $items = array_filter(array_map('trim', explode("\n", $value)), static fn(string $item): bool => $item !== '');
    return array_values(array_unique($items));
}

/**
 * @return list<string>
 */
function commentBlockedEmails(): array
{
    return commentListSetting('comment_blocked_emails');
}

/**
 * @return list<string>
 */
function commentSpamPhrases(): array
{
    return commentListSetting('comment_spam_words');
}

function blockedCommentEmailRule(string $authorEmail): string
{
    $normalizedEmail = mb_strtolower(trim($authorEmail));
    if ($normalizedEmail === '') {
        return '';
    }

    foreach (commentBlockedEmails() as $rule) {
        $normalizedRule = mb_strtolower(trim($rule));
        if ($normalizedRule === '') {
            continue;
        }

        if ($normalizedRule[0] === '@' && str_ends_with($normalizedEmail, $normalizedRule)) {
            return $rule;
        }

        if ($normalizedEmail === $normalizedRule) {
            return $rule;
        }
    }

    return '';
}

function matchedCommentSpamPhrase(string $authorName, string $content): string
{
    $haystack = mb_strtolower($authorName . "\n" . $content);
    foreach (commentSpamPhrases() as $phrase) {
        $normalizedPhrase = mb_strtolower(trim($phrase));
        if ($normalizedPhrase !== '' && mb_strpos($haystack, $normalizedPhrase) !== false) {
            return $phrase;
        }
    }

    return '';
}

function articleCommentsClosedByAge(array $article): bool
{
    $days = commentCloseDays();
    if ($days <= 0) {
        return false;
    }

    $reference = trim((string)($article['publish_at'] ?? ''));
    if ($reference === '') {
        $reference = trim((string)($article['created_at'] ?? ''));
    }
    if ($reference === '') {
        return false;
    }

    $referenceTs = strtotime($reference);
    if ($referenceTs === false) {
        return false;
    }

    return $referenceTs < strtotime('-' . $days . ' days');
}

function articleCommentsState(array $article): array
{
    if (!commentsEnabledGlobally()) {
        return [
            'enabled' => false,
            'reason' => 'global_disabled',
            'message' => 'Komentáře jsou na tomto webu vypnuté.',
        ];
    }

    if ((int)($article['comments_enabled'] ?? 1) !== 1) {
        return [
            'enabled' => false,
            'reason' => 'article_disabled',
            'message' => 'Komentáře jsou u tohoto článku vypnuté.',
        ];
    }

    if (articleCommentsClosedByAge($article)) {
        return [
            'enabled' => false,
            'reason' => 'closed_by_age',
            'message' => 'Komentáře jsou u starších článků uzavřené.',
        ];
    }

    return [
        'enabled' => true,
        'reason' => '',
        'message' => '',
    ];
}

/**
 * @return array{status:string, public_result:string}
 */
function determineCommentStatus(PDO $pdo, string $authorName, string $authorEmail, string $content): array
{
    $blockedEmailRule = blockedCommentEmailRule($authorEmail);
    if ($blockedEmailRule !== '') {
        return ['status' => 'spam', 'public_result' => 'pending'];
    }

    $spamPhrase = matchedCommentSpamPhrase($authorName, $content);
    if ($spamPhrase !== '') {
        return ['status' => 'spam', 'public_result' => 'pending'];
    }

    $mode = commentModerationMode();
    if ($mode === 'none') {
        return ['status' => 'approved', 'public_result' => 'approved'];
    }

    if ($mode === 'known') {
        $normalizedEmail = mb_strtolower(trim($authorEmail));
        if ($normalizedEmail !== '') {
            try {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM cms_comments
                     WHERE LOWER(author_email) = ? AND status = 'approved'"
                );
                $stmt->execute([$normalizedEmail]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return ['status' => 'approved', 'public_result' => 'approved'];
                }
            } catch (\PDOException $e) {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM cms_comments
                     WHERE LOWER(author_email) = ? AND is_approved = 1"
                );
                $stmt->execute([$normalizedEmail]);
                if ((int)$stmt->fetchColumn() > 0) {
                    return ['status' => 'approved', 'public_result' => 'approved'];
                }
            }
        }
    }

    return ['status' => 'pending', 'public_result' => 'pending'];
}

function notifyAdminAboutPendingComment(array $article, string $authorName, string $authorEmail, string $content): void
{
    if (!commentNotifyAdminEnabled()) {
        return;
    }

    $recipient = commentNotificationEmail();
    if ($recipient === '') {
        return;
    }

    $siteName = getSetting('site_name', 'Kora CMS');
    $articleUrl = articlePublicUrl($article);
    $adminUrl = siteUrl('/admin/comments.php?filter=pending');
    $safeEmail = trim($authorEmail) !== '' ? $authorEmail : 'neuveden';
    $message = "Na webu {$siteName} čeká nový komentář na schválení.\n\n"
        . "Článek: " . (string)($article['title'] ?? 'Článek') . "\n"
        . "Autor: {$authorName}\n"
        . "E-mail: {$safeEmail}\n\n"
        . "Komentář:\n{$content}\n\n"
        . "Článek: {$articleUrl}\n"
        . "Moderace: {$adminUrl}\n";

    if (!sendMail($recipient, 'Nový komentář čeká na schválení', $message)) {
        error_log("sendMail FAILED: notifikace o komentáři pro {$recipient}");
    }
}

function loadCommentModerationContext(PDO $pdo, int $commentId): ?array
{
    try {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.article_id, c.author_name, c.author_email, c.content, c.status, c.is_approved,
                    c.created_at, a.title AS article_title, a.slug AS article_slug
             FROM cms_comments c
             LEFT JOIN cms_articles a ON a.id = c.article_id
             WHERE c.id = ?"
        );
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
    } catch (\PDOException $e) {
        $stmt = $pdo->prepare(
            "SELECT c.id, c.article_id, c.author_name, c.author_email, c.content,
                    CASE WHEN c.is_approved = 1 THEN 'approved' ELSE 'pending' END AS status,
                    c.is_approved, c.created_at, a.title AS article_title, a.slug AS article_slug
             FROM cms_comments c
             LEFT JOIN cms_articles a ON a.id = c.article_id
             WHERE c.id = ?"
        );
        $stmt->execute([$commentId]);
        $comment = $stmt->fetch();
    }

    return $comment ?: null;
}

function notifyAuthorAboutApprovedComment(array $comment): void
{
    if (!commentNotifyAuthorOnApproveEnabled()) {
        return;
    }

    $recipient = trim((string)($comment['author_email'] ?? ''));
    if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $articleId = (int)($comment['article_id'] ?? 0);
    if ($articleId <= 0) {
        return;
    }

    $siteName = getSetting('site_name', 'Kora CMS');
    $articleTitle = trim((string)($comment['article_title'] ?? '')) ?: 'Článek';
    $articleUrl = articlePublicUrl([
        'id' => $articleId,
        'slug' => (string)($comment['article_slug'] ?? ''),
    ]);
    $authorName = trim((string)($comment['author_name'] ?? ''));
    $greetingName = $authorName !== '' ? $authorName : 'dobrý den';
    $message = "Dobrý den"
        . ($greetingName !== 'dobrý den' ? " {$greetingName}" : '')
        . ",\n\n"
        . "na webu {$siteName} byl schválen váš komentář a je nyní veřejně viditelný.\n\n"
        . "Článek: {$articleTitle}\n"
        . "Odkaz na článek: {$articleUrl}\n\n"
        . "Váš komentář:\n"
        . (string)($comment['content'] ?? '')
        . "\n\nDěkujeme.\n";

    if (!sendMail($recipient, 'Váš komentář byl schválen', $message)) {
        error_log("sendMail FAILED: notifikace o schválení komentáře pro {$recipient}");
    }
}

function setCommentModerationStatus(PDO $pdo, int $commentId, string $status): bool
{
    $comment = loadCommentModerationContext($pdo, $commentId);
    if (!$comment) {
        return false;
    }

    $normalizedStatus = normalizeCommentStatus($status);
    $previousStatus = normalizeCommentStatus((string)($comment['status'] ?? 'pending'));

    try {
        $pdo->prepare(
            "UPDATE cms_comments SET status = ?, is_approved = ? WHERE id = ?"
        )->execute([$normalizedStatus, commentStatusApprovalValue($normalizedStatus), $commentId]);
    } catch (\PDOException $e) {
        $pdo->prepare(
            "UPDATE cms_comments SET is_approved = ? WHERE id = ?"
        )->execute([commentStatusApprovalValue($normalizedStatus), $commentId]);
    }

    if ($normalizedStatus === 'approved' && $previousStatus !== 'approved') {
        notifyAuthorAboutApprovedComment($comment);
    }

    return true;
}

function pendingCommentCount(): int
{
    if (!isModuleEnabled('blog')) {
        return 0;
    }

    try {
        return (int)db_connect()->query(
            "SELECT COUNT(*) FROM cms_comments WHERE status = 'pending'"
        )->fetchColumn();
    } catch (\PDOException $e) {
        try {
            return (int)db_connect()->query(
                "SELECT COUNT(*) FROM cms_comments WHERE is_approved = 0"
            )->fetchColumn();
        } catch (\PDOException $fallbackError) {
            return 0;
        }
    }
}
