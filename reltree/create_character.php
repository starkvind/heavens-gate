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
            header("Location: create_character.php");
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
				.error { color: red; margin-bottom: 10px; }</style>
		</head>
		<body>
			<h2>🔐 Acceso restringido</h2>
			<?php if (isset($error)): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
			<form method="post"><label>Introduce la contraseña:</label><br><br>
				<input type="password" name="admin_pass" required><br><br>
				<button type="submit">Entrar</button>
			</form>
		</body>
	</html>
    <?php exit;
}
// Subida e inserción
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nombre'])) {
    $imgPath = null;

	if (!isset($_FILES['img']) || $_FILES['img']['error'] !== UPLOAD_ERR_OK) {
		$error = "❌ Debes subir una imagen del personaje.";
	} else {
        $tmp = $_FILES['img']['tmp_name'];
        $check = getimagesize($tmp);
        if ($check !== false && ($check[0] >= 100 || $check[0] <= 125) && ($check[1] >= 100 || $check[1] <= 125)) {
            $ext = pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . "." . $ext;
            $dest = "../img/subidas/" . $filename;
            if (move_uploaded_file($tmp, $dest)) {
                $imgPath = "img/subidas/" . $filename;
            }
        } else {
            $error = "❌ La imagen debe ser de 125x125 píxeles.";
        }
    }

    if (!isset($error)) {
        $stmt = $pdo->prepare("INSERT INTO pjs1 
        (nombre, alias, nombregarou, estado, kes, sistema, clan, manada, cronica, concepto, notas, cumple, tipo, raza, auspicio, tribu, infotext, img, genero_pj)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $_POST['nombre'],
            $_POST['alias'],
            $_POST['nombregarou'],
            $_POST['estado'],
            $_POST['kes'],
            $_POST['sistema'],
            $_POST['clan'],
            $_POST['manada'],
            $_POST['concepto'],
            $_POST['notas'],
            $_POST['cumple'] ?: 'Desconocido',
			$_POST['tipo'],
			$_POST['raza'],
			$_POST['auspicio'],
			$_POST['tribu'],
			$_POST['infotext'],
            $imgPath,
			$_POST['genero_pj'],
        ]);

        $success = "✅ Personaje creado correctamente.";
    }
}
$sistemas = $pdo->query("SELECT DISTINCT sistema FROM pjs1 WHERE sistema IS NOT NULL AND sistema != '' ORDER BY sistema")->fetchAll();
$clanes = $pdo->query("SELECT id, name FROM nuevo2_clanes ORDER BY orden")->fetchAll();
$manadas = $pdo->query("SELECT id, name, clan FROM nuevo2_manadas ORDER BY name")->fetchAll();
$razas = $pdo->query("SELECT id, name, sistema FROM nuevo_razas ORDER BY name")->fetchAll();
$auspicios = $pdo->query("SELECT id, name, sistema FROM nuevo_auspicios ORDER BY name")->fetchAll();
$tribus = $pdo->query("SELECT id, name, sistema FROM nuevo_tribus ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html>
	<head>
		<meta charset="utf-8">
		<title>Crear personaje</title>
		<style>
			body { font-family: sans-serif; padding: 30px; }
			h2 { text-align: center; }
			form { max-width: 600px; margin: auto; background: #f8f8f8; padding: 20px; border-radius: 8px; }
			input, select, textarea { width: 100%; padding: 8px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 4px; }
			button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
			.success { color: green; text-align: center; margin-bottom: 15px; }
			.error { color: red; text-align: center; margin-bottom: 15px; }
		</style>
		<link rel="stylesheet" href="style.css">
	</head>
	<body>
	<h2>➕ Crear nuevo personaje</h2>
	<?php if (isset($success)): ?><div class="success"><?= $success ?></div><?php endif; ?>
	<?php if (isset($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>

	<form method="post" enctype="multipart/form-data">
		<label>Nombre:</label>
		<input type="text" name="nombre" required>

		<label>Alias:</label>
		<input type="text" name="alias" required>

		<label>Nombre Garou:</label>
		<input type="text" name="nombregarou">
		
		<label>Concepto:</label>
		<input type="text" name="concepto" required>
		
		<label>Cumpleaños (opcional):</label>
		<input type="text" name="cumple" placeholder="Ej. 12/06/1992 o Desconocido">

		<label>Estado:</label>
		<select name="estado" required>
			<option value="En activo">En activo</option>
			<option value="Cadáver">Cadáver</option>
			<option value="Paradero desconocido">Paradero desconocido</option>
			<option value="Aún por aparecer">Aún por aparecer</option>
		</select>

	<!--    <label>Tipo (pj / pnj):</label>
		<select name="kes" required>
			<option value="pj">PJ</option>
			<option value="pnj">PNJ</option>
		</select>
	-->
		<input type="hidden" name="kes" value="pnj"/>
		
		<label>Género:</label>
		<select name="genero_pj" required>
			<option>-</option>
			<option value="m">Masculino</option>
			<option value="f">Femenino</option>
			<option value="i">Indeterminado</option>
		</select>
		
		<label>Sistema:</label>
		<select name="sistema" required>
			<option value="0">-</option>
			<?php foreach ($sistemas as $s): ?>
				<option value="<?= htmlspecialchars($s['sistema']) ?>"><?= htmlspecialchars($s['sistema']) ?></option>
			<?php endforeach; ?>
		</select>
		
		<label>Tipo:</label>
		<select name="tipo" required>
			<option value="1">Protagonista</option>
			<option value="10">Sociedad Garou</option>
			<option value="5">Sociedad vampírica</option>
			<option value="6">Sociedad humana</option>
			<option value="11">Otro Fêra</option>
			<option value="12">Otros seres</option>
		</select>
		
		<label>Raza:</label>
		<select name="raza" required>
			<option value="0">-</option>
		</select>
		
		<label>Auspicio:</label>
		<select name="auspicio" required>
			<option value="0">-</option>
		</select>
		
		<label>Tribu:</label>
		<select name="tribu" required>
			<option value="0">-</option>
		</select>

		<label>Clan:</label>
		<select name="clan" required>
			<option value="0">-</option>
			<?php foreach ($clanes as $c): ?>
				<option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
			<?php endforeach; ?>
		</select>

		<label>Manada:</label>
		<select name="manada" required>
			<option value="0">-</option>
		</select>
		
		<label>Información (opcional):</label>
		<textarea name="infotext" rows="3"></textarea>

		<label>Notas:</label>
		<textarea name="notas" rows="1">Añadido desde la constelación de relaciones.</textarea>

		<label>Imagen (125x125 px, JPG o PNG):</label>
		<input type="file" name="img" accept="image/*">

		<button type="submit">Crear personaje</button>
	</form>

	<br />

	<?php include("footer.php"); ?>

		<script>
		// JavaScript para filtrado dinámico
		let razas = <?= json_encode($razas) ?>;
		let auspicios = <?= json_encode($auspicios) ?>;
		let tribus = <?= json_encode($tribus) ?>;
		let manadas = <?= json_encode($manadas) ?>;

		document.addEventListener('DOMContentLoaded', () => {

			function filterOptions(select, items, key, value) {
				select.innerHTML = '<option value="0">-</option>';
				items.filter(item => item[key] === value).forEach(item => {
					let option = new Option(item.name, item.id);
					select.add(option);
				});
			}

			document.querySelector('select[name="sistema"]').addEventListener('change', function() {
				let sistema = this.value;

				filterOptions(document.querySelector('select[name="raza"]'), razas, 'sistema', sistema);
				filterOptions(document.querySelector('select[name="auspicio"]'), auspicios, 'sistema', sistema);
				filterOptions(document.querySelector('select[name="tribu"]'), tribus, 'sistema', sistema);
			});

			document.querySelector('select[name="clan"]').addEventListener('change', function() {
				let clanId = this.value;

				let clanName = this.options[this.selectedIndex].text;
				filterOptions(document.querySelector('select[name="manada"]'), manadas, 'clan', clanName);
			});

		});
		</script>
	</body>
</html>


<?php


/*
		<label>Raza:</label>
		<select name="raza" required>
			<option value="0">-</option>
			<?php foreach ($razas as $r): ?>
				<option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['name']) ?> (<?= htmlspecialchars($r['sistema']) ?>)</option>
			<?php endforeach; ?>
		</select>
		
		<label>Auspicio:</label>
		<select name="auspicio" required>
			<option value="0">-</option>
			<?php foreach ($auspicios as $a): ?>
				<option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['name']) ?> (<?= htmlspecialchars($a['sistema']) ?>)</option>
			<?php endforeach; ?>
		</select>
		
		<label>Tribu:</label>
		<select name="tribu" required>
			<option value="0">-</option>
			<?php foreach ($tribus as $t): ?>
				<option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['name']) ?> (<?= htmlspecialchars($t['sistema']) ?>)</option>
			<?php endforeach; ?>
		</select>

		<label>Clan:</label>
		<select name="clan" required>
			<option value="0">-</option>
			<?php foreach ($clanes as $c): ?>
				<option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
			<?php endforeach; ?>
		</select>

		<label>Manada:</label>
		<select name="manada" required>
			<option value="0">-</option>
			<?php foreach ($manadas as $m): ?>
				<option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['clan']) ?>)</option>
			<?php endforeach; ?>
		</select>

*/ 

?>