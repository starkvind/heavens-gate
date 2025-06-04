<?php include("sep/main/main_nav_bar.php");	// Barra Navegación ?>
<h2>Enlaces</h2>
<br/>
<fieldset class="grupoBioClan">
	<?php
		$consulta ="SELECT nombre, enlace FROM enlaces ORDER BY orden";
		$IdConsulta = mysqli_query($link, $consulta);
			while ($ResultQuery = mysqli_fetch_assoc($IdConsulta)) {
				$nameLink = $ResultQuery["nombre"];
				$urlLink  = $ResultQuery["enlace"];
				if (isset($yearBook) && $yearBook != 0) {
					$goodYearBook = $yearBook;
				} else {
					$goodYearBook = "";
				}
					print("<a href='$urlLink' target='_blank'><div class='renglonBiblio' style='text-align: center;width:258px;'>$nameLink</div>");
			}
		mysqli_free_result($IdConsulta);
	?>
</fieldset>