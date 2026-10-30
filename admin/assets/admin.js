(function () {
  'use strict';

  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var editor = document.getElementById('editor');
  var nav = document.getElementById('sections');
  var statusEl = document.getElementById('status');
  var saveBtn = document.getElementById('save');
  var errorBox = document.getElementById('errors');
  var dlg = document.getElementById('media');
  var state = { schema: null, content: null, backups: [], section: 'teksten', dirty: false };
  var uid = 0;

  function el(tag, props, children) {
    var node = document.createElement(tag);
    props = props || {};
    Object.keys(props).forEach(function (k) {
      if (k === 'text') node.textContent = props[k];
      else if (k === 'class') node.className = props[k];
      else if (k === 'on') Object.keys(props.on).forEach(function (ev) { node.addEventListener(ev, props.on[ev]); });
      else if (props[k] !== null && props[k] !== undefined && props[k] !== false) node.setAttribute(k, props[k] === true ? '' : String(props[k]));
    });
    (children || []).forEach(function (c) { if (c) node.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return node;
  }

  function api(action, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { Accept: 'application/json' } };
    if (init.method !== 'GET') init.headers['X-CSRF-Token'] = csrf;
    if (opts.body) {
      init.headers['Content-Type'] = 'application/json';
      init.body = JSON.stringify(opts.body);
    }
    if (opts.form) init.body = opts.form;
    return fetch('api.php?action=' + encodeURIComponent(action), init).then(function (r) {
      return r.json().catch(function () { return null; }).then(function (data) {
        if (r.status === 401) { window.location.href = 'login.php'; }
        if (!r.ok || !data || data.ok !== true) {
          var err = new Error((data && data.error) || 'Er ging iets mis. Probeer het opnieuw.');
          err.errors = data && data.errors;
          throw err;
        }
        return data;
      });
    });
  }

  function setStatus(text, cls) {
    statusEl.textContent = text;
    statusEl.className = 'status' + (cls ? ' ' + cls : '');
  }

  function markDirty() {
    if (state.dirty) return;
    state.dirty = true;
    saveBtn.disabled = false;
    setStatus('Er zijn wijzigingen die nog niet zijn opgeslagen.', 'dirty');
  }

  function clearDirty(text) {
    state.dirty = false;
    saveBtn.disabled = true;
    setStatus(text || 'Alles is opgeslagen.', text ? 'ok' : '');
  }

  function showErrors(err) {
    errorBox.textContent = '';
    errorBox.appendChild(el('strong', { text: err.message }));
    if (err.errors && err.errors.length) {
      errorBox.appendChild(el('ul', {}, err.errors.map(function (m) { return el('li', { text: m }); })));
    }
    errorBox.hidden = false;
  }

  function hideErrors() { errorBox.hidden = true; errorBox.textContent = ''; }

  function textControl(field, obj, key) {
    var id = 'f' + (++uid);
    var multiline = field.type === 'textarea' || field.type === 'lines';
    var input = multiline ? el('textarea', { id: id, rows: field.type === 'lines' ? 6 : 3 }) : el('input', { id: id, type: 'text' });
    var value = obj[key];
    input.value = field.type === 'lines' ? (Array.isArray(value) ? value.join('\n') : '') : (value || '');
    var counter = null;
    if (field.type !== 'lines') {
      input.maxLength = field.max * 2;
      counter = el('div', { class: 'count', 'aria-hidden': 'true' });
    }
    function update() {
      if (field.type === 'lines') {
        obj[key] = input.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
      } else {
        obj[key] = input.value;
        counter.textContent = input.value.length + ' / ' + field.max;
        counter.style.color = input.value.length > field.max ? '#a3361f' : '';
      }
      markDirty();
    }
    input.addEventListener('input', update);
    if (counter) counter.textContent = input.value.length + ' / ' + field.max;
    return el('div', {}, [
      el('label', { for: id }, [field.label, field.optional ? ' (optioneel)' : '', field.help ? el('span', { class: 'help', text: field.help }) : null]),
      input,
      counter
    ]);
  }

  function boolControl(field, obj, key) {
    var id = 'f' + (++uid);
    var input = el('input', { id: id, type: 'checkbox' });
    input.checked = obj[key] === true;
    input.addEventListener('change', function () { obj[key] = input.checked; markDirty(); });
    return el('div', {}, [el('label', { class: 'check', for: id }, [input, field.label])]);
  }

  function imageControl(field, obj, key) {
    var wrap = el('div', { class: 'photo-field' });
    function draw() {
      wrap.textContent = '';
      var path = obj[key];
      wrap.appendChild(path
        ? el('img', { class: 'thumb', src: '../' + path, alt: '' })
        : el('div', { class: 'thumb empty', text: 'Geen foto gekozen' }));
      var buttons = el('div', {}, [
        el('button', {
          type: 'button', class: 'btn',
          text: path ? 'Andere foto kiezen' : 'Foto kiezen',
          on: { click: function () { openMedia(function (p) { obj[key] = p; markDirty(); draw(); }); } }
        })
      ]);
      if (path && field.optional) {
        buttons.appendChild(document.createTextNode(' '));
        buttons.appendChild(el('button', { type: 'button', class: 'btn danger', text: 'Foto weghalen', on: { click: function () { obj[key] = ''; markDirty(); draw(); } } }));
      }
      wrap.appendChild(buttons);
    }
    draw();
    return el('div', {}, [el('span', { class: 'label', text: field.label + (field.optional ? ' (optioneel)' : '') }), field.help ? el('span', { class: 'help', text: field.help }) : null, wrap]);
  }

  function control(field, obj, key) {
    if (field.type === 'bool') return boolControl(field, obj, key);
    if (field.type === 'image') return imageControl(field, obj, key);
    return textControl(field, obj, key);
  }

  function blankItem(section) {
    var item = {};
    section.fields.forEach(function (f) {
      item[f.key] = f.type === 'bool' ? false : (f.type === 'lines' ? [] : '');
    });
    return item;
  }

  function renderList(section, container) {
    var items = state.content[section.id];
    var list = el('div');
    container.appendChild(list);

    function draw(openIndex) {
      list.textContent = '';
      items.forEach(function (item, i) {
        var titleEl = el('span', { class: 'item-title', text: (i + 1) + '. ' + (item[section.itemTitle] || 'Nieuw item') });
        var tools = el('span', { class: 'item-tools' }, [
          el('button', { type: 'button', class: 'btn small', text: 'Omhoog', 'aria-label': 'Verplaats omhoog', disabled: i === 0, on: { click: function (e) { e.preventDefault(); move(i, -1); } } }),
          el('button', { type: 'button', class: 'btn small', text: 'Omlaag', 'aria-label': 'Verplaats omlaag', disabled: i === items.length - 1, on: { click: function (e) { e.preventDefault(); move(i, 1); } } }),
          el('button', { type: 'button', class: 'btn small danger', text: 'Verwijderen', disabled: items.length <= section.min, on: { click: function (e) { e.preventDefault(); remove(i); } } })
        ]);
        var body = el('div', { class: 'item-body' });
        section.fields.forEach(function (f) {
          var node = control(f, item, f.key);
          if (f.key === section.itemTitle) {
            var input = node.querySelector('input, textarea');
            if (input) input.addEventListener('input', function () { titleEl.textContent = (i + 1) + '. ' + (item[section.itemTitle] || 'Nieuw item'); });
          }
          body.appendChild(node);
        });
        var details = el('details', { class: 'item' }, [el('summary', {}, [titleEl, tools]), body]);
        if (openIndex === i) details.open = true;
        list.appendChild(details);
      });
      addBtn.disabled = items.length >= section.max;
    }
    function move(i, d) {
      var j = i + d;
      if (j < 0 || j >= items.length) return;
      var tmp = items[i]; items[i] = items[j]; items[j] = tmp;
      markDirty(); draw(j);
    }
    function remove(i) {
      if (items.length <= section.min) return;
      if (!window.confirm('Weet u zeker dat u dit item wilt verwijderen?')) return;
      items.splice(i, 1);
      markDirty(); draw(Math.min(i, items.length - 1));
    }
    var addBtn = el('button', {
      type: 'button', class: 'btn', text: section.addLabel || 'Toevoegen',
      on: { click: function () { items.push(blankItem(section)); markDirty(); draw(items.length - 1); } }
    });
    draw(-1);
    container.appendChild(addBtn);
  }

  function renderImages(section, container) {
    section.fields.forEach(function (f) {
      var data = state.content[section.id][f.key];
      var card = el('div', { class: 'card' }, [el('h2', { text: f.label })]);
      card.appendChild(imageControl({ label: 'Foto', type: 'image' }, data, 'src'));
      card.appendChild(textControl({ label: 'Beschrijving van de foto', type: 'text', max: 160, help: 'Voor slechtzienden en Google. Beschrijf kort wat er op de foto staat.' }, data, 'alt'));
      container.appendChild(card);
    });
  }

  function renderFields(section, container) {
    var card = el('div', { class: 'card' });
    section.fields.forEach(function (f) { card.appendChild(control(f, state.content[section.id], f.key)); });
    container.appendChild(card);
  }

  function formatBackup(id) {
    var m = /^(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})$/.exec(id);
    return m ? m[3] + '-' + m[2] + '-' + m[1] + ' ' + m[4] + ':' + m[5] : id;
  }

  function renderBackups(container) {
    container.appendChild(el('p', { class: 'section-intro', text: 'Bij elke keer opslaan bewaart het systeem automatisch de vorige versie. Hier kunt u een eerdere versie terugzetten. De huidige versie wordt dan ook bewaard.' }));
    if (!state.backups.length) {
      container.appendChild(el('p', { class: 'muted', text: 'Er zijn nog geen back-ups. Die ontstaan na de eerste keer opslaan.' }));
      return;
    }
    var card = el('div', { class: 'card' });
    state.backups.forEach(function (id) {
      card.appendChild(el('div', { class: 'row' }, [
        el('span', { text: formatBackup(id) }),
        el('button', {
          type: 'button', class: 'btn small', text: 'Terugzetten',
          on: { click: function () { restore(id); } }
        })
      ]));
    });
    container.appendChild(card);
  }

  function restore(id) {
    var warning = state.dirty ? 'U heeft niet-opgeslagen wijzigingen; die gaan verloren. ' : '';
    if (!window.confirm(warning + 'Versie van ' + formatBackup(id) + ' terugzetten? De website verandert direct.')) return;
    hideErrors();
    api('restore', { method: 'POST', body: { id: id } }).then(function (r) {
      state.content = r.content;
      state.backups = r.backups;
      clearDirty('Eerdere versie teruggezet.');
      render();
    }).catch(showErrors);
  }

  function renderNav() {
    nav.textContent = '';
    var entries = state.schema.sections.map(function (s) { return { id: s.id, title: s.title }; });
    entries.push({ id: 'backups', title: 'Back-ups' });
    entries.forEach(function (s) {
      nav.appendChild(el('button', {
        type: 'button', text: s.title,
        'aria-current': state.section === s.id ? 'true' : null,
        on: { click: function () { state.section = s.id; render(); window.scrollTo(0, 0); } }
      }));
    });
  }

  function render() {
    renderNav();
    editor.textContent = '';
    if (state.section === 'backups') {
      editor.appendChild(el('h1', { text: 'Back-ups' }));
      renderBackups(editor);
      return;
    }
    var section = state.schema.sections.filter(function (s) { return s.id === state.section; })[0];
    editor.appendChild(el('h1', { text: section.title }));
    if (section.intro) editor.appendChild(el('p', { class: 'section-intro', text: section.intro }));
    if (section.kind === 'list') renderList(section, editor);
    else if (section.kind === 'images') renderImages(section, editor);
    else renderFields(section, editor);
  }

  function save() {
    if (!state.dirty) return;
    hideErrors();
    saveBtn.disabled = true;
    setStatus('Opslaan…');
    api('save', { method: 'POST', body: { content: state.content } }).then(function (r) {
      state.backups = r.backups;
      clearDirty('Opgeslagen. De wijzigingen staan op de website.');
    }).catch(function (err) {
      state.dirty = true;
      saveBtn.disabled = false;
      setStatus('Opslaan is niet gelukt.', 'dirty');
      showErrors(err);
    });
  }

  function formatSize(bytes) {
    return bytes > 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(bytes / 1024)) + ' kB';
  }

  var pickCallback = null;
  var mediaMsg = document.getElementById('media-msg');
  var mediaGrid = document.getElementById('media-grid');

  function loadMedia() {
    return api('media').then(function (r) {
      mediaGrid.textContent = '';
      var groups = [
        { kind: 'upload', title: 'Mijn foto’s', empty: 'U heeft nog geen foto’s geüpload.' },
        { kind: 'bundled', title: 'Standaardfoto’s', empty: '' }
      ];
      groups.forEach(function (g) {
        var items = r.items.filter(function (i) { return i.kind === g.kind; });
        mediaGrid.appendChild(el('p', { class: 'media-group', text: g.title }));
        if (!items.length && g.empty) mediaGrid.appendChild(el('p', { class: 'muted', text: g.empty }));
        items.forEach(function (item) {
          var meta = el('div', { class: 'meta' }, [el('span', { text: formatSize(item.size) })]);
          if (item.kind === 'upload') {
            meta.appendChild(el('button', {
              type: 'button', class: 'btn small danger', text: 'Verwijder', disabled: item.used || null,
              title: item.used ? 'Deze foto wordt gebruikt op de website.' : null,
              on: { click: function () { removeMedia(item.path); } }
            }));
          }
          mediaGrid.appendChild(el('div', { class: 'media-item' }, [
            el('button', {
              type: 'button', class: 'pick', 'aria-label': 'Kies deze foto',
              on: { click: function () { if (pickCallback) pickCallback(item.path); dlg.close(); } }
            }, [el('img', { src: '../' + item.path, alt: '', loading: 'lazy' })]),
            meta
          ]));
        });
      });
    }).catch(function (err) { mediaMsg.textContent = err.message; });
  }

  function removeMedia(path) {
    if (!window.confirm('Deze foto definitief verwijderen?')) return;
    api('media_delete', { method: 'POST', body: { path: path } }).then(loadMedia).catch(function (err) { mediaMsg.textContent = err.message; });
  }

  function openMedia(cb) {
    pickCallback = cb;
    mediaMsg.textContent = '';
    dlg.showModal();
    loadMedia();
  }

  document.getElementById('media-close').addEventListener('click', function () { dlg.close(); });
  document.getElementById('media-file').addEventListener('change', function (e) {
    var file = e.target.files && e.target.files[0];
    e.target.value = '';
    if (!file) return;
    if (file.size > 12 * 1024 * 1024) { mediaMsg.textContent = 'Het bestand is te groot (maximaal 12 MB).'; return; }
    var form = new FormData();
    form.append('file', file);
    mediaMsg.textContent = 'Foto wordt verwerkt…';
    api('upload', { method: 'POST', form: form }).then(function () {
      mediaMsg.textContent = 'Foto geüpload. Klik op de foto om hem te kiezen.';
      return loadMedia();
    }).catch(function (err) { mediaMsg.textContent = err.message; });
  });

  saveBtn.addEventListener('click', save);
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
  });
  window.addEventListener('beforeunload', function (e) {
    if (state.dirty) { e.preventDefault(); e.returnValue = ''; }
  });

  api('load').then(function (r) {
    state.schema = r.schema;
    state.content = r.content;
    state.backups = r.backups;
    clearDirty();
    render();
  }).catch(function (err) {
    editor.textContent = '';
    editor.appendChild(el('p', { class: 'alert', text: err.message }));
  });
})();
