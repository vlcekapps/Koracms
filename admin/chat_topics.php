<?php
require_once __DIR__ . '/layout.php';
requireCapability('messages_manage', 'Přístup odepřen. Pro správu témat chatu nemáte potřebné oprávnění.');
requireModuleEnabled('chat');

$pdo = db_connect();
$success = '';
$error = '';
$fieldErrors = [];
$editId = inputInt('get', 'edit');
$formState = [
    'name' => '',
    'slug' => '',
    'description' => '',
    'is_active' => '1',
    'sort_order' => '0',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = trim((string)($_POST['action'] ?? 'save'));
    $updateId = inputInt('post', 'update_id');

    if ($action === 'delete') {
        $deleteId = inputInt('post', 'id');
        if ($deleteId !== null) {
            $pdo->prepare("UPDATE cms_chat SET topic_id = NULL WHERE topic_id = ?")->execute([$deleteId]);
            $pdo->prepare("DELETE FROM cms_chat_topics WHERE id = ?")->execute([$deleteId]);
            logAction('chat_topic_delete', 'id=' . $deleteId);
            header('Location: ' . BASE_URL . '/admin/chat_topics.php?ok=delete');
            exit;
        }
        $error = 'Téma se nepodařilo najít.';
    } else {
        $formState = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'slug' => trim((string)($_POST['slug'] ?? '')),
            'description' => trim((string)($_POST['description'] ?? '')),
            'is_active' => isset($_POST['is_active']) ? '1' : '0',
            'sort_order' => (string)max(0, (int)($_POST['sort_order'] ?? 0)),
        ];
        $editId = $updateId;

        if ($formState['name'] === '') {
            $fieldErrors['name'] = 'Název tématu je povinný.';
        }

        $submittedSlug = chatTopicSlug($formState['slug'] !== '' ? $formState['slug'] : $formState['name']);
        if ($submittedSlug === '') {
            $fieldErrors['slug'] = 'Slug tématu musí obsahovat alespoň jedno písmeno nebo číslo.';
        }

        if ($fieldErrors === []) {
            $uniqueSlug = uniqueChatTopicSlug($pdo, $submittedSlug, $updateId);
            if ($formState['slug'] !== '' && $uniqueSlug !== $submittedSlug) {
                $fieldErrors['slug'] = 'Tento slug už používá jiné téma chatu.';
            } else {
                if ($updateId !== null) {
                    $stmt = $pdo->prepare(
                        "UPDATE cms_chat_topics
                         SET name = ?, slug = ?, description = ?, is_active = ?, sort_order = ?, updated_at = NOW()
                         WHERE id = ?"
                    );
                    $stmt->execute([
                        $formState['name'],
                        $uniqueSlug,
                        $formState['description'],
                        (int)$formState['is_active'],
                        (int)$formState['sort_order'],
                        $updateId,
                    ]);
                    logAction('chat_topic_edit', 'id=' . $updateId . ';slug=' . $uniqueSlug);
                    header('Location: ' . BASE_URL . '/admin/chat_topics.php?ok=save');
                    exit;
                }

                $stmt = $pdo->prepare(
                    "INSERT INTO cms_chat_topics (name, slug, description, is_active, sort_order)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $formState['name'],
                    $uniqueSlug,
                    $formState['description'],
                    (int)$formState['is_active'],
                    (int)$formState['sort_order'],
                ]);
                logAction('chat_topic_add', 'slug=' . $uniqueSlug);
                header('Location: ' . BASE_URL . '/admin/chat_topics.php?ok=save');
                exit;
            }
        }

        $error = 'Zkontrolujte prosím zvýrazněná pole.';
    }
}

$topics = chatTopics($pdo, false);

if ($editId !== null && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $topic = chatTopicById($pdo, $editId, false);
    if ($topic !== null) {
        $formState = [
            'name' => (string)($topic['name'] ?? ''),
            'slug' => (string)($topic['slug'] ?? ''),
            'description' => (string)($topic['description'] ?? ''),
            'is_active' => (string)(int)($topic['is_active'] ?? 1),
            'sort_order' => (string)(int)($topic['sort_order'] ?? 0),
        ];
    } else {
        $editId = null;
    }
}

$success = match ((string)($_GET['ok'] ?? '')) {
    'save' => 'Téma chatu bylo uloženo.',
    'delete' => 'Téma chatu bylo smazáno.',
    default => $success,
};

