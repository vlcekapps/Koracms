<?php
require_once __DIR__ . '/layout.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro správu newsletteru nemáte potřebné oprávnění.');
requireModuleEnabled('newsletter');

$pdo = db_connect();
$statusFilter = trim($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'pending', 'confirmed'], true)) {
    $statusFilter = 'all';
}
$q = trim($_GET['q'] ?? '');

$subscriberCounts = newsletterSubscriberCounts($pdo);
$totalSubscribers = array_sum($subscriberCounts);
$sentNewsletterCount = (int)$pdo->query("SELECT COUNT(*) FROM cms_newsletters")->fetchColumn();

$subscriberWhere = 'WHERE 1';
$subscriberParams = [];
if ($statusFilter === 'pending') {
    $subscriberWhere .= ' AND s.confirmed = 0';
} elseif ($statusFilter === 'confirmed') {
    $subscriberWhere .= ' AND s.confirmed = 1';
}
if ($q !== '') {
    $subscriberWhere .= ' AND s.email LIKE ?';
    $subscriberParams[] = '%' . $q . '%';
}

$subscriberStmt = $pdo->prepare(
    "SELECT s.id, s.email, s.confirmed, s.created_at
     FROM cms_subscribers s
     {$subscriberWhere}
     ORDER BY s.confirmed ASC, s.created_at DESC"
);
$subscriberStmt->execute($subscriberParams);
$subscribers = $subscriberStmt->fetchAll();

$newsletterWhere = 'WHERE 1';
$newsletterParams = [];
if ($q !== '') {
    $newsletterWhere .= ' AND (n.subject LIKE ? OR n.body LIKE ?)';
    $newsletterParams[] = '%' . $q . '%';
    $newsletterParams[] = '%' . $q . '%';
}

$newsletterStmt = $pdo->prepare(
    "SELECT n.id, n.subject, n.sent_at, n.recipient_count, n.created_at
     FROM cms_newsletters n
     {$newsletterWhere}
     ORDER BY COALESCE(n.sent_at, n.created_at) DESC, n.id DESC
     LIMIT 30"
);
$newsletterStmt->execute($newsletterParams);
$newsletters = $newsletterStmt->fetchAll();

$currentParams = [];
if ($statusFilter !== 'all') {
    $currentParams['status'] = $statusFilter;
}
if ($q !== '') {
    $currentParams['q'] = $q;
}
$currentRedirect = BASE_URL . '/admin/newsletter.php' . ($currentParams !== [] ? '?' . http_build_query($currentParams) : '');
$bulkOptions = [
    'confirm' => 'Potvrdit vybrané odběry',
    'resend' => 'Znovu poslat potvrzení',
    'delete' => 'Smazat vybrané',
];
$bulkCount = max(0, (int)($_GET['count'] ?? 0));
$bulkFailed = max(0, (int)($_GET['failed'] ?? 0));
$bulkConfirmError = trim($_GET['error'] ?? '') === 'bulk_confirm_required';
$bulkErrorFields = $bulkConfirmError ? ['confirm_newsletter_bulk_action'] : [];
$subscriberDeleteConfirmError = trim($_GET['error'] ?? '') === 'subscriber_delete_confirm_required';
$subscriberDeleteErrorId = inputInt('get', 'delete_error_id');

$successMessages = [
    'sent' => 'Newsletter byl odeslán a uložen do historie.',
    'confirmed' => 'Odběratel byl potvrzen.',
    'resent' => 'Potvrzovací e-mail byl znovu odeslán.',
    'deleted' => 'Odběratel byl smazán.',
];
$errorMessages = [
    'resend_failed' => 'Potvrzovací e-mail se nepodařilo odeslat. Zkuste to prosím znovu.',
];
$ok = trim($_GET['ok'] ?? '');
$error = trim($_GET['error'] ?? '');
$successMessage = match ($ok) {
    'sent' => 'Newsletter byl odeslán a uložen do historie.',
    'confirmed' => 'Odběratel byl potvrzen.',
    'resent' => 'Potvrzovací e-mail byl znovu odeslán.',
    'deleted' => 'Odběratel byl smazán.',
    'bulk_confirmed' => $bulkCount === 1
        ? 'Potvrzen byl 1 odběratel.'
        : 'Potvrzeno bylo ' . $bulkCount . ' odběratelů.',
    'bulk_resent' => $bulkCount === 1
        ? 'Potvrzovací e-mail byl znovu odeslán 1 odběrateli.'
        : 'Potvrzovací e-maily byly znovu odeslány ' . $bulkCount . ' odběratelům.',
    'bulk_deleted' => $bulkCount === 1
        ? 'Smazán byl 1 odběratel.'
        : 'Smazáno bylo ' . $bulkCount . ' odběratelů.',
    'bulk_no_change' => 'U vybraných odběratelů nebyla potřeba žádná změna.',
    default => $successMessages[$ok] ?? '',
};
$errorMessage = match ($error) {
    'bulk_resend_failed' => $bulkFailed === 1
        ? 'Potvrzovací e-mail se nepodařilo odeslat 1 odběrateli.'
        : 'Potvrzovací e-mail se nepodařilo odeslat ' . $bulkFailed . ' odběratelům.',
    'bulk_confirm_required' => 'Hromadnou akci nejde provést bez potvrzení kontroly vybraných odběratelů.',
    'subscriber_delete_confirm_required' => 'Odběratele newsletteru nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.',
    default => $errorMessages[$error] ?? '',
};

