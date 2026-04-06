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
var RAZA_ID_TO_ALLOWED_SYS = BOOT.RAZA_ID_TO_ALLOWED_SYS || {};

var AUSP_BY_SYS       = BOOT.AUSP_BY_SYS || {};
var AUSP_ID_TO_SYS    = BOOT.AUSP_ID_TO_SYS || {};
var AUSP_ID_TO_ALLOWED_SYS = BOOT.AUSP_ID_TO_ALLOWED_SYS || {};

var TRIBUS_BY_SYS     = BOOT.TRIBUS_BY_SYS || {};
var TRIBU_ID_TO_SYS   = BOOT.TRIBU_ID_TO_SYS || {};
var TRIBU_ID_TO_ALLOWED_SYS = BOOT.TRIBU_ID_TO_ALLOWED_SYS || {};

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
var TRAITS_OPTS      = Array.isArray(BOOT.TRAITS_OPTS) ? BOOT.TRAITS_OPTS : [];
var CHAR_TRAITS      = BOOT.CHAR_TRAITS || {};
var TRAIT_KIND_ORDER = Array.isArray(BOOT.TRAIT_KIND_ORDER) ? BOOT.TRAIT_KIND_ORDER : [];
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
  var fRango      = document.getElementById('f_rango');
  var fInfo       = document.getElementById('f_infotext');
  var fNotes      = document.getElementById('f_notes');

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
  var fTraitsDirty = document.getElementById('f_traits_dirty');
  var traitSel = document.getElementById('trait_sel');
  var traitAdd = document.getElementById('trait_add');
  var traitDefaultList = document.getElementById('traitsDefaultList');
  var traitExtraList = document.getElementById('traitExtraList');
  var pjOnlyBlocks = document.querySelectorAll('.kind-pj-only');
  var noMonsterBlocks = document.querySelectorAll('.kind-no-monster');
  var traitMetaById = {};
  var traitBaseline = {};
  var traitTouchedIds = {};
  var traitRemovedIds = {};

  function rebuildTraitMetaIndex(){
    traitMetaById = {};
    (TRAITS_OPTS || []).forEach(function(trait){
      var id = parseInt(trait && trait.id, 10) || 0;
      if (!id) return;
      traitMetaById[String(id)] = trait;
    });
  }
  rebuildTraitMetaIndex();

  function applyBootData(nextBoot){
    if (!nextBoot || typeof nextBoot !== 'object') return;
    if (typeof nextBoot.AJAX_BASE === 'string') AJAX_BASE = nextBoot.AJAX_BASE;
    if (nextBoot.MANADAS_BY_CLAN) MANADAS_BY_CLAN = nextBoot.MANADAS_BY_CLAN;
    if (nextBoot.MANADA_ID_TO_CLAN) MANADA_ID_TO_CLAN = nextBoot.MANADA_ID_TO_CLAN;
    if (nextBoot.RAZAS_BY_SYS) RAZAS_BY_SYS = nextBoot.RAZAS_BY_SYS;
    if (nextBoot.RAZA_ID_TO_SYS) RAZA_ID_TO_SYS = nextBoot.RAZA_ID_TO_SYS;
    if (nextBoot.RAZA_ID_TO_ALLOWED_SYS) RAZA_ID_TO_ALLOWED_SYS = nextBoot.RAZA_ID_TO_ALLOWED_SYS;
    if (nextBoot.AUSP_BY_SYS) AUSP_BY_SYS = nextBoot.AUSP_BY_SYS;
    if (nextBoot.AUSP_ID_TO_SYS) AUSP_ID_TO_SYS = nextBoot.AUSP_ID_TO_SYS;
    if (nextBoot.AUSP_ID_TO_ALLOWED_SYS) AUSP_ID_TO_ALLOWED_SYS = nextBoot.AUSP_ID_TO_ALLOWED_SYS;
    if (nextBoot.TRIBUS_BY_SYS) TRIBUS_BY_SYS = nextBoot.TRIBUS_BY_SYS;
    if (nextBoot.TRIBU_ID_TO_SYS) TRIBU_ID_TO_SYS = nextBoot.TRIBU_ID_TO_SYS;
    if (nextBoot.TRIBU_ID_TO_ALLOWED_SYS) TRIBU_ID_TO_ALLOWED_SYS = nextBoot.TRIBU_ID_TO_ALLOWED_SYS;
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
    TRAITS_OPTS = Array.isArray(nextBoot.TRAITS_OPTS) ? nextBoot.TRAITS_OPTS : TRAITS_OPTS;
    CHAR_TRAITS = nextBoot.CHAR_TRAITS || CHAR_TRAITS;
    TRAIT_KIND_ORDER = Array.isArray(nextBoot.TRAIT_KIND_ORDER) ? nextBoot.TRAIT_KIND_ORDER : TRAIT_KIND_ORDER;
    TRAIT_SET_ORDER = nextBoot.TRAIT_SET_ORDER || TRAIT_SET_ORDER;
    CHAR_DETAILS = nextBoot.CHAR_DETAILS || CHAR_DETAILS;
    rebuildTraitMetaIndex();
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

  function isMonsterKind(kind){
    var normalized = normalizeText(kind);
    return normalized === 'monster' || normalized === 'mon';
  }

  function getTraitInputs(){
    return document.querySelectorAll('.trait-input');
  }

  function getTraitMeta(traitId){
    return traitMetaById[String(parseInt(traitId, 10) || 0)] || null;
  }

  function isTraitBlockedForMonster(trait){
    if (!trait) return false;
    var kindNorm = normalizeText(trait.kind || '');
    var classNorm = normalizeText(trait.classification || '');
    var isSecondary = classNorm.indexOf('002 secundarias') === 0;
    return kindNorm === 'trasfondos'
      || (isSecondary && (kindNorm === 'talentos' || kindNorm === 'tecnicas' || kindNorm === 'conocimientos'));
  }

  function getSystemTraitIds(systemId){
    var orderMap = (TRAIT_SET_ORDER && systemId && TRAIT_SET_ORDER[String(systemId)]) ? TRAIT_SET_ORDER[String(systemId)] : {};
    return Object.keys(orderMap).sort(function(a, b){
      var ao = parseInt(orderMap[a], 10);
      var bo = parseInt(orderMap[b], 10);
      if (ao !== bo) return ao - bo;
      var am = getTraitMeta(a);
      var bm = getTraitMeta(b);
      var an = normalizeText(am ? am.name : ('#' + a));
      var bn = normalizeText(bm ? bm.name : ('#' + b));
      return an.localeCompare(bn);
    }).map(function(id){
      return parseInt(id, 10) || 0;
    }).filter(Boolean);
  }

  function getTraitKindOrder(kinds){
    var known = Array.isArray(TRAIT_KIND_ORDER) ? TRAIT_KIND_ORDER.slice() : [];
    var extra = (kinds || []).filter(function(kind){
      return known.indexOf(kind) === -1;
    }).sort(function(a, b){
      return normalizeText(a).localeCompare(normalizeText(b));
    });
    return known.concat(extra).filter(function(kind){
      return (kinds || []).indexOf(kind) !== -1;
    });
  }

  function collectTraitValues(){
    var map = {};
    Array.prototype.forEach.call(getTraitInputs(), function(inp){
      var tid = parseInt(inp.getAttribute('data-trait-id'), 10) || 0;
      if (!tid) return;
      var val = parseInt(inp.value, 10);
      if (isNaN(val) || val < 0) val = 0;
      if (val > 10) val = 10;
      map[tid] = val;
    });
    return map;
  }

  function collectExtraTraitIds(){
    var ids = [];
    if (!traitExtraList) return ids;
    Array.prototype.forEach.call(traitExtraList.querySelectorAll('.trait-chip'), function(chip){
      var tid = parseInt(chip.dataset.id, 10) || 0;
      if (tid) ids.push(tid);
    });
    return ids;
  }

  function normalizeTraitValue(value){
    var normalized = parseInt(value, 10);
    if (isNaN(normalized) || normalized < 0) normalized = 0;
    if (normalized > 10) normalized = 10;
    return normalized;
  }

  function cloneTraitMap(map){
    var out = {};
    Object.keys(map || {}).forEach(function(key){
      var tid = parseInt(key, 10) || 0;
      if (!tid) return;
      out[String(tid)] = normalizeTraitValue(map[key]);
    });
    return out;
  }

  function getTraitMapValue(map, traitId){
    var key = String(parseInt(traitId, 10) || 0);
    if (!key || key === '0') return 0;
    if (!map || !Object.prototype.hasOwnProperty.call(map, key)) return 0;
    return normalizeTraitValue(map[key]);
  }

  function setTraitBaseline(map){
    traitBaseline = cloneTraitMap(map || {});
    traitTouchedIds = {};
    traitRemovedIds = {};
    resetTraitsDirty();
  }

  function markTraitTouched(traitId){
    traitId = parseInt(traitId, 10) || 0;
    if (!traitId) return;
    var key = String(traitId);
    traitTouchedIds[key] = true;
    delete traitRemovedIds[key];
    markTraitsDirty();
  }

  function markTraitRemoved(traitId){
    traitId = parseInt(traitId, 10) || 0;
    if (!traitId) return;
    var key = String(traitId);
    traitRemovedIds[key] = true;
    traitTouchedIds[key] = true;
    markTraitsDirty();
  }

  function onTraitInputChange(ev){
    var target = ev && ev.target ? ev.target : null;
    var traitId = parseInt(target && target.getAttribute('data-trait-id'), 10) || 0;
    if (!traitId) return;
    markTraitTouched(traitId);
  }

  function buildTraitDelta(){
    var currentMap = cloneTraitMap(collectTraitValues());
    var upserts = {};
    var deleteMap = {};

    Object.keys(traitTouchedIds).forEach(function(key){
      var traitId = parseInt(key, 10) || 0;
      if (!traitId) return;
      var baselineValue = getTraitMapValue(traitBaseline, traitId);
      var hasCurrent = Object.prototype.hasOwnProperty.call(currentMap, String(traitId));
      if (!hasCurrent) {
        if (traitRemovedIds[key] && baselineValue > 0) {
          deleteMap[String(traitId)] = traitId;
        }
        return;
      }

      var currentValue = getTraitMapValue(currentMap, traitId);
      if (currentValue > 0) {
        if (currentValue !== baselineValue) {
          upserts[String(traitId)] = currentValue;
        }
        return;
      }

      if (baselineValue > 0) {
        deleteMap[String(traitId)] = traitId;
      }
    });

    Object.keys(traitRemovedIds).forEach(function(key){
      var traitId = parseInt(key, 10) || 0;
      if (!traitId) return;
      if (getTraitMapValue(traitBaseline, traitId) > 0) {
        deleteMap[String(traitId)] = traitId;
      }
    });

    return {
      upserts: upserts,
      deletes: Object.keys(deleteMap).map(function(key){
        return parseInt(deleteMap[key], 10) || 0;
      }).filter(Boolean)
    };
  }

  function createTraitField(meta, value){
    var label = document.createElement('label');
    label.className = 'trait-item';
    label.setAttribute('data-trait-name', meta.name || ('#' + meta.id));
    label.setAttribute('data-trait-kind', meta.kind || '');
    label.setAttribute('data-trait-classification', meta.classification || '');

    var text = document.createElement('span');
    text.textContent = meta.name || ('#' + meta.id);
    label.appendChild(text);

    var input = document.createElement('input');
    input.className = 'inp trait-input';
    input.type = 'number';
    input.min = '0';
    input.max = '10';
    input.name = 'traits[' + meta.id + ']';
    input.setAttribute('data-trait-id', String(meta.id));
    input.value = String(Math.max(0, Math.min(10, parseInt(value, 10) || 0)));
    input.addEventListener('input', onTraitInputChange);
    input.addEventListener('change', onTraitInputChange);
    label.appendChild(input);

    return label;
  }

  function renderDefaultTraits(systemId, values){
    if (!traitDefaultList) return;
    traitDefaultList.innerHTML = '';

    var groups = {};
    var isMonster = isMonsterKind(selKind ? selKind.value : '');
    getSystemTraitIds(systemId).forEach(function(traitId){
      var meta = getTraitMeta(traitId);
      if (!meta) return;
      if (isMonster && isTraitBlockedForMonster(meta)) return;
      if (!groups[meta.kind]) groups[meta.kind] = [];
      groups[meta.kind].push(meta);
    });

    var groupKinds = getTraitKindOrder(Object.keys(groups));
    if (!groupKinds.length) {
      var empty = document.createElement('div');
      empty.className = 'small-note';
      empty.textContent = systemId ? 'Este sistema no tiene traits base configurados.' : 'Selecciona un sistema para cargar sus traits base.';
      traitDefaultList.appendChild(empty);
      return;
    }

    groupKinds.forEach(function(kind){
      var group = document.createElement('div');
      group.className = 'traits-group';

      var title = document.createElement('div');
      title.className = 'traits-title';
      title.textContent = kind;
      group.appendChild(title);

      var items = document.createElement('div');
      items.className = 'traits-items';

      groups[kind].forEach(function(meta){
        var traitId = parseInt(meta.id, 10) || 0;
        items.appendChild(createTraitField(meta, values && values[traitId] !== undefined ? values[traitId] : 0));
      });

      group.appendChild(items);
      traitDefaultList.appendChild(group);
    });
  }

  function refreshTraitSelect(){
    if (!traitSel) return;
    var sys = parseInt(selSistema && selSistema.value, 10) || 0;
    var exclude = {};
    getSystemTraitIds(sys).forEach(function(id){ exclude[String(id)] = true; });
    collectExtraTraitIds().forEach(function(id){ exclude[String(id)] = true; });
    var isMonster = isMonsterKind(selKind ? selKind.value : '');
    var list = (TRAITS_OPTS || []).filter(function(trait){
      if (!trait || !trait.id) return false;
      if (exclude[String(trait.id)]) return false;
      if (isMonster && isTraitBlockedForMonster(trait)) return false;
      return true;
    }).sort(function(a, b){
      var aKindIdx = TRAIT_KIND_ORDER.indexOf(a.kind);
      var bKindIdx = TRAIT_KIND_ORDER.indexOf(b.kind);
      var ao = aKindIdx >= 0 ? aKindIdx : 9999;
      var bo = bKindIdx >= 0 ? bKindIdx : 9999;
      if (ao !== bo) return ao - bo;
      var an = normalizeText(a.name || ('#' + a.id));
      var bn = normalizeText(b.name || ('#' + b.id));
      return an.localeCompare(bn);
    }).map(function(trait){
      return {
        id: trait.id,
        name: (trait.name || ('#' + trait.id)) + (trait.kind ? ' — ' + trait.kind : '')
      };
    });

    fillSelectFrom(list, traitSel, '— Sin traits extra —', 0);
  }

  function addTraitChip(traitId, value){
    if (!traitExtraList) return;
    traitId = parseInt(traitId, 10) || 0;
    if (!traitId) return;
    delete traitRemovedIds[String(traitId)];

    var existing = Array.prototype.find.call(traitExtraList.querySelectorAll('.trait-chip'), function(chip){
      return (parseInt(chip.dataset.id, 10) || 0) === traitId;
    });
    if (existing) {
      var existingInput = existing.querySelector('.trait-input');
      if (existingInput && value !== undefined && value !== null) {
        existingInput.value = String(Math.max(0, Math.min(10, parseInt(value, 10) || 0)));
      }
      return;
    }

    var meta = getTraitMeta(traitId) || { id: traitId, name: '#' + traitId, kind: 'Trait', classification: '' };
    var chip = document.createElement('span');
    chip.className = 'chip trait-chip';
    chip.dataset.id = String(traitId);
    chip.dataset.kind = meta.kind || '';

    var tag = document.createElement('span');
    tag.className = 'tag';
    tag.textContent = meta.kind || 'Trait';
    chip.appendChild(tag);

    var name = document.createElement('span');
    name.className = 'pname';
    name.textContent = meta.name || ('#' + traitId);
    chip.appendChild(name);

    var input = document.createElement('input');
    input.className = 'inp trait-input';
    input.type = 'number';
    input.min = '0';
    input.max = '10';
    input.name = 'traits[' + traitId + ']';
    input.setAttribute('data-trait-id', String(traitId));
    input.value = String(Math.max(0, Math.min(10, parseInt(value, 10) || 0)));
    input.addEventListener('input', onTraitInputChange);
    input.addEventListener('change', onTraitInputChange);
    chip.appendChild(input);

    var btnDel = document.createElement('button');
    btnDel.type = 'button';
    btnDel.className = 'btn btn-red btn-del-trait';
    btnDel.textContent = 'X';
    btnDel.addEventListener('click', function(){
      markTraitRemoved(traitId);
      chip.remove();
      refreshTraitSelect();
    });
    chip.appendChild(btnDel);

    traitExtraList.appendChild(chip);
  }

  function renderExtraTraits(systemId, values, preserveIds){
    if (!traitExtraList) return;
    traitExtraList.innerHTML = '';

    var defaultMap = {};
    getSystemTraitIds(systemId).forEach(function(id){ defaultMap[String(id)] = true; });
    var keepMap = {};
    (preserveIds || []).forEach(function(id){
      id = parseInt(id, 10) || 0;
      if (id) keepMap[String(id)] = true;
    });
    Object.keys(values || {}).forEach(function(id){
      var numericId = parseInt(id, 10) || 0;
      if (numericId && (parseInt(values[id], 10) || 0) > 0) {
        keepMap[String(numericId)] = true;
      }
    });

    var isMonster = isMonsterKind(selKind ? selKind.value : '');
    Object.keys(keepMap).map(function(id){
      return parseInt(id, 10) || 0;
    }).filter(Boolean).sort(function(a, b){
      var am = getTraitMeta(a) || { kind: '', name: '#' + a };
      var bm = getTraitMeta(b) || { kind: '', name: '#' + b };
      var aKindIdx = TRAIT_KIND_ORDER.indexOf(am.kind);
      var bKindIdx = TRAIT_KIND_ORDER.indexOf(bm.kind);
      var ao = aKindIdx >= 0 ? aKindIdx : 9999;
      var bo = bKindIdx >= 0 ? bKindIdx : 9999;
      if (ao !== bo) return ao - bo;
      return normalizeText(am.name).localeCompare(normalizeText(bm.name));
    }).forEach(function(traitId){
      var meta = getTraitMeta(traitId) || { id: traitId, name: '#' + traitId, kind: 'Trait', classification: '' };
      if (defaultMap[String(traitId)]) return;
      if (isMonster && isTraitBlockedForMonster(meta)) return;
      addTraitChip(traitId, values && values[traitId] !== undefined ? values[traitId] : 0);
    });
  }

  function renderTraitsUI(values, preserveExtraIds){
    var traitValues = values || collectTraitValues();
    var manualIds = Array.isArray(preserveExtraIds) ? preserveExtraIds : collectExtraTraitIds();
    var systemId = parseInt(selSistema && selSistema.value, 10) || 0;
    renderDefaultTraits(systemId, traitValues);
    renderExtraTraits(systemId, traitValues, manualIds);
    refreshTraitSelect();
  }

  function applyMonsterTraitFilter(_kind, trackTraitChanges){
    var currentValues = collectTraitValues();
    var currentExtraIds = collectExtraTraitIds();
    renderTraitsUI(currentValues, currentExtraIds);
    if (trackTraitChanges) markTraitsDirty();
  }

  function applyKindVisibility(kind, trackTraitChanges){
    var k = String(kind || '').toLowerCase();
    var isPj = (k !== 'pnj');
    var isMonster = isMonsterKind(k);
    pjOnlyBlocks.forEach(function(block){
      block.style.display = isPj ? '' : 'none';
    });
    noMonsterBlocks.forEach(function(block){
      block.style.display = (isPj && !isMonster) ? '' : 'none';
    });
    applyMonsterTraitFilter(kind, !!trackTraitChanges);
  }

  function clearSelect(sel, keepFirst){
    while (sel.options.length > (keepFirst ? 1 : 0)) sel.remove(keepFirst ? 1 : 0);
  }

  function fillSelectFrom(list, sel, placeholder, preselect){
    clearSelect(sel, false);

    if (!list || !list.length){
      sel.disabled = true;
      var o = document.createElement('option');
      o.value = '0';
      o.textContent = placeholder;
      sel.appendChild(o);
      sel.value = '0';
      reinitSelect2(sel);
      return false;
    }

    sel.disabled = false;
    var ph = document.createElement('option');
    ph.value = '0';
    ph.textContent = '— Elige —';
    sel.appendChild(ph);

    var found = false;
    list.forEach(function(it){
      var o = document.createElement('option');
      o.value = String(it.id);
      o.textContent = it.name;
      sel.appendChild(o);
      if (preselect && String(preselect) === String(it.id)) found = true;
    });

    sel.value = found ? String(preselect) : '0';
    reinitSelect2(sel);
    return found;
  }

  function updateManadas(clanId, preselect){
    var list = MANADAS_BY_CLAN[String(clanId || 0)] || [];
    fillSelectFrom(list, selManada, '— Sin manadas en este Clan —', preselect);
  }

  function updateSistemaSets(sys, preRaza, preAusp, preTribu){
    if (!sys){
      clearSelect(selRaza, false); var a1 = document.createElement('option'); a1.value = '0'; a1.textContent = '— Elige un Sistema —'; selRaza.appendChild(a1); selRaza.disabled = true; reinitSelect2(selRaza);
      clearSelect(selAusp, false); var a2 = document.createElement('option'); a2.value = '0'; a2.textContent = '— Elige un Sistema —'; selAusp.appendChild(a2); selAusp.disabled = true; reinitSelect2(selAusp);
      clearSelect(selTribu, false); var a3 = document.createElement('option'); a3.value = '0'; a3.textContent = '— Elige un Sistema —'; selTribu.appendChild(a3); selTribu.disabled = true; reinitSelect2(selTribu);
      return;
    }

    var okR = fillSelectFrom(RAZAS_BY_SYS[sys] || [], selRaza, '— Sin razas para este Sistema —', preRaza);
    var okA = fillSelectFrom(AUSP_BY_SYS[sys] || [], selAusp, '— Sin auspicios para este Sistema —', preAusp);
    var okT = fillSelectFrom(TRIBUS_BY_SYS[sys] || [], selTribu, '— Sin tribus para este Sistema —', preTribu);

    if (preRaza && !okR){
      var w = document.createElement('option'); w.value = String(preRaza); w.textContent = '[WARN] (Fuera del Sistema) ID ' + preRaza;
      selRaza.appendChild(w); selRaza.value = String(preRaza); selRaza.disabled = false;
      reinitSelect2(selRaza);
    }
    if (preAusp && !okA){
      var w2 = document.createElement('option'); w2.value = String(preAusp); w2.textContent = '[WARN] (Fuera del Sistema) ID ' + preAusp;
      selAusp.appendChild(w2); selAusp.value = String(preAusp); selAusp.disabled = false;
      reinitSelect2(selAusp);
    }
    if (preTribu && !okT){
      var w3 = document.createElement('option'); w3.value = String(preTribu); w3.textContent = '[WARN] (Fuera del Sistema) ID ' + preTribu;
      selTribu.appendChild(w3); selTribu.value = String(preTribu); selTribu.disabled = false;
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
    if (traitDefaultList) traitDefaultList.innerHTML = '';
    if (traitExtraList) traitExtraList.innerHTML = '';
    refreshTraitSelect();
  }

  function resetTraitsDirty(){
    if (fTraitsDirty) fTraitsDirty.value = '0';
  }

  function markTraitsDirty(){
    if (fTraitsDirty) fTraitsDirty.value = '1';
  }

  function fillTraits(map){
    var values = map || {};
    var systemId = parseInt(selSistema && selSistema.value, 10) || 0;
    var defaultIds = {};
    getSystemTraitIds(systemId).forEach(function(id){ defaultIds[String(id)] = true; });
    var extraIds = [];
    Object.keys(values).forEach(function(id){
      if (!defaultIds[String(id)]) extraIds.push(parseInt(id, 10) || 0);
    });
    renderTraitsUI(values, extraIds);
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

    ['nombre','alias','nombregarou','gender','concept','text_color','rango'].forEach(function(k){
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
    if (fNotes) fNotes.value = '';

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
    setTraitBaseline({});
    applyKindVisibility(selKind ? selKind.value : 'pnj', false);

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
    setTraitBaseline(CHAR_TRAITS[cid] || {});
    applyKindVisibility(selKind ? selKind.value : 'pnj', false);

    fInfo.value   = '';
    fRango.value  = '';
    if (fNotes) fNotes.value = '';
    ensureEstadoOption(DEFAULT_STATUS_ID);

    var d = CHAR_DETAILS[cid];
    if (d) {
      ensureEstadoOption(d.status_id || DEFAULT_STATUS_ID, d.status || '');
      fRango.value  = d.rango || '';
      fInfo.value   = d.infotext || '';
      if (fNotes) fNotes.value = d.notes || '';
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
    var currentTraitValues = collectTraitValues();
    var currentExtraTraitIds = collectExtraTraitIds();
    updateSistemaSets(sys, 0,0,0);
    renderTraitsUI(currentTraitValues, currentExtraTraitIds);
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
    applyKindVisibility(selKind ? selKind.value : 'pnj', false);
  });
  applyKindVisibility(selKind ? selKind.value : 'pnj', false);

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
    function isAllowedForSystem(allowedByIdMap, nativeByIdMap, valueId, systemId){
      var id = parseInt(valueId, 10) || 0;
      var sys = parseInt(systemId, 10) || 0;
      if (!id || !sys) return true;

      var idKey = String(id);
      var sysKey = String(sys);
      var allowedObj = allowedByIdMap && allowedByIdMap[idKey];
      if (allowedObj && typeof allowedObj === 'object') {
        return !!allowedObj[sysKey];
      }

      if (nativeByIdMap && Object.prototype.hasOwnProperty.call(nativeByIdMap, idKey)) {
        return (parseInt(nativeByIdMap[idKey], 10) || 0) === sys;
      }
      return true;
    }

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
      if (!isAllowedForSystem(RAZA_ID_TO_ALLOWED_SYS, RAZA_ID_TO_SYS, rz, sys)){ return 'La Raza no pertenece al Sistema elegido.'; }
      if (!isAllowedForSystem(AUSP_ID_TO_ALLOWED_SYS, AUSP_ID_TO_SYS, au, sys)){ return 'El Auspicio no pertenece al Sistema elegido.'; }
      if (!isAllowedForSystem(TRIBU_ID_TO_ALLOWED_SYS, TRIBU_ID_TO_SYS, tr, sys)){ return 'La Tribu no pertenece al Sistema elegido.'; }
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
    var traitKeys = [];
    formData.forEach(function(_value, key){
      if (key.indexOf('traits[') === 0) traitKeys.push(key);
    });
    traitKeys.forEach(function(key){ formData.delete(key); });

    var traitsDirty = (formData.get('traits_dirty') === '1');
    if (traitsDirty) {
      var traitDelta = buildTraitDelta();
      var upsertIds = Object.keys(traitDelta.upserts || {});
      if (!upsertIds.length && !(traitDelta.deletes || []).length) {
        traitsDirty = false;
      } else {
        formData.set('traits_mode', 'delta');
        upsertIds.forEach(function(id){
          formData.append('traits_upsert[' + id + ']', String(traitDelta.upserts[id]));
        });
        (traitDelta.deletes || []).forEach(function(id){
          formData.append('traits_delete[]', String(id));
        });
      }
    }
    formData.set('traits_dirty', traitsDirty ? '1' : '0');
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

  // TRAITS UI
  renderTraitsUI({}, []);
  reinitSelect2(traitSel);
  if (traitAdd) {
    traitAdd.addEventListener('click', function(){
      var traitId = parseInt(traitSel.value, 10) || 0;
      if (!traitId) { alert('Elige un trait.'); return; }
      addTraitChip(traitId, 0);
      refreshTraitSelect();
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
