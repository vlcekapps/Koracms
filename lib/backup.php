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

function koraSqlCreateTableStatement(PDO $pdo, string $tableName): string
{
    $quotedTableName = koraSqlQuoteIdentifier($tableName);
    $createTableStmt = $pdo->query("SHOW CREATE TABLE {$quotedTableName}");
    if (!$createTableStmt instanceof PDOStatement) {
        throw new RuntimeException('Nepodařilo se načíst definici tabulky pro zálohu.');
    }

    $createTable = $createTableStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($createTable) || !isset($createTable['Create Table']) || !is_string($createTable['Create Table'])) {
        throw new RuntimeException('Nepodařilo se načíst definici tabulky pro zálohu.');
    }

    return $createTable['Create Table'];
}

function koraSqlQuoteValue(PDO $pdo, mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    $quotedValue = $pdo->quote((string)$value);
    if (!is_string($quotedValue)) {
        throw new RuntimeException('Nepodařilo se bezpečně escapovat hodnotu pro zálohu.');
    }

    return $quotedValue;
}

/**
 * @param callable(string): void $write
 */
function koraSqlWriteTableDump(PDO $pdo, string $tableName, callable $write): void
{
    $quotedTableName = koraSqlQuoteIdentifier($tableName);

    $write("DROP TABLE IF EXISTS {$quotedTableName};\n");
    $write(koraSqlCreateTableStatement($pdo, $tableName) . ";\n\n");

    $rows = $pdo->query("SELECT * FROM {$quotedTableName}");
    if (!$rows instanceof PDOStatement) {
        throw new RuntimeException('Nepodařilo se načíst data tabulky pro zálohu.');
    }
    $rows->setFetchMode(PDO::FETCH_ASSOC);

    $firstRow = true;
    $columnNames = [];
    $quotedColumnNames = '';

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        /** @var array<string, mixed> $row */
        if ($firstRow) {
            $columnNames = array_keys($row);
            $quotedColumnNames = koraSqlQuoteIdentifierList($columnNames);
            $firstRow = false;
        }

        $values = [];
        foreach ($columnNames as $columnName) {
            $values[] = koraSqlQuoteValue($pdo, $row[$columnName] ?? null);
        }

        $write("INSERT INTO {$quotedTableName} ({$quotedColumnNames}) VALUES (" . implode(', ', $values) . ");\n");
    }

    if (!$firstRow) {
        $write("\n");
    }
}
