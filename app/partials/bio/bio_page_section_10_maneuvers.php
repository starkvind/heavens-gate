<?php
$baseManeuversJson = json_encode(array_values($bioBaseManeuvers ?? []), JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG);
?>
<div class="bioSheetPowers bio-maneuvers" data-bio-base-maneuvers='<?= h((string)$baseManeuversJson) ?>'>
    <fieldset class="bioSeccion">
        <legend><?= $titleManeuvers ?></legend>
        <div class="bio-maneuvers__list" data-bio-maneuver-list></div>
    </fieldset>
</div>
<script>
(() => {
    const root = document.querySelector('.bio-maneuvers[data-bio-base-maneuvers]');
    if (!root) return;
    const list = root.querySelector('[data-bio-maneuver-list]');
    let baseManeuvers = [];
    try { baseManeuvers = JSON.parse(root.dataset.bioBaseManeuvers || '[]'); } catch (_) { return; }

    const render = maneuvers => {
        list.innerHTML = '';
        if (!maneuvers.length) {
            const empty = document.createElement('p');
            empty.className = 'bio-maneuvers__empty';
            empty.textContent = 'No hay maniobras disponibles para esta forma.';
            list.appendChild(empty);
            return;
        }
        maneuvers.forEach(item => {
            const link = document.createElement('a');
            link.href = item.href;
            link.className = 'bio-maneuvers__link hg-tooltip';
            link.dataset.tip = 'maneuver';
            link.dataset.id = String(item.id);

            const card = document.createElement('div');
            card.className = 'bioSheetPower';
            const image = document.createElement('img');
            image.className = 'valign bio-inline-icon bio-maneuvers__icon';
            image.src = item.image_url
                ? (item.image_url.includes('/') ? item.image_url : '/img/maneuvers/' + item.image_url)
                : '/img/ui/icons/icon_machete.webp';
            image.alt = '';

            const name = document.createElement('span');
            name.className = 'bio-maneuvers__name';
            name.textContent = item.name;
            card.appendChild(image);
            card.appendChild(name);
            link.appendChild(card);
            list.appendChild(link);
        });
    };

    document.addEventListener('hg:form-change', event => {
        render((event.detail && event.detail.maneuvers) || []);
    });
    render(baseManeuvers);
})();
</script>