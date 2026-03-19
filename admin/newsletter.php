<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();

$subscribers = $pdo->query(
    "SELECT id, email, confirmed, created_at FROM cms_subscribers ORDER BY created_at DESC"
)->fetchAll();

$confirmedCount = (int)$pdo->query(
    "SELECT COUNT(*) FROM cms_subscribers WHERE confirmed = 1"
)->fetchColumn();

$newsletters = $pdo->query(
    "SELECT id, subject, sent_at, recipient_count, created_at
     FROM cms_newsletters ORDER BY created_at DESC LIMIT 20"
)->fetchAll();

adminHeader('Newsletter');
?>

<div style="display:flex;gap:2rem;flex-wrap:wrap;margin-bottom:1.5rem">
  <div>
    <strong><?= $confirmedCount ?></strong> potvrzených odběratelů
    z <?= count($subscribers) ?> celkem
  </div>
  <a href="newsletter_form.php" class="btn">+ Napsat newsletter</a>
</div>

<h2>Odeslané newslettery</h2>
<?php if (empty($newsletters)): ?>
  <p>Zatím žádné newslettery.</p>
<?php else: ?>
  <table>
    <thead>
      <tr>
        <th scope="col">Předmět</th>
        <th scope="col">Odesláno</th>
        <th scope="col">Příjemců</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($newsletters as $n): ?>
      <tr>
        <td><?= h($n['subject']) ?></td>
        <td><?= $n['sent_at'] ? formatCzechDate($n['sent_at']) : '<em>Neodesláno</em>' ?></td>
        <td><?= (int)$n['recipient_count'] ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<h2>Odběratelé</h2>
<?php if (empty($subscribers)): ?>
  <p>Žádní odběratelé.</p>
<?php else: ?>
  <table>
    <caption>Odběratelé newsletteru</caption>
    <thead>
      <tr>
        <th scope="col">E-mail</th>
        <th scope="col">Stav</th>
        <th scope="col">Datum</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($subscribers as $s): ?>
      <tr>
        <td><?= h($s['email']) ?></td>
        <td><?= $s['confirmed'] ? 'Potvrzeno' : '<strong>Čeká na potvrzení</strong>' ?></td>
        <td><?= formatCzechDate($s['created_at']) ?></td>
        <td class="actions">
          <form action="newsletter_subscriber_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id"         value="<?= (int)$s['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat odběratele?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
