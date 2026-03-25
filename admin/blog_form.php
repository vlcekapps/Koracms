<?php
require_once __DIR__ . '/layout.php';
requireLogin(BASE_URL . '/admin/login.php');

$pdo = db_connect();
$id = inputInt('get', 'id');
$article = null;

if ($id !== null) {
    if (canManageOwnBlogOnly()) {
        $stmt = $pdo->prepare("SELECT * FROM cms_articles WHERE id = ? AND author_id = ?");
        $stmt->execute([$id, currentUserId()]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM cms_articles WHERE id = ?");
        $stmt->execute([$id]);
    }
    $article = $stmt->fetch();
    if (!$article) {
        header('Location: blog.php');
        exit;
    }
}

$categories = $pdo->query("SELECT id, name FROM cms_categories ORDER BY name")->fetchAll();
$allTags = [];
$articleTagIds = [];
try {
    $allTags = $pdo->query("SELECT id, name FROM cms_tags ORDER BY name")->fetchAll();
    if ($id !== null) {
        $tagStmt = $pdo->prepare("SELECT tag_id FROM cms_article_tags WHERE article_id = ?");
        $tagStmt->execute([$id]);
        $articleTagIds = array_column($tagStmt->fetchAll(), 'tag_id');
    }
} catch (\PDOException $e) {
}

$useWysiwyg = getSetting('content_editor', 'html') === 'wysiwyg';
$err = trim($_GET['err'] ?? '');
$publishAtInput = '';
if (!empty($article['publish_at'])) {
    $publishAtInput = date('Y-m-d\TH:i', strtotime((string)$article['publish_at']));
}

$contentPickerEndpoint = BASE_URL . '/admin/blog_content_reference_search.php';
$contentPickerTypes = [
    'all' => 'Všechen obsah',
    'blog' => 'Články blogu',
    'page' => 'Statické stránky',
];

if (isModuleEnabled('news')) {
    $contentPickerTypes['news'] = 'Novinky';
}
if (isModuleEnabled('events')) {
    $contentPickerTypes['event'] = 'Události';
}
if (isModuleEnabled('faq')) {
    $contentPickerTypes['faq'] = 'FAQ';
}
if (isModuleEnabled('gallery')) {
    $contentPickerTypes['gallery'] = 'Fotogalerie';
}
if (isModuleEnabled('podcast')) {
    $contentPickerTypes['podcast'] = 'Podcasty';
}
if (isModuleEnabled('downloads')) {
    $contentPickerTypes['download'] = 'Ke stažení';
}
if (isModuleEnabled('places')) {
    $contentPickerTypes['place'] = 'Zajímavá místa';
}
if (isModuleEnabled('board')) {
    $contentPickerTypes['board'] = boardModulePublicLabel();
}
if (isModuleEnabled('polls')) {
    $contentPickerTypes['poll'] = 'Ankety';
}

adminHeader($article ? 'Upravit článek' : 'Přidat článek');
?>

<?php if ($err === 'slug'): ?>
  <p role="alert" class="error" id="form-error">Slug článku je povinný a musí být unikátní.</p>
<?php endif; ?>

<?php if (!$useWysiwyg): ?>
<style>
  .blog-content-picker-launch {
    margin-top: 1rem;
  }

  .blog-content-picker-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.54);
    z-index: 1000;
  }

  .blog-content-picker-dialog {
    position: fixed;
    inset: 50% auto auto 50%;
    transform: translate(-50%, -50%);
    width: min(56rem, calc(100vw - 2rem));
    max-height: calc(100vh - 2rem);
    overflow: auto;
    padding: 1rem 1.1rem 1.15rem;
    border: 1px solid #cbd5e1;
    border-radius: .9rem;
    background: #fff;
    box-shadow: 0 28px 60px rgba(15, 23, 42, 0.28);
    z-index: 1001;
  }

  .blog-content-picker-dialog__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: .75rem;
  }

  .blog-content-picker-dialog__title {
    margin: 0;
    font-size: 1.2rem;
  }

  .blog-content-picker-toolbar {
    display: grid;
    grid-template-columns: minmax(15rem, 2fr) minmax(12rem, 1fr) auto;
    gap: .75rem;
    align-items: end;
  }

  .blog-content-picker-toolbar label {
    margin-top: 0;
  }

  .blog-content-picker-toolbar .btn {
    margin-top: .2rem;
  }

  .blog-content-picker-results {
    margin-top: 1rem;
  }

  .blog-content-picker-results__list {
    display: grid;
    gap: .8rem;
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .blog-content-picker-result {
    padding: .85rem 1rem;
    border: 1px solid #d0d5dd;
    border-radius: .75rem;
    background: #f8fafc;
  }

  .blog-content-picker-result__meta {
    margin: 0 0 .35rem;
    color: #475467;
    font-size: .86rem;
    font-weight: 700;
  }

  .blog-content-picker-result__title {
    margin: 0;
    font-size: 1rem;
  }

  .blog-content-picker-result__path {
    display: block;
    margin-top: .35rem;
    color: #475467;
    font-size: .85rem;
    word-break: break-word;
  }

  .blog-content-picker-result__excerpt {
    margin: .55rem 0 0;
    color: #344054;
  }

  .blog-content-picker-result__actions {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    align-items: center;
    margin-top: .85rem;
  }

  .blog-content-picker-result__actions a {
    color: #0f4c81;
  }

  @media (max-width: 720px) {
    .blog-content-picker-dialog {
      width: calc(100vw - 1rem);
      max-height: calc(100vh - 1rem);
      padding: .9rem;
    }

    .blog-content-picker-toolbar {
      grid-template-columns: 1fr;
    }
  }
</style>
<?php endif; ?>

<form method="post" action="blog_save.php" enctype="multipart/form-data" novalidate<?= $err === 'slug' ? ' aria-describedby="form-error"' : '' ?>>
  <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
  <?php if ($article): ?>
    <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
  <?php endif; ?>

  <?php if ($article && !empty($article['author_id'])): ?>
    <?php
    try {
        $authorStmt = $pdo->prepare("SELECT first_name, last_name, nickname, email FROM cms_users WHERE id = ?");
        $authorStmt->execute([(int)$article['author_id']]);
        $authorRow = $authorStmt->fetch();
        if ($authorRow) {
            $authorName = $authorRow['nickname'] !== '' ? $authorRow['nickname'] : trim($authorRow['first_name'] . ' ' . $authorRow['last_name']);
            if ($authorName === '') {
                $authorName = $authorRow['email'];
            }
        } else {
            $authorName = '–';
        }
    } catch (\PDOException $e) {
        $authorName = '–';
    }
    ?>
    <p style="color:#555;font-size:.9rem;margin-bottom:1rem">
      Autor: <strong><?= h($authorName) ?></strong>
    </p>
  <?php elseif (!$article): ?>
    <p style="color:#555;font-size:.9rem;margin-bottom:1rem">
      Autor: <strong><?= h(currentUserDisplayName()) ?></strong>
    </p>
  <?php endif; ?>

  <fieldset>
    <legend>Základní údaje článku</legend>

    <label for="title">Titulek <span aria-hidden="true">*</span></label>
    <input type="text" id="title" name="title" required aria-required="true" maxlength="255"
           value="<?= h($article['title'] ?? '') ?>">

    <label for="slug">Slug (URL článku) <span aria-hidden="true">*</span></label>
    <input type="text" id="slug" name="slug" required aria-required="true" maxlength="255" pattern="[a-z0-9\-]+"
           aria-describedby="blog-slug-help"
           value="<?= h($article['slug'] ?? '') ?>">
    <small id="blog-slug-help" class="field-help">Adresa se vyplní automaticky, dokud ji neupravíte ručně. Použijte malá písmena, číslice a pomlčky.</small>

    <label for="category_id">Kategorie</label>
    <select id="category_id" name="category_id">
      <option value="">– bez kategorie –</option>
      <?php foreach ($categories as $category): ?>
        <option value="<?= (int)$category['id'] ?>" <?= ((int)($article['category_id'] ?? 0) === (int)$category['id']) ? 'selected' : '' ?>>
          <?= h($category['name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </fieldset>

  <?php if (!empty($allTags)): ?>
  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Štítky článku</legend>
    <?php foreach ($allTags as $tag): ?>
      <label style="display:inline-block;margin-right:1rem;font-weight:normal">
        <input type="checkbox" name="tags[]" value="<?= (int)$tag['id'] ?>"
               <?= in_array((int)$tag['id'], $articleTagIds, true) ? 'checked' : '' ?>>
        <?= h($tag['name']) ?>
      </label>
    <?php endforeach; ?>
  </fieldset>
  <?php endif; ?>

  <fieldset>
    <legend>Text článku</legend>

    <label for="perex">Perex (krátký úvod)</label>
    <textarea id="perex" name="perex" rows="3"><?= h($article['perex'] ?? '') ?></textarea>

    <label for="content">Text článku <span aria-hidden="true">*</span></label>
    <textarea id="content" name="content" rows="15" required aria-required="true"<?= !$useWysiwyg ? ' aria-describedby="blog-content-help"' : '' ?>><?= h($article['content'] ?? '') ?></textarea>
    <?php if (!$useWysiwyg): ?><small id="blog-content-help" class="field-help">Můžete použít HTML, Markdown nebo snippety jako <code>[audio]https://example.test/audio.mp3[/audio]</code>, <code>[video]https://example.test/video.mp4[/video]</code> a <code>[gallery]slug-alba[/gallery]</code>.</small><?php endif; ?>
    <?php if (!$useWysiwyg): ?>
      <div class="blog-content-picker-launch">
        <button type="button"
                class="btn"
                id="open-blog-content-picker"
                aria-haspopup="dialog"
                aria-controls="blog-content-picker-dialog"
                aria-describedby="blog-content-picker-launch-help">
          Vložit odkaz nebo HTML z webu
        </button>
        <small id="blog-content-picker-launch-help" class="field-help">Vyhledejte existující článek, stránku nebo jiný veřejný obsah a vložte ho rovnou do textu jako odkaz nebo hotový HTML blok.</small>
      </div>

      <div id="blog-content-picker-overlay" class="blog-content-picker-overlay" hidden></div>
      <section id="blog-content-picker-dialog"
               class="blog-content-picker-dialog"
               role="dialog"
               aria-modal="true"
               aria-labelledby="blog-content-picker-title"
               aria-describedby="blog-content-picker-description"
               hidden>
        <div class="blog-content-picker-dialog__header">
          <div>
            <h2 id="blog-content-picker-title" class="blog-content-picker-dialog__title">Vložit odkaz nebo HTML z webu</h2>
            <p id="blog-content-picker-description" class="field-help" style="margin-top:.35rem">Tento nástroj je dostupný v režimu čistého HTML editoru. Vyhledaný obsah můžete vložit jako inline odkaz nebo jako hotový HTML blok.</p>
          </div>
          <button type="button" class="btn" id="close-blog-content-picker">Zavřít</button>
        </div>

        <fieldset style="margin:0;border:1px solid #ccc;padding:.5rem 1rem">
          <legend>Vyhledání obsahu</legend>
          <div class="blog-content-picker-toolbar">
            <div>
              <label for="blog-content-picker-query">Hledat obsah webu</label>
              <input type="text"
                     id="blog-content-picker-query"
                     autocomplete="off"
                     aria-describedby="blog-content-picker-query-help blog-content-picker-selection-help">
            </div>
            <div>
              <label for="blog-content-picker-type">Typ obsahu</label>
              <select id="blog-content-picker-type">
                <?php foreach ($contentPickerTypes as $pickerTypeValue => $pickerTypeLabel): ?>
                  <option value="<?= h($pickerTypeValue) ?>"><?= h($pickerTypeLabel) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <button type="button" class="btn" id="blog-content-picker-submit">Vyhledat</button>
            </div>
          </div>
          <small id="blog-content-picker-query-help" class="field-help">Hledání prochází jen veřejně dostupný obsah webu.</small>
          <small id="blog-content-picker-selection-help" class="field-help">Pokud máte v editoru označený text, při vložení odkazu se použije jako text odkazu. Jinak se vloží název nalezené položky.</small>
        </fieldset>

        <p id="blog-content-picker-status" role="status" aria-live="polite" aria-atomic="true" style="margin:.85rem 0 0;color:#555;font-size:.92rem;line-height:1.45">Zadejte alespoň 2 znaky a vyhledejte obsah.</p>
        <div id="blog-content-picker-results" class="blog-content-picker-results" aria-live="polite"></div>
      </section>
    <?php endif; ?>

    <label for="image">Náhledový obrázek</label>
    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/gif,image/webp"
           aria-describedby="<?= !empty($article['image_file']) ? 'blog-image-current' : 'blog-image-help' ?>">
    <?php if (!empty($article['image_file'])): ?>
      <small id="blog-image-current" class="field-help">Aktuální obrázek: <a href="<?= BASE_URL ?>/uploads/articles/<?= rawurlencode((string)$article['image_file']) ?>"
             target="_blank" rel="noopener noreferrer"><?= h((string)$article['image_file']) ?></a>.</small>
    <?php else: ?>
      <small id="blog-image-help" class="field-help">Volitelné. Hodí se pro úvodní náhled článku.</small>
    <?php endif; ?>
    <?php if (!empty($article['image_file'])): ?>
      <label style="font-weight:normal;margin-top:.3rem">
        <input type="checkbox" name="image_delete" value="1"> Smazat stávající obrázek
      </label>
    <?php endif; ?>

    <label for="publish_at">Plánované publikování</label>
    <input type="datetime-local" id="publish_at" name="publish_at" aria-describedby="blog-publish-at-help"
           style="width:auto" value="<?= h($publishAtInput) ?>">
    <small id="blog-publish-at-help" class="field-help">Nechte prázdné, pokud se má článek zveřejnit hned.</small>
  </fieldset>

  <fieldset style="margin-top:1rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Komentáře</legend>
    <div>
      <input type="checkbox" id="comments_enabled" name="comments_enabled" value="1" aria-describedby="blog-comments-help"
             <?= (int)($article['comments_enabled'] ?? 1) === 1 ? 'checked' : '' ?>>
      <label for="comments_enabled" style="display:inline;font-weight:normal">
        Povolit komentáře u tohoto článku
      </label>
    </div>
    <small id="blog-comments-help" class="field-help">Globální pravidla moderace nastavíte v základním nastavení webu.</small>
  </fieldset>

  <fieldset style="margin-top:1.5rem;border:1px solid #ccc;padding:.5rem 1rem">
    <legend>Vyhledávače a sdílení</legend>
    <small id="blog-seo-help" class="field-help" style="margin-top:0">Nepovinné. Ponechte prázdné pro automatické hodnoty.</small>
    <label for="meta_title">Meta titulek</label>
    <input type="text" id="meta_title" name="meta_title" maxlength="160" aria-describedby="blog-seo-help"
           value="<?= h($article['meta_title'] ?? '') ?>">

    <label for="meta_description">Meta popis</label>
    <textarea id="meta_description" name="meta_description" rows="2" aria-describedby="blog-seo-help"
              style="min-height:0"><?= h($article['meta_description'] ?? '') ?></textarea>
  </fieldset>

  <div style="margin-top:1.5rem">
    <button type="submit"><?= $article ? 'Uložit změny' : 'Přidat článek' ?></button>
    <a href="blog.php" style="margin-left:1rem">Zrušit</a>
    <?php if ($article && !empty($article['preview_token'])): ?>
      <a href="<?= h(articlePreviewPath($article)) ?>" target="_blank" rel="noopener noreferrer" style="margin-left:1rem">Náhled</a>
    <?php elseif ($article): ?>
      <small style="margin-left:1rem;color:#666">(Uložte pro aktivaci odkazu „Náhled“)</small>
    <?php endif; ?>
  </div>
</form>

<?php if (!$useWysiwyg): ?>
<script>
(function () {
    const textarea = document.getElementById('content');
    const openButton = document.getElementById('open-blog-content-picker');
    const closeButton = document.getElementById('close-blog-content-picker');
    const overlay = document.getElementById('blog-content-picker-overlay');
    const dialog = document.getElementById('blog-content-picker-dialog');
    const queryInput = document.getElementById('blog-content-picker-query');
    const typeSelect = document.getElementById('blog-content-picker-type');
    const searchButton = document.getElementById('blog-content-picker-submit');
    const statusNode = document.getElementById('blog-content-picker-status');
    const resultsNode = document.getElementById('blog-content-picker-results');
    const liveRegion = document.getElementById('a11y-live');
    const endpoint = <?= json_encode($contentPickerEndpoint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    let lastTrigger = null;
    let savedSelection = { start: 0, end: 0, text: '' };

    if (!textarea || !openButton || !dialog || !overlay || !queryInput || !typeSelect || !searchButton || !statusNode || !resultsNode) {
        return;
    }

    const isVisible = (element) => !element.hasAttribute('hidden') && element.getClientRects().length > 0;

    const visibleFocusableNodes = () => Array.from(dialog.querySelectorAll(focusableSelector)).filter(isVisible);

    const setStatus = (message) => {
        statusNode.textContent = message;
        if (liveRegion && message) {
            liveRegion.textContent = message;
        }
    };

    const rememberSelection = () => {
        savedSelection = {
            start: textarea.selectionStart ?? 0,
            end: textarea.selectionEnd ?? 0,
            text: textarea.value.slice(textarea.selectionStart ?? 0, textarea.selectionEnd ?? 0),
        };
    };

    const countLabel = (count) => {
        if (count === 1) {
            return 'Nalezena 1 položka.';
        }
        if (count >= 2 && count <= 4) {
            return 'Nalezeny ' + count + ' položky.';
        }
        return 'Nalezeno ' + count + ' položek.';
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

    const insertSnippet = (snippet, blockMode) => {
        const start = Number.isInteger(savedSelection.start) ? savedSelection.start : (textarea.selectionStart ?? textarea.value.length);
        const end = Number.isInteger(savedSelection.end) ? savedSelection.end : (textarea.selectionEnd ?? start);
        const before = textarea.value.slice(0, start);
        const after = textarea.value.slice(end);
        let insertion = snippet;

        if (blockMode) {
            const needsLeadingBreak = before !== '' && !before.endsWith('\n\n');
            const needsTrailingBreak = after !== '' && !after.startsWith('\n\n');
            insertion = (needsLeadingBreak ? (before.endsWith('\n') ? '\n' : '\n\n') : '')
                + snippet
                + (needsTrailingBreak ? (after.startsWith('\n') ? '\n' : '\n\n') : '');
        }

        textarea.value = before + insertion + after;

        const caretPosition = (before + insertion).length;
        textarea.focus();
        textarea.setSelectionRange(caretPosition, caretPosition);
        rememberSelection();
    };

    const buildInlineLink = (item) => {
        const linkText = savedSelection.text.trim() !== '' ? savedSelection.text : item.title;
        return '<a href="' + escapeHtml(item.url) + '">' + escapeHtml(linkText) + '</a>';
    };

    const buildHtmlBlock = (item) => {
        const lines = [
            '<aside class="content-reference">',
            '  <p class="content-reference__eyebrow">' + escapeHtml(item.kind_label) + '</p>',
            '  <p class="content-reference__title"><a href="' + escapeHtml(item.url) + '">' + escapeHtml(item.title) + '</a></p>',
        ];

        if (item.excerpt) {
            lines.push('  <p class="content-reference__excerpt">' + escapeHtml(item.excerpt) + '</p>');
        }

        lines.push('</aside>');
        return lines.join('\n');
    };

    const clearResults = () => {
        resultsNode.innerHTML = '';
        resultsNode.removeAttribute('aria-busy');
    };

    const closeDialog = (restoreFocus = true) => {
        dialog.hidden = true;
        overlay.hidden = true;
        if (restoreFocus) {
            (lastTrigger || openButton).focus();
        }
    };

    const openDialog = () => {
        rememberSelection();
        lastTrigger = document.activeElement;
        overlay.hidden = false;
        dialog.hidden = false;
        queryInput.focus();
        queryInput.select();
    };

    const renderResults = (items) => {
        clearResults();

        if (!items.length) {
            return;
        }

        const list = document.createElement('ul');
        list.className = 'blog-content-picker-results__list';

        items.forEach((item) => {
            const listItem = document.createElement('li');
            const article = document.createElement('article');
            article.className = 'blog-content-picker-result';

            const meta = document.createElement('p');
            meta.className = 'blog-content-picker-result__meta';
            meta.textContent = item.kind_label;
            article.appendChild(meta);

            const heading = document.createElement('h3');
            heading.className = 'blog-content-picker-result__title';
            heading.textContent = item.title;
            article.appendChild(heading);

            const path = document.createElement('code');
            path.className = 'blog-content-picker-result__path';
            path.textContent = item.path;
            article.appendChild(path);

            if (item.excerpt) {
                const excerpt = document.createElement('p');
                excerpt.className = 'blog-content-picker-result__excerpt';
                excerpt.textContent = item.excerpt;
                article.appendChild(excerpt);
            }

            const actions = document.createElement('div');
            actions.className = 'blog-content-picker-result__actions';

            const inlineButton = document.createElement('button');
            inlineButton.type = 'button';
            inlineButton.className = 'btn';
            inlineButton.textContent = 'Vložit jako odkaz';
            inlineButton.addEventListener('click', () => {
                insertSnippet(buildInlineLink(item), false);
                closeDialog(false);
                setStatus('Do článku byl vložen odkaz.');
            });
            actions.appendChild(inlineButton);

            const blockButton = document.createElement('button');
            blockButton.type = 'button';
            blockButton.className = 'btn';
            blockButton.textContent = 'Vložit jako HTML blok';
            blockButton.addEventListener('click', () => {
                insertSnippet(buildHtmlBlock(item), true);
                closeDialog(false);
                setStatus('Do článku byl vložen HTML blok.');
            });
            actions.appendChild(blockButton);

            const previewLink = document.createElement('a');
            previewLink.href = item.url;
            previewLink.target = '_blank';
            previewLink.rel = 'noopener noreferrer';
            previewLink.textContent = 'Zobrazit na webu';
            actions.appendChild(previewLink);

            article.appendChild(actions);
            listItem.appendChild(article);
            list.appendChild(listItem);
        });

        resultsNode.appendChild(list);
    };

    const runSearch = async () => {
        const query = queryInput.value.trim();

        if (query.length < 2) {
            clearResults();
            setStatus('Zadejte alespoň 2 znaky.');
            return;
        }

        resultsNode.innerHTML = '';
        resultsNode.setAttribute('aria-busy', 'true');
        setStatus('Hledám obsah…');
        searchButton.disabled = true;

        try {
            const url = endpoint + '?q=' + encodeURIComponent(query) + '&type=' + encodeURIComponent(typeSelect.value);
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json'
                }
            });

            let payload = null;
            try {
                payload = await response.json();
            } catch (error) {
                payload = null;
            }

            if (!response.ok || !payload || payload.ok === false) {
                throw new Error(payload && payload.message ? payload.message : 'Vyhledávání se nepodařilo dokončit.');
            }

            const items = Array.isArray(payload.results) ? payload.results : [];
            renderResults(items);
            setStatus(items.length ? countLabel(items.length) : (payload.message || 'Žádný veřejný obsah neodpovídá hledání.'));
        } catch (error) {
            clearResults();
            setStatus(error instanceof Error ? error.message : 'Vyhledávání se nepodařilo dokončit.');
        } finally {
            resultsNode.removeAttribute('aria-busy');
            searchButton.disabled = false;
        }
    };

    openButton.addEventListener('mousedown', rememberSelection);
    openButton.addEventListener('click', openDialog);
    closeButton?.addEventListener('click', () => closeDialog());
    overlay.addEventListener('click', () => closeDialog());
    searchButton.addEventListener('click', runSearch);
    typeSelect.addEventListener('change', () => {
        if (queryInput.value.trim().length >= 2) {
            runSearch();
        }
    });

    queryInput.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            runSearch();
        }
    });

    textarea.addEventListener('keyup', rememberSelection);
    textarea.addEventListener('click', rememberSelection);
    textarea.addEventListener('select', rememberSelection);

    dialog.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            closeDialog();
            return;
        }

        if (event.key !== 'Tab') {
            return;
        }

        const focusable = visibleFocusableNodes();
        if (!focusable.length) {
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
})();
</script>
<?php endif; ?>

