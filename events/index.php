<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('events')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$perPage = max(1, (int)getSetting('events_per_page', '10'));

$upPage = max(1, (int)($_GET['up_page'] ?? 1));
$upTotal = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_events WHERE status = 'published' AND is_published = 1 AND event_date >= NOW()"
)->fetchColumn();
$upPages = max(1, (int)ceil($upTotal / $perPage));
$upPage = min($upPage, $upPages);
$upOffset = ($upPage - 1) * $perPage;

$stmtUp = $pdo->prepare(
    "SELECT * FROM cms_events
     WHERE status = 'published' AND is_published = 1 AND event_date >= NOW()
     ORDER BY event_date ASC LIMIT :lim OFFSET :off"
);
$stmtUp->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtUp->bindValue(':off', $upOffset, PDO::PARAM_INT);
$stmtUp->execute();
$upcoming = $stmtUp->fetchAll();

$pastPage = max(1, (int)($_GET['past_page'] ?? 1));
$pastTotal = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_events WHERE status = 'published' AND is_published = 1 AND event_date < NOW()"
)->fetchColumn();
$pastPages = max(1, (int)ceil($pastTotal / $perPage));
$pastPage = min($pastPage, $pastPages);
$pastOffset = ($pastPage - 1) * $perPage;

$stmtPast = $pdo->prepare(
    "SELECT * FROM cms_events
     WHERE status = 'published' AND is_published = 1 AND event_date < NOW()
     ORDER BY event_date DESC LIMIT :lim OFFSET :off"
);
$stmtPast->bindValue(':lim', $perPage, PDO::PARAM_INT);
$stmtPast->bindValue(':off', $pastOffset, PDO::PARAM_INT);
$stmtPast->execute();
$past = $stmtPast->fetchAll();

function eventsPagerUrl(string $param, int $page, string $otherParam): string
{
    $otherValue = (int)($_GET[$otherParam] ?? 1);
    $query = [];
    if ($page > 1) {
        $query[$param] = $page;
    }
    if ($otherValue > 1) {
        $query[$otherParam] = $otherValue;
    }

    return 'index.php' . ($query ? '?' . http_build_query($query) : '');
}

function eventsPager(int $current, int $total, string $param, string $otherParam, string $anchor): string
{
    if ($total <= 1) {
        return '';
    }

    $html = '<nav class="pager" aria-label="Stránkování">';
    if ($current > 1) {
        $html .= '<a href="' . h(eventsPagerUrl($param, $current - 1, $otherParam)) . '#' . $anchor . '"><span aria-hidden="true">←</span> Předchozí</a>';
    }
    $html .= '<span aria-current="page">' . $current . '&nbsp;/&nbsp;' . $total . '</span>';
    if ($current < $total) {
        $html .= '<a href="' . h(eventsPagerUrl($param, $current + 1, $otherParam)) . '#' . $anchor . '">Další <span aria-hidden="true">→</span></a>';
    }
    $html .= '</nav>';

    return $html;
}

renderPublicPage([
    'title' => 'Akce – ' . $siteName,
    'meta' => [
        'title' => 'Akce – ' . $siteName,
        'url' => BASE_URL . '/events/index.php',
    ],
    'view' => 'modules/events-index',
    'view_data' => [
        'upcoming' => $upcoming,
        'past' => $past,
        'upTotal' => $upTotal,
        'pastTotal' => $pastTotal,
        'upPage' => $upPage,
        'upPages' => $upPages,
        'pastPage' => $pastPage,
        'pastPages' => $pastPages,
    ],
    'current_nav' => 'events',
    'body_class' => 'page-events-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/events.php',
]);
