<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');

$pdo = db_connect();
$statusDefinitions = messageStatusDefinitions();
$validFilters = array_merge(array_keys($statusDefinitions), ['all']);
$statusFilter = trim($_GET['status'] ?? 'new');
if (!in_array($statusFilter, $validFilters, true)) {
    $statusFilter = 'new';
}
$q = trim($_GET['q'] ?? '');

$statusCounts = inboxStatusCounts($pdo, 'cms_chat');
$where = 'WHERE 1';
$params = [];

if ($statusFilter !== 'all') {
    $where .= ' AND c.status = ?';
    $params[] = $statusFilter;
}

if ($q !== '') {
    $where .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.web LIKE ? OR c.message LIKE ?)';
    $needle = '%' . $q . '%';
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
    $params[] = $needle;
}

try {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.email, c.web, c.message, c.status, c.created_at, c.updated_at
         FROM cms_chat c
         {$where}
         ORDER BY FIELD(c.status, 'new', 'read', 'handled'), c.created_at DESC"
    );
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
} catch (\PDOException $e) {
    $stmt = $pdo->prepare(
        "SELECT c.id, c.name, c.email, c.web, c.message,
                'read' AS status, c.created_at, c.created_at AS updated_at
         FROM cms_chat c
         " . ($q !== ''
            ? "WHERE (c.name LIKE ? OR c.email LIKE ? OR c.web LIKE ? OR c.message LIKE ?)"
            : '') . "
         ORDER BY c.created_at DESC"
    );
    $fallbackParams = [];
    if ($q !== '') {
        $fallbackNeedle = '%' . $q . '%';
        $fallbackParams = [$fallbackNeedle, $fallbackNeedle, $fallbackNeedle, $fallbackNeedle];
    }
    $stmt->execute($fallbackParams);
    $messages = $stmt->fetchAll();
    if ($statusFilter !== 'all') {
        $messages = array_values(array_filter(
            $messages,
            static fn(array $message): bool => normalizeMessageStatus((string)($message['status'] ?? 'read')) === $statusFilter
        ));
    }
}

$currentParams = [];
if ($statusFilter !== 'all') {
    $currentParams['status'] = $statusFilter;
}
if ($q !== '') {
    $currentParams['q'] = $q;
}
$currentRedirect = BASE_URL . '/admin/chat.php' . ($currentParams !== [] ? '?' . http_build_query($currentParams) : '');
$bulkOptions = [
    'read' => 'Označit jako přečtené',
    'new' => 'Označit jako nové',
    'handled' => 'Označit jako vyřízené',
    'delete' => 'Smazat trvale',
];
$emptyStateText = match ($statusFilter) {
    'new' => 'Zatím tu nejsou žádné nové chat zprávy.',
    'read' => 'Zatím tu nejsou žádné přečtené chat zprávy.',
    'handled' => 'Zatím tu nejsou žádné vyřízené chat zprávy.',
    default => 'Zatím tu nejsou žádné chat zprávy.',
};

adminHeader('Chat');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Chat zprávy byly aktualizovány.</p>
<?php endif; ?>

<nav aria-label="Filtr chat zpráv" class="button-row" style="margin-bottom:1rem">
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
  <label for="q" class="sr-only">Hledat v chat zprávách</label>
  <input type="search" id="q" name="q" placeholder="Hledat v chat zprávách…"
         value="<?= h($q) ?>" style="width:min(100%, 24rem)">
  <button type="submit" class="btn">Hledat</button>
  <?php if ($q !== ''): ?>
    <a href="?status=<?= h($statusFilter) ?>" class="btn">Zrušit</a>
  <?php endif; ?>
</form>

<?php if (empty($messages)): ?>
  <p><?= h($emptyStateText) ?></p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/chat_bulk.php" id="chat-bulk-form">
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
      <button type="submit" form="chat-bulk-form" name="action" value="<?= h($bulkAction) ?>"
              class="btn<?= $bulkAction === 'delete' ? ' btn-danger' : '' ?>"
              <?php if ($bulkAction === 'delete'): ?>onclick="return confirm('Smazat vybrané chat zprávy trvale?')"<?php endif; ?>>
        <?= h($bulkLabel) ?>
      </button>
    <?php endforeach; ?>
  </div>

  <table>
    <caption>Chat zprávy</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="chat-check-all" aria-label="Vybrat všechny chat zprávy" form="chat-bulk-form"></th>
        <th scope="col">Odesílatel</th>
        <th scope="col">Zpráva</th>
        <th scope="col">Přijato</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($messages as $message): ?>
        <?php
        $messageStatus = normalizeMessageStatus((string)($message['status'] ?? 'new'));
        $detailHref = 'chat_message.php?id=' . (int)$message['id'] . '&redirect=' . rawurlencode($currentRedirect);
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
                   aria-label="Vybrat chat zprávu od <?= h((string)$message['name']) ?>" form="chat-bulk-form">
          </td>
          <td>
            <strong><?= h((string)$message['name']) ?></strong>
            <?php if ((string)$message['email'] !== ''): ?>
              <br><a href="mailto:<?= h((string)$message['email']) ?>"><?= h((string)$message['email']) ?></a>
            <?php endif; ?>
            <?php if ((string)$message['web'] !== ''): ?>
              <br><a href="<?= h((string)$message['web']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= h((string)$message['web']) ?></a>
            <?php endif; ?>
          </td>
          <td><?= h($messagePreview) ?></td>
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
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="read">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Označit jako přečtené</button>
              </form>
            <?php endif; ?>
            <?php if ($messageStatus !== 'handled'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="handled">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Označit jako vyřízené</button>
              </form>
            <?php endif; ?>
            <?php if ($messageStatus !== 'new'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="new">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Označit jako nové</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" style="display:inline"
                  onsubmit="return confirm('Smazat tuto chat zprávu trvale?')">
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
  document.getElementById('chat-check-all')?.addEventListener('change', function () {
      document.querySelectorAll('input[form="chat-bulk-form"][name="ids[]"]').forEach((checkbox) => {
          checkbox.checked = this.checked;
      });
  });
  </script>
<?php endif; ?>

<?php adminFooter(); ?>
