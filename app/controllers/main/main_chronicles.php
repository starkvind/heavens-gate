<?php
if (function_exists('hg_page_register_stylesheet')) {
    hg_page_register_stylesheet('/assets/css/hg-archive.css');
    hg_page_register_stylesheet('/assets/css/hg-seasons.css');
} else {
    echo '<link rel="stylesheet" href="/assets/css/hg-archive.css">';
    echo '<link rel="stylesheet" href="/assets/css/hg-seasons.css">';
}
include_once(__DIR__ . '/../../helpers/public_response.php');
require_once(__DIR__ . '/../../domains/chronicles/queries.php');

if (!$link) {
    hg_public_log_error('main_chronicles', 'missing DB connection');
    hg_public_render_error('Crónicas no disponibles', 'No se pudieron cargar las crónicas en este momento.');
    return;
}

if (!function_exists('hg_ch_h')) {
    function hg_ch_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('hg_ch_excerpt')) {
    function hg_ch_excerpt(string $txt, int $max = 180): string {
        $txt = trim(strip_tags($txt));
        if ($txt === '') return '';
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return (mb_strlen($txt, 'UTF-8') > $max) ? (mb_substr($txt, 0, $max, 'UTF-8') . '...') : $txt;
        }
        return (strlen($txt) > $max) ? (substr($txt, 0, $max) . '...') : $txt;
    }
}
if (!function_exists('hg_ch_render_text')) {
    function hg_ch_render_text(string $txt): string {
        $txt = trim($txt);
        if ($txt === '') return '';
        if (preg_match('/<[^>]+>/', $txt)) return $txt;
        return nl2br(hg_ch_h($txt));
    }
}
if (!function_exists('hg_ch_image_route')) {
    function hg_ch_image_route(string $prettyId = '', int $id = 0): string {
        $slug = trim($prettyId);
        if ($slug === '') $slug = (string)$id;
        return '/chronicles/' . rawurlencode($slug) . '/image';
    }
}
if (!function_exists('hg_ch_kind_badge')) {
    function hg_ch_kind_badge(string $kind, int $number = 0): string {
        $kind = trim($kind);
        if ($kind === 'historia_personal') return 'Historia personal';
        if ($kind === 'especial') return 'Especial';
        if ($kind === 'inciso') {
            $incisoNum = $number;
            if ($incisoNum >= 100 && $incisoNum < 200) $incisoNum -= 100;
            return 'Inciso ' . ($incisoNum > 0 ? $incisoNum : '?');
        }
        return 'Temporada ' . ($number > 0 ? $number : '?');
    }
}
if (!function_exists('hg_ch_status_meta')) {
    function hg_ch_status_meta(int $finished): array {
        if ($finished === 1) return ['Finalizada', 'season-home-status--done'];
        if ($finished === 2) return ['Cancelada', 'season-home-status--cancelled'];
        return ['En curso', 'season-home-status--active'];
    }
}
if (!function_exists('hg_ch_affiliation_label')) {
    function hg_ch_affiliation_label(string $orgs, string $groups): string {
        $orgs = trim($orgs);
        $groups = trim($groups);
        if ($orgs !== '' && $groups !== '') return $orgs . ' / ' . $groups;
        if ($orgs !== '') return $orgs;
        if ($groups !== '') return $groups;
        return '-';
    }
}
if (!function_exists('hg_ch_season_image')) {
    function hg_ch_season_image(string $kind): string {
        if ($kind === 'inciso') return '/img/og/og_image.webp';
        if ($kind === 'historia_personal') return '/img/og/og_image_bio.webp';
        if ($kind === 'especial') return '/img/og/og_image_power.webp';
        return '/img/og/og_image_temp.webp';
    }
}
if (!function_exists('hg_ch_count_label')) {
    function hg_ch_count_label(int $count, string $singular, string $plural): string {
        if ($count <= 0) return '';
        return number_format($count, 0, ',', '.') . ' ' . ($count === 1 ? $singular : $plural);
    }
}

$schema = hg_chronicles_schema($link);
$excludedChronicleIds = hg_chronicles_normalize_ids($excludeChronicles ?? '');
$chronicleFilterId = (int)hg_request_param($hgRequest, 'chronicle');
$hasSeasonChronicleId = !empty($schema['season_chronicle']);

