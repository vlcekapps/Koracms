<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');
requireModuleEnabled('chat');

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
$topics = chatTopics($pdo, false);
$topicFilter = trim((string)($_GET['topic_id'] ?? 'all'));
$topicFilterId = $topicFilter !== 'all' ? (int)$topicFilter : null;
$conversationTypes = chatConversationTypeDefinitions();
$conversationTypeFilter = normalizeChatConversationType((string)($_GET['type'] ?? 'public'));
if ((string)($_GET['type'] ?? 'public') === 'all') {
    $conversationTypeFilter = 'all';
}
$pinFilter = trim((string)($_GET['pin'] ?? 'all'));
if (!in_array($pinFilter, ['all', 'pinned'], true)) {
    $pinFilter = 'all';
}
$replyFilter = trim((string)($_GET['replies'] ?? 'all'));
if (!in_array($replyFilter, ['all', 'pending'], true)) {
    $replyFilter = 'all';
}
$perPage = chatAdminMessagesPerPage();
$statusCounts = inboxStatusCounts($pdo, 'cms_chat');
$visibilityCounts = chatPublicVisibilityCounts($pdo);
$replyStatusCounts = chatReplyStatusCounts($pdo);
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

if ($conversationTypeFilter !== 'all') {
    $whereSql .= ' AND c.conversation_type = ?';
    $queryParams[] = $conversationTypeFilter;
}

if ($topicFilterId !== null && $topicFilterId > 0) {
    $whereSql .= ' AND c.topic_id = ?';
    $queryParams[] = $topicFilterId;
}

if ($pinFilter === 'pinned') {
    $whereSql .= ' AND c.is_pinned = 1 AND (c.pinned_until IS NULL OR c.pinned_until >= NOW())';
}

if ($replyFilter === 'pending') {
    $whereSql .= " AND EXISTS (SELECT 1 FROM cms_chat_replies cr WHERE cr.chat_id = c.id AND cr.status = 'pending')";
}