adminHeader('Newsletter');
?>

<?php if ($successMessage !== ''): ?>
  <p class="success" role="status"><?= h($successMessage) ?></p>
<?php endif; ?>
<?php if ($errorMessage !== ''): ?>
  <p class="error" role="alert" id="newsletter-page-error" aria-atomic="true"><?= h($errorMessage) ?></p>
<?php endif; ?>

<div class="button-row button-row--between button-row--top admin-stack-md">
  <div>
    <p class="admin-copy">Tady spravujete odběratele newsletteru a kontrolujete historii odeslaných rozesílek.</p>
    <p class="admin-copy admin-copy--compact">
      <strong><?= $subscriberCounts['confirmed'] ?></strong> potvrzených odběratelů,
      <strong><?= $subscriberCounts['pending'] ?></strong> čeká na potvrzení,
      <strong><?= $sentNewsletterCount ?></strong> rozesílek v historii.
    </p>
  </div>
  <a href="newsletter_form.php" class="btn">+ Nová rozesílka</a>
</div>

<nav aria-labelledby="newsletter-filter-heading" class="button-row admin-stack-sm">
  <h2 id="newsletter-filter-heading" class="sr-only">Filtr odběratelů newsletteru</h2>
  <a href="?status=all" <?= $statusFilter === 'all' ? 'aria-current="page"' : '' ?>>
    Všichni (<?= $totalSubscribers ?>)
  </a>
  <a href="?status=pending" <?= $statusFilter === 'pending' ? 'aria-current="page"' : '' ?>>
    Čekají na potvrzení (<?= $subscriberCounts['pending'] ?>)
  </a>
  <a href="?status=confirmed" <?= $statusFilter === 'confirmed' ? 'aria-current="page"' : '' ?>>
    Potvrzení (<?= $subscriberCounts['confirmed'] ?>)
  </a>
</nav>

<form method="get" class="button-row admin-stack-md">
  <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
  <label for="q" class="sr-only">Hledat v odběratelích a historii newsletterů</label>
  <input type="search" id="q" name="q" placeholder="Hledat podle e-mailu nebo předmětu rozesílky…"
         value="<?= h($q) ?>" class="admin-search-input">
  <button type="submit" class="btn">Použít filtr</button>
  <?php if ($q !== ''): ?>
    <a href="?status=<?= h($statusFilter) ?>" class="btn">Zrušit filtr</a>
  <?php endif; ?>
</form>