if ($chronicleFilterId <= 0) {
    setMetaFromPage("Crónicas | Heaven's Gate", "Crónicas del universo Heaven's Gate.", '/img/og/og_image_bio.webp', 'website');
    include("app/partials/main_nav_bar.php");

    $chronicles = hg_chronicles_fetch_catalog($link, $schema, $excludedChronicleIds) ?? [];

    echo "<div class='chron-detail'>";
    echo "  <section class='chron-box'>";
    echo "    <div class='chron-box-head'>";
    echo "      <h2>Crónicas</h2>";
    echo "      <p>Consulta aquí todas las crónicas (agrupación de temporadas) que han dado sentido a Heaven's Gate.</p>";
    echo "    </div>";

    if (count($chronicles) === 0) {
        echo "<p class='texti chron-empty'>No hay crónicas disponibles.</p>";
        echo "  </section>";
        echo "</div>";
        return;
    }

    echo "    <div class='chron-grid'>";
    foreach ($chronicles as $chronicle) {
        $cid = (int)($chronicle['id'] ?? 0);
        $pretty = (string)($chronicle['pretty_id'] ?? '');
        $name = (string)($chronicle['name'] ?? '');
        $desc = (string)($chronicle['description'] ?? '');
        $href = pretty_url($link, 'dim_chronicles', '/chronicles', $cid);
        $img = hg_ch_image_route($pretty, $cid);
        $descShort = hg_ch_excerpt($desc, 180);
        $seasonCount = (int)($chronicle['season_count'] ?? 0);
        $characterCount = (int)($chronicle['character_count'] ?? 0);
        $seasonLabel = hg_ch_count_label($seasonCount, 'temporada', 'temporadas');
        $characterLabel = hg_ch_count_label($characterCount, 'personaje', 'personajes');

        echo "<a class='chron-card' href='" . hg_ch_h($href) . "' title='" . hg_ch_h($name) . "'>";
        echo "  <img src='" . hg_ch_h($img) . "' alt='" . hg_ch_h($name) . "'>";
        echo "  <div class='chron-card-body'>";
        echo "    <h3>" . hg_ch_h($name) . "</h3>";
        echo "    <p>" . hg_ch_h($descShort !== '' ? $descShort : 'Sin descripción.') . "</p>";
        if ($seasonLabel !== '' || $characterLabel !== '') {
            echo "    <div class='chron-card-meta'>";
            if ($seasonLabel !== '') echo "      <span>" . hg_ch_h($seasonLabel) . "</span>";
            if ($characterLabel !== '') echo "      <span>" . hg_ch_h($characterLabel) . "</span>";
            echo "    </div>";
        }
        echo "  </div>";
        echo "</a>";
    }
    echo "    </div>";
    echo "  </section>";
    echo "</div>";
    return;
}

$chronicle = hg_chronicles_fetch_one($link, $schema, $chronicleFilterId);
if (!$chronicle) {
    setMetaFromPage("Crónica no encontrada | Heaven's Gate", "La crónica solicitada no existe.", '/img/og/og_image_bio.webp', 'article');
    include("app/partials/main_nav_bar.php");
    echo "<div class='chron-detail'><section class='chron-box'><h2>Crónica no encontrada</h2><p class='texti chron-empty'>La crónica solicitada no existe o no está disponible.</p></section></div>";
    return;
}

$chronicleId = (int)($chronicle['id'] ?? 0);
$chroniclePretty = (string)($chronicle['pretty_id'] ?? '');
$chronicleName = (string)($chronicle['name'] ?? '');
$chronicleDescription = (string)($chronicle['description'] ?? '');
$chronicleImageRoute = hg_ch_image_route($chroniclePretty, $chronicleId);
$pageTitle2 = $chronicleName;
setMetaFromPage($chronicleName . " | Crónicas | Heaven's Gate", meta_excerpt($chronicleDescription), $chronicleImageRoute, 'article');

include("app/partials/main_nav_bar.php");

$seasonRows = $hasSeasonChronicleId ? hg_chronicles_fetch_seasons($link, $chronicleId) : [];
$members = hg_chronicles_fetch_members($link, $chronicleId);

