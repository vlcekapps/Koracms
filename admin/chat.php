<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo   = db_connect();
$items = $pdo->query(
    "SELECT id, name, email, web, message, created_at FROM cms_chat ORDER BY created_at DESC"
)->fetchAll();

adminHeader('Chat – zprávy');
?>
<?php if (empty($items)): ?>
  <p>Žádné zprávy.</p>
<?php else: ?>
<form method="post" action="chat_bulk.php" id="bulk-form">
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <table>
    <thead>
      <tr>
        <th scope="col"><input type="checkbox" id="check-all" aria-label="Vybrat vše"></th>
        <th scope="col">Jméno</th>
        <th scope="col">Zpráva</th>
        <th scope="col">Datum</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $m): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= (int)$m['id'] ?>" aria-label="Vybrat zprávu"></td>
        <td><?= h($m['name']) ?>
          <?php if ($m['email'] !== ''): ?>
            <br><a href="mailto:<?= h($m['email']) ?>"><?= h($m['email']) ?></a>
          <?php endif; ?>
        </td>
        <td><?= nl2br(h(mb_strimwidth($m['message'], 0, 120, '…'))) ?></td>
        <td><?= h(formatCzechDate($m['created_at'])) ?></td>
        <td class="actions">
          <form action="chat_delete.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat zprávu?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="margin-top:.75rem">
    <button type="submit" class="btn btn-danger"
            onclick="return confirm('Smazat vybrané zprávy?')">Smazat vybrané</button>
  </div>
</form>
<?php endif; ?>

<style>.visually-hidden{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}</style>
<script>
document.getElementById('check-all')?.addEventListener('change', function () {
    document.querySelectorAll('#bulk-form input[name="ids[]"]')
        .forEach(cb => cb.checked = this.checked);
});
</script>
<?php adminFooter(); ?>
