(function () {
    const formsRoot = document.querySelector('.hg-mobile-forms[data-hg-mobile-forms]');
    const actionsRoot = document.querySelector('.hg-mobile-actions[data-mobile-actions]');
    if (!formsRoot || !actionsRoot) return;

    const select = formsRoot.querySelector('[data-hg-mobile-form-select]');
    if (!select) return;

    const rollLinks = () => Array.from(actionsRoot.querySelectorAll('[data-mobile-action-card] a.boton2'));

    function syncFormContext() {
        const formId = parseInt(select.value || '0', 10);
        rollLinks().forEach(function (link) {
            let url;
            try {
                url = new URL(link.getAttribute('href') || '/tools/dice', window.location.origin);
            } catch (error) {
                return;
            }

            if (formId > 0) {
                url.searchParams.set('form_id', String(formId));
            } else {
                url.searchParams.delete('form_id');
            }

            link.setAttribute('href', url.pathname + url.search + url.hash);
        });
    }

    select.addEventListener('change', syncFormContext);
    document.addEventListener('hg-mobile-form-change', syncFormContext);
    syncFormContext();
})();
