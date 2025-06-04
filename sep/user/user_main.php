<?php
	$urlLegend	= "&nbsp;Usuario:&nbsp;";
	$urlAvatar	= "eloise_hagen";
		$urlImage 	= "<img src='img/subidas/$urlAvatar.jpg'/>";
	$urlMenuIcon1 = "img/usermenu/001.png";
	$urlMenuIcon2 = "img/usermenu/002.png";
	$urlMenuIcon3 = "img/usermenu/003.png";
	$urlMenuIcon4 = "img/usermenu/004.png";
	$urlMenuIcon5 = "img/usermenu/005.png";
		$urlText	= 
			"<img src='$urlMenuIcon1'/>
			<img src='$urlMenuIcon3'/>
			<img src='$urlMenuIcon4'/>
			<img src='$urlMenuIcon5'/>
			";
	// ----------------------------------
	//echo "<a>";
	echo "<div id='zonaUsuario'>";
		echo "<fieldset>";
			echo "<legend>$urlLegend</legend>";
			echo $urlImage;
			echo "<div id='textoUsuario'>$urlText</div>";
		echo "</fieldset>";
	echo "</div>";
	//echo "</a>";
	// ----------------------------------
?>