if ($queryText !== '') {
    $whereSql .= ' AND (c.name LIKE ? OR c.email LIKE ? OR c.web LIKE ? OR c.message LIKE ? OR c.reference_code LIKE ? OR c.topic_label LIKE ?)';
    $queryNeedle = '%' . $queryText . '%';
    $queryParams[] = $queryNeedle;
    $queryParams[] = $queryNeedle;
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
            c.approved_at, c.replied_at, c.topic_id, c.topic_label, c.conversation_type, c.reference_code,
            c.is_pinned, c.pinned_until,
            t.name AS topic_name,
            (SELECT COUNT(*) FROM cms_chat_replies cr WHERE cr.chat_id = c.id) AS reply_count,
            (SELECT COUNT(*) FROM cms_chat_replies cr WHERE cr.chat_id = c.id AND cr.status = 'pending') AS pending_reply_count
     FROM cms_chat c
     LEFT JOIN cms_chat_topics t ON t.id = c.topic_id
     {$whereSql}
     ORDER BY (c.is_pinned = 1 AND (c.pinned_until IS NULL OR c.pinned_until >= NOW())) DESC,
              FIELD(c.public_visibility, 'pending', 'approved', 'hidden'),
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
if ($conversationTypeFilter !== 'public') {
    $currentParams['type'] = $conversationTypeFilter;
}
if ($topicFilterId !== null && $topicFilterId > 0) {
    $currentParams['topic_id'] = (string)$topicFilterId;
}
if ($pinFilter !== 'all') {
    $currentParams['pin'] = $pinFilter;
}
if ($replyFilter !== 'all') {
    $currentParams['replies'] = $replyFilter;
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

$messageRows = [];
foreach ($messages as $message) {
    $messageStatus = normalizeMessageStatus((string)($message['status'] ?? 'new'));
    $messageVisibility = normalizeChatPublicVisibility((string)($message['public_visibility'] ?? 'pending'));
    $messageRows[] = $message + [
        'detail_href' => 'chat_message.php?id=' . (int)$message['id'] . '&redirect=' . rawurlencode($currentRedirect),
        'message_preview' => mb_strimwidth(
            preg_replace('/\s+/u', ' ', trim((string)$message['message'])),
            0,
            140,
            '…',
            'UTF-8'
        ),
        'normalized_status' => $messageStatus,
        'normalized_visibility' => $messageVisibility,
        'normalized_type' => normalizeChatConversationType((string)($message['conversation_type'] ?? 'public')),
        'is_currently_pinned' => chatMessageIsPinned($message),
    ];
}

adminHeader('Chat');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Chat zprávy byly aktualizovány.</p>
<?php endif; ?>

<nav aria-labelledby="chat-status-filter-heading" class="button-row admin-stack-sm">
  <h2 id="chat-status-filter-heading" class="sr-only">Stav chat zpráv</h2>
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

<nav aria-labelledby="chat-visibility-filter-heading" class="button-row admin-stack-sm">
  <h2 id="chat-visibility-filter-heading" class="sr-only">Veřejná viditelnost chat zpráv</h2>
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

<form method="get" class="admin-stack-sm">
  <fieldset class="button-row">
    <legend class="sr-only">Hledat v chat zprávách</legend>
    <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
    <input type="hidden" name="visibility" value="<?= h($visibilityFilter) ?>">
    <label for="type" class="sr-only">Typ zprávy</label>
    <select id="type" name="type">
      <option value="all"<?= $conversationTypeFilter === 'all' ? ' selected' : '' ?>>Všechny typy</option>
      <?php foreach ($conversationTypes as $typeKey => $definition): ?>
        <option value="<?= h($typeKey) ?>"<?= $conversationTypeFilter === $typeKey ? ' selected' : '' ?>><?= h((string)$definition['label']) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="topic_id" class="sr-only">Téma chatu</label>
    <select id="topic_id" name="topic_id">
      <option value="all"<?= $topicFilterId === null ? ' selected' : '' ?>>Všechna témata</option>
      <?php foreach ($topics as $topic): ?>
        <option value="<?= (int)$topic['id'] ?>"<?= $topicFilterId === (int)$topic['id'] ? ' selected' : '' ?>><?= h((string)$topic['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="pin" class="sr-only">Připnutí</label>
    <select id="pin" name="pin">
      <option value="all"<?= $pinFilter === 'all' ? ' selected' : '' ?>>Všechny zprávy</option>
      <option value="pinned"<?= $pinFilter === 'pinned' ? ' selected' : '' ?>>Jen připnuté</option>
    </select>
    <label for="replies" class="sr-only">Odpovědi</label>
    <select id="replies" name="replies">
      <option value="all"<?= $replyFilter === 'all' ? ' selected' : '' ?>>Všechny odpovědi</option>
      <option value="pending"<?= $replyFilter === 'pending' ? ' selected' : '' ?>>Odpovědi ke schválení (<?= (int)$replyStatusCounts['pending'] ?>)</option>
    </select>
    <label for="q" class="sr-only">Hledat v chat zprávách</label>
    <input type="search" id="q" name="q" placeholder="Hledat v chat zprávách…"
           value="<?= h($queryText) ?>" class="admin-search-input">
    <button type="submit" class="btn">Použít filtr</button>
    <?php if ($queryText !== '' || $conversationTypeFilter !== 'public' || $topicFilterId !== null || $pinFilter !== 'all' || $replyFilter !== 'all'): ?>
      <a href="?status=<?= h($statusFilter) ?>&amp;visibility=<?= h($visibilityFilter) ?>" class="btn">Zrušit filtr</a>
    <?php endif; ?>
  </fieldset>
</form>

<?php if (empty($messageRows)): ?>
  <p><?= h($emptyStateText) ?></p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/chat_bulk.php" id="chat-bulk-form">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
    <fieldset class="admin-fieldset-card">
      <legend>Hromadné akce s vybranými zprávami</legend>
      <p data-selection-status="chat" class="field-help field-help--flush" aria-live="polite">Zatím není vybraná žádná zpráva.</p>
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
        <th scope="col"><label for="chat-check-all" class="sr-only">Vybrat všechny chat zprávy</label><input type="checkbox" id="chat-check-all" form="chat-bulk-form"></th>
        <th scope="col">Odesílatel</th>
        <th scope="col">Zpráva</th>
        <th scope="col">Typ a téma</th>
        <th scope="col">Přijato</th>
        <th scope="col">Stav</th>
        <th scope="col">Veřejně</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($messageRows as $message): ?>
        <tr>
          <td>
            <label for="chat-message-select-<?= (int)$message['id'] ?>" class="sr-only">Vybrat chat zprávu od <?= h((string)$message['name']) ?></label>
            <input type="checkbox" id="chat-message-select-<?= (int)$message['id'] ?>" name="ids[]" value="<?= (int)$message['id'] ?>" form="chat-bulk-form">
          </td>
          <td>
            <strong><?= h((string)$message['name']) ?></strong>
            <?php if ((string)$message['email'] !== ''): ?>
              <br><a href="mailto:<?= h((string)$message['email']) ?>"><?= h((string)$message['email']) ?></a>
            <?php endif; ?>
            <?php if ((string)$message['web'] !== ''): ?>
              <br><a href="<?= h((string)$message['web']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= h((string)$message['web']) ?><?= newWindowLinkSrOnlySuffix() ?></a>
            <?php endif; ?>
          </td>
          <td>
            <?= h((string)$message['message_preview']) ?>
            <?php if ((int)($message['reply_count'] ?? 0) > 0): ?>
              <br><small><?= (int)$message['reply_count'] ?> odpovědí<?= (int)$message['pending_reply_count'] > 0 ? ', z toho ' . (int)$message['pending_reply_count'] . ' ke schválení' : '' ?></small>
            <?php endif; ?>
            <?php if ($message['is_currently_pinned']): ?>
              <br><small>Připnuto<?= trim((string)($message['pinned_until'] ?? '')) !== '' ? ' do ' . h(formatCzechDate((string)$message['pinned_until'])) : '' ?></small>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= h(chatConversationTypeLabel((string)$message['normalized_type'])) ?></strong>
            <?php if (trim((string)($message['reference_code'] ?? '')) !== ''): ?>
              <br><code><?= h((string)$message['reference_code']) ?></code>
            <?php endif; ?>
            <?php if (trim((string)($message['topic_name'] ?? $message['topic_label'] ?? '')) !== ''): ?>
              <br><small><?= h((string)($message['topic_name'] ?? $message['topic_label'])) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <time datetime="<?= h(str_replace(' ', 'T', (string)$message['created_at'])) ?>">
              <?= formatCzechDate((string)$message['created_at']) ?>
            </time>
          </td>
          <td><strong><?= h(messageStatusLabel((string)$message['normalized_status'])) ?></strong></td>
          <td><strong><?= h(chatPublicVisibilityLabel((string)$message['normalized_visibility'])) ?></strong></td>
          <td class="actions">
            <a href="<?= h((string)$message['detail_href']) ?>" class="btn">Zobrazit detail</a>
            <?php if ($message['normalized_visibility'] !== 'approved'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Schválit</button>
              </form>
            <?php endif; ?>
            <?php if ($message['normalized_visibility'] !== 'hidden'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="hide">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Skrýt</button>
              </form>
            <?php endif; ?>
            <?php if ($message['normalized_status'] !== 'read'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="read">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Přečtené</button>
              </form>
            <?php endif; ?>
            <?php if ($message['normalized_status'] !== 'handled'): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
                <input type="hidden" name="action" value="handled">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <button type="submit" class="btn">Vyřízené</button>
              </form>
            <?php endif; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
              <input type="hidden" name="action" value="<?= $message['is_currently_pinned'] ? 'unpin' : 'pin' ?>">
              <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
              <button type="submit" class="btn"><?= $message['is_currently_pinned'] ? 'Odepnout' : 'Připnout' ?></button>
            </form>
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

  <div class="table-note" aria-hidden="true">Po výběru zpráv můžete použít hromadné akce nahoře.</div>

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
