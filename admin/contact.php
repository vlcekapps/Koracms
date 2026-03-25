<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu kontaktních zpráv nemáte potřebné oprávnění.');

$pdo = db_connect();
$statusDefinitions = messageStatusDefinitions();
$validFilters = array_merge(array_keys($statusDefinitions), ['all']);
$statusFilter = trim($_GET['status'] ?? 'new');
if (!in_array($statusFilter, $validFilters, true)) {
    $statusFilter = 'new';
}
$q = trim($_GET['q'] ?? '');

$statusCounts = inboxStatusCounts($pdo, 'cms_contact', true);
$where = 'WHERE 1';
$params = [];

if ($statusFilter !== 'all') {
    $where .= ' AND c.status = ?';
    $params[] = $statusFilter;
}

if ($q !== '') {
    $where .= ' AND (c.sender_email LIKE ? OR c.subject LIKE ? OR c.message LIKE ?)';
    $needle = '%' . $q . '%';
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}

try {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.sender_email, c.subject, c.message, c.status, c.created_at, c.updated_at
         FROM cms_contact c
         {$where}
         ORDER BY FIELD(c.status, 'new', 'read', 'handled'), c.created_at DESC"
    );
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
} catch (\PDOException $e) {
    $fallbackWhere = $statusFilter === 'handled'
        ? str_replace('c.status = ?', '0 = 1', $where)
        : str_replace('c.status = ?', 'c.is_read = ?', $where);
    $fallbackParams = $params;
    if ($statusFilter !== 'all' && $statusFilter !== 'handled') {
        $fallbackParams[0] = $statusFilter === 'new' ? 0 : 1;
    }
    $stmt = $pdo->prepare(
        "SELECT c.id, c.sender_email, c.subject, c.message,
                CASE WHEN c.is_read = 1 THEN 'read' ELSE 'new' END AS status,
                c.created_at, c.created_at AS updated_at
         FROM cms_contact c
         {$fallbackWhere}
         ORDER BY c.is_read ASC, c.created_at DESC"
    );
    $stmt->execute($fallbackParams);
    $messages = $stmt->fetchAll();
}

$currentParams = [];
if ($statusFilter !== 'all') {
    $currentParams['status'] = $statusFilter;
}
if ($q !== '') {
    $currentParams['q'] = $q;
}
$currentRedirect = BASE_URL . '/admin/contact.php' . ($currentParams !== [] ? '?' . http_build_query($currentParams) : '');
$bulkOptions = [
    'read' => 'Označit jako přečtené',
    'new' => 'Označit jako nové',
    'handled' => 'Označit jako vyřízené',
    'delete' => 'Smazat trvale',
];

adminHeader('Kontakt');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Kontaktní zprávy byly aktualizovány.</p>
<?php endif; ?>

<nav aria-label="Filtr kontaktních zpráv" class="button-row" style="margin-bottom:1rem">
  <a href="?status=new" <?= $statusFilter === 'new' ? 'aria-current="page"' : '' ?>>
    Nové (<?= $statusCounts['new'] ?>)
  </a>
  <a href="?status=read" <?= $statusFilter === 'read' ? 'aria-current="page"' : '' ?>>
    Přečtené (<?= $statusCounts['read'] ?>)
  </a>
  <a href="?status=handled" <?= $statusFilter === 'handled' ? 'aria-current="page"' : '' ?>>
    Vyřízené (<?= $statusCounts['handled'] ?>)
  </a>
  <a href="?status=all" <?= $statusFilter === 'all' ? 'aria-current="page"' : '' ?>>
    Všechny (<?= array_sum($statusCounts) ?>)
  </a>
</nav>

<form method="get" class="button-row" style="margin-bottom:1rem">
  <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
  <label for="q" class="sr-only">Hledat v kontaktních zprávách</label>
  <input type="search" id="q" name="q" placeholder="Hledat v kontaktních zprávách…"
         value="<?= h($q) ?>" style="width:min(100%, 24rem)">
  <button type="submit" class="btn">Hledat</button>
  <?php if ($q !== ''): ?>
    <a href="?status=<?= h($statusFilter) ?>" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($messages)): ?>
  <p>Zatím tu nejsou žádné kontaktní zprávy.</p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/contact_bulk.php" id="contact-bulk-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
  </form>

  <div class="button-row" style="margin-bottom:.75rem">
    <?php foreach ($bulkOptions as $bulkAction => $bulkLabel): ?>
      <?php if (($bulkAction === 'read' && $statusFilter === 'read')
          || ($bulkAction === 'new' && $statusFilter === 'new')
          || ($bulkAction === 'handled' && $statusFilter === 'handled')): ?>
        <?php continue; ?>
      <?php endif; ?>
      <button type="submit" form="contact-bulk-form" name="action" value="<?= h($bulkAction) ?>"
              class="btn<?= $bulkAction === 'delete' ? ' btn-danger' : '' ?>"
              <?php if ($bulkAction === 'delete'): ?>onclick="return confirm('Smazat vybrané kontaktní zprávy trvale?')"<?php endif; ?>>
        <?= h($bulkLabel) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <table>
    <caption>Kontaktní zprávy</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="contact-check-all" aria-label="Vybrat všechny kontaktní zprávy" form="contact-bulk-form"></th>
        <th scope="col">Od</th>
        <th scope="col">Předmět a zpráva</th>
        <th scope="col">Přijato</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($messages as $message): ?>
        <?php
        $messageStatus = normalizeMessageStatus((string)($message['status'] ?? 'new'));
        $detailHref = 'contact_message.php?id=' . (int)$message['id'] . '&redirect=' . rawurlencode($currentRedirect);
        $messagePreview = mb_strimwidth(
            preg_replace('/\s+/u', ' ', trim((string)$message['message'])),
            0,
            140,
            '…',
            'UTF-8'
        );
        ?>
        <tr>
          <td>
            <input type="checkbox" name="ids[]" value="<?= (int)$message['id'] ?>"
                   aria-label="Vybrat zprávu od <?= h((string)$message['sender_email']) ?>" form="contact-bulk-form">
          </td>
          <td>
            <a href="mailto:<?= h((string)$message['sender_email']) ?>"><?= h((string)$message['sender_email']) ?></a>
          </td>
          <td>
            <strong><?= h((string)$message['subject']) ?></strong>
            <br><small style="color:#555"><?= h($messagePreview) ?></small>
          </td>
          <td>
            <time datetime="<?= h(str_replace(' ', 'T', (string)$message['created_at'])) ?>">
              <?= formatCzechDate((string)$message['created_at']) ?>
            </time>
          </td>
          <td>
            <strong<?= $messageStatus === 'new' ? ' style="color:#9a3412"' : '' ?>>
              <?= h(messageStatusLabel($messageStatus)) ?>
            </strong>
          </td>
          <td class="actions">
            <a href="<?= h($detailHref) ?>" class="btn">Detail</a>
            <?php if ($messageStatus !== 'read'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="read">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Přečteno</button>
              </form>
            <?php endif; ?>
            <?php if ($messageStatus !== 'handled'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="handled">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Vyřízeno</button>
              </form>
            <?php endif; ?>
            <?php if ($messageStatus !== 'new'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="new">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Označit jako nové</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php" style="display:inline"
                  onsubmit="return confirm('Smazat tuto kontaktní zprávu trvale?')">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <script>
  document.getElementById('contact-check-all')?.addEventListener('change', function () {
      document.querySelectorAll('input[form="contact-bulk-form"][name="ids[]"]').forEach((checkbox) => {
          checkbox.checked = this.checked;
      });
  });
  </script>
<?php endif; ?>

<?php adminFooter(); ?>
