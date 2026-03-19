<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo   = db_connect();
$polls = $pdo->query(
    "SELECT p.*,
            (SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = p.id) AS vote_count
     FROM cms_polls p ORDER BY p.created_at DESC"
)->fetchAll();

adminHeader('Ankety');
?>
<p><a href="polls_form.php" class="btn">+ Nová anketa</a></p>

<?php if (empty($polls)): ?>
  <p>Žádné ankety.</p>
<?php else: ?>
  <table>
    <caption>Ankety</caption>
    <thead>
      <tr>
        <th scope="col">Otázka</th>
        <th scope="col">Stav</th>
        <th scope="col">Hlasů</th>
        <th scope="col">Začátek</th>
        <th scope="col">Konec</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($polls as $p): ?>
      <?php
        $now = date('Y-m-d H:i:s');
        if ($p['status'] === 'closed') {
            $statusLabel = 'Uzavřená';
            $statusStyle = 'color:#666';
        } elseif ($p['end_date'] && $p['end_date'] < $now) {
            $statusLabel = 'Vypršela';
            $statusStyle = 'color:#c60';
        } elseif ($p['start_date'] && $p['start_date'] > $now) {
            $statusLabel = 'Naplánovaná';
            $statusStyle = 'color:#069';
        } else {
            $statusLabel = 'Aktivní';
            $statusStyle = 'color:#060';
        }
      ?>
      <tr>
        <td><?= h($p['question']) ?></td>
        <td><strong style="<?= $statusStyle ?>"><?= $statusLabel ?></strong></td>
        <td><?= (int)$p['vote_count'] ?></td>
        <td><?= $p['start_date'] ? h(formatCzechDate($p['start_date'])) : '–' ?></td>
        <td><?= $p['end_date'] ? h(formatCzechDate($p['end_date'])) : '–' ?></td>
        <td class="actions">
          <a href="polls_form.php?id=<?= (int)$p['id'] ?>" class="btn">Upravit</a>
          <form action="polls_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat anketu včetně všech hlasů?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
