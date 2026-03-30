<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');

$pdo = db_connect();
$statusDefinitions = messageStatusDefinitions();
$validStatusFilters = array_merge(array_keys($statusDefinitions), ['all']);
$statusFilter = trim((string)($_GET['status'] ?? 'new'));
if (!in_array($statusFilter, $validStatusFilters, true)) {
    $statusFilter = 'new';
}

$visibilityDefinitions = chatPublicVisibilityDefinitions();
$validVisibilityFilters = array_merge(array_keys($visibilityDefinitions), ['all']);
$visibilityFilter = trim((string)($_GET['visibility'] ?? 'pending'));
if (!in_array($visibilityFilter, $validVisibilityFilters, true)) {
    $visibilityFilter = 'pending';
}

$queryText = trim((string)($_GET['q'] ?? ''));
$perPage = chatAdminMessagesPerPage();
$statusCounts = inboxStatusCounts($pdo, 'cms_chat');
$visibilityCounts = chatPublicVisibilityCounts($pdo);
$whereSql = 'WHERE 1';
$queryParams = [];

if ($statusFilter !== 'all') {
    $whereSql .= ' AND c.status = ?';
    $queryParams[] = $statusFilter;
}

if ($visibilityFilter !== 'all') {
    $whereSql .= ' AND c.public_visibility = ?';
    $queryParams[] = $visibilityFilter;
}

if ($queryText !== '') {
    $whereSql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.web LIKE ? OR c.message LIKE ?)';
    $queryNeedle = '%' . $queryText . '%';
    $queryParams[] = $queryNeedle;
    $queryParams[] = $queryNeedle;
    $queryParams[] = $queryNeedle;
    $queryParams[] = $queryNeedle;
}

$pagination = paginate(
    $pdo,
    "SELECT COUNT(*) FROM cms_chat c {$whereSql}",
    $queryParams,
    $perPage
);

$messagesStmt = $pdo->prepare(
    "SELECT c.id, c.name, c.email, c.web, c.message, c.status, c.public_visibility, c.created_at, c.updated_at,
            c.approved_at, c.replied_at
     FROM cms_chat c
     {$whereSql}
     ORDER BY FIELD(c.public_visibility, 'pending', 'approved', 'hidden'),
              FIELD(c.status, 'new', 'read', 'handled'),
              c.created_at DESC
     LIMIT ? OFFSET ?"
);
$messagesStmt->execute(array_merge($queryParams, [$pagination['perPage'], $pagination['offset']]));
$messages = $messagesStmt->fetchAll();

$currentParams = [];
if ($statusFilter !== 'all') {
    $currentParams['status'] = $statusFilter;
}
if ($visibilityFilter !== 'all') {
    $currentParams['visibility'] = $visibilityFilter;
}
if ($queryText !== '') {
    $currentParams['q'] = $queryText;
}
if ($pagination['page'] > 1) {
    $currentParams['strana'] = $pagination['page'];
}
$currentRedirect = BASE_URL . '/admin/chat.php' . ($currentParams !== [] ? '?' . http_build_query($currentParams) : '');
$pagerParams = $currentParams;
unset($pagerParams['strana']);
$pagerBaseUrl = BASE_URL . '/admin/chat.php?' . ($pagerParams !== [] ? http_build_query($pagerParams) . '&' : '');
$bulkOptions = [
    'approve' => 'Schválit pro veřejný web',
    'hide' => 'Skrýt z veřejného webu',
    'read' => 'Označit jako přečtené',
    'new' => 'Označit jako nové',
    'handled' => 'Označit jako vyřízené',
    'delete' => 'Smazat trvale',
];
$emptyStateText = match ($visibilityFilter) {
    'pending' => 'Zatím tu nejsou žádné chat zprávy čekající na schválení.',
    'approved' => 'Zatím tu nejsou žádné veřejně schválené chat zprávy.',
    'hidden' => 'Zatím tu nejsou žádné skryté chat zprávy.',
    default => 'Zatím tu nejsou žádné chat zprávy.',
};
if ($statusFilter === 'new') {
    $emptyStateText = 'Zatím tu nejsou žádné nové chat zprávy v tomto filtru.';
}

