<div class="listing-shell">
  <article class="surface" aria-labelledby="chat-message-title">
    <p class="section-kicker">Chat</p>
    <h1 id="chat-message-title" class="section-title section-title--hero">Zpráva od <?= h((string)$message['name']) ?></h1>
    <p class="meta-row">
      <time datetime="<?= h(str_replace(' ', 'T', (string)$message['created_at'])) ?>">
        <?= formatCzechDate((string)$message['created_at']) ?>
      </time>
      <?php if (chatMessageIsPinned($message)): ?>
        <span class="badge">Připnuto</span>
      <?php endif; ?>
    </p>
    <?php if (trim((string)($message['topic_name'] ?? $message['topic_label'] ?? '')) !== ''): ?>
      <p class="meta-row">
        Téma:
        <?php if (trim((string)($message['topic_slug'] ?? '')) !== ''): ?>
          <a href="<?= h(chatTopicPath(['slug' => (string)$message['topic_slug']])) ?>"><?= h((string)($message['topic_name'] ?? $message['topic_label'])) ?></a>
        <?php else: ?>
          <?= h((string)$message['topic_label']) ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>
    <p class="chat-message__body"><?= nl2br(h((string)$message['message'])) ?></p>
    <p><a href="<?= h($backUrl) ?>">Zpět na chat</a></p>
  </article>

  <section class="surface" aria-labelledby="chat-replies-title">
    <h2 id="chat-replies-title" class="section-title">Odpovědi</h2>
    <?php if ($successState === 'pending'): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="chat-reply-pending">
        <p id="chat-reply-pending">Odpověď byla přijata a po schválení se zobrazí ve vlákně.</p>
      </div>
    <?php endif; ?>

    <?php if ($replies === []): ?>
      <p class="empty-state">Zatím tu nejsou žádné schválené odpovědi.</p>
    <?php else: ?>
      <div class="chat-stream" role="list" aria-labelledby="chat-replies-title">
        <?php foreach ($replies as $replyIndex => $reply): ?>
          <?php $replyTitleId = 'chat-reply-author-' . (int)$replyIndex; ?>
          <article class="chat-message" role="listitem" aria-labelledby="<?= h($replyTitleId) ?>">
            <header class="chat-message__header">
              <p class="meta-row">
                <strong id="<?= h($replyTitleId) ?>"><?= h((string)$reply['name']) ?></strong>
                <time datetime="<?= h(str_replace(' ', 'T', (string)$reply['created_at'])) ?>">
                  <?= formatCzechDate((string)$reply['created_at']) ?>
                </time>
              </p>
            </header>
            <p class="chat-message__body"><?= nl2br(h((string)$reply['message'])) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="surface surface--narrow" aria-labelledby="chat-reply-form-title">
    <h2 id="chat-reply-form-title" class="section-title">Přidat odpověď</h2>
    <p class="field-help">Odpověď se zobrazí až po schválení. Do textu nevkládejte odkazy.</p>

    <?php if ($errors !== []): ?>
      <div id="chat-reply-errors" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="chat-reply-errors-heading">
        <p id="chat-reply-errors-heading" class="sr-only">Odpověď se nepodařilo odeslat</p>
        <ul>
          <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate class="form-stack"<?php if ($errors !== []): ?> aria-describedby="chat-reply-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <?= honeypotField() ?>
      <fieldset class="form-fieldset">
        <legend>Odpovědět ve vlákně</legend>
        <div class="field">
          <label for="reply-name">Jméno <span aria-hidden="true">*</span></label>
          <input type="text" id="reply-name" name="name" class="form-control" required aria-required="true" maxlength="100"
                 value="<?= h($formData['name']) ?>">
        </div>
        <div class="field">
          <label for="reply-email">E-mail</label>
          <input type="email" id="reply-email" name="email" class="form-control" maxlength="255"
                 aria-describedby="reply-email-help" autocomplete="email" value="<?= h($formData['email']) ?>">
          <small id="reply-email-help" class="help-text">Nepovinné pole. Veřejně se nezobrazuje.</small>
        </div>
        <div class="field">
          <label for="reply-message">Odpověď <span aria-hidden="true">*</span></label>
          <textarea id="reply-message" name="message" class="form-control" required aria-required="true"><?= h($formData['message']) ?></textarea>
        </div>
        <div class="field">
          <label for="reply-captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
          <input type="text" id="reply-captcha" name="captcha" class="form-control form-control--compact" required
                 aria-required="true" inputmode="numeric" autocomplete="off">
        </div>
        <div class="button-row button-row--start">
          <button type="submit" class="button-primary">Odeslat odpověď</button>
        </div>
      </fieldset>
    </form>
  </section>
</div>
