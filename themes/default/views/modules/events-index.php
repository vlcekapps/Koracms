<div class="listing-shell">
  <section class="surface" aria-labelledby="events-title">
    <div class="section-heading">
      <div>
        <p class="section-kicker">Program</p>
        <h1 id="events-title" class="section-title section-title--hero">Akce a události</h1>
      </div>
    </div>

    <?php if ($upTotal === 0 && $pastTotal === 0): ?>
      <p class="empty-state">Zatím nejsou zveřejněné žádné akce.</p>
    <?php else: ?>
      <div class="stack-sections">
        <?php if ($upTotal > 0): ?>
          <section aria-labelledby="events-upcoming-title">
            <h2 id="events-upcoming-title" class="section-title section-title--compact">Připravované akce</h2>
            <div class="table-shell">
              <table class="data-table" aria-labelledby="events-upcoming-title">
                <thead>
                  <tr>
                    <th scope="col">Název</th>
                    <th scope="col">Začátek</th>
                    <th scope="col">Konec</th>
                    <th scope="col">Místo</th>
                  </tr>
                </thead>
                <?php foreach ($upcoming as $event): ?>
                  <tbody>
                    <tr>
                      <td><?= h($event['title']) ?></td>
                      <td>
                        <time datetime="<?= h(str_replace(' ', 'T', $event['event_date'])) ?>"><?= formatCzechDate($event['event_date']) ?></time>
                      </td>
                      <td>
                        <?php if ($event['event_end']): ?>
                          <time datetime="<?= h(str_replace(' ', 'T', $event['event_end'])) ?>"><?= formatCzechDate($event['event_end']) ?></time>
                        <?php else: ?>
                          <span class="table-muted" aria-label="bez data konce">Neuvedeno</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($event['location'] !== ''): ?>
                          <?= h($event['location']) ?>
                        <?php else: ?>
                          <span class="table-muted" aria-label="místo neuvedeno">Neuvedeno</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                    <?php if (!empty($event['description'])): ?>
                      <tr>
                        <td colspan="4">
                          <details class="toggle-card toggle-card--inline">
                            <summary>Popis akce</summary>
                            <div class="prose toggle-card__content"><?= renderContent($event['description']) ?></div>
                          </details>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                <?php endforeach; ?>
              </table>
            </div>
            <?= eventsPager($upPage, $upPages, 'up_page', 'past_page', 'events-upcoming-title') ?>
          </section>
        <?php endif; ?>

        <?php if ($pastTotal > 0): ?>
          <section aria-labelledby="events-past-title">
            <h2 id="events-past-title" class="section-title section-title--compact">Proběhlé akce</h2>
            <div class="table-shell">
              <table class="data-table data-table--muted" aria-labelledby="events-past-title">
                <thead>
                  <tr>
                    <th scope="col">Název</th>
                    <th scope="col">Začátek</th>
                    <th scope="col">Konec</th>
                    <th scope="col">Místo</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($past as $event): ?>
                    <tr>
                      <td><?= h($event['title']) ?></td>
                      <td>
                        <time datetime="<?= h(str_replace(' ', 'T', $event['event_date'])) ?>"><?= formatCzechDate($event['event_date']) ?></time>
                      </td>
                      <td>
                        <?php if ($event['event_end']): ?>
                          <time datetime="<?= h(str_replace(' ', 'T', $event['event_end'])) ?>"><?= formatCzechDate($event['event_end']) ?></time>
                        <?php else: ?>
                          <span class="table-muted" aria-label="bez data konce">Neuvedeno</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <?php if ($event['location'] !== ''): ?>
                          <?= h($event['location']) ?>
                        <?php else: ?>
                          <span class="table-muted" aria-label="místo neuvedeno">Neuvedeno</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?= eventsPager($pastPage, $pastPages, 'past_page', 'up_page', 'events-past-title') ?>
          </section>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</div>
