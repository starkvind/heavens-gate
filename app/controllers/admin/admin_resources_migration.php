<?php
// admin_resources_migration.php
// Crea tablas de recursos por personaje y permite backfill (dry-run o ejecucion).

if (!isset($link) || !($link instanceof mysqli)) {
	die("DB no disponible.");
}

if (!function_exists('h')) {
	function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function table_exists_admin(mysqli $link, string $table): bool {
	$st = $link->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
	if (!$st) return false;
	$st->bind_param('s', $table);
	$st->execute();
	$st->bind_result($c);
	$st->fetch();
	$st->close();
	return ((int)$c > 0);
}

function table_has_column_admin(mysqli $link, string $table, string $col): bool {
	$st = $link->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
	if (!$st) return false;
	$st->bind_param('ss', $table, $col);
	$st->execute();
	$st->bind_result($c);
	$st->fetch();
	$st->close();
	return ((int)$c > 0);
}

function resolve_resource_value_from_legacy(array $resourceRow, array $charRow): array {
	$kind = strtolower((string)($resourceRow['kind'] ?? ''));
	$sort = (int)($resourceRow['sort_order'] ?? 0);
	$name = strtolower((string)($resourceRow['name'] ?? ''));

	$raw = null;
	if ($kind === 'renombre') {
		if ($sort === 1) $raw = (int)($charRow['glory_points'] ?? 0);
		elseif ($sort === 2) $raw = (int)($charRow['honor_points'] ?? 0);
		elseif ($sort === 3) $raw = (int)($charRow['wisdom_points'] ?? 0);
	} elseif ($kind === 'estado') {
		if ($sort === 1) {
			$raw = (int)($charRow['rage_points'] ?? 0);
		} elseif ($sort === 2) {
			// En legacy solo existe una columna comun para energia secundaria.
			$raw = (int)($charRow['gnosis_points'] ?? 0);
		} elseif ($sort === 3) {
			$raw = (int)($charRow['fvp'] ?? 0);
		}
	} elseif ($kind === 'exp' || strpos($name, 'experiencia') !== false) {
		$raw = (int)($charRow['xp_points'] ?? 0);
	}

	if ($raw === null) $raw = 0;

	// Requisito solicitado: todos los recursos tienen permanente+temporal.
	// En legacy no existe PX total, solo restante, asi que se copia en ambos.
	$perm = $raw;
	$temp = $raw;

	return [$perm, $temp];
}

echo "<h2>Migracion de Recursos de Personajes</h2>";

$msg = [];
$err = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_tables'])) {
	$sql1 = "
		CREATE TABLE IF NOT EXISTS bridge_characters_system_resources (
			id INT(11) NOT NULL AUTO_INCREMENT,
			character_id INT(100) NOT NULL,
			resource_id INT(11) NOT NULL,
			value_permanent INT(11) NOT NULL DEFAULT 0,
			value_temporary INT(11) NOT NULL DEFAULT 0,
			created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uq_char_resource (character_id, resource_id),
			KEY idx_bcsr_character (character_id),
			KEY idx_bcsr_resource (resource_id),
			CONSTRAINT fk_bcsr_character FOREIGN KEY (character_id) REFERENCES fact_characters(id) ON DELETE CASCADE,
			CONSTRAINT fk_bcsr_resource FOREIGN KEY (resource_id) REFERENCES dim_systems_resources(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
	";

	$sql2 = "
		CREATE TABLE IF NOT EXISTS bridge_characters_system_resources_log (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			character_id INT(100) NOT NULL,
			resource_id INT(11) NOT NULL,
			old_permanent INT(11) DEFAULT NULL,
			new_permanent INT(11) DEFAULT NULL,
			old_temporary INT(11) DEFAULT NULL,
			new_temporary INT(11) DEFAULT NULL,
			delta_permanent INT(11) DEFAULT NULL,
			delta_temporary INT(11) DEFAULT NULL,
			reason VARCHAR(255) DEFAULT NULL,
			source VARCHAR(50) NOT NULL,
			created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_by VARCHAR(80) DEFAULT NULL,
			PRIMARY KEY (id),
			KEY idx_bcsrl_character (character_id),
			KEY idx_bcsrl_resource (resource_id),
			KEY idx_bcsrl_source_created (source, created_at),
			CONSTRAINT fk_bcsrl_character FOREIGN KEY (character_id) REFERENCES fact_characters(id) ON DELETE CASCADE,
			CONSTRAINT fk_bcsrl_resource FOREIGN KEY (resource_id) REFERENCES dim_systems_resources(id) ON DELETE CASCADE
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
	";

	if (!$link->query($sql1)) $err[] = "Error creando bridge_characters_system_resources: " . $link->error;
	if (!$link->query($sql2)) $err[] = "Error creando bridge_characters_system_resources_log: " . $link->error;
	if (empty($err)) $msg[] = "Tablas creadas/verificadas correctamente.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['backfill_dryrun']) || isset($_POST['backfill_execute']))) {
	$isDryRun = isset($_POST['backfill_dryrun']);
	$showAllDryRun = isset($_POST['show_all']);
	$writeLog = isset($_POST['write_log']);
	$actor = trim((string)($_POST['created_by'] ?? 'admin'));

	if (!table_exists_admin($link, 'dim_systems_resources')) $err[] = "Falta dim_systems_resources.";
	if (!table_exists_admin($link, 'bridge_systems_resources_to_system')) $err[] = "Falta bridge_systems_resources_to_system.";
	if (!table_exists_admin($link, 'fact_characters')) $err[] = "Falta fact_characters.";
	if (!$isDryRun && !table_exists_admin($link, 'bridge_characters_system_resources')) $err[] = "Falta bridge_characters_system_resources (creala primero).";
	if (!$isDryRun && $writeLog && !table_exists_admin($link, 'bridge_characters_system_resources_log')) $err[] = "Falta bridge_characters_system_resources_log (creala primero).";

	if (empty($err)) {
		$hasActive = table_has_column_admin($link, 'bridge_systems_resources_to_system', 'is_active');
		$bridgeSql = "
			SELECT b.system_id, r.id AS resource_id, r.name, r.kind, r.sort_order
			FROM bridge_systems_resources_to_system b
			INNER JOIN dim_systems_resources r ON r.id = b.resource_id
		";
		if ($hasActive) $bridgeSql .= " WHERE b.is_active = 1";

		$mapBySystem = [];
		if ($res = $link->query($bridgeSql)) {
			while ($row = $res->fetch_assoc()) {
				$sid = (int)$row['system_id'];
				if (!isset($mapBySystem[$sid])) $mapBySystem[$sid] = [];
				$mapBySystem[$sid][] = $row;
			}
			$res->free();
		}

		$chars = [];
		$charSql = "
			SELECT id, name, system_id, glory_points, honor_points, wisdom_points, rage_points, gnosis_points, fvp, xp_points
			FROM fact_characters
			WHERE LOWER(TRIM(character_kind)) = 'pj'
		";
		if ($res = $link->query($charSql)) {
			while ($row = $res->fetch_assoc()) $chars[] = $row;
			$res->free();
		}

		$rows = [];
		foreach ($chars as $c) {
			$cid = (int)$c['id'];
			$sid = (int)$c['system_id'];
			$resources = $mapBySystem[$sid] ?? [];
			foreach ($resources as $r) {
				[$perm, $temp] = resolve_resource_value_from_legacy($r, $c);
				$rows[] = [
					'character_id' => $cid,
					'character_name' => (string)($c['name'] ?? ''),
					'resource_id' => (int)$r['resource_id'],
					'resource_name' => (string)($r['name'] ?? ''),
					'value_permanent' => $perm,
					'value_temporary' => $temp,
				];
			}
		}

		$msg[] = "Backfill calculado: " . count($rows) . " filas (personaje-recurso).";

		if ($isDryRun) {
			$sample = $showAllDryRun ? $rows : array_slice($rows, 0, 20);
			echo "<div class='bioTextData'><fieldset class='bioSeccion'><legend>Dry-run</legend>";
			echo "<p>No se ha escrito nada en BD.</p>";
			if (!empty($sample)) {
				echo "<table style='width:100%;border-collapse:collapse;'><tr><th style='border:1px solid #003399;padding:4px;'>character_id</th><th style='border:1px solid #003399;padding:4px;'>personaje</th><th style='border:1px solid #003399;padding:4px;'>resource_id</th><th style='border:1px solid #003399;padding:4px;'>recurso</th><th style='border:1px solid #003399;padding:4px;'>value_permanent</th><th style='border:1px solid #003399;padding:4px;'>value_temporary</th></tr>";
				foreach ($sample as $s) {
					echo "<tr>";
					echo "<td style='border:1px solid #003399;padding:4px;'>" . (int)$s['character_id'] . "</td>";
					echo "<td style='border:1px solid #003399;padding:4px;'>" . h($s['character_name']) . "</td>";
					echo "<td style='border:1px solid #003399;padding:4px;'>" . (int)$s['resource_id'] . "</td>";
					echo "<td style='border:1px solid #003399;padding:4px;'>" . h($s['resource_name']) . "</td>";
					echo "<td style='border:1px solid #003399;padding:4px;'>" . (int)$s['value_permanent'] . "</td>";
					echo "<td style='border:1px solid #003399;padding:4px;'>" . (int)$s['value_temporary'] . "</td>";
					echo "</tr>";
				}
				echo "</table>";
				if (!$showAllDryRun && count($rows) > 20) {
					echo "<p style='font-size:11px;color:#9fc9ff;'>Mostrando 20 primeras filas de " . (int)count($rows) . ".</p>";
					echo "<form method='post' style='margin-top:8px;'>";
					if ($writeLog) echo "<input type='hidden' name='write_log' value='1'>";
					echo "<input type='hidden' name='created_by' value='" . h($actor) . "'>";
					echo "<input type='hidden' name='backfill_dryrun' value='1'>";
					echo "<input type='hidden' name='show_all' value='1'>";
					echo "<button type='submit' class='boton2'>Mostrar todas las filas</button>";
					echo "</form>";
				} elseif ($showAllDryRun) {
					echo "<p style='font-size:11px;color:#9fc9ff;'>Mostrando todas las filas: " . (int)count($rows) . ".</p>";
				}
			}
			echo "</fieldset></div>";
		} else {
			$link->begin_transaction();
			try {
				$stUpsert = $link->prepare("
					INSERT INTO bridge_characters_system_resources
						(character_id, resource_id, value_permanent, value_temporary)
					VALUES (?, ?, ?, ?)
					ON DUPLICATE KEY UPDATE
						value_permanent = VALUES(value_permanent),
						value_temporary = VALUES(value_temporary),
						updated_at = CURRENT_TIMESTAMP
				");
				if (!$stUpsert) throw new RuntimeException("No se pudo preparar UPSERT de estado.");

				$stLog = null;
				if ($writeLog) {
					$stLog = $link->prepare("
						INSERT INTO bridge_characters_system_resources_log
							(character_id, resource_id, old_permanent, new_permanent, old_temporary, new_temporary, delta_permanent, delta_temporary, reason, source, created_by)
						VALUES (?, ?, NULL, ?, NULL, ?, NULL, NULL, ?, 'migration_init', ?)
					");
					if (!$stLog) throw new RuntimeException("No se pudo preparar INSERT de log.");
				}

				$affected = 0;
				foreach ($rows as $r) {
					$cid = (int)$r['character_id'];
					$rid = (int)$r['resource_id'];
					$perm = (int)$r['value_permanent'];
					$temp = (int)$r['value_temporary'];

					$stUpsert->bind_param('iiii', $cid, $rid, $perm, $temp);
					$stUpsert->execute();
					$affected++;

					if ($stLog) {
						$reason = 'Initial import from fact_characters legacy columns';
						$stLog->bind_param('iiiiss', $cid, $rid, $perm, $temp, $reason, $actor);
						$stLog->execute();
					}
				}

				$stUpsert->close();
				if ($stLog) $stLog->close();

				$link->commit();
				$msg[] = "Backfill ejecutado: $affected filas upsert.";
				if ($writeLog) $msg[] = "Log insertado con source='migration_init'.";
				$msg[] = "Nota: XP total no existe en legacy; se ha copiado xp_points a permanente y temporal.";
			} catch (Throwable $e) {
				$link->rollback();
				$err[] = "Error en backfill: " . $e->getMessage();
			}
		}
	}
}
?>

<style>
.mig-box { margin-bottom: 14px; }
.mig-note { font-size: 11px; color: #9fc9ff; }
</style>

<?php if (!empty($msg)): ?>
<div class="bioTextData mig-box"><fieldset class="bioSeccion"><legend>Resultado</legend>
	<?php foreach ($msg as $m): ?><div><?= h($m) ?></div><?php endforeach; ?>
</fieldset></div>
<?php endif; ?>

<?php if (!empty($err)): ?>
<div class="bioTextData mig-box"><fieldset class="bioSeccion"><legend>Errores</legend>
	<?php foreach ($err as $e): ?><div style="color:#ff9c9c;"><?= h($e) ?></div><?php endforeach; ?>
</fieldset></div>
<?php endif; ?>

<div class="bioTextData mig-box">
	<fieldset class="bioSeccion">
		<legend>&nbsp;1) Crear tablas destino&nbsp;</legend>
		<form method="post">
			<button type="submit" name="create_tables" value="1" class="boton2">Crear/Verificar tablas</button>
		</form>
		<div class="mig-note">Crea: bridge_characters_system_resources y bridge_characters_system_resources_log.</div>
	</fieldset>
</div>

<div class="bioTextData mig-box">
	<fieldset class="bioSeccion">
		<legend>&nbsp;2) Backfill desde fact_characters&nbsp;</legend>
		<form method="post">
			<label><input type="checkbox" name="write_log" value="1" checked> Escribir log de migracion</label>
			&nbsp;&nbsp;
			<label>created_by: <input type="text" name="created_by" value="admin" style="width:160px;"></label>
			<br><br>
			<button type="submit" name="backfill_dryrun" value="1" class="boton2">Dry-run</button>
			<button type="submit" name="backfill_execute" value="1" class="boton2">Ejecutar backfill</button>
		</form>
		<div class="mig-note">
			Dry-run no escribe en BD. Ejecucion usa UPSERT por (character_id, resource_id).<br>
			Para XP: como legacy solo tiene <code>xp_points</code>, se copia a permanente y temporal.
		</div>
	</fieldset>
</div>
