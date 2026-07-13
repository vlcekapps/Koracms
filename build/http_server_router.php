<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$requestUri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$requestPath = parse_url($requestUri, PHP_URL_PATH);
if (!is_string($requestPath) || $requestPath === '') {
    $requestPath = '/';
}

$decodedPath = rawurldecode($requestPath);
$normalizedPath = '/' . ltrim(str_replace('\\', '/', $decodedPath), '/');
$relativePath = ltrim($normalizedPath, '/');
$staticPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
$routeScriptPath = null;
$routeScriptWebPath = '';
$routeParams = [];
$routeHandled = false;

/**
 * @param array<string, string> $params
 * @return array{0:?string, 1:string, 2:array<string, string>, 3:bool}
 */
function routeToScript(string $script, array $params = []): array
{
    global $projectRoot;

    $targetPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $script);
    if (!is_file($targetPath)) {
        http_response_code(404);
        return [null, '', [], true];
    }

    return [$targetPath, '/' . str_replace('\\', '/', $script), $params, true];
}

function denyRequest(): bool
{
    http_response_code(403);
    echo "Forbidden\n";
    return true;
}

function isProtectedRequest(string $path): bool
{
    if (str_contains($path, "\0") || str_contains($path, '..')) {
        return true;
    }

    $trimmedPath = ltrim($path, '/');
    $lowerPath = strtolower($trimmedPath);
    $baseName = strtolower(basename($trimmedPath));

    if (
        str_starts_with($lowerPath, '.git')
        || str_starts_with($lowerPath, '.github')
        || str_starts_with($lowerPath, '.claude')
        || str_starts_with($lowerPath, '.codex')
        || str_starts_with($lowerPath, '.cursor')
        || str_starts_with($lowerPath, 'vendor/')
        || str_starts_with($lowerPath, 'node_modules/')
        || str_starts_with($lowerPath, 'uploads/forms/')
        || str_starts_with($lowerPath, 'uploads/backups/')
        || str_starts_with($lowerPath, 'uploads/gallery/')
        || str_starts_with($lowerPath, 'uploads/places/')
        || str_starts_with($lowerPath, 'uploads/podcasts/')
    ) {
        return true;
    }

    if (preg_match('#^uploads/media/.+\.svg$#i', $trimmedPath) === 1) {
        return true;
    }

    if (in_array($baseName, [
        '.env',
        '.php-cs-fixer.dist.php',
        'auth.php',
        'composer.json',
        'composer.lock',
        'config.php',
        'db.php',
        'phpstan.neon.dist',
    ], true)) {
        return true;
    }

    return $baseName !== 'robots.txt'
        && preg_match('/\.(?:inc|txt|log|sql|bak|sh|cfg|env)$/i', $baseName) === 1;
}

if (isProtectedRequest($normalizedPath)) {
    return denyRequest();
}

if (is_file($staticPath)) {
    return false;
}

$routePath = trim($normalizedPath, '/');

