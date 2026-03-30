<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');

$pdo = db_connect();
$messageId = inputInt('get', 'id');
$redirect = internalRedirectTarget(trim((string)($_GET['redirect'] ?? '')), BASE_URL . '/admin/chat.php');

if ($messageId === null) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT c.id, c.name, c.email, c.web, c.message, c.status, c.public_visibility, c.created_at, c.updated_at,
            c.approved_at, c.approved_by_user_id, c.internal_note, c.replied_at, c.replied_by_user_id,
            c.replied_subject, c.replied_to_email,
            au.email AS approved_by_email, au.first_name AS approved_by_first_name, au.last_name AS approved_by_last_name, au.nickname AS approved_by_nickname,
            ru.email AS replied_by_email, ru.first_name AS replied_by_first_name, ru.last_name AS replied_by_last_name, ru.nickname AS replied_by_nickname
     FROM cms_chat c
     LEFT JOIN cms_users au ON au.id = c.approved_by_user_id
     LEFT JOIN cms_users ru ON ru.id = c.replied_by_user_id
     WHERE c.id = ?"
);
$stmt->execute([$messageId]);
$message = $stmt->fetch() ?: null;

if (!$message) {
    header('Location: ' . $redirect);
    exit;
}

$messageStatus = normalizeMessageStatus((string)($message['status'] ?? 'new'));
if ($messageStatus === 'new') {
    if (setChatMessageStatus($pdo, $messageId, 'read')) {
        chatHistoryCreate($pdo, $messageId, currentUserId(), 'workflow', 'Zpráva byla při otevření detailu označena jako přečtená.');
    }
    $message['status'] = 'read';
    $messageStatus = 'read';
    $message['updated_at'] = date('Y-m-d H:i:s');
}

$visibilityDefinitions = chatPublicVisibilityDefinitions();
$statusDefinitions = messageStatusDefinitions();
$historyEntries = chatHistoryEntries($pdo, $messageId);
$selfRedirect = BASE_URL . '/admin/chat_message.php?id=' . (int)$message['id'] . '&redirect=' . rawurlencode($redirect);
$approvedByLabel = trim((string)($message['approved_by_email'] ?? '')) !== ''
    ? formSubmissionAssigneeDisplayName([
        'email' => (string)($message['approved_by_email'] ?? ''),
        'first_name' => (string)($message['approved_by_first_name'] ?? ''),
        'last_name' => (string)($message['approved_by_last_name'] ?? ''),
        'nickname' => (string)($message['approved_by_nickname'] ?? ''),
    ])
    : '–';
$repliedByLabel = trim((string)($message['replied_by_email'] ?? '')) !== ''
    ? formSubmissionAssigneeDisplayName([
        'email' => (string)($message['replied_by_email'] ?? ''),
        'first_name' => (string)($message['replied_by_first_name'] ?? ''),
        'last_name' => (string)($message['replied_by_last_name'] ?? ''),
        'nickname' => (string)($message['replied_by_nickname'] ?? ''),
    ])
    : '–';

adminHeader('Chat zpráva');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Chat zpráva byla aktualizována.</p>
<?php endif; ?>
<?php if (isset($_GET['reply']) && $_GET['reply'] === 'sent'): ?>
  <p class="success" role="status">Odpověď odesílateli byla úspěšně odeslána.</p>
<?php elseif (isset($_GET['reply']) && $_GET['reply'] === 'missing'): ?>
  <p class="error" role="alert">U této zprávy není dostupná žádná platná e-mailová adresa pro odpověď.</p>
<?php elseif (isset($_GET['reply']) && $_GET['reply'] === 'invalid'): ?>
  <p class="error" role="alert">Vyplňte předmět i text odpovědi.</p>
<?php elseif (isset($_GET['reply']) && $_GET['reply'] === 'failed'): ?>
  <p class="error" role="alert">Odpověď se nepodařilo odeslat. Zkuste to prosím znovu později.</p>
<?php endif; ?>

<div class="button-row">
  <a href="<?= h($redirect) ?>" class="btn">Zpět na přehled chat zpráv</a>
  <a href="<?= BASE_URL ?>/chat/index.php" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit veřejný chat</a>
</div>

