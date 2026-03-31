<div class="listing-shell">
  <section class="surface" aria-labelledby="chat-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Komunita</p>
        <h1 id="chat-title" class="section-title section-title--hero">Chat</h1>
      </div>
    </div>

    <?php if ($successState === 'pending'): ?>
      <p class="status-message status-message--success" role="status">
        Zpráva byla přijata a po schválení se objeví ve veřejném chatu.
      </p>
    <?php endif; ?>

    <p class="section-summary">
      Chat funguje jako moderovaná veřejná nástěnka. Zobrazujeme jen schválené zprávy a e-mail autora zůstává jen pro administraci.
    </p>

    <form method="get" class="chat-filter-form">
      <fieldset class="button-row">
        <legend class="sr-only">Filtrovat zprávy chatu</legend>

        <label for="chat-q" class="sr-only">Hledat v chatu</label>
        <input type="search" id="chat-q" name="q" class="form-control chat-filter-search" placeholder="Hledat ve zprávách…"
               value="<?= h($searchQuery) ?>">

        <label for="chat-razeni" class="sr-only">Řazení zpráv</label>
        <select id="chat-razeni" name="razeni" class="form-control form-control--compact">
          <option value="newest"<?= $sortOrder === 'newest' ? ' selected' : '' ?>>Nejnovější první</option>
          <option value="oldest"<?= $sortOrder === 'oldest' ? ' selected' : '' ?>>Nejstarší první</option>
        </select>

        <button type="submit" class="btn">Použít filtr</button>
        <?php if ($searchQuery !== '' || $sortOrder !== 'newest'): ?>
          <a href="<?= BASE_URL ?>/chat/index.php" class="btn">Zrušit filtr</a>
        <?php endif; ?>
      </fieldset>
    </form>

    <?php if (empty($messages)): ?>
      <p class="empty-state">
        <?= $searchQuery !== '' ? 'Pro zadané hledání jsme zatím nenašli žádnou schválenou zprávu.' : 'Zatím tu nejsou žádné schválené zprávy.' ?>
      </p>
    <?php else: ?>
      <div class="chat-stream" aria-label="Zprávy z chatu">
        <?php foreach ($messages as $message): ?>
          <article class="chat-message">
            <header class="chat-message__header">
              <p class="meta-row">
                <strong><?= h((string)$message['name']) ?></strong>
                <time datetime="<?= h(str_replace(' ', 'T', (string)$message['created_at'])) ?>">
                  <?= formatCzechDate((string)$message['created_at']) ?>
                </time>
              </p>
            </header>
            <p class="chat-message__body"><?= nl2br(h((string)$message['message'])) ?></p>
          </article>
        <?php endforeach; ?>
      </div>

      <?= renderPager(
          (int)$pagination['page'],
          (int)$pagination['totalPages'],
          $pagerBaseUrl,
          'Stránkování veřejného chatu',
          'Novější zprávy',
          'Starší zprávy'
      ) ?>
    <?php endif; ?>
  </section>

  <section class="surface surface--narrow" aria-labelledby="chat-form-title">
    <p class="section-kicker">Přidejte se</p>
    <h2 id="chat-form-title" class="section-title">Napsat zprávu</h2>
    <p class="field-help">Do textu nevkládejte odkazy. Každá nová zpráva před zveřejněním projde schválením.</p>

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
                 aria-describedby="chat-email-help" autocomplete="email"
                 value="<?= h($formData['email']) ?>">
          <small id="chat-email-help" class="help-text">Nepovinné pole. Slouží jen pro případnou odpověď správce a veřejně se nezobrazuje.</small>
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
