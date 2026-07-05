<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu kontaktních zpráv nemáte potřebné oprávnění.');
requireModuleEnabled('contact');

$pdo = db_connect();
$messageId = inputInt('get', 'id');
$redirect = internalRedirectTarget(trim($_GET['redirect'] ?? ''), BASE_URL . '/admin/contact.php');

if ($messageId === null) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT c.id, c.sender_name, c.sender_email, c.topic_id, c.topic_label, c.reference_code,
            c.subject, c.message, c.status, c.replied_at, c.replied_by_user_id,
            c.reply_subject, c.reply_body, c.created_at, c.updated_at,
            ru.email AS replied_by_email, ru.first_name AS replied_by_first_name, ru.last_name AS replied_by_last_name, ru.nickname AS replied_by_nickname
     FROM cms_contact c
     LEFT JOIN cms_users ru ON ru.id = c.replied_by_user_id
     WHERE c.id = ?"
);
$stmt->execute([$messageId]);
$message = $stmt->fetch();

if (!$message) {
    header('Location: ' . $redirect);
    exit;
}

$messageStatus = normalizeMessageStatus((string)($message['status'] ?? 'new'));
if ($messageStatus === 'new') {
    setContactMessageStatus($pdo, $messageId, 'read');
    $message['status'] = 'read';
    $messageStatus = 'read';
    $message['updated_at'] = date('Y-m-d H:i:s');
}

$selfRedirect = BASE_URL . '/admin/contact_message.php?id=' . (int)$message['id'] . '&redirect=' . rawurlencode($redirect);
$repliedByLabel = trim((string)($message['replied_by_email'] ?? '')) !== ''
    ? formSubmissionAssigneeDisplayName([
        'email' => (string)($message['replied_by_email'] ?? ''),
        'first_name' => (string)($message['replied_by_first_name'] ?? ''),
        'last_name' => (string)($message['replied_by_last_name'] ?? ''),
        'nickname' => (string)($message['replied_by_nickname'] ?? ''),
    ])
    : '–';
$replyFieldErrors = isset($_GET['reply']) && $_GET['reply'] === 'invalid' ? ['reply_subject', 'reply_message'] : [];
$replyFieldErrorMessages = [
    'reply_subject' => 'Zadejte předmět odpovědi, aby příjemce poznal, k jaké zprávě se vracíte.',
    'reply_message' => 'Doplňte text odpovědi. Nestačí prázdná zpráva.',
];

adminHeader('Kontaktní zpráva');
?>

<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Kontaktní zpráva byla aktualizována.</p>
<?php endif; ?>
<?php if (isset($_GET['reply']) && $_GET['reply'] === 'sent'): ?>
  <p class="success" role="status">Odpověď odesílateli byla úspěšně odeslána.</p>
<?php elseif (isset($_GET['reply']) && $_GET['reply'] === 'missing'): ?>
  <p class="error" role="alert">U této zprávy není dostupná žádná platná e-mailová adresa pro odpověď.</p>
<?php elseif (isset($_GET['reply']) && $_GET['reply'] === 'invalid'): ?>
  <p class="error" role="alert" aria-atomic="true">Před odesláním odpovědi vyplňte předmět i text. U obou polí je doplněná konkrétní nápověda.</p>
<?php elseif (isset($_GET['reply']) && $_GET['reply'] === 'failed'): ?>
  <p class="error" role="alert">Odpověď se nepodařilo odeslat. Zkuste to prosím znovu později.</p>
<?php endif; ?>

<p><a href="<?= h($redirect) ?>">&larr; Zpět na přehled kontaktních zpráv</a></p>

