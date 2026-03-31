<?php
require_once __DIR__ . '/layout.php';
requireCapability('content_manage_shared', 'Přístup odepřen. Pro správu anket nemáte potřebné oprávnění.');

$pdo = db_connect();
$id = inputInt('get', 'id');
$backUrl = internalRedirectTarget(trim((string)($_GET['redirect'] ?? '')), BASE_URL . '/admin/polls.php');
$poll = null;
$options = [];

if ($id !== null) {
    $stmt = $pdo->prepare("SELECT * FROM cms_polls WHERE id = ?");
    $stmt->execute([$id]);
    $poll = $stmt->fetch() ?: null;
    if (!$poll) {
        header('Location: ' . $backUrl);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT o.*, (SELECT COUNT(*) FROM cms_poll_votes WHERE option_id = o.id) AS vote_count
         FROM cms_poll_options o
         WHERE o.poll_id = ?
         ORDER BY o.sort_order, o.id"
    );
    $stmt->execute([$id]);
    $options = $stmt->fetchAll();
}

$poll = $poll ?: [
    'question' => '',
    'slug' => '',
    'description' => '',
    'status' => 'active',
    'start_date' => null,
    'end_date' => null,
    'meta_title' => '',
    'meta_description' => '',
];
$poll = hydratePollPresentation($poll);

$totalVotes = 0;
if ($id !== null) {
    $stmtVotes = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ?");
    $stmtVotes->execute([$id]);
    $totalVotes = (int)$stmtVotes->fetchColumn();
}

$err = trim((string)($_GET['err'] ?? ''));
$formError = match ($err) {
    'required' => 'Vyplňte prosím otázku a alespoň 2 možnosti odpovědi.',
    'max_options' => 'Maximální počet možností je 10.',
    'has_votes' => 'Nelze odebrat možnosti, které už mají hlasy.',
    'slug' => 'Slug ankety je povinný a musí být unikátní.',
    'range' => 'Konec ankety musí být později než začátek.',
    'save' => 'Anketu se nepodařilo uložit. Zkuste to prosím znovu.',
    default => '',
};

adminHeader($id ? 'Upravit anketu' : 'Nová anketa');
?>

<?php if ($id !== null): ?>
  <p><a href="revisions.php?type=poll&amp;id=<?= (int)$id ?>">Historie revizí</a></p>
<?php endif; ?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error"><?= h($formError) ?></p>
<?php endif; ?>

<p style="margin-top:0;font-size:.9rem">
  Vyplňte otázku, možnosti a případné časové omezení. Pole označená <span aria-hidden="true">*</span><span class="sr-only">hvězdičkou</span> jsou povinná.
</p>

<p><a href="<?= h($backUrl) ?>"><span aria-hidden="true">←</span> Zpět na ankety</a></p>

