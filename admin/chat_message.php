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
    "SELECT c.id, c.topic_id, c.topic_label, c.conversation_type, c.reference_code,
            c.name, c.email, c.web, c.message, c.status, c.public_visibility, c.created_at, c.updated_at,
            c.is_pinned, c.pinned_until, c.pinned_at, c.pinned_by_user_id,
            c.approved_at, c.approved_by_user_id, c.internal_note, c.replied_at, c.replied_by_user_id,
            c.replied_subject, c.replied_to_email, c.replied_body,
            t.name AS topic_name, t.slug AS topic_slug,
            au.email AS approved_by_email, au.first_name AS approved_by_first_name, au.last_name AS approved_by_last_name, au.nickname AS approved_by_nickname,
            ru.email AS replied_by_email, ru.first_name AS replied_by_first_name, ru.last_name AS replied_by_last_name, ru.nickname AS replied_by_nickname
     FROM cms_chat c
     LEFT JOIN cms_chat_topics t ON t.id = c.topic_id
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
$conversationTypes = chatConversationTypeDefinitions();
$topics = chatTopics($pdo, false);
$historyEntries = chatHistoryEntries($pdo, $messageId);
$threadReplies = chatAdminReplies($pdo, $messageId);
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
$replyFieldErrors = isset($_GET['reply']) && $_GET['reply'] === 'invalid' ? ['reply_subject', 'reply_message'] : [];
$replyFieldErrorMessages = [
    'reply_subject' => 'Zadejte předmět odpovědi, aby příjemce poznal, k jaké zprávě se vracíte.',
    'reply_message' => 'Doplňte text odpovědi. Nestačí prázdná zpráva.',
];
$messageDeleteConfirmError = trim((string)($_GET['error'] ?? '')) === 'chat_delete_confirm_required'
    && inputInt('get', 'delete_id') === $messageId;
$messageDeleteConfirmField = 'confirm_chat_delete_' . $messageId;
$messageDeleteConfirmId = 'confirm-chat-message-delete-' . $messageId;
$messageDeleteReviewId = 'chat-message-delete-review-' . $messageId;
$messageDeleteFieldErrorId = 'confirm-chat-message-delete-' . $messageId . '-error';
$messageDeleteErrorFields = $messageDeleteConfirmError ? [$messageDeleteConfirmField] : [];
$replyDeleteConfirmError = trim((string)($_GET['error'] ?? '')) === 'chat_reply_delete_confirm_required';
$replyDeleteErrorId = inputInt('get', 'delete_reply_id');
$feedbackCode = trim((string)($_GET['ok'] ?? ''));

adminHeader('Chat zpráva');
?>

<?php if ($messageDeleteConfirmError): ?>
  <p id="chat-message-delete-error" class="error" role="alert" aria-atomic="true">Chat zprávu nelze trvale smazat bez potvrzení. U pole Potvrzení trvalého smazání je konkrétní nápověda.</p>
<?php elseif ($replyDeleteConfirmError): ?>
  <p id="chat-reply-delete-error" class="error" role="alert" aria-atomic="true">Chatovou odpověď nelze trvale smazat bez potvrzení. U pole Potvrzení trvalého smazání odpovědi je konkrétní nápověda.</p>
<?php endif; ?>
<?php if ($feedbackCode === 'reply_deleted'): ?>
  <p class="success" role="status" aria-atomic="true">Chatová odpověď byla trvale smazána a ve workflow historii zůstal záznam o smazání.</p>
<?php elseif ($feedbackCode !== ''): ?>
  <p class="success" role="status">Chat zpráva byla aktualizována.</p>
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

<div class="button-row">
  <a href="<?= h($redirect) ?>" class="btn">Zpět na přehled chat zpráv</a>
  <a href="<?= h(BASE_URL . '/chat/index.php') ?>" class="btn" target="_blank" rel="noopener noreferrer">Zobrazit veřejný chat<?= newWindowLinkSrOnlySuffix() ?></a>
</div>

