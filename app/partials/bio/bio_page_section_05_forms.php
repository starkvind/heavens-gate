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
    try {
        forms = JSON.parse(root.dataset.bioForms || '[]');
        baseManeuvers = JSON.parse(root.dataset.bioBaseManeuvers || '[]');
    } catch (_) { return; }

    const labels = {};
    document.querySelectorAll('[data-bio-form-trait-id]').forEach(cell => {
        const label = cell.previousElementSibling;
        labels[cell.dataset.bioFormTraitId] = label ? label.textContent.replace(':', '').trim() : 'Atributo';
    });

    const render = () => {
        const form = forms.find(item => String(item.id) === select.value) || null;
        const modifiers = form && form.modifiers ? form.modifiers : {};
        document.querySelectorAll('[data-bio-form-trait-id]').forEach(cell => {
            const traitId = cell.dataset.bioFormTraitId;
            const base = Number(cell.dataset.bioFormBase || 0);
            const total = Math.max(1, base + Number(modifiers[traitId] || 0));
            const value = cell.querySelector('.bio-form-attribute-value');
            const gem = cell.querySelector('img.bioAttCircle');
            if (value) value.textContent = form ? String(total) : '';
            if (gem) {
                const gemValue = Math.min(9, total);
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

        document.dispatchEvent(new CustomEvent('hg:form-change', {
            detail: { form: form, maneuvers: form ? (form.maneuvers || []) : baseManeuvers }
        }));
    };

    select.addEventListener('change', render);
    render();
})();
</script>