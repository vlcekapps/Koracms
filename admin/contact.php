<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo   = db_connect();

// Označit vše jako přečtené při zobrazení
$pdo->exec("UPDATE cms_contact SET is_read = 1 WHERE is_read = 0");

$items = $pdo->query(
    "SELECT id, sender_email, subject, message, is_read, created_at
     FROM cms_contact ORDER BY created_at DESC"
)->fetchAll();

adminHeader('Kontakt – přijaté zprávy');
?>
<?php if (empty($items)): ?>
  <p>Žádné zprávy.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th scope="col">Od</th>
        <th scope="col">Předmět</th>
        <th scope="col">Zpráva</th>
        <th scope="col">Datum</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $m): ?>
      <tr>
        <td><a href="mailto:<?= h($m['sender_email']) ?>"><?= h($m['sender_email']) ?></a></td>
        <td><?= h($m['subject']) ?></td>
        <td><?= nl2br(h($m['message'])) ?></td>
        <td><?= h(formatCzechDate($m['created_at'])) ?></td>
        <td class="actions">
          <form action="contact_delete.php" method="post">
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
<?php endif; ?>
<?php adminFooter(); ?>
