<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu chat zpráv nemáte potřebné oprávnění.');

$pdo = db_connect();
$messageId = inputInt('get', 'id');
$redirect = internalRedirectTarget(trim($_GET['redirect'] ?? ''), BASE_URL . '/admin/chat.php');

if ($messageId === null) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, name, email, web, message, status, created_at, updated_at
     FROM cms_chat
     WHERE id = ?"
);
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    header('Location: ' . $redirect);
    exit;
}

$messageStatus = normalizeMessageStatus((string)($message['status'] ?? 'new'));
if ($messageStatus === 'new') {
    setChatMessageStatus($pdo, $messageId, 'read');
    $message['status'] = 'read';
    $messageStatus = 'read';
    $message['updated_at'] = date('Y-m-d H:i:s');
}

$selfRedirect = BASE_URL . '/admin/chat_message.php?id=' . (int)$message['id'] . '&redirect=' . rawurlencode($redirect);

adminHeader('Chat zpráva');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Chat zpráva byla aktualizována.</p>
<?php endif; ?>

<p><a href="<?= h($redirect) ?>">&larr; Zpět na chat zprávy</a></p>

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
      <td><strong><?= h(messageStatusLabel($messageStatus)) ?></strong></td>
    </tr>
    <tr>
      <th scope="row">Přijato</th>
      <td>
        <time datetime="<?= h(str_replace(' ', 'T', (string)$message['created_at'])) ?>">
          <?= formatCzechDate((string)$message['created_at']) ?>
        </time>
      </td>
    </tr>
    <tr>
      <th scope="row">Naposledy změněno</th>
      <td>
        <time datetime="<?= h(str_replace(' ', 'T', (string)$message['updated_at'])) ?>">
          <?= formatCzechDate((string)$message['updated_at']) ?>
        </time>
      </td>
    </tr>
    <tr>
      <th scope="row">Zpráva</th>
      <td style="white-space:pre-wrap"><?= h((string)$message['message']) ?></td>
    </tr>
  </tbody>
</table>

<h2>Akce</h2>
<div class="button-row">
  <?php if ($messageStatus !== 'new'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="new">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Vrátit jako nové</button>
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

<?php adminFooter(); ?>
