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
$replyStatus = trim((string)($_GET['reply'] ?? ''));
$replyConfirmField = 'confirm_contact_reply_send_' . $messageId;
$replyConfirmId = 'confirm-contact-reply-send-' . $messageId;
$replyReviewId = 'contact-reply-review-' . $messageId;
$replyConfirmErrorId = 'confirm-contact-reply-send-' . $messageId . '-error';
$replyFormErrorId = 'contact-reply-form-error';
$replyFlash = adminReplyFlashPull('contact', $messageId);
$replyHasFlash = in_array($replyStatus, ['invalid', 'confirm_required', 'failed'], true) && $replyFlash !== [];
$replyFlashErrorFields = $replyHasFlash ? $replyFlash['error_fields'] : [];
$replyFieldErrors = match ($replyStatus) {
    'invalid' => $replyFlashErrorFields !== [] ? $replyFlashErrorFields : ['reply_subject', 'reply_message'],
    'confirm_required' => $replyFlashErrorFields !== [] ? $replyFlashErrorFields : [$replyConfirmField],
    default => [],
};
$replySubjectValue = $replyHasFlash
    ? (string)$replyFlash['subject']
    : (trim((string)($message['reply_subject'] ?? '')) !== '' ? (string)$message['reply_subject'] : 'Re: ' . (string)$message['subject']);
$replyMessageValue = $replyHasFlash
    ? (string)$replyFlash['message']
    : (trim((string)($message['reply_body'] ?? '')) !== '' ? (string)$message['reply_body'] : "Dobrý den,\n\nděkujeme za vaši zprávu.\n\n");
$replyHasFormError = in_array($replyStatus, ['invalid', 'confirm_required', 'failed'], true);
$replyFieldErrorMessages = [
    'reply_subject' => 'Zadejte předmět odpovědi, aby příjemce poznal, k jaké zprávě se vracíte.',
    'reply_message' => 'Doplňte text odpovědi. Nestačí prázdná zpráva.',
    $replyConfirmField => 'Před odesláním potvrďte, že jste zkontrolovali příjemce, předmět a text odpovědi.',
];
$deleteConfirmError = trim((string)($_GET['error'] ?? '')) === 'contact_delete_confirm_required'
    && inputInt('get', 'delete_id') === $messageId;
$deleteConfirmField = 'confirm_contact_delete_' . $messageId;
$deleteConfirmId = 'confirm-contact-message-delete-' . $messageId;
$deleteReviewId = 'contact-message-delete-review-' . $messageId;
$deleteFieldErrorId = 'confirm-contact-message-delete-' . $messageId . '-error';
$deleteErrorFields = $deleteConfirmError ? [$deleteConfirmField] : [];

adminHeader('Kontaktní zpráva');
?>

<?php if ($deleteConfirmError): ?>
  <p id="contact-message-delete-error" class="error" role="alert" aria-atomic="true">Kontaktní zprávu nelze trvale smazat bez potvrzení. U pole Potvrzení trvalého smazání je konkrétní nápověda.</p>
<?php endif; ?>
<?php if (isset($_GET['ok'])): ?>
  <p class="success" role="status">Kontaktní zpráva byla aktualizována.</p>
<?php endif; ?>
<?php if ($replyStatus === 'sent'): ?>
  <p class="success" role="status" aria-atomic="true">Odpověď odesílateli byla úspěšně odeslána.</p>
<?php elseif ($replyStatus === 'missing'): ?>
  <p class="error" role="alert" aria-atomic="true">U této zprávy není dostupná žádná platná e-mailová adresa pro odpověď.</p>
<?php elseif ($replyStatus === 'invalid'): ?>
  <p id="<?= h($replyFormErrorId) ?>" class="error" role="alert" aria-atomic="true">Odpověď nejde odeslat, protože chybí povinný obsah nebo potvrzení kontroly. U zvýrazněných polí je konkrétní nápověda.</p>
<?php elseif ($replyStatus === 'confirm_required'): ?>
  <p id="<?= h($replyFormErrorId) ?>" class="error" role="alert" aria-atomic="true">Odpověď nejde odeslat bez potvrzení kontroly příjemce a obsahu. U pole Potvrzení odeslání je konkrétní nápověda.</p>
