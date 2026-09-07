(() => {
    'use strict';

    const HG_AVATAR_ORIGIN = 'https://naufragio-heavensgate.duckdns.org';
    const frameByWindow = new Map();

    function registerFrame(iframe) {
        if (!(iframe instanceof HTMLIFrameElement) || !iframe.contentWindow) {
            return;
        }
        frameByWindow.set(iframe.contentWindow, iframe);
    }

    function registerFrames(root = document) {
        root.querySelectorAll('.hgavatar_iframe').forEach(registerFrame);
    }

    window.addEventListener('message', (event) => {
        if (event.origin !== HG_AVATAR_ORIGIN) {
            return;
        }

        const data = event.data;
        if (!data || data.type !== 'setHeight') {
            return;
        }

        const height = Number(data.height);
        if (!Number.isFinite(height) || height <= 0) {
            return;
        }

        let iframe = frameByWindow.get(event.source);
        if (!iframe) {
            iframe = Array.from(document.querySelectorAll('.hgavatar_iframe'))
                .find((candidate) => candidate.contentWindow === event.source);
            if (iframe) {
                registerFrame(iframe);
            }
        }

        if (!iframe) {
            return;
        }

        iframe.style.height = `${Math.ceil(height)}px`;
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => registerFrames(), { once: true });
    } else {
        registerFrames();
    }

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (!(node instanceof Element)) {
                    continue;
                }
                if (node.matches('.hgavatar_iframe')) {
                    registerFrame(node);
                }
                registerFrames(node);
            }
        }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
