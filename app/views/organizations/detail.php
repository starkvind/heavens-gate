<?php
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/pages/legacy/controllers-bio-bio_pack_page.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/pages/legacy/controllers-bio-bio_pack_page.css">';
}
?>
<h2><?= hg_bio_pack_page_h($namePack) ?></h2>
<?php if ($typePack === 2): ?>
    <?php $markdownJson = json_encode($markdownData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>
    <script>window.hgBioPackMarkdownData = <?= $markdownJson ?: '{}' ?>;</script>
<?php endif; ?>

<table class="notix">
    <tr>
        <td colspan="2" class="texti">
            <?php if ($typePack === 1 && $clanLink !== ''): ?>
                <b>Clan</b>: <?= $clanLink ?><br>
            <?php endif; ?>
            <?php if ($totemLink !== ''): ?>
                <b>Tótem</b>: <?= $totemLink ?><br>
            <?php endif; ?>
            <b>Descripción</b>:<br><br><?= $infoPack ?>
            <?php if ($typePack === 2): ?>
                <div class="bio-pack-copy-row">
                    <span class="bio-pack-copy-status" id="bio-pack-copy-md-status"></span>
                    <button type="button" class="bio-pack-copy-btn" id="bio-pack-copy-md-btn"
                            title="Copiar estructura de la organización en Markdown"
                            aria-label="Copiar estructura de la organización en Markdown">Copiar estructura</button>
                </div>
                <?php if ($orgChartAvailable): ?>
                    <?php $orgChartHref = rtrim(pretty_url($link, 'dim_organizations', '/organizations', $packId), '/') . '/org-chart'; ?>
                    <br><a class="bio-pack-action-link" href="<?= hg_bio_pack_page_h($orgChartHref) ?>">Ver organigrama</a>
                <?php endif; ?>
            <?php endif; ?>
        </td>
    </tr>

    <?php if ($typePack === 1 && !empty($activeMembers)): ?>
        <tr><td colspan="2" class="texti"><b>Miembros de <?= hg_bio_pack_page_h($namePack) ?></b>:<br><br>
            <div style="padding-left:30px;">
                <?php foreach ($activeMembers as $member) hg_bio_pack_page_render_character_tile($link, $member); ?>
            </div>
        </td></tr>
    <?php endif; ?>

    <?php if ($typePack === 1 && !empty($formerMembers)): ?>
        <tr><td colspan="2" class="texti"><b>Antiguos miembros</b>:<br><br>
            <div style="padding-left:30px;">
                <?php foreach ($formerMembers as $member) hg_bio_pack_page_render_character_tile($link, $member); ?>
            </div>
        </td></tr>
    <?php endif; ?>

    <?php if ($typePack === 2 && (!empty($activeGroups) || !empty($inactiveGroups))): ?>
        <?php $widthCell = (!empty($activeGroups) && !empty($inactiveGroups)) ? '50%' : '100%'; ?>
        <tr>
            <?php if (!empty($activeGroups)): ?>
                <td class="texti" style="width:<?= $widthCell ?>; vertical-align:top;"><b>En activo</b>:<br><ul>
                    <?php foreach ($activeGroups as $group): ?>
                        <li><a href="<?= hg_bio_pack_page_h(hg_bio_pack_page_group_url($link, $packId, (int)$group['id'])) ?>"><?= hg_bio_pack_page_h($group['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul></td>
            <?php endif; ?>
            <?php if (!empty($inactiveGroups)): ?>
                <td class="texti" style="width:<?= $widthCell ?>; vertical-align:top;"><b>Grupos antiguos</b>:<br><ul>
                    <?php foreach ($inactiveGroups as $group): ?>
                        <li><a href="<?= hg_bio_pack_page_h(hg_bio_pack_page_group_url($link, $packId, (int)$group['id'])) ?>"><?= hg_bio_pack_page_h($group['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul></td>
            <?php endif; ?>
        </tr>
    <?php endif; ?>

    <?php if ($typePack === 2 && !empty($ungroupedMembers)): ?>
        <tr><td colspan="2" class="texti"><b>Personajes</b>:<br><br>
            <div style="padding-left:30px;">
                <?php foreach ($ungroupedMembers as $member) hg_bio_pack_page_render_character_tile($link, $member); ?>
            </div>
        </td></tr>
    <?php endif; ?>
</table>

<?php if ($typePack === 2): ?>
<script>
(function () {
 var button=document.getElementById('bio-pack-copy-md-btn'),status=document.getElementById('bio-pack-copy-md-status'),data=window.hgBioPackMarkdownData;if(!button||!data)return;
 function text(html){var node=document.createElement('div');node.innerHTML=html||'';return node.innerText.replace(/\n{3,}/g,'\n\n').trim();}
 function characterLines(character){var lines=['- **Nombre completo:** '+String(character.name||'')],alias=String(character.alias||'').trim(),garouName=String(character.garou_name||'').trim(),description=text(character.description),state=String(character.status||'').trim();if(alias)lines.push('  - **Alias:** '+alias);if(garouName)lines.push('  - **Nombre Garou:** '+garouName);if(description)lines.push('  - **Descripción:** '+description.replace(/\n/g,'\n    '));if(state&&state.toLocaleLowerCase()!=='en activo')lines.push('  - ['+state+']');return lines;}
 function build(){var lines=['# '+data.name],description=text(data.description);if(description)lines.push('',description);if(data.members.length){lines.push('','## Miembros sin grupo asociado','');data.members.forEach(function(character){lines=lines.concat(characterLines(character));});}if(data.groups.length){lines.push('','## Grupos');data.groups.forEach(function(group){lines.push('','### '+group.name);var description=text(group.description);if(description)lines.push('',description);if(group.members.length){lines.push('','#### Miembros','');group.members.forEach(function(character){lines=lines.concat(characterLines(character));});}});}return lines.join('\n').trim();}
 function copy(value){if(navigator.clipboard&&navigator.clipboard.writeText)return navigator.clipboard.writeText(value);var area=document.createElement('textarea');area.value=value;area.style.position='fixed';area.style.left='-9999px';document.body.appendChild(area);area.select();var ok=document.execCommand('copy');document.body.removeChild(area);return ok?Promise.resolve():Promise.reject();}
 button.addEventListener('click',function(){copy(build()).then(function(){status.textContent='Markdown copiado al portapapeles.';status.className='bio-pack-copy-status is-ok';button.textContent='✓';window.setTimeout(function(){button.textContent='Copiar estructura de la organización';},1000);}).catch(function(){status.textContent='No se pudo copiar automáticamente.';status.className='bio-pack-copy-status is-error';});});
})();
</script>
<?php endif; ?>
