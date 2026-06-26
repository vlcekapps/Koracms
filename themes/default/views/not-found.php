<section class="surface not-found" aria-labelledby="not-found-title">
  <p class="section-kicker">404</p>
  <h1 id="not-found-title" class="section-title section-title--hero"><?= h((string)($title ?? 'Stránka nenalezena')) ?></h1>
  <p><?= h((string)($message ?? 'Požadovaný obsah se nepodařilo najít nebo už není veřejně dostupný.')) ?></p>
  <p><a class="section-link" href="<?= BASE_URL ?>/index.php">Zpět na úvod <span aria-hidden="true">→</span></a></p>
</section>
