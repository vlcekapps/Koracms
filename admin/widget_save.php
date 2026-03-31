<?php
require_once __DIR__ . '/../db.php';
requireCapability('settings_manage', 'Přístup odepřen.');
verifyCsrf();

$id = inputInt('post', 'widget_id');
if ($id === null) {
    header('Location: ' . BASE_URL . '/admin/widgets.php');
    exit;
}

$pdo = db_connect();
$widget = $pdo->prepare("SELECT * FROM cms_widgets WHERE id = ?");
$widget->execute([$id]);
$widget = $widget->fetch();
if (!$widget) {
    header('Location: ' . BASE_URL . '/admin/widgets.php');
    exit;
}

$title = trim($_POST['title'] ?? '');
$zone = trim($_POST['zone'] ?? $widget['zone']);
$isActive = isset($_POST['is_active']) ? 1 : 0;

$zones = widgetZoneDefinitions();
if (!isset($zones[$zone])) {
    $zone = $widget['zone'];
}

// Sestavit settings JSON z POST dat specifických pro typ widgetu
$settings = widgetSettings($widget);
$type = $widget['widget_type'];

  switch ($type) {
    case 'intro':
        $settings['content'] = $_POST['widget_content'] ?? ($settings['content'] ?? ($settings['text'] ?? ''));
        unset($settings['text']);
        break;
    case 'latest_articles':
        $settings['count'] = max(1, min(50, (int)($_POST['widget_count'] ?? 5)));
        $rawBlogId = (int)($_POST['widget_blog_id'] ?? 0);
        $settings['blog_id'] = $rawBlogId === -1 ? -1 : ($rawBlogId > 0 ? $rawBlogId : null);
        break;
    case 'latest_news':
    case 'board':
    case 'upcoming_events':
    case 'latest_downloads':
    case 'latest_faq':
    case 'latest_places':
        $settings['count'] = max(1, min(50, (int)($_POST['widget_count'] ?? 5)));
        break;
    case 'latest_podcast_episodes':
        $settings['count'] = max(1, min(50, (int)($_POST['widget_count'] ?? 5)));
        $settings['show_id'] = (int)($_POST['widget_show_id'] ?? 0) ?: 0;
        break;
    case 'featured_article':
        $settings['source'] = in_array($_POST['widget_source'] ?? '', ['blog', 'board', 'poll', 'newsletter'], true)
            ? $_POST['widget_source'] : 'blog';
        break;
    case 'newsletter':
        $settings['cta_text'] = trim($_POST['widget_cta_text'] ?? '');
        break;
    case 'search':
        $settings['cta_text'] = trim($_POST['widget_cta_text'] ?? '');
        break;
    case 'gallery_preview':
        $settings['album_id'] = (int)($_POST['widget_album_id'] ?? 0);
        break;
    case 'selected_form':
        $settings['form_id'] = (int)($_POST['widget_form_id'] ?? 0) ?: 0;
        break;
    case 'custom_html':
        $settings['content'] = $_POST['widget_content'] ?? '';
        break;
    case 'social_links':
        foreach (array_keys(widgetSocialLinkDefinitions()) as $socialSettingKey) {
            $settings[$socialSettingKey] = normalizeWidgetExternalUrl((string)($_POST['widget_' . $socialSettingKey] ?? ''));
        }
        break;
}

// Pokud se mění zóna, dát na konec nové zóny
if ($zone !== $widget['zone']) {
    $sortOrder = (int)$pdo->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM cms_widgets WHERE zone = " . $pdo->quote($zone))->fetchColumn();
} else {
    $sortOrder = (int)$widget['sort_order'];
}

$pdo->prepare("UPDATE cms_widgets SET title = ?, zone = ?, settings = ?, sort_order = ?, is_active = ? WHERE id = ?")
    ->execute([$title, $zone, json_encode($settings, JSON_UNESCAPED_UNICODE), $sortOrder, $isActive, $id]);

logAction('widget_save', "id={$id} type={$type} zone={$zone}");
header('Location: ' . appendUrlQuery(BASE_URL . '/admin/widgets.php', ['zone' => $zone]) . '#widget-zone-' . rawurlencode($zone));
exit;