<?php elseif ($replyStatus === 'failed'): ?>
  <p id="<?= h($replyFormErrorId) ?>" class="error" role="alert" aria-atomic="true">Odpověď se nepodařilo odeslat. Rozepsaný obsah zůstal zachovaný; před dalším pokusem jej zkontrolujte a znovu potvrďte odeslání.</p>
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
  <form method="post" action="<?= BASE_URL ?>/admin/contact_action.php" id="contact-message-delete-form-<?= $messageId ?>" class="admin-inline-form" novalidate
        data-confirm="Smazat tuto kontaktní zprávu trvale?"<?= $deleteConfirmError ? ' aria-describedby="contact-message-delete-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
    <input type="hidden" name="success_redirect" value="<?= h($redirect) ?>">
    <fieldset class="admin-inline-fieldset">
      <legend class="sr-only">Trvalé smazání kontaktní zprávy <?= h(trim((string)($message['reference_code'] ?? '')) !== '' ? (string)$message['reference_code'] : (string)$message['subject']) ?></legend>
      <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">Odstraní text zprávy, referenci, kontaktní údaje odesílatele a případnou uloženou odpověď. Akci nelze vrátit.</p>
      <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
        <input type="checkbox" id="<?= h($deleteConfirmId) ?>" name="<?= h($deleteConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
        Potvrzuji trvalé smazání této kontaktní zprávy.
      </label>
      <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před trvalým smazáním potvrďte, že jste zkontrolovali zprávu a nevratnost akce.', $deleteFieldErrorId); ?>
      <button type="submit" class="btn btn-danger">Smazat</button>
    </fieldset>
  </form>
</div>

<h2>Odpověď odesílateli</h2>
<?php if (!filter_var((string)$message['sender_email'], FILTER_VALIDATE_EMAIL)): ?>
  <p>Tato zpráva neobsahuje platnou e-mailovou adresu, takže na ni nejde odpovědět přímo z administrace.</p>
<?php else: ?>
  <form method="post" action="<?= BASE_URL ?>/admin/contact_reply.php" novalidate<?= $replyHasFormError ? ' aria-describedby="' . h($replyFormErrorId) . '"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
    <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
    <fieldset>
      <legend>Obsah e-mailové odpovědi</legend>

      <label for="reply-to">Komu</label>
      <input type="email" id="reply-to" value="<?= h((string)$message['sender_email']) ?>" disabled>

      <label for="reply-subject">Předmět</label>
      <input type="text" id="reply-subject" name="subject" maxlength="255" required aria-required="true"
             value="<?= h($replySubjectValue) ?>"<?= adminFieldAttributes('reply_subject', $replyFieldErrors, [], [], 'reply-subject-error') ?>>
      <?php adminRenderFieldError('reply_subject', $replyFieldErrors, [], $replyFieldErrorMessages['reply_subject'], 'reply-subject-error'); ?>

      <label for="reply-message">Text odpovědi</label>
      <textarea id="reply-message" name="message" rows="7" required aria-required="true"<?= adminFieldAttributes('reply_message', $replyFieldErrors, [], [], 'reply-message-error') ?>><?= h($replyMessageValue) ?></textarea>
      <?php adminRenderFieldError('reply_message', $replyFieldErrors, [], $replyFieldErrorMessages['reply_message'], 'reply-message-error'); ?>
    </fieldset>

    <fieldset class="admin-fieldset-card admin-fieldset-spaced" aria-describedby="<?= h($replyReviewId) ?>">
      <legend>Kontrola odeslání</legend>
      <p id="<?= h($replyReviewId) ?>" class="field-help field-help--flush">E-mail se po potvrzení nevratně odešle na <strong><?= h((string)$message['sender_email']) ?></strong>. Před odesláním zkontrolujte příjemce, předmět a text; odpověď se také uloží ke kontaktní zprávě.</p>
      <label for="<?= h($replyConfirmId) ?>" class="admin-checkbox-label">
        <input type="checkbox" id="<?= h($replyConfirmId) ?>" name="<?= h($replyConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($replyConfirmField, $replyFieldErrors, [], [$replyReviewId], $replyConfirmErrorId) ?>>
        Potvrzuji, že jsem zkontroloval(a) příjemce, předmět a text odpovědi.
      </label>
      <?php adminRenderFieldError($replyConfirmField, $replyFieldErrors, [], $replyFieldErrorMessages[$replyConfirmField], $replyConfirmErrorId); ?>
    </fieldset>

    <div class="button-row admin-action-row">
      <button type="submit" class="btn" data-confirm="Opravdu odeslat tuto odpověď na <?= h((string)$message['sender_email']) ?>?">Odeslat odpověď</button>
    </div>
  </form>
<?php endif; ?>

<?php adminFooter(); ?>
