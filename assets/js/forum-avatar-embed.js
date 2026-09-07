(() => {
    'use strict';

    if (window.__hgAvatarResizeInstalled) {
        return;
    }
    window.__hgAvatarResizeInstalled = true;

    const HG_AVATAR_ORIGIN = 'https://naufragio-heavensgate.duckdns.org';
    const LEGACY_HEIGHT_PADDING = 16;
    const frameByWindow = new Map();
    const boundFrames = new WeakSet();

    function requestFrameHeight(iframe) {
        if (!(iframe instanceof HTMLIFrameElement) || !iframe.contentWindow) {
            return;
        }

        iframe.contentWindow.postMessage({ type: 'requestHeight' }, HG_AVATAR_ORIGIN);
    }

    function registerFrame(iframe) {
        if (!(iframe instanceof HTMLIFrameElement) || !iframe.contentWindow) {
            return;
        }

        frameByWindow.set(iframe.contentWindow, iframe);

        if (!boundFrames.has(iframe)) {
            iframe.style.height = '1px';
            iframe.addEventListener('load', () => requestFrameHeight(iframe));
            boundFrames.add(iframe);
        }

        window.setTimeout(() => requestFrameHeight(iframe), 0);
    }

    window.hgAvatarRegisterFrame = registerFrame;
    window.hgAvatarRequestFrameHeight = requestFrameHeight;

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

        const reportedHeight = Number(data.height);
        if (!Number.isFinite(reportedHeight) || reportedHeight <= LEGACY_HEIGHT_PADDING) {
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

        const contentHeight = Math.max(1, Math.ceil(reportedHeight - LEGACY_HEIGHT_PADDING));
        iframe.style.height = `${contentHeight}px`;
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
