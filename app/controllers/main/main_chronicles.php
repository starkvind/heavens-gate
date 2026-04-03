<?php
echo '<link rel="stylesheet" href="/assets/css/hg-main.css">';
include_once(__DIR__ . '/../../helpers/public_response.php');

if (!$link) {
    hg_public_log_error('main_chronicles', 'missing DB connection');
    hg_public_render_error('Crónicas no disponibles', 'No se pudieron cargar las crónicas en este momento.');
    return;
}

if (!function_exists('hg_ch_h')) {
    function hg_ch_h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('hg_ch_sanitize_int_csv')) {
    function hg_ch_sanitize_int_csv($csv){
        $csv = (string)$csv;
        if (trim($csv) === '') return '';
        $parts = preg_split('/\s*,\s*/', trim($csv));
        $ints = [];
        foreach ($parts as $p) {
            if ($p === '') continue;
            if (preg_match('/^\d+$/', $p)) $ints[] = (string)(int)$p;
        }
        $ints = array_values(array_unique($ints));
        return implode(',', $ints);
    }
}
if (!function_exists('hg_ch_has_column')) {
    function hg_ch_has_column(mysqli $link, string $table, string $column): bool {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
        if ($table === '' || $column === '') return false;
        $rs = mysqli_query($link, "SHOW COLUMNS FROM `$table` LIKE '$column'");
        if (!$rs) return false;
        $ok = (mysqli_num_rows($rs) > 0);
        mysqli_free_result($rs);
        return $ok;
    }
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
if (!function_exists('hg_ch_normalize_public_path')) {
    function hg_ch_normalize_public_path(string $path): string {
        $path = trim($path);
        if ($path === '') return '';
        if (preg_match('#^https?://#i', $path)) return $path;
        $path = str_replace('\\', '/', $path);
        $path = preg_replace('#^/?public/#i', '', $path);
        $path = preg_replace('#/+#', '/', $path);
        return '/' . ltrim($path, '/');
    }
}
if (!function_exists('hg_ch_default_image')) {
    function hg_ch_default_image(string $prettyId = ''): string {
        static $map = [
            'heavens-gate' => '/img/og/og_image_bio.jpg',
            'javi' => '/img/og/og_image.jpg',
            'werewolf-gt' => '/img/og/og_image_temp.jpg',
            'hg-tercer-ojo' => '/img/og/og_image_power.jpg',
            'hg-babylon' => '/img/og/og_image_monster.jpg',
            'hg-london' => '/img/og/og_image_temp.jpg',
            'cenizas' => '/img/og/og_image_power.jpg',
        ];
        if ($prettyId !== '' && isset($map[$prettyId])) return $map[$prettyId];
        return '/img/og/og_image_bio.jpg';
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
        if ($kind === 'inciso') return '/img/og/og_image.jpg';
        if ($kind === 'historia_personal') return '/img/og/og_image_bio.jpg';
        if ($kind === 'especial') return '/img/og/og_image_power.jpg';
        return '/img/og/og_image_temp.jpg';
    }
}
if (!function_exists('hg_ch_count_label')) {
    function hg_ch_count_label(int $count, string $singular, string $plural): string {
        if ($count <= 0) return '';
        return number_format($count, 0, ',', '.') . ' ' . ($count === 1 ? $singular : $plural);
    }
}

$excludeChronicles = isset($excludeChronicles) ? hg_ch_sanitize_int_csv($excludeChronicles) : '';
$chronicleFilterId = isset($_GET['t']) ? (int)$_GET['t'] : 0;
$hasSeasonChronicleId = hg_ch_has_column($link, 'dim_seasons', 'chronicle_id');
$hasChronicleImage = hg_ch_has_column($link, 'dim_chronicles', 'image_url');
$statusExpr = "COALESCE(dcs.label, '')";

if ($chronicleFilterId <= 0) {
    setMetaFromPage("Crónicas | Heaven's Gate", "Crónicas del universo Heaven's Gate.", '/img/og/og_image_bio.jpg', 'website');
    include("app/partials/main_nav_bar.php");

    $selectImage = $hasChronicleImage ? "COALESCE(ch.image_url, '') AS image_url," : "'' AS image_url,";
    $joinSeasons = $hasSeasonChronicleId ? "LEFT JOIN dim_seasons s ON s.chronicle_id = ch.id" : "";
    $seasonCountSql = $hasSeasonChronicleId ? "COUNT(DISTINCT s.id) AS season_count," : "0 AS season_count,";

    $chronicles = [];
    $sqlChron = "
        SELECT
            ch.id,
            ch.pretty_id,
            ch.name,
            ch.description,
            IFNULL(ch.sort_order, 999999) AS sort_order,
            $selectImage
            $seasonCountSql
            COUNT(DISTINCT fc.id) AS character_count
        FROM dim_chronicles ch
        LEFT JOIN fact_characters fc ON fc.chronicle_id = ch.id
        $joinSeasons
        WHERE 1=1
        " . (($excludeChronicles !== '') ? " AND ch.id NOT IN ($excludeChronicles) " : "") . "
        GROUP BY ch.id, ch.pretty_id, ch.name, ch.description, ch.sort_order" . ($hasChronicleImage ? ", ch.image_url" : "") . "
        ORDER BY sort_order ASC, ch.name ASC
    ";
    if ($rsChron = mysqli_query($link, $sqlChron)) {
        while ($r = mysqli_fetch_assoc($rsChron)) { $chronicles[] = $r; }
        mysqli_free_result($rsChron);
    }

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

$chronicleSelectImage = $hasChronicleImage ? ", COALESCE(image_url, '') AS image_url" : ", '' AS image_url";
$chronicle = null;
if ($stmtChron = $link->prepare("SELECT id, pretty_id, name, description $chronicleSelectImage FROM dim_chronicles WHERE id = ? LIMIT 1")) {
    $stmtChron->bind_param('i', $chronicleFilterId);
    $stmtChron->execute();
    $rsChron = $stmtChron->get_result();
    if ($rsChron) $chronicle = $rsChron->fetch_assoc();
    $stmtChron->close();
}

if (!$chronicle) {
    setMetaFromPage("Crónica no encontrada | Heaven's Gate", "La crónica solicitada no existe.", '/img/og/og_image_bio.jpg', 'article');
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

$seasonRows = [];
if ($hasSeasonChronicleId && ($stmtSeason = $link->prepare("
    SELECT
        s.id,
        s.name,
        s.pretty_id,
        s.description,
        s.season_number,
        COALESCE(s.season_kind, 'temporada') AS season_kind,
        COALESCE(s.finished, 0) AS finished,
        COALESCE(s.sort_order, 999999) AS sort_order,
        COUNT(c.id) AS chapter_count
    FROM dim_seasons s
    LEFT JOIN dim_chapters c ON c.season_id = s.id
    WHERE s.chronicle_id = ?
    GROUP BY
        s.id, s.name, s.pretty_id, s.description, s.season_number,
        s.season_kind, s.finished, s.sort_order
    ORDER BY
        CASE
            WHEN COALESCE(s.season_kind, 'temporada') = 'temporada' THEN 1
            WHEN COALESCE(s.season_kind, 'temporada') = 'inciso' THEN 2
            WHEN COALESCE(s.season_kind, 'temporada') = 'historia_personal' THEN 3
            WHEN COALESCE(s.season_kind, 'temporada') = 'especial' THEN 4
            ELSE 99
        END ASC,
        COALESCE(s.sort_order, 999999) ASC,
        s.season_number ASC,
        s.name ASC
"))) {
    $stmtSeason->bind_param('i', $chronicleId);
    $stmtSeason->execute();
    $rsSeason = $stmtSeason->get_result();
    while ($rsSeason && ($rowSeason = $rsSeason->fetch_assoc())) {
        $seasonRows[] = $rowSeason;
    }
    $stmtSeason->close();
}

$members = [];
if ($stmtChars = $link->prepare("
    SELECT
        p.id,
        p.name,
        {$statusExpr} AS status,
        GROUP_CONCAT(DISTINCT o.name ORDER BY o.name SEPARATOR ', ') AS organizations,
        GROUP_CONCAT(DISTINCT g.name ORDER BY g.name SEPARATOR ', ') AS groups
    FROM fact_characters p
    LEFT JOIN dim_character_status dcs ON dcs.id = p.status_id
    LEFT JOIN bridge_characters_organizations bco
        ON bco.character_id = p.id
       AND (bco.is_active = 1 OR bco.is_active IS NULL)
    LEFT JOIN dim_organizations o ON o.id = bco.organization_id
    LEFT JOIN bridge_characters_groups bcg
        ON bcg.character_id = p.id
       AND (bcg.is_active = 1 OR bcg.is_active IS NULL)
    LEFT JOIN dim_groups g ON g.id = bcg.group_id
    WHERE p.chronicle_id = ?
    GROUP BY p.id, p.name, {$statusExpr}
    ORDER BY p.name ASC
")) {
    $stmtChars->bind_param('i', $chronicleId);
    $stmtChars->execute();
    $rsChars = $stmtChars->get_result();
    while ($rsChars && ($rowChar = $rsChars->fetch_assoc())) {
        $members[] = $rowChar;
    }
    $stmtChars->close();
}

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
