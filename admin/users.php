<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$publicRegistrationEnabled = publicRegistrationEnabled();
$canCreateUsers = $publicRegistrationEnabled || isSuperAdmin();
$accounts = $pdo->query(
    "SELECT id, email, first_name, last_name, nickname, role, is_superadmin, is_confirmed,
            author_public_enabled, author_slug, created_at
     FROM cms_users ORDER BY is_superadmin DESC, created_at ASC, id ASC"
)->fetchAll();
$blogMembershipRows = [];
try {
    $blogMembershipRows = $pdo->query(
        "SELECT bm.user_id, bm.member_role, b.id AS blog_id, b.name AS blog_name
         FROM cms_blog_members bm
         INNER JOIN cms_blogs b ON b.id = bm.blog_id
         ORDER BY b.name ASC"
    )->fetchAll();
} catch (\PDOException $e) {
    $blogMembershipRows = [];
}
$blogMembershipsByUser = [];
foreach ($blogMembershipRows as $membershipRow) {
    $membershipUserId = (int)($membershipRow['user_id'] ?? 0);
    if ($membershipUserId <= 0) {
        continue;
    }
    $blogMembershipsByUser[$membershipUserId][] = [
        'blog_id' => (int)($membershipRow['blog_id'] ?? 0),
        'blog_name' => (string)($membershipRow['blog_name'] ?? ''),
        'member_role' => (string)($membershipRow['member_role'] ?? 'author'),
    ];
}
$blogRoleLabels = blogMembershipRoleDefinitions();

adminHeader('Uživatelé a role');
?>

<?php if (!$publicRegistrationEnabled): ?>
  <p class="notice" role="status">Veřejná registrace je vypnutá. Nové účty může ručně přidávat jen hlavní administrátor.</p>
<?php endif; ?>

<?php if ($canCreateUsers): ?>
  <p><a href="user_form.php" class="btn">+ Přidat uživatele</a></p>
<?php endif; ?>

<table>
  <caption>Přehled uživatelů</caption>
  <thead>
    <tr>
      <th scope="col">E-mail</th>
      <th scope="col">Jméno / Přezdívka</th>
      <th scope="col">Role</th>
      <th scope="col">Blogy</th>
      <th scope="col">Stav</th>
      <th scope="col">Přidán</th>
      <th scope="col">Akce</th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($accounts as $account): ?>
    <?php $displayName = $account['nickname'] !== '' ? $account['nickname'] : trim($account['first_name'] . ' ' . $account['last_name']); ?>
    <?php $assignedBlogs = $blogMembershipsByUser[(int)$account['id']] ?? []; ?>
    <tr>
      <td><?= h($account['email']) ?></td>
      <td>
        <?= $displayName !== '' ? h($displayName) : '<em>–</em>' ?>
        <?php if ($account['role'] !== 'public' && (int)($account['author_public_enabled'] ?? 0) === 1 && (string)($account['author_slug'] ?? '') !== ''): ?>
          <div style="margin-top:.35rem">
            <small style="display:inline-flex;align-items:center;padding:.2rem .5rem;border-radius:999px;background:#edf5fc;color:#15486d;font-weight:700">
              Veřejný autor
            </small>
          </div>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($account['is_superadmin']): ?>
          <strong>Hlavní admin</strong>
        <?php else: ?>
          <?= h(userRoleLabel((string)$account['role'])) ?>
        <?php endif; ?>
      </td>
      <td>
        <?php if ($assignedBlogs === []): ?>
          <?php if ((string)$account['role'] === 'public'): ?>
            <small class="field-help">Veřejný účet</small>
          <?php else: ?>
            <small class="field-help">Bez přiřazení k blogům</small>
          <?php endif; ?>
        <?php else: ?>
          <ul style="margin:0;padding-left:1rem">
            <?php foreach ($assignedBlogs as $assignedBlog): ?>
              <li>
                <?= h((string)$assignedBlog['blog_name']) ?>
                <small class="field-help">(<?= h($blogRoleLabels[(string)$assignedBlog['member_role']] ?? 'Autor blogu') ?>)</small>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </td>
      <td><?= (int)$account['is_confirmed'] ? 'Aktivní' : '<em>Nepotvrzený</em>' ?></td>
      <td><?= h($account['created_at']) ?></td>
      <td class="actions">
        <?php if (!(int)$account['is_superadmin']): ?>
          <a href="user_form.php?id=<?= (int)$account['id'] ?>" class="btn">Upravit</a>
          <?php if (!empty($assignedBlogs) && currentUserHasCapability('blog_taxonomies_manage')): ?>
            <a href="blog_members.php?blog_id=<?= (int)$assignedBlogs[0]['blog_id'] ?>" class="btn">Blogy</a>
          <?php endif; ?>
          <form action="user_delete.php" method="post" style="display:inline">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <input type="hidden" name="id" value="<?= (int)$account['id'] ?>">
            <button type="submit" class="btn btn-danger"
                    data-confirm="Smazat uživatele <?= h(addslashes($account['email'])) ?>?">
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
