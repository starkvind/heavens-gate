<?php
$formsJson = json_encode(array_values($bioForms ?? []), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG);
$baseManeuversJson = json_encode(array_values($bioBaseManeuvers ?? []), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG);
?>
<div class="bio-forms"
     data-bio-forms='<?= h((string)$formsJson) ?>'
     data-bio-base-maneuvers='<?= h((string)$baseManeuversJson) ?>'>
    <div class="bio-forms__control">
        <label for="bio-form-select">Forma activa</label>
        <select id="bio-form-select" class="bio-forms__select">
            <option value="">Hom&iacute;nido</option>
            <?php foreach (($bioForms ?? []) as $form): ?>
                <option value="<?= (int)($form['id'] ?? 0) ?>"><?= h((string)($form['name'] ?? '')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <p class="bio-forms__summary" data-bio-form-summary>Elige una forma para comprobar sus cambios de Atributos.</p>
</div>
<script>
(() => {
    const root = document.querySelector('.bio-forms[data-bio-forms]');
    if (!root) return;

    const select = root.querySelector('#bio-form-select');
    const summary = root.querySelector('[data-bio-form-summary]');
    let forms = [];
    let baseManeuvers = [];
    let detailUi = null;

    try {
        forms = JSON.parse(root.dataset.bioForms || '[]');
        baseManeuvers = JSON.parse(root.dataset.bioBaseManeuvers || '[]');
    } catch (_) { return; }

    const traitCells = Array.from(document.querySelectorAll('[data-bio-form-trait-id]'));
    const labels = {};
    traitCells.forEach(cell => {
        const label = cell.previousElementSibling;
        labels[cell.dataset.bioFormTraitId] = label ? label.textContent.replace(':', '').trim() : 'Atributo';
    });

    const publicImageUrl = (value) => {
        const path = String(value || '').trim();
        if (!path) return '';
        if (path.startsWith('/') || /^(?:https?:)?\/\//i.test(path)) return path;
        return '/' + path.replace(/^\/+/, '');
    };

    const plainDescription = (value) => {
        const raw = String(value || '')
            .replace(/<br\s*\/?\s*>/gi, '\n')
            .replace(/<[^>]*>/g, '');
        const decoder = document.createElement('textarea');
        decoder.innerHTML = raw;
        return decoder.value.trim();
    };

    const resolvedTraitValue = (base, modifier) => Math.max(0, Number(base || 0) + Number(modifier || 0));

    const renderDetail = (form) => {
        if (!detailUi) return;

        const formId = form ? String(form.id) : '';
        detailUi.cards.forEach(card => {
            card.classList.toggle('is-active', card.dataset.formId === formId);
        });

        detailUi.title.textContent = form ? String(form.name || 'Forma') : 'Homínido';
        detailUi.attributes.replaceChildren();

        traitCells.forEach(cell => {
            const traitId = String(cell.dataset.bioFormTraitId || '');
            const base = Number(cell.dataset.bioFormBase || 0);
            const modifier = form && form.modifiers ? Number(form.modifiers[traitId] || 0) : 0;
            const total = resolvedTraitValue(base, modifier);

            const item = document.createElement('div');
            item.className = 'bio-forms-detail__attribute';

            const name = document.createElement('span');
            name.className = 'bio-forms-detail__attribute-name';
            name.textContent = labels[traitId] || ('Rasgo #' + traitId);

            const value = document.createElement('span');
            value.className = 'bio-forms-detail__attribute-value';
            value.textContent = form ? (base + ' → ' + total) : String(base);

            if (form && modifier !== 0) {
                const delta = document.createElement('span');
                delta.className = 'bio-forms-detail__delta';
                delta.textContent = '(' + (modifier > 0 ? '+' : '') + modifier + ')';
                value.appendChild(delta);
            }

            if (total === 0) {
                item.classList.add('is-zero');
            }

            item.append(name, value);
            detailUi.attributes.appendChild(item);
        });

        if (form) {
            detailUi.capabilities.hidden = false;
            detailUi.melee.textContent = Number(form.weapons || 0) === 1 ? 'Sí' : 'No';
            detailUi.firearms.textContent = Number(form.firearms || 0) === 1 ? 'Sí' : 'No';
            detailUi.regeneration.textContent = Number(form.hpregen || 0) > 0
                ? String(Number(form.hpregen)) + ' / turno'
                : 'No';
            detailUi.description.textContent = plainDescription(form.description);
        } else {
            detailUi.capabilities.hidden = true;
            detailUi.description.textContent = 'Atributos originales del personaje.';
        }
    };

    const createFormCard = (form) => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'bio-forms-detail__card';
        card.dataset.formId = form ? String(form.id) : '';

        const silhouette = document.createElement('span');
        silhouette.className = 'bio-forms-detail__silhouette';
        silhouette.setAttribute('aria-hidden', 'true');

        const silhouetteUrl = form ? publicImageUrl(form.silhouette_image_url) : '';
        if (silhouetteUrl) {
            const image = document.createElement('img');
            image.src = silhouetteUrl;
            image.alt = '';
            image.loading = 'lazy';
            image.decoding = 'async';
            silhouette.appendChild(image);
        }

        const label = document.createElement('span');
        label.className = 'bio-forms-detail__card-label';
        label.textContent = form ? String(form.name || 'Forma') : 'Homínido';

        card.append(silhouette, label);
        card.addEventListener('click', () => {
            select.value = form ? String(form.id) : '';
            select.dispatchEvent(new Event('change', { bubbles: true }));
        });
        return card;
    };

    const installDetailTab = () => {
        if (!forms.length || document.getElementById('sec-forms')) return;

        const tabs = document.querySelector('.hg-tabs');
        const sheetTab = tabs ? tabs.querySelector('.hgTabBtn[data-tab="sheet"]') : null;
        const bioBody = document.querySelector('.bioBody');
        if (!tabs || !sheetTab || !bioBody) return;

        const tab = document.createElement('button');
        tab.type = 'button';
        tab.className = 'hgTabBtn';
        tab.dataset.tab = 'forms';
        tab.innerHTML = '<span class="hgTabEmoji" aria-hidden="true"><img class="hgTabIcon" src="/img/ui/icons/icon_character_sheet.webp" alt="" width="16" height="16" loading="lazy" decoding="async"></span><span class="hgTabLabel">Formas</span>';
        sheetTab.insertAdjacentElement('afterend', tab);

        const panel = document.createElement('section');
        panel.id = 'sec-forms';
        panel.className = 'bio-tab-panel';
        panel.dataset.tab = 'forms';

        const detail = document.createElement('div');
        detail.className = 'bio-forms-detail';

        const rail = document.createElement('div');
        rail.className = 'bio-forms-detail__rail';
        rail.setAttribute('aria-label', 'Formas disponibles');

        const cards = [createFormCard(null), ...forms.map(createFormCard)];
        cards.forEach(card => rail.appendChild(card));

        const detailPanel = document.createElement('div');
        detailPanel.className = 'bio-forms-detail__panel';

        const title = document.createElement('h3');
        title.className = 'bio-forms-detail__title';

        const attributes = document.createElement('div');
        attributes.className = 'bio-forms-detail__attributes';

        const capabilities = document.createElement('div');
        capabilities.className = 'bio-forms-detail__capabilities';

        const makeCapability = (nameText) => {
            const item = document.createElement('div');
            item.className = 'bio-forms-detail__capability';
            const name = document.createElement('span');
            name.className = 'bio-forms-detail__capability-name';
            name.textContent = nameText;
            const value = document.createElement('span');
            value.className = 'bio-forms-detail__capability-value';
            item.append(name, value);
            capabilities.appendChild(item);
            return value;
        };

        const melee = makeCapability('Armas cuerpo a cuerpo');
        const firearms = makeCapability('Armas de fuego');
        const regeneration = makeCapability('Regeneración');

        const description = document.createElement('p');
        description.className = 'bio-forms-detail__description';

        detailPanel.append(title, attributes, capabilities, description);
        detail.append(rail, detailPanel);
        panel.appendChild(detail);
        bioBody.appendChild(panel);

        detailUi = { cards, title, attributes, capabilities, melee, firearms, regeneration, description };
        const selectedForm = forms.find(item => String(item.id) === select.value) || null;
        renderDetail(selectedForm);
    };

    const render = () => {
        const form = forms.find(item => String(item.id) === select.value) || null;
        const modifiers = form && form.modifiers ? form.modifiers : {};
        traitCells.forEach(cell => {
            const traitId = cell.dataset.bioFormTraitId;
            const base = Number(cell.dataset.bioFormBase || 0);
            const total = resolvedTraitValue(base, Number(modifiers[traitId] || 0));
            const value = cell.querySelector('.bio-form-attribute-value');
            const gem = cell.querySelector('img.bioAttCircle');
            if (value) value.textContent = form ? String(total) : '';
            if (gem) {
                const gemValue = Math.min(9, Math.max(0, total));
                gem.src = '/img/ui/gems/attr/gem-attr-0' + gemValue + '.webp';
                gem.alt = (labels[traitId] || 'Atributo') + ': ' + total;
            }
        });

        if (!form) {
            summary.textContent = 'Homínido: se muestran los atributos originales del personaje.';
        } else {
            const changes = Object.keys(modifiers)
                .filter(id => Number(modifiers[id]) !== 0)
                .map(id => (labels[id] || ('Rasgo #' + id)) + ' ' + (Number(modifiers[id]) > 0 ? '+' : '') + modifiers[id])
                .join(' | ');
            summary.textContent = form.name + ': ' + (changes || 'sin cambios de atributos') + '.';
        }

        renderDetail(form);

        document.dispatchEvent(new CustomEvent('hg:form-change', {
            detail: { form: form, maneuvers: form ? (form.maneuvers || []) : baseManeuvers }
        }));
    };

    select.addEventListener('change', render);
    document.addEventListener('DOMContentLoaded', installDetailTab, { once: true });
    render();
})();
</script>