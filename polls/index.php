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
$archiv = isset($_GET['archiv']);

$voteError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pollId !== null && isset($_POST['vote'])) {
    verifyCsrf();

    if (honeypotTriggered()) {
        header('Location: ' . BASE_URL . '/polls/index.php?id=' . $pollId . '&voted=1');
        exit;
    }

    rateLimit('poll_vote', 5, 60);

    $optionId = inputInt('post', 'option_id');

    $stmt = $pdo->prepare(
        "SELECT * FROM cms_polls WHERE id = ? AND status = 'active'
         AND (start_date IS NULL OR start_date <= ?) AND (end_date IS NULL OR end_date > ?)"
    );
    $stmt->execute([$pollId, $now, $now]);
    $votePoll = $stmt->fetch();

    if (!$votePoll) {
        $voteError = 'closed';
    } elseif ($optionId === null) {
        $voteError = 'no_option';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM cms_poll_options WHERE id = ? AND poll_id = ?");
        $stmt->execute([$optionId, $pollId]);
        if (!$stmt->fetch()) {
            $voteError = 'invalid_option';
        } else {
            $ipHash = pollIpHash($pollId);
            try {
                $pdo->prepare(
                    "INSERT INTO cms_poll_votes (poll_id, option_id, ip_hash) VALUES (?, ?, ?)"
                )->execute([$pollId, $optionId, $ipHash]);
                header('Location: ' . BASE_URL . '/polls/index.php?id=' . $pollId . '&voted=1');
                exit;
            } catch (\PDOException $e) {
                $voteError = 'already_voted';
            }
        }
    }
}

$pageTitle = 'Ankety';
$pageMetaTitle = 'Ankety – ' . $siteName;
$pageUrl = BASE_URL . '/polls/index.php';
$poll = null;
$options = [];
$hasVoted = false;
$voted = isset($_GET['voted']);
$polls = [];
$totalPages = 1;
$page = 1;
$isActive = false;
$showForm = false;
$totalVotes = 0;

if ($pollId !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_polls WHERE id = ?");
    $stmt->execute([$pollId]);
    $poll = $stmt->fetch();

    if (!$poll) {
        header('Location: ' . BASE_URL . '/polls/index.php');
        exit;
    }

    $pageTitle = $poll['question'] . ' – Ankety';
    $pageMetaTitle = $poll['question'] . ' – Ankety – ' . $siteName;
    $pageUrl = BASE_URL . '/polls/index.php?id=' . (int)$poll['id'];

    $stmt = $pdo->prepare(
        "SELECT o.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE option_id = o.id) AS vote_count
         FROM cms_poll_options o WHERE o.poll_id = ? ORDER BY o.sort_order, o.id"
    );
    $stmt->execute([$pollId]);
    $options = $stmt->fetchAll();

    $ipHash = pollIpHash($pollId);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ? AND ip_hash = ?");
    $stmt->execute([$pollId, $ipHash]);
    $hasVoted = (int)$stmt->fetchColumn() > 0;

    $isActive = $poll['status'] === 'active'
        && ($poll['start_date'] === null || $poll['start_date'] <= $now)
        && ($poll['end_date'] === null || $poll['end_date'] > $now);

    foreach ($options as $option) {
        $totalVotes += (int)$option['vote_count'];
    }

    $showForm = $isActive && !$hasVoted && !$voted;
} else {
    $perPage = 10;
    $page = max(1, (int)($_GET['strana'] ?? 1));

    if ($archiv) {
        $pageTitle = 'Archiv anket';
        $pageMetaTitle = 'Archiv anket – ' . $siteName;
        $pageUrl = BASE_URL . '/polls/index.php?archiv=1';
        $countSql = "SELECT COUNT(*) FROM cms_polls WHERE status = 'closed' OR (end_date IS NOT NULL AND end_date <= :now1)";
        $listSql = "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
                    FROM cms_polls p WHERE p.status = 'closed' OR (p.end_date IS NOT NULL AND p.end_date <= :now1)
                    ORDER BY p.created_at DESC LIMIT :lim OFFSET :off";
        $countBind = [':now1' => $now];
    } else {
        $countSql = "SELECT COUNT(*) FROM cms_polls WHERE status = 'active'
                     AND (start_date IS NULL OR start_date <= :now1) AND (end_date IS NULL OR end_date > :now2)";
        $listSql = "SELECT p.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
                    FROM cms_polls p WHERE p.status = 'active'
                    AND (p.start_date IS NULL OR p.start_date <= :now1) AND (p.end_date IS NULL OR p.end_date > :now2)
                    ORDER BY p.created_at DESC LIMIT :lim OFFSET :off";
        $countBind = [':now1' => $now, ':now2' => $now];
    }

    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($countBind);
    $total = (int)$stmtCount->fetchColumn();
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmtList = $pdo->prepare($listSql);
    foreach ($countBind as $key => $value) {
        $stmtList->bindValue($key, $value);
    }
    $stmtList->bindValue(':lim', $perPage, PDO::PARAM_INT);
    $stmtList->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmtList->execute();
    $polls = $stmtList->fetchAll();

    foreach ($polls as &$listPoll) {
        $pollIp = pollIpHash((int)$listPoll['id']);
        $stmtVoted = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ? AND ip_hash = ?");
        $stmtVoted->execute([(int)$listPoll['id'], $pollIp]);
        $listPoll['has_voted'] = (int)$stmtVoted->fetchColumn() > 0;
    }
    unset($listPoll);
}

$voteErrorMessages = [
    'too_many' => 'Příliš mnoho pokusů, zkuste to prosím později.',
    'closed' => 'Tato anketa již není aktivní.',
    'no_option' => 'Vyberte prosím jednu z možností.',
    'invalid_option' => 'Neplatná možnost.',
    'already_voted' => 'Z této IP adresy již bylo hlasováno.',
];

renderPublicPage([
    'title' => $pageMetaTitle,
    'meta' => [
        'title' => $pageMetaTitle,
        'url' => $pageUrl,
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
