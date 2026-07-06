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
    'vote_mode' => 'single',
    'max_choices' => null,
    'results_visibility' => 'after_vote',
    'status' => 'active',
    'start_date' => null,
    'end_date' => null,
    'meta_title' => '',
    'meta_description' => '',
];
$poll = hydratePollPresentation($poll);

$selectionCount = 0;
$voterCount = 0;
if ($id !== null) {
    $stmtVotes = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_votes WHERE poll_id = ?");
    $stmtVotes->execute([$id]);
    $selectionCount = (int)$stmtVotes->fetchColumn();

    $stmtVoters = $pdo->prepare("SELECT COUNT(*) FROM cms_poll_vote_sessions WHERE poll_id = ?");
    $stmtVoters->execute([$id]);
    $voterCount = (int)$stmtVoters->fetchColumn();
    if ($voterCount === 0 && $selectionCount > 0) {
        $stmtVoters = $pdo->prepare("SELECT COUNT(DISTINCT ip_hash) FROM cms_poll_votes WHERE poll_id = ?");
        $stmtVoters->execute([$id]);
        $voterCount = (int)$stmtVoters->fetchColumn();
    }
}

$err = trim((string)($_GET['err'] ?? ''));
$pollTimingErrorMessage = 'Začátek a konec ankety musí být platné datum a čas. Vyplňte datum a čas v příslušných polích, nebo časové omezení nechte prázdné.';
$pollRangeErrorMessage = 'Konec ankety musí být později než začátek. Upravte datum nebo čas začátku či konce.';
$formError = match ($err) {
    'required' => 'Anketu nejde uložit bez otázky a alespoň dvou možností odpovědi. U zvýrazněných polí je konkrétní nápověda.',
    'max_options' => 'Anketa má příliš mnoho možností odpovědi. U sekce Možnosti odpovědi je konkrétní nápověda.',
    'max_choices' => 'Limit vícevýběrové ankety není použitelný. U pole Maximální počet vybraných možností je konkrétní nápověda.',
    'has_votes' => 'Možnosti s uloženými hlasy nejde odebrat. U sekce Možnosti odpovědi je konkrétní nápověda.',
    'slug' => 'Slug ankety není použitelný nebo už existuje. U pole Slug veřejné stránky je konkrétní nápověda.',
    'dates' => $pollTimingErrorMessage,
    'range' => $pollRangeErrorMessage,
    'save' => 'Anketu se nepodařilo uložit. Zkuste to prosím znovu.',
    default => '',
};
$fieldErrorMap = [
    'required' => ['question', 'options'],
    'max_options' => ['options'],
    'max_choices' => ['max_choices'],
    'has_votes' => ['options'],
    'slug' => ['slug'],
    'dates' => ['start_date', 'start_time', 'end_date', 'end_time'],
    'range' => ['start_date', 'start_time', 'end_date', 'end_time'],
];
$fieldErrorMessages = [
    'question' => 'Doplňte otázku tak, jak ji návštěvník uvidí na webu.',
    'slug' => 'Použijte jedinečný slug z malých písmen, číslic a pomlček, nebo upravte otázku pro automatické vytvoření.',
    'max_choices' => 'Zadejte celé číslo alespoň 2 a nejvýše tolik, kolik má anketa možností.',
    'dates' => $pollTimingErrorMessage,
    'range' => $pollRangeErrorMessage,
];
$optionsErrorMessage = match ($err) {
    'required' => 'Doplňte alespoň dvě neprázdné možnosti odpovědi.',
    'max_options' => 'Nechte nejvýše deset možností odpovědi a přebytečné řádky odeberte.',
    'has_votes' => 'Možnosti, které už mají hlasy, ponechte v anketě nebo anketu uzavřete a vytvořte novou.',
    default => '',
};

adminHeader($id ? 'Upravit anketu' : 'Nová anketa');
?>

<?php if ($id !== null): ?>
  <p><a href="revisions.php?type=poll&amp;id=<?= (int)$id ?>">Historie revizí</a></p>
