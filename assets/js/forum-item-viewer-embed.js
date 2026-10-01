(() => {
    'use strict';

    if (window.__hgForumItemViewerEmbedInstalled) {
        return;
    }
    window.__hgForumItemViewerEmbedInstalled = true;

    const selector = '.hg-inline-item-frame';
    const frameByWindow = new Map();
    const boundFrames = new WeakSet();

    function resizeFromDocument(frame) {
        try {
            const doc = frame.contentDocument;
            if (!doc || !doc.body) return;
            const height = Math.max(doc.body.scrollHeight, doc.documentElement ? doc.documentElement.scrollHeight : 0);
            if (height > 0) frame.style.height = `${Math.ceil(height)}px`;
        } catch (error) {
            // Same-origin access may be unavailable in a non-production preview.
        }
    }

    function registerFrame(frame) {
        if (!(frame instanceof HTMLIFrameElement)) return;
        if (frame.contentWindow) frameByWindow.set(frame.contentWindow, frame);

        if (!boundFrames.has(frame)) {
            frame.addEventListener('load', () => {
                if (frame.contentWindow) frameByWindow.set(frame.contentWindow, frame);
                resizeFromDocument(frame);
            });
            boundFrames.add(frame);
        }

        window.setTimeout(() => resizeFromDocument(frame), 0);
    }

    function registerFrames(root = document) {
        root.querySelectorAll(selector).forEach(registerFrame);
    }

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;
        const data = event.data;
        if (!data || data.type !== 'setHeight') return;

        let frame = frameByWindow.get(event.source);
        if (!frame) {
            frame = Array.from(document.querySelectorAll(selector))
                .find((candidate) => candidate.contentWindow === event.source);
            if (frame) registerFrame(frame);
        }
        if (!frame) return;

        const height = Number(data.height);
        if (Number.isFinite(height) && height > 0) {
            frame.style.height = `${Math.ceil(height)}px`;
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => registerFrames(), { once: true });
    } else {
        registerFrames();
    }

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (!(node instanceof Element)) continue;
                if (node.matches(selector)) registerFrame(node);
                registerFrames(node);
            }
        }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
