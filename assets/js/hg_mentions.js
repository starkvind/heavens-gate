/* global window, document, fetch */
(function(){
  'use strict';

  function createDropdown() {
    var box = document.createElement('div');
    box.className = 'hg-mention-box';
    box.style.display = 'none';
    box.innerHTML = '<div class="hg-mention-list"></div>';
    document.body.appendChild(box);
    return box;
  }

  function positionDropdown(box, anchorEl) {
    if (!box || !anchorEl) return;
    var rect = anchorEl.getBoundingClientRect();
    box.style.left = (rect.left + window.scrollX) + 'px';
    box.style.top = (rect.bottom + window.scrollY + 6) + 'px';
    box.style.minWidth = Math.max(240, rect.width) + 'px';
  }

  function fetchMentions(type, q) {
    var query = encodeURIComponent(q || '');
    var kind = encodeURIComponent(type || '');
    var endpoint = '/ajax/mentions?type=' + kind + '&q=' + query;

    return fetch(endpoint, { credentials: 'same-origin' })
      .then(function(r){
        if (!r || !r.ok) throw new Error('HTTP ' + (r ? r.status : 0));
        return r.json();
      })
      .then(function(data){
        return data && data.ok && Array.isArray(data.items) ? data.items : [];
      })
      .catch(function(){ return []; });
  }

  function renderList(box, items, onPick) {
    var list = box.querySelector('.hg-mention-list');
    list.innerHTML = '';
    if (!items.length) {
      list.innerHTML = '<div class="hg-mention-empty">No results</div>';
      return;
    }
    items.forEach(function(it, idx){
      var row = document.createElement('div');
      row.className = 'hg-mention-item';
      row.dataset.index = String(idx);
      var meta = it.type;
      if (it.chronicle_name) meta += ' · ' + it.chronicle_name;
      if (it.system_name) meta += ' · ' + it.system_name;
      row.textContent = it.label + ' (' + meta + ')';
      row.addEventListener('click', function(){ onPick(it); });
      list.appendChild(row);
    });
  }

  function setupKeyboard(box, items, onPick) {
    var active = 0;
    function highlight() {
      var rows = box.querySelectorAll('.hg-mention-item');
      rows.forEach(function(r){ r.classList.remove('active'); });
      if (rows[active]) rows[active].classList.add('active');
    }
    highlight();
    return function onKey(e) {
      if (!items.length) return;
      if (e.key === 'ArrowDown') {
        active = (active + 1) % items.length;
        highlight();
        e.preventDefault();
      } else if (e.key === 'ArrowUp') {
        active = (active - 1 + items.length) % items.length;
        highlight();
        e.preventDefault();
      } else if (e.key === 'Enter') {
        onPick(items[active]);
        e.preventDefault();
      } else if (e.key === 'Escape') {
        box.style.display = 'none';
      }
    };
  }

  function attachQuill(quill, opts) {
    if (!quill) return;
    var types = (opts && opts.types) ? opts.types : [];
    var dropdown = createDropdown();
    var lastQuery = '';
    var lastType = '';
    var keyHandler = null;
    var debounce = null;

    function closeBox() {
      dropdown.style.display = 'none';
      if (keyHandler) {
        quill.root.removeEventListener('keydown', keyHandler);
        keyHandler = null;
      }
    }

    function openBox(items, onPick) {
      renderList(dropdown, items, onPick);
      dropdown.style.display = 'block';
      positionDropdown(dropdown, quill.root);
      if (keyHandler) quill.root.removeEventListener('keydown', keyHandler);
      keyHandler = setupKeyboard(dropdown, items, onPick);
      quill.root.addEventListener('keydown', keyHandler);
    }

    function insertMention(it, range, startIndex, tokenLen) {
      var href = it.href || '';
      var label = it.label || it.pretty_id || ('#' + it.id);
      quill.deleteText(startIndex, tokenLen, 'user');
      quill.insertText(startIndex, label, 'link', href, 'user');
      quill.insertText(startIndex + label.length, ' ', 'user');
      quill.setSelection(startIndex + label.length + 1, 0, 'user');
      closeBox();
    }

    quill.on('text-change', function(){
      var range = quill.getSelection();
      if (!range) return closeBox();
      var text = quill.getText(0, range.index);
      var match = text.match(/@([a-z_]+)(?:\s+([^@\n]*))?$/i);
      if (!match) return closeBox();
      var type = (match[1] || '').toLowerCase();
      if (types.indexOf(type) === -1) return closeBox();
      var query = (match[2] || '').trim();
      if (type !== lastType || query !== lastQuery) {
        lastType = type;
        lastQuery = query;
        if (debounce) clearTimeout(debounce);
        debounce = setTimeout(function(){
          fetchMentions(type, query).then(function(items){
            if (!items.length) return openBox([], function(){});
            openBox(items, function(it){
              var startIndex = range.index - match[0].length;
              insertMention(it, range, startIndex, match[0].length);
            });
          });
        }, 120);
      }
    });

    quill.on('selection-change', function(range){
      if (!range) closeBox();
    });
  }

  function attachTextarea(textarea, opts) {
    if (!textarea) return;
    var types = (opts && opts.types) ? opts.types : [];
    var dropdown = createDropdown();
    var debounce = null;
    var keyHandler = null;
    var lastQuery = '';
    var lastType = '';

    function closeBox() {
      dropdown.style.display = 'none';
      if (keyHandler) {
        textarea.removeEventListener('keydown', keyHandler);
        keyHandler = null;
      }
    }

    function openBox(items, onPick) {
      renderList(dropdown, items, onPick);
      dropdown.style.display = 'block';
      positionDropdown(dropdown, textarea);
      if (keyHandler) textarea.removeEventListener('keydown', keyHandler);
      keyHandler = setupKeyboard(dropdown, items, onPick);
      textarea.addEventListener('keydown', keyHandler);
    }

    function insertToken(it, startIndex, tokenLen) {
      var token = '[@' + it.type + ':' + it.id + ']';
      var val = textarea.value;
      textarea.value = val.slice(0, startIndex) + token + ' ' + val.slice(startIndex + tokenLen);
      var caret = startIndex + token.length + 1;
      textarea.setSelectionRange(caret, caret);
      closeBox();
    }

    textarea.addEventListener('keyup', function(){
      var pos = textarea.selectionStart || 0;
      var text = textarea.value.slice(0, pos);
      var match = text.match(/@([a-z_]+)(?:\s+([^@\n]*))?$/i);
      if (!match) return closeBox();
      var type = (match[1] || '').toLowerCase();
      if (types.indexOf(type) === -1) return closeBox();
      var query = (match[2] || '').trim();
      if (type !== lastType || query !== lastQuery) {
        lastType = type;
        lastQuery = query;
        if (debounce) clearTimeout(debounce);
        debounce = setTimeout(function(){
          fetchMentions(type, query).then(function(items){
            if (!items.length) return openBox([], function(){});
            openBox(items, function(it){
              var startIndex = pos - match[0].length;
              insertToken(it, startIndex, match[0].length);
            });
          });
        }, 120);
      }
    });

    textarea.addEventListener('blur', function(){ setTimeout(closeBox, 200); });
  }


  function attachJodit(editor, opts) {
    var root = editor.editor;
    var types = opts.types || [];
    var box = createDropdown();
    var serial = 0, timer = null, keyboard = null, active = false;
    function close() {
      serial++;
      clearTimeout(timer);
      box.style.display = 'none';
      if (keyboard) root.removeEventListener('keydown', keyboard, true);
      keyboard = null;
      active = false;
    }
    function token() {
      var sel = window.getSelection();
      if (!sel || !sel.isCollapsed || !sel.rangeCount) return null;
      var node = sel.anchorNode;
      if (!node || node.nodeType !== 3 || !root.contains(node) ||
          node.parentElement.closest('a,pre,code')) return null;
      var end = sel.anchorOffset;
      var match = node.data.slice(0,end).match(/@([a-z_]+)(?:\s+([^@\n]*))?$/i);
      if (!match || !types.includes(match[1].toLowerCase())) return null;
      return {node:node,end:end,start:end-match[0].length,raw:match[0],
        type:match[1].toLowerCase(),query:(match[2]||'').trim()};
    }
    function same(a,b) {
      return a && b && a.node===b.node && a.end===b.end && a.raw===b.raw;
    }
    function pick(item, original) {
      var now = token();
      if (!same(now, original)) return close();
      if (!types.includes(item.type) || !/^[1-9]\d*$/.test(String(item.id))) return close();
      var range = document.createRange();
      range.setStart(now.node,now.start);
      range.setEnd(now.node,now.end);
      var sel = window.getSelection();
      sel.removeAllRanges(); sel.addRange(range);
      // Server resolves this inert token into a canonical link on save.
      editor.s.insertHTML('[@' + item.type + ':' + item.id + '] ');
      close();
    }
    function changed() {
      close();
      var original = token();
      if (!original) return;
      var version = serial;
      timer = setTimeout(function(){
        fetchMentions(original.type,original.query).then(function(items){
          if (version!==serial || !same(token(),original)) return;
          renderList(box,items,function(it){pick(it,original);});
          positionDropdown(box,root);
          box.style.display='block';
          active=true;
          keyboard=setupKeyboard(box,items,function(it){pick(it,original);});
          root.addEventListener('keydown',keyboard,true);
        });
      },120);
    }
    function mouseDown(e){e.preventDefault();}
    function keyup(e){if (!active || !['ArrowDown','ArrowUp','Enter','Escape'].includes(e.key)) changed();}
    root.addEventListener('input',changed);
    root.addEventListener('keyup',keyup);
    root.addEventListener('blur',close);
    box.addEventListener('mousedown',mouseDown);
    return function(){
      close();
      root.removeEventListener('input',changed);
      root.removeEventListener('keyup',keyup);
      root.removeEventListener('blur',close);
      box.remove();
    };
  }

  function attachAuto() {
    if (!document.querySelectorAll) return;
    document.querySelectorAll('[data-hg-mentions-quill]').forEach(function(el){
      var id = el.getAttribute('data-hg-mentions-quill');
      var q = window.__hgQuillMap && window.__hgQuillMap[id];
      if (q) attachQuill(q, { types: (el.getAttribute('data-mentions') || '').split(',').filter(Boolean) });
    });
    document.querySelectorAll('textarea.hg-mention-input').forEach(function(el){
      var types = (el.getAttribute('data-mentions') || '').split(',').filter(Boolean);
      attachTextarea(el, { types: types });
    });
  }

  window.hgMentions = {
    attachQuill: attachQuill,
    attachJodit: attachJodit,
    attachTextarea: attachTextarea,
    attachAuto: attachAuto
  };
})();
