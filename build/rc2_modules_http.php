<?php

declare(strict_types=1);

/** @return list<string> */
function rc2ModuleRevisionHttpChecks(PDO $pdo, string $baseUrl): array
{
    $issues = [];
    $users = [];
    $articles = [];
    $blogId = 0;
    $newsId = 0;
    $pollId = 0;
    $placeId = 0;
    $prefix = 'rc2-revisions-' . bin2hex(random_bytes(5));
    $settings = [];
    foreach (['blog', 'news', 'polls', 'places'] as $module) {
        $settings['module_' . $module] = getSetting('module_' . $module, '0');
    }
    $check = static function (string $path, string $cookie, int $status, bool $visible) use ($baseUrl, $prefix, &$issues): void {
        $response = fetchUrl($baseUrl . BASE_URL . $path, $cookie, 0);
        if (httpIntegrationStatusCode($response) !== $status
            || str_contains($response['body'], $prefix . '-secret') !== $visible
            || ($status !== 302 && !httpIntegrationHeaderContains($response, 'Cache-Control', 'no-store'))) {
            $issues[] = $path . ': wrong authorization, leaked/missing revision, or missing private headers';
        }
    };
    try {
        foreach ($settings as $key => $value) {
            saveSetting($key, '1');
        }
        clearSettingsCache();
        $sessions = [];
        foreach (['author', 'editor', 'moderator'] as $role) {
            $pdo->prepare("INSERT INTO cms_users (email,password,first_name,last_name,role,is_superadmin,is_confirmed)
                VALUES (?,?,'RC2','Revision',?,0,1)")
                ->execute([$prefix . '-' . $role . '@example.test', password_hash('RC2-test-only-123!', PASSWORD_DEFAULT), $role]);
            $users[$role] = (int)$pdo->lastInsertId();
            $sessions[$role] = koraPrimeTestSession(['cms_logged_in' => true, 'cms_user_id' => $users[$role],
                'cms_user_role' => $role, 'cms_superadmin' => false]);
        }
        $pdo->prepare('INSERT INTO cms_blogs (name,slug) VALUES (?,?)')->execute([$prefix, $prefix]);
        $blogId = (int)$pdo->lastInsertId();
        $pdo->prepare("INSERT INTO cms_blog_members (blog_id,user_id,member_role) VALUES (?,?,'author')")
            ->execute([$blogId, $users['author']]);
        foreach (['author', 'editor'] as $role) {
            $pdo->prepare("INSERT INTO cms_articles (blog_id,author_id,title,slug,content,status) VALUES (?,?,?,?,?,'draft')")
                ->execute([$blogId, $users[$role], $prefix, $prefix . '-' . $role, 'Draft']);
            $articles[$role] = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("INSERT INTO cms_news (title,slug,content,author_id,status) VALUES (?,?,?,?,'draft')")
            ->execute([$prefix, $prefix, 'Draft', $users['editor']]);
        $newsId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_polls (question,slug) VALUES (?,?)')->execute([$prefix, $prefix]);
        $pollId = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO cms_places (name,slug) VALUES (?,?)')->execute([$prefix, $prefix]);
        $placeId = (int)$pdo->lastInsertId();
        $targets = [['article', $articles['author']], ['article', $articles['editor']],
            ['news', $newsId], ['poll', $pollId], ['place', $placeId]];
        foreach ($targets as [$type, $id]) {
            $pdo->prepare('INSERT INTO cms_revisions (entity_type,entity_id,field_name,old_value,new_value) VALUES (?,?,?,?,?)')
                ->execute([$type, $id, 'content', $prefix . '-secret', 'Changed']);
            $path = '/admin/revisions.php?type=' . $type . '&id=' . $id;
            $check($path, '', 302, false);
            $check($path, $sessions['editor']['cookie'], 200, true);
            $check($path, $sessions['moderator']['cookie'], 403, false);
            $owned = $type === 'article' && $id === $articles['author'];
            $check($path, $sessions['author']['cookie'], $owned ? 200 : 403, $owned);
        }
        saveSetting('module_blog', '0');
        clearSettingsCache();
        $check('/admin/revisions.php?type=article&id=' . $articles['author'], $sessions['editor']['cookie'], 403, false);
    } finally {
        foreach ($settings as $key => $value) {
            saveSetting($key, $value);
        }
        clearSettingsCache();
        foreach ($articles as $id) {
            $pdo->prepare("DELETE FROM cms_revisions WHERE entity_type='article' AND entity_id=?")->execute([$id]);
            $pdo->prepare('DELETE FROM cms_articles WHERE id=?')->execute([$id]);
        }
        foreach ([['news', 'cms_news', $newsId], ['poll', 'cms_polls', $pollId], ['place', 'cms_places', $placeId]] as [$type, $table, $id]) {
            if ($id > 0) {
                $pdo->prepare('DELETE FROM cms_revisions WHERE entity_type=? AND entity_id=?')->execute([$type, $id]);
                $pdo->prepare("DELETE FROM {$table} WHERE id=?")->execute([$id]);
            }
        }
        if ($blogId > 0) {
            $pdo->prepare('DELETE FROM cms_blog_members WHERE blog_id=?')->execute([$blogId]);
            $pdo->prepare('DELETE FROM cms_blogs WHERE id=?')->execute([$blogId]);
        }
        foreach ($users as $id) {
            $pdo->prepare('DELETE FROM cms_users WHERE id=?')->execute([$id]);
        }
    }
    return $issues;
}
