<?php
$bioActionsJson = json_encode(array_values($bioActions ?? []), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG);
$bioFormsJson = json_encode(array_values($bioForms ?? []), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG);
$bioBaseManeuversJson = json_encode(array_values($bioBaseManeuvers ?? []), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG);
$bioActionsLegend = '&nbsp;Acciones de ' . h((string)($bioName ?? 'este personaje')) . '&nbsp;';
?>
<section id="sec-actions" class="bio-tab-panel bio-actions" data-tab="actions"
         data-bio-actions='<?= h((string)$bioActionsJson) ?>'
         data-bio-forms='<?= h((string)$bioFormsJson) ?>'
         data-bio-base-maneuvers='<?= h((string)$bioBaseManeuversJson) ?>'
         data-bio-character-id="<?= (int)$characterId ?>">
    <div class="bioSheetData bio-actions__sheet">
        <fieldset class="bioSeccion bio-actions__fieldset">
            <legend><?= $bioActionsLegend ?></legend>
            <p class="bio-actions__intro">Acciones disponibles según los Atributos y Habilidades de <?php echo $bioName; ?>.</p>
            <label class="bio-actions__search"><span>Buscar acciones</span><input type="search" data-bio-action-search placeholder="Nombre, categoría, atributo o habilidad" autocomplete="off"></label>
            <p class="bio-actions__empty" data-bio-action-empty hidden>No hay acciones que coincidan con la búsqueda.</p>
            <div class="bio-actions__list" data-bio-action-list></div>
        </fieldset>
    </div>
