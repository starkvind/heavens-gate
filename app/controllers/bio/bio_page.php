<?php
	include_once(__DIR__ . '/../../helpers/character_avatar.php');
	if (session_status() === PHP_SESSION_NONE) { @session_start(); }
	/*  Índice de secciones
			1.- Query en Base de Datos 		[#SEC01]
			2.- Foto del Personaje 			[#SEC02]
			3.- Datos básicos - Detalles	[#SEC03]
			4.- Parte superior de la Hoja	[#SEC04]
			5.- Atributos					[#SEC05]
			6.- Habilidades					[#SEC06]
			7.- Trasfondos y Ventajas		[#SEC07]
			8.- Méritos y Defectos			[#SEC08]
			9.- Renombre / Virtudes			[#SEC09]
		   10.- Fuerza de Voluntad			[#SEC10]
		   11.- Poderes - Dones, Discip.	[#SEC11]
		   12.- Rituales					[#SEC12]
		   13.- Inventario del personaje	[#SEC13]
		   14.- Biografías similares		 [#SEC14]
		   15.- Comentarios					[#SEC15]
	*/

	// Helpers (escape + fetch sin depender de mysqlnd)
	function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
	$mensajeDeError = 'No se pudo cargar el personaje solicitado.';

	function bio_plain_text($value): string {
		$text = (string)$value;
		$text = preg_replace('/<\s*br\s*\/?\s*>/i', "\n", $text);
		$text = str_replace(['</p>', '</div>', '</li>', '</fieldset>', '</legend>', '</tr>'], "\n", $text);
		$text = str_replace(['<li>'], ['- '], $text);
		$text = strip_tags($text);
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		$text = preg_replace("/\r\n|\r/u", "\n", $text);
		$text = preg_replace("/[ \t]+\n/u", "\n", $text);
		$text = preg_replace("/\n{3,}/u", "\n\n", $text);
		return trim((string)$text);
	}

	function bio_export_add_section(array &$sections, string $title, array $lines): void {
		$clean = [];
		foreach ($lines as $line) {
			$text = bio_plain_text($line);
			if ($text === '') continue;
			$clean[] = $text;
		}
		if (empty($clean)) return;
		$sections[] = implode("\n", [
			str_repeat('=', 12),
			$title,
			str_repeat('=', 12),
			implode("\n", $clean),
		]);
	}

	function bio_export_fetch_resources(mysqli $link, int $characterId, int $systemId = 0): array {
		$out = [];
		$bridgeTable = null;
		foreach (['bridge_characters_system_resources', 'bridge_characters_resources'] as $candidate) {
			if (!table_exists($link, $candidate)) continue;
			$bridgeTable = $candidate;
			break;
		}
		if ($bridgeTable === null || $characterId <= 0 || !table_exists($link, 'dim_systems_resources')) return $out;

		$hasBridgeSysRes = table_exists($link, 'bridge_systems_resources_to_system');
		$hasBridgeSysResSort = $hasBridgeSysRes && column_exists($link, 'bridge_systems_resources_to_system', 'sort_order');

		$sql = "
			SELECT r.id, r.name, r.kind, b.value_permanent, b.value_temporary,
		";
		$sql .= ($hasBridgeSysRes && $hasBridgeSysResSort && $systemId > 0)
			? " COALESCE(bs.sort_order, r.sort_order, 9999) AS sort_order_eff "
			: " COALESCE(r.sort_order, 9999) AS sort_order_eff ";
		$sql .= "
			FROM `$bridgeTable` b
			INNER JOIN dim_systems_resources r ON r.id = b.resource_id
		";
		if ($hasBridgeSysRes && $hasBridgeSysResSort && $systemId > 0) {
			$sql .= "
			LEFT JOIN bridge_systems_resources_to_system bs
			       ON bs.resource_id = r.id
			      AND bs.system_id = ?
			";
		}
		$sql .= "
			WHERE b.character_id = ?
			ORDER BY r.kind, sort_order_eff, r.name
		";

		if ($st = $link->prepare($sql)) {
			if ($hasBridgeSysRes && $hasBridgeSysResSort && $systemId > 0) {
				$st->bind_param('ii', $systemId, $characterId);
			} else {
				$st->bind_param('i', $characterId);
			}
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$kind = strtolower(trim((string)($row['kind'] ?? '')));
					if (!isset($out[$kind])) $out[$kind] = [];
					$out[$kind][] = [
						'id' => (int)($row['id'] ?? 0),
						'name' => (string)($row['name'] ?? ''),
						'perm' => (int)($row['value_permanent'] ?? 0),
						'temp' => (int)($row['value_temporary'] ?? 0),
					];
				}
				$res->free();
			}
			$st->close();
		}

		return $out;
	}

	function bio_export_fetch_merits(mysqli $link, int $characterId): array {
		$out = [];
		$sql = "
			SELECT nmd.name, nmd.kind, nmd.cost, b.level
			FROM bridge_characters_merits_flaws b
			JOIN dim_merits_flaws nmd ON nmd.id = b.merit_flaw_id
			WHERE b.character_id = ?
			ORDER BY nmd.kind DESC, nmd.cost, nmd.name
		";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('i', $characterId);
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$out[] = [
						'name' => (string)($row['name'] ?? ''),
						'kind' => (string)($row['kind'] ?? ''),
						'level' => ($row['level'] !== null) ? (int)$row['level'] : null,
						'cost' => ($row['cost'] !== null) ? (int)$row['cost'] : null,
					];
				}
				$res->free();
			}
			$st->close();
		}
		return $out;
	}

	function bio_export_fetch_conditions(mysqli $link, int $characterId): array {
		$out = [];
		if (!table_exists($link, 'bridge_characters_conditions') || !table_exists($link, 'dim_character_conditions')) return $out;
		$hasConditionInstanceNo = column_exists($link, 'bridge_characters_conditions', 'instance_no');
		$hasConditionLocation = column_exists($link, 'bridge_characters_conditions', 'location');
		$hasConditionActive = column_exists($link, 'bridge_characters_conditions', 'is_active');
		$conditionInstanceSelect = $hasConditionInstanceNo ? 'bcc.instance_no' : '1';
		$conditionLocationSelect = $hasConditionLocation ? 'bcc.location' : 'NULL';
		$conditionActiveWhere = $hasConditionActive ? "AND (bcc.is_active = 1 OR bcc.is_active IS NULL)" : "";
		$sql = "
			SELECT c.name, c.category, {$conditionInstanceSelect} AS instance_no, {$conditionLocationSelect} AS condition_location
			FROM bridge_characters_conditions bcc
			JOIN dim_character_conditions c ON c.id = bcc.condition_id
			WHERE bcc.character_id = ?
			  {$conditionActiveWhere}
			ORDER BY
				CASE
					WHEN c.category = 'Deformidad Metis' THEN 0
					WHEN c.category = 'Herida de Guerra' THEN 1
					WHEN c.category LIKE '%Cicatrices%' THEN 1
					WHEN c.category = 'Trastorno Mental' THEN 2
					ELSE 9999
				END ASC,
				c.name ASC,
				instance_no ASC
		";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('i', $characterId);
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$out[] = [
						'name' => (string)($row['name'] ?? ''),
						'category' => (string)($row['category'] ?? ''),
						'instance_no' => (int)($row['instance_no'] ?? 1),
						'location' => trim((string)($row['condition_location'] ?? '')),
					];
				}
				$res->free();
			}
			$st->close();
		}
		return $out;
	}

	function bio_export_fetch_powers(mysqli $link, int $characterId): array {
		$bridgeRows = [];
		$out = ['dones' => [], 'disciplinas' => [], 'rituales' => []];
		if ($st = $link->prepare("SELECT power_kind, power_id, power_level FROM bridge_characters_powers WHERE character_id = ? ORDER BY power_kind ASC")) {
			$st->bind_param('i', $characterId);
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$kind = (string)($row['power_kind'] ?? '');
					if (!isset($out[$kind])) $out[$kind] = [];
					$bridgeRows[$kind][] = [
						'id' => (int)($row['power_id'] ?? 0),
						'level' => ($row['power_level'] !== null) ? (int)$row['power_level'] : null,
					];
				}
				$res->free();
			}
			$st->close();
		}

		foreach ($bridgeRows as $kind => $rows) {
			$ids = [];
			foreach ($rows as $row) {
				if (($row['id'] ?? 0) > 0) $ids[(int)$row['id']] = true;
			}
			if (empty($ids)) continue;
			$idList = implode(',', array_map('intval', array_keys($ids)));
			$meta = [];
			if ($kind === 'dones') {
				$query = "SELECT id, name, rank AS sort_level, rank AS display_level FROM fact_gifts WHERE id IN ($idList)";
			} elseif ($kind === 'disciplinas') {
				$query = "SELECT id, name, NULL AS sort_level, NULL AS display_level FROM dim_discipline_types WHERE id IN ($idList)";
			} elseif ($kind === 'rituales') {
				$query = "SELECT id, name, level AS sort_level, level AS display_level FROM fact_rites WHERE id IN ($idList)";
			} else {
				continue;
			}
			if ($rs = $link->query($query)) {
				while ($row = $rs->fetch_assoc()) {
					$meta[(int)$row['id']] = $row;
				}
				$rs->close();
			}
			foreach ($rows as $row) {
				$id = (int)($row['id'] ?? 0);
				$data = $meta[$id] ?? null;
				if (!$data) continue;
				$level = null;
				if ($kind === 'disciplinas') {
					$level = $row['level'];
				} else {
					$level = isset($data['display_level']) ? (int)$data['display_level'] : null;
				}
				$out[$kind][] = [
					'name' => (string)($data['name'] ?? ''),
					'level' => $level,
					'sort_level' => isset($data['sort_level']) && $data['sort_level'] !== null ? (int)$data['sort_level'] : 999,
				];
			}
			usort($out[$kind], static function (array $a, array $b): int {
				$cmp = ((int)($a['sort_level'] ?? 999)) <=> ((int)($b['sort_level'] ?? 999));
				if ($cmp !== 0) return $cmp;
				return strcasecmp((string)($a['name'] ?? ''), (string)($b['name'] ?? ''));
			});
		}

		return $out;
	}

	function bio_export_fetch_items(mysqli $link, int $characterId): array {
		$out = [];
		$sql = "
			SELECT o.name, o.item_type_id, COALESCE(t.name, '') AS item_type_name
			FROM bridge_characters_items b
			JOIN fact_items o ON o.id = b.item_id
			LEFT JOIN dim_item_types t ON t.id = o.item_type_id
			WHERE b.character_id = ?
			ORDER BY o.item_type_id, o.name
		";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('i', $characterId);
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$out[] = [
						'name' => (string)($row['name'] ?? ''),
						'type_name' => (string)($row['item_type_name'] ?? ''),
						'type_id' => (int)($row['item_type_id'] ?? 0),
					];
				}
				$res->free();
			}
			$st->close();
		}
		return $out;
	}

	function bio_page_is_admin_flag_enabled(): bool {
		$sessionAdmin = (!empty($_SESSION) && is_array($_SESSION) && !empty($_SESSION['is_admin']));
		if ($sessionAdmin) return true;
		$cookieValue = isset($_COOKIE['is_admin']) ? strtoupper(trim((string)$_COOKIE['is_admin'])) : '';
		return in_array($cookieValue, ['1', 'TRUE', 'YES', 'ON'], true);
	}

	function column_exists(mysqli $link, string $table, string $column): bool {
		static $cache = [];
		$key = $table . ':' . $column;
		if (isset($cache[$key])) return $cache[$key];
		$ok = false;
		if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?")) {
			$st->bind_param('ss', $table, $column);
			$st->execute();
			$st->bind_result($count);
			$st->fetch();
			$st->close();
			$ok = ((int)$count > 0);
		}
		$cache[$key] = $ok;
		return $ok;
	}

	function table_exists(mysqli $link, string $table): bool {
		static $cache = [];
		$key = $table;
		if (isset($cache[$key])) return $cache[$key];
		$ok = false;
		if ($st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?")) {
			$st->bind_param('s', $table);
			$st->execute();
			$st->bind_result($count);
			$st->fetch();
			$st->close();
			$ok = ((int)$count > 0);
		}
		$cache[$key] = $ok;
		return $ok;
	}

	function stmt_fetch_all_assoc_compat(mysqli_stmt $stmt): array {
		$out = [];

		// Camino rápido (mysqlnd)
		if (method_exists($stmt, 'get_result')) {
			$res = @$stmt->get_result();
			if ($res instanceof mysqli_result) {
				while ($row = $res->fetch_assoc()) { $out[] = $row; }
				$res->free();
				return $out;
			}
		}

		// Fallback sin mysqlnd
		mysqli_stmt_store_result($stmt);
		$meta = mysqli_stmt_result_metadata($stmt);
		if (!$meta) return $out;

		$fields = [];
		$row = [];
		$bind = [];

		while ($field = mysqli_fetch_field($meta)) {
			$fields[] = $field->name;
			$row[$field->name] = null;
			$bind[] = &$row[$field->name];
		}
		mysqli_free_result($meta);

		call_user_func_array([$stmt, 'bind_result'], $bind);

		while (mysqli_stmt_fetch($stmt)) {
			$r = [];
			foreach ($fields as $f) { $r[$f] = $row[$f]; }
			$out[] = $r;
		}

		return $out;
	}

	function fetch_trait_values(mysqli $link, int $characterId): array {
		$map = [];
		if ($characterId <= 0) return $map;
		$sql = "SELECT trait_id, value FROM bridge_characters_traits WHERE character_id = ?";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('i', $characterId);
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$map[(int)$row['trait_id']] = (int)$row['value'];
				}
				$res->free();
			}
			$st->close();
		}
		return $map;
	}



	function fetch_traits_by_type(mysqli $link, int $characterId, string $kind, bool $onlyNonZero = true): array {
		$out = [];
		if ($characterId <= 0) return $out;
		$sql = "SELECT t.id, t.name, f.value
				FROM bridge_characters_traits f
				INNER JOIN dim_traits t ON t.id = f.trait_id
				WHERE f.character_id = ? AND t.kind = ?";
		if ($onlyNonZero) $sql .= " AND f.value > 0";
		$sql .= " ORDER BY f.value DESC, t.name";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('is', $characterId, $kind);
			$st->execute();
			$res = $st->get_result();
			if ($res) {
				while ($row = $res->fetch_assoc()) {
					$out[] = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'value'=>(int)$row['value']];
				}
				$res->free();
			}
			$st->close();
		}
		return $out;
	}

	// Cálculo de círculos de habilidad, atributos, etc.
	if (!function_exists('createSkillCircle')) {
		function createSkillCircle(array $array, string $prefix): array {
			$result = [];
			foreach ($array as $value) {
				$baseDir = ($prefix === 'gem-pwr') ? 'img/ui/gems/pwr' : 'img/ui/gems/attr';
				$result[] = "<img class='bioAttCircle' src='{$baseDir}/{$prefix}-0{$value}.png'/>";
			}
			return $result;
		}
	}

	function fetch_traits_for_system_type(mysqli $link, int $characterId, int $systemId, string $kind, bool $bridgeOnly = false): array {
		$out = [];
		if ($characterId <= 0) return $out;
		if ($bridgeOnly) {
			// Monster: mostrar solo lo realmente guardado en bridge_characters_traits,
			// pero respetando el orden del set del sistema cuando exista.
			if ($systemId > 0) {
				$sql = "SELECT t.id, t.name, f.value, s.sort_order, t.classification
						FROM bridge_characters_traits f
						JOIN dim_traits t ON t.id = f.trait_id AND t.kind = ?
						LEFT JOIN fact_trait_sets s
							ON s.trait_id = t.id
						   AND s.system_id = ?
						   AND s.is_active = 1
						WHERE f.character_id = ?
						ORDER BY
							CASE WHEN s.sort_order IS NULL THEN 1 ELSE 0 END,
							COALESCE(NULLIF(CAST(SUBSTRING_INDEX(TRIM(t.classification), ' ', 1) AS UNSIGNED), 0), 9999),
							s.sort_order,
							t.name";
				if ($st = $link->prepare($sql)) {
					$st->bind_param('sii', $kind, $systemId, $characterId);
					$st->execute();
					$res = $st->get_result();
					if ($res) {
						while ($row = $res->fetch_assoc()) {
							$out[] = [
								'id' => (int)$row['id'],
								'name' => (string)$row['name'],
								'value' => (int)$row['value'],
							];
						}
						$res->free();
					}
					$st->close();
				}
				if (!empty($out)) return $out;
			}
			// Fallback sin set del sistema: orden nominal.
			$sql = "SELECT t.id, t.name, f.value
					FROM bridge_characters_traits f
					JOIN dim_traits t ON t.id = f.trait_id
					WHERE f.character_id = ? AND t.kind = ?
					ORDER BY t.name";
			if ($st = $link->prepare($sql)) {
				$st->bind_param('is', $characterId, $kind);
				$st->execute();
				$res = $st->get_result();
				if ($res) {
					while ($row = $res->fetch_assoc()) {
						$out[] = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'value'=>(int)$row['value']];
					}
					$res->free();
				}
				$st->close();
			}
			return $out;
		}
		$hasSet = false;
		if ($systemId > 0) {
			$sql = "SELECT t.id, t.name, COALESCE(f.value,0) AS value, s.sort_order, t.classification
					FROM fact_trait_sets s
					JOIN dim_traits t ON t.id = s.trait_id AND t.kind = ?
					LEFT JOIN bridge_characters_traits f ON f.trait_id = t.id AND f.character_id = ?
					WHERE s.system_id = ? AND s.is_active = 1
					ORDER BY
						COALESCE(NULLIF(CAST(SUBSTRING_INDEX(TRIM(t.classification), ' ', 1) AS UNSIGNED), 0), 9999),
						s.sort_order,
						t.name";
			if ($st = $link->prepare($sql)) {
				$st->bind_param('sii', $kind, $characterId, $systemId);
				$st->execute();
				$res = $st->get_result();
				if ($res) {
					while ($row = $res->fetch_assoc()) {
						$out[] = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'value'=>(int)$row['value']];
					}
					$res->free();
				}
				$st->close();
			}
			if (!empty($out)) $hasSet = true;
		}

		if ($hasSet && $systemId > 0) {
			// Append traits with value>0 not present in the set
			$sql = "SELECT t.id, t.name, f.value
					FROM bridge_characters_traits f
					JOIN dim_traits t ON t.id = f.trait_id AND t.kind = ?
					WHERE f.character_id = ? AND f.value > 0
					AND t.id NOT IN (SELECT trait_id FROM fact_trait_sets WHERE system_id = ? AND is_active = 1)
					ORDER BY f.value DESC, t.name";
			if ($st = $link->prepare($sql)) {
				$st->bind_param('sii', $kind, $characterId, $systemId);
				$st->execute();
				$res = $st->get_result();
				if ($res) {
					while ($row = $res->fetch_assoc()) {
						$out[] = ['id'=>(int)$row['id'], 'name'=>(string)$row['name'], 'value'=>(int)$row['value']];
					}
					$res->free();
				}
				$st->close();
			}
			return $out;
		}

		// Fallback: only existing values
		return fetch_traits_by_type($link, $characterId, $kind, false);
	}

	function order_trait_list(array $list, int $baseCount = 0): array {
		if ($baseCount <= 0) return $list;
		$base = array_slice($list, 0, $baseCount);
		$extras = array_slice($list, $baseCount);
		usort($extras, function($a, $b){
			$an = strtolower((string)($a['name'] ?? ''));
			$bn = strtolower((string)($b['name'] ?? ''));
			return $an <=> $bn;
		});
		return array_merge($base, $extras);
	}

	function fetch_system_detail_labels(mysqli $link, int $systemId): array {
		$labels = [];
		if ($systemId <= 0) return $labels;

		$columns = [];
		if ($stCols = $link->prepare("SHOW COLUMNS FROM bridge_systems_detail_labels")) {
			$stCols->execute();
			$rsCols = $stCols->get_result();
			if ($rsCols) {
				while ($col = $rsCols->fetch_assoc()) {
					$name = (string)($col['Field'] ?? '');
					if ($name !== '') $columns[$name] = true;
				}
				$rsCols->free();
			}
			$stCols->close();
		}
		if (empty($columns) || !isset($columns['system_id'])) return $labels;

		$candidates = [
			'label_breed',
			'label_auspice',
			'label_pack',
			'label_tribe',
			'label_clan',
			'label_pk_name',
			'label_social',
			'label_misc',
		];

		$selectCols = [];
		foreach ($candidates as $c) {
			if (isset($columns[$c])) $selectCols[] = $c;
		}
		if (empty($selectCols)) return $labels;

		$sql = "SELECT " . implode(', ', $selectCols) . " FROM bridge_systems_detail_labels WHERE system_id = ? LIMIT 1";
		if ($st = $link->prepare($sql)) {
			$st->bind_param('i', $systemId);
			$st->execute();
			$res = $st->get_result();
			if ($res && ($row = $res->fetch_assoc())) {
				foreach ($selectCols as $c) {
					$v = trim((string)($row[$c] ?? ''));
					if ($v !== '') $labels[$c] = $v;
				}
			}
			$st->close();
		}
		return $labels;
	}

	$characterId = isset($_GET['b']) ? (int)$_GET['b'] : 0; // Cogemos datos del GET "b"
	if ($characterId <= 0) {
		echo "<p class='bio-error-msg'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
		exit;
	}
	$bioIsAdminFlag = bio_page_is_admin_flag_enabled();

	$deathTable = null;
	if ($rs = $link->query("SHOW TABLES LIKE 'fact_characters_deaths'")) {
		if ($rs->num_rows > 0) $deathTable = 'fact_characters_deaths';
		$rs->close();
	}
	if ($deathTable === null && ($rs = $link->query("SHOW TABLES LIKE 'fact_characters_death'"))) {
		if ($rs->num_rows > 0) $deathTable = 'fact_characters_death';
		$rs->close();
	}

	$deathJoin = '';
	if ($deathTable !== null) {
		$deathJoin = " LEFT JOIN `{$deathTable}` fd ON fd.character_id = p.id ";
	}
	$orderData = "SELECT p.*, s.name AS system_label, COALESCE(fd.death_description, '') AS death_description, COALESCE(fd.death_date, '') AS death_date
		FROM fact_characters p
		LEFT JOIN dim_systems s ON p.system_id = s.id
		{$deathJoin}
		WHERE p.id = ? LIMIT 1;"; // Elegimos al PJ de la Base de Datos
	$stmtMain = mysqli_prepare($link, $orderData);
	if (!$stmtMain) {
		echo "<p class='bio-error-msg'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
		exit;
	}

	mysqli_stmt_bind_param($stmtMain, 'i', $characterId);
	mysqli_stmt_execute($stmtMain);

	// Evitar dependencia de get_result()/mysqlnd
	$rowsMain = stmt_fetch_all_assoc_compat($stmtMain);
	$NFilas = count($rowsMain);

	if ($NFilas > 0) { // Comenzamos chequeo de datos. Si no tenemos nada, mandamos un mensaje de error.
		foreach ($rowsMain as $dataResult) {
		// Empezamos a recolectar los datos. ~~ #SEC01
		// ================================================================== //
		// Datos básicos del personaje
			$characterIdDb = $dataResult["id"];
			$bioId 		   = $characterIdDb;
 			// ID del personaje. Aunque la tengamos en el get, mejor así.
			$bioName 	 = $dataResult["name"]; 		// Nombre completo del personaje.
			$bioAlias 	 = $dataResult["alias"]; 		// Alias del personaje, como le llaman.
			$bioPackName = $dataResult["garou_name"]; 	// Nombre de manada. Como "Cláusula", "Churrasco", "Chili-Chingón", etc.
			$bioPhoto	 = hg_character_avatar_url($dataResult["image_url"] ?? '', $dataResult["gender"] ?? ''); 	// Imagen del personaje.
			$bioType	 = $dataResult["kind"] ?? $dataResult["character_type_id"] ?? 0; // Tipo de personaje.
			$bioBday	 = 'Desconocido'; // Se resuelve desde timeline en bio_page_section_01_data.php
			$bioConcept	 = $dataResult["concept"]; 		// Concepto del personaje.
			$bioNature	 = $dataResult["nature_id"]; 	// Naturaleza del personaje.
			$bioBehavior = $dataResult["demeanor_id"]; 	// Conducta del personaje.
			$bioText	 = $dataResult["info_text"]; 	// Texto escrito que habla sobre el personaje.
			$bioNotes	 = (string)($dataResult["notes"] ?? ''); // Notas internas (solo admin flag).
		// ================================================================== //
			$pageSect 	 = "Biograf&iacute;a";						// Para cambiar el titulo a la pagina.
			$pageTitle2	 = $bioName;						// Título de la Página
			setMetaFromPage($bioName . " | Personajes | Heaven's Gate", meta_excerpt($bioText), $bioPhoto, 'article');
			$titleInfo	 = "&nbsp;Informaci&oacute;n&nbsp;";		// Titulo de la seccion "Informacion"
			$titleId	 = "&nbsp;Detalles de $bioName&nbsp;";// Titulo de la seccion "Identificacion"
			$titleAttr	 = "&nbsp;Atributos&nbsp;";			// Titulo de la seccion "Atributos"
			$titleSkill	 = "&nbsp;Habilidades&nbsp;";		// Titulo de la seccion "Habilidades"
			$titleBackg	 = "&nbsp;Trasfondos&nbsp;";		// Titulo de la seccion "Trasfondos"
			$titleMerits = "&nbsp;M&eacute;ritos y Defectos&nbsp;";// Titulo de la seccion "Meritos y Defectos"
			$titleConditions = "&nbsp;Condiciones&nbsp;";		// Titulo de la seccion "Condiciones"
			$titleSocial = "&nbsp;Renombre&nbsp;"; 			// Titulo de la seccion "Social"
			$titleAdvant = "&nbsp;Estado&nbsp;";			// Titulo de la seccion "Estado"
			$titlePowers = "&nbsp;Poderes&nbsp;";			// Titulo de la seccion "Poderes"
			$titleItems	 = "&nbsp;Inventario&nbsp;";		// Titulo de la seccion "Inventario"
			$titleSameBio= "&nbsp;Relaciones de $bioName&nbsp;";// Título de la sección "Relaciones"
			$titleNebulo = "&nbsp;Nebulosa de relaciones&nbsp;";// Título de la sección "Nebulosa de relaciones"	
			$titleParticp= "&nbsp;Participaci&oacute;n&nbsp;";		// Titulo de la seccion "Participacion"		
		// ================================================================== //
		// Datos de jugador y crónica
			$bioPlayer	  = $dataResult["player_id"]; 	// Jugador al que pertenece el personaje.
			$bioChronic	  = $dataResult["chronicle_id"]; // Crónica a la que pertenece el personaje.
			$bioStatus	  = $dataResult["status"] ?? ""; 	// Estado legacy; puede no venir desde fact_characters.
			$bioDethCaus  = $dataResult["death_description"] ?? ""; // Causa de la muerte.
			$bioDeathDateRaw = (string)($dataResult["death_date"] ?? '');
			$bioDeathDisplay = '';
			$bioSheetRaw  = strtolower(trim((string)($dataResult["character_kind"] ?? $dataResult["kind"] ?? "")));
			$bioSheet	  = $bioSheetRaw; // Compatibilidad con código legacy.
			$bioIsMonster = in_array($bioSheetRaw, ["mon", "monster"], true);
			$bioHasSheet  = in_array($bioSheetRaw, ["pj", "mon", "monster"], true);
		// ================================================================== //
		// Datos de raza y alineamientos
			$bioRace	 = $dataResult["breed_id"]; 	// Raza a la que pertenece el personaje.
			$bioAuspice	 = $dataResult["auspice_id"]; 	// Auspicio al que pertenece el personaje.
			$bioTribe	 = $dataResult["tribe_id"]; 	// Tribu a la que pertenece el personaje.
			$bioRange	 = $dataResult["rank"]; 		// Rango de importancia del personaje en su organización.
		// ================================================================== //
		// Ventajas y poderes
			$bioTotem	 = ""; 		// Tótem que guí­a al personaje.
			$bioTotemId  = (int)($dataResult["totem_id"] ?? 0);
		// Género
			$bioGender	 = $dataResult["gender"];	// Género del personaje
		// Títulos de la sección Detalles		
			$titlePkName	= "Nombre Garou";		// Título del nombre Garou
		// Sistema, para nombres de detalles y tal.
			$bioSystem 	= (string)($dataResult["system_label"] ?? "");
			$bioSystemId = (int)($dataResult["system_id"] ?? 0);
			$systemDetailLabels = fetch_system_detail_labels($link, $bioSystemId);
		// Nombres de conceptos
			// ================================================================== //
			// Datos y nombre del sistema
			// IDENTIFICACION
			$titleBreed		= "Raza";
			$titleAuspice	= "Auspicio";
			$titlePack 		= "Manada";
			$titleTribe 	= "Tribu";
			$titleClan 		= "Clan";
			// ================================================================== //
			// Cambiamos títulos de secciones acorde al Sistema del PJ
			include ("app/partials/bio/bio_page_section_00_system.php"); // Utilizamos "include" para no sobrecargar la página con código
		// ================================================================== //
		if ($bioHasSheet) { // <--- Inicio de comprobación si lleva hoja
		// ================================================================== //
		// Traits normalizados (bridge_characters_traits)
			$traitValues = fetch_trait_values($link, (int)$characterId);

			// Mapa trait_id por columna legacy (mismo mapping que migración)
			$traitIdMap = [
				'fuerza' => 1,
				'destreza' => 33,
				'resistencia' => 34,
				'carisma' => 35,
				'manipulacion' => 36,
				'apariencia' => 37,
				'percepcion' => 38,
				'inteligencia' => 39,
				'astucia' => 40,
				'alerta' => 5,
				'atletismo' => 6,
				'callejeo' => 7,
				'empatia' => 8,
				'esquivar' => 9,
				'expresion' => 10,
				'impulsprimario' => 11,
				'intimidacion' => 12,
				'pelea' => 13,
				'subterfugio' => 14,
				'armascc' => 43,
				'armasdefuego' => 44,
				'conducir' => 45,
				'etiqueta' => 46,
				'interpretacion' => 47,
				'liderazgo' => 52,
				'reparaciones' => 18,
				'sigilo' => 49,
				'supervivencia' => 50,
				'tratoanimales' => 51,
				'ciencias' => 19,
				'enigmas' => 20,
				'informatica' => 55,
				'investigacion' => 56,
				'leyes' => 57,
				'linguistica' => 58,
				'medicina' => 59,
				'ocultismo' => 60,
				'politica' => 61,
				'rituales' => 21,
			];

			$traitVal = function(string $col) use ($traitIdMap, $traitValues, $dataResult): int {
				$tid = $traitIdMap[$col] ?? 0;
				if ($tid > 0 && isset($traitValues[$tid])) return (int)$traitValues[$tid];
				return (int)($dataResult[$col] ?? 0);
			};

		// Atributos
			$bioArrayAtt = array(
				// FISICOS
				$traitVal('fuerza'),
				$traitVal('destreza'),
				$traitVal('resistencia'),	
				// SOCIALES				
				$traitVal('carisma'),
				$traitVal('manipulacion'),
				$traitVal('apariencia'),
				// MENTALES				
				$traitVal('percepcion'),
				$traitVal('inteligencia'),
				$traitVal('astucia'),
			);
		// ================================================================== //
		// Habilidades
		$bioTraitsByType = [
			'Atributos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Atributos', $bioIsMonster),
			'Talentos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Talentos', $bioIsMonster),
			'Técnicas' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Técnicas', $bioIsMonster),
			'Conocimientos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Conocimientos', $bioIsMonster),
			'Trasfondos' => fetch_traits_for_system_type($link, (int)$characterId, (int)($dataResult['system_id'] ?? 0), 'Trasfondos', $bioIsMonster),
		];

		// Orden + extras al final (alfabético)
		$bioTraitsByType['Talentos'] = order_trait_list($bioTraitsByType['Talentos'] ?? [], 10);
		$bioTraitsByType['Técnicas'] = order_trait_list($bioTraitsByType['Técnicas'] ?? [], 10);
		$bioTraitsByType['Conocimientos'] = order_trait_list($bioTraitsByType['Conocimientos'] ?? [], 10);

		$bioTraitImgsByType = [];
		foreach ($bioTraitsByType as $tipo => $list) {
			$vals = array_map(fn($t) => (int)($t['value'] ?? 0), $list);
			$bioTraitImgsByType[$tipo] = createSkillCircle($vals, 'gem-attr');
		}

		$bioAttrList = $bioTraitsByType['Atributos'] ?? [];
		$bioDebugTraitsEnabled = isset($_GET['debug_traits']) && (string)$_GET['debug_traits'] === '1';
		if ($bioDebugTraitsEnabled && $bioIsMonster) {
			echo "<div class='bio-debug-box'>";
			echo "<strong>DEBUG TRAITS (monster)</strong><br>";
			echo "system_id=" . (int)($dataResult['system_id'] ?? 0) . "<br>";
			foreach ($bioAttrList as $ix => $t) {
				$nm = h((string)($t['name'] ?? ''));
				$tv = (int)($t['value'] ?? 0);
				echo "#" . ($ix + 1) . " {$nm} (v={$tv})<br>";
			}
			echo "</div>";
		}
		$bioAttrCols = [
			array_slice($bioAttrList, 0, 3),
			array_slice($bioAttrList, 3, 3),
			array_slice($bioAttrList, 6, 3),
		];
		$bioAttrColImgs = [
			createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $bioAttrCols[0]), 'gem-attr'),
			createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $bioAttrCols[1]), 'gem-attr'),
			createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $bioAttrCols[2]), 'gem-attr'),
		];

		$bioSkillCols = [
			'Talentos' => $bioTraitsByType['Talentos'] ?? [],
			'Técnicas' => $bioTraitsByType['Técnicas'] ?? [],
			'Conocimientos' => $bioTraitsByType['Conocimientos'] ?? [],
		];
		$bioSkillColImgs = [
			'Talentos' => $bioTraitImgsByType['Talentos'] ?? [],
			'Técnicas' => $bioTraitImgsByType['Técnicas'] ?? [],
			'Conocimientos' => $bioTraitImgsByType['Conocimientos'] ?? [],
		];

		$bioBackgrounds = $bioTraitsByType['Trasfondos'] ?? [];
		$bioBackVals = array_map(fn($t) => (int)($t['value'] ?? 0), $bioBackgrounds);
		$bioBackImgs = createSkillCircle($bioBackVals, 'gem-attr');

		// Legacy: construir array de habilidades fijo (30) + extras por tipo
		$bioArraySkiLegacy = [
			$traitVal('alerta'),
			$traitVal('atletismo'),
			$traitVal('callejeo'),
			$traitVal('empatia'),
			$traitVal('esquivar'),
			$traitVal('expresion'),
			$traitVal('impulsprimario'),
			$traitVal('intimidacion'),
			$traitVal('pelea'),
			$traitVal('subterfugio'),
			$traitVal('armascc'),
			$traitVal('armasdefuego'),
			$traitVal('conducir'),
			$traitVal('etiqueta'),
			$traitVal('interpretacion'),
			$traitVal('liderazgo'),
			$traitVal('reparaciones'),
			$traitVal('sigilo'),
			$traitVal('supervivencia'),
			$traitVal('tratoanimales'),
			$traitVal('ciencias'),
			$traitVal('enigmas'),
			$traitVal('informatica'),
			$traitVal('investigacion'),
			$traitVal('leyes'),
			$traitVal('linguistica'),
			$traitVal('medicina'),
			$traitVal('ocultismo'),
			$traitVal('politica'),
			$traitVal('rituales'),
		];
		$bioSkilImg = createSkillCircle($bioArraySkiLegacy, 'gem-attr');

		$talentoExtras = array_slice($bioTraitsByType['Talentos'] ?? [], 10);
		$tecnicaExtras = array_slice($bioTraitsByType['Técnicas'] ?? [], 10);
		$conociExtras = array_slice($bioTraitsByType['Conocimientos'] ?? [], 10);
		$bioExtraTalImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $talentoExtras), 'gem-attr');
		$bioExtraTecImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $tecnicaExtras), 'gem-attr');
		$bioExtraConImg = createSkillCircle(array_map(fn($t) => (int)($t['value'] ?? 0), $conociExtras), 'gem-attr');
