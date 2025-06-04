<?php
	for($nKills=0;$nKills<$numberFilasKills;$nKills++) {
		print ("<a href='?p=muestrabio&amp;b=$killsId[$nKills]' target='_blank'>
			<div class='bioSheetPower'>
				<img class='valign' style='width:13px; height:13px;' src='$bioSameIcon'>
				$killsName[$nKills]
			</div></a>
		");
	}

?>