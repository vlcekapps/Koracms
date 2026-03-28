<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('polls')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function pollIpHash(int $pollId): string
{
    return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0') . '|poll_' . $pollId);
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$now = date('Y-m-d H:i:s');
$pollId = inputInt('get', 'id');
$pollSlugValue = pollSlug(trim($_GET['slug'] ?? ''));
$archiv = isset($_GET['archiv']);

$fetchPoll = static function (bool $onlyActive) use ($pdo, $pollId, $pollSlugValue, $now): ?array {
    if ($pollId === null && $pollSlugValue === '') {
        return null;
    }

    if ($onlyActive) {
        $whereSql = "p.status = 'active'
            AND (p.start_date IS NULL OR p.start_date <= ?)
            AND (p.end_date IS NULL OR p.end_date > ?)";
        $params = [$now, $now];
    } else {
        $whereSql = "(
                (p.status = 'active' AND (p.start_date IS NULL OR p.start_date <= ?) AND (p.end_date IS NULL OR p.end_date > ?))
                OR p.status = 'closed'
                OR (p.end_date IS NOT NULL AND p.end_date <= ?)
            )";
        $params = [$now, $now, $now];
    }

    if ($pollSlugValue !== '') {
        $stmt = $pdo->prepare(
            "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
             FROM cms_polls p
             WHERE p.slug = ? AND {$whereSql}
             LIMIT 1"
        );
        $stmt->execute(array_merge([$pollSlugValue], $params));
        return $stmt->fetch() ?: null;
    }

    $stmt = $pdo->prepare(
        "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
         FROM cms_polls p
         WHERE p.id = ? AND {$whereSql}
         LIMIT 1"
    );
    $stmt->execute(array_merge([$pollId], $params));
    return $stmt->fetch() ?: null;
};

$voteError = '';
$voted = isset($_GET['voted']);
$poll = null;
$options = [];
$hasVoted = false;
$polls = [];
$totalPages = 1;
$page = 1;
$isActive = false;
$showForm = false;
$totalVotes = 0;
$detailRequested = $pollId !== null || $pollSlugValue !== '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $detailRequested && isset($_POST['vote'])) {
    verifyCsrf();

    $votePoll = $fetchPoll(true);
    if ($votePoll !== null) {
        $votePoll = hydratePollPresentation($votePoll);
    }

    if (honeypotTriggered()) {
        if ($votePoll !== null) {
            header('Location: ' . pollPublicPath($votePoll, ['voted' => '1']));
        } else {
            header('Location: ' . BASE_URL . '/polls/index.php');
        }
        exit;
    }

    rateLimit('poll_vote', 5, 60);
    $optionId = inputInt('post', 'option_id');

    if ($votePoll === null) {
        $voteError = 'closed';
    } elseif ($optionId === null) {
        $voteError = 'no_option';
        $poll = $votePoll;
    } else {
        $optionStmt = $pdo->prepare("SELECT id FROM cms_poll_options WHERE id = ? AND poll_id = ?");
        $optionStmt->execute([$optionId, (int)$votePoll['id']]);
        if (!$optionStmt->fetch()) {
            $voteError = 'invalid_option';
            $poll = $votePoll;
        } else {
            $ipHash = pollIpHash((int)$votePoll['id']);
            try {
                $pdo->prepare(
                    "INSERT INTO cms_poll_votes (poll_id, option_id, ip_hash) VALUES (?, ?, ?)"
                )->execute([(int)$votePoll['id'], $optionId, $ipHash]);
                header('Location: ' . pollPublicPath($votePoll, ['voted' => '1']));
                exit;
            } catch (\PDOException $e) {
                $voteError = 'already_voted';
                $poll = $votePoll;
            }
        }
    }
}

$pageTitle = 'Ankety';
$pageMetaTitle = 'Ankety – ' . $siteName;
$pageUrl = BASE_URL . '/polls/index.php';