adminHeader('Témata chatu');
?>

<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="chat-topic-form-error" class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <a href="chat.php">Zpět na chat zprávy</a>
</p>

<form method="post" novalidate<?= $error !== '' ? ' aria-describedby="chat-topic-form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($editId !== null): ?>
    <input type="hidden" name="update_id" value="<?= (int)$editId ?>">
  <?php endif; ?>
  <fieldset>
    <legend><?= $editId !== null ? 'Upravit téma chatu' : 'Nové téma chatu' ?></legend>
    <p id="chat-topic-help" class="field-help field-help--flush">Aktivní témata se zobrazí ve veřejném chatu a mají vlastní veřejnou adresu.</p>

    <div class="form-grid">
      <div class="form-group">
        <label for="name">Název <span aria-hidden="true">*</span></label>
        <input type="text" id="name" name="name" required aria-required="true" maxlength="255"
               value="<?= h($formState['name']) ?>"<?= adminFieldAttributes('name', array_keys($fieldErrors), [], ['chat-topic-help']) ?>>
        <?php adminRenderFieldError('name', array_keys($fieldErrors), ['name' => ['name']], $fieldErrors['name'] ?? ''); ?>
      </div>

      <div class="form-group">
        <label for="slug">Slug</label>
        <input type="text" id="slug" name="slug" maxlength="150" pattern="[a-z0-9\-]+"
               value="<?= h($formState['slug']) ?>"<?= adminFieldAttributes('slug', array_keys($fieldErrors), [], ['chat-topic-help']) ?>>
        <?php adminRenderFieldError('slug', array_keys($fieldErrors), ['slug' => ['slug']], $fieldErrors['slug'] ?? ''); ?>
      </div>

      <div class="form-group">
        <label for="sort_order">Pořadí</label>
        <input type="number" id="sort_order" name="sort_order" min="0" value="<?= h($formState['sort_order']) ?>">
      </div>
    </div>

    <div class="form-group">
      <label for="description">Popis tématu</label>
      <textarea id="description" name="description" rows="4" aria-describedby="chat-topic-help"><?= h($formState['description']) ?></textarea>
    </div>

    <label class="checkbox-label">
      <input type="checkbox" name="is_active" value="1"<?= $formState['is_active'] === '1' ? ' checked' : '' ?>>
      Aktivní téma zobrazit ve veřejném chatu
    </label>

    <div class="button-row button-row--start">
      <button type="submit" class="btn"><?= $editId !== null ? 'Uložit téma' : 'Přidat téma' ?></button>
      <?php if ($editId !== null): ?><a href="chat_topics.php" class="btn">Zrušit úpravu</a><?php endif; ?>
    </div>
  </fieldset>
</form>

<h2>Existující témata</h2>
<?php if ($topics === []): ?>
  <p>Zatím tu nejsou žádná témata. Bez témat funguje veřejný chat stejně jako dříve.</p>
<?php else: ?>
  <table>
    <caption>Témata veřejného chatu</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Slug</th>
        <th scope="col">Stav</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Veřejná stránka</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($topics as $topic): ?>
        <tr>
          <td>
            <strong><?= h((string)$topic['name']) ?></strong>
            <?php if (trim((string)($topic['description'] ?? '')) !== ''): ?>
              <br><small class="table-meta"><?= h(mb_strimwidth(normalizePlainText((string)$topic['description']), 0, 120, '…', 'UTF-8')) ?></small>
            <?php endif; ?>
          </td>
          <td><code><?= h((string)$topic['slug']) ?></code></td>
          <td><?= (int)$topic['is_active'] === 1 ? 'Aktivní' : 'Vypnuté' ?></td>
          <td><?= (int)$topic['sort_order'] ?></td>
          <td>
            <?php if ((int)$topic['is_active'] === 1): ?>
              <a href="<?= h(chatTopicPath($topic)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
            <?php else: ?>
              –
            <?php endif; ?>
          </td>
          <td class="actions">
            <a href="chat_topics.php?edit=<?= (int)$topic['id'] ?>" class="btn">Upravit</a>
            <form method="post" action="<?= BASE_URL ?>/admin/chat_topics.php" data-confirm="Smazat toto téma chatu? Existující zprávy zůstanou zachované bez tématu.">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$topic['id'] ?>">
              <button type="submit" class="btn btn-danger">Smazat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
