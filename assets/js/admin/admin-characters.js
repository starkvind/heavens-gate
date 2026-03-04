function syncSelect2Palette(){
  var mbEl = document.getElementById('mb');
  if (!mbEl) return;
  var probe = mbEl.querySelector('select.select, select');
  if (!probe) return;
  var cs = window.getComputedStyle(probe);
  var bg = (cs.backgroundColor || '').trim() || '#000033';
  var fg = (cs.color || '').trim() || '#ffffff';
  var bd = (cs.borderColor || '').trim() || '#333333';
  mbEl.style.setProperty('--adm-s2-bg', bg);
  mbEl.style.setProperty('--adm-s2-color', fg);
  mbEl.style.setProperty('--adm-s2-border', bd);
}

/* ------------ Select2 init (dentro del modal) ------------ */
function initSelect2Modal(){
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
  syncSelect2Palette();

  var $parent = jQuery('#mb');
  // Sólo selects del modal
  $parent.find('select').each(function(){
  if (window.hgMentions) { window.hgMentions.attachAuto(); }
    var $s = jQuery(this);
    if ($s.data('select2')) $s.select2('destroy');

    $s.select2({
      width: 'style',
      dropdownParent: $parent,
      minimumResultsForSearch: 0
    });
  });
}

// Reinit individual (cuando se cambian options por JS)
function reinitSelect2(el){
  if (!window.jQuery || !jQuery.fn || !jQuery.fn.select2) return;
  if (!el) return;
  var $s = jQuery(el);
  if ($s.data('select2')) $s.select2('destroy');
  $s.select2({
    width: 'style',
    dropdownParent: jQuery('#mb'),
    minimumResultsForSearch: 0
  });
}

// Bind change que funciona con Select2 (jQuery) y sin él
function onSelectChange(el, handler){
  if (!el) return;
  if (window.jQuery) {
    jQuery(el).off('change.hg').on('change.hg', handler);
  } else {
    el.addEventListener('change', handler);
  }
}

var BOOT = window.HG_ADMIN_CHARACTERS_BOOT || {};

var AJAX_BASE = BOOT.AJAX_BASE || '';

// Dependencias
var MANADAS_BY_CLAN   = BOOT.MANADAS_BY_CLAN || {};
var MANADA_ID_TO_CLAN = BOOT.MANADA_ID_TO_CLAN || {};

var RAZAS_BY_SYS      = BOOT.RAZAS_BY_SYS || {};
var RAZA_ID_TO_SYS    = BOOT.RAZA_ID_TO_SYS || {};

var AUSP_BY_SYS       = BOOT.AUSP_BY_SYS || {};
var AUSP_ID_TO_SYS    = BOOT.AUSP_ID_TO_SYS || {};

var TRIBUS_BY_SYS     = BOOT.TRIBUS_BY_SYS || {};
var TRIBU_ID_TO_SYS   = BOOT.TRIBU_ID_TO_SYS || {};

// PODERES
var DONES_OPTS       = Array.isArray(BOOT.DONES_OPTS) ? BOOT.DONES_OPTS : [];
var DISC_OPTS        = Array.isArray(BOOT.DISC_OPTS) ? BOOT.DISC_OPTS : [];
var RITU_OPTS        = Array.isArray(BOOT.RITU_OPTS) ? BOOT.RITU_OPTS : [];
var CHAR_POWERS      = BOOT.CHAR_POWERS || {};

// MERITOS/DEFECTOS
var MYD_OPTS         = Array.isArray(BOOT.MYD_OPTS) ? BOOT.MYD_OPTS : [];
var CHAR_MYD         = BOOT.CHAR_MYD || {};

// INVENTARIO
var ITEMS_OPTS       = Array.isArray(BOOT.ITEMS_OPTS) ? BOOT.ITEMS_OPTS : [];
var CHAR_ITEMS       = BOOT.CHAR_ITEMS || {};

// RECURSOS
var RESOURCE_OPTS        = Array.isArray(BOOT.RESOURCE_OPTS) ? BOOT.RESOURCE_OPTS : [];
var SYS_RESOURCES_BY_SYS = BOOT.SYS_RESOURCES_BY_SYS || {};
var CHAR_RESOURCES       = BOOT.CHAR_RESOURCES || {};

// TRAITS
var CHAR_TRAITS      = BOOT.CHAR_TRAITS || {};
var TRAIT_SET_ORDER  = BOOT.TRAIT_SET_ORDER || {};
var CHAR_DETAILS     = BOOT.CHAR_DETAILS || {};
var DEFAULT_STATUS_ID = parseInt(BOOT.DEFAULT_STATUS_ID || 0, 10) || 0;

