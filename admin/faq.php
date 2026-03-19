<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$faqs = db_connect()->query(
    "SELECT f.id, f.question, f.sort_order, f.is_published, c.name AS category_name
     FROM cms_faqs f
     LEFT JOIN cms_faq_categories c ON c.id = f.category_id
     ORDER BY c.sort_order, c.name, f.sort_order, f.id"
)->fetchAll();

adminHeader('FAQ – otázky');
?>
<p>
  <a href="faq_form.php" class="btn">+ Nová otázka</a>
  <a href="faq_cats.php" style="margin-left:1rem">Správa kategorií</a>
</p>

<?php if (empty($faqs)): ?>
  <p>Žádné otázky.</p>
<?php else: ?>
  <table>
    <caption>Často kladené otázky</caption>
    <thead>
      <tr>
        <th scope="col">Otázka</th>
        <th scope="col">Kategorie</th>
        <th scope="col">Pořadí</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($faqs as $f): ?>
      <tr>
        <td><?= h($f['question']) ?></td>
        <td><?= h($f['category_name'] ?: '–') ?></td>
        <td><?= (int)$f['sort_order'] ?></td>
        <td><?= $f['is_published'] ? 'Publikováno' : '<strong style="color:#c60">Skryto</strong>' ?></td>
        <td class="actions">
          <a href="faq_form.php?id=<?= (int)$f['id'] ?>" class="btn">Upravit</a>
          <form action="faq_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat otázku?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
