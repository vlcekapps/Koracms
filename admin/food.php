<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$items = $pdo->query(
    "SELECT c.id, c.type, c.title, c.valid_from, c.valid_to, c.is_current, c.is_published,
            COALESCE(c.status,'published') AS status, c.created_at,
            COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email) AS author_name
     FROM cms_food_cards c
     LEFT JOIN cms_users u ON u.id = c.author_id
     ORDER BY c.type, c.is_current DESC, c.valid_from DESC, c.created_at DESC"
)->fetchAll();

adminHeader('Jídelní a nápojový lístek');
?>
<p>
  <a href="food_form.php?type=food" class="btn">+ Nový jídelní lístek</a>
  <a href="food_form.php?type=beverage" class="btn" style="margin-left:.5rem">+ Nový nápojový lístek</a>
</p>

<?php if (empty($items)): ?>
  <p>Zatím žádné lístky.</p>
<?php else: ?>
  <?php
  $groups = ['food' => [], 'beverage' => []];
  foreach ($items as $item) {
      $groups[$item['type']][] = $item;
  }
  $labels = ['food' => 'Jídelní lístky', 'beverage' => 'Nápojové lístky'];
  foreach ($groups as $type => $rows):
      if (empty($rows)) continue;
  ?>
  <h2 style="margin-top:2rem"><?= $labels[$type] ?></h2>
  <table>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Platnost</th>
        <th scope="col">Autor</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $c): ?>
      <tr>
        <td>
          <?php if ($c['is_current']): ?>
            <strong>&#9733; <?= h($c['title']) ?></strong>
          <?php else: ?>
            <?= h($c['title']) ?>
          <?php endif; ?>
        </td>
        <td>
          <?php
          $from = $c['valid_from'] ? formatCzechDate($c['valid_from']) : '–';
          $to   = $c['valid_to']   ? formatCzechDate($c['valid_to'])   : '–';
          echo h($from) . ' → ' . h($to);
          ?>
        </td>
        <td><?= $c['author_name'] ? h($c['author_name']) : '<em>–</em>' ?></td>
        <td>
          <?php if ($c['status'] === 'pending'): ?>
            <strong style="color:#c60">&#9203; Čeká na schválení</strong>
          <?php elseif ($c['is_current']): ?>
            <strong style="color:#060">&#9733; Aktuální</strong>
          <?php elseif ($c['is_published']): ?>
            V archivu
          <?php else: ?>
            <strong>Skryto</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="food_form.php?id=<?= (int)$c['id'] ?>" class="btn">Upravit</a>
          <?php if ($c['status'] === 'pending' && isSuperAdmin()): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="food">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/food.php">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="food_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat lístek &quot;<?= h(addslashes($c['title'])) ?>&quot;?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endforeach; ?>
<?php endif; ?>

<p style="margin-top:2rem;font-size:.85rem;color:#666">
  <span aria-hidden="true">&#9733;</span> = aktuálně zobrazený lístek na webu
</p>

<?php adminFooter(); ?>
