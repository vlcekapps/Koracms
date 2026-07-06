<?php
/**
 * Záloha databáze – export všech tabulek jako SQL ke stažení.
 * Generuje INSERT příkazy přes PDO (bez závislosti na mysqldump).
 */
require_once __DIR__ . '/../db.php';
requireCapability('import_export_manage', 'Přístup odepřen.');

function renderDatabaseBackupForm(bool $confirmBackupError = false): void
{
    require_once __DIR__ . '/layout.php';

    $backupErrorFields = $confirmBackupError ? ['confirm_database_backup'] : [];
    $backupConfirmErrorMessage = 'SQL zálohu nejde stáhnout bez potvrzení kontroly dopadu. U pole Potvrzení stažení je konkrétní nápověda.';

    adminHeader('Záloha databáze');
    ?>
    <p class="admin-description">Stáhne kompletní zálohu databáze jako SQL soubor. Záloha obsahuje strukturu i data všech tabulek CMS.</p>

    <?php if ($confirmBackupError): ?>
      <p id="database-backup-form-error" class="error" role="alert" aria-atomic="true"><?= h($backupConfirmErrorMessage) ?></p>
    <?php endif; ?>

    <form method="post" novalidate<?= $confirmBackupError ? ' aria-describedby="database-backup-form-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <fieldset class="admin-fieldset-card">
        <legend>Záloha databáze</legend>
        <p id="database-backup-review-help" class="field-help field-help--flush">
          SQL záloha obsahuje kompletní strukturu a data CMS včetně účtů, e-mailů, zpráv, objednávek, odběrů a dalších provozních údajů.
          Stahujte ji jen pro oprávněnou údržbu, bezpečné uložení nebo řízenou obnovu.
        </p>
        <label for="confirm_database_backup" class="admin-checkbox-label">
          <input type="checkbox" id="confirm_database_backup" name="confirm_database_backup" value="1" required aria-required="true"<?= adminFieldAttributes('confirm_database_backup', $backupErrorFields, [], ['database-backup-review-help'], 'confirm-database-backup-error') ?>>
          Potvrzuji, že jsem zkontroloval(a) citlivost SQL zálohy a mám oprávnění ji stáhnout.
        </label>
        <?php adminRenderFieldError('confirm_database_backup', $backupErrorFields, [], 'Před stažením SQL zálohy potvrďte, že rozumíte citlivosti exportovaných dat a máte oprávnění zálohu stáhnout.', 'confirm-database-backup-error'); ?>
        <div class="admin-field-row">
          <button type="submit" class="btn" data-confirm="Stáhnout kompletní SQL zálohu databáze? Soubor může obsahovat osobní a provozní údaje.">Stáhnout zálohu</button>
        </div>
      </fieldset>
    </form>

    <p><a href="index.php"><span aria-hidden="true">←</span> Zpět do administrace</a></p>
    <?php
    adminFooter();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    renderDatabaseBackupForm();
    exit;
}

verifyCsrf();
$confirmDatabaseBackup = isset($_POST['confirm_database_backup'])
    && (string)$_POST['confirm_database_backup'] === '1';
if (!$confirmDatabaseBackup) {
    renderDatabaseBackupForm(true);
    exit;
}
set_time_limit(300);

$pdo = db_connect();
$dbName = $GLOBALS['database'] ?? 'database';
$filename = 'kora_backup_' . date('Y-m-d_His') . '.sql';

sendAdminAttachmentHeaders('application/sql; charset=utf-8', $filename);

echo "-- Kora CMS database backup\n";
echo "-- Generated: " . date('Y-m-d H:i:s') . "\n";
echo "-- Database: " . $dbName . "\n\n";
echo "SET NAMES utf8mb4;\n";
echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

$tables = koraBackupTableNames($pdo);

foreach ($tables as $table) {
    koraSqlWriteTableDump($pdo, $table, static function (string $sql): void {
        echo $sql;
    });
}

echo "SET FOREIGN_KEY_CHECKS = 1;\n";

logAction('database_backup');
exit;
