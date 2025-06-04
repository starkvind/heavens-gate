<?php 
	//global $link; // Asegúrate de que $link sea accesible en el ámbito global
	include("sep/heroes.php"); // Archivo de la base de datos
?>
<nav class="tmenu">
    <!-- TEMA -->
	 <!-- UTILIDADES !-->
		<div>
		<br/>
		<a href="javascript:MostrarOcultar('toolsMenu');" id="menu7" onMouseover="Permut(1,'IMG8');" onMouseout="Permut(0,'IMG8');">
			<img src="img/menu/tools_icon.png" class="menuIcon" align="left" NAME="IMG8" onLoad="preloadPermut(this,'img/menu/tools_icon_hover.png');">
		</a>
		</div>
	
	
		<div class="sekzo">
			<div class="ocultable" id="toolsMenu">
				<!-- <a href="index.php?p=dados"><div class="renglonMenu">Tira-dados</div></a>-->
				<!--<a href="index.php?p=simulador"><div class="renglonMenu">Simulador</div></a>-->
				<a href="index.php?p=news"><div class="renglonMenu">Noticias</div></a>
				<a href="index.php?p=busq"><div class="renglonMenu">Buscar</div></a>
				<a href="index.php?p=about"><div class="renglonMenu">Acerca de...</div></a>
				<a href="index.php?p=csp"><div class="renglonMenu">Tablón de Mensajes</div></a>
				<!--<div class="renglonMenu">Test de conocimiento</div>
				<a href="index.php?p=dwn"><div class="renglonMenu">Descargas</div></a>-->
				<br /><br />
			</div>
		</div>
	 <!-- UTILIDADES !-->
    <!-- ============================================================================ -->
     <!-- ARCHIVO -->
        <div>
        <br/>
        <a href="javascript:MostrarOcultar('archivoMenu');" id="menu1" onMouseover="Permut(1,'IMG2');" onMouseout="Permut(0,'IMG2');">
            <img src="img/menu/archive_icon.png" class="menuIcon" align="left" name="IMG2" onload="preloadPermut(this,'img/menu/archive_icon_hover.png');">
        </a>
        </div>
    
        
        <div class='sekzo'>
            <div class="ocultable" id="archivoMenu">
                <?php
                    // Conexión a la base de datos usando MySQLi
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

                        echo "<a href='index.php?p=temp&amp;t=$idTemporada'><div class='renglonMenu'>$nombreTemporada</div></a>";
                    }

                    mysqli_stmt_close($stmt);
                ?>
            </div>
        </div>
     <!-- ARCHIVO -->
    <!-- ============================================================================ -->
     <!-- HISTORIAS PERSONALES -->
        <div>
        <br/>
        <a href="javascript:MostrarOcultar('personalesMenu');" id="menu2" onMouseover="Permut(1,'IMG3');" onMouseout="Permut(0,'IMG3');">
            <img src="img/menu/persona_icon.png" class="menuIcon" align="left" name="IMG3" onload="preloadPermut(this,'img/menu/persona_icon_hover.png');">
        </a>
        </div>
    
    
        <div class='sekzo'>
            <div class="ocultable" id="personalesMenu">
                <?php
                    $consulta = "SELECT id, name FROM archivos_temporadas WHERE season LIKE '1' ORDER BY numero";
                    $stmt = mysqli_prepare($link, $consulta);
                    mysqli_stmt_execute($stmt);
                    $result = mysqli_stmt_get_result($stmt);

                    while ($ResultQuery = mysqli_fetch_assoc($result)) {
                        $idHistoria = htmlspecialchars($ResultQuery["id"]);
                        $nombreHistoria = htmlspecialchars($ResultQuery["name"]);
                        echo "<a href='index.php?p=temp&amp;t=$idHistoria'><div class='renglonMenu'>$nombreHistoria</div></a>";
                    }

                    mysqli_stmt_close($stmt);
                ?>
            </div>
        </div>
     <!-- HISTORIAS PERSONALES -->
    <!-- ============================================================================ -->
     <!-- BIOGRAFIAS -->
        <div>
        <br/>
        <a href="javascript:MostrarOcultar('bioMenu');" id="menu3" onMouseover="Permut(1,'IMG4');" onMouseout="Permut(0,'IMG4');">  
            <img src="img/menu/bio_icon.png" class="menuIcon" align="left" name="IMG4" onload="preloadPermut(this,'img/menu/bio_icon_hover.png');">
        </a>
        </div>
    
    
        <div class='sekzo'>
        <div class="ocultable" id="bioMenu">
            <?php
                $consulta = "SELECT * FROM afiliacion ORDER BY orden";
                $stmt = mysqli_prepare($link, $consulta);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                while ($ResultQuery = mysqli_fetch_assoc($result)) {
                    $nombreAfiliacion = htmlspecialchars($ResultQuery["tipo"]);
                    $idAfiliacion = htmlspecialchars($ResultQuery["id"]);
                    echo "<a href='index.php?p=bios&amp;t=$idAfiliacion'><div class='renglonMenu'>$nombreAfiliacion</div></a>";
                }

                echo "<a href='index.php?p=listgroups'><div class='renglonMenu'>Grupos y sociedades</div></a>";
                echo "<a href='index.php?p=list_by_order'><div class='renglonMenu'>Listas organizadas</div></a>";
				echo "<a href='reltree/' target='_blank'><div class='renglonMenu'>Nebulosa relaciones</div></a>";

                /*if ($userIP == $ipPermit) {
                    echo "<a href='index.php?p=list_by_id'><div class='renglonMenu'>Lista por ID</div></a>";
                    echo "<a href='index.php?p=list_avatar'><div class='renglonMenu'>PJS sin avatar</div></a>";
                }*/

                mysqli_stmt_close($stmt);
            ?>
        </div>
        </div>
     <!-- BIOGRAFIAS -->
	<!-- ============================================================================ -->
	 <!-- Sigue con el mismo patrón para el resto de secciones -->
	 <?php if (1 === 0): ?>
	 <!-- DOCUMENTACION !-->
		<div>
		<br/>
		<a href="javascript:MostrarOcultar('documentMenu');" id="menu4" onMouseover="Permut(1,'IMG5');" onMouseout="Permut(0,'IMG5');">
			<img src="img/menu/doc_icon.png" class="menuIcon" align="left" NAME="IMG5" onLoad="preloadPermut(this,'img/menu/doc_icon_hover.png');">
		</a>
		</div>
	
	
		<div class='sekzo'>
		<div class="ocultable" id="documentMenu">
			<?php // DOCUMENTACION
                // Consulta para obtener la documentación ordenada por 'orden'
                $consulta = "SELECT id, tipo FROM documentacion ORDER BY orden";

                $stmt = mysqli_prepare($link, $consulta);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);

                // Recorrer los resultados y generar el menú
                while ($ResultQuery = mysqli_fetch_assoc($result)) {
                    $idDoc = htmlspecialchars($ResultQuery["id"]);
                    $nombreDoc = ($ResultQuery["tipo"]);
                    echo "<a href='index.php?p=doc&amp;t=$idDoc'><div class='renglonMenu'>$nombreDoc</div></a>";
                }

                // Liberar el resultado de la memoria
                mysqli_stmt_close($stmt);
			?>
		</div>
		</div>
	 <!-- DOCUMENTACION !-->
	<?php endif; ?>
    <!-- ============================================================================ -->
	 <!-- INVENTARIO !-->
		<div>
		<br/>
		<a href="javascript:MostrarOcultar('inventoryMenu');" id="menu5" onMouseover="Permut(1,'IMG6');" onMouseout="Permut(0,'IMG6');">
			<img src="img/menu/inventory_icon.png" class="menuIcon" align="left" NAME="IMG6" onLoad="preloadPermut(this,'img/menu/inventory_icon_hover.png');">
		</a>
		</div>
	
	
		<div class="sekzo">
			<div class="ocultable" id="inventoryMenu">
				<?php // INVENTARIO
					$consulta ="SELECT id, name FROM nuevo3_tipo_objetos ORDER BY orden";

					$stmt = mysqli_prepare($link, $consulta);
					mysqli_stmt_execute($stmt);
					$result = mysqli_stmt_get_result($stmt);
						while ($ResultQuery = mysqli_fetch_assoc($result)) {
							$idObj = htmlspecialchars($ResultQuery["id"]);
							$tipoObj = $ResultQuery["name"];
							echo "<a href='index.php?p=inv&amp;t=$idObj'><div class='renglonMenu'>$tipoObj</div></a>";
						}
					mysqli_stmt_close($stmt);
					/* Sección imágenes */
					//echo "<a href='index.php?p=imgz'><div class='renglonMenu'>Im&aacute;genes</div></a>";
					/* Fin sección imágenes */
				?>
			</div>
		</div>
	 <!-- INVENTARIO !-->
	<!-- ============================================================================ !-->
	<?php if (1 === 0): ?>
	 <!-- SISTEMAS !-->
		<div>
		<br/>
		<a href="javascript:MostrarOcultar('systemMenu');" id="menu6" onMouseover="Permut(1,'IMG7');" onMouseout="Permut(0,'IMG7');">
			<img src="img/menu/system_icon.png" class="menuIcon" align="left" NAME="IMG7" onLoad="preloadPermut(this,'img/menu/system_icon_hover.png');">
		</a>
		</div>
	
	
		<div class="sekzo">
			<div class="ocultable" id="systemMenu">
				<?php // SISTEMAS
					$consulta ="SELECT id, name FROM nuevo_sistema ORDER BY orden";
					$stmt = mysqli_prepare($link, $consulta);
					mysqli_stmt_execute($stmt);
					$result = mysqli_stmt_get_result($stmt);

						while ($ResultQuery = mysqli_fetch_assoc($result)) {
							$idSistema = $ResultQuery["id"];
							$nameSistema = $ResultQuery["name"];
							echo "<a href='index.php?p=sistemas&amp;b=$idSistema'><div class='renglonMenu'>$nameSistema</div></a>";
						}
				?>
			</div>
		</div>
	 <!-- SISTEMAS !-->
	<?php endif; ?>
	<!-- ============================================================================ !-->
	 <!-- HABILIDADES !-->
		<div>
		<br/>
		<a href="javascript:MostrarOcultar('skillMenu');" id="menu10" onMouseover="Permut(1,'IMG10');" onMouseout="Permut(0,'IMG10');">
			<img src="img/menu/skill_icon.png" class="menuIcon" align="left" NAME="IMG10" onLoad="preloadPermut(this,'img/menu/skill_icon_hover.png');">
		</a>
		</div>
	
	
		<div class="sekzo">
			<div class="ocultable" id="skillMenu">
				<?php // HABILIDADES
					$idLink = 0;
					$consulta ="SELECT DISTINCT tipo FROM nuevo_habilidades ORDER BY id";

					$stmt = mysqli_prepare($link, $consulta);
					mysqli_stmt_execute($stmt);
					$result = mysqli_stmt_get_result($stmt);

						while ($ResultQuery = mysqli_fetch_assoc($result)) {
							$tipoSkill = $ResultQuery["tipo"];
							$idLink = $idLink+1;
							echo "<a href='index.php?p=listsk&amp;b=$idLink'><div class='renglonMenu'>$tipoSkill</div></a>";
						}
				?>
				<?php // MERITOS Y DEFECTOS
					$idLink = 0;
					$consulta ="SELECT DISTINCT tipo FROM nuevo_mer_y_def ORDER BY id";

					$stmt = mysqli_prepare($link, $consulta);
					mysqli_stmt_execute($stmt);
					$result = mysqli_stmt_get_result($stmt);

						while ($ResultQuery = mysqli_fetch_assoc($result)) {
							
							$tipoMaf = $ResultQuery["tipo"];
							$idLink = $idLink+1;
							echo "<a href='index.php?p=mflist&amp;b=$idLink'><div class='renglonMenu'>$tipoMaf</div></a>";
						}
				?>	
				<a href="index.php?p=maneuver"><div class="renglonMenu">Maniobras de pelea</div></a>
				<a href="index.php?p=arquetip"><div class="renglonMenu">Personalidades</div></a>
			</div>
		</div>
	 <!-- HABILIDADES !-->
	<!-- ============================================================================ !-->
	 <!-- PODERES !-->
		<div>
		<br/>
		<a href="javascript:MostrarOcultar('powersMenu');" id="menu11" onMouseover="Permut(1,'IMG11');" onMouseout="Permut(0,'IMG11');">
			<img src="img/menu/powers_icon.png" class="menuIcon" align="left" NAME="IMG11" onLoad="preloadPermut(this,'img/menu/powers_icon_hover.png');">
		</a>
		</div>
	
	
		<div class="sekzo">
			<div class="ocultable" id="powersMenu">
				<a href="index.php?p=dones"><div class="renglonMenu">Dones</div></a>
				<a href="index.php?p=rites"><div class="renglonMenu">Rituales</div></a>
				<a href="index.php?p=totems"><div class="renglonMenu">T&oacute;tems</div></a>
				<a href="index.php?p=disciplinas"><div class="renglonMenu">Disciplinas</a></div>
			</div>
		</div>
	 <!-- PODERES !-->
	<!-- ============================================================================ !-->
    <!-- Pie del menú -->
	<!--
    
        <div align="center">
            <a rel="license" href="http://creativecommons.org/licenses/by-nc-sa/2.5/es/">
                <img alt="Creative Commons License" style="border-width:0" src="img/cc80x15.png" />
            </a>
            <a href="http://www.difundefirefox.com" title="Difunde Firefox" target="_blank">
                <img src="img/boton-dfx1.png" alt="Difunde Firefox" style="border:0;"/>
            </a>
            <a href="http://validator.w3.org/check?uri=referer" target="_blank">
                <img src="img/valid-xhtml10.gif" alt="Valid XHTML 1.0 Strict" />
            </a>
            <a href="http://jigsaw.w3.org/css-validator/check/referer" target="_blank">
                <img border="0" alt="Valid CSS!" title="Valid CSS!" src="img/vcss.gif" />
            </a>
            <a href="http://www.php.net" title="PHP Powered" target="_blank">
                <img border="0" alt="PHP Powered" title="PHP Powered" src="img/php.png" />
            </a>
            <a href="http://mysql.com" title="MySQL Database" target="_blank">
                <img border="0" alt="MySQL Database" title="MySQL Database" src="img/mysql.gif" />
            </a>
        </div>
     -->
	<!-- Pie del menú -->
	
</nav>