<table>
  <caption class="sr-only">Detail chat zprávy</caption>
  <tbody>
    <tr>
      <th scope="row">Jméno</th>
      <td><?= h((string)$message['name']) ?></td>
    </tr>
    <tr>
      <th scope="row">Typ zprávy</th>
      <td><strong><?= h(chatConversationTypeLabel((string)($message['conversation_type'] ?? 'public'))) ?></strong></td>
    </tr>
    <tr>
      <th scope="row">Referenční kód</th>
      <td><?= trim((string)($message['reference_code'] ?? '')) !== '' ? h((string)$message['reference_code']) : '–' ?></td>
    </tr>
    <tr>
      <th scope="row">Téma</th>
      <td><?= trim((string)($message['topic_name'] ?? $message['topic_label'] ?? '')) !== '' ? h((string)($message['topic_name'] ?? $message['topic_label'])) : 'Bez tématu' ?></td>
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
          <a href="<?= h((string)$message['web']) ?>" target="_blank" rel="nofollow noopener noreferrer"><?= h((string)$message['web']) ?><?= newWindowLinkSrOnlySuffix() ?></a>
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
      <th scope="row">Připnutí</th>
      <td>
        <?php if (chatMessageIsPinned($message)): ?>
          Připnuto<?= trim((string)($message['pinned_until'] ?? '')) !== '' ? ' do ' . h(formatCzechDate((string)$message['pinned_until'])) : '' ?>
        <?php else: ?>
          Nepřipnuto
        <?php endif; ?>
      </td>
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
          <?php if (trim((string)($message['replied_body'] ?? '')) !== ''): ?>
            <br><small>Text poslední odpovědi je uložen níže.</small>
          <?php endif; ?>
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
      <td class="table-cell--prewrap"><?= h((string)$message['message']) ?></td>
    </tr>
    <?php if (trim((string)($message['replied_body'] ?? '')) !== ''): ?>
      <tr>
        <th scope="row">Text poslední odpovědi</th>
        <td class="table-cell--prewrap"><?= h((string)$message['replied_body']) ?></td>
      </tr>
    <?php endif; ?>
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
  <form method="post" action="<?= BASE_URL ?>/admin/chat_action.php" id="chat-message-delete-form-<?= $messageId ?>" class="admin-inline-form" novalidate
        data-confirm="Smazat tuto chat zprávu trvale?"<?= $messageDeleteConfirmError ? ' aria-describedby="chat-message-delete-error"' : '' ?>>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="id" value="<?= (int)$message['id'] ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
    <input type="hidden" name="success_redirect" value="<?= h($redirect) ?>">
    <fieldset class="admin-inline-fieldset">
      <legend class="sr-only">Trvalé smazání chat zprávy od <?= h((string)$message['name']) ?></legend>
      <p id="<?= h($messageDeleteReviewId) ?>" class="field-help field-help--flush">Odstraní zprávu, kontaktní údaje, interní poznámku, <?= count($historyEntries) ?> záznamů workflow historie a <?= count($threadReplies) ?> veřejných odpovědí. Akci nelze vrátit.</p>
      <label for="<?= h($messageDeleteConfirmId) ?>" class="admin-checkbox-label">
        <input type="checkbox" id="<?= h($messageDeleteConfirmId) ?>" name="<?= h($messageDeleteConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($messageDeleteConfirmField, $messageDeleteErrorFields, [], [$messageDeleteReviewId], $messageDeleteFieldErrorId) ?>>
        Potvrzuji trvalé smazání této chat zprávy, její historie a odpovědí.
      </label>
      <?php adminRenderFieldError($messageDeleteConfirmField, $messageDeleteErrorFields, [], 'Před trvalým smazáním potvrďte, že jste zkontrolovali zprávu, historii, odpovědi a nevratnost akce.', $messageDeleteFieldErrorId); ?>
      <button type="submit" class="btn btn-danger">Smazat</button>
    </fieldset>
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

    <label for="chat-type">Typ zprávy</label>
    <select id="chat-type" name="conversation_type">
      <?php foreach ($conversationTypes as $typeKey => $definition): ?>
        <option value="<?= h($typeKey) ?>"<?= $typeKey === (string)($message['conversation_type'] ?? 'public') ? ' selected' : '' ?>>
          <?= h((string)$definition['label']) ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label for="chat-topic">Téma</label>
    <select id="chat-topic" name="topic_id">
      <option value="">Bez tématu</option>
      <?php foreach ($topics as $topic): ?>
        <option value="<?= (int)$topic['id'] ?>"<?= (int)($message['topic_id'] ?? 0) === (int)$topic['id'] ? ' selected' : '' ?>>
          <?= h((string)$topic['name']) ?><?= (int)$topic['is_active'] !== 1 ? ' (vypnuté)' : '' ?>
        </option>
      <?php endforeach; ?>
    </select>

    <label class="checkbox-label">
      <input type="checkbox" name="is_pinned" value="1"<?= (int)($message['is_pinned'] ?? 0) === 1 ? ' checked' : '' ?>>
      Připnout zprávu ve veřejném chatu
    </label>

    <label for="chat-pinned-until">Připnout do</label>
    <input type="datetime-local" id="chat-pinned-until" name="pinned_until"
           value="<?= h(trim((string)($message['pinned_until'] ?? '')) !== '' ? date('Y-m-d\TH:i', strtotime((string)$message['pinned_until'])) : '') ?>">

    <label for="chat-note">Interní poznámka</label>
    <textarea id="chat-note" name="internal_note" rows="4"><?= h((string)($message['internal_note'] ?? '')) ?></textarea>

    <button type="submit" class="btn">Uložit workflow</button>
  </fieldset>
</form>

<h2>Veřejné odpovědi ve vlákně</h2>
<?php if ($threadReplies === []): ?>
  <p>Zatím tu nejsou žádné odpovědi.</p>