<section aria-labelledby="newsletter-subscribers-heading" class="admin-stack-lg">
  <div class="button-row button-row--between button-row--baseline admin-heading-row">
    <h2 id="newsletter-subscribers-heading">Odběratelé newsletteru</h2>
    <small><?= count($subscribers) ?> zobrazených odběratelů</small>
  </div>

  <?php if (empty($subscribers)): ?>
    <p><?= $statusFilter === 'all' && $q === '' ? 'Zatím tu nejsou žádní odběratelé.' : 'Pro zvolený filtr teď není k dispozici žádný odběratel.' ?></p>
  <?php else: ?>
    <form method="post" action="<?= BASE_URL ?>/admin/newsletter_bulk.php" id="newsletter-bulk-form"<?= $bulkConfirmError ? ' aria-describedby="newsletter-page-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
      <fieldset class="admin-fieldset-card">
        <legend>Hromadné akce s vybranými odběrateli</legend>
        <p data-selection-status="newsletter-subscribers" class="field-help field-help--flush" aria-live="polite">Zatím není vybraný žádný odběratel.</p>
        <div class="admin-stack-sm">
          <p id="newsletter-bulk-review-help" class="field-help field-help--flush">
            Před hromadnou akcí zkontrolujte vybrané odběratele a zvolenou akci. Potvrzení chrání před nechtěným mazáním,
            změnou stavu odběru nebo opakovaným odesláním potvrzovacích e-mailů.
          </p>
          <label for="confirm_newsletter_bulk_action" class="admin-checkbox-label">
            <input type="checkbox" id="confirm_newsletter_bulk_action" name="confirm_newsletter_bulk_action" value="1" required
                   <?= adminFieldAttributes('confirm_newsletter_bulk_action', $bulkErrorFields, [], ['newsletter-bulk-review-help'], 'confirm-newsletter-bulk-action-error') ?>>
            Potvrzuji, že jsem zkontroloval(a) vybrané odběratele a zvolenou hromadnou akci.
          </label>
          <?php adminRenderFieldError('confirm_newsletter_bulk_action', $bulkErrorFields, [], 'Před spuštěním hromadné akce zaškrtněte potvrzení kontroly vybraných odběratelů.', 'confirm-newsletter-bulk-action-error'); ?>
        </div>
        <div class="button-row">
          <?php foreach ($bulkOptions as $bulkAction => $bulkLabel): ?>
            <?php if (($bulkAction === 'confirm' || $bulkAction === 'resend') && $statusFilter === 'confirmed'): ?>
              <?php continue; ?>
            <?php endif; ?>
            <button type="submit" form="newsletter-bulk-form" name="action" value="<?= h($bulkAction) ?>"
                    class="btn bulk-action-btn<?= $bulkAction === 'delete' ? ' btn-danger' : '' ?>"
                    disabled
                    <?php if ($bulkAction === 'resend'): ?>data-confirm="Opravdu znovu poslat potvrzovací e-mail vybraným odběratelům?"<?php endif; ?>
                    <?php if ($bulkAction === 'delete'): ?>data-confirm="Smazat vybrané odběratele?"<?php endif; ?>>
              <?= h($bulkLabel) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </fieldset>
    </form>

    <table>
      <caption class="sr-only">Odběratelé newsletteru</caption>
      <thead>
        <tr>
          <th scope="col"><label for="newsletter-check-all" class="sr-only">Vybrat všechny odběratele newsletteru</label><input type="checkbox" id="newsletter-check-all" form="newsletter-bulk-form"></th>
          <th scope="col">E-mail</th>
          <th scope="col">Stav</th>
          <th scope="col">Přihlášen</th>
          <th scope="col">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($subscribers as $subscriber): ?>
          <?php
            $subscriberId = (int)$subscriber['id'];
            $isConfirmed = (int)$subscriber['confirmed'] === 1;
            $subscriberDeleteConfirmField = 'confirm_newsletter_subscriber_delete_' . $subscriberId;
            $subscriberDeleteConfirmId = 'confirm-newsletter-subscriber-delete-' . $subscriberId;
            $subscriberDeleteReviewId = 'newsletter-subscriber-delete-review-' . $subscriberId;
            $subscriberDeleteFieldErrorId = 'confirm-newsletter-subscriber-delete-' . $subscriberId . '-error';
            $subscriberDeleteHasError = $subscriberDeleteConfirmError && $subscriberDeleteErrorId === $subscriberId;
            $subscriberDeleteErrorFields = $subscriberDeleteHasError ? [$subscriberDeleteConfirmField] : [];
            ?>
          <tr>
            <td>
              <label for="newsletter-subscriber-select-<?= $subscriberId ?>" class="sr-only">Vybrat odběratele <?= h((string)$subscriber['email']) ?></label>
              <input type="checkbox" id="newsletter-subscriber-select-<?= $subscriberId ?>" name="ids[]" value="<?= $subscriberId ?>" form="newsletter-bulk-form">
            </td>
            <td><a href="mailto:<?= h((string)$subscriber['email']) ?>"><?= h((string)$subscriber['email']) ?></a></td>
            <td>
              <strong<?= !$isConfirmed ? ' class="text-pending"' : '' ?>>
                <?= h(newsletterSubscriberStatusLabel($isConfirmed)) ?>
              </strong>
            </td>
            <td>
              <time datetime="<?= h(str_replace(' ', 'T', (string)$subscriber['created_at'])) ?>">
                <?= formatCzechDate((string)$subscriber['created_at']) ?>
              </time>
            </td>
            <td class="actions">
              <a class="btn" href="newsletter_subscriber.php?id=<?= $subscriberId ?>&redirect=<?= rawurlencode($currentRedirect) ?>">Zobrazit detail</a>
              <form action="<?= BASE_URL ?>/admin/newsletter_subscriber_action.php" method="post"
                    class="admin-inline-form"
                    novalidate<?= $subscriberDeleteHasError ? ' aria-describedby="newsletter-page-error"' : '' ?>
                    data-confirm="Smazat tohoto odběratele?">
                <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                <input type="hidden" name="id" value="<?= $subscriberId ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="redirect" value="<?= h($currentRedirect) ?>">
                <fieldset class="admin-inline-fieldset">
                  <legend class="sr-only">Smazání odběratele <?= h((string)$subscriber['email']) ?></legend>
                  <p id="<?= h($subscriberDeleteReviewId) ?>" class="field-help field-help--flush">
                    Smazání odstraní e-mail z aktivních odběrů newsletteru. Historie už odeslaných rozesílek zůstane zachovaná.
                  </p>
                  <label for="<?= h($subscriberDeleteConfirmId) ?>" class="admin-checkbox-label">
                    <input
                      type="checkbox"
                      id="<?= h($subscriberDeleteConfirmId) ?>"
                      name="<?= h($subscriberDeleteConfirmField) ?>"
                      value="1"
                      required
                      aria-required="true"<?= adminFieldAttributes($subscriberDeleteConfirmField, $subscriberDeleteErrorFields, [], [$subscriberDeleteReviewId], $subscriberDeleteFieldErrorId) ?>>
                    Potvrzuji smazání tohoto odběratele.
                  </label>
                  <?php adminRenderFieldError($subscriberDeleteConfirmField, $subscriberDeleteErrorFields, [], 'Před smazáním odběratele potvrďte, že jste zkontrolovali jeho e-mail a dopad na další rozesílky.', $subscriberDeleteFieldErrorId); ?>
                  <button type="submit" class="btn btn-danger">Smazat</button>
                </fieldset>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="table-note" aria-hidden="true">Po výběru odběratelů můžete použít hromadné akce nahoře.</div>

    <script nonce="<?= cspNonce() ?>">
    (() => {
        const checkAll = document.getElementById('newsletter-check-all');
        const checkboxes = Array.from(document.querySelectorAll('input[form="newsletter-bulk-form"][name="ids[]"]'));
        const actionButtons = Array.from(document.querySelectorAll('#newsletter-bulk-form .bulk-action-btn'));
        const status = document.querySelector('[data-selection-status="newsletter-subscribers"]');

        const updateBulkUi = () => {
            const selectedCount = checkboxes.filter((checkbox) => checkbox.checked).length;
            if (status) {
                status.textContent = selectedCount === 0
                    ? 'Zatím není vybraný žádný odběratel.'
                    : (selectedCount === 1
                        ? 'Vybraný je 1 odběratel.'
                        : 'Vybráno: ' + selectedCount + ' odběratelů.');
            }
            actionButtons.forEach((button) => {
                button.disabled = selectedCount === 0;
            });
            if (checkAll) {
                checkAll.checked = selectedCount > 0 && selectedCount === checkboxes.length;
                checkAll.indeterminate = selectedCount > 0 && selectedCount < checkboxes.length;
            }
        };

        checkAll?.addEventListener('change', function () {
            checkboxes.forEach((checkbox) => {
                checkbox.checked = this.checked;
            });
            updateBulkUi();
        });

        checkboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateBulkUi);
        });

        updateBulkUi();
    })();
    </script>
  <?php endif; ?>