<table>
  <caption class="sr-only">Detail chat zprávy</caption>
  <tbody>
    <tr>
      <th scope="row">Jméno</th>
      <td><?= h((string)$message['name']) ?></td>
    </tr>
    <tr>
      <th scope="row">E-mail</th>
      <td>
        <?php if ((string)$message['email'] !== ''): ?>
          <a href="mailto:<?= h((string)$message['email']) ?>"><?= h((string)$message['email']) ?></a>
        <?php else: ?>
          –
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <th scope="row">Web</th>
      <td>
        <?php if ((string)$message['web'] !== ''): ?>
          <a href="<?= h((string)$message['web']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= h((string)$message['web']) ?></a>
        <?php else: ?>
          –
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <th scope="row">Stav</th>
      <td><strong><?= h(messageStatusLabel((string)$message['status'])) ?></strong></td>
    </tr>
    <tr>
      <th scope="row">Veřejně</th>
      <td><strong><?= h(chatPublicVisibilityLabel((string)$message['public_visibility'])) ?></strong></td>
    </tr>
    <tr>
      <th scope="row">Přijato</th>
      <td><time datetime="<?= h(str_replace(' ', 'T', (string)$message['created_at'])) ?>"><?= formatCzechDate((string)$message['created_at']) ?></time></td>
    </tr>
    <tr>
      <th scope="row">Naposledy změněno</th>
      <td><time datetime="<?= h(str_replace(' ', 'T', (string)$message['updated_at'])) ?>"><?= formatCzechDate((string)$message['updated_at']) ?></time></td>
    </tr>
    <tr>
      <th scope="row">Schváleno</th>
      <td>
        <?php if (!empty($message['approved_at'])): ?>
          <time datetime="<?= h(str_replace(' ', 'T', (string)$message['approved_at'])) ?>"><?= formatCzechDate((string)$message['approved_at']) ?></time>
          <br><small><?= h($approvedByLabel) ?></small>
        <?php else: ?>
          –
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <th scope="row">Poslední odpověď</th>
      <td>
        <?php if (!empty($message['replied_at'])): ?>
          <time datetime="<?= h(str_replace(' ', 'T', (string)$message['replied_at'])) ?>"><?= formatCzechDate((string)$message['replied_at']) ?></time>
          <br><small><?= h(trim((string)($message['replied_subject'] ?? '')) !== '' ? (string)$message['replied_subject'] : 'Bez předmětu') ?></small>
          <br><small><?= h(trim((string)($message['replied_to_email'] ?? '')) !== '' ? (string)$message['replied_to_email'] : '–') ?></small>
          <br><small><?= h($repliedByLabel) ?></small>
        <?php else: ?>
          –
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <th scope="row">Interní poznámka</th>
      <td><?= nl2br(h(trim((string)($message['internal_note'] ?? '')) !== '' ? (string)$message['internal_note'] : '–')) ?></td>
    </tr>
    <tr>
      <th scope="row">Zpráva</th>
      <td style="white-space:pre-wrap"><?= h((string)$message['message']) ?></td>
    </tr>
  </tbody>
</table>

<h2>Rychlé kroky</h2>
<div class="button-row">
  <?php if ((string)$message['public_visibility'] !== 'approved'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="approve">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Schválit pro veřejný web</button>
    </form>
  <?php endif; ?>
  <?php if ((string)$message['public_visibility'] !== 'hidden'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="hide">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Skrýt z veřejného webu</button>
    </form>
  <?php endif; ?>
  <?php if ($messageStatus !== 'new'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="new">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Označit jako nové</button>
    </form>
  <?php endif; ?>
  <?php if ($messageStatus !== 'read'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="read">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Označit jako přečtené</button>
    </form>
  <?php endif; ?>
  <?php if ($messageStatus !== 'handled'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="handled">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Označit jako vyřízené</button>
    </form>
  <?php endif; ?>
  <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php"
        onsubmit="return confirm('Smazat tuto chat zprávu trvale?')">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
    <button type="submit" class="btn btn-danger">Smazat</button>
  </form>
</div>

<h2>Workflow zprávy</h2>
<form method="post" action="<?= BASE_URL ?>/admin/chat_update.php">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
  <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
  <fieldset>
    <legend>Nastavení workflow</legend>

    <label for="chat-status">Stav inboxu</label>
    <select id="chat-status" name="status">
      <?php foreach ($statusDefinitions as $statusKey => $definition): ?>
        <option value="<?= h($statusKey) ?>"<?= $statusKey === (string)$message['status'] ? ' selected' : '' ?>>
          <?= h((string)$definition['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="chat-visibility">Veřejná viditelnost</label>
    <select id="chat-visibility" name="public_visibility">
      <?php foreach ($visibilityDefinitions as $visibilityKey => $definition): ?>
        <option value="<?= h($visibilityKey) ?>"<?= $visibilityKey === (string)$message['public_visibility'] ? ' selected' : '' ?>>
          <?= h((string)$definition['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="chat-note">Interní poznámka</label>
    <textarea id="chat-note" name="internal_note" rows="4"><?= h((string)($message['internal_note'] ?? '')) ?></textarea>

    <button type="submit" class="btn">Uložit workflow</button>
  </fieldset>
</form>

<h2>Odpověď odesílateli</h2>
<?php if (trim((string)$message['email']) === ''): ?>
  <p>Tato zpráva neobsahuje e-mailovou adresu, takže na ni nejde odpovědět přímo z administrace.</p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/chat_reply.php">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
    <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
    <fieldset>
      <legend>Odeslat odpověď e-mailem</legend>

      <label for="reply-to">Komu</label>
      <input type="email" id="reply-to" value="<?= h((string)$message['email']) ?>" disabled>

      <label for="reply-subject">Předmět</label>
      <input type="text" id="reply-subject" name="subject"
             value="<?= h(trim((string)($message['replied_subject'] ?? '')) !== '' ? (string)$message['replied_subject'] : 'Re: zpráva z chatu – ' . getSetting('site_name', 'Kora CMS')) ?>">

      <label for="reply-message">Text odpovědi</label>
      <textarea id="reply-message" name="message" rows="6">Dobrý den,&#10;&#10;děkujeme za vaši zprávu v chatu.&#10;&#10;</textarea>

      <button type="submit" class="btn">Odeslat odpověď</button>
    </fieldset>
  </form>
<?php endif; ?>

<h2>Historie zprávy</h2>
<?php if ($historyEntries === []): ?>
  <p>Zatím tu není žádná historie změn.</p>
<?php else: ?>
  <ul>
    <?php foreach ($historyEntries as $historyEntry): ?>
      <li>
        <strong><?= h(chatHistoryActorLabel($historyEntry)) ?></strong>
        <span>· <time datetime="<?= h(str_replace(' ', 'T', (string)$historyEntry['created_at'])) ?>"><?= formatCzechDate((string)$historyEntry['created_at']) ?></time></span>
        <br><?= nl2br(h((string)$historyEntry['message'])) ?>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>

<?php adminFooter(); ?>
