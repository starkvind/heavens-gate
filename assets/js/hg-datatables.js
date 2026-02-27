(function (w) {
    'use strict';

    function init() {
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