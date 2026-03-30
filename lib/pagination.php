<?php
// Sdílený stránkovací helper – extrahuje opakující se logiku z výpisových modulů

/**
 * Vypočítá stránkovací parametry z GET požadavku.
 *
 * @return array{perPage: int, total: int, totalPages: int, page: int, offset: int}
 */
function paginate(PDO $pdo, string $countQuery, array $countParams, int $perPage = 10): array
{
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $total      = (int)$stmt->fetchColumn();
    $totalPages = max(1, (int)ceil($total / max(1, $perPage)));
    $page       = max(1, min($totalPages, (int)($_GET['strana'] ?? 1)));
    $offset     = ($page - 1) * $perPage;

    return compact('perPage', 'total', 'totalPages', 'page', 'offset');
}

/**
 * Vypočítá stránkování pro už hotový počet výsledků bez SQL COUNT.
 *
 * @return array{perPage: int, total: int, totalPages: int, page: int, offset: int}
 */
function paginateArray(int $total, int $perPage = 10, ?int $requestedPage = null): array
{
    $total = max(0, $total);
    $perPage = max(1, $perPage);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($totalPages, $requestedPage ?? (int)($_GET['strana'] ?? 1)));
    $offset = ($page - 1) * $perPage;

    return compact('perPage', 'total', 'totalPages', 'page', 'offset');
}

/**
 * Vykreslí stránkovací navigaci (<nav> s <ul class="pager">).
 * Vrátí prázdný řetězec, pokud je jen 1 strana.
 */
function renderPager(int $page, int $totalPages, string $baseUrl, string $ariaLabel, string $prevLabel = 'Předchozí', string $nextLabel = 'Další'): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $out = '<nav aria-label="' . h($ariaLabel) . '">' . "\n" . '  <ul class="pager">' . "\n";

    if ($page > 1) {
        $out .= '    <li><a href="' . h($baseUrl) . 'strana=' . ($page - 1) . '" rel="prev"><span aria-hidden="true">&larr;</span> ' . h($prevLabel) . '</a></li>' . "\n";
    }

    for ($p = 1; $p <= $totalPages; $p++) {
        if ($p === $page) {
            $out .= '    <li><span aria-current="page">' . $p . '</span></li>' . "\n";
        } else {
            $out .= '    <li><a href="' . h($baseUrl) . 'strana=' . $p . '">' . $p . '</a></li>' . "\n";
        }
    }

    if ($page < $totalPages) {
        $out .= '    <li><a href="' . h($baseUrl) . 'strana=' . ($page + 1) . '" rel="next">' . h($nextLabel) . ' <span aria-hidden="true">&rarr;</span></a></li>' . "\n";
    }

    $out .= '  </ul>' . "\n" . '</nav>';

    return $out;
}
