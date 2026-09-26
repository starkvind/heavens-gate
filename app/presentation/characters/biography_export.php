<?php

if (!function_exists('bio_plain_text')) {
    function bio_plain_text($value): string
    {
        $text = (string)$value;
        $text = preg_replace('/<\\s*br\\s*\\/?\\s*>/i', "\n", $text);
        $text = str_replace(['</p>', '</div>', '</li>', '</fieldset>', '</legend>', '</tr>'], "\n", $text);
        $text = str_replace(['<li>'], ['- '], $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace("/\\r\\n|\\r/u", "\n", $text);
        $text = preg_replace("/[ \\t]+\\n/u", "\n", $text);
        $text = preg_replace("/\\n{3,}/u", "\n\n", $text);
        return trim((string)$text);
    }
}

if (!function_exists('bio_export_add_section')) {
    function bio_export_add_section(array &$sections, string $title, array $lines): void
    {
        $clean = [];
        foreach ($lines as $line) {
            $text = bio_plain_text($line);
            if ($text !== '') $clean[] = $text;
        }
        if (!$clean) return;
        $sections[] = implode("\n", [
            str_repeat('=', 12),
            $title,
            str_repeat('=', 12),
            implode("\n", $clean),
        ]);
    }
}

$bioExportSections = [];
		$bioExportMeta = [];
		$bioExportMeta[] = 'Nombre: ' . $bioName;
		if (trim((string)$bioAlias) !== '') $bioExportMeta[] = 'Alias: ' . $bioAlias;
		if (trim((string)$bioPackName) !== '') $bioExportMeta[] = $titlePkName . ': ' . $bioPackName;
		$bioExportMeta[] = ($bioBirthLabel ?? 'Fecha de nacimiento') . ': ' . (($bioBday !== '') ? $bioBday : 'Desconocido');
		if (trim((string)$bioStatus) !== '') $bioExportMeta[] = 'Estado: ' . $bioStatus;
		if (trim((string)($bioDeathDisplay ?? '')) !== '') $bioExportMeta[] = 'Muerte: ' . $bioDeathDisplay;
		if (trim((string)$bioConcept) !== '') $bioExportMeta[] = 'Concepto: ' . $bioConcept;
		bio_export_add_section($bioExportSections, 'DATOS DEL PERSONAJE', $bioExportMeta);

		$bioExportInfo = [];
		if (trim((string)$bioText) !== '') $bioExportInfo[] = bio_plain_text($bioText);
		if ($bioIsAdminFlag && trim((string)$bioNotes) !== '') $bioExportInfo[] = 'Notas internas (admin):' . "\n" . bio_plain_text($bioNotes);
		bio_export_add_section($bioExportSections, 'INFORMACION', $bioExportInfo);

		if ($bioHasSheet) {
			$bioExportSheetTop = [];
			if ($bioRace != 0) $bioExportSheetTop[] = $titleBreed . ': ' . bio_plain_text($raceLink ?? '');
			if ($bioAuspice != 0) $bioExportSheetTop[] = $titleAuspice . ': ' . bio_plain_text($auspiceLink ?? '');
			if ($bioTribe != 0) $bioExportSheetTop[] = $titleTribe . ': ' . bio_plain_text($tribeLink ?? '');
			if (!empty($bioMiscLinksByKind) && is_array($bioMiscLinksByKind)) {
				foreach ($bioMiscLinksByKind as $miscKind => $miscLinks) {
					$txt = bio_plain_text(implode(', ', array_values((array)$miscLinks)));
					if ($txt !== '') $bioExportSheetTop[] = bio_plain_text((string)$miscKind) . ': ' . $txt;
				}
			}
			if (($bioTotemId ?? 0) > 0 || ($totemLink ?? '') !== '' || trim((string)$bioTotem) !== '') {
				$bioExportSheetTop[] = 'Totem: ' . bio_plain_text(($totemLink ?? '') !== '' ? $totemLink : $bioTotem);
			}
			if ((int)($bioNature ?? 0) > 0) $bioExportSheetTop[] = 'Naturaleza: ' . bio_plain_text($natureLink ?? '');
			if ((int)($bioBehavior ?? 0) > 0) $bioExportSheetTop[] = 'Conducta: ' . bio_plain_text($demeanorLink ?? '');
			if ($bioPack != 0) $bioExportSheetTop[] = $titlePack . ': ' . bio_plain_text($packLink ?? '');
			if ($bioClan != 0) $bioExportSheetTop[] = $titleClan . ': ' . bio_plain_text($clanLink ?? '');
			if ($bioPlayer != 0) {
				$playerDisplay = (isset($playerLinkOfChara) && $playerLinkOfChara !== '') ? $playerLinkOfChara : ($namePlayerOfChara ?? '');
				$bioExportSheetTop[] = 'Jugador: ' . bio_plain_text($playerDisplay);
			}
			if ($bioChronic != 0) $bioExportSheetTop[] = 'Cronica: ' . bio_plain_text($nameCronicaFinal ?? '');
			bio_export_add_section($bioExportSections, 'DETALLES DE HOJA', $bioExportSheetTop);

			$bioExportAttrs = [];
			foreach ($bioAttrList as $trait) {
				$name = trim((string)($trait['name'] ?? ''));
				if ($name === '') continue;
				$bioExportAttrs[] = $name . ': ' . (int)($trait['value'] ?? 0);
			}
			bio_export_add_section($bioExportSections, 'ATRIBUTOS', $bioExportAttrs);

			$bioExportSkills = [];
			foreach ($bioSkillCols as $groupName => $traits) {
				$bioExportSkills[] = '[' . bio_plain_text((string)$groupName) . ']';
				foreach ($traits as $trait) {
					$name = trim((string)($trait['name'] ?? ''));
					if ($name === '') continue;
					$bioExportSkills[] = $name . ': ' . (int)($trait['value'] ?? 0);
				}
				$bioExportSkills[] = '';
			}
			bio_export_add_section($bioExportSections, 'HABILIDADES', $bioExportSkills);

			$bioExportBackgrounds = [];
			foreach ($bioBackgrounds as $bg) {
				$name = trim((string)($bg['name'] ?? ''));
				$val = (int)($bg['value'] ?? 0);
				if ($name === '' || $val <= 0) continue;
				$bioExportBackgrounds[] = $name . ': ' . $val;
			}
			bio_export_add_section($bioExportSections, 'TRASFONDOS', $bioExportBackgrounds);

			if (!$bioIsMonster) {
				$bioExportMerits = [];
				foreach (hg_characters_fetch_merits_flaws($link, $characterId) as $row) {
					$name = trim((string)($row['name'] ?? ''));
					if ($name === '') continue;
					$kind = trim((string)($row['kind'] ?? ''));
					$level = $row['level'] ?? $row['cost'] ?? null;
					$line = $name;
					if ($kind !== '') $line .= ' [' . $kind . ']';
					if ($level !== null) $line .= ': ' . (int)$level;
					$bioExportMerits[] = $line;
				}
				bio_export_add_section($bioExportSections, 'MERITOS Y DEFECTOS', $bioExportMerits);
			}

			$bioExportResources = [];
			$resourcesByKindExport = hg_characters_fetch_resources($link, $characterId, (int)($bioSystemId ?? 0));
			foreach (($resourcesByKindExport['renombre'] ?? []) as $res) {
				$bioExportResources[] = '[Renombre] ' . (string)($res['name'] ?? '') . ': P ' . (int)($res['perm'] ?? 0) . ' / T ' . (int)($res['temp'] ?? 0);
			}
			if (!$bioIsMonster && trim((string)$bioRange) !== '') $bioExportResources[] = '[Renombre] Rango: ' . $bioRange;
			foreach (($resourcesByKindExport['estado'] ?? []) as $res) {
				$bioExportResources[] = '[Estado] ' . (string)($res['name'] ?? '') . ': ' . (int)($res['temp'] ?? 0) . '/' . (int)($res['perm'] ?? 0);
			}
			if (!$bioIsMonster) {
				foreach (($resourcesByKindExport['exp'] ?? []) as $res) {
					$bioExportResources[] = '[Experiencia] ' . (string)($res['name'] ?? '') . ': ' . (int)($res['temp'] ?? 0) . '/' . (int)($res['perm'] ?? 0) . ' PX';
				}
			}
			bio_export_add_section($bioExportSections, 'RECURSOS', $bioExportResources);

			$bioExportConditions = [];
			foreach (hg_characters_fetch_conditions($link, $characterId) as $row) {
				$line = trim((string)($row['name'] ?? ''));
				if ($line === '') continue;
				$location = trim((string)($row['condition_location'] ?? ''));
				$instanceNo = (int)($row['instance_no'] ?? 1);
				if ($location !== '') $line .= ' (' . $location . ')';
				elseif ($instanceNo > 1) $line .= ' #' . $instanceNo;
				$category = trim((string)($row['category'] ?? ''));
				if ($category !== '') $line .= ' [' . $category . ']';
				$bioExportConditions[] = $line;
			}
			bio_export_add_section($bioExportSections, 'CONDICIONES', $bioExportConditions);

			$bioExportPowers = [];
			$powersExport = hg_characters_fetch_powers($link, $characterId);
			foreach (['dones' => 'Dones', 'disciplinas' => 'Disciplinas', 'rituales' => 'Rituales'] as $kindKey => $kindLabel) {
				$list = $powersExport[$kindKey] ?? [];
				if (empty($list)) continue;
				$bioExportPowers[] = '[' . $kindLabel . ']';
				foreach ($list as $row) {
					$line = trim((string)($row['name'] ?? ''));
					if ($line === '') continue;
					if (($row['level'] ?? null) !== null) $line .= ': ' . (int)$row['level'];
					$bioExportPowers[] = $line;
				}
				$bioExportPowers[] = '';
			}
			bio_export_add_section($bioExportSections, 'PODERES', $bioExportPowers);

			$bioExportItems = [];
			foreach (hg_characters_fetch_items($link, $characterId) as $row) {
				$name = trim((string)($row['name'] ?? ''));
				if ($name === '') continue;
				$typeName = trim((string)($row['item_type_name'] ?? ''));
				$bioExportItems[] = ($typeName !== '' ? '[' . $typeName . '] ' : '') . $name;
			}
			bio_export_add_section($bioExportSections, 'INVENTARIO', $bioExportItems);
		}

		$bioExportRelations = [];
		foreach ($relaciones as $rel) {
			$name = trim((string)($rel['name'] ?? ''));
			$type = trim((string)($rel['relation_type'] ?? ''));
			if ($name === '' && $type === '') continue;
			$dir = ((string)($rel['direction'] ?? '') === 'incoming') ? 'recibe de' : 'hacia';
			$bioExportRelations[] = ($type !== '' ? $type : 'Relacion') . ' [' . $dir . ']: ' . $name;
		}
		foreach ($killsAsKiller as $kill) {
			$victim = trim((string)($kill['victim_name'] ?? ''));
			if ($victim === '') continue;
			$extra = trim((string)($kill['death_date'] ?? ''));
			if ($extra === '' && trim((string)($kill['event_date'] ?? '')) !== '') $extra = trim((string)$kill['event_date']);
			$line = 'Muerte causada: ' . $victim;
			if ($extra !== '') $line .= ' (' . $extra . ')';
			$bioExportRelations[] = $line;
		}
		bio_export_add_section($bioExportSections, 'RELACIONES', $bioExportRelations);

		$bioExportParticipation = [];
		foreach ($participacion as $part) {
			$seasonName = trim((string)($part['temporada_name'] ?? ''));
			$chapterName = trim((string)($part['name'] ?? ''));
			$playedDate = trim((string)($part['played_date'] ?? ''));
			$bits = [];
			if ($seasonName !== '') $bits[] = $seasonName;
			if ($chapterName !== '') $bits[] = $chapterName;
			if ($playedDate !== '') $bits[] = $playedDate;
			if (!empty($bits)) $bioExportParticipation[] = implode(' | ', $bits);
		}
		if ($numEventosParticipa > 0) $bioExportParticipation[] = 'Eventos de timeline vinculados: ' . $numEventosParticipa;
		bio_export_add_section($bioExportSections, 'PARTICIPACION', $bioExportParticipation);

		$bioExportDocs = [];
		foreach ($characterDocs as $doc) {
			$title = trim((string)($doc['title'] ?? ''));
			if ($title === '') continue;
			$prefix = trim((string)($doc['section_name'] ?? ''));
			$relLabel = trim((string)($doc['relation_label'] ?? ''));
			$line = $title;
			if ($prefix !== '') $line = '[' . $prefix . '] ' . $line;
			if ($relLabel !== '') $line .= ' - ' . $relLabel;
			$bioExportDocs[] = $line;
		}
		foreach ($characterExternalLinks as $ext) {
			$title = trim((string)($ext['title'] ?? ''));
			$url = trim((string)($ext['url'] ?? ''));
			if ($title === '' && $url === '') continue;
			$line = $title !== '' ? $title : $url;
			$kind = trim((string)($ext['kind'] ?? ''));
			if ($kind !== '') $line = '[' . $kind . '] ' . $line;
			if ($url !== '' && $url !== $title) $line .= ' - ' . $url;
			$bioExportDocs[] = $line;
		}
		bio_export_add_section($bioExportSections, 'DOCUMENTACION Y ENLACES', $bioExportDocs);

		$bioPlainExportText = trim(implode("\n\n", $bioExportSections));
