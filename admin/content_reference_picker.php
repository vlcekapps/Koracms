<?php
declare(strict_types=1);

function adminContentReferencePickerTypes(): array
{
    $types = [
        'all' => 'Všechen obsah',
        'blog' => 'Články blogu',
        'page' => 'Statické stránky',
    ];

    if (isModuleEnabled('news')) {
        $types['news'] = 'Novinky';
    }
    if (isModuleEnabled('events')) {
        $types['event'] = 'Události';
    }
    if (isModuleEnabled('faq')) {
        $types['faq'] = 'FAQ';
    }
    if (isModuleEnabled('gallery')) {
        $types['gallery'] = 'Fotogalerie';
    }
    if (isModuleEnabled('podcast')) {
        $types['podcast'] = 'Podcasty';
    }
    if (isModuleEnabled('downloads')) {
        $types['download'] = 'Ke stažení';
    }
    $types['media'] = 'Knihovna médií';
    if (isModuleEnabled('forms')) {
        $types['forms'] = 'Formuláře';
    }
    if (isModuleEnabled('places')) {
        $types['place'] = 'Zajímavá místa';
    }
    if (isModuleEnabled('board')) {
        $types['board'] = boardModulePublicLabel();
    }
    if (isModuleEnabled('polls')) {
        $types['poll'] = 'Ankety';
    }

    return $types;
}

function adminHtmlSnippetSupportMarkup(): string
{
    $snippets = [
        '<code>[audio]https://example.test/audio.mp3[/audio]</code>',
        '<code>[video]https://example.test/video.mp4[/video]</code>',
    ];

    if (isModuleEnabled('gallery')) {
        $snippets[] = '<code>[gallery]slug-alba[/gallery]</code>';
    }
    if (isModuleEnabled('forms')) {
        $snippets[] = '<code>[form]slug-formulare[/form]</code>';
    }
    if (isModuleEnabled('polls')) {
        $snippets[] = '<code>[poll]slug-ankety[/poll]</code>';
    }
    if (isModuleEnabled('downloads')) {
        $snippets[] = '<code>[download]slug-polozky[/download]</code>';
    }
    if (isModuleEnabled('podcast')) {
        $snippets[] = '<code>[podcast]slug-poradu[/podcast]</code>';
        $snippets[] = '<code>[podcast_episode]slug-poradu/slug-epizody[/podcast_episode]</code>';
    }
    if (isModuleEnabled('places')) {
        $snippets[] = '<code>[place]slug-mista[/place]</code>';
    }
    if (isModuleEnabled('events')) {
        $snippets[] = '<code>[event]slug-udalosti[/event]</code>';
    }
    if (isModuleEnabled('board')) {
        $snippets[] = '<code>[board]slug-oznameni[/board]</code>';
    }

    $last = array_pop($snippets);
    if ($last === null) {
        return 'Můžete použít HTML nebo Markdown.';
    }

    $body = $snippets !== []
        ? implode(', ', $snippets) . ' a ' . $last
        : $last;

    return 'Můžete použít HTML, Markdown nebo snippety jako ' . $body . '.';
}

