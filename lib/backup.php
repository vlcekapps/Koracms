<?php
// Sdílené helpery pro SQL zálohy.

function koraSqlIdentifierAllowed(string $identifier): bool
{
    return preg_match('/\A[A-Za-z0-9_]+\z/', $identifier) === 1;
}

function koraSqlQuoteIdentifier(string $identifier): string
{
    if (!koraSqlIdentifierAllowed($identifier)) {
        throw new InvalidArgumentException('Neplatný SQL identifikátor pro zálohu.');
    }

    return '`' . $identifier . '`';
}

/**
 * @param array<int, string> $identifiers
 */
function koraSqlQuoteIdentifierList(array $identifiers): string
{
    return implode(', ', array_map('koraSqlQuoteIdentifier', $identifiers));
}

/**
 * @return list<string>
 */
function koraBackupTableNames(PDO $pdo): array
{
    $tables = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $tableName) {
        $tableName = (string)$tableName;
        if (!str_starts_with($tableName, 'cms_') || !koraSqlIdentifierAllowed($tableName)) {
            continue;
        }

        $tables[] = $tableName;
    }

    return $tables;
}
