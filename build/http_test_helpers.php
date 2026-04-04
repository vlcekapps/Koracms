<?php
declare(strict_types=1);

if (!function_exists('fetchUrl')) {
    /**
     * @return array{status:string,headers:array<int,string>,body:string}
     */
    function fetchUrl(string $url, string $cookie = '', int $maxRedirects = 20): array
    {
        $headers = [
            'User-Agent: KoraHttpIntegration/1.0',
        ];
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers) . "\r\n",
                'ignore_errors' => true,
                'timeout' => 20,
                'follow_location' => $maxRedirects > 0 ? 1 : 0,
                'max_redirects' => $maxRedirects,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = $responseHeaders[0] ?? 'HTTP status unknown';

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => is_string($body) ? $body : '',
        ];
    }
}

if (!function_exists('postUrl')) {
    /**
     * @param array<string,string> $fields
     * @return array<string,string>
     */
    function refreshPostFieldsCsrfToken(array $fields, string $cookie): array
    {
        if (!array_key_exists('csrf_token', $fields) || $cookie === '') {
            return $fields;
        }

        if (preg_match('/(?:^|;\s*)PHPSESSID=([^;]+)/', $cookie, $matches) !== 1) {
            return $fields;
        }

        $sessionId = $matches[1];
        $originalSessionId = session_status() === PHP_SESSION_ACTIVE ? session_id() : '';
        $hadActiveSession = session_status() === PHP_SESSION_ACTIVE;

        if ($hadActiveSession) {
            session_write_close();
        }

        session_id($sessionId);
        session_start();
        $fields['csrf_token'] = (string)($_SESSION['csrf_token'] ?? $fields['csrf_token']);
        session_write_close();

        if ($hadActiveSession && $originalSessionId !== '' && $originalSessionId !== $sessionId) {
            session_id($originalSessionId);
            session_start();
        }

        return $fields;
    }

    /**
     * @param array<string,string> $fields
     * @return array{status:string,headers:array<int,string>,body:string}
     */
    function postUrl(string $url, array $fields, string $cookie = '', int $maxRedirects = 20): array
    {
        $fields = refreshPostFieldsCsrfToken($fields, $cookie);
        $headers = [
            'User-Agent: KoraHttpIntegration/1.0',
            'Content-Type: application/x-www-form-urlencoded',
        ];
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => http_build_query($fields),
                'ignore_errors' => true,
                'timeout' => 20,
                'follow_location' => $maxRedirects > 0 ? 1 : 0,
                'max_redirects' => $maxRedirects,
            ],
        ]);

        $body = file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = $responseHeaders[0] ?? 'HTTP status unknown';

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => is_string($body) ? $body : '',
        ];
    }
}

if (!function_exists('postMultipartUrl')) {
    /**
     * @param array<string,string> $fields
     * @param array<string,array{path:string,filename:string,type?:string}> $files
     * @return array{status:string,headers:array<int,string>,body:string}
     */
    function postMultipartUrl(string $url, array $fields, array $files, string $cookie = '', int $maxRedirects = 20): array
    {
        $fields = refreshPostFieldsCsrfToken($fields, $cookie);
        $boundary = '----KoraHttpIntegration' . bin2hex(random_bytes(8));
        $eol = "\r\n";
        $body = '';

        foreach ($fields as $fieldName => $fieldValue) {
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Disposition: form-data; name="' . $fieldName . '"' . $eol . $eol;
            $body .= $fieldValue . $eol;
        }

        foreach ($files as $fieldName => $file) {
            $contents = (string)file_get_contents($file['path']);
            $body .= '--' . $boundary . $eol;
            $body .= 'Content-Disposition: form-data; name="' . $fieldName . '"; filename="' . $file['filename'] . '"' . $eol;
            $body .= 'Content-Type: ' . ($file['type'] ?? 'application/octet-stream') . $eol . $eol;
            $body .= $contents . $eol;
        }

        $body .= '--' . $boundary . '--' . $eol;

        $headers = [
            'User-Agent: KoraHttpIntegration/1.0',
            'Content-Type: multipart/form-data; boundary=' . $boundary,
        ];
        if ($cookie !== '') {
            $headers[] = 'Cookie: ' . $cookie;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $body,
                'ignore_errors' => true,
                'timeout' => 20,
                'follow_location' => $maxRedirects > 0 ? 1 : 0,
                'max_redirects' => $maxRedirects,
            ],
        ]);

        $responseBody = file_get_contents($url, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $status = $responseHeaders[0] ?? 'HTTP status unknown';

        return [
            'status' => $status,
            'headers' => $responseHeaders,
            'body' => is_string($responseBody) ? $responseBody : '',
        ];
    }
}

if (!function_exists('responseHasLocationHeader')) {
    function responseHasLocationHeader(array $headers, string $expectedPath, string $baseUrl = ''): bool
    {
        $expectedAbsolute = rtrim($baseUrl, '/') . $expectedPath;
        foreach ($headers as $header) {
            if (stripos($header, 'Location:') !== 0) {
                continue;
            }

            $location = trim(substr($header, 9));
            if ($location === $expectedPath || $location === $expectedAbsolute) {
                return true;
            }

            $parsedPath = (string)(parse_url($location, PHP_URL_PATH) ?? '');
            $parsedQuery = (string)(parse_url($location, PHP_URL_QUERY) ?? '');
            $normalizedLocation = $parsedPath . ($parsedQuery !== '' ? '?' . $parsedQuery : '');
            if ($normalizedLocation === $expectedPath) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('extractHiddenInputValue')) {
    function extractHiddenInputValue(string $html, string $name): string
    {
        $pattern = '/<input[^>]+name="' . preg_quote($name, '/') . '"[^>]+value="([^"]*)"/i';
        if (preg_match($pattern, $html, $matches) === 1) {
            return html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return '';
    }
}

if (!function_exists('koraPrimeTestSession')) {
    /**
     * @param array<string,mixed> $sessionData
     * @return array{id:string,cookie:string,csrf:string}
     */
    function koraPrimeTestSession(array $sessionData, ?string $sessionId = null): array
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $sessionId = $sessionId !== null && $sessionId !== '' ? $sessionId : 'koratest-' . bin2hex(random_bytes(6));
        session_id($sessionId);
        session_start();
        foreach ($sessionData as $key => $value) {
            $_SESSION[$key] = $value;
        }
        $csrfToken = function_exists('csrfToken') ? csrfToken() : '';
        session_write_close();

        return [
            'id' => $sessionId,
            'cookie' => 'PHPSESSID=' . $sessionId,
            'csrf' => $csrfToken,
        ];
    }
}
