(function () {
    'use strict';

    var selector = '.hg-help-figure img';

    function createLightbox() {
        var overlay = document.createElement('div');
        overlay.className = 'hg-help-lightbox';
        overlay.hidden = true;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Imagen ampliada');

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'hg-help-lightbox__close';
        close.setAttribute('aria-label', 'Cerrar imagen ampliada');
        close.textContent = '×';

        var image = document.createElement('img');
        image.className = 'hg-help-lightbox__image';
        image.alt = '';

        overlay.appendChild(close);
        overlay.appendChild(image);
        document.body.appendChild(overlay);

        function hide() {
            overlay.hidden = true;
            image.removeAttribute('src');
            document.body.classList.remove('hg-help-lightbox-open');
        }

        close.addEventListener('click', hide);
        overlay.addEventListener('click', function (event) {
            if (event.target === overlay) hide();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !overlay.hidden) hide();
        });

        return {
            show: function (source) {
                image.src = source.currentSrc || source.src;
                image.alt = source.alt || '';
                overlay.hidden = false;
                document.body.classList.add('hg-help-lightbox-open');
                close.focus();
            }
        };
    }

    function enhance(img) {
        if (!img || img.dataset.hgHelpZoomReady === '1') return;
        img.dataset.hgHelpZoomReady = '1';
        img.classList.add('hg-help-zoomable');
        img.tabIndex = 0;
        img.setAttribute('role', 'button');
        img.setAttribute('aria-label', (img.alt || 'Imagen') + '. Abrir ampliada');
    }

    function boot() {
        var lightbox = createLightbox();
        document.querySelectorAll(selector).forEach(enhance);

        document.addEventListener('click', function (event) {
            var img = event.target.closest ? event.target.closest(selector) : null;
            if (!img) return;
            event.preventDefault();
            lightbox.show(img);
        });

        document.addEventListener('keydown', function (event) {
            var target = event.target;
            if (!target || !target.matches || !target.matches(selector)) return;
            if (event.key !== 'Enter' && event.key !== ' ') return;
            event.preventDefault();
            lightbox.show(target);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
