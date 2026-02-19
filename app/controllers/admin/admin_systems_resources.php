<?php
// admin_systems_resources.php
if (!isset($link) || !($link instanceof mysqli)) {
	die("DB no disponible.");
}

if (!function_exists('h')) {
	function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

function table_exists(mysqli $link, string $table): bool {
	$st = $link->prepare("SELECT COUNT(*) AS c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
	if (!$st) return false;
	$st->bind_param('s', $table);
	$st->execute();
	$res = $st->get_result();
	$row = $res ? $res->fetch_assoc() : null;
	$st->close();
	return ((int)($row['c'] ?? 0) > 0);
}

function table_columns(mysqli $link, string $table): array {
	$out = [];
	$sql = "SHOW COLUMNS FROM `" . str_replace("`", "``", $table) . "`";
	if ($res = $link->query($sql)) {
		while ($r = $res->fetch_assoc()) {
			$out[(string)$r['Field']] = true;
		}
		$res->free();
	}
	return $out;
}

$tblResources = 'dim_systems_resources';
$tblBridge = 'bridge_systems_resources_to_system';
$tblSystems = 'dim_systems';

$hasResources = table_exists($link, $tblResources);
$hasBridge = table_exists($link, $tblBridge);
$hasSystems = table_exists($link, $tblSystems);
$bridgeCols = $hasBridge ? table_columns($link, $tblBridge) : [];

echo "<h2>Recursos por Sistema</h2>";

if (!$hasSystems || !$hasResources || !$hasBridge) {
	echo "<div class='bioTextData'><fieldset class='bioSeccion'><legend>Estado</legend>";
	echo "<p>Faltan tablas para este m&oacute;dulo:</p><ul>";
	if (!$hasSystems) echo "<li><code>dim_systems</code></li>";
	if (!$hasResources) echo "<li><code>dim_systems_resources</code></li>";
	if (!$hasBridge) echo "<li><code>bridge_systems_resources_to_system</code></li>";
	echo "</ul>";
	echo "<p>Cuando existan, este panel permitir&aacute; mapear recursos a cada sistema.</p>";
	echo "</fieldset></div>";
	return;
}

$systemId = max(0, (int)($_GET['system_id'] ?? $_POST['system_id'] ?? 0));
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $systemId > 0) {
	$rows = $_POST['rows'] ?? [];
	$selected = $_POST['selected'] ?? [];
	$selectedMap = [];
	foreach ($selected as $rid) {
		$rid = (int)$rid;
		if ($rid > 0) $selectedMap[$rid] = true;
	}

	$keepRows = [];
	foreach ($rows as $rid => $row) {
		$rid = (int)$rid;
		if ($rid <= 0 || !isset($selectedMap[$rid])) continue;
		$keepRows[$rid] = [
			'sort_order' => (int)($row['sort_order'] ?? 0),
			'is_active'  => isset($row['is_active']) ? 1 : 0,
			'position'   => trim((string)($row['position'] ?? '')),
		];
	}

	$link->begin_transaction();
	try {
		$del = $link->prepare("DELETE FROM `$tblBridge` WHERE system_id = ?");
		$del->bind_param('i', $systemId);
		$del->execute();
		$del->close();

		$insertCols = ['system_id', 'resource_id'];
		$hasSort = isset($bridgeCols['sort_order']);
		$hasActive = isset($bridgeCols['is_active']);
		$hasPosition = isset($bridgeCols['position']);
		if ($hasSort) $insertCols[] = 'sort_order';
		if ($hasActive) $insertCols[] = 'is_active';
		if ($hasPosition) $insertCols[] = 'position';

		$placeholders = implode(',', array_fill(0, count($insertCols), '?'));
		$sqlIns = "INSERT INTO `$tblBridge` (" . implode(',', $insertCols) . ") VALUES ($placeholders)";
		$ins = $link->prepare($sqlIns);
		if (!$ins) throw new RuntimeException("No se pudo preparar INSERT en $tblBridge");

		foreach ($keepRows as $rid => $row) {
			$sortOrder = (int)$row['sort_order'];
			$isActive = (int)$row['is_active'];
			$position = (string)$row['position'];

			if ($hasSort && $hasActive && $hasPosition) {
				$ins->bind_param('iiiis', $systemId, $rid, $sortOrder, $isActive, $position);
			} elseif ($hasSort && $hasActive && !$hasPosition) {
				$ins->bind_param('iiii', $systemId, $rid, $sortOrder, $isActive);
			} elseif ($hasSort && !$hasActive && $hasPosition) {
				$ins->bind_param('iiis', $systemId, $rid, $sortOrder, $position);
			} elseif (!$hasSort && $hasActive && $hasPosition) {
				$ins->bind_param('iiis', $systemId, $rid, $isActive, $position);
			} elseif ($hasSort && !$hasActive && !$hasPosition) {
				$ins->bind_param('iii', $systemId, $rid, $sortOrder);
			} elseif (!$hasSort && $hasActive && !$hasPosition) {
				$ins->bind_param('iii', $systemId, $rid, $isActive);
			} elseif (!$hasSort && !$hasActive && $hasPosition) {
				$ins->bind_param('iis', $systemId, $rid, $position);
			} else {
				$ins->bind_param('ii', $systemId, $rid);
			}

			$ins->execute();
		}
		$ins->close();

		$link->commit();
		$msg = "Guardado correctamente.";
	} catch (Throwable $e) {
		$link->rollback();
		$msg = "Error al guardar: " . h($e->getMessage());
	}
}

$systems = [];
if ($res = $link->query("SELECT id, name FROM `$tblSystems` ORDER BY sort_order, name")) {
	while ($r = $res->fetch_assoc()) $systems[] = $r;
	$res->free();
}

$resources = [];
if ($res = $link->query("SELECT id, name, kind, sort_order, description FROM `$tblResources` ORDER BY kind, sort_order, name")) {
	while ($r = $res->fetch_assoc()) $resources[] = $r;
	$res->free();
}

$current = [];
if ($systemId > 0) {
	$selCols = ['resource_id'];
	if (isset($bridgeCols['sort_order'])) $selCols[] = 'sort_order';
	if (isset($bridgeCols['is_active'])) $selCols[] = 'is_active';
	if (isset($bridgeCols['position'])) $selCols[] = 'position';
	$sqlCur = "SELECT " . implode(',', $selCols) . " FROM `$tblBridge` WHERE system_id = ?";
	$st = $link->prepare($sqlCur);
	$st->bind_param('i', $systemId);
	$st->execute();
	$rs = $st->get_result();
	while ($r = $rs->fetch_assoc()) {
		$rid = (int)$r['resource_id'];
		$current[$rid] = [
			'sort_order' => (int)($r['sort_order'] ?? 0),
			'is_active'  => (int)($r['is_active'] ?? 1),
			'position'   => (string)($r['position'] ?? ''),
		];
	}
	$st->close();
}
?>
<style>
.asr-box { margin-bottom: 14px; }
.asr-grid { width:100%; border-collapse: collapse; }
.asr-grid th, .asr-grid td { border:1px solid #003399; padding:6px 8px; }
.asr-grid th { background:#001a66; color:#aee7ff; }
.asr-muted { color:#8fb4ff; font-size:11px; }
</style>

<div class="bioTextData asr-box">
	<fieldset class="bioSeccion">
		<legend>&nbsp;Sistema objetivo&nbsp;</legend>
		<form method="get">
			<input type="hidden" name="p" value="talim">
			<input type="hidden" name="s" value="admin_systems_resources">
			<select name="system_id" onchange="this.form.submit()">
				<option value="0">-- Selecciona sistema --</option>
				<?php foreach ($systems as $s): ?>
					<option value="<?= (int)$s['id'] ?>" <?= ($systemId === (int)$s['id']) ? 'selected' : '' ?>>
						<?= h($s['name']) ?> (#<?= (int)$s['id'] ?>)
					</option>
				<?php endforeach; ?>
			</select>
		</form>
		<?php if ($msg !== ''): ?><p><b><?= $msg ?></b></p><?php endif; ?>
	</fieldset>
</div>

<?php if ($systemId > 0): ?>
<div class="bioTextData">
	<fieldset class="bioSeccion">
		<legend>&nbsp;Recursos enlazados&nbsp;</legend>
		<form method="post">
			<input type="hidden" name="system_id" value="<?= (int)$systemId ?>">
			<table class="asr-grid">
				<thead>
					<tr>
						<th>Usar</th>
						<th>Recurso</th>
						<th>Kind</th>
						<th>Orden</th>
						<?php if (isset($bridgeCols['position'])): ?><th>Posición</th><?php endif; ?>
						<th>Activo</th>
						<th>Descripción</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ($resources as $r):
					$rid = (int)$r['id'];
					$isLinked = isset($current[$rid]);
					$curSort = $isLinked ? (int)$current[$rid]['sort_order'] : (int)$r['sort_order'];
					$curActive = $isLinked ? (int)$current[$rid]['is_active'] : 1;
					$curPos = $isLinked ? (string)$current[$rid]['position'] : '';
				?>
					<tr>
						<td><input type="checkbox" name="selected[]" value="<?= $rid ?>" <?= $isLinked ? 'checked' : '' ?>></td>
						<td><?= h($r['name']) ?> <span class="asr-muted">#<?= $rid ?></span></td>
						<td><?= h($r['kind']) ?></td>
						<td><input type="number" name="rows[<?= $rid ?>][sort_order]" value="<?= $curSort ?>" style="width:70px;"></td>
						<?php if (isset($bridgeCols['position'])): ?>
							<td><input type="text" name="rows[<?= $rid ?>][position]" value="<?= h($curPos) ?>" placeholder="main / reputation"></td>
						<?php endif; ?>
						<td><input type="checkbox" name="rows[<?= $rid ?>][is_active]" value="1" <?= $curActive ? 'checked' : '' ?>></td>
						<td><?= h((string)($r['description'] ?? '')) ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<div style="margin-top:10px;">
				<button type="submit" class="boton2">Guardar mapeo</button>
			</div>
		</form>
	</fieldset>
</div>
<?php endif; ?>
