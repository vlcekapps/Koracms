<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$pdo = db_connect();
$allBlogs = (currentUserHasCapability('blog_taxonomies_manage') || currentUserHasCapability('settings_manage'))
    ? getAllBlogs()
    : getTaxonomyManagedBlogsForUser();

if ($allBlogs === []) {
    requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu týmů blogů nemáte potřebné oprávnění.');
}

$accessibleBlogIds = array_map(static fn(array $blogRow): int => (int)$blogRow['id'], $allBlogs);
$blogId = inputInt('get', 'blog_id') ?? inputInt('post', 'blog_id') ?? (int)($allBlogs[0]['id'] ?? 0);
if (!in_array($blogId, $accessibleBlogIds, true)) {
    $blogId = (int)($allBlogs[0]['id'] ?? 0);
}

$currentBlog = getBlogById($blogId) ?? ($allBlogs[0] ?? null);
$blogId = (int)($currentBlog['id'] ?? 0);
$success = '';
$error = '';
$roleOptions = blogMembershipRoleDefinitions();

$eligibleUsersStmt = $pdo->query(
    "SELECT id, email, first_name, last_name, nickname, role, is_superadmin
     FROM cms_users
     WHERE role != 'public'
     ORDER BY COALESCE(NULLIF(nickname,''), NULLIF(TRIM(CONCAT(first_name,' ',last_name)),''), email)"
);
$eligibleUsers = $eligibleUsersStmt->fetchAll();
$allMembershipRows = [];
try {
    $allMembershipStmt = $pdo->query(
        "SELECT bm.user_id, bm.blog_id, bm.member_role, b.name AS blog_name
         FROM cms_blog_members bm
         INNER JOIN cms_blogs b ON b.id = bm.blog_id
         ORDER BY b.name ASC, bm.member_role ASC"
    );
    $allMembershipRows = $allMembershipStmt->fetchAll();
} catch (\PDOException $e) {
    $allMembershipRows = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if ($blogId <= 0 || !getBlogById($blogId)) {
        $error = 'Vybraný blog nebyl nalezen.';
    } elseif (!canCurrentUserManageBlogTaxonomies($blogId)) {
        $error = 'Tým tohoto blogu nemůžete upravovat.';
    } else {
        $pdo->prepare("DELETE FROM cms_blog_members WHERE blog_id = ?")->execute([$blogId]);

        $insertMember = $pdo->prepare(
            "INSERT INTO cms_blog_members (blog_id, user_id, member_role)
             VALUES (?, ?, ?)"
        );

        foreach ($eligibleUsers as $eligibleUser) {
            $userId = (int)($eligibleUser['id'] ?? 0);
            if ($userId <= 0 || !isset($_POST['member_' . $userId])) {
                continue;
            }

            $memberRole = (string)($_POST['member_role_' . $userId] ?? 'author');
            if (!isset($roleOptions[$memberRole])) {
                $memberRole = 'author';
            }

            $insertMember->execute([$blogId, $userId, $memberRole]);
        }

        clearBlogCache();
        logAction('blog_members_save', 'blog_id=' . $blogId);
        $success = 'Tým blogu byl uložen.';
    }
}

$memberMap = [];
foreach (getBlogMembers($blogId) as $memberRow) {
    $memberMap[(int)($memberRow['user_id'] ?? 0)] = (string)($memberRow['member_role'] ?? 'author');
}
$allMembershipsByUser = [];
foreach ($allMembershipRows as $membershipRow) {
    $membershipUserId = (int)($membershipRow['user_id'] ?? 0);
    if ($membershipUserId <= 0) {
        continue;
    }
    $allMembershipsByUser[$membershipUserId][] = [
        'blog_id' => (int)($membershipRow['blog_id'] ?? 0),
        'blog_name' => (string)($membershipRow['blog_name'] ?? ''),
        'member_role' => (string)($membershipRow['member_role'] ?? 'author'),
    ];
}

