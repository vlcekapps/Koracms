<div class="listing-shell">
  <section class="surface" aria-labelledby="chat-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Komunita</p>
        <h1 id="chat-title" class="section-title section-title--hero">Chat</h1>
      </div>
    </div>

    <?php if (empty($messages)): ?>
      <p class="empty-state">Zatím tu nejsou žádné zprávy.</p>
    <?php else: ?>
      <div class="chat-stream" aria-label="Zprávy z chatu">
        <?php foreach ($messages as $message): ?>
          <article class="chat-message">
            <header class="chat-message__header">
              <p class="meta-row">
                <strong><?= h($message['name']) ?></strong>
                <?php if ($message['email'] !== ''): ?>
                  <a href="mailto:<?= h($message['email']) ?>"><?= h($message['email']) ?></a>
                <?php endif; ?>
                <?php if ($message['web'] !== ''): ?>
                  <a href="<?= h($message['web']) ?>" rel="nofollow noopener noreferrer"><?= h($message['web']) ?></a>
                <?php endif; ?>
                <time datetime="<?= h(str_replace(' ', 'T', $message['created_at'])) ?>"><?= formatCzechDate($message['created_at']) ?></time>
              </p>
            </header>
            <p class="chat-message__body"><?= nl2br(h($message['message'])) ?></p>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="surface surface--narrow" aria-labelledby="chat-form-title">
    <p class="section-kicker">Přidejte se</p>
    <h2 id="chat-form-title" class="section-title">Napsat zprávu</h2>

    <?php if (!empty($errors)): ?>
      <div id="form-errors" class="status-message status-message--error" role="alert">
        <ul>
          <?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" novalidate class="form-stack"<?php if (!empty($errors)): ?> aria-describedby="form-errors"<?php endif; ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <?= honeypotField() ?>

      <fieldset class="form-fieldset">
        <legend>Přidat zprávu</legend>

        <div class="field">
          <label for="name">Jméno <span aria-hidden="true">*</span></label>
          <input type="text" id="name" name="name" class="form-control" required maxlength="100"
                 aria-required="true" value="<?= h($formData['name']) ?>">
        </div>

        <div class="field">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" class="form-control" maxlength="255"
                 aria-describedby="chat-email-help"
                 value="<?= h($formData['email']) ?>">
          <small id="chat-email-help" class="help-text">Nepovinné pole.</small>
        </div>

        <div class="field">
          <label for="web">Web</label>
          <input type="url" id="web" name="web" class="form-control" maxlength="255"
                 aria-describedby="chat-web-help"
                 value="<?= h($formData['web']) ?>">
          <small id="chat-web-help" class="help-text">Nepovinné pole.</small>
        </div>

        <div class="field">
          <label for="message">Zpráva <span aria-hidden="true">*</span></label>
          <textarea id="message" name="message" class="form-control" required
                    aria-required="true"><?= h($formData['message']) ?></textarea>
        </div>

        <div class="field">
          <label for="captcha">Ověření: kolik je <?= h($captchaExpr) ?>? <span aria-hidden="true">*</span></label>
          <input type="text" id="captcha" name="captcha" class="form-control form-control--compact" required
                 aria-required="true" inputmode="numeric" autocomplete="off">
        </div>

        <div class="button-row button-row--start">
          <button type="submit" class="button-primary">Odeslat zprávu</button>
        </div>
      </fieldset>
    </form>
  </section>
</div>