</section>
<script>
(() => {
    const root = document.querySelector('.bio-actions[data-bio-actions]');
    if (!root) return;
    const list = root.querySelector('[data-bio-action-list]');
    const search = root.querySelector('[data-bio-action-search]');
    const empty = root.querySelector('[data-bio-action-empty]');
    const characterId = Number(root.dataset.bioCharacterId || 0);
    let actions = [];
    let forms = [];
    let baseManeuvers = [];
    let activeFormId = 0;
    let activeModifiers = {};
    let activeManeuverIds = new Set();
    try {
        actions = JSON.parse(root.dataset.bioActions || '[]');
        forms = JSON.parse(root.dataset.bioForms || '[]');
        baseManeuvers = JSON.parse(root.dataset.bioBaseManeuvers || '[]');
    } catch (_) { return; }

    const normalizeSearch = value => String(value || '').toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    const matchesSearch = (action, query) => !query || normalizeSearch([action.name, action.roll_label, action.category, action.text, action.attribute_name, action.skill_name].join(' ')).includes(query);
    const isManeuverVisible = action => action.source_type !== 'maneuver' || forms.length === 0 || activeManeuverIds.has(Number(action.source_id || action.id));
    const diceFor = action => Math.max(1, Number(action.attribute_value || 0) + Number(activeModifiers[String(action.attribute_trait_id)] || activeModifiers[action.attribute_trait_id] || 0)) + Number(action.skill_value || 0);

    const buildRollHref = (action, difficulty) => {
        const url = new URL('/tools/dice', window.location.origin);
        url.searchParams.set('character_id', String(characterId));
        url.searchParams.set('attr_trait_id', String(action.attribute_trait_id));
        url.searchParams.set('skill_trait_id', String(action.skill_trait_id));
        url.searchParams.set('dificultad', String(difficulty));
        url.searchParams.set('action_name', action.name);
        if (activeFormId > 0) url.searchParams.set('form_id', String(activeFormId));
        return url.pathname + url.search;
    };

    const buildActionCard = action => {
        const row = document.createElement('article');
        row.className = 'bio-actions__card';
        const main = document.createElement('div');
        main.className = 'bio-actions__main';

        const title = document.createElement('a');
        title.href = action.href;
        title.textContent = action.roll_label && action.roll_label !== action.name ? action.name + ': ' + action.roll_label : action.name;
        title.className = 'bio-actions__title hg-tooltip';
        title.dataset.tip = action.source_type === 'maneuver' ? 'maneuver' : 'action';
        title.dataset.id = String(action.source_id || action.id);
        main.appendChild(title);

        const combo = document.createElement('span');
        combo.className = 'bio-actions__combo';
        combo.textContent = action.attribute_name + ' + ' + action.skill_name;
        main.appendChild(combo);

        const meta = document.createElement('span');
        meta.className = 'bio-actions__meta';
        meta.textContent = action.difficulty_mode === 'special' || action.difficulty_mode === 'narrator' ? 'Tirada especial' : diceFor(action) + ' dados';
        main.appendChild(meta);
        row.appendChild(main);

        const controls = document.createElement('div');
        controls.className = 'bio-actions__controls';
        const difficultyBox = document.createElement('label');
        difficultyBox.className = 'bio-actions__difficulty';
        const difficultyLabel = document.createElement('span');
        difficultyLabel.textContent = 'Dificultad';
        difficultyBox.appendChild(difficultyLabel);
        let difficulty = Number(action.fixed_difficulty || action.suggested_difficulty || 6);

        const roll = document.createElement('a');
        roll.className = 'boton2 bio-actions__roll';
        if (action.difficulty_mode === 'variable') {
            const select = document.createElement('select');
            select.setAttribute('aria-label', 'Dificultad de ' + action.name);
            const min = Number(action.min_difficulty || 2);
            const max = Number(action.max_difficulty || 10);
            for (let value = min; value <= max; value++) {
                const option = document.createElement('option');
                option.value = String(value);
                option.textContent = String(value);
                option.selected = value === difficulty;
                select.appendChild(option);
            }
            difficultyBox.appendChild(select);
            roll.href = buildRollHref(action, difficulty);
            roll.textContent = 'Tirar';
            select.addEventListener('change', () => { difficulty = Number(select.value); roll.href = buildRollHref(action, difficulty); });
        } else if (action.difficulty_mode === 'fixed') {
            const fixed = document.createElement('strong');
            fixed.textContent = String(difficulty);
            difficultyBox.appendChild(fixed);
            roll.href = buildRollHref(action, difficulty);
            roll.textContent = 'Tirar';
        } else {
            const special = document.createElement('strong');
            special.textContent = 'Especial';
            difficultyBox.appendChild(special);
            roll.href = action.href;
            roll.textContent = 'Ver';
        }
        controls.appendChild(difficultyBox);
        controls.appendChild(roll);
        row.appendChild(controls);
        return row;
    };

    const render = () => {
        const query = normalizeSearch(search.value.trim());
        const categories = new Map();
        actions.forEach(action => {
            if (!isManeuverVisible(action) || !matchesSearch(action, query)) return;
            const category = String(action.category || '').trim() || 'Sin categoría';
            if (!categories.has(category)) categories.set(category, []);
            categories.get(category).push(action);
        });
        list.innerHTML = '';
        empty.hidden = categories.size > 0;
        categories.forEach((categoryActions, category) => {
            const section = document.createElement('details');
            section.className = 'bio-actions__category';
            section.open = true;
            const title = document.createElement('summary');
            title.className = 'bio-actions__category-title';
            const label = document.createElement('span');
            label.className = 'bio-actions__category-label';
            label.textContent = category;
            title.appendChild(label);
            const count = document.createElement('span');
            count.className = 'bio-actions__category-count';
            count.textContent = String(categoryActions.length);
            title.appendChild(count);
            section.appendChild(title);
            const categoryList = document.createElement('div');
            categoryList.className = 'bio-actions__category-list';
            categoryActions.forEach(action => categoryList.appendChild(buildActionCard(action)));
            section.appendChild(categoryList);
            list.appendChild(section);
        });
    };

    const setActiveForm = (form, maneuvers) => {
        activeFormId = form ? Number(form.id || 0) : 0;
        activeModifiers = form && form.modifiers ? form.modifiers : {};
        activeManeuverIds = new Set((Array.isArray(maneuvers) ? maneuvers : []).map(item => Number(item.id || 0)).filter(Boolean));
        render();
    };
    const initialFormSelect = document.querySelector('#bio-form-select');
    const initialForm = initialFormSelect ? forms.find(item => String(item.id) === initialFormSelect.value) || null : null;
    setActiveForm(initialForm, initialForm ? initialForm.maneuvers : baseManeuvers);
    document.addEventListener('hg:form-change', event => {
        const form = event.detail && event.detail.form ? event.detail.form : null;
        setActiveForm(form, event.detail && event.detail.maneuvers ? event.detail.maneuvers : baseManeuvers);
    });
    search.addEventListener('input', render);
})();
</script>
