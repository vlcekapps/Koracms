<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="status-title">
    <?php if ($kicker !== ''): ?>
      <p class="section-kicker"><?= h($kicker) ?></p>
    <?php endif; ?>
    <h1 id="status-title" class="section-title section-title--hero"><?= h($title) ?></h1>

    <?php
    $statusMessageId = isset($statusMessageId) && $statusMessageId !== '' ? (string)$statusMessageId : 'status-message';
    $statusMessageAttributes = $announceRole !== ''
        ? ' role="' . h($announceRole) . '" aria-atomic="true" aria-labelledby="' . h($statusMessageId) . '"'
        : '';
    $statusMessageIndex = 0;
    ?>
    <div class="status-message status-message--<?= h($variant) ?>"<?= $statusMessageAttributes ?>>
      <?php foreach ($messages as $message): ?>
        <p<?= $statusMessageAttributes !== '' && $statusMessageIndex === 0 ? ' id="' . h($statusMessageId) . '"' : '' ?>><?= h($message) ?></p>
        <?php $statusMessageIndex++; ?>
      <?php endforeach; ?>
    </div>

    <?php if (!empty($actions)): ?>
      <div class="status-actions">
        <?php foreach ($actions as $action): ?>
          <a class="<?= h($action['class'] ?? 'button-secondary') ?>" href="<?= h($action['href']) ?>"><?= h($action['label']) ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
