<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');
requireModuleEnabled('blog');

if (!hasAnyBlogs()) {
    header('Location: ' . BASE_URL . '/admin/blogs.php?msg=no_blog');
    exit;
}

$pdo = db_connect();
$canManageAllBlogTeams = currentUserHasCapability('blog_taxonomies_manage')
    || currentUserHasCapability('settings_manage');
$allBlogs = $canManageAllBlogTeams
    ? getAllBlogs()
    : getTaxonomyManagedBlogsForUser();

if ($allBlogs === []) {
    requireCapability('blog_taxonomies_manage', 'Přístup odepřen. Pro správu týmů blogů nemáte potřebné oprávnění.');
}

$accessibleBlogIds = array_map(static fn (array $blogRow): int => (int)$blogRow['id'], $allBlogs);
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
$requestedBlogId = $isPostRequest ? inputInt('post', 'blog_id') : inputInt('get', 'blog_id');
$requestedBlogIsAccessible = $requestedBlogId !== null && in_array($requestedBlogId, $accessibleBlogIds, true);
$blogId = $requestedBlogIsAccessible
    ? (int)$requestedBlogId
    : (int)($allBlogs[0]['id'] ?? 0);
$postBlogScopeValid = !$isPostRequest || $requestedBlogIsAccessible;

$currentBlog = getBlogById($blogId) ?? ($allBlogs[0] ?? null);
$blogId = (int)($currentBlog['id'] ?? 0);
$success = '';
$error = '';
$roleOptions = blogMembershipRoleDefinitions();
$membershipFingerprint = static function (array $memberMap): string {
    $normalizedMap = [];
    foreach ($memberMap as $memberUserId => $memberRole) {
        $memberUserId = (int)$memberUserId;
        if ($memberUserId > 0 && is_string($memberRole)) {
            $normalizedMap[$memberUserId] = $memberRole;
        }
    }
    ksort($normalizedMap, SORT_NUMERIC);
    $encodedMap = json_encode($normalizedMap, JSON_UNESCAPED_UNICODE);

    return hash('sha256', $encodedMap !== false ? $encodedMap : '');
};
$blogCreator = null;
$canAssignBlogCreator = $canManageAllBlogTeams;
$blogMemberFlash = $_SESSION['blog_members_flash'] ?? [];
unset($_SESSION['blog_members_flash']);
$creatorForm = [
    'creator_user_id' => '',
];
$creatorFieldErrors = [];
$teamFieldErrors = [];
$teamFormMemberMap = null;
$flashContext = '';
$creatorConfirmField = 'confirm_blog_creator_backfill';
$creatorReviewId = 'blog-creator-review';
$creatorConfirmErrorId = 'confirm-blog-creator-backfill-error';
$teamConfirmField = 'confirm_blog_members_save';
$teamReviewId = 'blog-team-review';
$teamConfirmErrorId = 'confirm-blog-members-save-error';

if (is_array($blogMemberFlash) && (int)($blogMemberFlash['blog_id'] ?? 0) === $blogId) {
    $success = is_string($blogMemberFlash['success'] ?? null) ? (string)$blogMemberFlash['success'] : '';
    $error = is_string($blogMemberFlash['error'] ?? null) ? (string)$blogMemberFlash['error'] : '';
    $flashContext = is_string($blogMemberFlash['context'] ?? null) ? (string)$blogMemberFlash['context'] : '';
    if ($flashContext === 'creator' && isset($blogMemberFlash['form']) && is_array($blogMemberFlash['form'])) {
        $creatorForm = array_merge($creatorForm, $blogMemberFlash['form']);
        $creatorFieldErrors = array_values(array_unique(array_filter((array)($blogMemberFlash['field_errors'] ?? []), 'is_string')));
    } elseif ($flashContext === 'team') {
        $teamFieldErrors = array_values(array_unique(array_filter((array)($blogMemberFlash['field_errors'] ?? []), 'is_string')));
        if (isset($blogMemberFlash['member_map']) && is_array($blogMemberFlash['member_map'])) {
            $teamFormMemberMap = [];
            foreach ($blogMemberFlash['member_map'] as $memberUserId => $memberRole) {
                $memberUserId = (int)$memberUserId;
                if ($memberUserId > 0 && is_string($memberRole)) {
                    $teamFormMemberMap[$memberUserId] = $memberRole;
                }
            }
        }
    }
}

