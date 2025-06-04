<?php
	session_start();

	include '../error_reporting.php';
	include 'config.php';
	include '../security.php';

	include 'get_pwd.php';

	// Obtener y descifrar contraseña de administrador
	$stmt = $pdo->prepare("SELECT config_value FROM configuracion_web WHERE config_name = 'rel_pwd' LIMIT 1");
	$stmt->execute();
	$row = $stmt->fetch();

	if (!$row) {
		die("Error: no se pudo cargar la contraseña.");
	}

	$adminPassword = decrypt_string($row['config_value']);

// Verificar sesión o mostrar formulario de login
if (!isset($_SESSION['is_admin'])) {
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_pass'])) {
        if ($_POST['admin_pass'] === $adminPassword) {
            $_SESSION['is_admin'] = true;
            header("Location: admin_relaciones.php");
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    }
	?>
	<!DOCTYPE html><html><head><meta charset="utf-8"><title>Acceso</title>
	<style>body{font-family:sans-serif;padding:50px;background:#f5f5f5;text-align:center;}
	form{display:inline-block;padding:20px;background:white;border:1px solid #ccc;border-radius:6px;}
	input[type="password"],button{padding:6px 12px;font-size:16px;margin-top:10px;width:200px;}
	.error{color:red;margin-bottom:10px;}</style></head>
	<body><h2>🔐 Acceso restringido</h2>
	<?php if (isset($error)) echo "<div class='error'>" . htmlspecialchars($error) . "</div>"; ?>
	<form method="post"><label>Introduce la contraseña:</label><br><br>
	<input type="password" name="admin_pass" required><br><br>
	<button type="submit">Entrar</button></form></body></html><?php exit;
}

// Eliminar relación
if (isset($_GET['delete'])) {
	$id = intval($_GET['delete']);
	$pdo->prepare("DELETE FROM character_relations WHERE id = ?")->execute([$id]);
	header("Location: admin_relaciones.php?deleted=1");
	exit;
}

// Crear nueva relación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_relation'])) {
	$stmt = $pdo->prepare("INSERT INTO character_relations (source_id, target_id, relation_type, tag, importance, description, arrows) VALUES (?, ?, ?, ?, ?, ?, ?)");
	$stmt->execute([
		$_POST['new']['source_id'],
		$_POST['new']['target_id'],
		$_POST['new']['relation_type'],
		$_POST['new']['tag'],
		$_POST['new']['importance'] ?? 0,
		$_POST['new']['description'] ?? '',
		$_POST['new']['arrows'] ?? ''
	]);
	header("Location: admin_relaciones.php?added=1");
	exit;
}

// Guardar relaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_changes'])) {
	foreach ($_POST['relation'] as $id => $data) {
		$stmt = $pdo->prepare("UPDATE character_relations SET source_id = ?, target_id = ?, relation_type = ?, tag = ?, importance = ?, description = ?, arrows = ? WHERE id = ?");
		$stmt->execute([
			$data['source_id'], $data['target_id'], $data['relation_type'],
			$data['tag'], $data['importance'], $data['description'], $data['arrows'], $id
		]);
	}
	header("Location: admin_relaciones.php?saved=1");
	exit;
}

// Datos
	$limit = 25;
	$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
	$offset = ($page - 1) * $limit;
	$total = $pdo->query("SELECT COUNT(*) FROM character_relations")->fetchColumn();
	$totalPages = ceil($total / $limit);

	$personajes = $pdo->query("SELECT id, nombre FROM pjs1 WHERE cronica NOT IN (2, 7) ORDER BY nombre")->fetchAll(PDO::FETCH_KEY_PAIR);
	$relaciones = $pdo->prepare("SELECT * FROM character_relations ORDER BY source_id ASC LIMIT $limit OFFSET $offset");
	$relaciones->execute();
	$relaciones = $relaciones->fetchAll();

	$tipos = ['Amigo','Aliado','Mentor','Protegido','Salvador','Amante','Pareja','Rival','Traidor','Extorsionador','Enemigo','Asesino','Padre','Madre','Hijo','Hermano','Abuelo','Tío','Primo','Superior','Subordinado','Amo','Creador','Vínculo'];
	$tags  = ['amistad','conflicto','familia','alianza','otro'];
	$arrows = ["to" => "➡️ Origen → Destino","from" => "⬅️ Destino → Origen","to,from" => "🔁 Doble dirección","" => "🚫 Sin flechas"];
?>