<form method="post" action="polls_save.php" novalidate<?= $formError !== '' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <input type="hidden" name="redirect" value="<?= h($backUrl) ?>">
  <?php if ($id !== null): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
  <?php endif; ?>

  <fieldset>
    <legend>Anketa</legend>

    <label for="question">Otázka <span aria-hidden="true">*</span><span class="sr-only">(povinné)</span></label>
    <input
      type="text"
      id="question"
      name="question"
      required
      aria-required="true"
      maxlength="500"
      value="<?= h((string)$poll['question']) ?>"
    >

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input
      type="text"
      id="slug"
      name="slug"
      required
      aria-required="true"
      maxlength="255"
      pattern="[a-z0-9\-]+"
      aria-describedby="poll-slug-help"
      value="<?= h((string)$poll['slug']) ?>"
    >
    <small id="poll-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Používejte malá písmena, číslice a pomlčky.</small>

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="4" aria-describedby="poll-description-help"><?= h((string)($poll['description'] ?? '')) ?></textarea>
    <small id="poll-description-help" class="field-help">Volitelné. Krátké vysvětlení se zobrazí ve výpisu i na detailu ankety.</small>

    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="active"<?= (string)$poll['status'] === 'active' ? ' selected' : '' ?>>Aktivní</option>
      <option value="closed"<?= (string)$poll['status'] === 'closed' ? ' selected' : '' ?>>Uzavřená</option>
    </select>
  </fieldset>

  <fieldset style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Časové omezení</legend>
    <small id="poll-timing-help" class="field-help" style="margin-top:0">Vyplňte jen pokud má anketa začít nebo skončit v konkrétní čas.</small>
    <div style="display:flex;gap:1rem;align-items:flex-end;flex-wrap:wrap">
      <div>
        <label for="start_date">Začátek – datum</label>
        <input
          type="date"
          id="start_date"
          name="start_date"
          style="width:auto;display:block;margin-top:.2rem"
          aria-describedby="poll-timing-help"
          value="<?= !empty($poll['start_date']) ? h(date('Y-m-d', strtotime((string)$poll['start_date']))) : '' ?>"
        >
      </div>
      <div>
        <label for="start_time">Začátek – čas</label>
        <input
          type="time"
          id="start_time"
          name="start_time"
          style="width:auto;display:block;margin-top:.2rem"
          aria-describedby="poll-timing-help"
          value="<?= !empty($poll['start_date']) ? h(date('H:i', strtotime((string)$poll['start_date']))) : '' ?>"
        >
      </div>
      <div>
        <label for="end_date">Konec – datum</label>
        <input
          type="date"
          id="end_date"
          name="end_date"
          style="width:auto;display:block;margin-top:.2rem"
          aria-describedby="poll-timing-help"
          value="<?= !empty($poll['end_date']) ? h(date('Y-m-d', strtotime((string)$poll['end_date']))) : '' ?>"
        >
      </div>
      <div>
        <label for="end_time">Konec – čas</label>
        <input
          type="time"
          id="end_time"
          name="end_time"
          style="width:auto;display:block;margin-top:.2rem"
          aria-describedby="poll-timing-help"
          value="<?= !empty($poll['end_date']) ? h(date('H:i', strtotime((string)$poll['end_date']))) : '' ?>"
        >
      </div>
    </div>
  </fieldset>

  <fieldset id="options-fieldset" style="border:1px solid #ccc;padding:.5rem 1rem;margin-top:1rem">
    <legend>Možnosti odpovědi <span aria-hidden="true">*</span><span class="sr-only">(povinné, min. 2, max. 10)</span></legend>
    <div id="options-list">
      <?php if (!empty($options)): ?>
        <?php foreach ($options as $index => $option): ?>
          <div class="option-row" style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
            <input type="hidden" name="option_ids[]" value="<?= (int)$option['id'] ?>">
            <label for="option_<?= $index ?>" class="sr-only">Možnost <?= $index + 1 ?></label>
            <input
              type="text"
              id="option_<?= $index ?>"
              name="options[]"
              required
              aria-required="true"
              maxlength="500"
              value="<?= h((string)$option['option_text']) ?>"
              style="flex:1"
            >
            <?php if ((int)$option['vote_count'] > 0): ?>
              <span style="font-size:.85rem;color:#666">(<?= (int)$option['vote_count'] ?> hlasů)</span>
            <?php else: ?>
              <button
                type="button"
                class="btn btn-danger btn-remove-option"
                aria-label="Odebrat možnost <?= $index + 1 ?>"
                onclick="removeOption(this)"
              >Odebrat</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($index = 0; $index < 2; $index++): ?>
          <div class="option-row" style="display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem">
            <input type="hidden" name="option_ids[]" value="0">
            <label for="option_<?= $index ?>" class="sr-only">Možnost <?= $index + 1 ?></label>
            <input
              type="text"
              id="option_<?= $index ?>"
              name="options[]"
              required
              aria-required="true"
              maxlength="500"
              value=""
              style="flex:1"
            >
            <button
              type="button"
              class="btn btn-danger btn-remove-option"
              aria-label="Odebrat možnost <?= $index + 1 ?>"
              onclick="removeOption(this)"
            >Odebrat</button>
          </div>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
    <button type="button" id="add-option" class="btn" onclick="addOption()" style="margin-top:.5rem">+ Přidat možnost</button>
  </fieldset>

  <fieldset style="margin-top:1rem">
    <legend>Vyhledávače a sdílení</legend>

    <label for="meta_title">Meta titulek</label>
    <input
      type="text"
      id="meta_title"
      name="meta_title"
      maxlength="160"
      value="<?= h((string)($poll['meta_title'] ?? '')) ?>"
      aria-describedby="poll-meta-title-help"
    >
    <small id="poll-meta-title-help" class="field-help">Volitelné. Pokud pole nevyplníte, použije se otázka ankety.</small>

    <label for="meta_description">Meta popis</label>
    <textarea
      id="meta_description"
      name="meta_description"
      rows="3"
      maxlength="320"
      aria-describedby="poll-meta-description-help"
    ><?= h((string)($poll['meta_description'] ?? '')) ?></textarea>
    <small id="poll-meta-description-help" class="field-help">Volitelné. Pokud pole nevyplníte, použije se popis ankety nebo automatický fallback.</small>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Vytvořit anketu' ?></button>
    <?php if ($id !== null && (string)$poll['slug'] !== '' && (string)($poll['state'] ?? '') !== 'scheduled'): ?>
      <a href="<?= h(pollPublicPath($poll)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Zobrazit na webu</a>
    <?php endif; ?>
    <a href="<?= h($backUrl) ?>" style="margin-left:1rem">Zrušit</a>
  </div>
