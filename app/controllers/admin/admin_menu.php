<?php
// admin_menu.php ? Editor de menu (dim_menu_items)
if (!isset($link) || !$link) { die("Error de conexion a la base de datos."); }
if (method_exists($link, 'set_charset')) { $link->set_charset('utf8mb4'); } else { mysqli_set_charset($link, 'utf8mb4'); }

include(__DIR__ . '/../../partials/admin/admin_styles.php');
admin_panel_open('Menu', '<button class="btn btn-green" type="button" id="btnAddMenu">+ Nuevo menu</button>');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ----------------------
// AJAX
// ----------------------
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=UTF-8');
    $raw = file_get_contents('php://input');
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = [];

    $action = (string)($payload['action'] ?? '');
    $ok = false; $msg = ''; $data = null;

    if ($action === 'update') {
        $id = (int)($payload['id'] ?? 0);
        $fields = (array)($payload['fields'] ?? []);
        $allowed = ['label','href','target','item_type','dynamic_source','css_class','icon','icon_hover','menu_key','enabled'];
        $set = []; $types = ''; $values = [];
        foreach ($fields as $k => $v) {
            if (!in_array($k, $allowed, true)) continue;
            if ($k === 'enabled') { $v = (int)((string)$v === '1' || $v === 1 || $v === true); $types .= 'i'; }
            else { $v = (string)$v; $types .= 's'; }
            $set[] = "$k=?";
            $values[] = $v;
        }
        if ($id > 0 && !empty($set)) {
            $sql = "UPDATE dim_menu_items SET " . implode(',', $set) . " WHERE id=?";
            if ($st = $link->prepare($sql)) {
                $types .= 'i';
                $values[] = $id;
                $st->bind_param($types, ...$values);
                $ok = $st->execute();
                $st->close();
            }
        }
    }

    if ($action === 'create') {
        $parentId = $payload['parent_id'] ?? null;
        $parentId = ($parentId === null || $parentId === '' ? null : (int)$parentId);
        $label = trim((string)($payload['label'] ?? 'Nuevo menu'));
        if ($label === '') $label = 'Nuevo menu';

        // next sort_order
        $next = 1;
        if ($parentId === null) {
            $res = $link->query("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM dim_menu_items WHERE parent_id IS NULL");
            if ($res && ($row = $res->fetch_assoc())) { $next = (int)$row['n']; }
            if ($res) $res->close();
        } else {
            if ($st = $link->prepare("SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM dim_menu_items WHERE parent_id = ?")) {
                $st->bind_param('i', $parentId);
                $st->execute();
                $st->bind_result($n);
                if ($st->fetch()) { $next = (int)$n; }
                $st->close();
            }
        }

        if ($parentId === null) {
            $sql = "INSERT INTO dim_menu_items (parent_id,label,href,target,item_type,dynamic_source,css_class,icon,icon_hover,menu_key,enabled,sort_order) VALUES (NULL, ?, '', '_self', 'static', '', '', '', '', '', 1, ?)";
            if ($st2 = $link->prepare($sql)) {
                $st2->bind_param('si', $label, $next);
                $ok = $st2->execute();
                $newId = $st2->insert_id;
                $st2->close();
            }
        } else {
            $sql = "INSERT INTO dim_menu_items (parent_id,label,href,target,item_type,dynamic_source,css_class,icon,icon_hover,menu_key,enabled,sort_order) VALUES (?, ?, '', '_self', 'static', '', '', '', '', '', 1, ?)";
            if ($st2 = $link->prepare($sql)) {
                $st2->bind_param('isi', $parentId, $label, $next);
                $ok = $st2->execute();
                $newId = $st2->insert_id;
                $st2->close();
            }
        }

        if ($ok ?? false) {
            $data = [
                'id' => (int)$newId,
                'parent_id' => $parentId,
                'label' => $label,
                'href' => '',
                'target' => '_self',
                'item_type' => 'static',
                'dynamic_source' => '',
                'css_class' => '',
                'icon' => '',
                'icon_hover' => '',
                'menu_key' => '',
                'enabled' => 1,
                'sort_order' => $next,
            ];
        }
    }

    if ($action === 'delete') {
        $ids = (array)($payload['ids'] ?? []);
        $ids = array_values(array_filter(array_map('intval', $ids), function($v){ return $v > 0; }));
        if (!empty($ids)) {
            $in = implode(',', array_fill(0, count($ids), '?'));
            $types = str_repeat('i', count($ids));
            $sql = "UPDATE dim_menu_items SET enabled=0 WHERE id IN ($in)";
            if ($st = $link->prepare($sql)) {
                $st->bind_param($types, ...$ids);
                $ok = $st->execute();
                $st->close();
            }
        }
    }

    if ($action === 'update_bulk') {
        $items = (array)($payload['items'] ?? []);
        $ok = true;
        foreach ($items as $it) {
            $id = (int)($it['id'] ?? 0);
            $fields = (array)($it['fields'] ?? []);
            if ($id <= 0 || empty($fields)) continue;
            $allowed = ['label','href','target','item_type','dynamic_source','css_class','icon','icon_hover','menu_key','enabled'];
            $set = []; $types = ''; $values = [];
            foreach ($fields as $k => $v) {
                if (!in_array($k, $allowed, true)) continue;
                if ($k === 'enabled') { $v = (int)((string)$v === '1' || $v === 1 || $v === true); $types .= 'i'; }
                else { $v = (string)$v; $types .= 's'; }
                $set[] = "$k=?";
                $values[] = $v;
            }
            if (!empty($set)) {
                $sql = "UPDATE dim_menu_items SET " . implode(',', $set) . " WHERE id=?";
                if ($st = $link->prepare($sql)) {
                    $types .= 'i';
                    $values[] = $id;
                    $ok = $ok && $st->bind_param($types, ...$values) && $st->execute();
                    $st->close();
                }
            }
        }
    }

    if ($action === 'reorder') {
        $items = (array)($payload['items'] ?? []);
        $ok = true;
        foreach ($items as $it) {
            $id = (int)($it['id'] ?? 0);
            $parentId = $it['parent_id'] ?? null;
            $order = (int)($it['sort_order'] ?? 0);
            if ($id <= 0) continue;
            if ($parentId === null || $parentId === '') {
                if ($st = $link->prepare("UPDATE dim_menu_items SET parent_id=NULL, sort_order=? WHERE id=?")) {
                    $st->bind_param('ii', $order, $id);
                    $ok = $ok && $st->execute();
                    $st->close();
                }
            } else {
                $pid = (int)$parentId;
                if ($st = $link->prepare("UPDATE dim_menu_items SET parent_id=?, sort_order=? WHERE id=?")) {
                    $st->bind_param('iii', $pid, $order, $id);
                    $ok = $ok && $st->execute();
                    $st->close();
                }
            }
        }
    }

    echo json_encode(['ok'=>$ok, 'msg'=>$msg, 'data'=>$data], JSON_UNESCAPED_UNICODE);
    exit;
}

