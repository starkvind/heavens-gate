<?php 
	include("sep/heroes.php"); // Archivo de la base de datos
?>
<div class="main-menu flex-column" style="margin-top:6.5em;">

	<!-- UTILIDADES -->
	<div class="menu-section" style="margin-top:10px;">
		<a href="javascript:MostrarOcultar('toolsMenu');" id="menu7" 
		   onmouseover="Permut(1,'IMG8');" onmouseout="Permut(0,'IMG8');">
			<img src="img/menu/tools_icon.png" class="menuIcon" name="IMG8" 
				 onload="preloadPermut(this,'img/menu/tools_icon_hover.png');" style="float:left;margin-right:7px;">
		</a>
	</div>
	<div class="menu-subsection">
		<div class="ocultable" id="toolsMenu">
			<a href="?p=news"><div class="renglonMenu">Noticias</div></a>
			<a href="?p=busq"><div class="renglonMenu">Buscar</div></a>
			<a href="?p=about"><div class="renglonMenu">Acerca de...</div></a>
			<a href="?p=csp"><div class="renglonMenu">Tablón de Mensajes</div></a>
			<br><br>
		</div>
	</div>

	<!-- ARCHIVO -->
	<div class="menu-section">
		<a href="javascript:MostrarOcultar('archivoMenu');" id="menu1"
		   onmouseover="Permut(1,'IMG2');" onmouseout="Permut(0,'IMG2');">
			<img src="img/menu/archive_icon.png" class="menuIcon" name="IMG2"
				 onload="preloadPermut(this,'img/menu/archive_icon_hover.png');" style="float:left;margin-right:7px;">
		</a>
	</div>
	<div class="menu-subsection">
		<div class="ocultable" id="archivoMenu">
			<?php
				$consulta = "SELECT id, name, numero FROM archivos_temporadas WHERE season LIKE '0' ORDER BY numero";
				$stmt = mysqli_prepare($link, $consulta);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				while ($ResultQuery = mysqli_fetch_assoc($result)) {
					$idTemporada = htmlspecialchars($ResultQuery["id"]);
					$numeroTemp = htmlspecialchars($ResultQuery["numero"]);
					$nombreTemporada = $numeroTemp . "ª Temporada";
					if ($numeroTemp < 101) { 
						$nombreTemporada = $numeroTemp . "ª Temporada";
					} elseif ($numeroTemp == 999) {
						$nombreTemporada = htmlspecialchars($ResultQuery["name"]);
					} else {
						$numeroTemp -= 100;
						$nombreTemporada = "Inciso " . $numeroTemp . "º";
					}
					echo "<a href='?p=temp&amp;t=$idTemporada'><div class='renglonMenu'>$nombreTemporada</div></a>";
				}
				mysqli_stmt_close($stmt);
			?>
		</div>
	</div>

	<!-- HISTORIAS PERSONALES -->
	<div class="menu-section">
		<a href="javascript:MostrarOcultar('personalesMenu');" id="menu2"
		   onmouseover="Permut(1,'IMG3');" onmouseout="Permut(0,'IMG3');">
			<img src="img/menu/persona_icon.png" class="menuIcon" name="IMG3"
				 onload="preloadPermut(this,'img/menu/persona_icon_hover.png');" style="float:left;margin-right:7px;">
		</a>
	</div>
	<div class="menu-subsection">
		<div class="ocultable" id="personalesMenu">
			<?php
				$consulta = "SELECT id, name FROM archivos_temporadas WHERE season LIKE '1' ORDER BY numero";
				$stmt = mysqli_prepare($link, $consulta);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				while ($ResultQuery = mysqli_fetch_assoc($result)) {
					$idHistoria = htmlspecialchars($ResultQuery["id"]);
					$nombreHistoria = htmlspecialchars($ResultQuery["name"]);
					echo "<a href='?p=temp&amp;t=$idHistoria'><div class='renglonMenu'>$nombreHistoria</div></a>";
				}
				mysqli_stmt_close($stmt);
			?>
		</div>
	</div>

	<!-- BIOGRAFIAS -->
	<div class="menu-section">
		<a href="javascript:MostrarOcultar('bioMenu');" id="menu3"
		   onmouseover="Permut(1,'IMG4');" onmouseout="Permut(0,'IMG4');">
			<img src="img/menu/bio_icon.png" class="menuIcon" name="IMG4"
				 onload="preloadPermut(this,'img/menu/bio_icon_hover.png');" style="float:left;margin-right:7px;">
		</a>
	</div>
	<div class="menu-subsection">
		<div class="ocultable" id="bioMenu">
			<?php
				$consulta = "SELECT * FROM afiliacion ORDER BY orden";
				$stmt = mysqli_prepare($link, $consulta);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				while ($ResultQuery = mysqli_fetch_assoc($result)) {
					$nombreAfiliacion = htmlspecialchars($ResultQuery["tipo"]);
					$idAfiliacion = htmlspecialchars($ResultQuery["id"]);
					echo "<a href='?p=bios&amp;t=$idAfiliacion'><div class='renglonMenu'>$nombreAfiliacion</div></a>";
				}
				echo "<a href='?p=listgroups'><div class='renglonMenu'>Grupos y sociedades</div></a>";
				echo "<a href='?p=list_by_order'><div class='renglonMenu'>Listas organizadas</div></a>";
				echo "<a href='reltree/' target='_blank'><div class='renglonMenu'>Nebulosa relaciones</div></a>";
				mysqli_stmt_close($stmt);
			?>
		</div>
	</div>

	<!-- INVENTARIO -->
	<div class="menu-section">
		<a href="javascript:MostrarOcultar('inventoryMenu');" id="menu5"
		   onmouseover="Permut(1,'IMG6');" onmouseout="Permut(0,'IMG6');">
			<img src="img/menu/inventory_icon.png" class="menuIcon" name="IMG6"
				 onload="preloadPermut(this,'img/menu/inventory_icon_hover.png');" style="float:left;margin-right:7px;">
		</a>
	</div>
	<div class="menu-subsection">
		<div class="ocultable" id="inventoryMenu">
			<?php
				$consulta ="SELECT id, name FROM nuevo3_tipo_objetos ORDER BY orden";
				$stmt = mysqli_prepare($link, $consulta);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				while ($ResultQuery = mysqli_fetch_assoc($result)) {
					$idObj = htmlspecialchars($ResultQuery["id"]);
					$tipoObj = $ResultQuery["name"];
					echo "<a href='?p=inv&amp;t=$idObj'><div class='renglonMenu'>$tipoObj</div></a>";
				}
				mysqli_stmt_close($stmt);
			?>
		</div>
	</div>

	<!-- HABILIDADES -->
	<div class="menu-section">
		<a href="javascript:MostrarOcultar('skillMenu');" id="menu10"
		   onmouseover="Permut(1,'IMG10');" onmouseout="Permut(0,'IMG10');">
			<img src="img/menu/skill_icon.png" class="menuIcon" name="IMG10"
				 onload="preloadPermut(this,'img/menu/skill_icon_hover.png');" style="float:left;margin-right:7px;">
		</a>
	</div>
	<div class="menu-subsection">
		<div class="ocultable" id="skillMenu">
			<?php
				$idLink = 0;
				$consulta ="SELECT DISTINCT tipo FROM nuevo_habilidades ORDER BY id";
				$stmt = mysqli_prepare($link, $consulta);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				while ($ResultQuery = mysqli_fetch_assoc($result)) {
					$tipoSkill = $ResultQuery["tipo"];
					$idLink++;
					echo "<a href='?p=listsk&amp;b=$idLink'><div class='renglonMenu'>$tipoSkill</div></a>";
				}
				$idLink = 0;
				$consulta ="SELECT DISTINCT tipo FROM nuevo_mer_y_def ORDER BY id";
				$stmt = mysqli_prepare($link, $consulta);
				mysqli_stmt_execute($stmt);
				$result = mysqli_stmt_get_result($stmt);
				while ($ResultQuery = mysqli_fetch_assoc($result)) {
					$tipoMaf = $ResultQuery["tipo"];
					$idLink++;
					echo "<a href='?p=mflist&amp;b=$idLink'><div class='renglonMenu'>$tipoMaf</div></a>";
				}
			?>	
			<a href="?p=maneuver"><div class="renglonMenu">Maniobras de pelea</div></a>
			<a href="?p=arquetip"><div class="renglonMenu">Personalidades</div></a>
		</div>
	</div>

	<!-- PODERES -->
	<div class="menu-section">
		<a href="javascript:MostrarOcultar('powersMenu');" id="menu11"
		   onmouseover="Permut(1,'IMG11');" onmouseout="Permut(0,'IMG11');">
			<img src="img/menu/powers_icon.png" class="menuIcon" name="IMG11"
				 onload="preloadPermut(this,'img/menu/powers_icon_hover.png');" style="float:left;margin-right:7px;">
		</a>
	</div>
	<div class="menu-subsection">
		<div class="ocultable" id="powersMenu">
			<a href="?p=dones"><div class="renglonMenu">Dones</div></a>
			<a href="?p=rites"><div class="renglonMenu">Rituales</div></a>
			<a href="?p=totems"><div class="renglonMenu">T&oacute;tems</div></a>
			<a href="?p=disciplinas"><div class="renglonMenu">Disciplinas</div></a>
		</div>
	</div>

</div>