function renderAdminContentReferencePicker(string $textareaId): void
{
    static $stylesPrinted = false;

    $pickerId = preg_replace('/[^a-z0-9_-]+/i', '-', $textareaId) ?: 'content';
    $endpoint = BASE_URL . '/admin/content_reference_search.php';
    $contentPickerTypes = adminContentReferencePickerTypes();

    if (!$stylesPrinted) {
        $stylesPrinted = true;
        $nonce = cspNonce();
        echo '<style nonce="' . $nonce . '">'; ?>
  .content-reference-picker-launch {
    margin-top: 1rem;
  }

  .content-reference-picker-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.54);
    z-index: 1000;
  }

  .content-reference-picker-dialog[hidden],
  .content-reference-picker-overlay[hidden] {
    display: none !important;
  }
  .content-reference-picker-dialog {
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

  .content-reference-picker-dialog__header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: .75rem;
  }

  .content-reference-picker-dialog__title {
    margin: 0;
    font-size: 1.2rem;
  }

  .content-reference-picker-toolbar {
    display: grid;
    grid-template-columns: minmax(15rem, 2fr) minmax(12rem, 1fr) auto;
    gap: .75rem;
    align-items: end;
  }

  .content-reference-picker-toolbar label {
    margin-top: 0;
  }

  .content-reference-picker-toolbar .btn {
    margin-top: .2rem;
  }

  .content-reference-picker-results {
    margin-top: 1rem;
  }

  .content-reference-picker-results__list {
    display: grid;
    gap: .8rem;
    margin: 0;
    padding: 0;
    list-style: none;
  }

  .content-reference-picker-result {
    padding: .85rem 1rem;
    border: 1px solid #d0d5dd;
    border-radius: .75rem;
    background: #f8fafc;
  }

  .content-reference-picker-result--with-thumb {
    display: grid;
    grid-template-columns: 5.5rem minmax(0, 1fr);
    gap: .9rem;
    align-items: start;
  }

  .content-reference-picker-result__thumb {
    width: 5.5rem;
    aspect-ratio: 1 / 1;
    border-radius: .6rem;
    overflow: hidden;
    background: #e2e8f0;
    border: 1px solid #d0d5dd;
  }

  .content-reference-picker-result__thumb img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
  }

  .content-reference-picker-result__meta {
    margin: 0 0 .35rem;
    color: #475467;
    font-size: .86rem;
    font-weight: 700;
  }

  .content-reference-picker-result__title {
    margin: 0;
    font-size: 1rem;
  }

  .content-reference-picker-result__path {
    display: block;
    margin-top: .35rem;
    color: #475467;
    font-size: .85rem;
    word-break: break-word;
  }

  .content-reference-picker-result__excerpt {
    margin: .55rem 0 0;
    color: #344054;
  }

  .content-reference-picker-result__actions {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    align-items: center;
    margin-top: .85rem;
  }

  .content-reference-picker-result__actions a {
    color: #0f4c81;
  }

  @media (max-width: 720px) {
    .content-reference-picker-dialog {
      width: calc(100vw - 1rem);
      max-height: calc(100vh - 1rem);
      padding: .9rem;
    }

    .content-reference-picker-toolbar {
      grid-template-columns: 1fr;
    }

    .content-reference-picker-result--with-thumb {
      grid-template-columns: 1fr;
    }

    .content-reference-picker-result__thumb {
      width: 100%;
      aspect-ratio: 16 / 9;
    }
  }
