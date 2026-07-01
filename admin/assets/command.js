(function () {
  'use strict';

  var dialog = document.getElementById('admin-command-dialog');
  var overlay = document.getElementById('admin-command-overlay');
  var openButton = document.getElementById('admin-command-open');
  var closeButton = document.getElementById('admin-command-close');
  var input = document.getElementById('admin-command-dialog-q');
  var form = dialog ? dialog.querySelector('.admin-command-dialog__search') : null;
  var resultsList = document.getElementById('admin-command-results');
  var status = document.getElementById('admin-command-status');
  var lastFocused = null;
  var searchTimer = null;
  var activeRequest = null;

  if (!dialog || !overlay || !openButton || !closeButton || !input || !form || !resultsList || !status) {
    return;
  }

  function isTypingTarget(target) {
    if (!target || target === document.body) {
      return false;
    }
    return target.matches('input, textarea, select, [contenteditable="true"]');
  }

  function setCsrfToken(token) {
    if (typeof token === 'string' && token !== '') {
      dialog.dataset.csrfToken = token;
    }
  }

  function clearResults(message) {
    resultsList.innerHTML = '';
    var item = document.createElement('li');
    item.className = 'admin-command-empty';
    item.textContent = message;
    resultsList.appendChild(item);
  }

  function renderResults(items) {
    resultsList.innerHTML = '';
    if (!items.length) {
      clearResults('Žádné výsledky.');
      status.textContent = 'Žádné výsledky.';
      return;
    }

    items.forEach(function (item) {
      var row = document.createElement('li');
      row.className = 'admin-command-result';

      var body = document.createElement('div');
      body.className = 'admin-command-result__body';

      var link = document.createElement('a');
      link.className = 'admin-command-result__link';
      link.href = item.url || '#';
      link.textContent = item.label || 'Položka';
      body.appendChild(link);

      var meta = document.createElement('p');
      meta.className = 'admin-command-result__meta';
      meta.textContent = item.description || '';
      body.appendChild(meta);

      if (item.badge) {
        var badge = document.createElement('span');
        badge.className = 'admin-command-result__badge';
        badge.textContent = item.badge;
        body.appendChild(badge);
      }

      row.appendChild(body);

      if (item.pin_available) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-muted admin-command-result__pin';
        button.dataset.commandShortcutAction = item.pinned ? 'unpin' : 'pin';
        button.dataset.commandItemType = item.type || '';
        button.dataset.commandItemKey = item.key || '';
        button.textContent = item.pinned ? 'Odepnout' : 'Připnout';
        row.appendChild(button);
      }

      resultsList.appendChild(row);
    });

    status.textContent = items.length === 1 ? 'Nalezen 1 výsledek.' : 'Nalezeno ' + items.length + ' výsledků.';
  }

  function runSearch() {
    var query = input.value.trim();
    if (activeRequest) {
      activeRequest.abort();
    }
    activeRequest = new AbortController();
    status.textContent = 'Hledám v administraci.';

    var url = new URL(dialog.dataset.searchUrl || '', window.location.origin);
    url.searchParams.set('q', query);
    url.searchParams.set('limit', '20');

    fetch(url.toString(), {
      credentials: 'same-origin',
      headers: {'Accept': 'application/json'},
      signal: activeRequest.signal
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('search failed');
      }
      return response.json();
    }).then(function (payload) {
      setCsrfToken(payload.csrf_token);
      renderResults(Array.isArray(payload.results) ? payload.results : []);
    }).catch(function (error) {
      if (error.name === 'AbortError') {
        return;
      }
      clearResults('Hledání se nepodařilo. Zkuste běžnou stránku výsledků.');
      status.textContent = 'Hledání se nepodařilo.';
    });
  }

  function scheduleSearch() {
    window.clearTimeout(searchTimer);
    searchTimer = window.setTimeout(runSearch, 220);
  }

  function getFocusable() {
    return Array.prototype.slice.call(dialog.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
      .filter(function (element) {
        return element.offsetParent !== null || element === input;
      });
  }

  function openDialog(seed) {
    lastFocused = document.activeElement;
    overlay.hidden = false;
    dialog.hidden = false;
    document.body.classList.add('admin-command-open');
    if (typeof seed === 'string') {
      input.value = seed;
    }
    input.focus();
    input.select();
    runSearch();
  }

  function closeDialog() {
    overlay.hidden = true;
    dialog.hidden = true;
    document.body.classList.remove('admin-command-open');
    status.textContent = '';
    if (activeRequest) {
      activeRequest.abort();
      activeRequest = null;
    }
    if (lastFocused && typeof lastFocused.focus === 'function') {
      lastFocused.focus();
    }
  }

  openButton.addEventListener('click', function () {
    openDialog('');
  });

  closeButton.addEventListener('click', closeDialog);
  overlay.addEventListener('click', closeDialog);

  input.addEventListener('input', scheduleSearch);

  form.addEventListener('submit', function (event) {
    var firstLink = resultsList.querySelector('.admin-command-result__link');
    if (firstLink && input.value.trim() !== '') {
      event.preventDefault();
      window.location.href = firstLink.href;
    }
  });

  resultsList.addEventListener('click', function (event) {
    var button = event.target.closest('[data-command-shortcut-action]');
    if (!button) {
      return;
    }
    var formData = new FormData();
    formData.append('csrf_token', dialog.dataset.csrfToken || '');
    formData.append('json', '1');
    formData.append('action', button.dataset.commandShortcutAction || '');
    formData.append('item_type', button.dataset.commandItemType || '');
    formData.append('item_key', button.dataset.commandItemKey || '');
    button.disabled = true;

    fetch(dialog.dataset.shortcutUrl || '', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Accept': 'application/json'},
      body: formData
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload.success) {
          throw payload;
        }
        return payload;
      });
    }).then(function (payload) {
      setCsrfToken(payload.csrf_token);
      status.textContent = payload.message || 'Zkratka upravena.';
      runSearch();
    }).catch(function (payload) {
      if (payload && payload.csrf_token) {
        setCsrfToken(payload.csrf_token);
      }
      status.textContent = payload && payload.message ? payload.message : 'Zkratku se nepodařilo upravit.';
      button.disabled = false;
    });
  });

  document.addEventListener('keydown', function (event) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k' && !isTypingTarget(event.target)) {
      event.preventDefault();
      openDialog('');
      return;
    }
    if (dialog.hidden) {
      return;
    }
    if (event.key === 'Escape') {
      event.preventDefault();
      closeDialog();
      return;
    }
    if (event.key !== 'Tab') {
      return;
    }
    var focusable = getFocusable();
    if (!focusable.length) {
      event.preventDefault();
      input.focus();
      return;
    }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });
})();
