(() => {
  if (window.__hgTooltipBound) return;

  function initTooltip() {
    if (window.__hgTooltipBound) return true;
    if (!document.body) return false;

    window.__hgTooltipBound = true;
    window.__hgTooltipInitQueued = false;

    const cache = new Map();
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

    async function showTipFor(target) {
      const type = target.getAttribute("data-tip") || "";
      const id = target.getAttribute("data-id") || "";
      if (!type || !id) return;

      const key = `${type}:${id}`;
      currentKey = key;
      if (cache.has(key)) {
        tooltip.innerHTML = cache.get(key);
        tooltip.style.display = "block";
        moveTip(lastX, lastY);
        return;
      }

      try {
        const res = await fetch(`/ajax/tooltip?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
        const html = await res.text();
        if (currentKey !== key) return;
        cache.set(key, html);
        tooltip.innerHTML = html;
        tooltip.style.display = "block";
        moveTip(lastX, lastY);
      } catch (_err) {
        // Silencioso
      }
    }

    document.addEventListener("mousemove", (ev) => {
      lastX = ev.clientX;
      lastY = ev.clientY;
      if (tooltip.style.display === "block") moveTip(lastX, lastY);
    });

    document.addEventListener("mouseover", (ev) => {
      const target = getTooltipTarget(ev.target);
      if (!target) return;
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
