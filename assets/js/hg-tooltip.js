(() => {
  if (window.__hgTooltipBound) return;

  function initTooltip() {
    if (window.__hgTooltipBound) return true;
    if (!document.body) return false;

    window.__hgTooltipBound = true;
    window.__hgTooltipInitQueued = false;

    const cache = new Map();
    const pending = new Map();
    let tooltip = document.getElementById("hg-tooltip");
    if (!tooltip) {
      tooltip = document.createElement("div");
      tooltip.id = "hg-tooltip";
      document.body.appendChild(tooltip);
    }

    let timer = null;
    let currentKey = "";
    let lastX = 0;
    let lastY = 0;

    function getTooltipTarget(node) {
      return node instanceof Element
        ? node.closest(".hg-tooltip[data-tip][data-id]")
        : null;
    }

    function moveTip(x, y) {
      const pad = 14;
      const tw = tooltip.offsetWidth || 320;
      const th = tooltip.offsetHeight || 120;
      let left = x + pad;
      let top = y + pad;
      if (left + tw > window.innerWidth - 8) left = x - tw - pad;
      if (top + th > window.innerHeight - 8) top = y - th - pad;
      if (left < 8) left = 8;
      if (top < 8) top = 8;
      tooltip.style.left = left + "px";
      tooltip.style.top = top + "px";
    }

    function hideTip() {
      tooltip.style.display = "none";
      tooltip.innerHTML = "";
      currentKey = "";
    }

    function delay(ms) {
      return new Promise(resolve => window.setTimeout(resolve, ms));
    }

    function preloadTooltipImages(html) {
      const template = document.createElement("template");
      template.innerHTML = html;
      const sources = Array.from(template.content.querySelectorAll("img[src]"))
        .map(img => img.getAttribute("src"))
        .filter(Boolean);

      if (!sources.length) return Promise.resolve();

      return Promise.allSettled(sources.map(src => new Promise(resolve => {
        const image = new Image();
        image.decoding = "async";
        image.onload = resolve;
        image.onerror = resolve;
        image.src = src;
        if (image.complete) resolve();
      })));
    }

    function loadTooltip(type, id) {
      const key = `${type}:${id}`;
      if (cache.has(key)) return Promise.resolve(cache.get(key));
      if (pending.has(key)) return pending.get(key);

      const request = fetch(`/ajax/tooltip?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`)
        .then(res => res.text())
        .then(html => {
          const entry = {
            html,
            imagesReady: preloadTooltipImages(html),
          };
          cache.set(key, entry);
          return entry;
        })
        .finally(() => pending.delete(key));

      pending.set(key, request);
      return request;
    }

    async function showTipFor(target) {
      const type = target.getAttribute("data-tip") || "";
      const id = target.getAttribute("data-id") || "";
      if (!type || !id) return;

      const key = `${type}:${id}`;
      currentKey = key;

      try {
        const entry = await loadTooltip(type, id);
        if (currentKey !== key) return;

        // The request starts as soon as the pointer enters the target, so the
        // image normally reaches the browser cache before the tooltip appears.
        // Give a still-decoding image a very short grace period to avoid a
        // visible empty frame without making the tooltip feel sluggish.
        await Promise.race([entry.imagesReady, delay(120)]);
        if (currentKey !== key) return;

        tooltip.innerHTML = entry.html;
        tooltip.style.display = "block";
        moveTip(lastX, lastY);
      } catch (_err) {
        // Silencioso
      }
    }

    function warmTarget(target) {
      const type = target.getAttribute("data-tip") || "";
      const id = target.getAttribute("data-id") || "";
      if (!type || !id) return;
      void loadTooltip(type, id);
    }

    document.addEventListener("mousemove", (ev) => {
      lastX = ev.clientX;
      lastY = ev.clientY;
      if (tooltip.style.display === "block") moveTip(lastX, lastY);
    });

    document.addEventListener("mouseover", (ev) => {
      const target = getTooltipTarget(ev.target);
      if (!target) return;
      if (getTooltipTarget(ev.relatedTarget) === target) return;

      warmTarget(target);
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => showTipFor(target), 550);
    });

    document.addEventListener("mouseout", (ev) => {
      const target = getTooltipTarget(ev.target);
      if (!target) return;
      const related = ev.relatedTarget;
      if (related instanceof Node && target.contains(related)) return;
      if (timer) clearTimeout(timer);
      timer = null;
      hideTip();
    });

    document.addEventListener("focusin", (ev) => {
      const target = getTooltipTarget(ev.target);
      if (!target) return;
      warmTarget(target);
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => showTipFor(target), 250);
    });

    document.addEventListener("focusout", (ev) => {
      const target = getTooltipTarget(ev.target);
      if (!target) return;
      if (timer) clearTimeout(timer);
      timer = null;
      hideTip();
    });

    window.addEventListener("scroll", hideTip, true);
    return true;
  }

  if (!initTooltip() && !window.__hgTooltipInitQueued) {
    window.__hgTooltipInitQueued = true;
    document.addEventListener("DOMContentLoaded", initTooltip, { once: true });
  }
})();