// ----------------------
// DATA
// ----------------------
$iconBase = '/img/menu/';
$iconDir = rtrim($_SERVER['DOCUMENT_ROOT'] ?? __DIR__, '/') . '/public/img/menu';
$iconFiles = [];
if (is_dir($iconDir)) {
    foreach (glob($iconDir . '/*.png') as $f) { $iconFiles[] = basename($f); }
    sort($iconFiles);
}
$rows = [];
if ($rs = $link->query("SELECT id,parent_id,label,href,target,item_type,dynamic_source,css_class,icon,icon_hover,menu_key,enabled,sort_order FROM dim_menu_items ORDER BY parent_id, sort_order, id")) {
    while ($r = $rs->fetch_assoc()) { $rows[] = $r; }
    $rs->close();
}

$parents = [];
$children = [];
foreach ($rows as $r) {
    if ($r['parent_id'] === null) {
        $parents[] = $r;
    } else {
        $pid = (int)$r['parent_id'];
        if (!isset($children[$pid])) $children[$pid] = [];
        $children[$pid][] = $r;
    }
}
?>

<link rel="stylesheet" href="/assets/css/admin/admin.menu.css">


<div class="menu-admin">
    <div class="menu-cards" id="menuCards">
        <?php foreach ($parents as $p):
            $pid = (int)$p['id'];
            $kids = $children[$pid] ?? [];
        ?>
        <div class="menu-card" data-id="<?= (int)$p['id'] ?>">
            <div class="menu-header">
                <span class="drag-handle-top">||</span>
                <span class="badge">Menu</span>
                <input class="inp mi-input" data-field="label" value="<?= h($p['label']) ?>">
                <label class="chk"><input type="checkbox" data-field="enabled" <?= ((int)$p['enabled']===1?'checked':'') ?>> visible</label>
                <button class="btn" type="button" data-action="add-child">+ Item</button>
                <button class="btn btn-red" type="button" data-action="delete">Borrar menu</button>
            </div>
            <div class="menu-adv">
                <label>menu_key
                    <input class="inp mi-small" data-field="menu_key" value="<?= h($p['menu_key']) ?>">
                </label>
                <label>icon
                    <div class="icon-field">
                        <input class="inp mi-wide" data-field="icon" value="<?= h($p['icon']) ?>">
                        <button class="btn btn-small" type="button" data-action="pick-icon" data-target="icon">Elegir</button>
                        <img class="icon-preview" data-preview="icon" src="<?= h($p['icon']) ?>">
                    </div>
                </label>
                <label>icon_hover
                    <div class="icon-field">
                        <input class="inp mi-wide" data-field="icon_hover" value="<?= h($p['icon_hover']) ?>">
                        <button class="btn btn-small" type="button" data-action="pick-icon" data-target="icon_hover">Elegir</button>
                        <img class="icon-preview" data-preview="icon_hover" src="<?= h($p['icon_hover']) ?>">
                    </div>
                </label>
                <label>href
                    <input class="inp mi-wide" data-field="href" value="<?= h($p['href']) ?>">
                </label>
                <label>target
                    <select class="select mi-small" data-field="target">
                        <option value="_self" <?= ($p['target']==='_self'?'selected':'') ?>>_self</option>
                        <option value="_blank" <?= ($p['target']==='_blank'?'selected':'') ?>>_blank</option>
                    </select>
                </label>
                <label>item_type
                    <select class="select mi-small" data-field="item_type">
                        <option value="static" <?= ($p['item_type']==='static'?'selected':'') ?>>static</option>
                        <option value="dynamic" <?= ($p['item_type']==='dynamic'?'selected':'') ?>>dynamic</option>
                        <option value="separator" <?= ($p['item_type']==='separator'?'selected':'') ?>>separator</option>
                    </select>
                </label>
                <label>dynamic_source
                    <input class="inp mi-small" data-field="dynamic_source" value="<?= h($p['dynamic_source']) ?>">
                </label>
                <label>css_class
                    <input class="inp mi-small" data-field="css_class" value="<?= h($p['css_class']) ?>">
                </label>
            </div>

            <ul class="menu-items" data-parent="<?= (int)$p['id'] ?>">
                <?php foreach ($kids as $k): ?>
                <li class="menu-item" data-id="<?= (int)$k['id'] ?>">
                    <span class="drag-handle-item">::</span>
                    <input class="inp mi-input" data-field="label" value="<?= h($k['label']) ?>">
                    <input class="inp mi-wide" data-field="href" value="<?= h($k['href']) ?>">
                    <select class="select mi-small" data-field="target">
                        <option value="_self" <?= ($k['target']==='_self'?'selected':'') ?>>_self</option>
                        <option value="_blank" <?= ($k['target']==='_blank'?'selected':'') ?>>_blank</option>
                    </select>
                    <select class="select mi-small" data-field="item_type">
                        <option value="static" <?= ($k['item_type']==='static'?'selected':'') ?>>static</option>
                        <option value="dynamic" <?= ($k['item_type']==='dynamic'?'selected':'') ?>>dynamic</option>
                        <option value="separator" <?= ($k['item_type']==='separator'?'selected':'') ?>>separator</option>
                    </select>
                    <input class="inp mi-small" data-field="dynamic_source" value="<?= h($k['dynamic_source']) ?>" placeholder="dynamic_source">
                    <input class="inp mi-small" data-field="css_class" value="<?= h($k['css_class']) ?>" placeholder="css">
                    <label class="chk"><input type="checkbox" data-field="enabled" <?= ((int)$k['enabled']===1?'checked':'') ?>> visible</label>
                    <button class="btn btn-red" type="button" data-action="delete">Borrar</button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div id="iconPicker" class="icon-picker">
    <div class="icon-grid">
        <?php foreach ($iconFiles as $f): ?>
            <button class="icon-btn" type="button" data-icon="<?= h($iconBase . $f) ?>">
                <img src="<?= h($iconBase . $f) ?>" alt="">
            </button>
        <?php endforeach; ?>
    </div>