if ($detailRequested) {
    $poll = $poll ?? $fetchPoll(false);
    if ($poll === null) {
        http_response_code(404);
        $missingPath = $pollSlugValue !== ''
            ? BASE_URL . '/polls/' . rawurlencode($pollSlugValue)
            : BASE_URL . '/polls/index.php' . ($pollId !== null ? '?id=' . urlencode((string)$pollId) : '');

        renderPublicPage([
            'title' => 'Anketa nenalezena – ' . $siteName,
            'meta' => [
                'title' => 'Anketa nenalezena – ' . $siteName,
                'url' => $missingPath,
            ],
            'view' => 'not-found',
            'body_class' => 'page-poll-not-found',
        ]);
        exit;
    }

    $poll = hydratePollPresentation($poll);
    if ($pollSlugValue === '' && !empty($poll['slug']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        $redirectQuery = [];
        if ($voted) {
            $redirectQuery['voted'] = '1';
        }
        header('Location: ' . pollPublicPath($poll, $redirectQuery));
        exit;
    }

    $pageTitle = (string)$poll['question'] . ' – Ankety';
    $pageMetaTitle = (string)$poll['question'] . ' – Ankety – ' . $siteName;
    $pageUrl = pollPublicPath($poll);

    $optionsStmt = $pdo->prepare(
        "SELECT o.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE option_id = o.id) AS vote_count
         FROM cms_poll_options o
         WHERE o.poll_id = ?
         ORDER BY o.sort_order, o.id"
    );
    $optionsStmt->execute([(int)$poll['id']]);
    $options = $optionsStmt->fetchAll();

    $ipHash = pollIpHash((int)$poll['id']);
    $hasVotedStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ? AND ip_hash = ?");
    $hasVotedStmt->execute([(int)$poll['id'], $ipHash]);
    $hasVoted = (int)$hasVotedStmt->fetchColumn() > 0;

    foreach ($options as $option) {
        $totalVotes += (int)$option['vote_count'];
    }

    $isActive = (string)($poll['state'] ?? '') === 'active';
    $showForm = $isActive && !$hasVoted && !$voted;

    if (!isset($_SESSION['cms_user_id'])) {
        trackPageView('poll', (int)$poll['id']);
    }
} else {
    $perPage = 10;

    if ($archiv) {
        $pageTitle = 'Archiv anket';
        $pageMetaTitle = 'Archiv anket – ' . $siteName;
        $pageUrl = BASE_URL . '/polls/index.php?archiv=1';
        $countSql = "SELECT COUNT(*) FROM cms_polls
                     WHERE status = 'closed' OR (end_date IS NOT NULL AND end_date <= :now1)";
        $listSql = "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
                    FROM cms_polls p
                    WHERE p.status = 'closed' OR (p.end_date IS NOT NULL AND p.end_date <= :now1)
                    ORDER BY COALESCE(p.end_date, p.created_at) DESC, p.id DESC
                    LIMIT :lim OFFSET :off";
        $countParams = [':now1' => $now];
    } else {
        $countSql = "SELECT COUNT(*) FROM cms_polls
                     WHERE status = 'active'
                       AND (start_date IS NULL OR start_date <= :now1)
                       AND (end_date IS NULL OR end_date > :now2)";
        $listSql = "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
                    FROM cms_polls p
                    WHERE p.status = 'active'
                      AND (p.start_date IS NULL OR p.start_date <= :now1)
                      AND (p.end_date IS NULL OR p.end_date > :now2)
                    ORDER BY COALESCE(p.start_date, p.created_at) DESC, p.id DESC
                    LIMIT :lim OFFSET :off";
        $countParams = [':now1' => $now, ':now2' => $now];
    }

    $pag = paginate($pdo, $countSql, $countParams, $perPage);
    ['totalPages' => $totalPages, 'page' => $page, 'offset' => $offset] = $pag;

    $listStmt = $pdo->prepare($listSql);
    foreach ($countParams as $key => $value) {
        $listStmt->bindValue($key, $value);
    }
    $listStmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $listStmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $listStmt->execute();
    $polls = array_map(
        static fn(array $item): array => hydratePollPresentation($item),
        $listStmt->fetchAll()
    );

    foreach ($polls as &$listPoll) {
        $pollIp = pollIpHash((int)$listPoll['id']);
        $votedStmt = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ? AND ip_hash = ?");
        $votedStmt->execute([(int)$listPoll['id'], $pollIp]);
        $listPoll['has_voted'] = (int)$votedStmt->fetchColumn() > 0;
    }
    unset($listPoll);
}

$voteErrorMessages = [
    'too_many' => 'Příliš mnoho pokusů, zkuste to prosím později.',
    'closed' => 'Tato anketa už není aktivní.',
    'no_option' => 'Vyberte prosím jednu z možností.',
    'invalid_option' => 'Neplatná možnost hlasování.',
    'already_voted' => 'Z této IP adresy už bylo hlasováno.',
];

renderPublicPage([
    'title' => $pageMetaTitle,
    'meta' => [
        'title' => $pageMetaTitle,
        'description' => $poll !== null
            ? ($poll['excerpt'] !== '' ? (string)$poll['excerpt'] : 'Detail ankety ' . (string)$poll['question'])
            : ($archiv ? 'Archiv anket na webu ' . $siteName : 'Přehled aktivních anket na webu ' . $siteName),
        'url' => $pageUrl,
        'type' => $poll !== null ? 'article' : 'website',
    ],
    'view' => 'modules/polls-index',
    'view_data' => [
        'poll' => $poll,
        'options' => $options,
        'hasVoted' => $hasVoted,
        'voted' => $voted,
        'voteErrorMessage' => $voteErrorMessages[$voteError] ?? '',
        'polls' => $polls,
        'totalPages' => $totalPages,
        'page' => $page,
        'archiv' => $archiv,
        'isActive' => $isActive,
        'showForm' => $showForm,
        'totalVotes' => $totalVotes,
    ],
    'current_nav' => 'polls',
    'body_class' => 'page-polls',
    'page_kind' => $poll !== null ? 'detail' : 'listing',
    'admin_edit_url' => $poll !== null
        ? BASE_URL . '/admin/polls_form.php?id=' . (int)$poll['id']
        : BASE_URL . '/admin/polls.php',
]);