if ($routePath === '') {
    [$routeScriptPath, $routeScriptWebPath, $routeParams, $routeHandled] = routeToScript('index.php');
} elseif (preg_match('#^robots\.txt$#i', $routePath) === 1) {
    [$routeScriptPath, $routeScriptWebPath, $routeParams, $routeHandled] = routeToScript('robots.php');
} elseif (preg_match('#^sitemap\.xml$#i', $routePath) === 1) {
    [$routeScriptPath, $routeScriptWebPath, $routeParams, $routeHandled] = routeToScript('sitemap.php');
} elseif (preg_match('#^authors/?$#i', $routePath) === 1) {
    [$routeScriptPath, $routeScriptWebPath, $routeParams, $routeHandled] = routeToScript('authors/index.php');
} elseif (preg_match('#^changelog/?$#i', $routePath) === 1) {
    [$routeScriptPath, $routeScriptWebPath, $routeParams, $routeHandled] = routeToScript('changelog.php');
} else {
    $routeMap = [
        '#^author/([a-z0-9-]+)/?$#i' => ['author.php', ['slug']],
        '#^blog/([a-z0-9-]+)/?$#i' => ['blog/article.php', ['slug']],
        '#^board/kategorie/([a-z0-9-]+)/?$#i' => ['board/index.php', ['category_slug']],
        '#^board/([a-z0-9-]+)/?$#i' => ['board/document.php', ['slug']],
        '#^chat/tema/([a-z0-9-]+)/?$#i' => ['chat/index.php', ['topic_slug']],
        '#^chat/zprava/([0-9]+)/?$#i' => ['chat/message.php', ['id']],
        '#^downloads/kategorie/([a-z0-9-]+)/?$#i' => ['downloads/index.php', ['category_slug']],
        '#^downloads/serie/([a-z0-9-]+)/?$#i' => ['downloads/series.php', ['slug']],
        '#^downloads/([a-z0-9-]+)/?$#i' => ['downloads/item.php', ['slug']],
        '#^events/([a-z0-9-]+)\.ics$#i' => ['events/ics.php', ['slug']],
        '#^events/typ/([a-z0-9-]+)/?$#i' => ['events/index.php', ['type_slug']],
        '#^events/([a-z0-9-]+)/?$#i' => ['events/event.php', ['slug']],
        '#^faq/kategorie/([a-z0-9-]+)/?$#i' => ['faq/index.php', ['category_slug']],
        '#^faq/([a-z0-9-]+)/?$#i' => ['faq/item.php', ['slug']],
        '#^forms/([a-z0-9-]+)/?$#i' => ['forms/index.php', ['slug']],
        '#^food/card/([a-z0-9-]+)/?$#i' => ['food/card.php', ['slug']],
        '#^gallery/album/([a-z0-9-]+)/?$#i' => ['gallery/album.php', ['slug']],
        '#^gallery/photo/([a-z0-9-]+)/?$#i' => ['gallery/photo.php', ['slug']],
        '#^news/([a-z0-9-]+)/?$#i' => ['news/article.php', ['slug']],
        '#^polls/([a-z0-9-]+)/?$#i' => ['polls/index.php', ['slug']],
        '#^places/([a-z0-9-]+)/?$#i' => ['places/place.php', ['slug']],
        '#^podcast/([a-z0-9-]+)/([a-z0-9-]+)/?$#i' => ['podcast/episode.php', ['show', 'slug']],
        '#^podcast/([a-z0-9-]+)/?$#i' => ['podcast/show.php', ['slug']],
        '#^([a-z0-9-]+)/archiv/([0-9]{4})/(0[1-9]|1[0-2])/?$#i' => ['blog_router.php', ['blog_slug', 'archive_year', 'archive_month']],
        '#^([a-z0-9-]+)/kategorie/([a-z0-9-]+)/?$#i' => ['blog_router.php', ['blog_slug', 'category_slug']],
        '#^([a-z0-9-]+)/stitky/([a-z0-9-]+)/?$#i' => ['blog_router.php', ['blog_slug', 'tag_slug']],
        '#^([a-z0-9-]+)/serie/([a-z0-9-]+)/?$#i' => ['blog_router.php', ['blog_slug', 'series_slug']],
        '#^([a-z0-9-]+)/stranka/([a-z0-9-]+)/?$#i' => ['blog_router.php', ['blog_slug', 'page_slug']],
        '#^([a-z0-9-]+)/([a-z0-9-]+)/?$#i' => ['blog_router.php', ['blog_slug', 'slug']],
        '#^([a-z0-9-]+)/?$#i' => ['blog_router.php', ['blog_slug']],
    ];

    foreach ($routeMap as $pattern => [$script, $parameterNames]) {
        if (preg_match($pattern, $routePath, $matches) !== 1) {
            continue;
        }

        $params = [];
        foreach ($parameterNames as $index => $name) {
            $params[$name] = (string)$matches[$index + 1];
        }

        [$routeScriptPath, $routeScriptWebPath, $routeParams, $routeHandled] = routeToScript($script, $params);
        break;
    }
}

if ($routeScriptPath !== null) {
    $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
    $queryParams = [];
    if ($queryString !== '') {
        parse_str($queryString, $queryParams);
    }

    $_GET = array_merge($queryParams, $routeParams);
    $_REQUEST = array_merge($_REQUEST, $routeParams);
    $_SERVER['SCRIPT_FILENAME'] = $routeScriptPath;
    $_SERVER['SCRIPT_NAME'] = $routeScriptWebPath;
    $_SERVER['PHP_SELF'] = $routeScriptWebPath;

    require $routeScriptPath;
    return true;
}

if ($routeHandled) {
    return true;
}

http_response_code(404);
echo "Not Found\n";
return true;