</div>

<div id="menuToast" class="toast">
    <span id="toastMsg">Guardado</span>
    <button class="btn" type="button" id="undoBtn">Deshacer</button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(function(){
    const menuCards = document.getElementById('menuCards');
    const iconPicker = document.getElementById('iconPicker');
    const toast = document.getElementById('menuToast');
    const toastMsg = document.getElementById('toastMsg');
    const undoBtn = document.getElementById('undoBtn');
    let currentIconTarget = null;
    const history = [];

    function api(action, payload){
        return fetch('/talim?s=admin_menu&ajax=1', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(Object.assign({ action: action }, payload || {}))
        }).then(r => r.json());
    }

    function showToast(msg){
        if (!toast) return;
        toastMsg.textContent = msg || 'Guardado';
        toast.style.display = 'flex';
        setTimeout(() => { if (toast) toast.style.display = 'none'; }, 1400);
    }

    function pushHistory(entry){
        history.push(entry);
        if (history.length > 50) history.shift();
        if (undoBtn) undoBtn.disabled = history.length === 0;
    }

    if (undoBtn) {
        undoBtn.addEventListener('click', function(){
            const entry = history.pop();
            if (!entry || typeof entry.undo !== 'function') return;
            entry.undo();
            showToast('Deshecho');
            if (undoBtn) undoBtn.disabled = history.length === 0;
        });
        undoBtn.disabled = true;
    }

    function setFieldValue(el, value){
        if (!el) return;
        if (el.type === 'checkbox') el.checked = !!value;
        else el.value = value;
        el.dataset.prev = (el.type === 'checkbox') ? (el.checked ? '1' : '0') : String(el.value);
        updatePreview(el);
    }

    function updatePreview(el){
        const row = el.closest('.menu-card');
        if (!row) return;
        const field = el.getAttribute('data-field');
        if (field === 'icon' || field === 'icon_hover') {
            const img = row.querySelector('.icon-preview[data-preview=\"'+field+'\"]');
            if (img) img.src = el.value || '';
        }
    }

    function getOrder(){
        const items = [];
        const cards = Array.from(menuCards.querySelectorAll('.menu-card'));
        cards.forEach((card, idx) => {
            const id = parseInt(card.getAttribute('data-id'),10)||0;
            if (id) items.push({ id: id, parent_id: null, sort_order: idx+1 });
            const list = card.querySelector('.menu-items');
            if (list) {
                Array.from(list.querySelectorAll('.menu-item')).forEach((li, i) => {
                    const cid = parseInt(li.getAttribute('data-id'),10)||0;
                    if (cid) items.push({ id: cid, parent_id: id, sort_order: i+1 });
                });
            }
        });
        return items;
    }

    function applyOrder(items){
        const byParent = {};
        items.forEach(it => {
            const pid = (it.parent_id === null || it.parent_id === '' ? 'root' : String(it.parent_id));
            if (!byParent[pid]) byParent[pid] = [];
            byParent[pid].push(it);
        });
        Object.keys(byParent).forEach(k => {
            byParent[k].sort((a,b) => a.sort_order - b.sort_order);
        });

        // reorder cards
        const root = byParent['root'] || [];
        root.forEach(it => {
            const card = menuCards.querySelector('.menu-card[data-id=\"'+it.id+'\"]');
            if (card) menuCards.appendChild(card);
        });

        // reorder children
        Object.keys(byParent).forEach(k => {
            if (k === 'root') return;
            const list = menuCards.querySelector('.menu-items[data-parent=\"'+k+'\"]');
            if (!list) return;
            byParent[k].forEach(it => {
                const li = list.querySelector('.menu-item[data-id=\"'+it.id+'\"]');
                if (li) list.appendChild(li);
            });
        });
    }

    function bindAutosave(scope){
        const inputs = scope.querySelectorAll('[data-field]');
        const timers = new Map();

        function scheduleSave(el){
            const row = el.closest('[data-id]');
            if (!row) return;
            const id = parseInt(row.getAttribute('data-id'),10)||0;
            if (!id) return;
            const field = el.getAttribute('data-field');
            let value = '';
            if (el.type === 'checkbox') value = el.checked ? 1 : 0;
            else value = el.value;

            const prev = el.dataset.prev !== undefined ? el.dataset.prev : (el.type === 'checkbox' ? (el.checked ? '1' : '0') : el.value);
            const key = id + ':' + field;
            if (timers.has(key)) clearTimeout(timers.get(key));
            timers.set(key, setTimeout(() => {
                api('update', { id: id, fields: { [field]: value } }).then(() => {
                    pushHistory({
                        undo: () => {
                            setFieldValue(el, prev);
                            api('update', { id: id, fields: { [field]: prev } });
                        }
                    });
                    showToast('Guardado');
                });
                el.dataset.prev = String(value);
                updatePreview(el);
            }, 350));
        }

        inputs.forEach(el => {
            el.dataset.prev = (el.type === 'checkbox') ? (el.checked ? '1' : '0') : String(el.value);
            updatePreview(el);
            el.addEventListener('change', () => scheduleSave(el));
            el.addEventListener('input', () => scheduleSave(el));
        });
    }

    function initSortables(){
        new Sortable(menuCards, {
            handle: '.drag-handle-top',
            animation: 150,
            onStart: function(){ menuCards.dataset.prevOrder = JSON.stringify(getOrder()); },
            onEnd: saveOrder
        });

        document.querySelectorAll('.menu-items').forEach(list => {
            new Sortable(list, {
                group: 'menu-items',
                handle: '.drag-handle-item',
                animation: 150,
                onStart: function(){ menuCards.dataset.prevOrder = JSON.stringify(getOrder()); },
                onEnd: saveOrder
            });
        });
    }

    function saveOrder(){
        const before = menuCards.dataset.prevOrder ? JSON.parse(menuCards.dataset.prevOrder) : null;
        const after = getOrder();
        api('reorder', { items: after }).then(() => {
            if (before) {
                pushHistory({
                    undo: () => {
                        applyOrder(before);
                        api('reorder', { items: before });
                    }
                });
            }
            showToast('Orden guardado');
        });
    }

    function createChild(parentId){
        api('create', { parent_id: parentId, label: 'Nuevo item' }).then(res => {
            if (!res || !res.ok || !res.data) return;
            const card = menuCards.querySelector('.menu-card[data-id="'+parentId+'"]');
            if (!card) return;
            const list = card.querySelector('.menu-items');
            if (!list) return;
            const it = buildItem(res.data);
            list.appendChild(it);
            bindAutosave(it);
            saveOrder();
            pushHistory({
                undo: () => {
                    it.classList.add('is-deleted');
                    api('update_bulk', { items: [{ id: res.data.id, fields: { enabled: 0 } }] });
                }
            });
            showToast('Item creado');
        });
    }

    function createMenu(){
        api('create', { parent_id: null, label: 'Nuevo menu' }).then(res => {
            if (!res || !res.ok || !res.data) return;
            const card = buildMenuCard(res.data);
            menuCards.appendChild(card);
            bindAutosave(card);
            initSortables();
            saveOrder();
            pushHistory({
                undo: () => {
                    card.classList.add('is-deleted');
                    api('update_bulk', { items: [{ id: res.data.id, fields: { enabled: 0 } }] });
                }
            });
            showToast('Menu creado');
        });
    }

    function deleteItem(el){
        const row = el.closest('[data-id]');
        if (!row) return;
        const id = parseInt(row.getAttribute('data-id'),10)||0;
        if (!id) return;
        const ids = [];
        if (row.classList.contains('menu-card')) {
            ids.push(id);
            row.querySelectorAll('.menu-item').forEach(li => {
                const cid = parseInt(li.getAttribute('data-id'),10)||0;
                if (cid) ids.push(cid);
            });
        } else {
            ids.push(id);
        }
        api('delete', { ids: ids }).then(res => {
            if (res && res.ok !== false) {
                row.classList.add('is-deleted');
                pushHistory({
                    undo: () => {
                        row.classList.remove('is-deleted');
                        api('update_bulk', { items: ids.map(i => ({ id: i, fields: { enabled: 1 } })) });
                    }
                });
                showToast('Eliminado');
                saveOrder();
            }
        });
    }

    function buildItem(data){
        const li = document.createElement('li');
        li.className = 'menu-item';
        li.setAttribute('data-id', data.id);
        li.innerHTML = `
            <span class="drag-handle-item">::</span>
            <input class="inp mi-input" data-field="label" value="${escapeHtml(data.label || '')}">
            <input class="inp mi-wide" data-field="href" value="${escapeHtml(data.href || '')}">
            <select class="select mi-small" data-field="target">
                <option value="_self" ${(data.target==='_self'?'selected':'')}>_self</option>
                <option value="_blank" ${(data.target==='_blank'?'selected':'')}>_blank</option>
            </select>
            <select class="select mi-small" data-field="item_type">
                <option value="static" ${(data.item_type==='static'?'selected':'')}>static</option>
                <option value="dynamic" ${(data.item_type==='dynamic'?'selected':'')}>dynamic</option>
                <option value="separator" ${(data.item_type==='separator'?'selected':'')}>separator</option>
            </select>
            <input class="inp mi-small" data-field="dynamic_source" value="${escapeHtml(data.dynamic_source || '')}" placeholder="dynamic_source">
            <input class="inp mi-small" data-field="css_class" value="${escapeHtml(data.css_class || '')}" placeholder="css">
            <label class="chk"><input type="checkbox" data-field="enabled" ${(parseInt(data.enabled,10)===1?'checked':'')}> visible</label>
            <button class="btn btn-red" type="button" data-action="delete">Borrar</button>
        `;
        li.querySelector('[data-action="delete"]').addEventListener('click', (e)=>{ deleteItem(e.currentTarget); });
        return li;
    }

    function buildMenuCard(data){
        const card = document.createElement('div');
        card.className = 'menu-card';
        card.setAttribute('data-id', data.id);
        card.innerHTML = `
            <div class="menu-header">
                <span class="drag-handle-top">||</span>
                <span class="badge">Menu</span>
                <input class="inp mi-input" data-field="label" value="${escapeHtml(data.label || '')}">
                <label class="chk"><input type="checkbox" data-field="enabled" ${(parseInt(data.enabled,10)===1?'checked':'')}> visible</label>
                <button class="btn" type="button" data-action="add-child">+ Item</button>
                <button class="btn btn-red" type="button" data-action="delete">Borrar menu</button>
            </div>
            <div class="menu-adv">
                <label>menu_key
                    <input class="inp mi-small" data-field="menu_key" value="${escapeHtml(data.menu_key || '')}">
                </label>
                <label>icon
                    <div class="icon-field">
                        <input class="inp mi-wide" data-field="icon" value="${escapeHtml(data.icon || '')}">
                        <button class="btn btn-small" type="button" data-action="pick-icon" data-target="icon">Elegir</button>
                        <img class="icon-preview" data-preview="icon" src="${escapeHtml(data.icon || '')}">
                    </div>
                </label>
                <label>icon_hover
                    <div class="icon-field">
                        <input class="inp mi-wide" data-field="icon_hover" value="${escapeHtml(data.icon_hover || '')}">
                        <button class="btn btn-small" type="button" data-action="pick-icon" data-target="icon_hover">Elegir</button>
                        <img class="icon-preview" data-preview="icon_hover" src="${escapeHtml(data.icon_hover || '')}">
                    </div>
                </label>
                <label>href
                    <input class="inp mi-wide" data-field="href" value="${escapeHtml(data.href || '')}">
                </label>
                <label>target
                    <select class="select mi-small" data-field="target">
                        <option value="_self" ${(data.target==='_self'?'selected':'')}>_self</option>
                        <option value="_blank" ${(data.target==='_blank'?'selected':'')}>_blank</option>
                    </select>
                </label>
                <label>item_type
                    <select class="select mi-small" data-field="item_type">
                        <option value="static" ${(data.item_type==='static'?'selected':'')}>static</option>
                        <option value="dynamic" ${(data.item_type==='dynamic'?'selected':'')}>dynamic</option>
                        <option value="separator" ${(data.item_type==='separator'?'selected':'')}>separator</option>
                    </select>
                </label>
                <label>dynamic_source
                    <input class="inp mi-small" data-field="dynamic_source" value="${escapeHtml(data.dynamic_source || '')}">
                </label>
                <label>css_class
                    <input class="inp mi-small" data-field="css_class" value="${escapeHtml(data.css_class || '')}">
                </label>
            </div>
            <ul class="menu-items" data-parent="${data.id}"></ul>
        `;
        card.querySelector('[data-action="add-child"]').addEventListener('click', () => createChild(data.id));
        card.querySelector('[data-action="delete"]').addEventListener('click', (e)=>{ deleteItem(e.currentTarget); });
        card.querySelectorAll('[data-action="pick-icon"]').forEach(btn => {
            btn.addEventListener('click', (e) => openIconPicker(e.currentTarget));
        });
        return card;
    }

    function escapeHtml(s){
        return String(s || '').replace(/[&<>"']/g, function(c){
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#39;'}[c]);
        });
    }

    function openIconPicker(btn){
        if (!iconPicker) return;
        currentIconTarget = btn.closest('.menu-card').querySelector('input[data-field="'+btn.getAttribute('data-target')+'"]');
        const rect = btn.getBoundingClientRect();
        iconPicker.style.left = (rect.left + window.scrollX) + 'px';
        iconPicker.style.top = (rect.bottom + window.scrollY + 6) + 'px';
        iconPicker.style.display = 'block';
    }

    function closeIconPicker(){
        if (iconPicker) iconPicker.style.display = 'none';
        currentIconTarget = null;
    }

    // Bind existing
    bindAutosave(menuCards);
    document.querySelectorAll('[data-action="add-child"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const card = e.currentTarget.closest('.menu-card');
            const id = card ? parseInt(card.getAttribute('data-id'),10) : 0;
            if (id) createChild(id);
        });
    });
    document.querySelectorAll('[data-action="delete"]').forEach(btn => {
        btn.addEventListener('click', (e) => deleteItem(e.currentTarget));
    });
    document.querySelectorAll('[data-action="pick-icon"]').forEach(btn => {
        btn.addEventListener('click', (e) => openIconPicker(e.currentTarget));
    });

    if (iconPicker) {
        iconPicker.querySelectorAll('.icon-btn').forEach(b => {
            b.addEventListener('click', () => {
                if (!currentIconTarget) return;
                const prev = currentIconTarget.dataset.prev !== undefined ? currentIconTarget.dataset.prev : '';
                const val = b.getAttribute('data-icon') || '';
                setFieldValue(currentIconTarget, val);
                const row = currentIconTarget.closest('[data-id]');
                const id = row ? parseInt(row.getAttribute('data-id'),10) : 0;
                const field = currentIconTarget.getAttribute('data-field');
                if (id && field) {
                    api('update', { id: id, fields: { [field]: val } }).then(() => {
                        pushHistory({
                            undo: () => {
                                setFieldValue(currentIconTarget, prev);
                                api('update', { id: id, fields: { [field]: prev } });
                            }
                        });
                        showToast('Guardado');
                    });
                }
                closeIconPicker();
            });
        });
        document.addEventListener('click', (e) => {
            if (!iconPicker.contains(e.target) && !e.target.closest('[data-action="pick-icon"]')) {
                closeIconPicker();
            }
        });
    }

    const btnAddMenu = document.getElementById('btnAddMenu');
    if (btnAddMenu) btnAddMenu.addEventListener('click', createMenu);

    initSortables();
})();
</script>

<?php admin_panel_close(); ?>