<script>
(function () {
    const titleInput = document.getElementById('title');
    const slugInput = document.getElementById('slug');
    let slugManual = <?= $article && !empty($article['slug']) ? 'true' : 'false' ?>;

    const slugify = (value) => value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    slugInput?.addEventListener('input', function () {
        slugManual = this.value.trim() !== '';
    });

    titleInput?.addEventListener('input', function () {
        if (slugManual || !slugInput) {
            return;
        }
        slugInput.value = slugify(this.value);
    });
})();
</script>

<?php if ($useWysiwyg): ?>
<link href="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@2/dist/quill.js"></script>
<script>
(function () {
    const textarea = document.getElementById('content');
    const wrapper = document.createElement('div');
    wrapper.style.cssText = 'background:#fff;border:1px solid #ccc;margin-top:.2rem;min-height:300px';
    textarea.parentNode.insertBefore(wrapper, textarea);
    textarea.style.display = 'none';

    const quill = new Quill(wrapper, {
        theme: 'snow',
        modules: { toolbar: [
            [{ header: [2, 3, 4, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'code-block'],
            ['link', 'image'],
            ['clean']
        ] }
    });

    quill.root.innerHTML = textarea.value;

    textarea.closest('form')?.addEventListener('submit', function () {
        textarea.value = quill.root.innerHTML;
    });
})();
</script>
<?php endif; ?>

<?php adminFooter(); ?>