$displayName = static function (array $userRow): string {
    $nickname = trim((string)($userRow['nickname'] ?? ''));
    if ($nickname !== '') {
        return $nickname;
    }

    $fullName = trim((string)($userRow['first_name'] ?? '') . ' ' . (string)($userRow['last_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    return (string)($userRow['email'] ?? '');
};
$roleLabel = static fn(string $roleKey): string => $roleOptions[$roleKey] ?? 'Autor blogu';

usort($eligibleUsers, static function (array $leftUser, array $rightUser) use ($memberMap, $displayName): int {
    $leftAssigned = isset($memberMap[(int)($leftUser['id'] ?? 0)]) ? 0 : 1;
    $rightAssigned = isset($memberMap[(int)($rightUser['id'] ?? 0)]) ? 0 : 1;
    if ($leftAssigned !== $rightAssigned) {
        return $leftAssigned <=> $rightAssigned;
    }

    return strcasecmp($displayName($leftUser), $displayName($rightUser));
});

$assignedCount = count($memberMap);
$managerCount = count(array_filter($memberMap, static fn(string $memberRole): bool => $memberRole === 'manager'));
$authorCount = count(array_filter($memberMap, static fn(string $memberRole): bool => $memberRole === 'author'));

adminHeader('Tým blogu' . ($currentBlog ? ' – ' . (string)$currentBlog['name'] : ''));
?>
<?php if ($success !== ''): ?><p class="success" role="status"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="error" role="alert"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <?php if (currentUserHasCapability('blog_taxonomies_manage') || currentUserHasCapability('settings_manage')): ?>
    <a href="blogs.php"><span aria-hidden="true">←</span> Zpět na správu blogů</a>
  <?php else: ?>
    <a href="blog.php?blog=<?= $blogId ?>"><span aria-hidden="true">←</span> Zpět na články blogu</a>
  <?php endif; ?>
  <a href="blog.php?blog=<?= $blogId ?>">Články blogu</a>
  <a href="blog_cats.php?blog_id=<?= $blogId ?>">Kategorie blogu</a>
  <a href="blog_tags.php?blog_id=<?= $blogId ?>">Štítky blogu</a>
  <?php if ($currentBlog): ?>
    <a href="<?= h(blogIndexPath($currentBlog)) ?>" target="_blank" rel="noopener">Zobrazit blog na webu</a>
    <a href="<?= h(blogFeedPath($currentBlog)) ?>" target="_blank" rel="noopener">RSS feed blogu</a>
  <?php endif; ?>
</p>

<?php if (count($allBlogs) > 1): ?>
  <form method="get" style="margin-bottom:1rem;display:flex;gap:.5rem;align-items:center">
    <label for="blog_id">Blog:</label>
    <select id="blog_id" name="blog_id" style="min-width:15rem">
      <?php foreach ($allBlogs as $blogRow): ?>
        <option value="<?= (int)$blogRow['id'] ?>"<?= (int)$blogRow['id'] === $blogId ? ' selected' : '' ?>><?= h((string)$blogRow['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn">Zobrazit</button>
  </form>
<?php endif; ?>

<?php if ($currentBlog): ?>
  <p class="field-help">
    Přehled pro <strong><?= h((string)$currentBlog['name']) ?></strong>:
    přiřazeno <?= $assignedCount ?> <?= $assignedCount === 1 ? 'uživatel' : (($assignedCount >= 2 && $assignedCount <= 4) ? 'uživatelé' : 'uživatelů') ?>,
    z toho <?= $managerCount ?> správc<?= $managerCount === 1 ? 'e' : 'ů' ?> blogu a
    <?= $authorCount ?> autor<?= $authorCount === 1 ? '' : (($authorCount >= 2 && $authorCount <= 4) ? 'i' : 'ů') ?>.
  </p>
<?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="blog_id" value="<?= $blogId ?>">

  <fieldset>
    <legend>Tým blogu</legend>
    <p class="field-help">
      Jakmile u blogů začnete používat přiřazení, autoři uvidí a upraví jen své přidělené blogy.
      Role <strong>Správce blogu</strong> navíc umožňuje spravovat kategorie a štítky tohoto blogu.
    </p>

    <?php if ($eligibleUsers === []): ?>
      <p>Zatím tu nejsou žádní interní uživatelé, které by šlo do blogu přiřadit.</p>
    <?php else: ?>
      <table>
        <caption>Tým blogu</caption>
        <thead>
          <tr>
            <th scope="col">Uživatel</th>
            <th scope="col">Systémová role</th>
            <th scope="col">E-mail</th>
            <th scope="col">Další blogy uživatele</th>
            <th scope="col">Zařadit do blogu</th>
            <th scope="col">Role v blogu</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($eligibleUsers as $eligibleUser): ?>
            <?php
            $userId = (int)($eligibleUser['id'] ?? 0);
            $assignedRole = $memberMap[$userId] ?? '';
            $isAssigned = $assignedRole !== '';
            $otherMemberships = array_values(array_filter(
                $allMembershipsByUser[$userId] ?? [],
                static fn(array $membership): bool => (int)($membership['blog_id'] ?? 0) !== $blogId
            ));
            ?>
            <tr>
              <td>
                <?= h($displayName($eligibleUser)) ?>
                <?php if ((int)($eligibleUser['is_superadmin'] ?? 0) === 1): ?>
                  <small class="field-help">(hlavní administrátor)</small>
                <?php endif; ?>
              </td>
              <td><?= h((string)($eligibleUser['role'] ?? '')) ?></td>
              <td><?= h((string)($eligibleUser['email'] ?? '')) ?></td>
              <td>
                <?php if ($otherMemberships === []): ?>
                  <small class="field-help">Jen tento blog nebo zatím bez dalších přiřazení.</small>
                <?php else: ?>
                  <ul style="margin:0;padding-left:1rem">
                    <?php foreach ($otherMemberships as $membership): ?>
                      <li>
                        <?= h((string)$membership['blog_name']) ?>
                        <small class="field-help">(<?= h($roleLabel((string)$membership['member_role'])) ?>)</small>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </td>
              <td>
                <label for="member-<?= $userId ?>" class="visually-hidden">Zařadit uživatele do blogu</label>
                <input type="checkbox" id="member-<?= $userId ?>" name="member_<?= $userId ?>" value="1"<?= $isAssigned ? ' checked' : '' ?>>
              </td>
              <td>
                <label for="member-role-<?= $userId ?>" class="visually-hidden">Role uživatele v blogu</label>
                <select id="member-role-<?= $userId ?>" name="member_role_<?= $userId ?>">
                  <?php foreach ($roleOptions as $roleKey => $roleLabel): ?>
                    <option value="<?= h($roleKey) ?>"<?= ($assignedRole !== '' ? $assignedRole : 'author') === $roleKey ? ' selected' : '' ?>><?= h($roleLabel) ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <div class="button-row" style="margin-top:1rem">
      <button type="submit" class="btn">Uložit tým blogu</button>
    </div>
  </fieldset>
</form>

<?php adminFooter(); ?>
