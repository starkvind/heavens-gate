<?php
require_once __DIR__ . '/../../helpers/sim_legacy_bootstrap.php';

$simRouteMap = [
    'combat_simulator' => 'simulator_page.php',
    'combat_simulator_result' => 'battle_result_page.php',
    'combat_simulator_logs' => 'battle_log_list_page.php',
    'combat_simulator_log' => 'battle_log_detail_page.php',
    'combat_simulator_scores' => 'scoreboard_page.php',
    'combat_simulator_weapons' => 'weapon_usage_page.php',
    'combat_simulator_tournament' => 'tournament_page.php',

    // Legacy aliases kept for backward compatibility
    'simulador' => 'simulator_page.php',
    'simulador2' => 'battle_result_page.php',
    'combtodo' => 'battle_log_list_page.php',
    'vercombat' => 'battle_log_detail_page.php',
    'punts' => 'scoreboard_page.php',
    'arms' => 'weapon_usage_page.php',
    'sim_tournament' => 'tournament_page.php',
];

$simTarget = $simRouteMap[$routeKey] ?? 'simulator_page.php';
$simMetaSuffix = " | Simulador de Combate | Heaven's Gate";
$metaTitle = "Simulador de Combate | Heaven's Gate";
$metaDesc = "Configura y ejecuta combates 1 vs 1 entre personajes del universo Heaven's Gate.";

switch ($simTarget) {
    case 'battle_result_page.php':
        $metaTitle = "Resultado del combate" . $simMetaSuffix;
        $metaDesc = "Detalle completo de la simulacion: turnos, dano, eventos y ganador.";
        break;

    case 'battle_log_list_page.php':
        $metaTitle = "Registro de combates" . $simMetaSuffix;
        $metaDesc = "Historial de combates del simulador con filtros de temporada y acceso al detalle.";
        break;

    case 'battle_log_detail_page.php':
        $metaTitle = "Detalle de combate" . $simMetaSuffix;
        $metaDesc = "Consulta el desarrollo completo de un combate del simulador turno a turno.";
        $battleId = isset($_GET['b']) ? (int)$_GET['b'] : 0;
        if ($battleId > 0 && !empty($link)) {
            $battleQuery = "SELECT id,"
                . " COALESCE(NULLIF(fighter_one_alias_snapshot, ''), NULLIF(fighter_one_name_snapshot, ''), 'P1') AS p1,"
                . " COALESCE(NULLIF(fighter_two_alias_snapshot, ''), NULLIF(fighter_two_name_snapshot, ''), 'P2') AS p2"
                . " FROM fact_sim_battles WHERE id = $battleId LIMIT 1";
            $battleRs = @mysql_query($battleQuery, $link);
            if ($battleRs && mysql_num_rows($battleRs) > 0) {
                $battleRow = mysql_fetch_array($battleRs);
                $p1 = trim(preg_replace('/\s+/', ' ', (string)($battleRow['p1'] ?? 'P1')));
                $p2 = trim(preg_replace('/\s+/', ' ', (string)($battleRow['p2'] ?? 'P2')));
                $metaTitle = "Combate #{$battleId} - {$p1} vs {$p2}" . $simMetaSuffix;
                $metaDesc = "Resultado detallado del combate #{$battleId} entre {$p1} y {$p2}.";
            }
            if ($battleRs) {
                @mysql_free_result($battleRs);
            }
        }
        break;

    case 'scoreboard_page.php':
        $metaTitle = "Puntuaciones" . $simMetaSuffix;
        $metaDesc = "Clasificacion de personajes y estadisticas de rendimiento en el simulador de combate.";
        break;

    case 'weapon_usage_page.php':
        $metaTitle = "Armas utilizadas" . $simMetaSuffix;
        $metaDesc = "Estadisticas de uso de armas y equipamiento en combates simulados.";
        break;

    case 'tournament_page.php':
        $metaTitle = "Torneos" . $simMetaSuffix;
        $metaDesc = "Visualiza y administra torneos del simulador en formato bracket.";
        $tournamentId = isset($_GET['tid']) ? (int)$_GET['tid'] : 0;
        if (!empty($link)) {
            $tournamentQuery = ($tournamentId > 0)
                ? "SELECT COALESCE(name, '') AS name FROM fact_sim_tournaments WHERE id = $tournamentId LIMIT 1"
                : "SELECT COALESCE(name, '') AS name FROM fact_sim_tournaments ORDER BY id DESC LIMIT 1";
            $tournamentRs = @mysql_query($tournamentQuery, $link);
            if ($tournamentRs && mysql_num_rows($tournamentRs) > 0) {
                $tournamentRow = mysql_fetch_array($tournamentRs);
                $tournamentName = trim((string)($tournamentRow['name'] ?? ''));
                if ($tournamentName !== '') {
                    $metaTitle = $tournamentName . $simMetaSuffix;
                    $metaDesc = "Seguimiento del torneo '" . $tournamentName . "' en el simulador de combate.";
                } elseif ($tournamentId > 0) {
                    $metaTitle = "Torneo #{$tournamentId}" . $simMetaSuffix;
                }
            }
            if ($tournamentRs) {
                @mysql_free_result($tournamentRs);
            }
        }
        break;

    case 'simulator_page.php':
    default:
        $metaTitle = "Simulador de Combate | Heaven's Gate";
        $metaDesc = "Configura y ejecuta combates 1 vs 1 entre personajes del universo Heaven's Gate.";
        break;
}

setMetaFromPage($metaTitle, $metaDesc, null, 'website');

$simFile = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'tool' . DIRECTORY_SEPARATOR . 'combat_simulator' . DIRECTORY_SEPARATOR . $simTarget;

if (!is_file($simFile)) {
    http_response_code(500);
    echo "<p>No se encuentra el archivo del simulador de combate: " . htmlspecialchars($simTarget, ENT_QUOTES, 'UTF-8') . ".</p>";
    return;
}

echo '<link rel="stylesheet" href="/assets/css/combat-simulator.css">';
$simPageClass = preg_replace('/[^a-z0-9\-]+/i', '-', (string)$routeKey);
if ($simPageClass === '' || $simPageClass === '-') {
    $simPageClass = 'combat-simulator';
}
echo '<section class="hg-sim-shell sim-' . htmlspecialchars($simPageClass, ENT_QUOTES, 'UTF-8') . '">';
include $simFile;
echo '</section>';
