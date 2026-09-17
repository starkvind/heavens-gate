<?php

include_once(__DIR__ . '/../../helpers/public_response.php');
include_once(__DIR__ . '/../../helpers/character_avatar.php');
require_once(__DIR__ . '/../../domains/relationships/mobile_queries.php');
require_once(__DIR__ . '/../../domains/relationships/archive_queries.php');

$metaTitle = "Grupo | Heaven's Gate";
$metaDescription = "Ficha móvil de grupo u organizacion.";
$pageSect = 'Grupos';

if (!function_exists('hg_mobile_ogd_h')) {
    function hg_mobile_ogd_h($value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('hg_mobile_ogd_url')) {
    function hg_mobile_ogd_url(mysqli $link, string $table, string $base, int $id): string
    {
        return $id > 0 && function_exists('pretty_url')
            ? pretty_url($link, $table, $base, $id)
            : rtrim($base, '/') . '/' . $id;
    }
}

if (!function_exists('hg_mobile_ogd_group_url')) {
    function hg_mobile_ogd_group_url(mysqli $link, int $organizationId, int $groupId): string
    {
        if ($organizationId <= 0) {
            return hg_mobile_ogd_url($link, 'dim_groups', '/groups', $groupId);
        }
        $orgPath = (string)parse_url(hg_mobile_ogd_url($link, 'dim_organizations', '/organizations', $organizationId), PHP_URL_PATH);
        $groupPath = (string)parse_url(hg_mobile_ogd_url($link, 'dim_groups', '/groups', $groupId), PHP_URL_PATH);
        return '/groups/' . rawurlencode(basename($orgPath)) . '/' . rawurlencode(basename($groupPath));
    }
}

if (!function_exists('hg_mobile_ogd_resolve')) {
    function hg_mobile_ogd_resolve(mysqli $link, string $table, string $value): int
    {
        $value = trim($value);
        if ($value === '') return 0;
        if (preg_match('/^\d+$/', $value)) return (int)$value;
        return function_exists('resolve_pretty_id') ? (int)(resolve_pretty_id($link, $table, $value) ?? 0) : 0;
    }
}

if (!function_exists('hg_mobile_ogd_character_card')) {
    function hg_mobile_ogd_character_card(mysqli $link, array $character): void
    {
        $id = (int)($character['id'] ?? 0);
        $href = hg_mobile_ogd_url($link, 'fact_characters', '/characters', $id);
        $avatar = hg_character_avatar_url((string)($character['image_url'] ?? ''), (string)($character['gender'] ?? ''));
        ?>
        <a class="hg-mobile-character-card" href="<?= hg_mobile_ogd_h($href) ?>">
            <?php if ($avatar !== ''): ?>
                <img src="<?= hg_mobile_ogd_h($avatar) ?>" alt="">
            <?php else: ?>
                <span class="hg-mobile-character-avatar" aria-hidden="true"></span>
            <?php endif; ?>
            <span class="hg-mobile-character-main">
                <strong><?= hg_mobile_ogd_h($character['name'] ?? '') ?></strong>
                <?php if (!empty($character['alias'])): ?>
                    <em><?= hg_mobile_ogd_h($character['alias']) ?></em>
                <?php endif; ?>
                <?php if (!empty($character['position'])): ?>
                    <span><?= hg_mobile_ogd_h($character['position']) ?></span>
                <?php elseif (!empty($character['role'])): ?>
                    <span><?= hg_mobile_ogd_h($character['role']) ?></span>
                <?php endif; ?>
                <small><?= hg_mobile_ogd_h($character['status_label'] ?? '') ?></small>
            </span>
        </a>
        <?php
    }
}

if (!isset($link) || !($link instanceof mysqli)) {
    hg_public_log_error('mobile_organization_group_detail', 'missing DB connection');
    hg_public_render_error('Ficha no disponible', 'No se pudo cargar la ficha solicitada.');
    return;
}

$excludedChronicles = function_exists('hg_mobile_excluded_chronicles_csv')
    ? hg_mobile_excluded_chronicles_csv()
    : '2,7';
$type = (int)hg_request_param($hgRequest, 'group_type');
if ($type === 2) {
    $rawId = hg_request_param($hgRequest, 'organization');
    $preferredOrgRaw = '';
} else {
    $rawId = hg_request_param($hgRequest, 'group');
    $preferredOrgRaw = hg_request_param($hgRequest, 'organization');
}

if ($type === 1) {
    $id = hg_mobile_ogd_resolve($link, 'dim_groups', $rawId);
} elseif ($type === 2) {
    $id = hg_mobile_ogd_resolve($link, 'dim_organizations', $rawId);
} else {
    hg_public_render_not_found('Ficha no encontrada', 'El tipo solicitado no existe.');
    return;
}

if ($id <= 0) {
    hg_public_render_not_found('Ficha no encontrada', 'No se pudo localizar el elemento solicitado.');
    return;
}

if ($type === 2) {
    $item = hg_relationship_mobile_fetch_organization($link, $id);
    if (!$item) {
        hg_public_render_not_found('Organizacion no encontrada', 'No se pudo localizar la organizacion solicitada.');
        return;
    }

    $name = (string)($item['name'] ?? 'Organizacion');
    $metaTitle = $name . " | Organizaciones | Heaven's Gate";
    $metaDescription = trim(strip_tags((string)($item['description'] ?? '')));
    $orgChartHref = hg_relationship_archive_org_chart_available($link, $id)
        ? rtrim(hg_mobile_ogd_url($link, 'dim_organizations', '/organizations', $id), '/') . '/org-chart'
        : '';

    $activeGroups = hg_relationship_mobile_fetch_organization_groups($link, $id, true) ?? [];
    $inactiveGroups = hg_relationship_mobile_fetch_organization_groups($link, $id, false) ?? [];
    $directMembers = hg_relationship_mobile_fetch_direct_members($link, $id, $excludedChronicles) ?? [];
    $markdownData = hg_relationship_mobile_fetch_markdown_data($link, $id, $excludedChronicles) ?? ['members' => [], 'groups' => []];
    $markdownData['name'] = $name;
    $markdownData['description'] = (string)($item['description'] ?? '');
    $markdownJson = json_encode(
        $markdownData,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
    );
    ?>

    <article class="hg-mobile-bio">
        <nav class="hg-mobile-local-nav">
            <a href="/organizations?view=mobile">Volver a organizaciones</a>
        </nav>

        <section class="hg-mobile-section">
            <h1><?= hg_mobile_ogd_h($name) ?></h1>
            <div class="hg-mobile-fact-grid">
                <?php if (!empty($item['totem_name'])): ?><div><span>Totem</span><strong><?= hg_mobile_ogd_h($item['totem_name']) ?></strong></div><?php endif; ?>
                <div><span>Grupos activos</span><strong><?= count($activeGroups) ?></strong></div>
                <div><span>Grupos antiguos</span><strong><?= count($inactiveGroups) ?></strong></div>
            </div>
            <div class="hg-mobile-action-row hg-mobile-organization-actions">
                <button type="button" data-mobile-copy-organization>Copiar estructura</button>
                <?php if ($orgChartHref !== ''): ?>
                    <a href="<?= hg_mobile_ogd_h($orgChartHref) ?>">Ver organigrama</a>
                <?php endif; ?>
                <span class="hg-mobile-copy-status" data-mobile-copy-organization-status aria-live="polite"></span>
            </div>
        </section>

        <?php if (trim(strip_tags((string)($item['description'] ?? ''))) !== ''): ?>
            <section class="hg-mobile-section hg-mobile-prose">
                <h2>Descripción</h2>
                <?= (string)$item['description'] ?>
            </section>
        <?php endif; ?>

        <?php foreach (['Grupos activos' => $activeGroups, 'Grupos antiguos' => $inactiveGroups] as $title => $rows): ?>
            <?php if (empty($rows)) { continue; } ?>
            <section class="hg-mobile-section">
                <h2><?= hg_mobile_ogd_h($title) ?></h2>
                <div class="hg-mobile-list hg-mobile-linked-list">
                    <?php foreach ($rows as $group): ?>
                        <?php $href = hg_mobile_ogd_group_url($link, $id, (int)($group['id'] ?? 0)); ?>
                        <div>
                            <strong><a href="<?= hg_mobile_ogd_h($href) ?>"><?= hg_mobile_ogd_h($group['name'] ?? '') ?></a></strong>
                            <span><?= (int)($group['member_count'] ?? 0) ?> miembros<?= !empty($group['totem_name']) ? ' - Totem: ' . hg_mobile_ogd_h($group['totem_name']) : '' ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <?php if (!empty($directMembers)): ?>
            <section class="hg-mobile-section">
                <h2>Personajes sin grupo</h2>
                <div class="hg-mobile-character-list">
                    <?php foreach ($directMembers as $member): ?>
                        <?php hg_mobile_ogd_character_card($link, $member); ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </article>
    <script>
    (function () {
        var button = document.querySelector('[data-mobile-copy-organization]');
        var status = document.querySelector('[data-mobile-copy-organization-status]');
        var data = <?= $markdownJson ?: '{}' ?>;
        if (!button || !data) return;

        function text(html) {
            var node = document.createElement('div');
            node.innerHTML = html || '';
            return node.innerText.replace(/\n{3,}/g, '\n\n').trim();
        }

        function characterLines(character) {
            var lines = ['- **Nombre completo:** ' + String(character.name || '')];
            var alias = String(character.alias || '').trim();
            var garouName = String(character.garou_name || '').trim();
            var description = text(character.description);
            var state = String(character.status || '').trim();
            if (alias) lines.push('  - **Alias:** ' + alias);
            if (garouName) lines.push('  - **Nombre Garou:** ' + garouName);
            if (description) lines.push('  - **Descripción:** ' + description.replace(/\n/g, '\n    '));
            if (state && state.toLocaleLowerCase() !== 'en activo') lines.push('  - [' + state + ']');
            return lines;
        }

        function build() {
            var lines = ['# ' + data.name];
            var description = text(data.description);
            if (description) lines.push('', description);
            if (data.members.length) {
                lines.push('', '## Miembros sin grupo asociado', '');
                data.members.forEach(function (character) { lines = lines.concat(characterLines(character)); });
            }
            if (data.groups.length) {
                lines.push('', '## Grupos');
                data.groups.forEach(function (group) {
                    lines.push('', '### ' + group.name);
                    var groupDescription = text(group.description);
                    if (groupDescription) lines.push('', groupDescription);
                    if (group.members.length) {
                        lines.push('', '#### Miembros', '');
                        group.members.forEach(function (character) { lines = lines.concat(characterLines(character)); });
                    }
                });
            }
            return lines.join('\n').trim();
        }

        function copy(value) {
            if (navigator.clipboard && navigator.clipboard.writeText) return navigator.clipboard.writeText(value);
            var area = document.createElement('textarea');
            area.value = value;
            area.style.position = 'fixed';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();
            var copied = document.execCommand('copy');
            document.body.removeChild(area);
            return copied ? Promise.resolve() : Promise.reject();
        }

        button.addEventListener('click', function () {
            copy(build()).then(function () {
                status.textContent = 'Markdown copiado al portapapeles.';
                status.className = 'hg-mobile-copy-status is-ok';
            }).catch(function () {
                status.textContent = 'No se pudo copiar automáticamente.';
                status.className = 'hg-mobile-copy-status is-error';
            });
        });
    })();
    </script>
    <?php
    return;
}

$preferredOrgId = hg_mobile_ogd_resolve($link, 'dim_organizations', $preferredOrgRaw);
$item = hg_relationship_mobile_fetch_group($link, $id);
if (!$item) {
    hg_public_render_not_found('Grupo no encontrado', 'No se pudo localizar el grupo solicitado.');
    return;
}

$organization = hg_relationship_mobile_fetch_group_organization($link, $id, $preferredOrgId);
if ($organization === null) $organization = ['id' => 0, 'name' => ''];
$organizationId = (int)($organization['id'] ?? 0);

$name = (string)($item['name'] ?? 'Grupo');
$metaTitle = $name . " | Grupos | Heaven's Gate";
$metaDescription = trim(strip_tags((string)($item['description'] ?? '')));

$activeMembers = hg_relationship_mobile_fetch_group_members($link, $id, false, $excludedChronicles) ?? [];
$oldMembers = hg_relationship_mobile_fetch_group_members($link, $id, true, $excludedChronicles) ?? [];
?>

<article class="hg-mobile-bio">
    <nav class="hg-mobile-local-nav">
        <a href="/groups?view=mobile">Volver a grupos</a>
    </nav>

    <section class="hg-mobile-section">
        <h1><?= hg_mobile_ogd_h($name) ?></h1>
        <div class="hg-mobile-fact-grid">
            <?php if ($organizationId > 0): ?>
                <div><span>Organizacion</span><strong><a href="<?= hg_mobile_ogd_h(hg_mobile_ogd_url($link, 'dim_organizations', '/organizations', $organizationId)) ?>"><?= hg_mobile_ogd_h($organization['name'] ?? '') ?></a></strong></div>
            <?php endif; ?>
            <?php if (!empty($item['chronicle_name'])): ?><div><span>Cronica</span><strong><?= hg_mobile_ogd_h($item['chronicle_name']) ?></strong></div><?php endif; ?>
            <?php if (!empty($item['totem_name'])): ?><div><span>Totem</span><strong><?= hg_mobile_ogd_h($item['totem_name']) ?></strong></div><?php endif; ?>
            <div><span>Estado</span><strong><?= ((int)($item['is_active'] ?? 0) === 1) ? 'Activo' : 'Antiguo' ?></strong></div>
        </div>
    </section>

    <?php if (trim(strip_tags((string)($item['description'] ?? ''))) !== ''): ?>
        <section class="hg-mobile-section hg-mobile-prose">
            <h2>Descripción</h2>
            <?= (string)$item['description'] ?>
        </section>
    <?php endif; ?>

    <?php if (!empty($activeMembers)): ?>
        <section class="hg-mobile-section">
            <h2>Miembros</h2>
            <div class="hg-mobile-character-list">
                <?php foreach ($activeMembers as $member): ?>
                    <?php hg_mobile_ogd_character_card($link, $member); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($oldMembers)): ?>
        <section class="hg-mobile-section">
            <h2>Antiguos miembros</h2>
            <div class="hg-mobile-character-list">
                <?php foreach ($oldMembers as $member): ?>
                    <?php hg_mobile_ogd_character_card($link, $member); ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>