<?php

if (!function_exists('sim_pick_random_armor_for_character')) {
	function sim_pick_random_armor_for_character($link, $characterId)
	{
		$characterId = (int)$characterId;
		$ids = array();

		if ($characterId > 0) {
			$query = "
			SELECT i.id
			FROM bridge_characters_items b
			INNER JOIN vw_sim_items i ON i.id = b.item_id
			WHERE b.character_id = {$characterId}
			  AND COALESCE(i.tipo, 0) = 2
			";
			$rs = mysql_query($query, $link);
			if ($rs) {
				while ($row = mysql_fetch_array($rs)) {
					$ids[] = (int)($row['id'] ?? 0);
				}
				mysql_free_result($rs);
			}
		}

		if (empty($ids)) {
			$rs = mysql_query("SELECT id FROM vw_sim_items WHERE COALESCE(tipo, 0) = 2", $link);
			if ($rs) {
				while ($row = mysql_fetch_array($rs)) {
					$ids[] = (int)($row['id'] ?? 0);
				}
				mysql_free_result($rs);
			}
		}

		$ids = array_values(array_filter(array_unique($ids)));
		if (empty($ids)) {
			return 0;
		}
		return $ids[array_rand($ids)];
	}
}

$protec11 = sim_pick_random_armor_for_character($link, $idPJno1 ?? 0);
$protec21 = sim_pick_random_armor_for_character($link, $idPJno2 ?? 0);

$protec1 = $protec11;
$protec2 = $protec21;

?>
