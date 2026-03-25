<?php
require_once __DIR__ . '/layout.php';
requireCapability('newsletter_manage', 'Přístup odepřen. Pro správu historie newsletteru nemáte potřebné oprávnění.');

$pdo = db_connect();
$historyId = inputInt('get', 'id');
$redirect = internalRedirectTarget(trim($_GET['redirect'] ?? ''), BASE_URL . '/admin/newsletter.php');

if ($historyId === null) {
    header('Location: ' . $redirect);
    exit;
}

$stmt = $pdo->prepare(
    "SELECT id, subject, body, recipient_count, sent_at, created_at
     FROM cms_newsletters
     WHERE id = ?"
);
$stmt->execute([$historyId]);
$newsletter = $stmt->fetch();

if (!$newsletter) {
    header('Location: ' . $redirect);
    exit;
}

adminHeader('Odeslaná rozesílka');
?>

<?php if (trim($_GET['ok'] ?? '') === 'sent'): ?>
  <p class="success" role="status">Newsletter byl odeslán a uložen do historie.</p>
<?php endif; ?>

<p><a href="<?= h($redirect) ?>">&larr; Zpět na přehled newsletteru</a></p>

<table>
  <caption class="sr-only">Detail odeslaného newsletteru</caption>
  <tbody>
    <tr>
      <th scope="row">Předmět</th>
      <td><?= h((string)$newsletter['subject']) ?></td>
    </tr>
    <tr>
      <th scope="row">Odesláno</th>
      <td>
        <?php if (!empty($newsletter['sent_at'])): ?>
          <time datetime="<?= h(str_replace(' ', 'T', (string)$newsletter['sent_at'])) ?>">
            <?= formatCzechDate((string)$newsletter['sent_at']) ?>
          </time>
        <?php else: ?>
          <em>Neodesláno</em>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <th scope="row">Příjemců</th>
      <td><?= (int)$newsletter['recipient_count'] ?></td>
    </tr>
    <tr>
      <th scope="row">Vytvořeno</th>
      <td>
        <time datetime="<?= h(str_replace(' ', 'T', (string)$newsletter['created_at'])) ?>">
          <?= formatCzechDate((string)$newsletter['created_at']) ?>
        </time>
      </td>
    </tr>
    <tr>
      <th scope="row">Obsah</th>
      <td style="white-space:pre-wrap"><?= h((string)$newsletter['body']) ?></td>
    </tr>
  </tbody>
</table>

<?php adminFooter(); ?>
