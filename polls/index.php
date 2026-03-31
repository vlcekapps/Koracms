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
$pollId = inputInt('get', 'id');
$pollSlugValue = pollSlug(trim((string)($_GET['slug'] ?? '')));
$archiv = isset($_GET['archiv']);
$isEmbedded = (string)($_GET['embed'] ?? '') === '1';
$q = trim((string)($_GET['q'] ?? ''));

$fetchPoll = static function (string $scope = 'all') use ($pdo, $pollId, $pollSlugValue): ?array {
    if ($pollId === null && $pollSlugValue === '') {
        return null;
    }

    $whereSql = pollPublicVisibilitySql('p', $scope);
    if ($pollSlugValue !== '') {
        $stmt = $pdo->prepare(
            "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
             FROM cms_polls p
             WHERE p.slug = ? AND {$whereSql}
             LIMIT 1"
        );
        $stmt->execute([$pollSlugValue]);
        return $stmt->fetch() ?: null;
    }

    $stmt = $pdo->prepare(
        "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
         FROM cms_polls p
         WHERE p.id = ? AND {$whereSql}
         LIMIT 1"
    );
    $stmt->execute([$pollId]);
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

    $votePoll = $fetchPoll('active');
    if ($votePoll !== null) {
        $votePoll = hydratePollPresentation($votePoll);
    }

    if (honeypotTriggered()) {
        if ($votePoll !== null) {
            $redirectQuery = ['voted' => '1'];
            if ($isEmbedded) {
                $redirectQuery['embed'] = '1';
            }
            header('Location: ' . pollPublicPath($votePoll, $redirectQuery));
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
                $redirectQuery = ['voted' => '1'];
                if ($isEmbedded) {
                    $redirectQuery['embed'] = '1';
                }
                header('Location: ' . pollPublicPath($votePoll, $redirectQuery));
                exit;
            } catch (\PDOException) {
                $voteError = 'already_voted';
                $poll = $votePoll;
            }
        }
    }
}

$pageTitle = 'Ankety';
$pageMetaTitle = 'Ankety – ' . $siteName;
$pageUrl = siteUrl(appendUrlQuery('/polls/index.php', array_filter([
    'archiv' => $archiv ? '1' : null,
    'q' => $q !== '' ? $q : null,
])));

if ($detailRequested) {
    $poll = $poll ?? $fetchPoll('all');
    if ($poll === null) {
        http_response_code(404);
        $missingUrl = $pollSlugValue !== ''
            ? siteUrl('/polls/' . rawurlencode($pollSlugValue))
            : siteUrl('/polls/index.php' . ($pollId !== null ? '?id=' . urlencode((string)$pollId) : ''));

        renderPublicPage([
            'title' => 'Anketa nenalezena – ' . $siteName,
            'meta' => [
                'title' => 'Anketa nenalezena – ' . $siteName,
                'url' => $missingUrl,
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
        if ($isEmbedded) {
            $redirectQuery['embed'] = '1';
        }
        header('Location: ' . pollPublicPath($poll, $redirectQuery));
        exit;
    }

    $metaTitleBase = $poll['meta_title'] !== '' ? $poll['meta_title'] : (string)$poll['question'];
    $metaDescription = $poll['meta_description'] !== ''
        ? $poll['meta_description']
        : ($poll['excerpt'] !== '' ? (string)$poll['excerpt'] : 'Detail ankety ' . (string)$poll['question']);
    $pageTitle = $metaTitleBase;
    $pageMetaTitle = $metaTitleBase . ' – ' . $siteName;
    $pageUrl = pollPublicUrl($poll);

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
    $whereParts = [pollPublicVisibilitySql('p', $archiv ? 'archive' : 'active')];
    $params = [];

    if ($q !== '') {
        $whereParts[] = '(p.question LIKE ? OR p.description LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }

    $whereSql = 'WHERE ' . implode(' AND ', $whereParts);

    if ($archiv) {
        $pageTitle = $q !== '' ? 'Archiv anket – hledání' : 'Archiv anket';
        $pageMetaTitle = $pageTitle . ' – ' . $siteName;
    } elseif ($q !== '') {
        $pageTitle = 'Ankety – hledání';
        $pageMetaTitle = 'Ankety – hledání – ' . $siteName;
    }

    $pag = paginate(
        $pdo,
        "SELECT COUNT(*) FROM cms_polls p {$whereSql}",
        $params,
        $perPage
    );
    ['totalPages' => $totalPages, 'page' => $page, 'offset' => $offset] = $pag;

    $listStmt = $pdo->prepare(
        "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
         FROM cms_polls p
         {$whereSql}
         ORDER BY COALESCE(p.start_date, p.created_at) DESC, p.id DESC
         LIMIT ? OFFSET ?"
    );
    $listStmt->execute(array_merge($params, [$perPage, $offset]));
    $polls = array_map(
        static fn(array $item): array => hydratePollPresentation($item),
        $listStmt->fetchAll()
    );

    if ($polls !== []) {
        $pollHashes = [];
        foreach ($polls as $listedPoll) {
            $pollHashes[(int)$listedPoll['id']] = pollIpHash((int)$listedPoll['id']);
        }

        $placeholders = implode(',', array_fill(0, count($pollHashes), '?'));
        $voteLookup = [];
        $voteLookupStmt = $pdo->prepare(
            "SELECT poll_id
             FROM cms_poll_votes
             WHERE ip_hash IN ({$placeholders})"
        );
        $voteLookupStmt->execute(array_values($pollHashes));
        foreach ($voteLookupStmt->fetchAll(PDO::FETCH_COLUMN) as $votedPollId) {
            $voteLookup[(int)$votedPollId] = true;
        }

        foreach ($polls as &$listPoll) {
            $listPoll['has_voted'] = isset($voteLookup[(int)$listPoll['id']]);
        }
        unset($listPoll);
    }
}

$voteErrorMessages = [
    'too_many' => 'Příliš mnoho pokusů, zkuste to prosím později.',
    'closed' => 'Tato anketa už není aktivní.',
    'no_option' => 'Vyberte prosím jednu z možností.',
    'invalid_option' => 'Neplatná možnost hlasování.',
    'already_voted' => 'Z této IP adresy už bylo hlasováno.',
];

$listingMetaDescription = $archiv
    ? 'Archiv uzavřených anket na webu ' . $siteName
    : 'Přehled aktivních anket na webu ' . $siteName;
if ($q !== '') {
    $listingMetaDescription = 'Výsledky hledání v anketách pro dotaz „' . $q . '“ na webu ' . $siteName;
}

$pageData = [
    'title' => $pageMetaTitle,
    'meta' => [
        'title' => $pageMetaTitle,
        'description' => $poll !== null
            ? $metaDescription
            : $listingMetaDescription,
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
        'isEmbedded' => $isEmbedded,
        'q' => $q,
    ],
    'current_nav' => 'polls',
    'body_class' => 'page-polls',
    'page_kind' => $poll !== null ? 'detail' : 'listing',
    'admin_edit_url' => $poll !== null
        ? BASE_URL . '/admin/polls_form.php?id=' . (int)$poll['id']
        : BASE_URL . '/admin/polls.php',
];

if ($isEmbedded) {
    renderPublicEmbedPage($pageData);
    exit;
}

renderPublicPage($pageData);
