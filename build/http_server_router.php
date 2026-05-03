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

/**
 * @param array<string, string> $params
 */
function routeToScript(string $script, array $params = []): bool
{
    global $projectRoot;

    $targetPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $script);
    if (!is_file($targetPath)) {
        http_response_code(404);
        return true;
    }

    $queryString = (string)($_SERVER['QUERY_STRING'] ?? '');
    $queryParams = [];
    if ($queryString !== '') {
        parse_str($queryString, $queryParams);
    }

    $_GET = array_merge($queryParams, $params);
    $_REQUEST = array_merge($_REQUEST, $params);
    $_SERVER['SCRIPT_FILENAME'] = $targetPath;

    require $targetPath;
    return true;
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
        || str_starts_with($lowerPath, 'vendor/')
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

if (preg_match('#^robots\.txt$#i', $routePath) === 1) {
    return routeToScript('robots.php');
}

if (preg_match('#^sitemap\.xml$#i', $routePath) === 1) {
    return routeToScript('sitemap.php');
}

if (preg_match('#^authors/?$#i', $routePath) === 1) {
    return routeToScript('authors/index.php');
}

$routeMap = [
    '#^author/([a-z0-9-]+)/?$#i' => ['author.php', ['slug']],
    '#^blog/([a-z0-9-]+)/?$#i' => ['blog/article.php', ['slug']],
    '#^board/([a-z0-9-]+)/?$#i' => ['board/document.php', ['slug']],
    '#^downloads/([a-z0-9-]+)/?$#i' => ['downloads/item.php', ['slug']],
    '#^events/([a-z0-9-]+)\.ics$#i' => ['events/ics.php', ['slug']],
    '#^events/([a-z0-9-]+)/?$#i' => ['events/event.php', ['slug']],
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

    return routeToScript($script, $params);
}

http_response_code(404);
echo "Not Found\n";
return true;
