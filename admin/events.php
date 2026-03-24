<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$events = db_connect()->query(
    "SELECT id, title, location, event_date, is_published,
            COALESCE(status,'published') AS status
     FROM cms_events ORDER BY event_date DESC"
)->fetchAll();

adminHeader('Události');
?>
<p><a href="event_form.php" class="btn">+ Přidat událost</a></p>

<?php if (empty($events)): ?>
  <p>Žádné události.</p>
<?php else: ?>
  <table>
    <caption>Události</caption>
    <thead>
      <tr>
        <th scope="col">Název</th>
        <th scope="col">Datum konání</th>
        <th scope="col">Místo</th>
        <th scope="col">Stav</th>
        <th scope="col">Akce</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($events as $e): ?>
      <tr>
        <td><?= h($e['title']) ?></td>
        <td><time datetime="<?= h(str_replace(' ', 'T', $e['event_date'])) ?>"><?= h($e['event_date']) ?></time></td>
        <td><?= h($e['location'] ?: '–') ?></td>
        <td>
          <?php if ($e['status'] === 'pending'): ?>
            <strong style="color:#c60"><span aria-hidden="true">⏳</span> Čeká na schválení</strong>
          <?php elseif ($e['is_published']): ?>
            Publikováno
          <?php else: ?>
            <strong>Skrytá</strong>
          <?php endif; ?>
        </td>
        <td class="actions">
          <a href="event_form.php?id=<?= (int)$e['id'] ?>" class="btn">Upravit</a>
          <?php if ($e['status'] === 'pending' && currentUserHasCapability('content_approve_shared')): ?>
            <form action="approve.php" method="post" style="display:inline">
              <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
              <input type="hidden" name="module" value="events">
              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
              <input type="hidden" name="redirect" value="<?= h(BASE_URL) ?>/admin/events.php">
              <button type="submit" class="btn" style="background:#060;color:#fff">Schválit</button>
            </form>
          <?php endif; ?>
          <form action="event_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat událost?')">Smazat</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>

<?php adminFooter(); ?>
