<div class="auth-shell">
  <section class="surface surface--narrow" aria-labelledby="status-title">
    <?php if ($kicker !== ''): ?>
      <p class="section-kicker"><?= h($kicker) ?></p>
    <?php endif; ?>
    <h1 id="status-title" class="section-title section-title--hero"><?= h($title) ?></h1>

    <div class="status-message status-message--<?= h($variant) ?>"<?= $announceRole !== '' ? ' role="' . h($announceRole) . '"' : '' ?>>
      <?php foreach ($messages as $message): ?>
        <p><?= h($message) ?></p>
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
