<?php

// Verificar si la conexión a la base de datos ($link) está definida y es válida
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// ORDEN GUAY
include("sep/main/main_nav_bar.php"); // Barra Navegación
echo "<h2>Estado</h2>";

// - Jugadores y Personajes
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Jugadores y Personajes</legend>";

// Consulta para Jugadores
$consulta = "SELECT COUNT(*) as total FROM nuevo_jugadores";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Jugadores:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Personajes
$consulta = "SELECT COUNT(*) as total FROM pjs1";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Personajes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Personajes
$consulta = "SELECT COUNT(*) as total FROM afiliacion";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Personajes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Personajes con ficha
$consulta = "SELECT COUNT(*) as total FROM pjs1 WHERE kes = 'pj'";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Personajes con ficha:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Luchadores para el Simulador
$consulta = "SELECT COUNT(*) as total FROM pjs1 WHERE kes = 'pj' AND jugador != 0";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Luchadores para el Simulador:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Notas sobre personajes
$consulta = "SELECT COUNT(*) as total FROM koment";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Notas sobre personajes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Archivos - Temporadas y Capítulos
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Sesiones en Archivo</legend>";

// Consulta para Campañas o Temporadas
$consulta = "SELECT COUNT(*) as total FROM archivos_temporadas";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Campañas o Temporadas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Capítulos
$consulta = "SELECT COUNT(*) as total FROM archivos_capitulos";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Capítulos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Documentación e Inventario
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Documentaci&oacute;n e Inventario</legend>";

// Consulta para Documentos
$consulta = "SELECT COUNT(*) as total FROM docz";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Documentos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Documentos
$consulta = "SELECT COUNT(*) as total FROM documentacion";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Documentos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Objetos
$consulta = "SELECT COUNT(*) as total FROM nuevo3_objetos";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Objetos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Objetos
$consulta = "SELECT COUNT(*) as total FROM nuevo3_tipo_objetos";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Objetos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Imágenes
$consulta = "SELECT COUNT(*) as total FROM imagenes";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Im&aacute;genes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Habilidades y Ventajas
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Habilidades y Ventajas</legend>";

// Consulta para Habilidades
$consulta = "SELECT COUNT(*) as total FROM nuevo_habilidades";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Habilidades:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Habilidades
$consulta = "SELECT COUNT(DISTINCT tipo) as total FROM nuevo_habilidades";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Habilidades:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Méritos y Defectos
$consulta = "SELECT COUNT(*) as total FROM nuevo_mer_y_def";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>M&eacute;ritos y Defectos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Maniobras de combate
$consulta = "SELECT COUNT(*) as total FROM nuevo2_maniobras";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Maniobras de combate:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Arquetipos de personalidad
$consulta = "SELECT COUNT(*) as total FROM nuevo_personalidad";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Arquetipos de personalidad:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Poderes Sobrenaturales
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Poderes Sobrenaturales</legend>";

// Consulta para Dones
$consulta = "SELECT COUNT(*) as total FROM dones";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Dones:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Dones
$consulta = "SELECT COUNT(DISTINCT tipo) as total FROM dones";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Dones:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Rituales
$consulta = "SELECT COUNT(*) as total FROM nuevo_rituales";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Rituales:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Rituales
$consulta = "SELECT COUNT(*) as total FROM nuevo2_tipo_rituales";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Rituales:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Tótems
$consulta = "SELECT COUNT(*) as total FROM nuevo_totems";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>T&oacute;tems:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Tótems
$consulta = "SELECT COUNT(DISTINCT tipo) as total FROM nuevo_totems";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de T&oacute;tems:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Disciplinas
$consulta = "SELECT COUNT(*) as total FROM nuevo_disciplinas";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Disciplinas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Disciplinas
$consulta = "SELECT COUNT(*) as total FROM nuevo2_tipo_disciplinas";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Disciplinas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Sistemas de Juego - Razas Cambiantes
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Sistemas de Juego</legend>";

// Consulta para Sistemas
$consulta = "SELECT COUNT(*) as total FROM nuevo_sistema";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Sistemas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Razas
$consulta = "SELECT COUNT(*) as total FROM nuevo_razas";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Razas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Auspicios y Roles
$consulta = "SELECT COUNT(*) as total FROM nuevo_auspicios";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Auspicios y Roles:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Tribus y Clanes
$consulta = "SELECT COUNT(*) as total FROM nuevo_tribus";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Tribus y Clanes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Otros datos misceláneos
$consulta = "SELECT COUNT(*) as total FROM nuevo_miscsistemas";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Otros datos miscel&aacute;neos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Datos de las Herramientas
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Herramientas de la Web</legend>";

// Consulta para Tiradas de Dados
$consulta = "SELECT COUNT(*) as total FROM tiradax";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Tiradas de Dados:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Combates en el Simulador
$consulta = "SELECT COUNT(*) as total FROM ultimoscombates";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Combates en el Simulador:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";
?>
