(function (w) {
    'use strict';

    function wireMultiselectAccessibility() {
        var doc = w.document;

        doc.querySelectorAll('.ms-wrap').forEach(function (wrap) {
            var toggle = wrap.querySelector('.ms-btn[role="button"]');
            var panel = wrap.querySelector('.ms-panel');
            if (!toggle || !panel) return;

            if (panel.id && !toggle.hasAttribute('aria-controls')) {
                toggle.setAttribute('aria-controls', panel.id);
            }
            if (toggle.id && !panel.hasAttribute('aria-labelledby')) {
                panel.setAttribute('aria-labelledby', toggle.id);
            }
        });

        doc.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;

            var target = event.target;
            var wrap = target && target.closest ? target.closest('.ms-wrap') : null;
            if (!wrap) return;

            var toggle = wrap.querySelector('.ms-btn[role="button"]');
            var panel = wrap.querySelector('.ms-panel');
            if (!toggle || !panel || panel.getAttribute('aria-hidden') !== 'false') return;

            event.preventDefault();
            panel.style.display = 'none';
            panel.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');
            toggle.focus();
        });
    }

    function init() {
        wireMultiselectAccessibility();

        var $ = w.jQuery;
        if (!$ || !$.fn || !$.fn.dataTable) return;

        if ($.fn.dataTable.ext) {
            $.fn.dataTable.ext.errMode = 'none';
        }

        if ($.fn.dataTable.defaults && !$.fn.dataTable.defaults.__hgDefaultsApplied) {
            $.extend(true, $.fn.dataTable.defaults, {
                language: {
                    search: '&#128269; Buscar:&nbsp;',
                    lengthMenu: 'Mostrar _MENU_ resultados',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ resultados',
                    infoEmpty: 'No hay resultados disponibles',
                    emptyTable: 'No hay datos en la tabla',
                    paginate: {
                        first: 'Primero',
                        last: 'Ultimo',
                        next: '&#9654;',
                        previous: '&#9664;'
                    }
                }
            });
            $.fn.dataTable.defaults.__hgDefaultsApplied = true;
        }
    }

    if (w.document.readyState === 'loading') {
        w.document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);