adminHeader('Chat');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Chat zprávy byly aktualizovány.</p>
<?php endif; ?>

<nav aria-label="Stav chat zpráv" class="button-row" style="margin-bottom:1rem">
  <a href="?status=new&amp;visibility=<?= h($visibilityFilter) ?>" <?= $statusFilter === 'new' ? 'aria-current="page"' : '' ?>>
    Nové (<?= $statusCounts['new'] ?>)
  </a>
  <a href="?status=read&amp;visibility=<?= h($visibilityFilter) ?>" <?= $statusFilter === 'read' ? 'aria-current="page"' : '' ?>>
    Přečtené (<?= $statusCounts['read'] ?>)
  </a>
  <a href="?status=handled&amp;visibility=<?= h($visibilityFilter) ?>" <?= $statusFilter === 'handled' ? 'aria-current="page"' : '' ?>>
    Vyřízené (<?= $statusCounts['handled'] ?>)
  </a>
  <a href="?status=all&amp;visibility=<?= h($visibilityFilter) ?>" <?= $statusFilter === 'all' ? 'aria-current="page"' : '' ?>>
    Všechny (<?= array_sum($statusCounts) ?>)
  </a>
</nav>

<nav aria-label="Veřejná viditelnost chat zpráv" class="button-row" style="margin-bottom:1rem">
  <a href="?status=<?= h($statusFilter) ?>&amp;visibility=pending" <?= $visibilityFilter === 'pending' ? 'aria-current="page"' : '' ?>>
    Ke schválení (<?= $visibilityCounts['pending'] ?>)
  </a>
  <a href="?status=<?= h($statusFilter) ?>&amp;visibility=approved" <?= $visibilityFilter === 'approved' ? 'aria-current="page"' : '' ?>>
    Zveřejněné (<?= $visibilityCounts['approved'] ?>)
  </a>
  <a href="?status=<?= h($statusFilter) ?>&amp;visibility=hidden" <?= $visibilityFilter === 'hidden' ? 'aria-current="page"' : '' ?>>
    Skryté (<?= $visibilityCounts['hidden'] ?>)
  </a>
  <a href="?status=<?= h($statusFilter) ?>&amp;visibility=all" <?= $visibilityFilter === 'all' ? 'aria-current="page"' : '' ?>>
    Vše (<?= array_sum($visibilityCounts) ?>)
  </a>
</nav>

<form method="get" style="margin-bottom:1rem">
  <fieldset class="button-row">
    <legend class="sr-only">Hledat v chat zprávách</legend>
    <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
    <input type="hidden" name="visibility" value="<?= h($visibilityFilter) ?>">
    <label for="q" class="sr-only">Hledat v chat zprávách</label>
    <input type="search" id="q" name="q" placeholder="Hledat v chat zprávách…"
           value="<?= h($queryText) ?>" style="width:min(100%, 24rem)">
    <button type="submit" class="btn">Použít filtr</button>
    <?php if ($queryText !== ''): ?>
      <a href="?status=<?= h($statusFilter) ?>&amp;visibility=<?= h($visibilityFilter) ?>" class="btn">Zrušit filtr</a>
    <?php endif; ?>
  </fieldset>
</form>