<!DOCTYPE html>
<html>
	<head>
	<meta charset="utf-8">
	<title>Editar relaciones</title>
	<style>
		body{font-family:sans-serif;padding:20px;}
		table{border-collapse:collapse;width:100%;font-size:13px;}
		th,td{border:1px solid #ccc;padding:6px;text-align:left;vertical-align:top;}
		select,input[type="number"],textarea{width:100%;box-sizing:border-box;font-size:12px;}
		.success{background:#d4edda;color:#155724;padding:10px;border:1px solid #c3e6cb;margin-bottom:20px;}
		a.del{color:red;font-weight:bold;text-decoration:none;}
		a.page{padding:4px 8px;display:inline-block;margin-right:4px;text-decoration:none;border:1px solid #ccc;}
		a.page.active{background:#ccc;}
		#popupForm{display:none;padding:15px;border:1px solid #ccc;background:#fafafa;margin-top:10px;}
		button.toggle{margin-top:15px;margin-bottom:10px;}
	</style>
	<link rel="stylesheet" href="style.css">
	<script>
	function togglePopupForm() {
		const popup = document.getElementById("popupForm");
		const currentDisplay = window.getComputedStyle(popup).display;
		popup.style.display = (currentDisplay === "none") ? "block" : "none";
	}

	</script>
</head>
	<body>
		<h2>🔧 Edición de relaciones</h2>
		<?php 
		if (isset($_GET['saved'])) echo "<div class='success'>Relaciones actualizadas correctamente.</div>";
		if (isset($_GET['deleted'])) echo "<div class='success'>Relación eliminada.</div>";
		if (isset($_GET['added'])) echo "<div class='success'>Nueva relación creada.</div>";
		?>
	<form method="post">
		<table>
			<tr><th>ID</th><th>Origen</th><th>Destino</th><th>Tipo</th><th>Tag</th><th>Flechas</th><th>Descripción</th><th></th>
			</tr>
			<?php foreach ($relaciones as $r): ?>
			<tr>
			<td><?= $r['id'] ?></td>
			<td><select name="relation[<?= $r['id'] ?>][source_id]">
			<?php foreach ($personajes as $id => $n): ?><option value="<?= $id ?>" <?= $id == $r['source_id'] ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option><?php endforeach; ?>
			</select></td>
			<td><select name="relation[<?= $r['id'] ?>][target_id]">
			<?php foreach ($personajes as $id => $n): ?><option value="<?= $id ?>" <?= $id == $r['target_id'] ? 'selected' : '' ?>><?= htmlspecialchars($n) ?></option><?php endforeach; ?>
			</select></td>
			<td><select name="relation[<?= $r['id'] ?>][relation_type]">
			<?php foreach ($tipos as $t): ?><option value="<?= $t ?>" <?= $r['relation_type'] == $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?>
			</select></td>
			<td><select name="relation[<?= $r['id'] ?>][tag]">
			<?php foreach ($tags as $t): ?><option value="<?= $t ?>" <?= $r['tag'] == $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option><?php endforeach; ?>
			</select></td>
			<td><select name="relation[<?= $r['id'] ?>][arrows]">
			<?php foreach ($arrows as $val => $label): ?><option value="<?= $val ?>" <?= $r['arrows'] == $val ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?>
			</select></td>
			<input type="hidden" name="relation[<?= $r['id'] ?>][importance]" value="<?= $r['importance'] ?>" />
			<td><textarea name="relation[<?= $r['id'] ?>][description]" rows="2"><?= htmlspecialchars($r['description']) ?></textarea></td>
			<td><a href="?delete=<?= $r['id'] ?>" class="del" onclick="return confirm('Eliminar relación?')">❌</a></td>
			</tr>
			<?php endforeach; ?>
		</table>
	<br />
	</form>
	<button type="submit" name="save_changes">📀 Guardar relaciones</button>
	<button class="toggle" onclick="togglePopupForm()">📅 Crear nueva relación</button>
	<div id="popupForm">
		<h3>➕ Nueva relación</h3>
		<form method="post">
			<input type="hidden" name="new_relation" value="1">
			<table>
				<tr><td>Origen:</td><td><select name="new[source_id]">
				<?php foreach ($personajes as $id => $n): ?><option value="<?= $id ?>"><?= htmlspecialchars($n) ?></option><?php endforeach; ?>
				</select></td></tr>
				<tr><td>Destino:</td><td><select name="new[target_id]">
				<?php foreach ($personajes as $id => $n): ?><option value="<?= $id ?>"><?= htmlspecialchars($n) ?></option><?php endforeach; ?>
				</select></td></tr>
				<tr><td>Tipo:</td><td><select name="new[relation_type]">
				<?php foreach ($tipos as $t): ?><option value="<?= $t ?>"><?= $t ?></option><?php endforeach; ?>
				</select></td></tr>
				<tr><td>Tag:</td><td><select name="new[tag]">
				<?php foreach ($tags as $t): ?><option value="<?= $t ?>"><?= ucfirst($t) ?></option><?php endforeach; ?>
				</select></td></tr>
				<tr><td>Flechas:</td><td><select name="new[arrows]">
				<?php foreach ($arrows as $val => $label): ?><option value="<?= $val ?>"><?= $label ?></option><?php endforeach; ?>
				</select></td></tr>
				<tr><td>Importancia:</td><td><input type="number" name="new[importance]" min="0" max="10" value="1"></td></tr>
				<tr><td>Descripción:</td><td><textarea name="new[description]" rows="2"></textarea></td></tr>
			</table>
			<br />
			<button type="submit">➕ Crear relación</button>
		</form>
	</div>

	<!-- Paginación -->
	<div style="margin-top:20px;">
	<?php for ($i = 1; $i <= $totalPages; $i++): ?>
		<a class="page <?= $i == $page ? 'active' : '' ?>" href="?page=<?= $i ?>"><?= $i ?></a>
	<?php endfor; ?>
	</div>

	<br />
	<?php include("footer.php"); ?>
	</body>
</html>