<table>
  <caption class="sr-only">Detail kontaktní zprávy</caption>
  <tbody>
    <tr>
      <th scope="row">Referenční kód</th>
      <td><?= trim((string)($message['reference_code'] ?? '')) !== '' ? h((string)$message['reference_code']) : '–' ?></td>
    </tr>
    <tr>
      <th scope="row">Jméno</th>
      <td><?= trim((string)($message['sender_name'] ?? '')) !== '' ? h((string)$message['sender_name']) : '–' ?></td>
    </tr>
    <tr>
      <th scope="row">E-mail odesílatele</th>
      <td><a href="mailto:<?= h((string)$message['sender_email']) ?>"><?= h((string)$message['sender_email']) ?></a></td>
    </tr>
    <tr>
      <th scope="row">Téma</th>
      <td><?= trim((string)($message['topic_label'] ?? '')) !== '' ? h((string)$message['topic_label']) : 'Bez tématu' ?></td>
    </tr>
    <tr>
      <th scope="row">Předmět</th>
      <td><?= h((string)$message['subject']) ?></td>
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
      <th scope="row">Poslední odpověď</th>
      <td>
        <?php if (!empty($message['replied_at'])): ?>
          <time datetime="<?= h(str_replace(' ', 'T', (string)$message['replied_at'])) ?>">
            <?= formatCzechDate((string)$message['replied_at']) ?>
          </time>
          <br><small><?= h(trim((string)($message['reply_subject'] ?? '')) !== '' ? (string)$message['reply_subject'] : 'Bez předmětu') ?></small>
          <br><small><?= h($repliedByLabel) ?></small>
        <?php else: ?>
          –
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <th scope="row">Zpráva</th>
      <td class="table-cell--prewrap"><?= h((string)$message['message']) ?></td>
    </tr>
    <?php if (trim((string)($message['reply_body'] ?? '')) !== ''): ?>
      <tr>
        <th scope="row">Text poslední odpovědi</th>
        <td class="table-cell--prewrap"><?= h((string)$message['reply_body']) ?></td>
      </tr>
    <?php endif; ?>
  </tbody>
</table>

<h2>Co můžete udělat</h2>
<div class="button-row">
  <?php if ($messageStatus !== 'new'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="new">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Označit jako nové</button>
    </form>
  <?php endif; ?>
  <?php if ($messageStatus !== 'read'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="read">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Označit jako přečtené</button>
    </form>
  <?php endif; ?>
  <?php if ($messageStatus !== 'handled'): ?>
    <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php">
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
      <input type="hidden" name="action" value="handled">
      <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
      <button type="submit" class="btn">Označit jako vyřízené</button>
    </form>
  <?php endif; ?>
  <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php"
        data-confirm="Smazat tuto kontaktní zprávu trvale?">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
    <button type="submit" class="btn btn-danger">Smazat</button>
  </form>
</div>

<h2>Odpověď odesílateli</h2>
<?php if (!filter_var((string)$message['sender_email'], FILTER_VALIDATE_EMAIL)): ?>
  <p>Tato zpráva neobsahuje platnou e-mailovou adresu, takže na ni nejde odpovědět přímo z administrace.</p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/contact_reply.php">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
    <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
    <fieldset>
      <legend>Odeslat odpověď e-mailem</legend>

      <label for="reply-to">Komu</label>
      <input type="email" id="reply-to" value="<?= h((string)$message['sender_email']) ?>" disabled>

      <label for="reply-subject">Předmět</label>
      <input type="text" id="reply-subject" name="subject" maxlength="255"
             value="<?= h(trim((string)($message['reply_subject'] ?? '')) !== '' ? (string)$message['reply_subject'] : 'Re: ' . (string)$message['subject']) ?>"<?= adminFieldAttributes('reply_subject', $replyFieldErrors, [], [], 'reply-subject-error') ?>>
      <?php adminRenderFieldError('reply_subject', $replyFieldErrors, [], $replyFieldErrorMessages['reply_subject'], 'reply-subject-error'); ?>

      <label for="reply-message">Text odpovědi</label>
      <textarea id="reply-message" name="message" rows="7"<?= adminFieldAttributes('reply_message', $replyFieldErrors, [], [], 'reply-message-error') ?>><?= h(trim((string)($message['reply_body'] ?? '')) !== '' ? (string)$message['reply_body'] : "Dobrý den,\n\nděkujeme za vaši zprávu.\n\n") ?></textarea>
      <?php adminRenderFieldError('reply_message', $replyFieldErrors, [], $replyFieldErrorMessages['reply_message'], 'reply-message-error'); ?>

      <button type="submit" class="btn">Odeslat odpověď</button>
    </fieldset>
  </form>
<?php endif; ?>

<?php adminFooter(); ?>