<?php endif; ?>

<?php if ($formError !== ''): ?>
  <p role="alert" class="error" id="form-error" aria-atomic="true"><?= h($formError) ?></p>
<?php endif; ?>

<p class="admin-description admin-description--flush">
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
      <?= adminFieldAttributes('question', $err, $fieldErrorMap) ?>
      value="<?= h((string)$poll['question']) ?>"
    >
    <?php adminRenderFieldError('question', $err, $fieldErrorMap, $fieldErrorMessages['question']); ?>

    <label for="slug">Slug veřejné stránky <span aria-hidden="true">*</span></label>
    <input
      type="text"
      id="slug"
      name="slug"
      required
      aria-required="true"
      maxlength="255"
      pattern="[a-z0-9\-]+"
      <?= adminFieldAttributes('slug', $err, $fieldErrorMap, ['poll-slug-help']) ?>
      value="<?= h((string)$poll['slug']) ?>"
    >
    <small id="poll-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Používejte malá písmena, číslice a pomlčky.</small>
    <?php adminRenderFieldError('slug', $err, $fieldErrorMap, $fieldErrorMessages['slug']); ?>

    <label for="description">Popis</label>
    <textarea id="description" name="description" rows="4" aria-describedby="poll-description-help"><?= h((string)($poll['description'] ?? '')) ?></textarea>
    <small id="poll-description-help" class="field-help">Volitelné. Krátké vysvětlení se zobrazí ve výpisu i na detailu ankety.</small>

    <label for="status">Stav</label>
    <select id="status" name="status">
      <option value="active"<?= (string)$poll['status'] === 'active' ? ' selected' : '' ?>>Aktivní</option>
      <option value="closed"<?= (string)$poll['status'] === 'closed' ? ' selected' : '' ?>>Uzavřená</option>
    </select>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-action-row">
    <legend>Nastavení hlasování</legend>
    <small id="poll-voting-help" class="field-help field-help--flush">Stávající jednoduché ankety používejte jako jednu možnost. Vícevýběr dovolí jednomu hlasujícímu vybrat více odpovědí najednou.</small>

    <label for="vote_mode">Typ hlasování</label>
    <select id="vote_mode" name="vote_mode" aria-describedby="poll-voting-help">
      <?php foreach (pollVoteModeOptions() as $modeValue => $modeLabel): ?>
        <option value="<?= h($modeValue) ?>"<?= (string)$poll['vote_mode'] === $modeValue ? ' selected' : '' ?>><?= h($modeLabel) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="max_choices">Maximální počet vybraných možností</label>
    <input
      type="number"
      id="max_choices"
      name="max_choices"
      min="2"
      max="10"
      step="1"
      value="<?= $poll['max_choices'] !== null ? h((string)$poll['max_choices']) : '' ?>"
      <?= adminFieldAttributes('max_choices', $err, $fieldErrorMap, ['poll-max-choices-help']) ?>
    >
    <small id="poll-max-choices-help" class="field-help">Použije se jen u vícevýběrové ankety. Prázdná hodnota znamená bezpečný výchozí limit 2 možnosti.</small>
    <?php adminRenderFieldError('max_choices', $err, $fieldErrorMap, $fieldErrorMessages['max_choices']); ?>

    <label for="results_visibility">Viditelnost výsledků</label>
    <select id="results_visibility" name="results_visibility" aria-describedby="poll-results-visibility-help">
      <?php foreach (pollResultsVisibilityOptions() as $visibilityValue => $visibilityLabel): ?>
        <option value="<?= h($visibilityValue) ?>"<?= (string)$poll['results_visibility'] === $visibilityValue ? ' selected' : '' ?>><?= h($visibilityLabel) ?></option>
      <?php endforeach; ?>
    </select>
    <small id="poll-results-visibility-help" class="field-help">Určuje, kdy veřejná stránka ankety ukáže číselné výsledky. Administrace výsledky vidí vždy.</small>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-action-row">
    <legend>Časové omezení</legend>
    <small id="poll-timing-help" class="field-help field-help--flush">Vyplňte jen pokud má anketa začít nebo skončit v konkrétní čas.</small>
    <div class="admin-form-grid admin-form-grid--end">
      <div>
        <label for="start_date">Začátek – datum</label>
        <input
          type="date"
          id="start_date"
          name="start_date"
          class="admin-input-auto"
          <?= adminFieldAttributes('start_date', $err, $fieldErrorMap, ['poll-timing-help'], 'poll-timing-error') ?>
          value="<?= !empty($poll['start_date']) ? h(date('Y-m-d', strtotime((string)$poll['start_date']))) : '' ?>"
        >
      </div>
      <div>
        <label for="start_time">Začátek – čas</label>
        <input
          type="time"
          id="start_time"
          name="start_time"
          class="admin-input-auto"
          <?= adminFieldAttributes('start_time', $err, $fieldErrorMap, ['poll-timing-help'], 'poll-timing-error') ?>
          value="<?= !empty($poll['start_date']) ? h(date('H:i', strtotime((string)$poll['start_date']))) : '' ?>"
        >
      </div>
      <div>
        <label for="end_date">Konec – datum</label>
        <input
          type="date"
          id="end_date"
          name="end_date"
          class="admin-input-auto"
          <?= adminFieldAttributes('end_date', $err, $fieldErrorMap, ['poll-timing-help'], 'poll-timing-error') ?>
          value="<?= !empty($poll['end_date']) ? h(date('Y-m-d', strtotime((string)$poll['end_date']))) : '' ?>"
        >
      </div>
      <div>
        <label for="end_time">Konec – čas</label>
        <input
          type="time"
          id="end_time"
          name="end_time"
          class="admin-input-auto"
          <?= adminFieldAttributes('end_time', $err, $fieldErrorMap, ['poll-timing-help'], 'poll-timing-error') ?>
          value="<?= !empty($poll['end_date']) ? h(date('H:i', strtotime((string)$poll['end_date']))) : '' ?>"
        >
      </div>
    </div>
    <?php if (adminFieldHasError('start_date', $err, $fieldErrorMap)): ?>
      <small id="poll-timing-error" class="field-help field-error">
        <?= h($fieldErrorMessages[$err] ?? '') ?>
      </small>
    <?php endif; ?>
  </fieldset>

  <fieldset id="options-fieldset" class="admin-fieldset-card admin-action-row"<?= adminFieldHasError('options', $err, $fieldErrorMap) ? ' aria-describedby="poll-options-error"' : '' ?>>
    <legend>Možnosti odpovědi <span aria-hidden="true">*</span><span class="sr-only">(povinné, min. 2, max. 10)</span></legend>
    <?php if ($optionsErrorMessage !== ''): ?>
      <small id="poll-options-error" class="field-help field-error"><?= h($optionsErrorMessage) ?></small>
    <?php endif; ?>
    <div id="options-list">
      <?php if (!empty($options)): ?>
        <?php foreach ($options as $index => $option): ?>
          <div class="option-row admin-option-row">
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
              class="admin-option-row__input"
            >
            <?php if ((int)$option['vote_count'] > 0): ?>
              <span class="table-meta">(<?= (int)$option['vote_count'] ?> hlasů)</span>
            <?php else: ?>
              <button
                type="button"
                class="btn btn-danger btn-remove-option"
                data-poll-option-remove
              >Odebrat<span class="sr-only"> možnost <?= $index + 1 ?></span></button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($index = 0; $index < 2; $index++): ?>
          <div class="option-row admin-option-row">
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
              class="admin-option-row__input"
            >
            <button
              type="button"
              class="btn btn-danger btn-remove-option"
              data-poll-option-remove
            >Odebrat<span class="sr-only"> možnost <?= $index + 1 ?></span></button>
          </div>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
    <button type="button" id="add-option" class="btn admin-action-row" data-poll-option-add>+ Přidat možnost</button>
  </fieldset>

  <fieldset class="admin-fieldset-card admin-action-row">
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

  <div class="button-row admin-fieldset-spaced">
    <button type="submit" class="btn"><?= $id !== null ? 'Uložit změny' : 'Vytvořit anketu' ?></button>
    <?php if ($id !== null && (string)$poll['slug'] !== '' && (string)($poll['state'] ?? '') !== 'scheduled'): ?>
      <a href="<?= h(pollPublicPath($poll)) ?>" target="_blank" rel="noopener noreferrer">Zobrazit na webu<?= newWindowLinkSrOnlySuffix() ?></a>
    <?php endif; ?>
    <?php if ($id !== null): ?>
      <a href="polls_results_export.php?id=<?= (int)$id ?>">Přejít na kontrolu CSV exportu výsledků</a>
    <?php endif; ?>
    <a href="<?= h($backUrl) ?>">Zrušit</a>
  </div>
