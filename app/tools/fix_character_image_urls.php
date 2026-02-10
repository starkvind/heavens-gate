<?php
// Report and optionally fix broken character image URLs (fact_characters.img).
// Usage:
//   /sep/tools/fix_character_image_urls.php         -> dry run (report only)
//   /sep/tools/fix_character_image_urls.php?apply=1 -> apply fixes
//   /sep/tools/fix_character_image_urls.php?limit=200&offset=0

require_once __DIR__ . "/../helpers/heroes.php";

if (!$link) {
    die("DB connection error: " . mysqli_connect_error());
}

$apply  = isset($_GET['apply']) && $_GET['apply'] === '1';
$limit  = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 5000;
$offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

$root = realpath(__DIR__ . "/../.."); // project root
if ($root === false) {
    die("Cannot resolve project root.");
}

function normalize_url_path($url) {
    $url = trim((string)$url);
    if ($url === '') return '';
    // Drop query/fragment
    $url = preg_replace('/[?#].*$/', '', $url);
    // Strip scheme+host if present
    $url = preg_replace('#^https?://[^/]+/#i', '', $url);
    // Remove leading slash
    $url = ltrim($url, '/');
    return $url;
}

function file_exists_any($root, $path) {
    $candidates = [];
    if ($path !== '') {
        $candidates[] = $root . "/" . $path;
        $candidates[] = $root . "/public/" . $path;
    }
    foreach ($candidates as $p) {
        if (is_file($p)) return true;
    }
    return false;
}

function propose_fix($root, $path) {
    if ($path === '') return '';
    $path = normalize_url_path($path);

    // If path starts with "public/", drop it (web path should not include it)
    if (strpos($path, 'public/') === 0) {
        $candidate = substr($path, strlen('public/'));
        if (file_exists_any($root, $candidate)) return $candidate;
    }

    // If already exists as-is (in root or /public), keep it
    if (file_exists_any($root, $path)) return $path;

    return '';
}

$sql = "SELECT id, nombre, img FROM fact_characters ORDER BY id ASC LIMIT ? OFFSET ?";
$stmt = $link->prepare($sql);
$stmt->bind_param('ii', $limit, $offset);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();

$total = count($rows);
$broken = 0;
$fixed = 0;

echo "<h2>Fix character image URLs</h2>";
echo "<p>Mode: " . ($apply ? "APPLY" : "DRY RUN") . "</p>";
echo "<p>Root: " . htmlspecialchars($root, ENT_QUOTES, 'UTF-8') . "</p>";
echo "<p>Limit: $limit | Offset: $offset</p>";

echo "<table border='1' cellpadding='4' cellspacing='0' style='border-collapse:collapse; width:100%; font-family:Verdana; font-size:12px;'>";
echo "<tr><th>ID</th><th>Name</th><th>Current</th><th>Status</th><th>Proposed</th></tr>";

foreach ($rows as $r) {
    $id = (int)$r['id'];
    $name = (string)$r['nombre'];
    $img = (string)($r['img'] ?? '');

    $norm = normalize_url_path($img);
    $exists = ($norm !== '') && file_exists_any($root, $norm);
    $proposed = $exists ? $norm : propose_fix($root, $img);

    $status = $exists ? "OK" : "BROKEN";
    if (!$exists) $broken++;

    if ($apply && !$exists && $proposed !== '') {
        $up = $link->prepare("UPDATE fact_characters SET img=? WHERE id=?");
        $up->bind_param('si', $proposed, $id);
        if ($up->execute()) {
            $status = "FIXED";
            $fixed++;
        } else {
            $status = "ERROR";
        }
        $up->close();
    }

    echo "<tr>";
    echo "<td>$id</td>";
    echo "<td>" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($img, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>$status</td>";
    echo "<td>" . htmlspecialchars($proposed, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "</tr>";
}

echo "</table>";
echo "<p>Total: $total | Broken: $broken | Fixed: $fixed</p>";
?>