if ($blogId > 0) {
    $blogCreatorStmt = $pdo->prepare(
        "SELECT b.created_by_user_id,
                u.email,
                COALESCE(NULLIF(u.nickname,''), NULLIF(TRIM(CONCAT(u.first_name,' ',u.last_name)),''), u.email, '') AS creator_label
         FROM cms_blogs b
         LEFT JOIN cms_users u ON u.id = b.created_by_user_id
         WHERE b.id = ?"
    );
    $blogCreatorStmt->execute([$blogId]);
    $blogCreator = $blogCreatorStmt->fetch() ?: null;
}

$eligibleUsersStmt = $pdo->query(
    "SELECT id, email, first_name, last_name, nickname, role, is_superadmin
     FROM cms_users
     WHERE role != 'public'
     ORDER BY COALESCE(NULLIF(nickname,''), NULLIF(TRIM(CONCAT(first_name,' ',last_name)),''), email)"
);
$eligibleUsers = $eligibleUsersStmt->fetchAll();
$eligibleUsersById = [];
foreach ($eligibleUsers as $eligibleUserRow) {
    $eligibleUserId = (int)($eligibleUserRow['id'] ?? 0);
    if ($eligibleUserId > 0) {
        $eligibleUsersById[$eligibleUserId] = $eligibleUserRow;
    }
}
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