<?php else: ?>
  <table>
    <caption>Odpovědi na chat zprávu</caption>
    <thead>
      <tr>
        <th scope="col">Autor</th>
        <th scope="col">Odpověď</th>
        <th scope="col">Stav</th>
        <th scope="col">Přijato</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($threadReplies as $reply): ?>
        <?php
        $replyId = (int)$reply['id'];
          $replyDeleteConfirmField = 'confirm_chat_reply_delete_' . $replyId;
          $replyDeleteConfirmId = 'confirm-chat-reply-delete-' . $replyId;
          $replyDeleteReviewId = 'chat-reply-delete-review-' . $replyId;
          $replyDeleteFieldErrorId = 'confirm-chat-reply-delete-' . $replyId . '-error';
          $replyDeleteHasError = $replyDeleteConfirmError && $replyDeleteErrorId === $replyId;
          $replyDeleteErrorFields = $replyDeleteHasError ? [$replyDeleteConfirmField] : [];
          ?>
        <tr>
          <td>
            <strong><?= h((string)$reply['name']) ?></strong>
            <?php if (trim((string)($reply['email'] ?? '')) !== ''): ?>
              <br><a href="mailto:<?= h((string)$reply['email']) ?>"><?= h((string)$reply['email']) ?></a>
            <?php endif; ?>
          </td>
          <td class="table-cell--prewrap"><?= h((string)$reply['message']) ?></td>
          <td><strong><?= h(chatReplyStatusLabel((string)$reply['status'])) ?></strong></td>
          <td><time datetime="<?= h(str_replace(' ', 'T', (string)$reply['created_at'])) ?>"><?= formatCzechDate((string)$reply['created_at']) ?></time></td>
          <td class="actions">
            <?php foreach (['approve' => 'Schválit', 'hide' => 'Skrýt'] as $replyAction => $replyLabel): ?>
              <form method="post" action="<?= BASE_URL ?>/admin/chat_reply_action.php">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $replyId ?>">
                <input type="hidden" name="action" value="<?= h($replyAction) ?>">
                <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
                <button type="submit" class="btn"><?= h($replyLabel) ?></button>
              </form>
            <?php endforeach; ?>
            <form method="post" action="<?= BASE_URL ?>/admin/chat_reply_action.php" id="chat-reply-delete-form-<?= $replyId ?>" class="admin-inline-form" novalidate
                  data-confirm="Smazat tuto odpověď trvale?"<?= $replyDeleteHasError ? ' aria-describedby="chat-reply-delete-error"' : '' ?>>
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="id" value="<?= $replyId ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="redirect" value="<?= h($selfRedirect) ?>">
              <fieldset class="admin-inline-fieldset">
                <legend class="sr-only">Trvalé smazání chatové odpovědi od <?= h((string)$reply['name']) ?></legend>
                <p id="<?= h($replyDeleteReviewId) ?>" class="field-help field-help--flush">Odstraní text odpovědi, kontaktní údaje autora a stav moderace. Ve workflow historii zůstane záznam o smazání. Akci nelze vrátit.</p>
                <label for="<?= h($replyDeleteConfirmId) ?>" class="admin-checkbox-label">
                  <input type="checkbox" id="<?= h($replyDeleteConfirmId) ?>" name="<?= h($replyDeleteConfirmField) ?>" value="1" required aria-required="true"<?= adminFieldAttributes($replyDeleteConfirmField, $replyDeleteErrorFields, [], [$replyDeleteReviewId], $replyDeleteFieldErrorId) ?>>
                  Potvrzuji trvalé smazání této chatové odpovědi.
                </label>
                <?php adminRenderFieldError($replyDeleteConfirmField, $replyDeleteErrorFields, [], 'Před trvalým smazáním potvrďte, že jste zkontrolovali odpověď a nevratnost akce.', $replyDeleteFieldErrorId); ?>
                <button type="submit" class="btn btn-danger">Smazat</button>
              </fieldset>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

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
             value="<?= h(trim((string)($message['replied_subject'] ?? '')) !== '' ? (string)$message['replied_subject'] : 'Re: zpráva z chatu – ' . getSetting('site_name', 'Kora CMS')) ?>"<?= adminFieldAttributes('reply_subject', $replyFieldErrors, [], [], 'reply-subject-error') ?>>
      <?php adminRenderFieldError('reply_subject', $replyFieldErrors, [], $replyFieldErrorMessages['reply_subject'], 'reply-subject-error'); ?>

      <label for="reply-message">Text odpovědi</label>
      <textarea id="reply-message" name="message" rows="6"<?= adminFieldAttributes('reply_message', $replyFieldErrors, [], [], 'reply-message-error') ?>>Dobrý den,&#10;&#10;děkujeme za vaši zprávu v chatu.&#10;&#10;</textarea>
      <?php adminRenderFieldError('reply_message', $replyFieldErrors, [], $replyFieldErrorMessages['reply_message'], 'reply-message-error'); ?>

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