// ================================================================== //
			}
	 	} // <---- Fin de comprobación si lleva hoja de PJ
		
		// ======================================================================================
		// Nueva preparación 2025. Tabla de relaciones.
		// ======================================================================================
		$relaciones = [];
		
		// Relaciones salientes
		$stmt1 = $link->prepare("SELECT cr.*, p2.name, p2.alias, p2.image_url, p2.gender, 'outgoing' as direction
								FROM bridge_characters_relations cr
								LEFT JOIN fact_characters p2 ON cr.target_id = p2.id
								WHERE cr.source_id = ?
								ORDER BY cr.relation_type");
		$stmt1->bind_param('i', $characterId);
		$stmt1->execute();
		$stm11_results = stmt_fetch_all_assoc_compat($stmt1);
		$relaciones = array_merge($relaciones, $stm11_results);

		// Relaciones entrantes
		$stmt2 = $link->prepare("SELECT cr.*, p2.name, p2.alias, p2.image_url, p2.gender, 'incoming' as direction
								FROM bridge_characters_relations cr
								LEFT JOIN fact_characters p2 ON cr.source_id = p2.id
								WHERE cr.target_id = ?
								ORDER BY cr.relation_type");
		$stmt2->bind_param('i', $characterId);
		$stmt2->execute();
		$stm12_results = stmt_fetch_all_assoc_compat($stmt2);
		$relaciones = array_merge($relaciones, $stm12_results);
		// Ordenar alfabéticamente por 'relation_type'
		usort($relaciones, function($a, $b) {
			return strcasecmp($a['relation_type'], $b['relation_type']);
		});
		
		$numRelaciones = count($relaciones);

		$killsAsKiller = [];
		if ($deathTable !== null) {
			$sqlKills = "SELECT
						fd.id AS death_id,
						fd.character_id AS victim_id,
						fd.death_type,
						fd.death_date,
						fd.death_description,
						fd.death_timeline_event_id,
						v.name AS victim_name,
						v.alias AS victim_alias,
						v.image_url AS victim_image,
						v.gender AS victim_gender,
						e.title AS event_title,
						e.event_date AS event_date
					FROM `{$deathTable}` fd
					INNER JOIN fact_characters v ON v.id = fd.character_id
					LEFT JOIN fact_timeline_events e ON e.id = fd.death_timeline_event_id
					WHERE fd.killer_character_id = ?
					  AND fd.character_id <> ?
					ORDER BY COALESCE(fd.death_date, e.event_date) DESC, fd.id DESC";
			if ($stKills = $link->prepare($sqlKills)) {
				$stKills->bind_param('ii', $characterId, $characterId);
				$stKills->execute();
				$killsAsKiller = stmt_fetch_all_assoc_compat($stKills);
				$stKills->close();
			}
		}
		$numKillsAsKiller = count($killsAsKiller);
		
		// ======================================================================================
		// Nueva preparación 2025. Participación del personaje.
		// ======================================================================================		
		$participacion = [];
		$hasSeasonKindBio = column_exists($link, 'dim_seasons', 'season_kind');
		$seasonKindExprBio = $hasSeasonKindBio
			? "COALESCE(at2.season_kind, 'temporada')"
			: "'temporada'";
		$stmtP = $link->prepare("SELECT ac.id, ac.name, ac.chapter_number, at2.name AS temporada_name, at2.season_number, {$seasonKindExprBio} AS season_kind, ac.played_date FROM dim_chapters ac
								INNER JOIN bridge_chapters_characters acp ON ac.id = acp.chapter_id 
								INNER JOIN dim_seasons at2 ON at2.id = ac.season_id
								WHERE acp.character_id = ?
								ORDER BY ac.played_date, ac.chapter_number");
		$stmtP->bind_param('i', $characterId);
		$stmtP->execute();
		$stmtP_results = stmt_fetch_all_assoc_compat($stmtP);
		$participacion = array_merge($participacion, $stmtP_results);
		
		$numParticipa = count($participacion);
		$numEventosParticipa = 0;
		if ($stPartEv = $link->prepare("SELECT COUNT(*) AS c FROM bridge_timeline_events_characters WHERE character_id = ?")) {
			$stPartEv->bind_param('i', $characterId);
			$stPartEv->execute();
			$resPartEv = $stPartEv->get_result();
			if ($resPartEv && ($rowPartEv = $resPartEv->fetch_assoc())) {
				$numEventosParticipa = (int)($rowPartEv['c'] ?? 0);
			}
			$stPartEv->close();
		}

		// ======================================================================================
		// Documentacion y enlaces externos del personaje
		// ======================================================================================
		$characterDocs = [];
		$characterExternalLinks = [];

		if (table_exists($link, 'bridge_characters_docs') && table_exists($link, 'fact_docs')) {
			$hasDocRelLabel = column_exists($link, 'bridge_characters_docs', 'relation_label');
			$hasDocSortOrder = column_exists($link, 'bridge_characters_docs', 'sort_order');
			$docRelExpr = $hasDocRelLabel ? 'COALESCE(b.relation_label, "")' : '""';
			$docSortExpr = $hasDocSortOrder ? 'COALESCE(b.sort_order, 0)' : '0';
			$docOrder = $hasDocSortOrder ? 'b.sort_order ASC, d.title ASC' : 'd.title ASC';

			$sqlDocLinks = "SELECT
							CONCAT(b.character_id, ':', b.doc_id) AS bridge_id,
							b.doc_id,
							{$docRelExpr} AS relation_label,
							{$docSortExpr} AS sort_order,
							d.title,
							d.pretty_id,
							COALESCE(c.kind, '') AS section_name
						FROM bridge_characters_docs b
						INNER JOIN fact_docs d ON d.id = b.doc_id
						LEFT JOIN dim_doc_categories c ON c.id = d.section_id
						WHERE b.character_id = ?
						ORDER BY {$docOrder}";
			if ($stDocLinks = $link->prepare($sqlDocLinks)) {
				$stDocLinks->bind_param('i', $characterId);
				$stDocLinks->execute();
				$characterDocs = stmt_fetch_all_assoc_compat($stDocLinks);
				$stDocLinks->close();
			}
		}

		if (table_exists($link, 'bridge_characters_external_links') && table_exists($link, 'fact_external_links')) {
			$hasExtRelLabel = column_exists($link, 'bridge_characters_external_links', 'relation_label');
			$hasExtSortOrder = column_exists($link, 'bridge_characters_external_links', 'sort_order');
			$extRelExpr = $hasExtRelLabel ? 'COALESCE(b.relation_label, "")' : '""';
			$extSortExpr = $hasExtSortOrder ? 'COALESCE(b.sort_order, 0)' : '0';
			$extOrder = $hasExtSortOrder ? 'b.sort_order ASC, l.title ASC' : 'l.title ASC';
			$hasExternalActive = column_exists($link, 'fact_external_links', 'is_active');
			$extActiveExpr = $hasExternalActive ? 'COALESCE(l.is_active, 1)' : '1';

			$sqlExternalLinks = "SELECT
								CONCAT(b.character_id, ':', b.external_link_id) AS bridge_id,
								b.external_link_id,
								{$extRelExpr} AS relation_label,
								{$extSortExpr} AS sort_order,
								l.title,
								l.url,
								l.kind,
								l.source_label,
								COALESCE(l.description, '') AS description,
								{$extActiveExpr} AS is_active
							FROM bridge_characters_external_links b
							INNER JOIN fact_external_links l ON l.id = b.external_link_id
							WHERE b.character_id = ?
							ORDER BY {$extOrder}";
			if ($stExternalLinks = $link->prepare($sqlExternalLinks)) {
				$stExternalLinks->bind_param('i', $characterId);
				$stExternalLinks->execute();
				$characterExternalLinks = stmt_fetch_all_assoc_compat($stExternalLinks);
				$stExternalLinks->close();
			}
		}
		$hasDocsLinks = (!empty($characterDocs) || !empty($characterExternalLinks));

		// Flags de secciones
		$hasInfo = true;
		$hasSheet = $bioHasSheet;
		$hasRel = ((isset($relaciones) && $numRelaciones > 0) || $numKillsAsKiller > 0);
		$hasPart = ((isset($participacion) && $numParticipa > 0) || $numEventosParticipa > 0);
		$characterComments = [];
		if ($stComments = $link->prepare("SELECT id, nick, comment_time, commented_at, message, ip, created_at FROM fact_characters_comments WHERE character_id = ? ORDER BY commented_at DESC, comment_time DESC, id DESC")) {
			$stComments->bind_param('i', $characterId);
			$stComments->execute();
			$characterComments = stmt_fetch_all_assoc_compat($stComments);
			$stComments->close();
		}
		$hasComments = !empty($characterComments);
		$hasBso = false;
		if ($stBso = $link->prepare("SELECT COUNT(*) AS c FROM bridge_soundtrack_links WHERE object_type = 'personaje' AND object_id = ?")) {
			$stBso->bind_param('i', $characterId);
			$stBso->execute();
			$resBso = $stBso->get_result();
			if ($resBso && ($rowBso = $resBso->fetch_assoc())) {
				$hasBso = ((int)$rowBso['c']) > 0;
			}
			$stBso->close();
		}
		
		// Hacemos un repaso a los datos y obtenemos los enlaces que corresponden
		// ----------------------------------------- //
		include ("app/partials/bio/bio_page_section_01_data.php"); // Utilizamos "include" para no sobrecargar la página con código
		// ----------------------------------------- //
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
				foreach (bio_export_fetch_merits($link, $characterId) as $row) {
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
			$resourcesByKindExport = bio_export_fetch_resources($link, $characterId, (int)($bioSystemId ?? 0));
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
			foreach (bio_export_fetch_conditions($link, $characterId) as $row) {
				$line = trim((string)($row['name'] ?? ''));
				if ($line === '') continue;
				$location = trim((string)($row['location'] ?? ''));
				$instanceNo = (int)($row['instance_no'] ?? 1);
				if ($location !== '') $line .= ' (' . $location . ')';
				elseif ($instanceNo > 1) $line .= ' #' . $instanceNo;
				$category = trim((string)($row['category'] ?? ''));
				if ($category !== '') $line .= ' [' . $category . ']';
				$bioExportConditions[] = $line;
			}
			bio_export_add_section($bioExportSections, 'CONDICIONES', $bioExportConditions);

			$bioExportPowers = [];
			$powersExport = bio_export_fetch_powers($link, $characterId);
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
			foreach (bio_export_fetch_items($link, $characterId) as $row) {
				$name = trim((string)($row['name'] ?? ''));
				if ($name === '') continue;
				$typeName = trim((string)($row['type_name'] ?? ''));
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
		/* MODERNO NUEVO */
		include("app/partials/main_nav_bar.php");	// Barra Navegación
		// ================================================================== //
		echo "<link rel='stylesheet' href='/assets/css/hg-bio.css'>";
		echo "<style>
			.bio-export-bar{display:flex;justify-content:flex-end;align-items:center;margin:12px 0 16px}
			.bio-export-btn{border:1px solid rgba(255,255,255,.18);background:#1f2b2f;color:#f5efe2;padding:10px 14px;border-radius:10px;cursor:pointer;font:inherit}
			.bio-export-btn:hover{background:#29383d}
			.bio-export-modal{position:fixed;inset:0;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;padding:20px;z-index:1200}
			.bio-export-modal.is-open{display:flex}
			.bio-export-modal__panel{width:min(980px,100%);max-height:min(85vh,900px);background:#11181b;border:1px solid rgba(255,255,255,.14);border-radius:14px;box-shadow:0 18px 60px rgba(0,0,0,.45);display:flex;flex-direction:column}
			.bio-export-modal__head,.bio-export-modal__foot{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 16px}
			.bio-export-modal__title{font-weight:700;color:#f5efe2}
			.bio-export-modal__body{padding:0 16px 16px}
			.bio-export-modal__ta{width:100%;min-height:56vh;resize:vertical;border:1px solid rgba(255,255,255,.12);border-radius:10px;background:#0a1012;color:#f3f0e7;padding:14px;font:13px/1.45 Consolas, Monaco, monospace}
			.bio-export-modal__actions{display:flex;gap:10px;flex-wrap:wrap}
			.bio-export-copy-state{color:#cfd8d3;font-size:12px;min-height:18px}
		</style>";

		echo "<div class='bioLayout'>";
		echo "<section class='bioContextHeader'>";
		echo "<div class='power-card power-card--bio'>";
		echo "  <div class='power-card__banner'><span class='power-card__title'>" . h($bioName) . "</span></div>";
		echo "  <div class='power-card__body'>";
		echo "    <div class='power-card__media'>";
		echo "      <div class='power-card__img-wrap'>";
		echo "        <img class='power-card__img' src='" . h($bioPhoto) . "' alt='" . h($bioName) . "'/>";
		echo "      </div>";
		echo "    </div>";
		echo "    <div class='power-card__stats'>";
		include ("app/partials/bio/bio_page_section_03_details.php"); // Detalles básicos (contexto fijo)
		echo "    </div>";
		echo "  </div>";
		echo "</div>";
		echo "</section>";

		// Config de iconos de tabs BIO (16x16).
		// Cambia solo las rutas de este bloque cuando tengas los iconos definitivos.
		$bioTabIconDefault = '/img/ui/icons/icon_character_sheet.png';
		$bioTabIcons = [
			// Keys validas: info, sheet, rel, part, docs, bso, comments
			'default'  => $bioTabIconDefault, // Fallback global
			'info'     => '/img/ui/icons/icon_character_info.png',
			'sheet'    => '/img/ui/icons/icon_character_sheet.png',
			'rel'      => '/img/ui/icons/icon_character_relationships.png',
			'part'     => '/img/ui/icons/icon_character_participation.png',
			'docs'     => '/img/ui/icons/icon_document.png',
			'bso'      => '/img/ui/icons/icon_character_music.png',
			'comments' => '/img/ui/icons/icon_character_comments.png',
			'export'   => '',
		];

		// Fallback por si alguna ruta llega vacia.
		foreach (['info', 'sheet', 'rel', 'part', 'docs', 'bso', 'comments'] as $k) {
			if (!isset($bioTabIcons[$k]) || trim((string)$bioTabIcons[$k]) === '') {
				$bioTabIcons[$k] = $bioTabIconDefault;
			}
		}

		$renderBioTab = function (string $tabKey, string $labelHtml) use ($bioTabIcons) {
			$iconHtml = '';
			if ($tabKey === 'export') {
				$iconHtml = "&#128203;";
			} else {
				$icon = trim((string)($bioTabIcons[$tabKey] ?? $bioTabIcons['default'] ?? ''));
				if ($icon !== '') {
				$iconHtml = "<img class='hgTabIcon' src='" . h($icon) . "' alt='' width='16' height='16' loading='lazy' decoding='async'>";
				}
			}
			echo "<button class='hgTabBtn' data-tab='" . h($tabKey) . "'><span class='hgTabEmoji' aria-hidden='true'>" . $iconHtml . "</span><span class='hgTabLabel'>" . $labelHtml . "</span></button>";
		};

		echo "<div class='hg-tabs'>";
		if ($hasInfo) $renderBioTab('info', 'Informaci&oacute;n');
		if ($hasSheet) $renderBioTab('sheet', 'Hoja de personaje');
		if ($hasRel) $renderBioTab('rel', 'Relaciones');
		if ($hasPart) $renderBioTab('part', 'Participaci&oacute;n');
		if ($hasDocsLinks) $renderBioTab('docs', 'Documentaci&oacute;n');
		if ($hasBso) $renderBioTab('bso', 'Banda sonora');
		if ($hasComments) $renderBioTab('comments', 'Comentarios');
		$renderBioTab('export', 'Exportar');
		echo "</div>";

	echo "<div class='bioBody'>"; // CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		// ================================================================== //
		echo "<section id='sec-info' class='bio-tab-panel' data-tab='info'>";
		// ================================================================== //
		if ($bioText != "") { // Empezamos colocando la información de Texto
			echo "<div class='bioTextData'>"; 
				echo "<fieldset class='bioSeccion'><legend>$titleInfo</legend>$bioText</fieldset>";
			echo "</div>";
		} // Finalizamos de poner el Texto
		if ($bioIsAdminFlag && trim($bioNotes) !== '') {
			echo "<div class='bioTextData'>";
				echo "<fieldset class='bioSeccion'><legend>&nbsp;Notas internas (admin)&nbsp;</legend><div class='bioAdminNotes'>" . nl2br(h($bioNotes)) . "</div></fieldset>";
			echo "</div>";
		}

		echo "</section>";
		// ================================================================== //
		// BANDA SONORA
		if ($hasBso) {
			echo "<section id='sec-bso' class='bio-tab-panel' data-tab='bso'>";
			include("app/partials/snippet_bso_card.php");
			mostrarTarjetaBSO($link, 'personaje', $characterId);
			echo "</section>";
		}
		// ================================================================== //
		if ($bioHasSheet) { // Comprobamos si el personaje dispone de Hoja
			// ----
			echo "<section id='sec-sheet' class='bio-tab-panel' data-tab='sheet'>";
			echo "<div class='bioSheetData'>"; // Parte Superior de la Hoja ~~ #SEC04
			echo "<fieldset class='bioSeccion'><legend>$titleId</legend>";
				// ----------------------------------------- //
				include ("app/partials/bio/bio_page_section_04_sheetup.php"); // Utilizamos "include" para no sobrecargar la página con código
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Parte Superior ~~
			// ================================================================== //
			echo "<div class='bioSheetData'>"; // Atributos de la Hoja ~~ #SEC05
			echo "<fieldset class='bioSeccion'><legend>$titleAttr</legend>";
				// ----------------------------------------- //
				include ("app/partials/bio/bio_page_section_05_attributes.php"); // Utilizamos "include" para no sobrecargar la página con código
				// ----------------------------------------- //
			echo "</fieldset>";
			echo "</div>"; // Cerramos Atributos ~~
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_06_skills.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
		if (!$bioIsMonster) {
			echo "<div class='bioSheetBackgrounds'>"; // Trasfondos de la Hoja ~~ #SEC07
				echo "<fieldset class='bioSeccion'><legend>$titleBackg</legend>";
					if (!empty($bioBackgrounds)) {
						foreach ($bioBackgrounds as $idx => $bg) {
							$tid = (int)($bg['id'] ?? 0);
							$nm = (string)($bg['name'] ?? '');
							$val = (int)($bg['value'] ?? 0);
							if ($nm === '' || $val <= 0) continue;
							$nameHtml = h($nm);
							if ($tid > 0 && function_exists('pretty_url')) {
								$hrefT = pretty_url($link, 'dim_traits', '/rules/traits', $tid);
								$nameHtml = "<a href='" . h($hrefT) . "' target='_blank' class='hg-tooltip' data-tip='trait' data-id='" . $tid . "'>" . h($nm) . "</a>";
							}
							echo"<div class='bioSheetBackgroundLeft'>" . $nameHtml . ":</div>";
							$img = $bioBackImgs[$idx] ?? '';
							echo"<div class='bioSheetBackgroundRight'>" . $img . "</div>";
						}
					}
				echo "</fieldset>";
			echo "</div>"; // Cerramos Trasfondos ~~
			// ================================================================== //
			// MÉRITOS Y DEFECTOS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_08_merits.php"); // Utilizamos "include" para no sobrecargar la página con código
		}
			// ================================================================== //
			// RECURSOS DEL PERSONAJE
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_07_resources.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
			// CONDICIONES DEL PERSONAJE
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_09_conditions.php"); // Condiciones del personaje, estilo inventario
			// ================================================================== //
			// PODERES, DONES, RITUALES Y DISCIPLINAS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_11_power.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
			// INVENTARIO Y OBJETOS
			// ================================================================== //
			include ("app/partials/bio/bio_page_section_13_items.php"); // Utilizamos "include" para no sobrecargar la página con código
			// ================================================================== //
			echo "</section>";
		} // Finalizamos la Hoja de Personaje
		?>
		
		<?php if ($hasRel): ?>
			<section id="sec-rel" class="bio-tab-panel" data-tab="rel">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend><?= ($titleSameBio) ?></legend>
					<button id="toggleRelaciones" class="boton2 bio-rel-toggle" type="button">Cambiar vista</button>
					<div id="seccion2">
						<?php include("app/partials/bio/bio_page_section_17_rel_graph.php"); ?>
					</div>
					<?php include("app/partials/bio/bio_page_section_20_kills.php"); ?>
					<div id="seccion1" class="bio-hidden">
						<?php include("app/partials/bio/bio_page_section_14_family.php"); ?>
					</div>
				</fieldset>
			</div>
			</section>

		<?php endif; ?>
		
		<?php if ($hasPart): ?>
			<section id="sec-part" class="bio-tab-panel" data-tab="part">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend>&nbsp;<?= ($titleParticp) ?>&nbsp;</legend>
					<?php include("app/partials/bio/bio_page_section_18_chapters.php"); ?>
					<?php include("app/partials/bio/bio_page_section_19_participation.php"); ?>
				</fieldset>
			</div>
			</section>
		<?php endif; ?>
		<?php if ($hasDocsLinks): ?>
			<section id="sec-docs" class="bio-tab-panel" data-tab="docs">
			<div class="bioTextData">
				<fieldset class='bioSeccion'>
					<legend>&nbsp;Documentaci&oacute;n y enlaces&nbsp;</legend>
					<?php include("app/partials/bio/bio_page_section_21_docs_links.php"); ?>
				</fieldset>
			</div>
			</section>
		<?php endif; ?>
		<?php if ($hasComments): ?>
			<section id="sec-comments" class="bio-tab-panel" data-tab="comments">
				<?php include("app/partials/bio/bio_page_section_15_comments.php"); ?>
			</section>
		<?php endif; ?>
		<?php
	echo "</div>"; // FIN DE CUERPO PRINCIPAL DE LA FICHA DE INFORMACION
		echo "<div class='bio-export-modal' id='bioExportModal' aria-hidden='true'>";
		echo "  <div class='bio-export-modal__panel' role='dialog' aria-modal='true' aria-labelledby='bioExportTitle'>";
		echo "    <div class='bio-export-modal__head'><div class='bio-export-modal__title' id='bioExportTitle'>Exportación en texto plano</div><button type='button' class='bio-export-btn' id='bioExportCloseTop'>Cerrar</button></div>";
		echo "    <div class='bio-export-modal__body'><textarea readonly class='bio-export-modal__ta' id='bioExportTextarea'>" . h($bioPlainExportText) . "</textarea></div>";
		echo "    <div class='bio-export-modal__foot'><div class='bio-export-copy-state' id='bioExportCopyState'></div><div class='bio-export-modal__actions'><button type='button' class='bio-export-btn' id='bioExportCopy'>Copiar todo</button><button type='button' class='bio-export-btn' id='bioExportCloseBottom'>Cerrar</button></div></div>";
		echo "  </div>";
		echo "</div>";
		echo "</div>"; // bioLayout
	} else {
		echo "<p class='bio-error-msg'>$mensajeDeError</p>"; // Mensaje de error en caso de introducir datos manualmente. Tomado del Cuerpo Trabajar
	}

	// Limpieza del stmt principal
	if (isset($stmt) && $stmt instanceof mysqli_stmt) {
		@mysqli_stmt_close($stmt);
	}
?>

<script>
	document.addEventListener('DOMContentLoaded', () => {
		const tabs = Array.from(document.querySelectorAll('.hgTabBtn, .bioTabBtn'));
		const panels = Array.from(document.querySelectorAll('.bio-tab-panel'));
		if (typeof HGBindHoverSound === 'function') {
			HGBindHoverSound('.hgTabBtn, .bioTabBtn', '/sounds/ui/hover.ogg');
		}
		function activate(tabKey){
			panels.forEach(p => {
				p.classList.toggle('active', p.dataset.tab === tabKey);
			});
			tabs.forEach(b => {
				b.classList.toggle('active', b.dataset.tab === tabKey);
			});
			if (tabKey === 'rel') {
				setTimeout(() => {
					try {
						if (typeof window.__bioRelNetworkRefresh === 'function') {
							window.__bioRelNetworkRefresh();
						} else {
							if (window.__bioRelNetwork && typeof window.__bioRelNetwork.fit === 'function') {
								window.__bioRelNetwork.fit({ animation: { duration: 200, easingFunction: 'easeInOutQuad' } });
							}
							if (window.__bioRelNetwork && typeof window.__bioRelNetwork.redraw === 'function') {
								window.__bioRelNetwork.redraw();
							}
						}
					} catch (e) {}
				}, 60);
			}
		}
		if (tabs.length) activate(tabs[0].dataset.tab);

		tabs.forEach(b => {
			b.addEventListener('click', () => {
				if (b.dataset.tab === 'export') return;
				activate(b.dataset.tab);
			});
		});

		document.querySelectorAll('.bioSideNav a[data-tab]').forEach(a => {
			a.addEventListener('click', (e) => {
				const tab = a.dataset.tab;
				if (tab) activate(tab);
			});
		});
	});

	document.addEventListener('DOMContentLoaded', () => {
		const btnToggle = document.getElementById('toggleRelaciones');
		const seccion1 = document.getElementById('seccion1');
		const seccion2 = document.getElementById('seccion2');
		const seccion3 = document.getElementById('seccion3');
		const seccion4 = document.getElementById('seccion4');

		// Si no existe el botón (porque no hay relaciones), no hacemos nada
		if (!btnToggle || !seccion1 || !seccion2) return;

		btnToggle.addEventListener('click', () => {
			const hidden = window.getComputedStyle(seccion1).display === 'none';
			if (hidden) {
				seccion1.style.display = 'block';
				seccion2.style.display = 'none';
			} else {
				seccion1.style.display = 'none';
				seccion2.style.display = 'block';
			}
			// Recalcular tamaño/redibujar vis-network si existe
			try {
				if (typeof window.__bioRelNetworkRefresh === 'function') {
					window.__bioRelNetworkRefresh();
				} else if (window.__bioRelNetwork && typeof window.__bioRelNetwork.fit === 'function') {
					window.__bioRelNetwork.fit({ animation: { duration: 300, easingFunction: 'easeInOutQuad' } });
				}
			} catch (e) {}
		});
	});

	document.addEventListener('DOMContentLoaded', () => {
		const modal = document.getElementById('bioExportModal');
		const openBtn = document.querySelector('.hgTabBtn[data-tab="export"], .bioTabBtn[data-tab="export"]');
		const closeTop = document.getElementById('bioExportCloseTop');
		const closeBottom = document.getElementById('bioExportCloseBottom');
		const copyBtn = document.getElementById('bioExportCopy');
		const textarea = document.getElementById('bioExportTextarea');
		const state = document.getElementById('bioExportCopyState');
		if (!modal || !openBtn || !textarea) return;

		const setState = (text) => {
			if (state) state.textContent = text || '';
		};
		const openModal = () => {
			modal.classList.add('is-open');
			modal.setAttribute('aria-hidden', 'false');
			setTimeout(() => {
				textarea.focus();
				textarea.select();
			}, 30);
		};
		const closeModal = () => {
			modal.classList.remove('is-open');
			modal.setAttribute('aria-hidden', 'true');
			setState('');
		};

		openBtn.addEventListener('click', openModal);
		if (closeTop) closeTop.addEventListener('click', closeModal);
		if (closeBottom) closeBottom.addEventListener('click', closeModal);
		modal.addEventListener('click', (e) => {
			if (e.target === modal) closeModal();
		});
		document.addEventListener('keydown', (e) => {
			if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal();
		});
		if (copyBtn) {
			copyBtn.addEventListener('click', async () => {
				const text = String(textarea.value || '');
				if (!text) return;
				try {
					if (navigator.clipboard && navigator.clipboard.writeText) {
						await navigator.clipboard.writeText(text);
					} else {
						textarea.focus();
						textarea.select();
						document.execCommand('copy');
					}
					setState('Texto copiado.');
				} catch (err) {
					textarea.focus();
					textarea.select();
					setState('No se pudo copiar. Usa Ctrl+C.');
				}
			});
		}
	});
</script>
	<script>
		document.addEventListener('DOMContentLoaded', () => {
			if (window.__hgTooltipBound) return;
			const tooltip = document.createElement('div');
			tooltip.id = 'hg-tooltip';
			document.body.appendChild(tooltip);

			const cache = new Map();
			let timer = null;
			let currentKey = '';
			let lastX = 0, lastY = 0;

			function moveTip(x, y){
				const pad = 14;
				const tw = tooltip.offsetWidth || 320;
				const th = tooltip.offsetHeight || 120;
				let left = x + pad;
				let top = y + pad;
				if (left + tw > window.innerWidth) left = x - tw - pad;
				if (top + th > window.innerHeight) top = y - th - pad;
				tooltip.style.left = left + 'px';
				tooltip.style.top = top + 'px';
			}

			function hideTip(){
				tooltip.style.display = 'none';
				tooltip.innerHTML = '';
				currentKey = '';
			}

			document.querySelectorAll('.hg-tooltip').forEach(el => {
				el.addEventListener('mousemove', (e) => {
					lastX = e.clientX;
					lastY = e.clientY;
					if (tooltip.style.display === 'block') moveTip(lastX, lastY);
				});

				el.addEventListener('mouseenter', (e) => {
					lastX = e.clientX;
					lastY = e.clientY;
					const type = el.getAttribute('data-tip') || '';
					const id = el.getAttribute('data-id') || '';
					if (!type || !id) return;
					const key = type + ':' + id;
					currentKey = key;
					if (cache.has(key)) {
						tooltip.innerHTML = cache.get(key);
						tooltip.style.display = 'block';
						moveTip(lastX, lastY);
						return;
					}
					timer = setTimeout(async () => {
						if (currentKey !== key) return;
						try {
							const res = await fetch(`/ajax/tooltip?type=${encodeURIComponent(type)}&id=${encodeURIComponent(id)}`);
							const html = await res.text();
							if (currentKey !== key) return;
							cache.set(key, html);
							tooltip.innerHTML = html;
							tooltip.style.display = 'block';
							moveTip(lastX, lastY);
						} catch (err) {
							// silencioso
						}
					}, 900);
				});

				el.addEventListener('mouseleave', () => {
					if (timer) clearTimeout(timer);
					timer = null;
					hideTip();
				});
			});
		});
	</script>
	<script>
		document.addEventListener('click', async (event) => {
			const btn = event.target.closest('.js-copy-roll');
			if (!btn) return;
			const text = String(btn.getAttribute('data-copy') || '');
			if (!text) return;
			const old = btn.innerHTML;
			try {
				if (navigator.clipboard && navigator.clipboard.writeText) {
					await navigator.clipboard.writeText(text);
				} else {
					const ta = document.createElement('textarea');
					ta.value = text;
					document.body.appendChild(ta);
					ta.select();
					document.execCommand('copy');
					ta.remove();
				}
				btn.innerHTML = '&#9989;';
			} catch (e) {
				btn.innerHTML = '&#10060;';
			}
			setTimeout(() => { btn.innerHTML = old; }, 1400);
		});
	</script>