$seasonCount = count($seasonRows);
$characterCount = count($members);
$chronicleDescHtml = hg_ch_render_text($chronicleDescription);
$seasonLabel = hg_ch_count_label($seasonCount, 'temporada', 'temporadas');
$characterLabel = hg_ch_count_label($characterCount, 'personaje', 'personajes');
?>
<div class="chron-detail">
    <section class="chron-hero">
        <div class="chron-hero-media">
            <img src="<?= hg_ch_h($chronicleImageRoute) ?>" alt="<?= hg_ch_h($chronicleName) ?>">
            <div class="chron-hero-overlay">
                <p class="chron-kicker">Crónica</p>
                <h2><?= hg_ch_h($chronicleName) ?></h2>
                <?php if ($seasonLabel !== '' || $characterLabel !== ''): ?>
                <div class="chron-hero-meta">
                    <?php if ($seasonLabel !== ''): ?><span class="chron-hero-pill"><?= hg_ch_h($seasonLabel) ?></span><?php endif; ?>
                    <?php if ($characterLabel !== ''): ?><span class="chron-hero-pill"><?= hg_ch_h($characterLabel) ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="chron-box">
        <div class="chron-box-head">
            <h3>Descripci&oacute;n</h3>
        </div>
        <div class="chron-rich"><?= $chronicleDescHtml !== '' ? $chronicleDescHtml : 'Sin descripci&oacute;n.' ?></div>
    </section>

    <section class="chron-box">
        <div class="chron-box-head">
            <h3>Temporadas vinculadas</h3>
        </div>
        <?php if (!$hasSeasonChronicleId): ?>
            <p class="texti chron-empty">La asociaci&oacute;n entre cr&oacute;nicas y temporadas a&aacute;n no est&aacute; disponible en la base de datos.</p>
        <?php elseif (count($seasonRows) === 0): ?>
            <p class="texti chron-empty">No hay temporadas vinculadas a esta cr&oacute;nica.</p>
        <?php else: ?>
            <div class="chron-detail-seasons season-home-grid">
                <?php foreach ($seasonRows as $seasonRow): ?>
                    <?php
                        $seasonId = (int)($seasonRow['id'] ?? 0);
                        $seasonKind = trim((string)($seasonRow['season_kind'] ?? 'temporada'));
                        $seasonNumber = (int)($seasonRow['season_number'] ?? 0);
                        $seasonName = (string)($seasonRow['name'] ?? '');
                        $seasonDesc = (string)($seasonRow['description'] ?? '');
                        $seasonHref = pretty_url($link, 'dim_seasons', '/seasons', $seasonId);
                        $seasonBadge = hg_ch_kind_badge($seasonKind, $seasonNumber);
                        $seasonChapterCount = (int)($seasonRow['chapter_count'] ?? 0);
                        [$seasonStatusText, $seasonStatusClass] = hg_ch_status_meta((int)($seasonRow['finished'] ?? 0));
                        $seasonImage = hg_ch_season_image($seasonKind);
                    ?>
                    <a class="season-home-card" href="<?= hg_ch_h($seasonHref) ?>" title="<?= hg_ch_h($seasonName) ?>">
                        <div class="season-home-card-media">
                            <img src="<?= hg_ch_h($seasonImage) ?>" alt="<?= hg_ch_h($seasonName) ?>">
                            <div class="season-home-card-overlay">
                                <span class="season-home-card-kicker"><?= hg_ch_h($seasonBadge) ?></span>
                                <h3><?= hg_ch_h($seasonName) ?></h3>
                            </div>
                        </div>
                        <div class="season-home-card-body">
                            <p><?= hg_ch_h(hg_ch_excerpt($seasonDesc !== '' ? $seasonDesc : 'Sin descripcion.', 170)) ?></p>
                            <div class="season-home-card-summary">
                                <span class="season-home-count"><?= number_format($seasonChapterCount, 0, ',', '.') ?> capitulos</span>
                                <span class="season-home-status <?= hg_ch_h($seasonStatusClass) ?>"><?= hg_ch_h($seasonStatusText) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="chron-box">
        <div class="chron-box-head">
            <h3>Personajes asociados</h3>
        </div>
        <?php if (count($members) === 0): ?>
            <p class="texti chron-empty">No hay personajes asociados a esta cr&oacute;nica.</p>
        <?php else: ?>
            <table id="tabla-chronicle-members" class="display chron-members-table">
                <thead>
                    <tr>
                        <th>Personaje</th>
                        <th>Estado</th>
                        <th>Afiliaci&oacute;n</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $member): ?>
                        <?php
                            $memberId = (int)($member['id'] ?? 0);
                            $memberName = (string)($member['name'] ?? '');
                            $memberStatus = trim((string)($member['status'] ?? ''));
                            $memberAffiliation = hg_ch_affiliation_label((string)($member['organizations'] ?? ''), (string)($member['groups'] ?? ''));
                            $memberHref = pretty_url($link, 'fact_characters', '/characters', $memberId);
                        ?>
                        <tr>
                            <td><a href="<?= hg_ch_h($memberHref) ?>"><?= hg_ch_h($memberName) ?></a></td>
                            <td><?= hg_ch_h($memberStatus !== '' ? $memberStatus : '-') ?></td>
                            <td><?= hg_ch_h($memberAffiliation) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
</div>
<?php if (count($members) > 0): ?>
<?php include_once("app/partials/datatable_assets.php"); ?>
<script>
(function(){
  if (typeof jQuery === 'undefined' || !jQuery.fn || !jQuery.fn.DataTable) return;
  jQuery(function($){
    $('#tabla-chronicle-members').DataTable({
      pageLength: 10,
      lengthMenu: [10, 20, 50, 100],
      order: [[0, 'asc']],
      language: {
        search: 'Buscar:&nbsp; ',
        lengthMenu: 'Mostrar _MENU_ personajes',
        info: 'Mostrando _START_ a _END_ de _TOTAL_ personajes',
        infoEmpty: 'No hay personajes disponibles',
        emptyTable: 'No hay datos en la tabla',
        paginate: { first: 'Primero', last: 'Ultimo', next: '&#9654;', previous: '&#9664;' }
      }
    });
  });
})();
</script>
<?php endif; ?>