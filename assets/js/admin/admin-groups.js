(function(){
  var BOOT = window.HG_ADMIN_GROUPS_BOOT || {};
  var ENDPOINT = BOOT.endpoint || '';
  var $ = function(selector, ctx){ return (ctx || document).querySelector(selector); };
  var $$ = function(selector, ctx){ return Array.prototype.slice.call((ctx || document).querySelectorAll(selector)); };
  var modal = $('#agModal');
  var modalContent = $('#modalContent');
  var activeTab = 'clans';

  function notify(msg, kind){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
      window.HGAdminHttp.notify(msg, kind === 'error' ? 'error' : 'ok', 2600);
      return;
    }
    if (kind === 'error') {
      alert(msg);
    }
  }

  function setLoading(el, active){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.setLoading === 'function') {
      window.HGAdminHttp.setLoading(el, active);
      return;
    }
    if (!el || !el.classList) return;
    if (active) {
      el.classList.add('adm-loading');
      el.setAttribute('aria-busy', 'true');
    } else {
      el.classList.remove('adm-loading');
      el.removeAttribute('aria-busy');
    }
  }

  function syncSelect2Palette(root){
    if (!root || !window.getComputedStyle) return;
    var modalRoot = $('#agModal');
    if (!modalRoot) return;
    var probe = root.querySelector('select');
    if (!probe) return;
    var cs = window.getComputedStyle(probe);
    var bg = (cs.backgroundColor || '').trim() || '#000033';
    var fg = (cs.color || '').trim() || '#ffffff';
    var bd = (cs.borderColor || '').trim() || '#333333';
    modalRoot.style.setProperty('--adm-s2-bg', bg);
    modalRoot.style.setProperty('--adm-s2-color', fg);
    modalRoot.style.setProperty('--adm-s2-border', bd);
  }

  function initSelect2(root){
    if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2 || !root) return;
    syncSelect2Palette(root);
    var $root = jQuery(root);
    $root.find('select').each(function(){
      var $el = jQuery(this);
      if ($el.data('select2')) $el.select2('destroy');
      $el.select2({
        width: 'style',
        dropdownParent: $root.closest('.modal-back').length ? $root.closest('.modal-back') : $root,
        minimumResultsForSearch: 0
      });
    });
  }

  function filterTable(input, tbodySelector){
    if (!input) return;
    var query = String(input.value || '').trim().toLowerCase();
    $$(tbodySelector + ' tr').forEach(function(row){
      row.style.display = row.textContent.toLowerCase().indexOf(query) !== -1 ? '' : 'none';
    });
  }

  async function htmlPost(action, data, loadingEl){
    var formData = new FormData();
    formData.append('action', action);
    if (window.ADMIN_CSRF_TOKEN) formData.append('csrf', window.ADMIN_CSRF_TOKEN);
    Object.entries(data || {}).forEach(function(entry){
      formData.append(entry[0], entry[1]);
    });

    if (loadingEl) setLoading(loadingEl, true);
    try {
      var response = await fetch(ENDPOINT, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        body: formData
      });
      if (!response.ok) {
        throw new Error('HTTP ' + response.status);
      }
      return response.text();
    } finally {
      if (loadingEl) setLoading(loadingEl, false);
    }
  }

  function setTab(tab){
    activeTab = (tab === 'groups') ? 'groups' : 'clans';
    $$('.tablink').forEach(function(link){
      link.classList.toggle('active', link.dataset.tab === activeTab);
    });
    var clans = $('#tab-clans');
    var groups = $('#tab-groups');
    if (clans) clans.style.display = activeTab === 'clans' ? '' : 'none';
    if (groups) groups.style.display = activeTab === 'groups' ? '' : 'none';
  }

  function openModal(html){
    if (!modal || !modalContent) return;
    modalContent.innerHTML = html;
    modal.style.display = 'flex';
    var closeBtn = $('.modal-close', modalContent);
    if (closeBtn) closeBtn.onclick = closeModal;
    initSelect2(modalContent);
    bindModalInside();
  }

  function closeModal(){
    if (!modal || !modalContent) return;
    modal.style.display = 'none';
    modalContent.innerHTML = '';
  }

  async function reloadClans(){
    var wrap = $('#clansTableWrap');
    if (!wrap) return;
    wrap.innerHTML = await htmlPost('load_clans_table');
    bindRowButtons();
    filterTable($('#filterClans'), '#clansTable tbody');
  }

  async function reloadGroups(){
    var wrap = $('#groupsTableWrap');
    if (!wrap) return;
    wrap.innerHTML = await htmlPost('load_groups_table');
    bindRowButtons();
    filterTable($('#filterGroups'), '#groupsTable tbody');
  }

  function bindRowButtons(){
    $$('#clansTableWrap .btn-edit-clan').forEach(function(btn){
      btn.onclick = async function(){
        openModal(await htmlPost('clan_modal', { organization_id: btn.dataset.id }, btn));
      };
    });
    $$('#groupsTableWrap .btn-edit-group').forEach(function(btn){
      btn.onclick = async function(){
        openModal(await htmlPost('group_modal', { group_id: btn.dataset.id }, btn));
      };
    });
  }

  function bindCharacterPicker(results, input){
    $$('.btn-pick-char', results).forEach(function(btn){
      btn.onclick = function(){
        input.value = btn.parentElement.firstElementChild.textContent.trim();
        input.dataset.charId = btn.dataset.id;
        results.classList.add('adm-hidden');
      };
    });
  }

  function bindModalInside(){
    var root = modalContent;
    if (!root) return;

    var btnCreateClan = $('#btnCreateClan', root);
    if (btnCreateClan) {
      btnCreateClan.onclick = async function(){
        try {
          var name = ($('#newClanName', root).value || '').trim();
          var sort_order = ($('#newClanSortOrder', root).value || '0').trim();
          var totem = ($('#newClanTotem', root).value || '').trim();
          var color = ($('#newClanColor', root).value || '#ffffff').trim();
          var is_npc = ($('#newClanIsNpc', root).value || '0').trim();
          var description = ($('#newClanDescription', root).value || '');
          openModal(await htmlPost('clan_create', { name: name, sort_order: sort_order, totem: totem, color: color, is_npc: is_npc, description: description }, btnCreateClan));
          await reloadClans();
          notify('Organización creada.', 'ok');
        } catch (err) {
          notify('No se pudo crear la organización.', 'error');
          console.error('[admin_groups] clan_create failed:', err);
        }
      };
    }

    var btnCreateGroup = $('#btnCreateGroup', root);
    if (btnCreateGroup) {
      btnCreateGroup.onclick = async function(){
        try {
          var name = ($('#newGroupName', root).value || '').trim();
          var cronica = ($('#newGroupCronica', root).value || '1').trim();
          var activa = $('#newGroupActiva', root).checked ? 1 : 0;
          var organization_id = ($('#newGroupClan', root).value || '0').trim();
          var totem = ($('#newGroupTotem', root).value || '').trim();
          var description = ($('#newGroupDescription', root).value || '');
          openModal(await htmlPost('group_create', { name: name, cronica: cronica, activa: activa, organization_id: organization_id, totem: totem, description: description }, btnCreateGroup));
          await reloadGroups();
          await reloadClans();
          notify('Manada creada.', 'ok');
        } catch (err) {
          notify('No se pudo crear la manada.', 'error');
          console.error('[admin_groups] group_create failed:', err);
        }
      };
    }

    var btnClanSave = $('#btnClanSave', root);
    if (btnClanSave) {
      btnClanSave.onclick = async function(){
        try {
          var organization_id = btnClanSave.dataset.id;
          var name = ($('#clanName', root).value || '').trim();
          var totem = ($('#clanTotem', root).value || '').trim();
          var color = ($('#clanColor', root).value || '#ffffff').trim();
          var is_npc = ($('#clanIsNpc', root).value || '0').trim();
          var description = ($('#clanDescription', root).value || '');
          openModal(await htmlPost('clan_update_basic', { organization_id: organization_id, name: name, totem: totem, color: color, is_npc: is_npc, description: description }, btnClanSave));
          await reloadClans();
          notify('Organización actualizada.', 'ok');
        } catch (err) {
          notify('No se pudo guardar la organización.', 'error');
          console.error('[admin_groups] clan_update_basic failed:', err);
        }
      };
    }

    var btnOpenGroupCreate = $('#btnOpenGroupCreate', root);
    if (btnOpenGroupCreate) {
      btnOpenGroupCreate.onclick = async function(){
        openModal(await htmlPost('group_create_form', { organization_id: btnOpenGroupCreate.dataset.clan }, btnOpenGroupCreate));
      };
    }

    var detailClan = $('#clanModalDetail', root);
    if (detailClan) {
      var rebindClanDetail = function(){
        $$('.btn-pack-activate', detailClan).forEach(function(btn){
          btn.onclick = async function(){
            detailClan.innerHTML = await htmlPost('clan_add_group', { organization_id: btn.dataset.clan, group_id: btn.dataset.gid }, btn);
            rebindClanDetail();
            await reloadClans();
          };
        });
        $$('.btn-pack-deactivate', detailClan).forEach(function(btn){
          btn.onclick = async function(){
            detailClan.innerHTML = await htmlPost('clan_remove_group', { organization_id: btn.dataset.clan, group_id: btn.dataset.gid }, btn);
            rebindClanDetail();
            await reloadClans();
          };
        });
        var btnAddPack = $('#btnAddPack', detailClan);
        if (btnAddPack) {
          btnAddPack.onclick = async function(){
            var select = $('#packsAvailable', detailClan);
            if (!select || !select.value) return;
            detailClan.innerHTML = await htmlPost('clan_add_group', { organization_id: btnAddPack.dataset.clan, group_id: select.value }, btnAddPack);
            rebindClanDetail();
            await reloadClans();
          };
        }
      };
      rebindClanDetail();
    }

    var btnSaveGroupBasic = $('#btnSaveGroupBasic', root);
    if (btnSaveGroupBasic) {
      btnSaveGroupBasic.onclick = async function(){
        try {
          var group_id = btnSaveGroupBasic.dataset.id;
          var name = ($('#groupName', root).value || '').trim();
          var activa = $('#groupActiva', root).checked ? 1 : 0;
          var cronica = ($('#groupCronica', root).value || '1').trim();
          var totem = ($('#groupTotem', root).value || '').trim();
          var description = ($('#groupDescription', root).value || '');
          openModal(await htmlPost('group_update_basic', { group_id: group_id, name: name, activa: activa, cronica: cronica, totem: totem, description: description }, btnSaveGroupBasic));
          await reloadGroups();
          await reloadClans();
          notify('Manada actualizada.', 'ok');
        } catch (err) {
          notify('No se pudo guardar la manada.', 'error');
          console.error('[admin_groups] group_update_basic failed:', err);
        }
      };
    }

    var groupDetail = $('#groupModalDetail', root);
    if (groupDetail) {
      var rebindGroupDetail = function(){
        var inSearch = $('#searchChar', groupDetail);
        var results = $('#searchResults', root);
        if (inSearch && results) {
          var timer = null;
          inSearch.oninput = function(){
            clearTimeout(timer);
            var query = inSearch.value.trim();
            if (!query) {
              results.classList.add('adm-hidden');
              results.innerHTML = '';
              delete inSearch.dataset.charId;
              return;
            }
            delete inSearch.dataset.charId;
            timer = setTimeout(async function(){
              results.innerHTML = await htmlPost('search_characters', { q: query }, inSearch);
              results.classList.remove('adm-hidden');
              bindCharacterPicker(results, inSearch);
            }, 250);
          };
        }

        var btnAdd = $('#btnAddMember', groupDetail);
        if (btnAdd) {
          btnAdd.onclick = async function(){
            var gid = btnAdd.dataset.group;
            var pos = ($('#newPosition', groupDetail).value || '').trim();
            var cid = ($('#searchChar', groupDetail).dataset.charId || '').trim();
            if (!cid) {
              notify('Selecciona un personaje de la búsqueda.', 'error');
              return;
            }
            groupDetail.innerHTML = await htmlPost('group_add_member', { group_id: gid, character_id: cid, position: pos }, btnAdd);
            rebindGroupDetail();
          };
        }

        $$('.btn-save-position', groupDetail).forEach(function(btn){
          btn.onclick = async function(){
            var chip = btn.closest('.chip');
            var pos = chip && chip.querySelector('input') ? chip.querySelector('input').value.trim() : '';
            groupDetail.innerHTML = await htmlPost('group_save_position', { group_id: btn.dataset.group, character_id: btn.dataset.id, position: pos }, btn);
            rebindGroupDetail();
          };
        });

        $$('.btn-rem-member', groupDetail).forEach(function(btn){
          btn.onclick = async function(){
            groupDetail.innerHTML = await htmlPost('group_remove_member', { group_id: btn.dataset.group, character_id: btn.dataset.id }, btn);
            rebindGroupDetail();
          };
        });

        $$('.btn-activate-member', groupDetail).forEach(function(btn){
          btn.onclick = async function(){
            var chip = btn.closest('.chip');
            var pos = chip && chip.querySelector('input') ? chip.querySelector('input').value.trim() : '';
            groupDetail.innerHTML = await htmlPost('group_add_member', { group_id: btn.dataset.group, character_id: btn.dataset.id, position: pos }, btn);
            rebindGroupDetail();
          };
        });
      };
      rebindGroupDetail();
    }
  }

  var filterClans = $('#filterClans');
  if (filterClans) {
    filterClans.addEventListener('input', function(){
      filterTable(filterClans, '#clansTable tbody');
    });
  }

  var filterGroups = $('#filterGroups');
  if (filterGroups) {
    filterGroups.addEventListener('input', function(){
      filterTable(filterGroups, '#groupsTable tbody');
    });
  }

  $$('.tablink').forEach(function(link){
    link.addEventListener('click', function(ev){
      ev.preventDefault();
      setTab(link.dataset.tab || 'clans');
    });
  });

  var reloadClansBtn = $('#reloadClans');
  if (reloadClansBtn) reloadClansBtn.onclick = reloadClans;
  var reloadGroupsBtn = $('#reloadGroups');
  if (reloadGroupsBtn) reloadGroupsBtn.onclick = reloadGroups;

  var btnNewClan = $('#btnNewClan');
  if (btnNewClan) {
    btnNewClan.onclick = async function(){
      openModal(await htmlPost('clan_create_form', {}, btnNewClan));
    };
  }

  var btnNewGroup = $('#btnNewGroup');
  if (btnNewGroup) {
    btnNewGroup.onclick = async function(){
      openModal(await htmlPost('group_create_form', {}, btnNewGroup));
    };
  }

  bindRowButtons();
  setTab(activeTab);

  var dialog = $('#agModal .modal');
  if (dialog) {
    dialog.addEventListener('click', function(ev){ ev.stopPropagation(); });
  }
  if (modal) {
    modal.addEventListener('click', function(ev){
      if (ev.target === modal) closeModal();
    });
  }
  document.addEventListener('keydown', function(ev){
    if (ev.key === 'Escape') closeModal();
  });
})();
