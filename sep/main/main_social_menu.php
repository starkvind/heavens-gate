<?php
	echo "<div style='float:left;text-align:right;width:97.5%;margin-bottom:-10px;position:relative;z-index:10;'>";
		/*echo "<a href='$linkTwitter' target='_blank'><img src='img/twitter.gif' alt='Compartir en Twitter'/></a>";
		echo "<a href='$linkFacebook' target='_blank'><img src='img/facebook.gif' alt='Compartir en Facebook' style='margin-left:6px;'/></a>";
		echo "<a href='$linkGoogle' target='_blank'><img src='img/googleplus.gif' alt='Compartir en Google+' style='margin-left:6px;' /></a>";*/

		// Comprobamos si $bioSheet está definida y es igual a "pj"
		if (isset($bioSheet) && $bioSheet == "pj") {
			//echo "<a href='$bioCharSheet'><img src='img/icon-impr.gif' alt='Imprimir FPJ' style='margin-left:6px;'/></a>";
			echo "";
		}
	echo "</div>";
?>
