<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu formulářů nemáte potřebné oprávnění.');

$pdo = db_connect();

$forms = [];
try {
    $forms = $pdo->query(
        "SELECT f.id, f.title, f.slug, f.is_active, f.created_at,
                (SELECT COUNT(*) FROM cms_form_fields WHERE form_id = f.id) AS field_count,
                (SELECT COUNT(*) FROM cms_form_submissions WHERE form_id = f.id) AS submission_count
         FROM cms_forms f
         ORDER BY f.created_at DESC"
    )->fetchAll();
} catch (\PDOException $e) {
    error_log('admin/forms: ' . $e->getMessage());
}

adminHeader('Formuláře');
?>
<p><a href="form_form.php" class="btn">+ Nový formulář</a></p>

<?php if (empty($forms)): ?>
  <p>Zatím tu nejsou žádné formuláře. <a href="form_form.php">Vytvořit první formulář</a>.</p>
<?php else: ?>
  <table>
    <caption>Přehled formulářů</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Pole</th>
        <th scope="col">Odpovědi</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($forms as $form): ?>
      <tr>
        <td>
          <strong><?= h((string)$form['title']) ?></strong><br>
          <small style="color:#555">/forms/<?= h((string)$form['slug']) ?></small>
        </td>
        <td><?= (int)$form['field_count'] ?></td>
        <td><?= (int)$form['submission_count'] ?></td>
        <td><?= (int)$form['is_active'] ? 'Aktivní' : '<strong>Neaktivní</strong>' ?></td>
        <td class="actions">
          <a href="form_form.php?id=<?= (int)$form['id'] ?>" class="btn">Upravit</a>
          <?php if ((int)$form['submission_count'] > 0): ?>
            <a href="form_submissions.php?id=<?= (int)$form['id'] ?>">Odpovědi (<?= (int)$form['submission_count'] ?>)</a>
          <?php endif; ?>
          <a href="<?= h(formPublicPath($form)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu</a>
          <form action="form_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$form['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat formulář včetně všech odpovědí?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
