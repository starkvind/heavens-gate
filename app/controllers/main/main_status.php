<?php setMetaFromPage("Estado | Heaven's Gate", "Estado general de la campana y sus recursos.", null, 'website'); ?>
<?php

// Verificar si la conexión a la base de datos ($link) está definida y es válida
if (!$link) {
    die("Error de conexión a la base de datos: " . mysqli_connect_error());
}

// ORDEN GUAY
include("app/partials/main_nav_bar.php"); // Barra Navegación
echo "<h2>Estado</h2>";

// - Jugadores y Personajes
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Biografías</legend>";

// Consulta para Jugadores
$consulta = "SELECT COUNT(*) as total FROM dim_players";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    //echo "<div class='renglonStatusIz'>Jugadores:</div>";
    //echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Personajes
$consulta = "SELECT COUNT(*) as total FROM dim_character_types";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Personajes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Personajes
$consulta = "SELECT COUNT(*) as total FROM fact_characters";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Personajes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Personajes con ficha
$consulta = "SELECT COUNT(*) as total FROM fact_characters WHERE character_kind = 'pj'";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Personajes con ficha de personaje:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Relaciones de personajes
$consulta = "SELECT COUNT(*) as total FROM bridge_characters_relations";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Relaciones entre personajes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Total Clanes
$consulta = "SELECT COUNT(*) as total FROM dim_organizations";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Clanes u organizaciones:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Total Manadas
$consulta = "SELECT COUNT(*) as total FROM dim_groups";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Manadas o grupos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Luchadores para el Simulador
$consulta = "SELECT COUNT(*) as total FROM fact_characters WHERE character_kind = 'pj' AND player_id != 0";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    //echo "<div class='renglonStatusIz'>Luchadores para el Simulador:</div>";
    //echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Notas sobre personajes
$consulta = "SELECT COUNT(*) as total FROM fact_characters_comments";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    //echo "<div class='renglonStatusIz'>Notas sobre personajes:</div>";
    //echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Archivos - Temporadas y Capítulos
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Temporadas e Historias personales</legend>";

// Consulta para Campañas o Temporadas
$consulta = "SELECT COUNT(*) as total FROM dim_seasons";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Nº Temporadas e Historias personales:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Capítulos
$consulta = "SELECT COUNT(*) as total FROM dim_chapters";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Capítulos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

/* ------------------------------------------------------- */
// Habilidades y Ventajas
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Valores y reglas</legend>";

// Consulta para Categorías de Habilidades
$consulta = "SELECT COUNT(DISTINCT kind) as total FROM dim_traits";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Rasgos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Habilidades
$consulta = "SELECT COUNT(*) as total FROM dim_traits";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Rasgos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Méritos y Defectos
$consulta = "SELECT COUNT(*) as total FROM dim_merits_flaws";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>M&eacute;ritos y Defectos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Arquetipos de personalidad
$consulta = "SELECT COUNT(*) as total FROM dim_archetypes";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Arquetipos de personalidad:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Maniobras de combate
$consulta = "SELECT COUNT(*) as total FROM fact_combat_maneuvers";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Maniobras de combate:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Documentación e Inventario
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Inventario</legend>";

// Consulta para Categorías de Documentos
$consulta = "SELECT COUNT(*) as total FROM dim_doc_categories";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Documentos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Documentos
$consulta = "SELECT COUNT(*) as total FROM fact_docs";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Documentos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Objetos
$consulta = "SELECT COUNT(*) as total FROM dim_item_types";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Objetos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);


// Consulta para Objetos
$consulta = "SELECT COUNT(*) as total FROM fact_items";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Objetos:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Imágenes
// $consulta = "SELECT COUNT(*) as total FROM imagenes";
// $result = mysqli_query($link, $consulta);
// if ($result) {
//     $row = mysqli_fetch_assoc($result);
//     //echo "<div class='renglonStatusIz'>Im&aacute;genes:</div>";
//     //echo "<div class='renglonStatusDe'>{$row['total']}</div>";
// }
// mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

// Poderes Sobrenaturales
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Poderes</legend>";

// Consulta para Categorías de Dones
$consulta = "SELECT COUNT(DISTINCT kind) as total FROM fact_gifts";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Dones:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Dones
$consulta = "SELECT COUNT(*) as total FROM fact_gifts";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Dones:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Rituales
$consulta = "SELECT COUNT(*) as total FROM dim_rite_types";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Rituales:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Rituales
$consulta = "SELECT COUNT(*) as total FROM fact_rites";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Rituales:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Tótems
$consulta = "SELECT COUNT(DISTINCT totem_type_id) as total FROM dim_totems";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de T&oacute;tems:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);


// Consulta para Tótems
$consulta = "SELECT COUNT(*) as total FROM dim_totems";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>T&oacute;tems:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Categorías de Disciplinas
$consulta = "SELECT COUNT(*) as total FROM dim_discipline_types";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Categor&iacute;as de Disciplinas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Disciplinas
$consulta = "SELECT COUNT(*) as total FROM fact_discipline_powers";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Disciplinas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

echo "</fieldset>";
echo "<br/>";

/*
// Sistemas de Juego - Razas Cambiantes
echo "<fieldset class='renglonPaginaDon'>";
echo "<legend>Sistemas de Juego</legend>";

// Consulta para Sistemas
$consulta = "SELECT COUNT(*) as total FROM dim_systems";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Sistemas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Razas
$consulta = "SELECT COUNT(*) as total FROM dim_breeds";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Razas:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Auspicios y Roles
$consulta = "SELECT COUNT(*) as total FROM dim_auspices";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Auspicios y Roles:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Tribus y Clanes
$consulta = "SELECT COUNT(*) as total FROM dim_tribes";
$result = mysqli_query($link, $consulta);
if ($result) {
    $row = mysqli_fetch_assoc($result);
    echo "<div class='renglonStatusIz'>Tribus y Clanes:</div>";
    echo "<div class='renglonStatusDe'>{$row['total']}</div>";
}
mysqli_free_result($result);

// Consulta para Otros datos misceláneos
$consulta = "SELECT COUNT(*) as total FROM fact_misc_systems";
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
*/
?>
