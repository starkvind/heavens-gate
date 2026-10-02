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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', moveFormsNextToAttributes, { once: true });
    } else {
        moveFormsNextToAttributes();
    }
})();
