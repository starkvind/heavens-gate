<?php
setMetaFromPage("Tiradados | Heaven's Gate", "Resultado de tiradas de dados.", null, 'website');
include("../app/helpers/heroes.php");

$nombre_jugador = trim($_POST['nombre'] ?? '');
$tirada_nombre = trim($_POST['tirada_nombre'] ?? '');
$dados = (int)($_POST['dados'] ?? 0);
$dificultad = (int)($_POST['dificultad'] ?? 0);
$ip = $_SERVER['REMOTE_ADDR'];

// Validación básica
if ($nombre_jugador === '' || $tirada_nombre === '' || $dados < 1 || $dados > 15 || $dificultad < 2 || $dificultad > 10) {
    die("Parámetros inválidos.");
}

// Anti-spam
$query = "SELECT timestamp FROM fact_dice_rolls WHERE ip = ? ORDER BY timestamp DESC LIMIT 1";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "s", $ip);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($res)) {
    if (strtotime($row['timestamp']) > time() - 10) {
        die("Has tirado hace menos de 10 segundos.");
    }
}

// Comprobar que la roll_name sea única
$query = "SELECT COUNT(*) as total FROM fact_dice_rolls WHERE roll_name = ?";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "s", $tirada_nombre);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
if ($row['total'] > 0) {
    die("Ese nombre de tirada ya existe. Usa uno diferente.");
}

// Realizar la tirada
$resultados = [];
$exitos = 0;
$uno_detectado = false;

for ($i = 0; $i < $dados; $i++) {
    $dado = rand(1, 10);
    $resultados[] = $dado;
    if ($dado >= $dificultad) $exitos++;
    if ($dado == 1 && !$uno_detectado) $uno_detectado = true;
}

if ($uno_detectado) {
    $exitos--;
    if ($exitos < 0) $exitos = 0;
}
$pifia = ($uno_detectado && $exitos === 0);

$str_resultados = implode(",", $resultados);

// Insertar la tirada
$query = "INSERT INTO fact_dice_rolls 
(name, roll_name, dados, dificultad, resultados, exitos, pifia, ip) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = mysqli_prepare($link, $query);
mysqli_stmt_bind_param($stmt, "isiiisis", $nombre_jugador, $tirada_nombre, $dados, $dificultad, $str_resultados, $exitos, $pifia, $ip);
mysqli_stmt_execute($stmt);

// Mostrar resultado
$link_roll = "https://tusitio.com/roll.php?codigo=" . urlencode($tirada_nombre);

echo "<h2>Tirada registrada</h2>";
echo "<p><strong>$nombre_jugador</strong> lanzó <strong>$dados d10</strong> a dificultad <strong>$dificultad</strong>.</p>";
echo "<p>Resultados: " . htmlspecialchars($str_resultados) . "</p>";
echo "<p>Éxitos netos: <strong>$exitos</strong></p>";
if ($pifia) echo "<p style='color:red;'>¡PIFIA!</p>";
echo "<p>Enlace para foro: <code>[roll]$link_roll[/roll]</code></p>";
?>




