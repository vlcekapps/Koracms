<div class="listing-shell">
  <section class="surface" aria-labelledby="chat-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Komunita</p>
        <h1 id="chat-title" class="section-title section-title--hero"><?= h($activeTopic !== null ? 'Chat: ' . (string)$activeTopic['name'] : 'Chat') ?></h1>
      </div>
    </div>

    <?php if ($successState === 'pending'): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="chat-pending-message">
        <p id="chat-pending-message">Zpráva byla přijata a po schválení se objeví ve veřejném chatu.</p>
      </div>
    <?php elseif ($successState === 'support'): ?>
      <div class="status-message status-message--success" role="status" aria-atomic="true" aria-labelledby="chat-support-message">
        <p id="chat-support-message">
          Soukromý dotaz byl přijat.<?php if ($successReference !== ''): ?> Referenční kód: <strong><?= h($successReference) ?></strong>.<?php endif; ?>
        </p>
      </div>
    <?php endif; ?>

    <p class="section-summary">
      <?php if ($activeTopic !== null && trim((string)($activeTopic['description'] ?? '')) !== ''): ?>
        <?= h((string)$activeTopic['description']) ?>
      <?php else: ?>
        Chat funguje jako moderovaná veřejná nástěnka. Zobrazujeme jen schválené zprávy a e-mail autora zůstává jen pro administraci.
      <?php endif; ?>
    </p>

    <?php if (!empty($topics)): ?>
      <nav class="button-row" aria-labelledby="chat-topics-heading">
        <h2 id="chat-topics-heading" class="sr-only">Témata chatu</h2>
        <a href="<?= BASE_URL ?>/chat/index.php" class="btn"<?= $activeTopic === null ? ' aria-current="page"' : '' ?>>Všechna témata</a>
        <?php foreach ($topics as $topic): ?>
          <a href="<?= h(chatTopicPath($topic)) ?>" class="btn"<?= $activeTopic !== null && (int)$activeTopic['id'] === (int)$topic['id'] ? ' aria-current="page"' : '' ?>>
            <?= h((string)$topic['name']) ?>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endif; ?>

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
      <h2 id="chat-messages-heading" class="sr-only">Zprávy z chatu</h2>
      <div class="chat-stream" role="list" aria-labelledby="chat-messages-heading">
        <?php foreach ($messages as $messageIndex => $message): ?>
          <?php $chatMessageTitleId = 'chat-message-author-' . (int)$messageIndex; ?>
          <article class="chat-message" role="listitem" aria-labelledby="<?= h($chatMessageTitleId) ?>">
            <header class="chat-message__header">
              <p class="meta-row">
                <strong id="<?= h($chatMessageTitleId) ?>"><?= h((string)$message['name']) ?></strong>
                <?php if (chatMessageIsPinned($message)): ?>
                  <span class="badge">Připnuto</span>
                <?php endif; ?>
                <time datetime="<?= h(str_replace(' ', 'T', (string)$message['created_at'])) ?>">
                  <?= formatCzechDate((string)$message['created_at']) ?>
                </time>
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
            </header>
            <p class="chat-message__body"><?= nl2br(h((string)$message['message'])) ?></p>
            <p class="chat-message__actions">
              <a href="<?= h(chatMessagePath($message)) ?>">Zobrazit vlákno</a>
              <span>· <?= (int)($message['reply_count'] ?? 0) ?> odpovědí</span>
            </p>
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
      <div id="form-errors" class="status-message status-message--error" role="alert" aria-atomic="true" aria-labelledby="chat-errors-heading">
        <p id="chat-errors-heading" class="sr-only">Zprávu do chatu se nepodařilo odeslat</p>
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

        <fieldset class="form-fieldset form-fieldset--nested">
          <legend>Typ zprávy</legend>
          <label class="checkbox-label" for="conversation_type_public">
            <input id="conversation_type_public" type="radio" name="conversation_type" value="public"<?= ($formData['conversation_type'] ?? 'public') === 'public' ? ' checked' : '' ?>>
            Veřejná zpráva do moderovaného chatu
          </label>
          <label class="checkbox-label" for="conversation_type_support">
            <input id="conversation_type_support" type="radio" name="conversation_type" value="support"<?= ($formData['conversation_type'] ?? 'public') === 'support' ? ' checked' : '' ?>>
            Soukromý dotaz správci
          </label>
          <p class="help-text">Soukromý dotaz se veřejně nezobrazí a vyžaduje e-mail pro odpověď.</p>
        </fieldset>

        <?php if (!empty($topics)): ?>
          <div class="field">
            <label for="topic_id">Téma</label>
            <select id="topic_id" name="topic_id" class="form-control">
              <option value="">Bez tématu</option>
              <?php foreach ($topics as $topic): ?>
                <option value="<?= (int)$topic['id'] ?>"<?= (string)($formData['topic_id'] ?? '') === (string)(int)$topic['id'] ? ' selected' : '' ?>>
                  <?= h((string)$topic['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>

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