</style>
<?php
    }
    ?>
    <div class="content-reference-picker-launch">
      <button type="button"
              class="btn"
              id="<?= h($pickerId) ?>-picker-open"
              aria-haspopup="dialog"
              aria-controls="<?= h($pickerId) ?>-picker-dialog"
              aria-expanded="false"
              aria-describedby="<?= h($pickerId) ?>-picker-launch-help">
        Vložit odkaz nebo HTML z webu
      </button>
      <small id="<?= h($pickerId) ?>-picker-launch-help" class="field-help">Vyhledejte existující článek, stránku, formulář, anketu, médium nebo jiný veřejný obsah a vložte ho rovnou do textu jako odkaz, HTML blok, fotogalerii, obrázek, přehrávač nebo obsahový snippet.</small>
    </div>

    <div id="<?= h($pickerId) ?>-picker-overlay" class="content-reference-picker-overlay" hidden style="display:none"></div>
    <section id="<?= h($pickerId) ?>-picker-dialog"
             class="content-reference-picker-dialog"
             role="dialog"
             aria-modal="true"
             aria-labelledby="<?= h($pickerId) ?>-picker-title"
             aria-describedby="<?= h($pickerId) ?>-picker-description"
             hidden
             style="display:none">
      <div class="content-reference-picker-dialog__header">
        <div>
          <h2 id="<?= h($pickerId) ?>-picker-title" class="content-reference-picker-dialog__title">Vložit odkaz nebo HTML z webu</h2>
          <p id="<?= h($pickerId) ?>-picker-description" class="field-help" style="margin-top:.35rem">Tento nástroj je dostupný v režimu čistého HTML editoru. Vyhledaný obsah můžete vložit jako inline odkaz, HTML blok a podle typu výsledku i jako fotogalerii, obrázek, přehrávač, formulář, anketu nebo obsahovou kartu.</p>
        </div>
        <button type="button" class="btn" id="<?= h($pickerId) ?>-picker-close">Zavřít</button>
      </div>

      <fieldset style="margin:0;border:1px solid #ccc;padding:.5rem 1rem">
        <legend>Vyhledání obsahu</legend>
        <div class="content-reference-picker-toolbar">
          <div>
            <label for="<?= h($pickerId) ?>-picker-query">Hledat obsah webu</label>
            <input type="text"
                   id="<?= h($pickerId) ?>-picker-query"
                   autocomplete="off"
                   aria-describedby="<?= h($pickerId) ?>-picker-query-help <?= h($pickerId) ?>-picker-selection-help">
          </div>
          <div>
            <label for="<?= h($pickerId) ?>-picker-type">Typ obsahu</label>
            <select id="<?= h($pickerId) ?>-picker-type">
              <?php foreach ($contentPickerTypes as $pickerTypeValue => $pickerTypeLabel): ?>
                <option value="<?= h($pickerTypeValue) ?>"><?= h($pickerTypeLabel) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <button type="button" class="btn" id="<?= h($pickerId) ?>-picker-submit">Vyhledat</button>
          </div>
        </div>
        <small id="<?= h($pickerId) ?>-picker-query-help" class="field-help">Hledání prochází veřejně dostupný obsah webu, formuláře, ankety i knihovnu médií.</small>
        <small id="<?= h($pickerId) ?>-picker-selection-help" class="field-help">Pokud máte v editoru označený text, při vložení odkazu se použije jako text odkazu. Jinak se vloží název nalezené položky.</small>
      </fieldset>

      <p id="<?= h($pickerId) ?>-picker-status" aria-live="polite" aria-atomic="true" style="margin:.85rem 0 0;color:#555;font-size:.92rem;line-height:1.45"></p>
      <div id="<?= h($pickerId) ?>-picker-results" class="content-reference-picker-results" aria-live="polite"></div>
    </section>

    <script nonce="<?= cspNonce() ?>">
    (function () {
        const textarea = document.getElementById(<?= json_encode($textareaId, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const openButton = document.getElementById(<?= json_encode($pickerId . '-picker-open', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const closeButton = document.getElementById(<?= json_encode($pickerId . '-picker-close', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const overlay = document.getElementById(<?= json_encode($pickerId . '-picker-overlay', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const dialog = document.getElementById(<?= json_encode($pickerId . '-picker-dialog', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const queryInput = document.getElementById(<?= json_encode($pickerId . '-picker-query', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const typeSelect = document.getElementById(<?= json_encode($pickerId . '-picker-type', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const searchButton = document.getElementById(<?= json_encode($pickerId . '-picker-submit', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const statusNode = document.getElementById(<?= json_encode($pickerId . '-picker-status', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const resultsNode = document.getElementById(<?= json_encode($pickerId . '-picker-results', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>);
        const liveRegion = document.getElementById('a11y-live');
        const endpoint = <?= json_encode($endpoint, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const focusableSelector = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
        let lastTrigger = null;
        let savedSelection = { start: 0, end: 0, text: '' };
        let previousBodyOverflow = '';
        let isSearching = false;

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

        const buildImageBlock = (item, imageUrlOverride = '') => {
            const imageUrl = typeof imageUrlOverride === 'string' && imageUrlOverride.trim() !== ''
                ? imageUrlOverride
                : (typeof item.url === 'string' ? item.url : '');
            if (!imageUrl) {
                return '';
            }

            const altText = typeof item.media_alt === 'string' && item.media_alt.trim() !== ''
                ? item.media_alt
                : item.title;
            const lines = [
                '<figure class="content-media content-media--image">',
                '  <img src="' + escapeHtml(imageUrl) + '" alt="' + escapeHtml(altText) + '" loading="lazy">',
            ];

            if (item.title) {
                lines.push('  <figcaption>' + escapeHtml(item.title) + '</figcaption>');
            }

            lines.push('</figure>');
            return lines.join('\n');
        };

        const buildActionSnippet = (action, item) => {
            switch (action.kind) {
                case 'link':
                    return buildInlineLink(item);
                case 'html_block':
                    return buildHtmlBlock(item);
                case 'image_html':
                    return buildImageBlock(item, typeof action.url === 'string' ? action.url : '');
                default:
                    return typeof action.snippet === 'string' ? action.snippet : '';
            }
        };

        const buildActionStatus = (action) => {
            if (typeof action.status === 'string' && action.status.trim() !== '') {
                return action.status;
            }
            return 'Do textu byl vložen obsah.';
        };

        const clearResults = () => {
            resultsNode.innerHTML = '';
            resultsNode.removeAttribute('aria-busy');
        };

        const setSearchPending = (pending) => {
            isSearching = pending;
            searchButton.setAttribute('aria-disabled', pending ? 'true' : 'false');
        };

        const closeDialog = (restoreFocus = true) => {
            dialog.hidden = true;
            dialog.style.display = 'none';
            overlay.hidden = true;
            overlay.style.display = 'none';
            openButton.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = previousBodyOverflow;
            if (restoreFocus) {
                (lastTrigger || openButton).focus();
            }
        };

        const openDialog = () => {
            rememberSelection();
            lastTrigger = document.activeElement;
            previousBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            overlay.hidden = false;
            overlay.style.display = '';
            dialog.hidden = false;
            dialog.style.display = '';
            openButton.setAttribute('aria-expanded', 'true');
            if (!statusNode.textContent.trim()) {
                setStatus('Zadejte alespoň 2 znaky a vyhledejte obsah.');
            }
            queryInput.focus();
            queryInput.select();
        };

        const renderResults = (items) => {
            clearResults();

            if (!items.length) {
                return;
            }

            const list = document.createElement('ul');
            list.className = 'content-reference-picker-results__list';

            items.forEach((item) => {
                const listItem = document.createElement('li');
                const article = document.createElement('article');
                article.className = 'content-reference-picker-result';
                let contentRoot = article;

                if (typeof item.thumbnail_url === 'string' && item.thumbnail_url.trim() !== '') {
                    article.classList.add('content-reference-picker-result--with-thumb');

                    const thumb = document.createElement('div');
                    thumb.className = 'content-reference-picker-result__thumb';
                    const thumbImage = document.createElement('img');
                    thumbImage.src = item.thumbnail_url;
                    thumbImage.alt = '';
                    thumbImage.loading = 'lazy';
                    thumb.appendChild(thumbImage);
                    article.appendChild(thumb);

                    contentRoot = document.createElement('div');
                    article.appendChild(contentRoot);
                }

                const meta = document.createElement('p');
                meta.className = 'content-reference-picker-result__meta';
                meta.textContent = item.kind_label;
                contentRoot.appendChild(meta);

                const heading = document.createElement('h3');
                heading.className = 'content-reference-picker-result__title';
                heading.textContent = item.title;
                contentRoot.appendChild(heading);

                const path = document.createElement('code');
                path.className = 'content-reference-picker-result__path';
                path.textContent = item.path;
                contentRoot.appendChild(path);

                if (item.excerpt) {
                    const excerpt = document.createElement('p');
                    excerpt.className = 'content-reference-picker-result__excerpt';
                    excerpt.textContent = item.excerpt;
                    contentRoot.appendChild(excerpt);
                }

                const actions = document.createElement('div');
                actions.className = 'content-reference-picker-result__actions';

                const availableActions = Array.isArray(item.insert_actions) && item.insert_actions.length
                    ? item.insert_actions
                    : [
                        { kind: 'link', label: 'Vložit jako odkaz', status: 'Do textu byl vložen odkaz.', block: false },
                        { kind: 'html_block', label: 'Vložit jako HTML blok', status: 'Do textu byl vložen HTML blok.', block: true },
                    ];

                availableActions.forEach((action) => {
                    const snippet = buildActionSnippet(action, item);
                    if (!snippet) {
                        return;
                    }

                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'btn';
                    button.textContent = action.label || 'Vložit';
                    button.setAttribute('aria-label', (action.label || 'Vložit') + ': ' + item.title);
                    button.addEventListener('click', () => {
                        insertSnippet(snippet, !!action.block);
                        closeDialog(false);
                        setStatus(buildActionStatus(action));
                    });
                    actions.appendChild(button);
                });

                const previewLink = document.createElement('a');
                previewLink.href = item.url;
                previewLink.target = '_blank';
                previewLink.rel = 'noopener noreferrer';
                previewLink.textContent = 'Zobrazit na webu';
                previewLink.setAttribute('aria-label', 'Zobrazit na webu: ' + item.title + ' (otevře se v novém okně)');
                actions.appendChild(previewLink);

                contentRoot.appendChild(actions);
                listItem.appendChild(article);
                list.appendChild(listItem);
            });

            resultsNode.appendChild(list);
        };

        const runSearch = async () => {
            if (isSearching) {
                return;
            }

            const query = queryInput.value.trim();

            if (query.length < 2) {
                clearResults();
                setStatus('Zadejte alespoň 2 znaky.');
                return;
            }

            resultsNode.innerHTML = '';
            resultsNode.setAttribute('aria-busy', 'true');
            setStatus('Hledám obsah…');
            setSearchPending(true);

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
                setSearchPending(false);
                if (!dialog.hidden && !dialog.contains(document.activeElement)) {
                    searchButton.focus();
                }
            }
        };

        openButton.addEventListener('mousedown', rememberSelection);
        openButton.addEventListener('click', openDialog);
        if (closeButton) {
            closeButton.addEventListener('click', () => closeDialog());
        }
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
    <?php
}
