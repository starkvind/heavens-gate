(() => {
    'use strict';

    if (window.__hgForumItemViewerEmbedInstalled) {
        return;
    }
    window.__hgForumItemViewerEmbedInstalled = true;

    const itemFrameSelector = '.hg-inline-item-frame';
    const flowEmbedSelector = '.hgfv-body .hg-inline-message, .hgfv-body .hg-inline-roll, .hgfv-body .hg-inline-item-frame';
    const frameByWindow = new Map();
    const boundFrames = new WeakSet();

    function isWhitespaceText(node) {
        return node && node.nodeType === Node.TEXT_NODE && node.textContent.trim() === '';
    }

    function collapseAdjacentBreaks(element, direction) {
        let node = element[direction];
        let keptBreak = false;

        while (node) {
            const next = node[direction];

            if (isWhitespaceText(node)) {
                node.remove();
                node = next;
                continue;
            }

            if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'BR') {
                if (keptBreak) {
                    node.remove();
                } else {
                    keptBreak = true;
                }
                node = next;
                continue;
            }

            break;
        }
    }

    function normalizeEmbedSpacing(element) {
        if (!(element instanceof Element) || !element.matches(flowEmbedSelector)) return;
        collapseAdjacentBreaks(element, 'previousSibling');
        collapseAdjacentBreaks(element, 'nextSibling');
    }

    function normalizeEmbeds(root = document) {
        if (root instanceof Element) {
            normalizeEmbedSpacing(root);
        }
        root.querySelectorAll(flowEmbedSelector).forEach(normalizeEmbedSpacing);
    }

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
        normalizeEmbedSpacing(frame);
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
        if (root instanceof HTMLIFrameElement && root.matches(itemFrameSelector)) {
            registerFrame(root);
        }
        root.querySelectorAll(itemFrameSelector).forEach(registerFrame);
    }

    function initialize(root = document) {
        normalizeEmbeds(root);
        registerFrames(root);
    }

    window.addEventListener('message', (event) => {
        if (event.origin !== window.location.origin) return;
        const data = event.data;
        if (!data || data.type !== 'setHeight') return;

        let frame = frameByWindow.get(event.source);
        if (!frame) {
            frame = Array.from(document.querySelectorAll(itemFrameSelector))
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
        document.addEventListener('DOMContentLoaded', () => initialize(), { once: true });
    } else {
        initialize();
    }

    const observer = new MutationObserver((mutations) => {
        for (const mutation of mutations) {
            for (const node of mutation.addedNodes) {
                if (!(node instanceof Element)) continue;
                initialize(node);
            }
        }
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