if ($isPostRequest) {
    verifyCsrf();
    $postAction = trim((string)($_POST['action'] ?? 'save_team'));
    $redirectTarget = internalRedirectTarget(
        trim((string)($_POST['redirect'] ?? '')),
        BASE_URL . '/admin/blog_members.php?blog_id=' . $blogId
    );
    $redirectWithFlash = static function (array $flash) use ($redirectTarget, $blogId): void {
        $flash['blog_id'] = $blogId;
        $_SESSION['blog_members_flash'] = $flash;
        header('Location: ' . $redirectTarget);
        exit;
    };

    if (!$postBlogScopeValid) {
        $redirectWithFlash([
            'context' => $postAction === 'set_creator' ? 'creator' : 'team',
            'error' => 'Vybraný blog není v rozsahu vašich oprávnění. Otevřete tým znovu z dostupného blogu a akci zopakujte.',
        ]);
    }

    if ($postAction === 'set_creator') {
        $creatorForm['creator_user_id'] = (string)($_POST['creator_user_id'] ?? '');
        $selectedCreatorId = inputInt('post', 'creator_user_id') ?? 0;
        $confirmedCreatorBackfill = isset($_POST[$creatorConfirmField])
            && (string)$_POST[$creatorConfirmField] === '1';
        $flashError = '';

        if ($blogId <= 0) {
            $flashError = 'Vybraný blog nebyl nalezen.';
        } elseif (!$canAssignBlogCreator) {
            $flashError = 'Zakladatele blogu může doplnit jen globální správce blogů nebo nastavení.';
        } elseif ($selectedCreatorId <= 0 || !isset($eligibleUsersById[$selectedCreatorId])) {
            $flashError = 'Zakladatele nelze doplnit bez platného interního uživatele. U pole Zakladatel blogu je konkrétní nápověda.';
            $creatorFieldErrors[] = 'creator_user_id';
        } elseif (!$confirmedCreatorBackfill) {
            $flashError = 'Zakladatele nelze doplnit bez potvrzení kontroly nevratného auditního údaje. U pole Potvrzení zakladatele je konkrétní nápověda.';
            $creatorFieldErrors[] = $creatorConfirmField;
        }

        if ($flashError !== '') {
            $redirectWithFlash([
                'context' => 'creator',
                'error' => $flashError,
                'field_errors' => $creatorFieldErrors,
                'form' => $creatorForm,
            ]);
        }

        try {
            $pdo->beginTransaction();
            $blogCreatorCheckStmt = $pdo->prepare(
                "SELECT created_by_user_id
                 FROM cms_blogs
                 WHERE id = ?
                 FOR UPDATE"
            );
            $blogCreatorCheckStmt->execute([$blogId]);
            $blogCreatorCheck = $blogCreatorCheckStmt->fetch();
            if (!$blogCreatorCheck) {
                $pdo->rollBack();
                $redirectWithFlash([
                    'context' => 'creator',
                    'error' => 'Vybraný blog už není dostupný. Otevřete správu týmů znovu.',
                    'form' => $creatorForm,
                ]);
            }
            if (!empty($blogCreatorCheck['created_by_user_id'])) {
                $pdo->rollBack();
                $redirectWithFlash([
                    'context' => 'creator',
                    'error' => 'Zakladatel už je u tohoto blogu evidovaný. Zkontrolujte aktuální údaj před další akcí.',
                    'form' => $creatorForm,
                ]);
            }

            $updateCreatorStmt = $pdo->prepare(
                "UPDATE cms_blogs
                 SET created_by_user_id = ?
                 WHERE id = ? AND created_by_user_id IS NULL"
            );
            $updateCreatorStmt->execute([$selectedCreatorId, $blogId]);
            if ($updateCreatorStmt->rowCount() !== 1) {
                throw new RuntimeException('Zakladatele se nepodařilo doplnit.');
            }

            logAction('blog_creator_backfill', 'blog_id=' . $blogId . ';creator_user_id=' . $selectedCreatorId);
            $pdo->commit();
            clearBlogCache();
            $redirectWithFlash([
                'context' => 'creator',
                'success' => 'Zakladatel blogu byl doplněn.',
            ]);
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            koraLog('warning', 'blog creator backfill failed', [
                'operation' => 'blog_creator_backfill',
                'blog_id' => $blogId,
                'creator_user_id' => $selectedCreatorId,
                'exception' => $e,
            ]);
            $redirectWithFlash([
                'context' => 'creator',
                'error' => 'Zakladatele se nepodařilo doplnit. Údaj zůstal beze změny; zkontrolujte výběr a zkuste akci znovu.',
                'form' => $creatorForm,
            ]);
        }
    }

    if ($postAction !== 'save_team') {
        $redirectWithFlash([
            'context' => 'team',
            'error' => 'Požadovaná akce správy týmu není platná. Otevřete formulář znovu.',
        ]);
    }

    $submittedMemberMap = [];
    foreach ($eligibleUsers as $eligibleUser) {
        $memberUserId = (int)($eligibleUser['id'] ?? 0);
        if ($memberUserId <= 0 || !isset($_POST['member_' . $memberUserId])) {
            continue;
        }

        $memberRoleField = 'member_role_' . $memberUserId;
        $memberRole = trim((string)($_POST[$memberRoleField] ?? ''));
        $submittedMemberMap[$memberUserId] = $memberRole;
        if (!isset($roleOptions[$memberRole])) {
            $teamFieldErrors[] = $memberRoleField;
        }
    }

    $confirmedTeamSave = isset($_POST[$teamConfirmField])
        && (string)$_POST[$teamConfirmField] === '1';
    if (!$confirmedTeamSave) {
        $teamFieldErrors[] = $teamConfirmField;
    }
    $teamFieldErrors = array_values(array_unique($teamFieldErrors));
    if ($teamFieldErrors !== []) {
        $hasRoleError = count(array_filter(
            $teamFieldErrors,
            static fn (string $fieldName): bool => str_starts_with($fieldName, 'member_role_')
        )) > 0;
        $redirectWithFlash([
            'context' => 'team',
            'error' => $hasRoleError
                ? 'Tým blogu nejde uložit, protože některá přiřazená role není platná. Opravte označenou roli a potvrďte kontrolu přístupů znovu.'
                : 'Tým blogu nejde uložit bez potvrzení kontroly přístupů a rolí. U pole Potvrzení změny týmu je konkrétní nápověda.',
            'field_errors' => $teamFieldErrors,
            'member_map' => $submittedMemberMap,
        ]);
    }

    $postedMembershipSnapshot = trim((string)($_POST['team_membership_snapshot'] ?? ''));
    if (preg_match('/\A[a-f0-9]{64}\z/', $postedMembershipSnapshot) !== 1) {
        $redirectWithFlash([
            'context' => 'team',
            'error' => 'Kontrolní stav týmu chybí nebo není platný. Zkontrolujte aktuální přiřazení a potvrďte změnu znovu.',
            'field_errors' => [$teamConfirmField],
            'member_map' => $submittedMemberMap,
        ]);
    }

    try {
        $pdo->beginTransaction();
        $blogLockStmt = $pdo->prepare("SELECT id FROM cms_blogs WHERE id = ? FOR UPDATE");
        $blogLockStmt->execute([$blogId]);
        if ((int)$blogLockStmt->fetchColumn() !== $blogId) {
            $pdo->rollBack();
            $redirectWithFlash([
                'context' => 'team',
                'error' => 'Vybraný blog už není dostupný. Tým zůstal beze změny.',
                'member_map' => $submittedMemberMap,
            ]);
        }

        if (!$canManageAllBlogTeams) {
            $managerScopeStmt = $pdo->prepare(
                "SELECT member_role
                 FROM cms_blog_members
                 WHERE blog_id = ? AND user_id = ?
                 FOR UPDATE"
            );
            $managerScopeStmt->execute([$blogId, currentUserId()]);
            if ((string)$managerScopeStmt->fetchColumn() !== 'manager') {
                $pdo->rollBack();
                $redirectWithFlash([
                    'context' => 'team',
                    'error' => 'Oprávnění ke správě týmu se mezitím změnilo. Tým zůstal beze změny.',
                    'member_map' => $submittedMemberMap,
                ]);
            }
        }

        $currentMembersStmt = $pdo->prepare(
            "SELECT user_id, member_role
             FROM cms_blog_members
             WHERE blog_id = ?
             ORDER BY user_id
             FOR UPDATE"
        );
        $currentMembersStmt->execute([$blogId]);
        $currentMemberMap = [];
        foreach ($currentMembersStmt->fetchAll() as $currentMemberRow) {
            $currentMemberMap[(int)$currentMemberRow['user_id']] = (string)$currentMemberRow['member_role'];
        }

        if (!hash_equals($membershipFingerprint($currentMemberMap), $postedMembershipSnapshot)) {
            $pdo->rollBack();
            $redirectWithFlash([
                'context' => 'team',
                'error' => 'Tým blogu byl mezitím změněn v jiném požadavku. Zkontrolujte aktuální přiřazení a potvrďte svou změnu znovu.',
                'field_errors' => [$teamConfirmField],
                'member_map' => $submittedMemberMap,
            ]);
        }

        $addedCount = count(array_diff_key($submittedMemberMap, $currentMemberMap));
        $removedCount = count(array_diff_key($currentMemberMap, $submittedMemberMap));
        $roleChangeCount = 0;
        foreach ($submittedMemberMap as $memberUserId => $memberRole) {
            if (isset($currentMemberMap[$memberUserId]) && $currentMemberMap[$memberUserId] !== $memberRole) {
                $roleChangeCount++;
            }
        }

        $pdo->prepare("DELETE FROM cms_blog_members WHERE blog_id = ?")->execute([$blogId]);
        $insertMember = $pdo->prepare(
            "INSERT INTO cms_blog_members (blog_id, user_id, member_role)
             VALUES (?, ?, ?)"
        );
        foreach ($submittedMemberMap as $memberUserId => $memberRole) {
            $insertMember->execute([$blogId, $memberUserId, $memberRole]);
        }

        logAction(
            'blog_members_save',
            'blog_id=' . $blogId
            . ';member_count=' . count($submittedMemberMap)
            . ';added=' . $addedCount
            . ';removed=' . $removedCount
            . ';role_changed=' . $roleChangeCount
        );
        $pdo->commit();
        clearBlogCache();
        $redirectWithFlash([
            'context' => 'team',
            'success' => 'Tým blogu byl uložen.',
        ]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        koraLog('warning', 'blog team save failed', [
            'operation' => 'blog_members_save',
            'blog_id' => $blogId,
            'member_count' => count($submittedMemberMap),
            'exception' => $e,
        ]);
        $redirectWithFlash([
            'context' => 'team',
            'error' => 'Tým blogu se nepodařilo uložit. Původní přiřazení zůstala beze změny; zkontrolujte výběr a zkuste akci znovu.',
            'member_map' => $submittedMemberMap,
        ]);
    }
}

$storedMemberMap = [];
foreach (getBlogMembers($blogId) as $memberRow) {
    $storedMemberMap[(int)($memberRow['user_id'] ?? 0)] = (string)($memberRow['member_role'] ?? 'author');
}
$memberMap = is_array($teamFormMemberMap) ? $teamFormMemberMap : $storedMemberMap;
$teamMembershipSnapshot = $membershipFingerprint($storedMemberMap);
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
$roleLabel = static fn (string $roleKey): string => $roleOptions[$roleKey] ?? 'Autor blogu';

usort($eligibleUsers, static function (array $leftUser, array $rightUser) use ($memberMap, $displayName): int {
    $leftAssigned = isset($memberMap[(int)($leftUser['id'] ?? 0)]) ? 0 : 1;
    $rightAssigned = isset($memberMap[(int)($rightUser['id'] ?? 0)]) ? 0 : 1;
    if ($leftAssigned !== $rightAssigned) {
        return $leftAssigned <=> $rightAssigned;
    }

    return strcasecmp($displayName($leftUser), $displayName($rightUser));
});

$assignedCount = count($storedMemberMap);
$managerCount = count(array_filter($storedMemberMap, static fn (string $memberRole): bool => $memberRole === 'manager'));
$authorCount = count(array_filter($storedMemberMap, static fn (string $memberRole): bool => $memberRole === 'author'));
$errorElementId = match ($flashContext) {
    'creator' => 'blog-creator-form-error',
    'team' => 'blog-team-form-error',
    default => 'blog-members-page-error',
};
$creatorHasFormError = $error !== '' && $flashContext === 'creator';
$teamHasFormError = $error !== '' && $flashContext === 'team';

adminHeader('Tým blogu' . ($currentBlog ? ' – ' . (string)$currentBlog['name'] : ''));
?>
<?php if ($success !== ''): ?><p class="success" role="status" aria-atomic="true"><?= h($success) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p id="<?= h($errorElementId) ?>" class="error" role="alert" aria-atomic="true"><?= h($error) ?></p><?php endif; ?>

<p class="button-row button-row--start">
  <?php if ($canManageAllBlogTeams): ?>
    <a href="blogs.php"><span aria-hidden="true">←</span> Zpět na správu blogů</a>
  <?php else: ?>
    <a href="blog.php?blog=<?= $blogId ?>"><span aria-hidden="true">←</span> Zpět na články blogu</a>
  <?php endif; ?>
  <a href="blog.php?blog=<?= $blogId ?>">Články blogu</a>
  <a href="blog_cats.php?blog_id=<?= $blogId ?>">Kategorie blogu</a>
  <a href="blog_tags.php?blog_id=<?= $blogId ?>">Štítky blogu</a>
  <?php if ($currentBlog): ?>
    <a href="<?= h(blogIndexPath($currentBlog)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit blog na webu<?= newWindowLinkSrOnlySuffix() ?></a>
    <a href="<?= h(blogFeedPath($currentBlog)) ?>" target="_blank" rel="noopener noreferrer">RSS feed blogu<?= newWindowLinkSrOnlySuffix() ?></a>
  <?php endif; ?>
</p>

<?php if (count($allBlogs) > 1): ?>
  <form method="get" class="button-row admin-stack-sm">
    <label for="blog_id" class="admin-inline-label">Blog:</label>
    <select id="blog_id" name="blog_id" class="admin-select-lg">
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
  <p class="field-help">
    Zakladatel blogu:
    <?php if (!empty($blogCreator['created_by_user_id'])): ?>
      <?php $creatorLabel = trim((string)($blogCreator['creator_label'] ?? '')) !== '' ? (string)$blogCreator['creator_label'] : ('Uživatel #' . (int)$blogCreator['created_by_user_id']); ?>
      <strong><?= h($creatorLabel) ?></strong>
      <?php if (!empty($blogCreator['email']) && (string)$blogCreator['email'] !== $creatorLabel): ?>
        (<?= h((string)$blogCreator['email']) ?>)
      <?php endif; ?>
      <?php if (!isset($storedMemberMap[(int)$blogCreator['created_by_user_id']])): ?>
        <br><span>Zakladatel zatím není přiřazený v týmu blogu. Pokud má mít přístup i jako člen týmu, přiřaďte ho níže samostatně.</span>
      <?php endif; ?>
    <?php else: ?>
      <?php if ($canAssignBlogCreator): ?>
        <span>Neevidován. U starších blogů ho můžete doplnit níže jako jednorázový auditní údaj.</span>
      <?php else: ?>
        <span>Neevidován. Doplnit ho může jen globální správce blogů nebo nastavení.</span>
      <?php endif; ?>
    <?php endif; ?>
  </p>

  <?php if (empty($blogCreator['created_by_user_id']) && $canAssignBlogCreator): ?>
    <form id="blog-creator-form" method="post" novalidate class="admin-action-row"<?= $creatorHasFormError ? ' aria-describedby="blog-creator-form-error"' : '' ?>>
      <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
      <input type="hidden" name="action" value="set_creator">
      <input type="hidden" name="blog_id" value="<?= $blogId ?>">
      <input type="hidden" name="redirect" value="<?= h(BASE_URL . '/admin/blog_members.php?blog_id=' . $blogId) ?>">
      <fieldset>
        <legend>Doplnit zakladatele</legend>
        <p id="<?= h($creatorReviewId) ?>" class="field-help">
          Tato volba slouží jen pro starší blogy bez evidovaného zakladatele. Uložením vznikne trvalý auditní údaj, který v administraci nepůjde změnit; automaticky se tím nemění členství ani přístup vybraného uživatele.
        </p>
        <label for="creator_user_id">Zakladatel blogu</label>
        <select
          id="creator_user_id"
          name="creator_user_id"
          <?= adminFieldAttributes('creator_user_id', $creatorFieldErrors, [], ['creator-user-help']) ?>
        >
          <option value="">Vyberte interního uživatele</option>
          <?php foreach ($eligibleUsers as $eligibleUser): ?>
            <?php $creatorUserId = (int)($eligibleUser['id'] ?? 0); ?>
            <option value="<?= $creatorUserId ?>"<?= (string)$creatorUserId === (string)($creatorForm['creator_user_id'] ?? '') ? ' selected' : '' ?>>
              <?= h($displayName($eligibleUser)) ?> (<?= h((string)($eligibleUser['email'] ?? '')) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <small id="creator-user-help" class="field-help">Vybrat můžete jen interní účet. Doplnění zakladatele automaticky nepřidá uživatele do týmu blogu.</small>
        <?php adminRenderFieldError('creator_user_id', $creatorFieldErrors, [], 'Vyberte prosím interního uživatele, kterého chcete evidovat jako zakladatele.'); ?>

        <label for="confirm-blog-creator-backfill" class="admin-checkbox-label">
          <input
            type="checkbox"
            id="confirm-blog-creator-backfill"
            name="<?= h($creatorConfirmField) ?>"
            value="1"
            required
            aria-required="true"<?= adminFieldAttributes($creatorConfirmField, $creatorFieldErrors, [], [$creatorReviewId], $creatorConfirmErrorId) ?>
          >
          Potvrzuji, že jsem zkontroloval vybraného zakladatele a nevratnost auditního údaje.
        </label>
        <?php adminRenderFieldError($creatorConfirmField, $creatorFieldErrors, [], 'Před uložením potvrďte, že jste zkontrolovali vybraného uživatele a rozumíte tomu, že údaj v administraci nepůjde změnit.', $creatorConfirmErrorId); ?>

        <div class="button-row admin-action-row">
          <button type="submit" class="btn">Potvrdit a uložit zakladatele</button>
        </div>
      </fieldset>
    </form>
  <?php endif; ?>
<?php endif; ?>

<form id="blog-team-form" method="post" novalidate<?= $teamHasFormError ? ' aria-describedby="blog-team-form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="blog_id" value="<?= $blogId ?>">
  <input type="hidden" name="action" value="save_team">
  <input type="hidden" name="redirect" value="<?= h(BASE_URL . '/admin/blog_members.php?blog_id=' . $blogId) ?>">
  <input type="hidden" name="team_membership_snapshot" value="<?= h($teamMembershipSnapshot) ?>">

  <fieldset>
    <legend>Tým blogu</legend>
    <p id="<?= h($teamReviewId) ?>" class="field-help">
      Uložení okamžitě přidá nebo odebere přístup k tomuto blogu a může změnit oprávnění jeho členů. Před potvrzením zkontrolujte u každého uživatele zařazení i roli; články ani účet uživatele se touto akcí nemažou. Role <strong>Správce blogu</strong> navíc umožňuje spravovat tým, kategorie a štítky tohoto blogu.
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
              $eligibleUserDisplayName = $displayName($eligibleUser);
              $assignedRole = $memberMap[$userId] ?? '';
              $isAssigned = $assignedRole !== '';
              $memberRoleField = 'member_role_' . $userId;
              $memberRoleErrorId = 'member-role-' . $userId . '-error';
              $otherMemberships = array_values(array_filter(
                  $allMembershipsByUser[$userId] ?? [],
                  static fn (array $membership): bool => (int)$membership['blog_id'] !== $blogId
              ));
              ?>
            <tr>
              <td>
                <?= h($eligibleUserDisplayName) ?>
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
                  <ul class="table-list-compact">
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
                <label for="member-<?= $userId ?>" class="visually-hidden">Zařadit uživatele <?= h($eligibleUserDisplayName) ?> do blogu</label>
                <input type="checkbox" id="member-<?= $userId ?>" name="member_<?= $userId ?>" value="1"<?= $isAssigned ? ' checked' : '' ?>>
              </td>
              <td>
                <label for="member-role-<?= $userId ?>" class="visually-hidden">Role uživatele <?= h($eligibleUserDisplayName) ?> v blogu</label>
                <select id="member-role-<?= $userId ?>" name="<?= h($memberRoleField) ?>"<?= adminFieldAttributes($memberRoleField, $teamFieldErrors, [], [], $memberRoleErrorId) ?>>
                  <?php foreach ($roleOptions as $roleKey => $roleOptionLabel): ?>
                    <option value="<?= h($roleKey) ?>"<?= ($assignedRole !== '' ? $assignedRole : 'author') === $roleKey ? ' selected' : '' ?>><?= h($roleOptionLabel) ?></option>
                  <?php endforeach; ?>
                </select>
                <?php adminRenderFieldError($memberRoleField, $teamFieldErrors, [], 'Vyberte roli Autor blogu nebo Správce blogu.', $memberRoleErrorId); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>

    <label for="confirm-blog-members-save" class="admin-checkbox-label">
      <input
        type="checkbox"
        id="confirm-blog-members-save"
        name="<?= h($teamConfirmField) ?>"
        value="1"
        required
        aria-required="true"<?= adminFieldAttributes($teamConfirmField, $teamFieldErrors, [], [$teamReviewId], $teamConfirmErrorId) ?>
      >
      Potvrzuji, že jsem zkontroloval přidávané a odebírané přístupy i role členů tohoto blogu.
    </label>
    <?php adminRenderFieldError($teamConfirmField, $teamFieldErrors, [], 'Před uložením potvrďte, že jste zkontrolovali všechny změny přístupů a rolí v tomto blogu.', $teamConfirmErrorId); ?>

    <div class="button-row admin-action-row">
      <button type="submit" class="btn">Potvrdit a uložit tým</button>
    </div>
  </fieldset>
</form>

<?php adminFooter(); ?>
