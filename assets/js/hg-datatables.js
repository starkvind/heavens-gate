(function (w) {
    'use strict';

    var legacyMultiselectStyleProps = [
        'font-family',
        'font-size',
        'font-weight',
        'line-height',
        'padding',
        'border',
        'border-color',
        'border-radius',
        'background-color',
        'color',
        'box-sizing',
        'height',
        'min-height',
        'outline',
        'box-shadow'
    ];

    function clearLegacyMultiselectInlineStyles() {
        w.document.querySelectorAll('.ms-btn').forEach(function (button) {
            legacyMultiselectStyleProps.forEach(function (property) {
                button.style.removeProperty(property);
            });
        });
    }

    function markWideDataTable(table, wrapper) {
        if (!table || !wrapper) return;

        var headerRow = table.tHead && table.tHead.rows.length ? table.tHead.rows[0] : null;
        var columnCount = headerRow ? headerRow.cells.length : 0;
        wrapper.classList.toggle('hg-datatable-wide', columnCount > 4);
    }

    function markExistingWideDataTables() {
        w.document.querySelectorAll('.dataTables_wrapper table.dataTable').forEach(function (table) {
            markWideDataTable(table, table.closest('.dataTables_wrapper'));
        });
    }

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

            var openPanels = Array.prototype.slice.call(
                doc.querySelectorAll('.ms-panel[aria-hidden="false"]')
            );
            if (!openPanels.length) return;

            var target = event.target;
            var targetWrap = target && target.closest ? target.closest('.ms-wrap') : null;
            var focusToggle = targetWrap ? targetWrap.querySelector('.ms-btn[role="button"]') : null;

            openPanels.forEach(function (panel) {
                var wrap = panel.closest('.ms-wrap');
                var toggle = wrap ? wrap.querySelector('.ms-btn[role="button"]') : null;

                panel.style.display = 'none';
                panel.setAttribute('aria-hidden', 'true');
                if (toggle) toggle.setAttribute('aria-expanded', 'false');

                if (!focusToggle && toggle) focusToggle = toggle;
            });

            event.preventDefault();
            if (focusToggle) focusToggle.focus();
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

        $(w.document).on('init.dt.hgMultiselectStyles', function (event, settings) {
            if (settings) {
                markWideDataTable(settings.nTable, settings.nTableWrapper);
            }
            w.setTimeout(clearLegacyMultiselectInlineStyles, 0);
        });
        w.setTimeout(function () {
            clearLegacyMultiselectInlineStyles();
            markExistingWideDataTables();
        }, 0);
    }

    if (w.document.readyState === 'loading') {
        w.document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})(window);