</form>

<?php if ($id !== null && $selectionCount > 0): ?>
  <?php
    $multipleMode = pollAllowsMultipleChoices($poll);
    $percentageDenominator = $multipleMode ? max(1, $voterCount) : max(1, $selectionCount);
    ?>
  <h2 id="poll-results-heading" class="admin-section-heading">Výsledky</h2>
  <p class="table-meta">
    Hlasujících: <?= (int)$voterCount ?>.
    Vybraných odpovědí: <?= (int)$selectionCount ?>.
    Režim: <?= h((string)($poll['vote_mode_label'] ?? 'Jedna možnost')) ?>.
  </p>
  <div role="list" class="admin-result-list" aria-labelledby="poll-results-heading">
    <?php foreach ($options as $option): ?>
      <?php $percentage = pollResultPercentage((int)$option['vote_count'], $percentageDenominator); ?>
      <div role="listitem" class="admin-result-item">
        <div class="admin-result-row">
          <span><?= h((string)$option['option_text']) ?></span>
          <span><?= h((string)$percentage) ?> % (<?= h(pollVoteSelectionLabel((int)$option['vote_count'], $multipleMode)) ?>)</span>
        </div>
        <progress class="admin-progress" value="<?= h((string)$percentage) ?>" max="100" aria-hidden="true"><?= h((string)$percentage) ?> %</progress>
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

  if (!list || !addButton) {
    return;
  }

  function updateState() {
    const rows = list.querySelectorAll('.option-row');
    const removable = list.querySelectorAll('[data-poll-option-remove]');
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
        const hiddenContext = button.querySelector('.sr-only');
        if (hiddenContext) {
          hiddenContext.textContent = ' možnost ' + (index + 1);
        }
      }
    });
  }

  function addOption() {
    const rows = list.querySelectorAll('.option-row');
    if (rows.length >= 10) {
      return;
    }

    const div = document.createElement('div');
    div.className = 'option-row admin-option-row';
    div.innerHTML =
      '<input type="hidden" name="option_ids[]" value="0">' +
      '<label for="option_' + counter + '" class="sr-only">Možnost ' + (rows.length + 1) + '</label>' +
      '<input type="text" id="option_' + counter + '" name="options[]" required aria-required="true" maxlength="500" class="admin-option-row__input">' +
      '<button type="button" class="btn btn-danger btn-remove-option" data-poll-option-remove>Odebrat<span class="sr-only"> možnost ' + (rows.length + 1) + '</span></button>';
    counter += 1;
    list.appendChild(div);
    updateState();
    div.querySelector('input[type="text"]')?.focus();
    if (live) {
      live.textContent = 'Přidána nová možnost.';
    }
  }

  function removeOption(button) {
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
  }

  addButton.addEventListener('click', addOption);
  list.addEventListener('click', function (event) {
    const button = event.target instanceof Element ? event.target.closest('[data-poll-option-remove]') : null;
    if (!button || !list.contains(button)) {
      return;
    }
    removeOption(button);
  });

  updateState();
})();
</script>

<?php adminFooter(); ?>
