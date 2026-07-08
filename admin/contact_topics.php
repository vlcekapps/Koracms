<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu témat kontaktu nemáte potřebné oprávnění.');
requireModuleEnabled('contact');

$pdo = db_connect();
$success = '';
$error = '';
$fieldErrors = [];
$editId = inputInt('get', 'edit');
$formState = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'recipient_email' => '',
    'is_active' => '1',
    'sort_order' => '0',
];
$contactTopicRecipientEmailErrorMessage = 'Zadejte úplný cílový e-mail ve tvaru jmeno@example.cz, nebo pole nechte prázdné.';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? 'save'));
    $updateId = inputInt('post', 'update_id');

    if ($action === 'delete') {
        $deleteId = inputInt('post', 'id');
        if ($deleteId !== null) {
            $confirmFieldName = 'confirm_contact_topic_delete_' . $deleteId;
            $deleteConfirmed = isset($_POST[$confirmFieldName])
                && (string)$_POST[$confirmFieldName] === '1';
            if (!$deleteConfirmed) {
                header('Location: ' . BASE_URL . '/admin/contact_topics.php?error=delete_confirm_required&delete_error_id=' . $deleteId);
                exit;
            }

            $pdo->prepare("UPDATE cms_contact SET topic_id = NULL WHERE topic_id = ?")->execute([$deleteId]);
            $pdo->prepare("DELETE FROM cms_contact_topics WHERE id = ?")->execute([$deleteId]);
            logAction('contact_topic_delete', 'id=' . $deleteId);
            header('Location: ' . BASE_URL . '/admin/contact_topics.php?ok=delete');
            exit;
        }
        $error = 'Téma se nepodařilo najít.';
    } else {
        $formState = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'recipient_email' => trim((string)($_POST['recipient_email'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? '1' : '0',
            'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
        ];
        $editId = $updateId;

        if ($formState['name'] === '') {
            $fieldErrors['name'] = 'Doplňte krátký název tématu, například Fakturace.';
        }
        if ($formState['recipient_email'] !== '' && !filter_var($formState['recipient_email'], FILTER_VALIDATE_EMAIL)) {
            $fieldErrors['recipient_email'] = $contactTopicRecipientEmailErrorMessage;
        }

        $submittedSlug = contactTopicSlug($formState['slug'] !== '' ? $formState['slug'] : $formState['name']);
        if ($submittedSlug === '') {
            $fieldErrors['slug'] = 'Použijte alespoň jedno písmeno nebo číslo. Vhodný slug může vypadat třeba fakturace.';
        }
        if ($fieldErrors === []) {
            $uniqueSlug = uniqueContactTopicSlug($pdo, $submittedSlug, $updateId);
            if ($formState['slug'] !== '' && $uniqueSlug !== $submittedSlug) {
                $fieldErrors['slug'] = 'Zadejte jiný unikátní slug, nebo pole nechte prázdné a CMS ho vytvoří z názvu.';
            } else {
                $slug = $uniqueSlug;
                if ($updateId !== null) {
                    $stmt = $pdo->prepare(
                        "UPDATE cms_contact_topics
                         SET name = ?, slug = ?, description = ?, recipient_email = ?, is_active = ?, sort_order = ?
                         WHERE id = ?"
                    );
                    $stmt->execute([
                        $formState['name'],
                        $slug,
                        $formState['description'],
                        $formState['recipient_email'],
                        (int)$formState['is_active'],
                        (int)$formState['sort_order'],
                        $updateId,
                    ]);
                    logAction('contact_topic_edit', 'id=' . $updateId . ';slug=' . $slug);
                    header('Location: ' . BASE_URL . '/admin/contact_topics.php?ok=save');
                    exit;
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO cms_contact_topics
                     (name, slug, description, recipient_email, is_active, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $formState['name'],
                    $slug,
                    $formState['description'],
                    $formState['recipient_email'],
                    (int)$formState['is_active'],
                    (int)$formState['sort_order'],
                ]);
                logAction('contact_topic_add', 'slug=' . $slug);
                header('Location: ' . BASE_URL . '/admin/contact_topics.php?ok=save');
                exit;
            }
        }

        $error = 'Téma kontaktu nejde uložit. U zvýrazněných polí je konkrétní nápověda.';
    }
}

$topics = contactTopics($pdo, false);

if ($editId !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $topic = contactTopicById($pdo, $editId, false);
    if ($topic !== null) {
        $formState = [
            'name' => (string)($topic['name'] ?? ''),
            'slug' => (string)($topic['slug'] ?? ''),
            'description' => (string)($topic['description'] ?? ''),
            'recipient_email' => (string)($topic['recipient_email'] ?? ''),
            'is_active' => (string)(int)($topic['is_active'] ?? 1),
            'sort_order' => (string)(int)($topic['sort_order'] ?? 0),
        ];
    } else {
        $editId = null;
    }
}

$success = match ((string)($_GET['ok'] ?? '')) {
    'save' => 'Téma kontaktu bylo uloženo.',
    'delete' => 'Téma kontaktu bylo smazáno.',
    default => $success,
};
$deleteConfirmError = trim((string)($_GET['error'] ?? '')) === 'delete_confirm_required';
$deleteErrorId = inputInt('get', 'delete_error_id');
if ($deleteConfirmError) {
    $error = 'Téma kontaktu nejde smazat bez potvrzení kontroly dopadu. U pole Potvrzení smazání je konkrétní nápověda.';
}
$saveFormHasError = $error !== '' && !$deleteConfirmError;

adminHeader('Témata kontaktu');
?>

<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="contact-topic-form-error" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="contact.php">Zpět na kontaktní zprávy</a>
</p>

<form method="post" novalidate<?= $saveFormHasError ? ' aria-describedby="contact-topic-form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($editId !== null): ?>
    <input type="hidden" name="update_id" value="<?= (int)$editId ?>">
  <?php endif; ?>
  <fieldset>
    <legend><?= $editId !== null ? 'Upravit téma kontaktu' : 'Nové téma kontaktu' ?></legend>
    <p id="contact-topic-help" class="field-help field-help--flush">Aktivní témata se zobrazí ve veřejném kontaktním formuláři. Pokud má téma vlastní e-mail, oznámení půjde na něj.</p>

    <div class="form-grid">
      <div class="form-group">
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
               value="<?= h($formState['name']) ?>"<?= adminFieldAttributes('name', array_keys($fieldErrors), [], ['contact-topic-help']) ?>>
        <?php adminRenderFieldError('name', array_keys($fieldErrors), ['name' => ['name']], $fieldErrors['name'] ?? ''); ?>
      </div>

      <div class="form-group">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" maxlength="150"
               value="<?= h($formState['slug']) ?>"<?= adminFieldAttributes('slug', array_keys($fieldErrors), [], ['contact-topic-slug-help']) ?>>
        <small id="contact-topic-slug-help" class="field-help">Volitelné. Pokud zůstane prázdný, vytvoří se automaticky z názvu.</small>
        <?php adminRenderFieldError('slug', array_keys($fieldErrors), ['slug' => ['slug']], $fieldErrors['slug'] ?? ''); ?>
      </div>

      <div class="form-group">
        <label for="recipient_email">Cílový e-mail</label>
        <input type="email" id="recipient_email" name="recipient_email" maxlength="255"
               value="<?= h($formState['recipient_email']) ?>"<?= adminFieldAttributes('recipient_email', array_keys($fieldErrors), [], ['contact-topic-recipient-email-help']) ?>>
        <small id="contact-topic-recipient-email-help" class="field-help">Volitelné. Pokud zůstane prázdný, použije se globální kontaktní e-mail.</small>
        <?php adminRenderFieldError('recipient_email', array_keys($fieldErrors), ['recipient_email' => ['recipient_email']], $fieldErrors['recipient_email'] ?? ''); ?>
      </div>

      <div class="form-group">
        <label for="sort_order">Pořadí</label>
        <input type="number" id="sort_order" name="sort_order" min="0" value="<?= h($formState['sort_order']) ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="description">Popis pro veřejný formulář</label>
      <textarea id="description" name="description" rows="4" aria-describedby="contact-topic-help"><?= h($formState['description']) ?></textarea>
    </div>

    <label class="checkbox-label">
      <input type="checkbox" name="is_active" value="1"<?= $formState['is_active'] === '1' ? ' checked' : '' ?>>
      Aktivní téma zobrazit ve veřejném formuláři
    </label>

    <div class="button-row button-row--start">
      <button type="submit" class="btn"><?= $editId !== null ? 'Uložit téma' : 'Přidat téma' ?></button>
      <?php if ($editId !== null): ?><a href="contact_topics.php" class="btn">Zrušit úpravu</a><?php endif; ?>
    </div>
  </fieldset>
</form>

<h2>Existující témata</h2>
<?php if ($topics === []): ?>
  <p>Zatím tu nejsou žádná témata. Bez témat funguje veřejný kontaktní formulář stejně jako dříve.</p>
<?php else: ?>
  <table>
    <caption>Témata kontaktního formuláře</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Cílový e-mail</th>
        <th scope="col">Stav</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($topics as $topic): ?>
        <?php
          $topicId = (int)$topic['id'];
          $deleteConfirmField = 'confirm_contact_topic_delete_' . $topicId;
          $deleteConfirmId = 'confirm-contact-topic-delete-' . $topicId;
          $deleteReviewId = 'contact-topic-delete-review-' . $topicId;
          $deleteFieldErrorId = 'confirm-contact-topic-delete-' . $topicId . '-error';
          $deleteHasError = $deleteConfirmError && $deleteErrorId === $topicId;
          $deleteErrorFields = $deleteHasError ? [$deleteConfirmField] : [];
          ?>
        <tr>
          <td>
            <strong><?= h((string)$topic['name']) ?></strong>
            <?php if (trim((string)($topic['description'] ?? '')) !== ''): ?>
              <br><small class="table-meta"><?= h(mb_strimwidth(normalizePlainText((string)$topic['description']), 0, 120, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$topic['slug']) ?></code></td>
          <td><?= trim((string)$topic['recipient_email']) !== '' ? h((string)$topic['recipient_email']) : 'Globální kontaktní e-mail' ?></td>
          <td><?= (int)$topic['is_active'] === 1 ? 'Aktivní' : 'Vypnuté' ?></td>
          <td><?= (int)$topic['sort_order'] ?></td>
          <td class="actions">
            <a href="contact_topics.php?edit=<?= $topicId ?>" class="btn">Upravit</a>
            <form method="post" action="<?= BASE_URL ?>/admin/contact_topics.php"
                  class="admin-inline-form"
                  novalidate<?= $deleteHasError ? ' aria-describedby="contact-topic-form-error"' : '' ?>
                  data-confirm="Smazat toto téma kontaktu? Existující zprávy si ponechají uložený název tématu.">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $topicId ?>">
              <fieldset class="admin-inline-fieldset">
                <legend class="sr-only">Smazání tématu kontaktu <?= h((string)$topic['name']) ?></legend>
                <p id="<?= h($deleteReviewId) ?>" class="field-help field-help--flush">
                  Smazání odebere téma z veřejného kontaktního formuláře a z existujících kontaktních zpráv odstraní vazbu na toto téma. Zprávy zůstanou zachované.
                </p>
                <label for="<?= h($deleteConfirmId) ?>" class="admin-checkbox-label">
                  <input
                    type="checkbox"
                    id="<?= h($deleteConfirmId) ?>"
                    name="<?= h($deleteConfirmField) ?>"
                    value="1"
                    required
                    aria-required="true"<?= adminFieldAttributes($deleteConfirmField, $deleteErrorFields, [], [$deleteReviewId], $deleteFieldErrorId) ?>>
                  Potvrzuji smazání tohoto tématu kontaktu.
                </label>
                <?php adminRenderFieldError($deleteConfirmField, $deleteErrorFields, [], 'Před smazáním tématu potvrďte, že jste zkontrolovali dopad na veřejný formulář a existující zprávy.', $deleteFieldErrorId); ?>
                <button type="submit" class="btn btn-danger">Smazat</button>
              </fieldset>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
