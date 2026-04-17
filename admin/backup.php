<?php
/**
 * Záloha databáze – export všech tabulek jako SQL ke stažení.
 * Generuje INSERT příkazy přes PDO (bez závislosti na mysqldump).
 */
require_once __DIR__ . '/../db.php';
requireCapability('import_export_manage', 'Přístup odepřen.');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    require_once __DIR__ . '/layout.php';
    adminHeader('Záloha databáze');
    ?>
    <p style="font-size:.9rem">Stáhne kompletní zálohu databáze jako SQL soubor. Záloha obsahuje strukturu i data všech tabulek CMS.</p>

    <form method="post" novalidate>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <fieldset>
        <legend>Záloha databáze</legend>
        <p>Kliknutím vytvoříte a stáhnete SQL zálohu celé databáze.</p>
        <button type="submit" class="btn">Stáhnout zálohu</button>
      </fieldset>
    </form>

    <p><a href="index.php"><span aria-hidden="true">←</span> Zpět do administrace</a></p>
    <?php
    adminFooter();
    exit;
}

verifyCsrf();
set_time_limit(300);

$pdo = db_connect();
$dbName = $GLOBALS['database'] ?? 'database';
$filename = 'kora_backup_' . date('Y-m-d_His') . '.sql';

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

echo "-- Kora CMS database backup\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Database: " . $dbName . "\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$tables = koraBackupTableNames($pdo);

foreach ($tables as $table) {
    $quotedTable = koraSqlQuoteIdentifier($table);

    // CREATE TABLE
    $create = $pdo->query("SHOW CREATE TABLE {$quotedTable}")->fetch();
    echo "DROP TABLE IF EXISTS {$quotedTable};\n";
    echo $create['Create Table'] . ";\n\n";

    // Data
    $rows = $pdo->query("SELECT * FROM {$quotedTable}");
    $first = true;
    $columns = null;
    $quotedColumns = '';

    foreach ($rows as $row) {
        if ($first) {
            $columns = array_keys($row);
            $quotedColumns = koraSqlQuoteIdentifierList($columns);
            $first = false;
        }

        $values = [];
        foreach ($columns as $col) {
            if ($row[$col] === null) {
                $values[] = 'NULL';
            } else {
                $values[] = $pdo->quote($row[$col]);
            }
        }
        echo "INSERT INTO {$quotedTable} ({$quotedColumns}) VALUES (" . implode(', ', $values) . ");\n";
    }

    if (!$first) {
        echo "\n";
    }
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";

logAction('database_backup');
exit;
