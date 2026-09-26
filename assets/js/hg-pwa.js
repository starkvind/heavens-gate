(function () {
    'use strict';

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(function () {});
        });
    }

    const section = document.querySelector('[data-hg-pwa-section]');
    const installButton = document.querySelector('[data-hg-pwa-install]');
    const iosHelp = document.querySelector('[data-hg-pwa-ios-help]');
    if (!section || !installButton) return;

    let deferredPrompt = null;

    const standalone = window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    const ua = navigator.userAgent || '';
    const isIos = /iPad|iPhone|iPod/.test(ua)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    function hideInstall() {
        section.hidden = true;
        installButton.hidden = true;
        if (iosHelp) iosHelp.hidden = true;
    }

    function showInstall() {
        section.hidden = false;
        installButton.hidden = false;
    }

    if (standalone) {
        hideInstall();
        return;
    }

    window.addEventListener('beforeinstallprompt', function (event) {
        event.preventDefault();
        deferredPrompt = event;
        showInstall();
    });

    window.addEventListener('appinstalled', function () {
        deferredPrompt = null;
        hideInstall();
    });

    if (isIos) {
        showInstall();
    }

    installButton.addEventListener('click', async function () {
        if (deferredPrompt) {
            installButton.disabled = true;
            try {
                await deferredPrompt.prompt();
                await deferredPrompt.userChoice;
            } catch (error) {
                // Browser-owned install UI may be cancelled without affecting the site.
            } finally {
                deferredPrompt = null;
                installButton.disabled = false;
            }
            return;
        }

        if (isIos && iosHelp) {
            iosHelp.hidden = !iosHelp.hidden;
        }
    });
})();
