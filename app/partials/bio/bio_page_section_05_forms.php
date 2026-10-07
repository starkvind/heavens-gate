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

    const maneuversForForm = (form) => {
        const merged = new Map();
        baseManeuvers.forEach(maneuver => {
            const id = Number(maneuver && maneuver.id || 0);
            if (id > 0) merged.set(id, maneuver);
        });
        if (form && Array.isArray(form.maneuvers)) {
            form.maneuvers.forEach(maneuver => {
                const id = Number(maneuver && maneuver.id || 0);
                if (id > 0) merged.set(id, maneuver);
            });
        }
        return Array.from(merged.values());
    };

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

    const hasOverride = (form, traitId) => {
        if (!form || !form.overrides) return false;
        return Object.prototype.hasOwnProperty.call(form.overrides, String(traitId))
            && form.overrides[String(traitId)] !== null
            && form.overrides[String(traitId)] !== '';
    };

    const resolvedTraitValue = (base, form, traitId) => {
        if (hasOverride(form, traitId)) {
            return Math.max(0, Number(form.overrides[String(traitId)] || 0));
        }
        const modifier = form && form.modifiers ? Number(form.modifiers[String(traitId)] || 0) : 0;
        return Math.max(0, Number(base || 0) + modifier);
    };

    const attributeGemUrl = (value) => {
        const gemValue = Math.min(9, Math.max(0, Number(value || 0)));
        return '/img/ui/gems/attr/gem-attr-0' + gemValue + '.webp';
    };

    const createAttributeRow = (value, className, ariaLabel) => {
        const row = document.createElement('div');
        row.className = 'bio-forms-detail__attribute-row ' + className;
        row.setAttribute('aria-label', ariaLabel);

        const gem = document.createElement('img');
        gem.className = 'bio-forms-detail__attribute-gem';
        gem.src = attributeGemUrl(value);
        gem.alt = '';
        gem.setAttribute('aria-hidden', 'true');
        gem.loading = 'lazy';
        gem.decoding = 'async';

        const number = document.createElement('span');
        number.className = 'bio-forms-detail__attribute-number';
        number.textContent = String(value);

        row.append(gem, number);
        return row;
    };

    const renderDetail = (form) => {
        if (!detailUi) return;

        const formId = form ? String(form.id) : '';
        detailUi.cards.forEach(card => {
            card.classList.toggle('is-active', card.dataset.formId === formId);
        });

        detailUi.title.textContent = form ? String(form.name || 'Forma') : 'Forma';
        detailUi.attributes.replaceChildren();

        traitCells.forEach(cell => {
            const traitId = String(cell.dataset.bioFormTraitId || '');
            const base = Number(cell.dataset.bioFormBase || 0);
            const modifier = form && form.modifiers ? Number(form.modifiers[traitId] || 0) : 0;
            const overridden = hasOverride(form, traitId);
            const total = resolvedTraitValue(base, form, traitId);
            const attributeLabel = labels[traitId] || ('Rasgo #' + traitId);

            const item = document.createElement('div');
            item.className = 'bio-forms-detail__attribute';

            const name = document.createElement('span');
            name.className = 'bio-forms-detail__attribute-name';
            name.textContent = attributeLabel;

            const rows = document.createElement('div');
            rows.className = 'bio-forms-detail__attribute-rows';

            const baseRow = createAttributeRow(base, 'is-base', attributeLabel + ', valor original: ' + base);
            const transformedRow = createAttributeRow(total, 'is-transformed', attributeLabel + ', valor transformado: ' + total);

            const delta = document.createElement('span');
            delta.className = 'bio-forms-detail__attribute-delta';
            if (form && overridden) {
                delta.textContent = 'FIJO';
                delta.classList.add('is-fixed');
            } else if (form && modifier !== 0) {
                delta.textContent = (modifier > 0 ? '+' : '') + modifier;
                delta.classList.add(modifier > 0 ? 'is-positive' : 'is-negative');
            } else {
                delta.textContent = '—';
            }
            transformedRow.appendChild(delta);

            if (total === 0) {
                item.classList.add('is-zero');
            }

            rows.append(baseRow, transformedRow);
            item.append(name, rows);
            detailUi.attributes.appendChild(item);
        });

        if (form) {
            detailUi.capabilities.hidden = false;
            detailUi.melee.textContent = Number(form.weapons || 0) === 1 ? 'Sí' : 'No';
            detailUi.firearms.textContent = Number(form.firearms || 0) === 1 ? 'Sí' : 'No';
            detailUi.regeneration.textContent = String(form.regeneration_label || (
                Number(form.hpregen || 0) > 0 ? String(Number(form.hpregen)) + ' / turno' : 'No'
            ));
            detailUi.description.textContent = plainDescription(form.description);
        } else {
            detailUi.capabilities.hidden = true;
            detailUi.description.textContent = 'No hay una Forma activa disponible.';
        }
    };

    const createFormCard = (form) => {
        const card = document.createElement('button');
        card.type = 'button';
        card.className = 'bio-forms-detail__card';
        card.dataset.formId = String(form.id);

        const silhouette = document.createElement('span');
        silhouette.className = 'bio-forms-detail__silhouette';
        silhouette.setAttribute('aria-hidden', 'true');

        const silhouetteUrl = publicImageUrl(form.silhouette_image_url);
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
        label.textContent = String(form.name || 'Forma');

        card.append(silhouette, label);
        card.addEventListener('click', () => {
            select.value = String(form.id);
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

        const cards = forms.map(createFormCard);
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
        const selectedForm = forms.find(item => String(item.id) === select.value) || forms[0] || null;
        if (selectedForm && String(select.value) !== String(selectedForm.id)) select.value = String(selectedForm.id);
        renderDetail(selectedForm);
    };

    const render = () => {
        const form = forms.find(item => String(item.id) === select.value) || forms[0] || null;
        if (form && String(select.value) !== String(form.id)) select.value = String(form.id);

        traitCells.forEach(cell => {
            const traitId = String(cell.dataset.bioFormTraitId || '');
            const base = Number(cell.dataset.bioFormBase || 0);
            const total = resolvedTraitValue(base, form, traitId);
            const value = cell.querySelector('.bio-form-attribute-value');
            const gem = cell.querySelector('img.bioAttCircle');
            if (value) value.textContent = form ? String(total) : '';
            if (gem) {
                gem.src = attributeGemUrl(total);
                gem.alt = (labels[traitId] || 'Atributo') + ': ' + total;
            }
        });

        if (!form) {
            summary.textContent = 'No hay una Forma activa disponible.';
        } else {
            const changes = traitCells.map(cell => {
                const traitId = String(cell.dataset.bioFormTraitId || '');
                if (hasOverride(form, traitId)) {
                    return (labels[traitId] || ('Rasgo #' + traitId)) + ' = ' + resolvedTraitValue(cell.dataset.bioFormBase || 0, form, traitId);
                }
                const modifier = form.modifiers ? Number(form.modifiers[traitId] || 0) : 0;
                if (modifier === 0) return '';
                return (labels[traitId] || ('Rasgo #' + traitId)) + ' ' + (modifier > 0 ? '+' : '') + modifier;
            }).filter(Boolean).join(' | ');
            summary.textContent = form.name + ': ' + (changes || 'sin cambios de atributos') + '.';
        }

        renderDetail(form);

        document.dispatchEvent(new CustomEvent('hg:form-change', {
            detail: { form: form, maneuvers: maneuversForForm(form) }
        }));
    };

    select.addEventListener('change', render);
    document.addEventListener('DOMContentLoaded', installDetailTab, { once: true });
    render();
})();
</script>