(function(){
  var filterForm = document.getElementById('charactersFilterForm');
  var quick = document.getElementById('quickFilter');
  var tableBody = document.getElementById('tablaPjsBody');
  var pagerWrap = document.getElementById('charactersPager');
  var quickTimer = null;
  var mb = document.getElementById('mb');
  var btnNew = document.getElementById('btnNew');
  var btnCancel = document.getElementById('btnCancel');
  var formCrud = document.getElementById('formCrud');
  var btnSave = document.getElementById('btnSave');

  var selSistema = document.getElementById('f_system_id');
	  var selRaza    = document.getElementById('f_raza');
	  var selAusp    = document.getElementById('f_auspicio');
	  var selTribu   = document.getElementById('f_tribu');
	  var selNature  = document.getElementById('f_nature_id');
	  var selDemeanor= document.getElementById('f_demeanor_id');

  var selClan    = document.getElementById('f_clan');
  var selManada  = document.getElementById('f_manada');
  var selTotem   = document.getElementById('f_totem_id');

  var selAfili   = document.getElementById('f_afiliacion');
  var selKind    = document.getElementById('f_kind');

  var avatar      = document.getElementById('f_avatar');
  var avatarPrev  = document.getElementById('f_avatar_preview');
  var avatarRm    = document.getElementById('f_avatar_remove');

  // Campos complejos
  var fEstado     = document.getElementById('f_estado');
  var fCumple     = document.getElementById('f_cumple');
  var fRango      = document.getElementById('f_rango');
  var fInfo       = document.getElementById('f_infotext');

  // PODERES
  var powTipo  = document.getElementById('pow_tipo');
  var powPoder = document.getElementById('pow_poder');
  var powLvl   = document.getElementById('pow_lvl');
  var powAdd   = document.getElementById('pow_add');
  var powList  = document.getElementById('powersList');

  // MYD
  var mydSel   = document.getElementById('myd_sel');
  var mydLvl   = document.getElementById('myd_lvl');
  var mydAdd   = document.getElementById('myd_add');
  var mydList  = document.getElementById('mydList');

  // INVENTARIO
  var invSel   = document.getElementById('inv_sel');
  var invAdd   = document.getElementById('inv_add');
  var invList  = document.getElementById('invList');

  // RECURSOS
  var resSel   = document.getElementById('res_sel');
  var resAdd   = document.getElementById('res_add');
  var resList  = document.getElementById('resourceList');
  var traitInputs = document.querySelectorAll('.trait-input');
  var pjOnlyBlocks = document.querySelectorAll('.kind-pj-only');
  var noMonsterBlocks = document.querySelectorAll('.kind-no-monster');

  function applyBootData(nextBoot){
    if (!nextBoot || typeof nextBoot !== 'object') return;
    if (typeof nextBoot.AJAX_BASE === 'string') AJAX_BASE = nextBoot.AJAX_BASE;
    if (nextBoot.MANADAS_BY_CLAN) MANADAS_BY_CLAN = nextBoot.MANADAS_BY_CLAN;
    if (nextBoot.MANADA_ID_TO_CLAN) MANADA_ID_TO_CLAN = nextBoot.MANADA_ID_TO_CLAN;
    if (nextBoot.RAZAS_BY_SYS) RAZAS_BY_SYS = nextBoot.RAZAS_BY_SYS;
    if (nextBoot.RAZA_ID_TO_SYS) RAZA_ID_TO_SYS = nextBoot.RAZA_ID_TO_SYS;
    if (nextBoot.AUSP_BY_SYS) AUSP_BY_SYS = nextBoot.AUSP_BY_SYS;
    if (nextBoot.AUSP_ID_TO_SYS) AUSP_ID_TO_SYS = nextBoot.AUSP_ID_TO_SYS;
    if (nextBoot.TRIBUS_BY_SYS) TRIBUS_BY_SYS = nextBoot.TRIBUS_BY_SYS;
    if (nextBoot.TRIBU_ID_TO_SYS) TRIBU_ID_TO_SYS = nextBoot.TRIBU_ID_TO_SYS;
    DONES_OPTS = Array.isArray(nextBoot.DONES_OPTS) ? nextBoot.DONES_OPTS : DONES_OPTS;
    DISC_OPTS = Array.isArray(nextBoot.DISC_OPTS) ? nextBoot.DISC_OPTS : DISC_OPTS;
    RITU_OPTS = Array.isArray(nextBoot.RITU_OPTS) ? nextBoot.RITU_OPTS : RITU_OPTS;
    CHAR_POWERS = nextBoot.CHAR_POWERS || CHAR_POWERS;
    MYD_OPTS = Array.isArray(nextBoot.MYD_OPTS) ? nextBoot.MYD_OPTS : MYD_OPTS;
    CHAR_MYD = nextBoot.CHAR_MYD || CHAR_MYD;
    ITEMS_OPTS = Array.isArray(nextBoot.ITEMS_OPTS) ? nextBoot.ITEMS_OPTS : ITEMS_OPTS;
    CHAR_ITEMS = nextBoot.CHAR_ITEMS || CHAR_ITEMS;
    RESOURCE_OPTS = Array.isArray(nextBoot.RESOURCE_OPTS) ? nextBoot.RESOURCE_OPTS : RESOURCE_OPTS;
    SYS_RESOURCES_BY_SYS = nextBoot.SYS_RESOURCES_BY_SYS || SYS_RESOURCES_BY_SYS;
    CHAR_RESOURCES = nextBoot.CHAR_RESOURCES || CHAR_RESOURCES;
    CHAR_TRAITS = nextBoot.CHAR_TRAITS || CHAR_TRAITS;
    TRAIT_SET_ORDER = nextBoot.TRAIT_SET_ORDER || TRAIT_SET_ORDER;
    CHAR_DETAILS = nextBoot.CHAR_DETAILS || CHAR_DETAILS;
  }
  function bindEditButtons(scope){
    var root = scope || document;
    Array.prototype.forEach.call(root.querySelectorAll('button[data-edit="1"]'), function(b){
      if (b.dataset.editBound === '1') return;
      b.dataset.editBound = '1';
      b.addEventListener('click', function(){ openEdit(b); });
    });
  }
  function bindDeleteButtons(scope){
    var root = scope || document;
    Array.prototype.forEach.call(root.querySelectorAll('button[data-delete="1"]'), function(b){
      if (b.dataset.deleteBound === '1') return;
      b.dataset.deleteBound = '1';
      b.addEventListener('click', function(){ deleteCharacter(b); });
    });
  }
  function extractBootFromDoc(doc){
    var scripts = doc.querySelectorAll('script');
    for (var i = 0; i < scripts.length; i++) {
      var txt = scripts[i].textContent || '';
      if (txt.indexOf('window.HG_ADMIN_CHARACTERS_BOOT') === -1) continue;
      var match = txt.match(/window\.HG_ADMIN_CHARACTERS_BOOT\s*=\s*(\{[\s\S]*\})\s*;/);
      if (!match || !match[1]) continue;
      try {
        return JSON.parse(match[1]);
      } catch (e) {
        return null;
      }
    }
    return null;
  }
  function syncFilterFromUrl(url){
    if (!filterForm) return;
    var parsed = new URL(url, window.location.origin);
    var keys = ['q', 'fil_cr', 'fil_ma', 'pp'];
    keys.forEach(function(key){
      var input = filterForm.querySelector('[name="' + key + '"]');
      if (!input) return;
      var val = parsed.searchParams.get(key);
      input.value = val === null ? '' : val;
    });
  }
  function submitFilterAjax(){
    if (!filterForm) return;
    var action = filterForm.getAttribute('action') || window.location.pathname || '/talim';
    var formData = new FormData(filterForm);
    formData.set('pg', '1');
    var qs = new URLSearchParams(formData);
    loadListViaAjax(action + '?' + qs.toString(), true);
  }
  function loadListViaAjax(url, pushState){
    if (!url || !tableBody || !pagerWrap) return;
    var parsed = new URL(url, window.location.origin);
    var href = parsed.pathname + parsed.search + parsed.hash;
    tableBody.classList.add('adm-opacity-60');
    if (quick) quick.disabled = true;
    fetch(href, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
      .then(function(res){
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.text();
      })
      .then(function(html){
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var nextBody = doc.getElementById('tablaPjsBody');
        var nextPager = doc.getElementById('charactersPager');
        if (!nextBody || !nextPager) throw new Error('No se pudo cargar el listado parcial');
        tableBody.innerHTML = nextBody.innerHTML;
        pagerWrap.innerHTML = nextPager.innerHTML;
        var nextBoot = extractBootFromDoc(doc);
        applyBootData(nextBoot);
        bindEditButtons(tableBody);
        bindDeleteButtons(tableBody);
        syncFilterFromUrl(href);
        if (pushState) {
          window.history.pushState({ adminCharactersList: true }, '', href);
        }
      })
      .catch(function(err){
        console.error('[admin_characters] fallo en carga AJAX de listado:', err);
        window.location.href = href;
      })
      .finally(function(){
        tableBody.classList.remove('adm-opacity-60');
        if (quick) quick.disabled = false;
      });
  }

  function normalizeText(v){
    v = String(v || '').toLowerCase();
    if (v.normalize) {
      v = v.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return v;
  }

  function applyMonsterTraitFilter(kind){
    var isMonster = (normalizeText(kind) === 'monster' || normalizeText(kind) === 'mon');
    document.querySelectorAll('.trait-item').forEach(function(item){
      var k = normalizeText(item.getAttribute('data-trait-kind') || '');
      var c = normalizeText(item.getAttribute('data-trait-classification') || '');
      var hide = false;
      if (isMonster) {
        hide = (k === 'trasfondos') || (
          (k === 'talentos' || k === 'tecnicas' || k === 'conocimientos') &&
          c.indexOf('002 secundarias') === 0
        );
      }
      item.style.display = hide ? 'none' : '';
      if (hide) {
        var inp = item.querySelector('.trait-input');
        if (inp) inp.value = '0';
      }
    });
  }

  function applyKindVisibility(kind){
    var k = String(kind || '').toLowerCase();
    var isPj = (k !== 'pnj');
    var isMonster = (normalizeText(k) === 'monster' || normalizeText(k) === 'mon');
    pjOnlyBlocks.forEach(function(block){
      block.style.display = isPj ? '' : 'none';
    });
    noMonsterBlocks.forEach(function(block){
      block.style.display = (isPj && !isMonster) ? '' : 'none';
    });
    applyMonsterTraitFilter(kind);
  }

  function clearSelect(sel, keepFirst){
    while (sel.options.length > (keepFirst?1:0)) sel.remove(keepFirst?1:0);
  }

  function fillSelectFrom(list, sel, placeholder, preselect){
    clearSelect(sel,false);

    if (!list || !list.length){
      sel.disabled = true;
      var o=document.createElement('option'); o.value='0'; o.textContent=placeholder;
      sel.appendChild(o);
      sel.value='0';
      reinitSelect2(sel);
      return false;
    }

    sel.disabled = false;
    var ph=document.createElement('option'); ph.value='0'; ph.textContent='— Elige —';
    sel.appendChild(ph);

    var found=false;
    list.forEach(function(it){
      var o=document.createElement('option'); o.value=String(it.id); o.textContent=it.name;
      sel.appendChild(o);
      if (preselect && String(preselect)===String(it.id)) found=true;
    });

    sel.value = found ? String(preselect) : '0';
    reinitSelect2(sel);
    return found;
  }

  function updateManadas(clanId, preselect){
    var list = MANADAS_BY_CLAN[String(clanId||0)] || [];
    fillSelectFrom(list, selManada, '— Sin manadas en este Clan —', preselect);
  }

  function updateSistemaSets(sys, preRaza, preAusp, preTribu){
    if (!sys){
      clearSelect(selRaza,false); var a1=document.createElement('option'); a1.value='0'; a1.textContent='— Elige un Sistema —'; selRaza.appendChild(a1); selRaza.disabled=true; reinitSelect2(selRaza);
      clearSelect(selAusp,false); var a2=document.createElement('option'); a2.value='0'; a2.textContent='— Elige un Sistema —'; selAusp.appendChild(a2); selAusp.disabled=true; reinitSelect2(selAusp);
      clearSelect(selTribu,false); var a3=document.createElement('option'); a3.value='0'; a3.textContent='— Elige un Sistema —'; selTribu.appendChild(a3); selTribu.disabled=true; reinitSelect2(selTribu);
      return;
    }

    var okR = fillSelectFrom(RAZAS_BY_SYS[sys]||[], selRaza, '— Sin razas para este Sistema —', preRaza);
    var okA = fillSelectFrom(AUSP_BY_SYS[sys]||[],  selAusp, '— Sin auspicios para este Sistema —', preAusp);
    var okT = fillSelectFrom(TRIBUS_BY_SYS[sys]||[], selTribu,'— Sin tribus para este Sistema —', preTribu);

    if (preRaza && !okR){
      var w=document.createElement('option'); w.value=String(preRaza); w.textContent='[WARN] (Fuera del Sistema) ID '+preRaza;
      selRaza.appendChild(w); selRaza.value=String(preRaza); selRaza.disabled=false;
      reinitSelect2(selRaza);
    }
    if (preAusp && !okA){
      var w2=document.createElement('option'); w2.value=String(preAusp); w2.textContent='[WARN] (Fuera del Sistema) ID '+preAusp;
      selAusp.appendChild(w2); selAusp.value=String(preAusp); selAusp.disabled=false;
      reinitSelect2(selAusp);
    }
    if (preTribu && !okT){
      var w3=document.createElement('option'); w3.value=String(preTribu); w3.textContent='[WARN] (Fuera del Sistema) ID '+preTribu;
      selTribu.appendChild(w3); selTribu.value=String(preTribu); selTribu.disabled=false;
      reinitSelect2(selTribu);
    }
  }

  function resetAvatarUI(){
    avatar.value = '';
    avatarRm.checked = false;
    avatarPrev.src = '';
    avatarPrev.style.display = 'none';
  }

  function resetTraits(){
    if (!traitInputs) return;
    traitInputs.forEach(function(inp){ inp.value = '0'; });
  }

  function fillTraits(map){
    resetTraits();
    if (!map) return;
    traitInputs.forEach(function(inp){
      var tid = inp.getAttribute('data-trait-id');
      if (tid && map[tid] !== undefined) {
        inp.value = String(map[tid]);
      }
    });
  }

  function applyTraitOrder(systemId){
    var orderMap = (TRAIT_SET_ORDER && systemId && TRAIT_SET_ORDER[String(systemId)]) ? TRAIT_SET_ORDER[String(systemId)] : {};
    document.querySelectorAll('.traits-group').forEach(function(group){
      var items = Array.prototype.slice.call(group.querySelectorAll('.trait-item'));
      items.sort(function(a,b){
        var aid = a.querySelector('[data-trait-id]')?.getAttribute('data-trait-id') || '';
        var bid = b.querySelector('[data-trait-id]')?.getAttribute('data-trait-id') || '';
        var ao = orderMap[aid] !== undefined ? parseInt(orderMap[aid],10) : 9999;
        var bo = orderMap[bid] !== undefined ? parseInt(orderMap[bid],10) : 9999;
        if (ao !== bo) return ao - bo;
        var an = (a.getAttribute('data-trait-name') || '').toLowerCase();
        var bn = (b.getAttribute('data-trait-name') || '').toLowerCase();
        return an.localeCompare(bn);
      });
      items.forEach(function(it){ group.appendChild(it); });
    });
  }

  // PODERES
  function powersCatalogFor(type){
    if (type==='dones') return DONES_OPTS;
    if (type==='disciplinas') return DISC_OPTS;
    return RITU_OPTS;
  }
  function refreshPowerSelect(){
    var t = powTipo.value;
    fillSelectFrom(powersCatalogFor(t), powPoder, '— Sin poderes —', 0);
  }
  function addPowerChip(type, id, name, lvl){
    var exists = Array.prototype.some.call(powList.querySelectorAll('.power-chip'), function(c){
      return c.dataset.type===type && c.dataset.id===String(id);
    });
    if (exists) return;

    var chip = document.createElement('span');
    chip.className = 'chip power-chip';
    chip.dataset.type = type;
    chip.dataset.id = String(id);
    chip.innerHTML =
      '<span class="tag">'+(type.charAt(0).toUpperCase()+type.slice(1))+'</span>' +
      '<span class="pname">'+name+'</span>' +
      '<input class="inp power-lvl" type="number" name="powers_lvl[]" min="0" max="9" value="'+(lvl||0)+'">' +
      '<input type="hidden" name="powers_type[]" value="'+type+'">' +
      '<input type="hidden" name="powers_id[]" value="'+id+'">' +
      '<button type="button" class="btn btn-red btn-del-power">X</button>';
    powList.appendChild(chip);
    chip.querySelector('.btn-del-power').addEventListener('click', function(){ chip.remove(); });
  }

  // MYD
  function refreshMydSelect(){
    // Construimos un name amigable: "Nombre — Tipo (Coste)"
    var list = (MYD_OPTS||[]).map(function(it){
      var extra = '';
      if (it.tipo) extra += ' — ' + it.tipo;
      if (it.coste!==undefined && it.coste!==null && String(it.coste)!=='') extra += ' ('+it.coste+')';
      return { id: it.id, name: (it.name || ('#'+it.id)) + extra, tipo: it.tipo, coste: it.coste };
    });
    fillSelectFrom(list, mydSel, '— Sin méritos/defectos —', 0);
  }

  function addMydChip(id, baseName, tipo, coste, nivel){
    var exists = Array.prototype.some.call(mydList.querySelectorAll('.myd-chip'), function(c){
      return c.dataset.id===String(id);
    });
    if (exists) return;

    var tag = (tipo || 'MYD');
    var name = baseName || ('#'+id);

    var chip = document.createElement('span');
    chip.className = 'chip myd-chip';
    chip.dataset.id = String(id);
    chip.dataset.tipo = tag;

    var lvlVal = (nivel===null || nivel===undefined) ? '' : String(nivel);

    chip.innerHTML =
      '<span class="tag">'+tag+'</span>' +
      '<span class="pname">'+name+'</span>' +
      '<input type="hidden" name="myd_id[]" value="'+id+'">' +
      '<input class="inp myd-lvl" type="number" name="myd_lvl[]" min="-99" max="999" placeholder="nivel" value="'+lvlVal+'">' +
      '<button type="button" class="btn btn-red btn-del-myd">X</button>';

    mydList.appendChild(chip);
    chip.querySelector('.btn-del-myd').addEventListener('click', function(){ chip.remove(); });
  }

  // INVENTARIO
  function refreshInvSelect(){
    var list = (ITEMS_OPTS||[]).map(function(it){
      return { id: it.id, name: (it.name || ('#'+it.id)), tipo: it.tipo };
    });
    fillSelectFrom(list, invSel, '— Sin objetos —', 0);
  }

  function addInvChip(id, name, tipo){
    var exists = Array.prototype.some.call(invList.querySelectorAll('.inv-chip'), function(c){
      return c.dataset.id===String(id);
    });
    if (exists) return;

    var chip = document.createElement('span');
    chip.className = 'chip inv-chip';
    chip.dataset.id = String(id);
    chip.innerHTML =
      '<span class="tag">OBJ</span>' +
      '<span class="pname">'+(name || ('#'+id))+'</span>' +
      '<input type="hidden" name="items_id[]" value="'+id+'">' +
      '<button type="button" class="btn btn-red btn-del-inv">X</button>';

    invList.appendChild(chip);
    chip.querySelector('.btn-del-inv').addEventListener('click', function(){ chip.remove(); });
  }

  // RECURSOS
  function refreshResourceSelect(){
    var list = (RESOURCE_OPTS||[]).map(function(it){
      return { id: it.id, name: (it.name || ('#'+it.id)) + ' [' + (it.kind || '') + ']' };
    });
    fillSelectFrom(list, resSel, '— Sin recursos —', 0);
  }

  function getResourceMeta(rid){
    rid = parseInt(rid, 10) || 0;
    for (var i=0; i<(RESOURCE_OPTS||[]).length; i++) {
      var r = RESOURCE_OPTS[i];
      if ((parseInt(r.id,10)||0) === rid) return r;
    }
    return null;
  }

  function setResourceChipDefault(chip, isDefault){
    if (!chip) return;
    chip.dataset.sysDefault = isDefault ? '1' : '0';
    var badge = chip.querySelector('.res-default-badge');
    var btnDel = chip.querySelector('.btn-del-res');
    if (badge) badge.style.display = isDefault ? '' : 'none';
    if (btnDel) btnDel.style.display = isDefault ? 'none' : '';
  }

  function addResourceChip(id, name, kind, perm, temp, isSystemDefault){
    id = parseInt(id,10)||0;
    if (!id) return;
    var exists = Array.prototype.find.call(resList.querySelectorAll('.res-chip'), function(c){
      return c.dataset.id === String(id);
    });
    if (exists) {
      if (isSystemDefault) setResourceChipDefault(exists, true);
      return;
    }

    perm = parseInt(perm,10); if (isNaN(perm) || perm < 0) perm = 0;
    temp = parseInt(temp,10); if (isNaN(temp) || temp < 0) temp = 0;

    var chip = document.createElement('span');
    chip.className = 'chip res-chip';
    chip.dataset.id = String(id);
    chip.innerHTML =
      '<span class="tag">'+(kind || 'res')+'</span>' +
      '<span class="pname">'+(name || ('#'+id))+'</span>' +
      '<span class="res-default-badge adm-hidden-sys-badge">SYS</span>' +
      '<input type="hidden" name="resource_ids[]" value="'+id+'">' +
      '<input class="inp adm-w-90" type="number" min="0" name="resource_perm[]" value="'+perm+'" title="Permanente">' +
      '<input class="inp adm-w-90" type="number" min="0" name="resource_temp[]" value="'+temp+'" title="Temporal">' +
      '<button type="button" class="btn btn-red btn-del-res">X</button>';

    resList.appendChild(chip);
    chip.querySelector('.btn-del-res').addEventListener('click', function(){ chip.remove(); });
    setResourceChipDefault(chip, !!isSystemDefault);
  }

  function ensureSystemResources(systemId){
    systemId = parseInt(systemId,10)||0;
    var defaults = SYS_RESOURCES_BY_SYS[String(systemId)] || [];
    defaults.forEach(function(r){
      addResourceChip(r.id, r.name, r.kind, 0, 0, true);
    });
    // Re-marca defaults de este sistema y desmarca el resto
    var defaultMap = {};
    defaults.forEach(function(r){ defaultMap[String(r.id)] = true; });
    Array.prototype.forEach.call(resList.querySelectorAll('.res-chip'), function(ch){
      setResourceChipDefault(ch, !!defaultMap[ch.dataset.id]);
    });
  }

  function ensureEstadoOption(val, label){
    var wanted = parseInt(val, 10) || 0;
    if (!wanted) return;
    var sel = fEstado;
    var ok = Array.prototype.some.call(sel.options, function(o){
      return (parseInt(o.value, 10) || 0) === wanted;
    });
    if (!ok) {
      var opt = document.createElement('option');
      opt.value = String(wanted);
      opt.textContent = '[WARN] ' + (label || ('Estado #' + wanted)) + ' (no en lista)';
      sel.appendChild(opt);
      reinitSelect2(sel);
    }
    sel.value = String(wanted);
    reinitSelect2(sel);
  }

  function openCreate(){
    document.getElementById('modalTitle').textContent = 'Nuevo personaje';
    document.getElementById('crud_action').value = 'create';
    document.getElementById('f_id').value = '0';

    ['nombre','alias','nombregarou','gender','concept','text_color','cumple','rango'].forEach(function(k){
      var el=document.getElementById('f_'+k); if(el) el.value='';
    });
    ['cronica','jugador','system_id'].forEach(function(k){
      var el=document.getElementById('f_'+k); if(el) el.value='0';
    });
    if (selTotem) selTotem.value = '0';
    selAfili.value = '0';
    if (selKind) selKind.value = 'pnj';

    ensureEstadoOption(DEFAULT_STATUS_ID);

    fInfo.value  = '';

	    updateSistemaSets('', 0,0,0);
	    if (selNature) selNature.value = '0';
	    if (selDemeanor) selDemeanor.value = '0';

    selClan.value='0';
    clearSelect(selManada,false);
    var o=document.createElement('option'); o.value='0'; o.textContent='— Selecciona primero un Clan —';
    selManada.appendChild(o); selManada.disabled=true;
    reinitSelect2(selManada);

    resetAvatarUI();

    // reset poderes
    powList.innerHTML = '';
    powTipo.value = 'dones';
    refreshPowerSelect();

    // reset myd
    mydList.innerHTML = '';
    mydLvl.value = '';
    refreshMydSelect();

    // reset inv
    invList.innerHTML = '';
    refreshInvSelect();

    // reset recursos
    resList.innerHTML = '';
    refreshResourceSelect();
    ensureSystemResources(parseInt(selSistema.value,10)||0);

    // reset traits
    resetTraits();
    applyTraitOrder(0);
    applyKindVisibility(selKind ? selKind.value : 'pnj');

    mb.style.display='flex';
    initSelect2Modal();

    document.getElementById('f_nombre').focus();
  }

  function openEdit(btn){
    document.getElementById('modalTitle').textContent = 'Editar personaje';
    document.getElementById('crud_action').value = 'update';
    var cid = btn.getAttribute('data-id') || '0';
    document.getElementById('f_id').value = cid;

    document.getElementById('f_nombre').value      = btn.getAttribute('data-nombre') || '';
    document.getElementById('f_alias').value       = btn.getAttribute('data-alias') || '';
    document.getElementById('f_nombregarou').value = btn.getAttribute('data-nombregarou') || '';
    document.getElementById('f_genero_pj').value   = btn.getAttribute('data-gender') || '';
    document.getElementById('f_concepto').value    = btn.getAttribute('data-concept') || '';
    document.getElementById('f_colortexto').value  = btn.getAttribute('data-text_color') || '';

    document.getElementById('f_cronica').value     = btn.getAttribute('data-cronica') || '0';
    document.getElementById('f_jugador').value     = btn.getAttribute('data-jugador') || '0';
    document.getElementById('f_afiliacion').value  = btn.getAttribute('data-afiliacion') || '0';
    if (selKind) {
      var k = (btn.getAttribute('data-kind') || 'pnj').toLowerCase();
      if (k === 'monster' || k === 'mon') selKind.value = 'mon';
      else selKind.value = (k === 'pj') ? 'pj' : 'pnj';
    }

    var sistId = parseInt(btn.getAttribute('data-system_id')||'0',10)||0;
    var selS = document.getElementById('f_system_id');
    if (selS) selS.value = String(sistId||0);

    if (selTotem) {
      var tId = parseInt(btn.getAttribute('data-totem_id')||'0',10)||0;
      selTotem.value = String(tId||0);
    }

	    var razaId = parseInt(btn.getAttribute('data-raza')||'0',10)||0;
	    var ausId  = parseInt(btn.getAttribute('data-auspice_id')||'0',10)||0;
	    var triId  = parseInt(btn.getAttribute('data-tribe_id')||'0',10)||0;
	    var natId  = parseInt(btn.getAttribute('data-nature_id')||'0',10)||0;
	    var demId  = parseInt(btn.getAttribute('data-demeanor_id')||'0',10)||0;
	    updateSistemaSets(sistId, razaId, ausId, triId);
	    applyTraitOrder(sistId);
	    if (selNature) selNature.value = String(natId||0);
	    if (selDemeanor) selDemeanor.value = String(demId||0);

    var clanId   = parseInt(btn.getAttribute('data-clan') || '0',10) || 0;
    var manadaId = parseInt(btn.getAttribute('data-manada') || '0',10) || 0;
    selClan.value = String(clanId||0);
    updateManadas(clanId, manadaId);

    resetAvatarUI();
    var img = btn.getAttribute('data-img') || '';
    if (img) { avatarPrev.src = img; avatarPrev.style.display='block'; }

    // Poderes: cargar
    powList.innerHTML = '';
    var list = CHAR_POWERS[cid] || [];
    list.forEach(function(p){ addPowerChip(p.t, p.id, p.name, p.lvl); });
    powTipo.value = 'dones';
    refreshPowerSelect();

    // MYD: cargar
    mydList.innerHTML = '';
    mydLvl.value = '';
    refreshMydSelect();
    var ml = CHAR_MYD[cid] || [];
    ml.forEach(function(m){
      addMydChip(m.id, m.name, m.tipo, m.coste, m.nivel);
    });

    // INV: cargar
    invList.innerHTML = '';
    refreshInvSelect();
    var il = CHAR_ITEMS[cid] || [];
    il.forEach(function(it){
      addInvChip(it.id, it.name, it.tipo);
    });

    // RECURSOS: cargar (existentes) + defaults del sistema
    resList.innerHTML = '';
    refreshResourceSelect();
    var rl = CHAR_RESOURCES[cid] || [];
    rl.forEach(function(rr){
      addResourceChip(rr.id, rr.name, rr.kind, rr.perm, rr.temp, false);
    });
    ensureSystemResources(sistId);

    // Traits: cargar
    fillTraits(CHAR_TRAITS[cid] || {});
    applyKindVisibility(selKind ? selKind.value : 'pnj');

    fInfo.value   = '';
    fCumple.value = '';
    fRango.value  = '';
    ensureEstadoOption(DEFAULT_STATUS_ID);

    var d = CHAR_DETAILS[cid];
    if (d) {
      ensureEstadoOption(d.status_id || DEFAULT_STATUS_ID, d.status || '');
      fCumple.value = d.cumple || '';
      fRango.value  = d.rango || '';
      fInfo.value   = d.infotext || '';
    }

    mb.style.display='flex';
    initSelect2Modal();

    document.getElementById('f_nombre').focus();
  }

  // Modal binds
  btnNew.addEventListener('click', openCreate);
  btnCancel.addEventListener('click', function(){ mb.style.display='none'; });
  mb.addEventListener('click', function(e){ if (e.target === mb) mb.style.display='none'; });
  bindEditButtons();
  bindDeleteButtons();

  if (filterForm) {
    filterForm.addEventListener('submit', function(ev){
      ev.preventDefault();
      submitFilterAjax();
    });

    Array.prototype.forEach.call(
      filterForm.querySelectorAll('select[name="fil_cr"], select[name="fil_ma"], select[name="pp"]'),
      function(sel){
        sel.addEventListener('change', function(){
          submitFilterAjax();
        });
      }
    );
  }

  if (quick) {
    quick.addEventListener('input', function(){
      if (quickTimer) clearTimeout(quickTimer);
      quickTimer = setTimeout(function(){
        submitFilterAjax();
      }, 350);
    });
    quick.addEventListener('keydown', function(ev){
      if (ev.key !== 'Enter') return;
      ev.preventDefault();
      if (quickTimer) clearTimeout(quickTimer);
      submitFilterAjax();
    });
  }

  if (pagerWrap) {
    pagerWrap.addEventListener('click', function(ev){
      var anchor = ev.target.closest('a[href]');
      if (!anchor) return;
      ev.preventDefault();
      loadListViaAjax(anchor.getAttribute('href'), true);
    });
  }

  window.addEventListener('popstate', function(){
    var parsed = new URL(window.location.href);
    if ((parsed.searchParams.get('s') || '') !== 'admin_characters') return;
    loadListViaAjax(parsed.pathname + parsed.search + parsed.hash, false);
  });

  // Sistema change
  onSelectChange(selSistema, function(){
    var sys = parseInt(selSistema.value,10)||0;
    updateSistemaSets(sys, 0,0,0);
    applyTraitOrder(sys);
    ensureSystemResources(sys);
  });

  // Clan -> manadas
  onSelectChange(selClan, function(){
    var c = parseInt(selClan.value,10)||0;
    if (!c){
      clearSelect(selManada,false);
      var o=document.createElement('option'); o.value='0'; o.textContent='— Selecciona primero un Clan —';
      selManada.appendChild(o); selManada.disabled=true;
      reinitSelect2(selManada);
      return;
    }
    updateManadas(c, 0);
  });

  onSelectChange(selKind, function(){
    applyKindVisibility(selKind ? selKind.value : 'pnj');
  });
  applyKindVisibility(selKind ? selKind.value : 'pnj');

  // Avatar preview / remove
  avatar.addEventListener('change', function(){
    if (avatar.files && avatar.files[0]) {
      avatarPrev.src = URL.createObjectURL(avatar.files[0]);
      avatarPrev.style.display = 'block';
      avatarRm.checked = false;
    } else if (!avatarRm.checked && !avatarPrev.src) {
      avatarPrev.style.display = 'none';
    }
  });
  avatarRm.addEventListener('change', function(){
    if (avatarRm.checked) {
      avatar.value = '';
      avatarPrev.src = '';
      avatarPrev.style.display = 'none';
    }
  });

  function validateCrudForm(){
    var c = parseInt(selClan.value,10)||0;
    var m = parseInt(selManada.value,10)||0;
    if (!c) return 'Debes seleccionar un Clan.';
    if (m && MANADA_ID_TO_CLAN[String(m)] && parseInt(MANADA_ID_TO_CLAN[String(m)],10)!==c) {
      return 'La Manada seleccionada no pertenece al Clan elegido.';
    }
    var sys = parseInt(selSistema.value,10)||0;
    var rz = parseInt(selRaza.value,10)||0;
    var au = parseInt(selAusp.value,10)||0;
    var tr = parseInt(selTribu.value,10)||0;
    if (sys){
      if (rz && RAZA_ID_TO_SYS[String(rz)]   && parseInt(RAZA_ID_TO_SYS[String(rz)],10)   !== sys){ return 'La Raza no pertenece al Sistema elegido.'; }
      if (au && AUSP_ID_TO_SYS[String(au)]   && parseInt(AUSP_ID_TO_SYS[String(au)],10)   !== sys){ return 'El Auspicio no pertenece al Sistema elegido.'; }
      if (tr && TRIBU_ID_TO_SYS[String(tr)]  && parseInt(TRIBU_ID_TO_SYS[String(tr)],10)  !== sys){ return 'La Tribu no pertenece al Sistema elegido.'; }
    }
    if (!fEstado.value) return 'Debes seleccionar un Estado.';
    return '';
  }
  function notifyResult(msg, kind){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.notify === 'function') {
      window.HGAdminHttp.notify(msg, kind === 'error' ? 'error' : 'ok', 2600);
      return;
    }
    if (kind === 'error') alert(msg);
  }
  function requestErrorMessage(err, fallback){
    if (window.HGAdminHttp && typeof window.HGAdminHttp.errorMessage === 'function') {
      return window.HGAdminHttp.errorMessage(err);
    }
    if (err && err.payload && (err.payload.message || err.payload.msg)) {
      return err.payload.message || err.payload.msg;
    }
    if (err && err.message) return err.message;
    return fallback || 'Error en la peticion';
  }
  function fallbackPostForm(url, formData){
    var headers = { 'X-Requested-With': 'XMLHttpRequest' };
    if (window.ADMIN_CSRF_TOKEN) headers['X-CSRF-Token'] = window.ADMIN_CSRF_TOKEN;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: formData
    }).then(function(res){
      return res.text().then(function(text){
        var payload = {};
        try { payload = text ? JSON.parse(text) : {}; } catch (e) {
          payload = { ok: false, message: 'Respuesta no JSON', raw: text };
        }
        if (!res.ok || payload.ok === false) {
          var err = new Error((payload && (payload.message || payload.msg)) || ('HTTP ' + res.status));
          err.status = res.status;
          err.payload = payload;
          throw err;
        }
        return payload;
      });
    });
  }
  function saveCrudAjax(){
    if (!formCrud) return;
    if (formCrud.dataset.saving === '1') return;
    var validationError = validateCrudForm();
    if (validationError) {
      notifyResult(validationError, 'error');
      return;
    }
    var postUrl = AJAX_BASE || (formCrud.getAttribute('action') || (window.location.pathname + window.location.search));
    var formData = new FormData(formCrud);
    formData.set('ajax', '1');
    if (window.ADMIN_CSRF_TOKEN && !formData.get('csrf')) {
      formData.set('csrf', window.ADMIN_CSRF_TOKEN);
    }
    formCrud.dataset.saving = '1';
    var req = null;
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      req = window.HGAdminHttp.request(postUrl, {
        method: 'POST',
        body: formData,
        loadingEl: btnSave || formCrud
      });
    } else {
      req = fallbackPostForm(postUrl, formData);
    }
    req.then(function(payload){
      var msg = (payload && (payload.message || payload.msg)) || 'Personaje guardado';
      notifyResult(msg, 'ok');
      mb.style.display = 'none';
      loadListViaAjax(window.location.pathname + window.location.search, false);
    }).catch(function(err){
      var msg = requestErrorMessage(err, 'No se pudo guardar');
      notifyResult(msg, 'error');
      console.error('[admin_characters] error guardando CRUD AJAX:', err);
    }).finally(function(){
      formCrud.dataset.saving = '';
    });
  }
  function deleteCharacter(btn){
    var id = parseInt(btn.getAttribute('data-id') || '0', 10) || 0;
    if (!id) return;
    var name = (btn.getAttribute('data-nombre') || '').trim();
    var question = name ? ('Vas a desactivar a "' + name + '". Continuar?') : 'Vas a desactivar este personaje. Continuar?';
    if (!window.confirm(question)) return;

    var postUrl = AJAX_BASE || (window.location.pathname + window.location.search);
    var formData = new FormData();
    formData.set('ajax', '1');
    formData.set('crud_action', 'delete');
    formData.set('id', String(id));
    if (window.ADMIN_CSRF_TOKEN) formData.set('csrf', window.ADMIN_CSRF_TOKEN);

    var req = null;
    if (window.HGAdminHttp && typeof window.HGAdminHttp.request === 'function') {
      req = window.HGAdminHttp.request(postUrl, {
        method: 'POST',
        body: formData,
        loadingEl: btn
      });
    } else {
      req = fallbackPostForm(postUrl, formData);
    }

    req.then(function(payload){
      var msg = (payload && (payload.message || payload.msg)) || 'Personaje desactivado';
      notifyResult(msg, 'ok');
      loadListViaAjax(window.location.pathname + window.location.search, false);
    }).catch(function(err){
      var msg = requestErrorMessage(err, 'No se pudo desactivar');
      notifyResult(msg, 'error');
      console.error('[admin_characters] error desactivando personaje:', err);
    });
  }
  if (formCrud) {
    formCrud.addEventListener('submit', function(ev){
      ev.preventDefault();
      saveCrudAjax();
    });
  }

  // PODERES UI
  onSelectChange(powTipo, function(){ refreshPowerSelect(); });
  refreshPowerSelect();
  reinitSelect2(powTipo);
  reinitSelect2(powPoder);

  powAdd.addEventListener('click', function(){
    var t = powTipo.value;
    var pid = parseInt(powPoder.value,10)||0;
    if (!pid){ alert('Elige un poder.'); return; }
    var nm = powPoder.options[powPoder.selectedIndex].textContent;
    var lvl = parseInt(powLvl.value,10); if (isNaN(lvl)) lvl=0; lvl=Math.max(0,Math.min(9,lvl));
    addPowerChip(t, pid, nm, lvl);
  });

  // MYD UI
  refreshMydSelect();
  reinitSelect2(mydSel);

  mydAdd.addEventListener('click', function(){
    var mid = parseInt(mydSel.value,10)||0;
    if (!mid){ alert('Elige un Mérito o Defecto.'); return; }

    var base = null, tipo=null, coste=null;
    for (var i=0;i<MYD_OPTS.length;i++){
      if (parseInt(MYD_OPTS[i].id,10)===mid){
        base = MYD_OPTS[i].name;
        tipo = MYD_OPTS[i].tipo;
        coste= MYD_OPTS[i].coste;
        break;
      }
    }

    var raw = (mydLvl.value||'').trim();
    var nivel = (raw==='') ? null : parseInt(raw,10);
    if (raw!=='' && isNaN(nivel)) nivel = null;

    addMydChip(mid, base, tipo, coste, nivel);
    mydLvl.value = '';
  });

  // INVENTARIO UI
  refreshInvSelect();
  reinitSelect2(invSel);

  invAdd.addEventListener('click', function(){
    var iid = parseInt(invSel.value,10)||0;
    if (!iid){ alert('Elige un objeto.'); return; }

    var nm=null, tp=0;
    for (var i=0;i<ITEMS_OPTS.length;i++){
      if (parseInt(ITEMS_OPTS[i].id,10)===iid){
        nm = ITEMS_OPTS[i].name;
        tp = ITEMS_OPTS[i].tipo || 0;
        break;
      }
    }
    addInvChip(iid, nm, tp);
  });

  // RECURSOS UI
  refreshResourceSelect();
  reinitSelect2(resSel);
  resAdd.addEventListener('click', function(){
    var rid = parseInt(resSel.value,10)||0;
    if (!rid){ alert('Elige un recurso.'); return; }
    var meta = getResourceMeta(rid);
    addResourceChip(rid, meta ? meta.name : ('#'+rid), meta ? meta.kind : 'res', 0, 0, false);
    ensureSystemResources(parseInt(selSistema.value,10)||0);
  });

})();