</form>

<?php if ($id !== null && $totalVotes > 0): ?>
  <h2 style="margin-top:2rem">Výsledky <small>(celkem <?= $totalVotes ?> hlasů)</small></h2>
  <div role="list" aria-label="Výsledky ankety">
    <?php foreach ($options as $option): ?>
      <?php $percentage = $totalVotes > 0 ? round((int)$option['vote_count'] / $totalVotes * 100, 1) : 0; ?>
      <div role="listitem" style="margin-bottom:.75rem">
        <div style="display:flex;justify-content:space-between;margin-bottom:.2rem">
          <span><?= h((string)$option['option_text']) ?></span>
          <span><?= $percentage ?> % (<?= (int)$option['vote_count'] ?> hlasů)</span>
        </div>
        <div style="background:#e8e8e8;border-radius:4px;overflow:hidden" aria-hidden="true">
          <div style="background:#005fcc;height:1.2rem;width:<?= $percentage ?>%;min-width:<?= $percentage > 0 ? '2px' : '0' ?>"></div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script nonce="<?= cspNonce() ?>">
(function () {
  const questionInput = document.getElementById('question');
  const slugInput = document.getElementById('slug');
  let slugManual = <?= !empty($poll['slug']) ? 'true' : 'false' ?>;

  const slugify = (value) => value
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  slugInput?.addEventListener('input', function () {
    slugManual = this.value.trim() !== '';
  });

  questionInput?.addEventListener('input', function () {
    if (slugManual || !slugInput) {
      return;
    }
    slugInput.value = slugify(this.value);
  });

  let counter = document.querySelectorAll('.option-row').length;
  const list = document.getElementById('options-list');
  const addButton = document.getElementById('add-option');
  const live = document.getElementById('a11y-live');

  function updateState() {
    const rows = list.querySelectorAll('.option-row');
    const removable = list.querySelectorAll('.btn-remove-option');
    removable.forEach((button) => {
      button.disabled = rows.length <= 2;
    });
    addButton.disabled = rows.length >= 10;
    rows.forEach((row, index) => {
      const input = row.querySelector('input[type="text"]');
      const label = row.querySelector('label');
      const button = row.querySelector('.btn-remove-option');
      if (input) {
        input.id = 'option_' + index;
      }
      if (label) {
        label.setAttribute('for', 'option_' + index);
        label.textContent = 'Možnost ' + (index + 1);
      }
      if (button) {
        button.setAttribute('aria-label', 'Odebrat možnost ' + (index + 1));
      }
    });
  }

  window.addOption = function () {
    const rows = list.querySelectorAll('.option-row');
    if (rows.length >= 10) {
      return;
    }

    const div = document.createElement('div');
    div.className = 'option-row';
    div.style.cssText = 'display:flex;gap:.5rem;align-items:center;margin-bottom:.5rem';
    div.innerHTML =
      '<input type="hidden" name="option_ids[]" value="0">' +
      '<label for="option_' + counter + '" class="sr-only">Možnost ' + (rows.length + 1) + '</label>' +
      '<input type="text" id="option_' + counter + '" name="options[]" required aria-required="true" maxlength="500" style="flex:1">' +
      '<button type="button" class="btn btn-danger btn-remove-option" aria-label="Odebrat možnost ' + (rows.length + 1) + '" onclick="removeOption(this)">Odebrat</button>';
    counter += 1;
    list.appendChild(div);
    updateState();
    div.querySelector('input[type="text"]')?.focus();
    if (live) {
      live.textContent = 'Přidána nová možnost.';
    }
  };

  window.removeOption = function (button) {
    const rows = list.querySelectorAll('.option-row');
    if (rows.length <= 2) {
      return;
    }
    const row = button.closest('.option-row');
    const previous = row?.previousElementSibling;
    row?.remove();
    updateState();
    if (previous) {
      previous.querySelector('input[type="text"]')?.focus();
    } else {
      list.querySelector('input[type="text"]')?.focus();
    }
    if (live) {
      live.textContent = 'Možnost odebrána.';
    }
  };

  updateState();
})();
</script>

<?php adminFooter(); ?>
