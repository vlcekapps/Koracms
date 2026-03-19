<?php
require_once __DIR__ . '/layout.php';
requireSuperAdmin();

$pdo   = db_connect();
$users = $pdo->query(
    "SELECT id, email, first_name, last_name, nickname, is_superadmin, created_at
     FROM cms_users ORDER BY is_superadmin DESC, created_at ASC"
)->fetchAll();

adminHeader('Správa uživatelů');
?>

<p><a href="user_form.php" class="btn">+ Přidat spolupracovníka</a></p>

<table>
  <thead>
    <tr>
      <th scope="col">E-mail</th>
      <th scope="col">Jméno / Přezdívka</th>
      <th scope="col">Role</th>
      <th scope="col">Přidán</th>
      <th scope="col">Akce</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($users as $u): ?>
    <?php
    $displayName = $u['nickname'] !== '' ? $u['nickname']
                 : trim($u['first_name'] . ' ' . $u['last_name']);
    ?>
    <tr>
      <td><?= h($u['email']) ?></td>
      <td><?= $displayName !== '' ? h($displayName) : '<em>–</em>' ?></td>
      <td><?= $u['is_superadmin'] ? '<strong>Hlavní admin</strong>' : 'Spolupracovník' ?></td>
      <td><?= h($u['created_at']) ?></td>
      <td class="actions">
        <?php if (!$u['is_superadmin']): ?>
          <a href="user_form.php?id=<?= (int)$u['id'] ?>" class="btn">Upravit</a>
          <form action="user_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    onclick="return confirm('Smazat spolupracovníka <?= h(addslashes($u['email'])) ?>?')">
              Smazat
            </button>
          </form>
        <?php else: ?>
          <a href="profile.php" class="btn">Můj profil</a>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php adminFooter(); ?>
