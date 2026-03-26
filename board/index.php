<?php
require_once __DIR__ . '/../db.php';
checkMaintenanceMode();

if (!isModuleEnabled('board')) {
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

function groupBoardByCategory(array $items): array
{
    $grouped = [];
    foreach ($items as $item) {
        $grouped[$item['category_name'] ?: 'Ostatní'][] = $item;
    }
    ksort($grouped);
    return $grouped;
}

function boardCountLabel(int $count): string
{
    return $count . ' ' . ($count === 1 ? 'položka' : ($count < 5 ? 'položky' : 'položek'));
}

$pdo = db_connect();
$siteName = getSetting('site_name', 'Kora CMS');
$boardLabel = boardModulePublicLabel();

$items = $pdo->query(
    "SELECT b.*, COALESCE(c.name, '') AS category_name
     FROM cms_board b
     LEFT JOIN cms_board_categories c ON c.id = b.category_id
     WHERE b.status = 'published' AND b.is_published = 1
     ORDER BY b.is_pinned DESC, b.posted_date DESC, b.created_at DESC, b.title"
)->fetchAll();
$items = array_map(
    static fn(array $document): array => hydrateBoardPresentation($document),
    $items
);

$today = date('Y-m-d');
$current = [];
$archive = [];
foreach ($items as $item) {
    if ($item['removal_date'] === null || $item['removal_date'] >= $today) {
        $current[] = $item;
    } else {
        $archive[] = $item;
    }
}

$currentGrouped = groupBoardByCategory($current);
$archiveGrouped = groupBoardByCategory($archive);

renderPublicPage([
    'title' => $boardLabel . ' - ' . $siteName,
    'meta' => [
        'title' => $boardLabel . ' - ' . $siteName,
        'url' => BASE_URL . '/board/index.php',
    ],
    'view' => 'modules/board-index',
    'view_data' => [
        'boardLabel' => $boardLabel,
        'current' => $current,
        'archive' => $archive,
        'currentGrouped' => $currentGrouped,
        'archiveGrouped' => $archiveGrouped,
        'showCurrentCategoryHeadings' => count($currentGrouped) > 1,
        'showArchiveCategoryHeadings' => count($archiveGrouped) > 1,
        'archiveCountLabel' => boardCountLabel(count($archive)),
    ],
    'current_nav' => 'board',
    'body_class' => 'page-board-index',
    'page_kind' => 'listing',
    'admin_edit_url' => BASE_URL . '/admin/board.php',
]);