</section>

<section aria-labelledby="newsletter-history-heading">
  <div class="button-row button-row--between button-row--baseline admin-heading-row">
    <h2 id="newsletter-history-heading">Poslední rozesílky</h2>
    <small><?= count($newsletters) ?> zobrazených rozesílek</small>
  </div>

  <?php if (empty($newsletters)): ?>
    <p>Zatím jste neodeslali žádný newsletter.</p>
  <?php else: ?>
    <table>
      <caption class="sr-only">Historie odeslaných newsletterů</caption>
      <thead>
        <tr>
          <th scope="col">Předmět</th>
          <th scope="col">Odesláno</th>
          <th scope="col">Příjemců</th>
          <th scope="col">Akce</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($newsletters as $newsletter): ?>
          <tr>
            <td><strong><?= h((string)$newsletter['subject']) ?></strong></td>
            <td>
              <?php if (!empty($newsletter['sent_at'])): ?>
                <time datetime="<?= h(str_replace(' ', 'T', (string)$newsletter['sent_at'])) ?>">
                  <?= formatCzechDate((string)$newsletter['sent_at']) ?>
                </time>
              <?php else: ?>
                <em>Neodesláno</em>
              <?php endif; ?>
            </td>
            <td><?= (int)$newsletter['recipient_count'] ?></td>
            <td class="actions">
              <a class="btn" href="newsletter_history.php?id=<?= (int)$newsletter['id'] ?>&redirect=<?= rawurlencode($currentRedirect) ?>">Zobrazit detail</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<?php adminFooter(); ?>
