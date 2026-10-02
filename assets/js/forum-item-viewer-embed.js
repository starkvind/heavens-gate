(() => {
    'use strict';

    if (window.__hgForumItemViewerEmbedInstalled) {
        return;
    }
    window.__hgForumItemViewerEmbedInstalled = true;

    const itemFrameSelector = '.hg-inline-item-frame';
    const forumBodySelector = '.hgfv-body';
    const nestedFlowSelector = 'blockquote, .hg-bb-align-left, .hg-bb-align-center, .hg-bb-align-right, .hg-bb-align-justify, .hg-bb-spoiler-body';
    const frameByWindow = new Map();
    const boundFrames = new WeakSet();
    const structuredFlows = new WeakSet();
    const blockTags = new Set([
        'ADDRESS', 'ARTICLE', 'ASIDE', 'BLOCKQUOTE', 'DETAILS', 'DIV', 'DL',
        'FIGURE', 'FOOTER', 'FORM', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6',
        'HEADER', 'HR', 'IFRAME', 'MAIN', 'NAV', 'OL', 'P', 'PRE', 'SECTION',
        'TABLE', 'UL', 'VIDEO'
    ]);

    function isFlowBlock(node) {
        return node instanceof Element && blockTags.has(node.tagName);
    }

    function paragraphHasContent(paragraph) {
        if (!(paragraph instanceof HTMLParagraphElement)) return false;
        if (paragraph.textContent.trim() !== '') return true;
        return paragraph.querySelector('img, svg, iframe, video, audio, input, button') !== null;
    }

    function structureNestedFlow(node) {
        if (!(node instanceof Element)) return;
        if (node.matches(nestedFlowSelector)) {
            structureFlow(node);
        }
        node.querySelectorAll(nestedFlowSelector).forEach((nested) => {
            if (!nested.closest('.hg-inline-message, .hg-inline-roll')) {
                structureFlow(nested);
            }
        });
    }

    function structureFlow(container) {
        if (!(container instanceof Element) || structuredFlows.has(container)) return;
        structuredFlows.add(container);

        const sourceNodes = Array.from(container.childNodes);
        const fragment = document.createDocumentFragment();
        let paragraph = null;
        let pendingBreaks = 0;

        function ensureParagraph() {
            if (!paragraph) {
                paragraph = document.createElement('p');
                paragraph.className = 'hgfv-prose';
            }
            return paragraph;
        }

        function flushParagraph() {
            if (!paragraph) return;
            if (paragraphHasContent(paragraph)) {
                fragment.appendChild(paragraph);
            }
            paragraph = null;
            pendingBreaks = 0;
        }

        function applySinglePendingBreak() {
            if (pendingBreaks === 1 && paragraph && paragraphHasContent(paragraph)) {
                paragraph.appendChild(document.createElement('br'));
            }
            pendingBreaks = 0;
        }

        for (const node of sourceNodes) {
            if (node instanceof HTMLBRElement) {
                pendingBreaks++;
                if (pendingBreaks >= 2) {
                    flushParagraph();
                }
                continue;
            }

            if (node.nodeType === Node.TEXT_NODE) {
                if (node.textContent.trim() === '') {
                    if (pendingBreaks > 0 || !paragraph) {
                        continue;
                    }
                    paragraph.appendChild(node);
                    continue;
                }

                applySinglePendingBreak();
                ensureParagraph().appendChild(node);
                continue;
            }

            if (isFlowBlock(node)) {
                flushParagraph();
                pendingBreaks = 0;
                structureNestedFlow(node);
                fragment.appendChild(node);
                continue;
            }

            applySinglePendingBreak();
            ensureParagraph().appendChild(node);
        }

        flushParagraph();
        container.replaceChildren(fragment);
        container.classList.add('hgfv-flow-ready');
    }

    function structureForumBodies(root = document) {
        if (root instanceof Element && root.matches(forumBodySelector)) {
            structureFlow(root);
        }
        root.querySelectorAll(forumBodySelector).forEach(structureFlow);
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
        structureForumBodies(root);
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