<?php if (empty($messages)): ?>
  <p><?= h($emptyStateText) ?></p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/chat_bulk.php" id="chat-bulk-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
    <fieldset style="margin:0 0 .85rem;border:1px solid #d6d6d6;border-radius:10px;padding:.85rem 1rem">
      <legend>Hromadné akce s vybranými zprávami</legend>
      <p data-selection-status="chat" class="field-help" aria-live="polite" style="margin-top:0">Zatím není vybraná žádná zpráva.</p>
      <div class="button-row">
        <?php foreach ($bulkOptions as $bulkAction => $bulkLabel): ?>
          <button type="submit" form="chat-bulk-form" name="action" value="<?= h($bulkAction) ?>"
                  class="btn bulk-action-btn<?= $bulkAction === 'delete' ? ' btn-danger' : '' ?>"
                  disabled
                  <?php if ($bulkAction === 'delete'): ?>data-confirm="Smazat vybrané chat zprávy trvale?"<?php endif; ?>>
            <?= h($bulkLabel) ?>
          </button>
        <?php endforeach; ?>
      </div>
    </fieldset>
  </form>

  <table>
    <caption>Chat zprávy</caption>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="chat-check-all" aria-label="Vybrat všechny chat zprávy" form="chat-bulk-form"></th>
        <th scope="col">Odesílatel</th>
        <th scope="col">Zpráva</th>
        <th scope="col">Přijato</th>
        <th scope="col">Stav</th>
        <th scope="col">Veřejně</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($messages as $message): ?>
        <?php
        $messageStatus = normalizeMessageStatus((string)($message['status'] ?? 'new'));
        $messageVisibility = normalizeChatPublicVisibility((string)($message['public_visibility'] ?? 'pending'));
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
          <td><strong><?= h(messageStatusLabel($messageStatus)) ?></strong></td>
          <td><strong><?= h(chatPublicVisibilityLabel($messageVisibility)) ?></strong></td>
          <td class="actions">
            <a href="<?= h($detailHref) ?>" class="btn">Zobrazit detail</a>
            <?php if ($messageVisibility !== 'approved'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Schválit</button>
              </form>
            <?php endif; ?>
            <?php if ($messageVisibility !== 'hidden'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="hide">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Skrýt</button>
              </form>
            <?php endif; ?>
            <?php if ($messageStatus !== 'read'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="read">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Přečtené</button>
              </form>
            <?php endif; ?>
            <?php if ($messageStatus !== 'handled'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="handled">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Vyřízené</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <?= renderPager(
      (int)$pagination['page'],
      (int)$pagination['totalPages'],
      $pagerBaseUrl,
      'Stránkování inboxu chatu',
      'Novější zprávy',
      'Starší zprávy'
  ) ?>

  <div style="margin-top:.75rem;color:#555" aria-hidden="true">Po výběru zpráv můžete použít hromadné akce nahoře.</div>

  <script nonce="<?= cspNonce() ?>">
  (() => {
      const checkAll = document.getElementById('chat-check-all');
      const checkboxes = Array.from(document.querySelectorAll('input[form="chat-bulk-form"][name="ids[]"]'));
      const actionButtons = Array.from(document.querySelectorAll('#chat-bulk-form .bulk-action-btn'));
      const status = document.querySelector('[data-selection-status="chat"]');

      const updateBulkUi = () => {
          const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
          if (status) {
              status.textContent = selectedCount === 0
                  ? 'Zatím není vybraná žádná zpráva.'
                  : (selectedCount === 1
                      ? 'Vybraná je 1 zpráva.'
                      : 'Vybrané jsou ' + selectedCount + ' zprávy.');
          }
          actionButtons.forEach((button) => {
              button.disabled = selectedCount === 0;
          });
          if (checkAll) {
              checkAll.checked = selectedCount > 0 && selectedCount === checkboxes.length;
              checkAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
          }
      };

      checkAll?.addEventListener('change', function () {
          checkboxes.forEach((checkbox) => {
              checkbox.checked = this.checked;
          });
          updateBulkUi();
      });

      checkboxes.forEach((checkbox) => {
          checkbox.addEventListener('change', updateBulkUi);
      });

      updateBulkUi();
  })();
  </script>
<?php endif; ?>

<?php adminFooter(); ?>
