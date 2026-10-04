(() => {
    'use strict';

    const moveFormsNextToAttributes = () => {
        const forms = document.querySelector('.hg-mobile-forms');
        if (!forms) return;

        const attributeDetails = Array.from(document.querySelectorAll('details')).find((details) => {
            const summary = details.querySelector(':scope > summary');
            if (!summary) return false;
            return summary.textContent.trim().toLocaleLowerCase('es') === 'atributos';
        });

        if (!attributeDetails || !attributeDetails.parentElement) return;
        attributeDetails.insertAdjacentElement('afterend', forms);
        forms.classList.add('hg-mobile-forms--near-attributes');
    };

    const installFormOverrideRenderer = () => {
        const root = document.querySelector('.hg-mobile-forms[data-hg-mobile-forms]');
        if (!root || root.dataset.hgMobileOverrideReady === '1') return;
        root.dataset.hgMobileOverrideReady = '1';

        const select = root.querySelector('[data-hg-mobile-form-select]');
        const summary = root.querySelector('[data-hg-mobile-form-summary]');
        if (!select) return;

        let forms = [];
        try {
            forms = JSON.parse(root.dataset.hgMobileForms || '[]');
        } catch (_) {
            return;
        }

        if (forms.length && !forms.some((item) => String(item.id) === String(select.value))) {
            select.value = String(forms[0].id);
        }

        const cells = Array.from(document.querySelectorAll('[data-hg-mobile-form-trait]'));
        const labels = {};
        cells.forEach((cell) => {
            const label = cell.parentElement ? cell.parentElement.querySelector(':scope > span') : null;
            labels[String(cell.dataset.hgMobileFormTrait || '')] = label ? label.textContent.trim() : 'Atributo';
        });

        const hasOverride = (form, traitId) => {
            if (!form || !form.overrides) return false;
            return Object.prototype.hasOwnProperty.call(form.overrides, String(traitId))
                && form.overrides[String(traitId)] !== null
                && form.overrides[String(traitId)] !== '';
        };

        const resolveValue = (base, form, traitId) => {
            if (hasOverride(form, traitId)) {
                return Math.max(0, Number(form.overrides[String(traitId)] || 0));
            }
            const modifier = form && form.modifiers ? Number(form.modifiers[String(traitId)] || 0) : 0;
            return Math.max(0, Number(base || 0) + modifier);
        };

        const dotsHtml = (value) => {
            const safeValue = Math.max(0, Math.min(10, Number(value || 0)));
            return '<span class="hg-mobile-trait-dots" aria-label="' + safeValue + ' puntos">'
                + '&#9679;'.repeat(safeValue)
                + '&#9675;'.repeat(Math.max(0, 5 - safeValue))
                + '</span>';
        };

        const syncRollLinks = (form) => {
            const formId = Number(form && form.id ? form.id : 0);
            document.querySelectorAll('.hg-mobile-actions [data-mobile-action-card] a.boton2').forEach((link) => {
                let url;
                try {
                    url = new URL(link.getAttribute('href') || '/tools/dice', window.location.origin);
                } catch (_) {
                    return;
                }

                if (formId > 0) {
                    url.searchParams.set('form_id', String(formId));
                } else {
                    url.searchParams.delete('form_id');
                }

                link.setAttribute('href', url.pathname + url.search + url.hash);
            });
        };

        const render = () => {
            const form = forms.find((item) => String(item.id) === String(select.value)) || forms[0] || null;
            if (form && String(select.value) !== String(form.id)) select.value = String(form.id);

            cells.forEach((cell) => {
                const traitId = String(cell.dataset.hgMobileFormTrait || '');
                const base = Number(cell.dataset.hgMobileFormBase || 0);
                const total = resolveValue(base, form, traitId);
                const value = cell.querySelector('[data-hg-mobile-form-value]');
                const dots = cell.querySelector('[data-hg-mobile-form-dots]');
                if (value) value.textContent = String(total);
                if (dots) dots.innerHTML = dotsHtml(total);
            });

            syncRollLinks(form);

            if (!summary) return;
            if (!form) {
                summary.textContent = 'No hay una Forma activa disponible.';
                return;
            }

            const changes = cells.map((cell) => {
                const traitId = String(cell.dataset.hgMobileFormTrait || '');
                if (hasOverride(form, traitId)) {
                    return (labels[traitId] || ('Rasgo #' + traitId)) + ' = ' + resolveValue(cell.dataset.hgMobileFormBase || 0, form, traitId);
                }
                const modifier = form.modifiers ? Number(form.modifiers[traitId] || 0) : 0;
                if (modifier === 0) return '';
                return (labels[traitId] || ('Rasgo #' + traitId)) + ' ' + (modifier > 0 ? '+' : '') + modifier;
            }).filter(Boolean).join(' | ');
            summary.textContent = String(form.name || 'Forma') + ': ' + (changes || 'sin cambios de atributos') + '.';
        };

        select.addEventListener('change', render);
        render();
    };

    const init = () => {
        moveFormsNextToAttributes();
        installFormOverrideRenderer();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
})();
