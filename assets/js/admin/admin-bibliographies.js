(function () {
  'use strict';
  var http = window.HGAdminHttp;
  var rows = [];
  var details = {};
  var deleteId = 0;
  var filter = document.getElementById('bibFilter');
  var tbody = document.getElementById('bibRows');
  var status = document.getElementById('bibStatus');
  var modal = document.getElementById('bibModal');
  var deleteModal = document.getElementById('bibDeleteModal');
  var form = document.getElementById('bibForm');
  if (!http || !tbody || !form) return;

  function endpoint(params) {
    var url = new URL('/talim', window.location.origin);
    url.searchParams.set('s', 'admin_bibliographies');
    url.searchParams.set('ajax', '1');
    Object.keys(params || {}).forEach(function (key) {
      url.searchParams.set(key, params[key]);
    });
    return url.toString();
  }
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, function (x) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[x];
    });
  }
  function message(text, bad) {
    status.textContent = text || '';
    status.style.color = bad ? '#e79c9c' : '';
    if (bad) http.notify(text, 'error', 6000);
  }
  function findRow(id) {
    return rows.find(function (row) { return Number(row.id) === Number(id); });
  }
  function render() {
    var query = String(filter.value || '').toLocaleLowerCase().trim();
    var list = rows.filter(function (r) {
      return [r.id, r.name, r.publisher, r.year, r.pretty_id, r.description].join(' ')
        .toLocaleLowerCase().indexOf(query) >= 0;
    });
    if (!list.length) {
      tbody.innerHTML = '<tr><td colspan="8">No hay coincidencias.</td></tr>';
      return;
    }
    tbody.innerHTML = list.map(function (r) {
      var id = Number(r.id), active = Number(r.active_count || 0);
      var archive = Number(r.archive_count || 0);
      var total = active + archive;
      var label = escapeHtml(r.name);
      return '<tr>'
        + '<td>' + id + '</td>'
        + '<td><strong>' + label + '</strong><div class="adm-help-text">'
        + escapeHtml(r.pretty_id || 'Sin slug') + '</div></td>'
        + '<td>' + escapeHtml(r.year) + '</td>'
        + '<td>' + escapeHtml(r.publisher) + '</td>'
        + '<td>' + escapeHtml(r.sort_order) + '</td>'
        + '<td>' + active + '</td>'
        + '<td>' + archive + '</td>'
        + '<td><button class="btn btn-small" type="button" data-action="usage" data-id="' + id + '">Usos</button> '
        + '<button class="btn btn-small" type="button" data-action="edit" data-id="' + id + '">Editar</button> '
        + '<button class="btn btn-red btn-small" type="button" data-action="delete" data-id="' + id
        + '"' + (total > 0 ? ' disabled title="Tiene referencias"' : '') + '>Borrar</button></td>'
        + '</tr><tr id="bibUsageRow-' + id + '" hidden><td colspan="8"><div id="bibUsage-' + id + '"></div></td></tr>';
    }).join('');
  }
  function showUsage(id) {
    var targetRow = document.getElementById('bibUsageRow-' + id);
    var container = document.getElementById('bibUsage-' + id);
    if (!targetRow || !container) return;
    if (!targetRow.hidden) { targetRow.hidden = true; return; }
    targetRow.hidden = false;
    var uses = details[id] || [];
    if (!uses.length) {
      container.textContent = 'Sin referencias. El borrado se comprobara de nuevo en el servidor.';
      return;
    }
    container.innerHTML = '<strong>Desglose por tabla</strong><div class="adm-table-scroll"><table class="table">'
      + '<thead><tr><th>Tabla</th><th>Referencias</th><th>Tipo</th><th>Inspección</th></tr></thead><tbody>'
      + uses.map(function (entry, i) {
        return '<tr><td>' + escapeHtml(entry.table) + '</td><td>' + Number(entry.count || 0)
          + '</td><td>' + (entry.archive ? 'Backup histórico' : (entry.fk ? 'FK activa' : 'Referencia sin FK'))
          + '</td><td><button class="btn btn-small" type="button" data-action="sample" data-id="' + id
          + '" data-index="' + i + '">Ver identificadores</button></td></tr>'
          + '<tr><td colspan="4"><div id="bibSample-' + id + '-' + i + '" hidden></div></td></tr>';
      }).join('') + '</tbody></table></div>';
  }
  function showSample(id, index) {
    var source = (details[id] || [])[index];
    var target = document.getElementById('bibSample-' + id + '-' + index);
    if (!source || !target) return;
    if (!target.hidden) { target.hidden = true; return; }
    target.hidden = false;
    target.textContent = 'Cargando identificadores...';
    http.request(endpoint({ action: 'references', id: id, table: source.table }), { method: 'GET' })
      .then(function (payload) {
        var data = payload.data || {};
        var cols = data.columns || [];
        var sample = data.rows || [];
        var text = '<p>' + escapeHtml(data.notice || '') + '</p>';
        if (cols.length && sample.length) {
          text += '<table class="table"><thead><tr>'
            + cols.map(function (c) { return '<th>' + escapeHtml(c) + '</th>'; }).join('')
            + '</tr></thead><tbody>'
            + sample.map(function (row) {
              return '<tr>' + cols.map(function (c) {
                return '<td>' + escapeHtml(row[c]) + '</td>';
              }).join('') + '</tr>';
            }).join('') + '</tbody></table>';
        }
        target.innerHTML = text;
      }).catch(function (err) {
        target.textContent = http.errorMessage(err);
      });
  }
  function openEditor(id) {
    var r = id > 0 ? findRow(id) : null;
    if (id > 0 && !r) return;
    form.reset();
    document.getElementById('bibId').value = r ? String(r.id) : '0';
    document.getElementById('bibModalTitle').textContent = r ? 'Editar bibliografia' : 'Nueva bibliografia';
    document.getElementById('bibName').value = r ? r.name : '';
    document.getElementById('bibSlug').value = r ? (r.pretty_id || '') : '';
    document.getElementById('bibPublisher').value = r ? r.publisher : '';
    document.getElementById('bibYear').value = r ? r.year : 2026;
    document.getElementById('bibOrder').value = r ? r.sort_order : 900;
    document.getElementById('bibDescription').value = r ? r.description : '';
    modal.style.display = 'flex';
    document.getElementById('bibName').focus();
  }
  function closeEditor() { modal.style.display = 'none'; }
  function closeDelete() { deleteModal.style.display = 'none'; deleteId = 0; }
  function openDelete(id) {
    var r = findRow(id);
    if (!r) return;
    if (Number(r.active_count || 0) + Number(r.archive_count || 0) > 0) {
      message('Esta bibliografia esta en uso; inspecciona el desglose antes de cualquier decision.', true);
      return;
    }
    deleteId = id;
    document.getElementById('bibDeleteText').textContent =
      'Borrar definitivamente «' + r.name + '» (ID ' + id + ')? Se comprobara el uso de nuevo.';
    deleteModal.style.display = 'flex';
  }
  function refresh() {
    message('Actualizando listado...');
    return http.request(endpoint({ action: 'list' }), { method: 'GET' })
      .then(function (payload) {
        var data = payload.data || {};
        rows = data.rows || [];
        details = data.details || {};
        render();
        message(rows.length + ' bibliografias. ' + Number(data.sources_count || 0)
          + ' tablas consumidoras inspeccionadas (' + Number(data.fk_count || 0) + ' con FK).');
      }).catch(function (err) {
        message(http.errorMessage(err), true);
        tbody.innerHTML = '<tr><td colspan="8">No se ha podido cargar el listado.</td></tr>';
      });
  }
  tbody.addEventListener('click', function (ev) {
    var button = ev.target.closest('[data-action]');
    if (!button || button.disabled) return;
    var id = Number(button.getAttribute('data-id')) || 0;
    var action = button.getAttribute('data-action');
    if (action === 'edit') openEditor(id);
    if (action === 'delete') openDelete(id);
    if (action === 'usage') showUsage(id);
    if (action === 'sample') showSample(id, Number(button.getAttribute('data-index')) || 0);
  });
  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var id = Number(document.getElementById('bibId').value) || 0;
    var data = {
      id: id,
      name: document.getElementById('bibName').value,
      pretty_id: document.getElementById('bibSlug').value,
      year: document.getElementById('bibYear').value,
      publisher: document.getElementById('bibPublisher').value,
      sort_order: document.getElementById('bibOrder').value,
      description: document.getElementById('bibDescription').value
    };
    var save = document.getElementById('bibSave');
    http.postAction(endpoint({}), id ? 'update' : 'create', data, { loadingEl: save })
      .then(function (response) {
        closeEditor();
        http.notify(response.message || 'Guardado', 'ok');
        return refresh();
      }).catch(function (err) { message(http.errorMessage(err), true); });
  });
  document.getElementById('bibDeleteConfirm').addEventListener('click', function () {
    if (!deleteId) return;
    var button = this;
    http.postAction(endpoint({}), 'delete', { id: deleteId }, { loadingEl: button })
      .then(function (response) {
        closeDelete();
        http.notify(response.message || 'Borrado', 'ok');
        return refresh();
      }).catch(function (err) {
        closeDelete();
        message(http.errorMessage(err), true);
        refresh();
      });
  });
  document.getElementById('bibNew').addEventListener('click', function () { openEditor(0); });
  document.getElementById('bibCancel').addEventListener('click', closeEditor);
  document.getElementById('bibDeleteCancel').addEventListener('click', closeDelete);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeEditor(); closeDelete(); }
  });
  filter.addEventListener('input', render);
  refresh();
}());
