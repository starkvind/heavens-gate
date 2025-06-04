<?php
	session_start();

	include '../error_reporting.php';
	include 'config.php';
	include '../security.php';

	include 'get_pwd.php';

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
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="utf-8">
			<title>Acceso a administración</title>
			<style>
				body { font-family: sans-serif; padding: 50px; background-color: #f5f5f5; text-align: center; }
				form { display: inline-block; padding: 20px; background: white; border: 1px solid #ccc; border-radius: 6px; }
				input[type="password"] {
					padding: 6px;
					font-size: 16px;
					width: 200px;
				}
				button {
					padding: 6px 12px;
					font-size: 16px;
					margin-top: 10px;
					cursor: pointer;
				}
				.error { color: red; margin-bottom: 10px; }
			</style>
			<link rel="stylesheet" href="style.css">
		</head>
		<body>

		<h2>🔐 Acceso restringido</h2>
		<?php if (isset($error)): ?>
			<div class="error"><?php echo htmlspecialchars($error); ?></div>
		<?php endif; ?>
		<form method="post">
			<label>Introduce la contraseña:</label><br><br>
			<input type="password" name="admin_pass" required><br><br>
			<button type="submit">Entrar</button>
		</form>

		</body>
		</html>
		<?php
		exit;
	}

	// Actualizar colores
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_colors'])) {
		foreach ($_POST['color'] as $id => $color) {
			$stmt = $pdo->prepare("UPDATE nuevo2_clanes SET color = ? WHERE id = ?");
			$stmt->execute([$color, $id]);
		}
		header("Location: admin_clanes.php?updated=1");
		exit;
	}

	// Añadir nuevo clan
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_clan'])) {
		$name = $_POST['name'];
		$sistema = $_POST['sistema'];
		$desc = $_POST['desc'];
		$color = $_POST['color'] ?: '#eeeeee';

		$stmt = $pdo->prepare("INSERT INTO nuevo2_clanes (name, sistema, `desc`, color, orden, pnj, totem) VALUES (?, ?, ?, ?, ?, ?, ?)");
		$stmt->execute([$name, $sistema, $desc, $color, 999, 1, 0]);

		header("Location: admin_clanes.php?added=1");
		exit;
	}

	$clanes = $pdo->query("SELECT * FROM nuevo2_clanes ORDER BY orden ASC")->fetchAll();
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>Administración de Clanes</title>
		<style>
			body { font-family: sans-serif; padding: 20px; }
			table { border-collapse: collapse; width: 100%; margin-bottom: 40px; }
			th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
			button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
			input[type="text"], textarea, select {
				width: 100%;
				padding: 5px;
				box-sizing: border-box;
			}
			.color-input {
				width: 60px;
				height: 30px;
				border: none;
				padding: 0;
			}
			.success { background-color: #d4edda; color: #155724; padding: 10px; border: 1px solid #c3e6cb; margin-bottom: 20px; }
		</style>
		<link rel="stylesheet" href="style.css">
	</head>
	<body>
	<h2>🛠️ Administración de Clanes</h2>
	<?php if (isset($_GET['updated'])): ?>
		<div class="success">Colores actualizados correctamente.</div>
	<?php endif; ?>
	<?php if (isset($_GET['added'])): ?>
		<div class="success">Nuevo clan añadido correctamente.</div>
	<?php endif; ?>

	<form method="post">
		<table>
			<tr>
				<th>Nombre</th>
				<th>Sistema</th>
				<th>Color</th>
			</tr>
			<?php foreach ($clanes as $clan): ?>
			<tr>
				<td><?= htmlspecialchars($clan['name']) ?></td>
				<td><?= htmlspecialchars($clan['sistema']) ?></td>
				<td>
					<input type="color" class="color-input" name="color[<?= $clan['id'] ?>]" value="<?= htmlspecialchars($clan['color'] ?? '#eeeeee') ?>">
				</td>
			</tr>
			<?php endforeach; ?>
		</table>
		
		<button type="submit" name="update_colors">Guardar cambios de color</button>
		<br><br>
	</form>

	<hr>

		<h3>➕ Añadir nuevo clan</h3>
		<form method="post">
			<label>Nombre del clan:</label>
			<input type="text" name="name" required><br><br>

			<label>Sistema (Garou, Vampiro, etc.):</label>
			<input type="text" name="sistema" required><br><br>

			<label>Descripción:</label>
			<textarea name="desc" rows="3"></textarea><br><br>

			<label>Color:</label>
			<input type="color" name="color" value="#eeeeee"><br><br>

			<button type="submit" name="add_clan">Añadir clan</button>
			<br><br>
		</form>

		<hr />
		<br />

		<?php include("footer.php"); ?>
	</body>
</html>
