<?php

declare(strict_types=1);

/** @return list<string> */
function rcPublicationHttpChecks(PDO $pdo, string $baseUrl): array
{
    $issues = [];
    $prefix = 'rcwindow' . bin2hex(random_bytes(5));
    $articleIds = [];
    $pageIds = [];
    $blogId = 0;
    $paths = [];
    try {
        $pdo->prepare('INSERT INTO cms_blogs (name, slug) VALUES (?, ?)')->execute([$prefix, $prefix]);
        $blogId = (int)$pdo->lastInsertId();
        foreach (['active', 'future', 'expired', 'trash', 'draft'] as $state) {
            $start = $state === 'future' ? 'DATE_ADD(NOW(), INTERVAL 1 DAY)' : 'DATE_SUB(NOW(), INTERVAL 1 DAY)';
            $end = $state === 'expired' ? 'DATE_SUB(NOW(), INTERVAL 1 SECOND)' : 'NULL';
            $deleted = $state === 'trash' ? 'NOW()' : 'NULL';
            $status = $state === 'draft' ? 'draft' : 'published';
            $slug = $prefix . '-article-' . $state;
            $pdo->prepare("INSERT INTO cms_articles (blog_id, title, slug, content, status, publish_at, unpublish_at, deleted_at)
                VALUES (?, ?, ?, ?, ?, {$start}, {$end}, {$deleted})")
                ->execute([$blogId, $prefix . ' ' . $slug, $slug, 'Public window test', $status]);
            $articleIds[] = (int)$pdo->lastInsertId();
            $paths[$slug] = ['path' => '/' . $prefix . '/' . $slug, 'visible' => $state === 'active'];
            foreach ([null, $blogId] as $pageBlogId) {
                $slug = $prefix . ($pageBlogId === null ? '-global-' : '-blogpage-') . $state;
                $pdo->prepare("INSERT INTO cms_pages (blog_id, title, slug, content, status, is_published, publish_at, unpublish_at, deleted_at)
                    VALUES (?, ?, ?, ?, ?, 1, {$start}, {$end}, {$deleted})")
                    ->execute([$pageBlogId, $prefix . ' ' . $slug, $slug, 'Public window test', $status]);
                $pageIds[] = (int)$pdo->lastInsertId();
                $paths[$slug] = ['path' => $pageBlogId === null ? '/page.php?slug=' . $slug : '/' . $prefix . '/stranka/' . $slug,
                    'visible' => $state === 'active'];
            }
        }
        foreach ($paths as $slug => $item) {
            $response = fetchUrl($baseUrl . BASE_URL . $item['path'], '', 0);
            $expected = $item['visible'] ? 200 : 404;
            if (httpIntegrationStatusCode($response) !== $expected) {
                $issues[] = $slug . ': expected ' . $expected . ', got ' . $response['status'];
            }
        }
        foreach (array_slice($pageIds, -2) as $pageId) {
            $token = bin2hex(random_bytes(16));
            $pdo->prepare('UPDATE cms_pages SET preview_token = ? WHERE id = ?')->execute([$token, $pageId]);
            $stmt = $pdo->prepare('SELECT slug, blog_id FROM cms_pages WHERE id = ?');
            $stmt->execute([$pageId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $path = $row['blog_id'] === null ? '/page.php?slug=' . $row['slug'] : '/' . $prefix . '/stranka/' . $row['slug'];
            foreach ([$token => 200, 'invalid-token' => 404] as $preview => $status) {
                $url = $baseUrl . BASE_URL . $path . (str_contains($path, '?') ? '&' : '?') . 'preview=' . $preview;
                $response = fetchUrl($url, '', 0, 'facebookexternalhit/1.1');
                $cache = httpIntegrationHeaderValue($response, 'Cache-Control');
                if (httpIntegrationStatusCode($response) !== $status
                    || !str_contains($cache, 'no-store') || str_contains($cache, 'public') || str_contains($cache, 's-maxage')
                    || !httpIntegrationHeaderContains($response, 'X-Robots-Tag', 'noindex')
                    || !httpIntegrationHeaderContains($response, 'Referrer-Policy', 'no-referrer')) {
                    $issues[] = 'Crawler preview must preserve private headers and expected status for page ' . $pageId;
                }
            }
        }
        foreach (['/search.php?q=' . $prefix, '/sitemap.php', '/' . $prefix, '/feed.php?blog=' . $prefix] as $path) {
            $response = fetchUrl($baseUrl . BASE_URL . $path);
            foreach ($paths as $slug => $item) {
                if (!$item['visible'] && str_contains($response['body'], $slug)) {
                    $issues[] = $path . ' exposed hidden content ' . $slug;
                }
            }
            if ($path === '/sitemap.php' || str_starts_with($path, '/search.php')) {
                $activePagePath = '/' . $prefix . '/stranka/' . $prefix . '-blogpage-active';
                if (!str_contains($response['body'], $activePagePath)) {
                    $issues[] = $path . ' omitted the canonical blog page URL';
                }
            }
        }
    } finally {
        foreach ($articleIds as $id) {
            $pdo->prepare("DELETE FROM cms_page_views WHERE page_type = 'article' AND page_ref_id = ?")->execute([$id]);
            $pdo->prepare('DELETE FROM cms_articles WHERE id = ?')->execute([$id]);
        }
        foreach ($pageIds as $id) {
            $pdo->prepare("DELETE FROM cms_page_views WHERE page_type = 'page' AND page_ref_id = ?")->execute([$id]);
            $pdo->prepare('DELETE FROM cms_pages WHERE id = ?')->execute([$id]);
        }
        if ($blogId > 0) {
            $pdo->prepare('DELETE FROM cms_blogs WHERE id = ?')->execute([$blogId]);
        }
    }
    return $issues;
}
